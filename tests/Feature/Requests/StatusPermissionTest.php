<?php

use App\Actions\Requests\ChangeRequestStatus;
use App\Enums\RequestStatus;
use Laravel\Sanctum\Sanctum;

function statusUrl($request): string
{
    return "/api/v1/requests/{$request->request_number}/status";
}

it('lets the assigned PIC start work and send it to review', function () {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->ahmad);

    $this->postJson(statusUrl($request), ['to_status' => 'IN_PROGRESS'])->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');
    $this->postJson(statusUrl($request), ['to_status' => 'REVIEW'])->assertOk()->assertJsonPath('data.status', 'REVIEW');
});

it('lets the PIC see the request they were assigned', function () {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->ahmad);

    $this->getJson('/api/v1/requests?assigned_to_me=1')->assertOk()
        ->assertJsonPath('data.0.request_number', $request->request_number);
});

it('refuses a teammate who is not the PIC', function () {
    $c = cast();
    $request = assignedRequest($c); // PIC is Ahmad
    Sanctum::actingAs($c->budi);   // same team, can see it, but not his to move

    $this->getJson("/api/v1/requests/{$request->request_number}")->assertOk();
    $this->postJson(statusUrl($request), ['to_status' => 'IN_PROGRESS'])->assertForbidden();

    expect($request->fresh()->status)->toBe(RequestStatus::Assigned);
});

it('lets a team lead and an admin move work along, but not other teams\' leads', function () {
    $c = cast();
    $request = assignedRequest($c);

    Sanctum::actingAs($c->creativeLead);
    $this->postJson(statusUrl($request), ['to_status' => 'IN_PROGRESS'])->assertOk();

    Sanctum::actingAs($c->itLead);
    $this->postJson(statusUrl($request), ['to_status' => 'REVIEW'])->assertNotFound(); // cannot even see it

    Sanctum::actingAs($c->admin);
    $this->postJson(statusUrl($request), ['to_status' => 'REVIEW'])->assertOk();
});

it('keeps management read-only', function () {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->management);

    $this->getJson("/api/v1/requests/{$request->request_number}")->assertOk()
        ->assertJsonPath('data.permissions.assign', false)
        ->assertJsonPath('data.permissions.edit_details', false)
        ->assertJsonPath('data.permissions.edit_priority', false)
        ->assertJsonPath('data.allowed_transitions', []);

    $this->postJson(statusUrl($request), ['to_status' => 'CANCELLED', 'note' => 'x'])->assertForbidden();
    $this->postJson("/api/v1/requests/{$request->request_number}/assign", ['team_id' => $c->it->id, 'user_id' => $c->dewi->id])->assertForbidden();
    $this->patchJson("/api/v1/requests/{$request->request_number}", ['title' => 'changed'])->assertForbidden();

    expect($request->fresh()->status)->toBe(RequestStatus::Assigned);
});

describe('a requester withdrawing their own request', function () {
    it('may cancel until work starts', function (RequestStatus $state) {
        $c = cast();
        $request = makeRequest($c->requester, $c->category);
        if ($state !== RequestStatus::New) {
            $request = assignedRequest($c);
        }
        Sanctum::actingAs($c->requester);

        $this->postJson(statusUrl($request), ['to_status' => 'CANCELLED', 'note' => 'plans changed'])
            ->assertOk()->assertJsonPath('data.status', 'CANCELLED');
    })->with([RequestStatus::New, RequestStatus::Assigned]);

    it('may not cancel once the work is in progress', function () {
        $c = cast();
        $request = assignedRequest($c);
        app(ChangeRequestStatus::class)->handle($c->ahmad, $request, RequestStatus::InProgress);
        Sanctum::actingAs($c->requester);

        $this->postJson(statusUrl($request), ['to_status' => 'CANCELLED', 'note' => 'plans changed'])->assertForbidden();
    });

    it('may not push work through the workflow', function (string $to) {
        $c = cast();
        $request = assignedRequest($c);
        Sanctum::actingAs($c->requester);

        $this->postJson(statusUrl($request), ['to_status' => $to])->assertForbidden();
    })->with(['IN_PROGRESS', 'BLOCKED', 'TRIAGED']);

    it('may not cancel someone else\'s request', function () {
        $c = cast();
        $request = makeRequest($c->requester, $c->category);
        Sanctum::actingAs($c->otherRequester);

        $this->postJson(statusUrl($request), ['to_status' => 'CANCELLED', 'note' => 'x'])->assertNotFound();
    });
});

it('blocks a deactivated user immediately, even with a valid token', function () {
    $c = cast();
    $request = assignedRequest($c);
    Sanctum::actingAs($c->ahmad);
    $this->getJson('/api/v1/auth/me')->assertOk();

    $c->ahmad->forceFill(['is_active' => false])->save();

    $this->postJson(statusUrl($request), ['to_status' => 'IN_PROGRESS'])->assertForbidden();
    expect($request->fresh()->status)->toBe(RequestStatus::Assigned);
});
