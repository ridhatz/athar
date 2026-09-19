<?php

use App\Actions\Requests\ChangeRequestStatus;
use App\Enums\RequestStatus;
use App\Exceptions\ImmutableRecordException;
use App\Models\ActivityLog;
use App\Models\Comment;
use Laravel\Sanctum\Sanctum;

function commentsUrl(object $request): string
{
    return "/api/v1/requests/{$request->request_number}/comments";
}

it('lets the requester and the working side talk on a request', function (string $who) {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->{$who});

    $this->postJson(commentsUrl($request), ['body' => 'Mohon foto resmi pembicara.'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Mohon foto resmi pembicara.')
        ->assertJsonPath('data.is_internal', false)
        ->assertJsonPath('data.author.id', $c->{$who}->id);
})->with(['requester', 'ahmad', 'creativeLead', 'admin']);

it('keeps management read-only and outsiders out', function () {
    $c = cast();
    $request = assignedRequest($c);

    Sanctum::actingAs($c->management);
    $this->postJson(commentsUrl($request), ['body' => 'hi'])->assertForbidden();

    Sanctum::actingAs($c->otherRequester);
    $this->postJson(commentsUrl($request), ['body' => 'hi'])->assertNotFound();

    expect(Comment::query()->count())->toBe(0);
});

it('separates internal notes from what the requester can see', function () {
    $c = cast();
    $request = assignedRequest($c);

    Sanctum::actingAs($c->requester);
    $this->postJson(commentsUrl($request), ['body' => 'Please send the speaker photo.'])->assertCreated();

    Sanctum::actingAs($c->ahmad);
    $this->postJson(commentsUrl($request), ['body' => 'Needs about 2 hours once assets arrive.', 'is_internal' => true])
        ->assertCreated()->assertJsonPath('data.is_internal', true);

    $bodies = fn ($user) => (function () use ($user, $request) {
        Sanctum::actingAs($user);

        return collect(test()->getJson(commentsUrl($request))->assertOk()->json('data'))->pluck('body')->all();
    })();

    expect($bodies($c->requester))->toBe(['Please send the speaker photo.'])
        ->and($bodies($c->ahmad))->toHaveCount(2)
        ->and($bodies($c->management))->toHaveCount(2);
});

it('does not let a requester write an internal note', function () {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->requester);

    $this->postJson(commentsUrl($request), ['body' => 'sneaky', 'is_internal' => true])->assertForbidden();
});

it('logs that a comment was made without copying its words, and hides internal ones from the requester', function () {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->ahmad);
    $this->postJson(commentsUrl($request), ['body' => 'secret plan', 'is_internal' => true])->assertCreated();
    $this->postJson(commentsUrl($request), ['body' => 'hello'])->assertCreated();

    $log = ActivityLog::query()->where('action', 'COMMENT_ADDED')->orderBy('id')->get();
    expect($log)->toHaveCount(2)
        ->and($log->pluck('is_internal')->all())->toBe([true, false])
        ->and($log->pluck('note')->filter()->all())->toBe([]);

    Sanctum::actingAs($c->requester);
    $actions = collect($this->getJson("/api/v1/requests/{$request->request_number}/activity")->json('data'))->pluck('action');
    expect($actions->filter(fn ($a) => $a === 'COMMENT_ADDED'))->toHaveCount(1);
});

it('rejects an empty message and a finished request', function () {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->requester);

    $this->postJson(commentsUrl($request), ['body' => ''])->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->postJson(commentsUrl($request), ['body' => '   '])->assertUnprocessable();

    app(ChangeRequestStatus::class)->handle($c->admin, $request, RequestStatus::Cancelled, 'no longer needed');
    $this->postJson(commentsUrl($request), ['body' => 'too late'])->assertUnprocessable()->assertJsonValidationErrors('body');
});

it('never edits or deletes a comment', function () {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->requester);
    $this->postJson(commentsUrl($request), ['body' => 'first version'])->assertCreated();

    $comment = Comment::query()->sole();
    $comment->body = 'rewritten';

    expect(fn () => $comment->save())->toThrow(ImmutableRecordException::class);
    expect(fn () => $comment->delete())->toThrow(ImmutableRecordException::class);
});
