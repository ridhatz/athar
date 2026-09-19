<?php

use App\Actions\Requests\AssignRequest;
use App\Models\ServiceRequest;
use Laravel\Sanctum\Sanctum;

/**
 * Three requests that make visibility interesting:
 *   own      - filed by $requester, still unrouted (in the triage queue)
 *   creative - filed by $otherRequester, routed to Creative / Ahmad
 *   it       - filed by $otherRequester, routed to IT / Dewi
 */
function scenario(): object
{
    $c = cast();

    $c->own = makeRequest($c->requester, $c->category, ['title' => 'own']);
    $c->creativeReq = makeRequest($c->otherRequester, $c->category, ['title' => 'creative']);
    app(AssignRequest::class)->handle($c->admin, $c->creativeReq, ['team_id' => $c->creative->id, 'user_id' => $c->ahmad->id]);
    $c->itReq = makeRequest($c->otherRequester, $c->category, ['title' => 'it']);
    app(AssignRequest::class)->handle($c->admin, $c->itReq, ['team_id' => $c->it->id, 'user_id' => $c->dewi->id]);

    return $c;
}

function titlesFor($user): array
{
    Sanctum::actingAs($user);

    return collect(test()->getJson('/api/v1/requests?per_page=50')->assertOk()->json('data'))
        ->pluck('title')->sort()->values()->all();
}

it('shows each role exactly what it should see in the list', function (string $who, array $expected) {
    $c = scenario();

    expect(titlesFor($c->{$who}))->toBe($expected);
})->with([
    'admin sees everything' => ['admin', ['creative', 'it', 'own']],
    'management sees everything' => ['management', ['creative', 'it', 'own']],
    'requester sees only their own' => ['requester', ['own']],
    'other requester sees only theirs' => ['otherRequester', ['creative', 'it']],
    'creative lead: own team + the unrouted queue' => ['creativeLead', ['creative', 'own']],
    'it lead: own team + the unrouted queue' => ['itLead', ['it', 'own']],
    'staff sees what is assigned to their team' => ['ahmad', ['creative']],
    'teammate sees the team\'s work too' => ['budi', ['creative']],
    'staff in another team sees only theirs' => ['dewi', ['it']],
]);

it('answers 404, not 403, for a request the caller may not see', function (string $who, string $target) {
    $c = scenario();
    $request = $c->{$target};
    Sanctum::actingAs($c->{$who});

    $this->getJson("/api/v1/requests/{$request->request_number}")->assertNotFound();
    $this->getJson("/api/v1/requests/{$request->id}")->assertNotFound();
    $this->getJson("/api/v1/requests/{$request->request_number}/activity")->assertNotFound();
    $this->postJson("/api/v1/requests/{$request->request_number}/status", ['to_status' => 'CANCELLED', 'note' => 'x'])->assertNotFound();
    $this->postJson("/api/v1/requests/{$request->request_number}/assign", ['team_id' => $c->it->id])->assertNotFound();
    $this->patchJson("/api/v1/requests/{$request->request_number}", ['title' => 'hijacked'])->assertNotFound();

    expect($request->fresh()->title)->not->toBe('hijacked');
})->with([
    'requester → someone else\'s' => ['requester', 'creativeReq'],
    'other requester → the unrouted one' => ['otherRequester', 'own'],
    'creative staff → the IT one' => ['ahmad', 'itReq'],
    'creative staff → the unrouted one' => ['ahmad', 'own'],
    'it lead → the creative one' => ['itLead', 'creativeReq'],
    'creative lead → the IT one' => ['creativeLead', 'itReq'],
]);

it('answers 404 for a request that does not exist, the same as for a hidden one', function () {
    $c = scenario();
    Sanctum::actingAs($c->requester);

    $this->getJson('/api/v1/requests/ATH-424242')->assertNotFound();
    $this->getJson('/api/v1/requests/424242')->assertNotFound();
});

it('does not let a filter widen what the caller may see', function () {
    $c = scenario();
    Sanctum::actingAs($c->requester);

    foreach (['?team_id='.$c->it->id, '?category_id='.$c->category->id, '?q=it', '?mine=0', '?assigned_to_me=1'] as $query) {
        $titles = collect($this->getJson("/api/v1/requests{$query}")->assertOk()->json('data'))->pluck('title')->all();
        expect($titles)->each->toBe('own');
    }
});

it('does not show soft-deleted requests', function () {
    $c = scenario();
    $c->own->delete(); // archival, not removal: the row is still in the table

    expect(ServiceRequest::withTrashed()->count())->toBe(3)
        ->and(titlesFor($c->admin))->toBe(['creative', 'it']);

    Sanctum::actingAs($c->requester);
    $this->getJson("/api/v1/requests/{$c->own->request_number}")->assertNotFound();
});

it('filters, searches and paginates within the visible set', function () {
    $c = scenario();
    Sanctum::actingAs($c->admin);

    expect(collect($this->getJson('/api/v1/requests?status=ASSIGNED')->json('data'))->pluck('title')->sort()->values()->all())
        ->toBe(['creative', 'it'])
        ->and(collect($this->getJson('/api/v1/requests?status=NEW')->json('data'))->pluck('title')->all())->toBe(['own'])
        ->and(collect($this->getJson('/api/v1/requests?team_id='.$c->it->id)->json('data'))->pluck('title')->all())->toBe(['it'])
        ->and(collect($this->getJson('/api/v1/requests?q=ATH-000001')->json('data'))->pluck('request_number')->all())->toBe(['ATH-000001'])
        ->and($this->getJson('/api/v1/requests?per_page=2')->json('meta.per_page'))->toBe(2)
        ->and($this->getJson('/api/v1/requests?per_page=2')->json('meta.total'))->toBe(3);

    $this->getJson('/api/v1/requests?status=NOPE')->assertUnprocessable();
    $this->getJson('/api/v1/requests?per_page=500')->assertUnprocessable();
});

it('treats % and _ in a search as plain characters', function () {
    $c = cast();
    makeRequest($c->requester, $c->category, ['title' => '100% ready']);
    makeRequest($c->requester, $c->category, ['title' => 'nothing special']);
    Sanctum::actingAs($c->requester);

    expect(collect($this->getJson('/api/v1/requests?q=%25')->json('data'))->pluck('title')->all())->toBe(['100% ready']);
});

it('finds overdue open requests only', function () {
    $c = cast();
    $late = makeRequest($c->requester, $c->category, ['title' => 'late']);
    makeRequest($c->requester, $c->category, ['title' => 'on time']);
    $late->forceFill(['deadline' => now()->subDay()])->save();

    Sanctum::actingAs($c->requester);
    $titles = collect($this->getJson('/api/v1/requests?overdue=1')->json('data'));

    expect($titles->pluck('title')->all())->toBe(['late'])
        ->and($titles->first()['is_overdue'])->toBeTrue();

    $late->forceFill(['status' => 'COMPLETED'])->save();
    expect($this->getJson('/api/v1/requests?overdue=1')->json('data'))->toBe([]);
});
