<?php

use App\Enums\ActivityAction;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\RoleCode;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\RequestCategory;
use App\Models\ServiceRequest;
use Laravel\Sanctum\Sanctum;

it('lets a requester create a request that starts NEW with a generated number', function () {
    $c = cast();
    Sanctum::actingAs($c->requester);

    $response = $this->postJson('/api/v1/requests', requestPayload($c->category))
        ->assertCreated()
        ->assertJsonPath('data.request_number', 'ATH-000001')
        ->assertJsonPath('data.status', 'NEW')
        ->assertJsonPath('data.priority', 'NORMAL')
        ->assertJsonPath('data.requester.id', $c->requester->id)
        ->assertJsonPath('data.department.id', $c->department->id)
        ->assertJsonPath('data.assigned_team', null)
        ->assertJsonPath('data.assigned_user', null);

    expect($response->headers->get('Location'))->toEndWith('/api/v1/requests/ATH-000001');

    $stored = ServiceRequest::query()->where('request_number', 'ATH-000001')->firstOrFail();
    expect($stored->requester_id)->toBe($c->requester->id)
        ->and($stored->status)->toBe(RequestStatus::New)
        ->and($stored->title)->toBe('Poster seminar nasional');
});

it('records who created it in the activity log', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    $log = ActivityLog::query()->where('request_id', $request->id)->sole();

    expect($log->action)->toBe(ActivityAction::RequestCreated)
        ->and($log->actor_id)->toBe($c->requester->id)
        ->and($log->new_value)->toBe('ATH-000001')
        ->and($log->is_internal)->toBeFalse();
});

it('accepts a priority chosen by the requester', function () {
    $c = cast();
    Sanctum::actingAs($c->requester);

    $this->postJson('/api/v1/requests', requestPayload($c->category, ['priority' => 'CRITICAL']))
        ->assertCreated()
        ->assertJsonPath('data.priority', RequestPriority::Critical->value);
});

it('rejects incomplete or invalid input', function (array $overrides, string $field) {
    $c = cast();
    Sanctum::actingAs($c->requester);

    $this->postJson('/api/v1/requests', array_merge(requestPayload($c->category), $overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(ServiceRequest::query()->count())->toBe(0);
})->with([
    'no title' => [['title' => ''], 'title'],
    'no description' => [['description' => ''], 'description'],
    'no deadline' => [['deadline' => null], 'deadline'],
    'deadline in the past' => [['deadline' => '2020-01-01T00:00:00Z'], 'deadline'],
    'unknown category' => [['category_id' => 99999], 'category_id'],
    'bad priority' => [['priority' => 'URGENT!!'], 'priority'],
    'title too long' => [['title' => str_repeat('a', 201)], 'title'],
]);

it('rejects an inactive category', function () {
    $c = cast();
    $retired = RequestCategory::factory()->inactive()->create();
    Sanctum::actingAs($c->requester);

    $this->postJson('/api/v1/requests', requestPayload($retired))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id');
});

it('asks for a department when the requester has none on record', function () {
    $c = cast();
    $homeless = userWith(RoleCode::Requester);
    Sanctum::actingAs($homeless);

    $this->postJson('/api/v1/requests', requestPayload($c->category))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('department_id');

    $this->postJson('/api/v1/requests', requestPayload($c->category, ['department_id' => $c->department->id]))
        ->assertCreated();
});

it('ignores a requester trying to file under someone elses department', function () {
    $c = cast();
    $other = Department::factory()->create();
    Sanctum::actingAs($c->requester);

    $this->postJson('/api/v1/requests', requestPayload($c->category, ['department_id' => $other->id]))
        ->assertCreated()
        ->assertJsonPath('data.department.id', $c->department->id);
});

it('lets staff and team leads file requests too, but not observers', function () {
    $c = cast();

    foreach ([$c->ahmad, $c->creativeLead, $c->admin] as $person) {
        Sanctum::actingAs($person);
        $this->postJson('/api/v1/requests', requestPayload($c->category, ['department_id' => $c->department->id]))
            ->assertCreated();
    }

    Sanctum::actingAs($c->management);
    $this->postJson('/api/v1/requests', requestPayload($c->category, ['department_id' => $c->department->id]))
        ->assertForbidden();
});

it('requires authentication', function () {
    $c = cast();

    $this->postJson('/api/v1/requests', requestPayload($c->category))->assertUnauthorized();
});
