<?php

use App\Actions\Requests\AssignRequest;
use App\Actions\Requests\ChangeRequestStatus;
use App\Actions\Requests\DecideReview;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\ReviewDecision;
use App\Enums\RoleCode;
use App\Models\ServiceRequest;
use App\Models\Team;
use App\Models\User;
use App\Notifications\RequestNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

/** type of each notification a user has received, oldest first */
function received(User $user): array
{
    return $user->notifications()->oldest()->get()->map(fn ($n) => $n->data['type'])->all();
}

it('tells a new PIC about the assignment, and the requester about the progress, but not the person who did it', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    app(AssignRequest::class)->handle($c->admin, $request, ['team_id' => $c->creative->id, 'user_id' => $c->ahmad->id]);

    expect(received($c->ahmad))->toBe(['ASSIGNED_PIC'])
        ->and(received($c->requester))->toBe(['STATUS_CHANGED'])   // TRIAGED is an internal step and is skipped
        ->and(received($c->admin))->toBe([])                       // never told about their own action
        ->and(received($c->budi))->toBe([]);

    $note = $c->requester->notifications()->first()->data;
    expect($note['request_number'])->toBe('ATH-000001')
        ->and($note['params']['from'])->toBe('TRIAGED')
        ->and($note['params']['to'])->toBe('ASSIGNED')
        ->and($note['params']['actor'])->toBe($c->admin->name);
});

it('tells the team leads when a request lands on their team with nobody named yet', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    app(AssignRequest::class)->handle($c->admin, $request, ['team_id' => $c->creative->id]);

    expect(received($c->creativeLead))->toBe(['ASSIGNED_TEAM'])
        ->and(received($c->itLead))->toBe([])   // another team's lead
        ->and(received($c->ahmad))->toBe([]);
});

it('keeps everyone informed through work, review, revision and completion', function () {
    $c = cast();
    $request = assignedRequest($c);
    $change = app(ChangeRequestStatus::class);
    foreach ([$c->ahmad, $c->requester, $c->creativeLead] as $u) {
        $u->notifications()->delete();
    }

    $change->handle($c->ahmad, $request, RequestStatus::InProgress);
    expect(received($c->requester))->toBe(['STATUS_CHANGED'])
        ->and(received($c->ahmad))->toBe([]);   // his own action

    $change->handle($c->ahmad, $request, RequestStatus::Review);
    expect(received($c->creativeLead))->toBe(['REVIEW_REQUESTED'])
        ->and(received($c->requester))->toBe(['STATUS_CHANGED', 'STATUS_CHANGED']);

    app(DecideReview::class)->handle($c->creativeLead, $request, ReviewDecision::RevisionRequested, 'Bigger logo');
    expect(received($c->ahmad))->toBe(['REVISION_REQUESTED']);

    $change->handle($c->ahmad, $request, RequestStatus::Review);
    app(DecideReview::class)->handle($c->creativeLead, $request, ReviewDecision::Approved);

    // Completion goes to the requester and the PIC, not to the reviewer who just did it.
    // (Notifications made in the same second have no defined order, so check membership, not position.)
    expect(received($c->requester))->toContain('COMPLETED')
        ->and(received($c->ahmad))->toContain('COMPLETED')
        ->and(received($c->creativeLead))->not->toContain('COMPLETED');
});

it('alerts the requester, the PIC and the team leads when work is blocked', function () {
    $c = cast();
    $request = assignedRequest($c);
    app(ChangeRequestStatus::class)->handle($c->ahmad, $request, RequestStatus::InProgress);
    foreach ([$c->requester, $c->creativeLead, $c->admin] as $u) {
        $u->notifications()->delete();
    }

    app(ChangeRequestStatus::class)->handle($c->ahmad, $request, RequestStatus::Blocked, 'waiting for the photo');

    expect(received($c->requester))->toBe(['STATUS_CHANGED'])
        ->and(received($c->creativeLead))->toBe(['STATUS_CHANGED']);
});

it('sends the review request to an admin when the team has no lead', function () {
    $c = cast();
    $leadless = Team::factory()->create(['name' => 'Digital']);
    $pic = User::factory()->withRole(RoleCode::Staff)->inTeam($leadless)->create();
    $request = makeRequest($c->requester, $c->category);
    app(AssignRequest::class)->handle($c->admin, $request, ['team_id' => $leadless->id, 'user_id' => $pic->id]);
    app(ChangeRequestStatus::class)->handle($pic, $request, RequestStatus::InProgress);
    $c->admin->notifications()->delete();

    app(ChangeRequestStatus::class)->handle($pic, $request, RequestStatus::Review);

    expect(received($c->admin))->toBe(['REVIEW_REQUESTED']);
});

it('does not notify deactivated people', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);
    $c->ahmad->forceFill(['is_active' => false])->save();
    $c->budi->joinTeam($c->creative);

    // Ahmad cannot be assigned while inactive, so use Budi and deactivate the requester instead.
    $c->requester->forceFill(['is_active' => false])->save();
    app(AssignRequest::class)->handle($c->admin, $request, ['team_id' => $c->creative->id, 'user_id' => $c->budi->id]);

    expect(received($c->requester))->toBe([])->and(received($c->budi))->toBe(['ASSIGNED_PIC']);
});

it('sends through every configured channel', function () {
    Notification::fake();
    config(['athar.notifications.channels' => ['database', 'mail']]);
    $c = cast();

    app(AssignRequest::class)->handle($c->admin, makeRequest($c->requester, $c->category), ['team_id' => $c->creative->id, 'user_id' => $c->ahmad->id]);

    Notification::assertSentTo($c->ahmad, RequestNotification::class, fn ($n, $channels) => $channels === ['database', 'mail']);
});

it('writes the email in the recipients own language', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category, ['title' => 'Poster seminar']);
    $params = ['actor' => 'Admin', 'from' => 'IN_PROGRESS', 'to' => 'REVIEW'];

    $indonesian = User::factory()->create(['locale' => 'id']);
    $english = User::factory()->create(['locale' => 'en']);

    $id = (new RequestNotification(NotificationType::StatusChanged, $request, $params))->toMail($indonesian);
    $en = (new RequestNotification(NotificationType::StatusChanged, $request, $params))->toMail($english);

    expect($id->subject)->toBe('[ATH-000001] Admin mengubah ATH-000001 dari Dikerjakan menjadi Direview')
        ->and($en->subject)->toBe('[ATH-000001] Admin changed ATH-000001 from In progress to In review')
        ->and($id->actionUrl)->toEndWith('/requests/ATH-000001')
        ->and(app()->getLocale())->toBe('en');   // the caller's language is restored afterwards
});

it('never lets a delivery problem undo or block the work', function () {
    config(['athar.notifications.channels' => ['a-channel-that-does-not-exist']]);
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    $assigned = app(AssignRequest::class)->handle($c->admin, $request, ['team_id' => $c->creative->id, 'user_id' => $c->ahmad->id]);

    expect($assigned->status)->toBe(RequestStatus::Assigned)->and($assigned->assigned_user_id)->toBe($c->ahmad->id);
});

it('does not notify when the action itself fails', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    try {
        app(AssignRequest::class)->handle($c->admin, $request, ['team_id' => $c->creative->id, 'user_id' => $c->dewi->id]); // Dewi is in IT
    } catch (ValidationException) {
    }

    expect(received($c->dewi))->toBe([])->and(received($c->requester))->toBe([]);
});

describe('deadline reminders', function () {
    function dueIn(object $c, string $when, ?User $pic = null, array $extra = []): ServiceRequest
    {
        $request = assignedRequest($c, pic: $pic);
        $request->forceFill(['deadline' => now()->modify($when)] + $extra)->save();

        return $request;
    }

    it('reminds the PIC once when a deadline is near', function () {
        $c = cast();
        dueIn($c, '+3 hours');
        $c->ahmad->notifications()->delete();

        Artisan::call('athar:notify-deadlines');
        Artisan::call('athar:notify-deadlines');   // running again must not nag

        expect(received($c->ahmad))->toBe(['DEADLINE_APPROACHING'])
            ->and(received($c->requester))->not->toContain('DEADLINE_APPROACHING');
    });

    it('says overdue, once per day', function () {
        $c = cast();
        dueIn($c, '-2 hours');
        $c->ahmad->notifications()->delete();

        Artisan::call('athar:notify-deadlines');
        Artisan::call('athar:notify-deadlines');
        expect(received($c->ahmad))->toBe(['OVERDUE']);

        $this->travel(1)->days();
        Artisan::call('athar:notify-deadlines');
        expect(received($c->ahmad))->toBe(['OVERDUE', 'OVERDUE']);
    });

    it('ignores far-off, completed and cancelled requests', function () {
        $c = cast();
        dueIn($c, '+10 days');
        dueIn($c, '-1 day', extra: ['status' => RequestStatus::Completed]);
        dueIn($c, '-1 day', extra: ['status' => RequestStatus::Cancelled]);
        $c->ahmad->notifications()->delete();

        Artisan::call('athar:notify-deadlines');

        expect(received($c->ahmad))->toBe([]);
    });

    it('falls back to the team leads, then to admins, when nobody is assigned', function () {
        $c = cast();

        $teamOnly = makeRequest($c->requester, $c->category);
        app(AssignRequest::class)->handle($c->admin, $teamOnly, ['team_id' => $c->creative->id]);
        $teamOnly->forceFill(['deadline' => now()->addHour()])->save();

        $unrouted = makeRequest($c->requester, $c->category);
        $unrouted->forceFill(['deadline' => now()->addHour()])->save();

        foreach ([$c->creativeLead, $c->admin, $c->itLead] as $u) {
            $u->notifications()->delete();
        }
        Artisan::call('athar:notify-deadlines');

        expect(received($c->creativeLead))->toBe(['DEADLINE_APPROACHING'])
            ->and(received($c->admin))->toBe(['DEADLINE_APPROACHING'])
            ->and(received($c->itLead))->toBe([]);
    });
});

describe('the inbox', function () {
    it('lists only my own notifications with the unread count', function () {
        $c = cast();
        assignedRequest($c);

        Sanctum::actingAs($c->ahmad);
        $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'ASSIGNED_PIC')
            ->assertJsonPath('data.0.request_number', 'ATH-000001')
            ->assertJsonPath('data.0.read_at', null);

        Sanctum::actingAs($c->budi);
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('unread_count', 0);
    });

    it('marks read, one or all, and never someone elses', function () {
        $c = cast();
        assignedRequest($c);
        $mine = $c->ahmad->notifications()->first();

        Sanctum::actingAs($c->budi);
        $this->postJson("/api/v1/notifications/{$mine->id}/read")->assertNotFound();
        expect($mine->fresh()->read_at)->toBeNull();

        Sanctum::actingAs($c->ahmad);
        $this->postJson("/api/v1/notifications/{$mine->id}/read")->assertOk();
        expect($mine->fresh()->read_at)->not->toBeNull();

        $c->requester->notifications()->first()->markAsUnread();
        Sanctum::actingAs($c->requester);
        $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
        expect($c->requester->unreadNotifications()->count())->toBe(0);
    });

    it('opens the request from the bell and marks it read', function () {
        $c = cast();
        assignedRequest($c);
        $note = $c->ahmad->notifications()->first();

        $this->actingAs($c->ahmad)->post("/notifications/{$note->id}/open")->assertRedirect('/requests/ATH-000001');

        expect($note->fresh()->read_at)->not->toBeNull();
    });

    it('shows the bell data on every page', function () {
        $c = cast();
        assignedRequest($c);

        $this->actingAs($c->ahmad)->get('/requests')->assertInertia(fn ($page) => $page
            ->where('notifications.unread', 1)
            ->where('notifications.recent.0.type', 'ASSIGNED_PIC')
            ->where('notifications.recent.0.params.actor', $c->admin->name)
        );
    });
});
