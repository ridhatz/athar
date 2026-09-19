<?php

use App\Actions\Requests\AddAttachment;
use App\Enums\AttachmentKind;
use App\Exceptions\ImmutableRecordException;
use App\Models\ActivityLog;
use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => Storage::fake('local'));

function attachUrl(object $request): string
{
    return "/api/v1/requests/{$request->request_number}/attachments";
}

function pdf(string $name = 'brief.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
}

it('stores an allowed file privately and records who, what, size and type', function () {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->requester);

    $response = $this->postJson(attachUrl($request), ['file' => pdf(), 'kind' => 'BRIEF'])
        ->assertCreated()
        ->assertJsonPath('data.original_name', 'brief.pdf')
        ->assertJsonPath('data.kind', 'BRIEF')
        ->assertJsonPath('data.mime_type', 'application/pdf')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.uploader.id', $c->requester->id);

    $stored = Attachment::query()->sole();
    Storage::disk('local')->assertExists($stored->stored_path);

    // The stored path is generated: it carries nothing the uploader typed.
    expect($stored->stored_path)->toMatch('#^attachments/\d+/[0-9a-f-]{36}\.pdf$#')
        ->and($stored->sha256)->toHaveLength(64)
        ->and($response->json('data.download_url'))->toContain("/attachments/{$stored->id}/download");

    $log = ActivityLog::query()->where('action', 'FILE_UPLOADED')->sole();
    expect($log->new_value)->toBe('brief.pdf (v1)')->and($log->actor_id)->toBe($c->requester->id);
});

it('never replaces a file: the same name becomes the next version', function () {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->ahmad);

    foreach (range(1, 3) as $i) {
        $this->postJson(attachUrl($request), ['file' => UploadedFile::fake()->image('poster.png'), 'kind' => 'DRAFT'])->assertCreated();
    }

    $rows = Attachment::query()->orderBy('id')->get();
    expect($rows->pluck('version')->all())->toBe([1, 2, 3])
        ->and($rows->pluck('stored_path')->unique())->toHaveCount(3);

    foreach ($rows as $row) {
        Storage::disk('local')->assertExists($row->stored_path); // every version is still there
    }
});

it('logs a deliverable as its own event', function () {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->ahmad);

    $this->postJson(attachUrl($request), ['file' => UploadedFile::fake()->image('final.png'), 'kind' => 'DELIVERABLE'])->assertCreated();

    expect(ActivityLog::query()->where('action', 'DELIVERABLE_UPLOADED')->value('new_value'))->toBe('final.png (v1)');
});

describe('what is refused', function () {
    it('rejects a script renamed to an allowed extension', function () {
        $c = cast();
        $request = assignedRequest($c);
        Sanctum::actingAs($c->requester);

        $evil = UploadedFile::fake()->createWithContent('photo.png', '<?php system($_GET["c"]); ?>');

        $this->postJson(attachUrl($request), ['file' => $evil])->assertUnprocessable()->assertJsonValidationErrors('file');
        expect(Attachment::query()->count())->toBe(0);
        expect(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('rejects executable, script and svg extensions', function (string $name) {
        $c = cast();
        $request = assignedRequest($c);
        Sanctum::actingAs($c->requester);

        $this->postJson(attachUrl($request), ['file' => UploadedFile::fake()->createWithContent($name, 'hello')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    })->with(['shell.php', 'run.exe', 'page.html', 'vector.svg', 'script.js', 'noextension', 'archive.php.txt.sh']);

    it('rejects a file over the size limit', function () {
        config(['athar.attachments.max_kb' => 1]);
        $c = cast();
        $request = assignedRequest($c);
        Sanctum::actingAs($c->requester);

        $this->postJson(attachUrl($request), ['file' => UploadedFile::fake()->create('big.pdf', 50, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    });

    it('requires a file', function () {
        $c = cast();
        $request = assignedRequest($c);
        Sanctum::actingAs($c->requester);

        $this->postJson(attachUrl($request), [])->assertUnprocessable()->assertJsonValidationErrors('file');
    });

    it('cleans hostile file names', function () {
        $c = cast();
        $request = assignedRequest($c);

        $attachment = app(AddAttachment::class)->handle($c->requester, $request, UploadedFile::fake()->image('..\\..\\etc/passwd\\evil.png'));

        expect($attachment->original_name)->toBe('evil.png')->and($attachment->stored_path)->not->toContain('evil');
    });
});

describe('who may attach', function () {
    it('lets the requester, PIC and managers attach; not teammates, other requesters or management', function () {
        $c = cast();
        $request = assignedRequest($c);

        foreach (['requester', 'ahmad', 'creativeLead', 'admin'] as $who) {
            Sanctum::actingAs($c->{$who});
            $this->postJson(attachUrl($request), ['file' => pdf("from-{$who}.pdf")])->assertCreated();
        }

        Sanctum::actingAs($c->budi);       // same team, not the PIC
        $this->postJson(attachUrl($request), ['file' => pdf('x.pdf')])->assertForbidden();
        Sanctum::actingAs($c->management);
        $this->postJson(attachUrl($request), ['file' => pdf('x.pdf')])->assertForbidden();
        Sanctum::actingAs($c->otherRequester);
        $this->postJson(attachUrl($request), ['file' => pdf('x.pdf')])->assertNotFound();
    });

    it('keeps drafts and deliverables for the working side', function () {
        $c = cast();
        $request = assignedRequest($c);
        Sanctum::actingAs($c->requester);

        foreach (['DRAFT', 'REVISION', 'DELIVERABLE'] as $kind) {
            $this->postJson(attachUrl($request), ['file' => pdf(), 'kind' => $kind])->assertForbidden();
        }

        expect(Attachment::query()->count())->toBe(0);
    });

    it('keeps internal files for the working side', function () {
        $c = cast();
        $request = assignedRequest($c);

        Sanctum::actingAs($c->requester);
        $this->postJson(attachUrl($request), ['file' => pdf(), 'is_internal' => true])->assertForbidden();

        Sanctum::actingAs($c->ahmad);
        $this->postJson(attachUrl($request), ['file' => pdf('notes.pdf'), 'is_internal' => true])->assertCreated();
    });
});

describe('reading files', function () {
    function upload(object $c, $request, $user, bool $internal = false): Attachment
    {
        return app(AddAttachment::class)->handle($user, $request, pdf($internal ? 'internal.pdf' : 'public.pdf'), AttachmentKind::Other, $internal);
    }

    it('downloads as an attachment, never inline, with sniffing disabled', function () {
        $c = cast();
        $request = assignedRequest($c);
        $file = upload($c, $request, $c->requester);
        Sanctum::actingAs($c->ahmad);

        $response = $this->get("/api/v1/requests/{$request->request_number}/attachments/{$file->id}/download")->assertOk();

        expect($response->headers->get('content-disposition'))->toContain('attachment')->toContain('public.pdf')
            ->and($response->headers->get('x-content-type-options'))->toBe('nosniff')
            ->and($response->headers->get('content-type'))->toBe('application/octet-stream');
    });

    it('lists and serves internal files only to the working side', function () {
        $c = cast();
        $request = assignedRequest($c);
        $internal = upload($c, $request, $c->ahmad, internal: true);

        Sanctum::actingAs($c->requester);
        $this->getJson(attachUrl($request))->assertOk()->assertJsonCount(0, 'data');
        $this->get("/api/v1/requests/{$request->request_number}/attachments/{$internal->id}/download")->assertNotFound();

        Sanctum::actingAs($c->creativeLead);
        $this->getJson(attachUrl($request))->assertOk()->assertJsonCount(1, 'data');
        $this->get("/api/v1/requests/{$request->request_number}/attachments/{$internal->id}/download")->assertOk();
    });

    it('will not serve a file through someone elses request', function () {
        $c = cast();
        $mine = assignedRequest($c);
        $theirs = makeRequest($c->otherRequester, $c->category);
        $file = upload($c, $theirs, $c->otherRequester);

        Sanctum::actingAs($c->requester);
        $this->get("/api/v1/requests/{$theirs->request_number}/attachments/{$file->id}/download")->assertNotFound();
        // ...nor by borrowing the id under a request they can see.
        $this->get("/api/v1/requests/{$mine->request_number}/attachments/{$file->id}/download")->assertNotFound();
    });

    it('requires signing in', function () {
        $c = cast();
        $request = assignedRequest($c);
        $file = upload($c, $request, $c->requester);

        $this->getJson("/api/v1/requests/{$request->request_number}/attachments/{$file->id}/download")->assertUnauthorized();
    });

    it('is never reachable by a public URL', function () {
        $c = cast();
        $file = upload($c, assignedRequest($c), $c->requester);

        expect(Storage::disk('local')->exists($file->stored_path))->toBeTrue()
            ->and(file_exists(public_path($file->stored_path)))->toBeFalse();
    });
});

it('never modifies or deletes an attachment record', function () {
    $c = cast();
    $file = app(AddAttachment::class)->handle($c->requester, assignedRequest($c), pdf());

    $file->original_name = 'renamed.pdf';
    expect(fn () => $file->save())->toThrow(ImmutableRecordException::class);
    expect(fn () => $file->delete())->toThrow(ImmutableRecordException::class);
});

it('accepts a file together with a comment', function () {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->requester);

    $this->postJson("/api/v1/requests/{$request->request_number}/comments", [
        'body' => 'Here is the logo',
        'file' => UploadedFile::fake()->image('logo.png'),
        'kind' => 'LOGO',
    ])->assertCreated()->assertJsonPath('data.attachments.0.original_name', 'logo.png')
        ->assertJsonPath('data.attachments.0.kind', 'LOGO');

    expect(Attachment::query()->sole()->comment_id)->not->toBeNull();
});
