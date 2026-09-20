<?php

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\RequestCategory;
use App\Models\ServiceRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => Storage::fake('local'));

function realPdf(string $name = 'brief.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n");
}

it('opens a ticket with a PDF attached', function () {
    $c = cast();
    Sanctum::actingAs($c->requester);

    $this->postJson('/api/v1/requests', requestPayload($c->category, [
        'files' => [realPdf('brief-seminar.pdf'), UploadedFile::fake()->image('logo.png')],
    ]))->assertCreated()->assertJsonPath('data.request_number', 'ATH-000001');

    $files = Attachment::query()->orderBy('id')->get();
    expect($files->pluck('original_name')->all())->toBe(['brief-seminar.pdf', 'logo.png'])
        ->and($files->pluck('kind.value')->unique()->all())->toBe(['BRIEF'])
        ->and($files->pluck('request_id')->unique()->all())->toBe([ServiceRequest::query()->value('id')])
        ->and($files->pluck('uploader_id')->unique()->all())->toBe([$c->requester->id]);

    foreach ($files as $file) {
        Storage::disk('local')->assertExists($file->stored_path);
    }

    expect(ActivityLog::query()->where('action', 'FILE_UPLOADED')->orderBy('id')->pluck('new_value')->all())
        ->toBe(['brief-seminar.pdf (v1)', 'logo.png (v1)']);
});

it('works for every kind of request, not just one category', function () {
    $c = cast();
    Sanctum::actingAs($c->requester);

    foreach (['it-support', 'web-development', 'documentation', 'video-multimedia'] as $slug) {
        $category = RequestCategory::factory()->create(['slug' => $slug]);

        $this->postJson('/api/v1/requests', requestPayload($category, ['files' => [realPdf()]]))->assertCreated();
    }

    expect(Attachment::query()->count())->toBe(4);
});

it('still opens a ticket with no files', function () {
    $c = cast();
    Sanctum::actingAs($c->requester);

    $this->postJson('/api/v1/requests', requestPayload($c->category))->assertCreated();

    expect(Attachment::query()->count())->toBe(0);
});

it('lets the requester download what they attached, and the team see it', function () {
    $c = cast();
    Sanctum::actingAs($c->requester);
    $this->postJson('/api/v1/requests', requestPayload($c->category, ['files' => [realPdf('brief.pdf')]]))->assertCreated();
    $file = Attachment::query()->sole();

    $this->get("/api/v1/requests/ATH-000001/attachments/{$file->id}/download")->assertOk();

    Sanctum::actingAs($c->admin);
    $this->getJson('/api/v1/requests/ATH-000001/attachments')->assertOk()->assertJsonPath('data.0.original_name', 'brief.pdf');
});

it('refuses the whole ticket when one file is bad, keeping no request, file or number', function () {
    $c = cast();
    Sanctum::actingAs($c->requester);

    $this->postJson('/api/v1/requests', requestPayload($c->category, [
        'files' => [realPdf('good.pdf'), UploadedFile::fake()->createWithContent('photo.png', '<?php system($_GET["c"]); ?>')],
    ]))->assertUnprocessable()->assertJsonValidationErrors('files');

    expect(ServiceRequest::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);

    // The number was not burned by the failed attempt.
    $this->postJson('/api/v1/requests', requestPayload($c->category))->assertCreated()->assertJsonPath('data.request_number', 'ATH-000001');
});

it('names the offending file in the error', function () {
    $c = cast();
    Sanctum::actingAs($c->requester);

    $response = $this->postJson('/api/v1/requests', requestPayload($c->category, [
        'files' => [UploadedFile::fake()->createWithContent('virus.exe', 'MZ')],
    ]))->assertUnprocessable();

    expect($response->json('errors.files.0'))->toContain('virus.exe');
});

it('limits how many files can come with a ticket, and their size', function () {
    $c = cast();
    Sanctum::actingAs($c->requester);

    $six = collect(range(1, 6))->map(fn ($i) => realPdf("f{$i}.pdf"))->all();
    $this->postJson('/api/v1/requests', requestPayload($c->category, ['files' => $six]))
        ->assertUnprocessable()->assertJsonValidationErrors('files');

    config(['athar.attachments.max_kb' => 1]);
    $this->postJson('/api/v1/requests', requestPayload($c->category, ['files' => [UploadedFile::fake()->create('big.pdf', 50, 'application/pdf')]]))
        ->assertUnprocessable();

    expect(ServiceRequest::query()->count())->toBe(0);
});

it('takes the files from the web form too', function () {
    $c = cast();

    $this->actingAs($c->requester)
        ->post('/requests', requestPayload($c->category, ['files' => [realPdf('form-brief.pdf')]]))
        ->assertRedirect('/requests/ATH-000001');

    expect(Attachment::query()->sole()->original_name)->toBe('form-brief.pdf');
});
