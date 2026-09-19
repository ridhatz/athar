<?php

use App\Models\ServiceRequest;
use Inertia\Testing\AssertableInertia as Assert;

it('sends guests to the login screen', function () {
    $this->get('/requests')->assertRedirect('/login');
    $this->get('/')->assertRedirect('/login');
});

it('sends a signed-in user from the front door to the dashboard', function () {
    $c = cast();

    $this->actingAs($c->requester)->get('/')->assertRedirect('/dashboard');
    $this->actingAs($c->requester)->get('/dashboard')->assertOk();
});

it('lists the requests the user may see, in the shape the React page expects', function () {
    $c = cast();
    makeRequest($c->requester, $c->category, ['title' => 'mine']);
    makeRequest($c->otherRequester, $c->category, ['title' => 'not mine']);

    $this->actingAs($c->requester)->get('/requests')->assertInertia(fn (Assert $page) => $page
        ->component('requests/index')
        ->has('requests.data', 1)
        ->where('requests.data.0.title', 'mine')
        ->where('requests.data.0.request_number', 'ATH-000001')
        ->has('requests.links')
        ->has('requests.meta.total')
        ->where('canCreate', true)
        ->has('categories', 1)
        ->has('translations')
        ->where('locale', 'en')
        ->where('auth.roles', ['REQUESTER'])
    );
});

it('shows a request with what the viewer may do, by request number', function () {
    $c = cast();
    $request = assignedRequest($c);

    $this->actingAs($c->ahmad)->get("/requests/{$request->request_number}")->assertInertia(fn (Assert $page) => $page
        ->component('requests/show')
        ->where('request.data.request_number', 'ATH-000001')
        ->where('request.data.status', 'ASSIGNED')
        ->where('request.data.permissions.assign', false)
        ->where('request.data.allowed_transitions.0.status', 'IN_PROGRESS')
        ->has('activity.data', 5)
        ->has('assignments.data', 1)
        ->where('assignables', null)
    );
});

it('gives the assignment panel data only to those who may assign', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    $this->actingAs($c->admin)->get("/requests/{$request->request_number}")->assertInertia(fn (Assert $page) => $page
        ->where('request.data.permissions.assign', true)
        ->has('assignables.teams', 2)
        ->has('assignables.users')
    );

    $this->actingAs($c->requester)->get("/requests/{$request->request_number}")->assertInertia(fn (Assert $page) => $page
        ->where('assignables', null)
        ->where('assignments', [])
    );
});

it('returns 404 for a request the viewer may not see', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    $this->actingAs($c->otherRequester)->get("/requests/{$request->request_number}")->assertNotFound();
});

it('creates a request from the web form and lands on it with a toast', function () {
    $c = cast();

    $this->actingAs($c->requester)
        ->post('/requests', requestPayload($c->category))
        ->assertRedirect('/requests/ATH-000001');

    expect(ServiceRequest::query()->where('request_number', 'ATH-000001')->exists())->toBeTrue();
});

it('returns validation errors to the form', function () {
    $c = cast();

    $this->actingAs($c->requester)
        ->from('/requests/create')
        ->post('/requests', requestPayload($c->category, ['title' => '', 'deadline' => '2020-01-01T00:00:00Z']))
        ->assertRedirect('/requests/create')
        ->assertSessionHasErrors(['title', 'deadline']);
});

it('runs assign and status changes through the web routes as well', function () {
    $c = cast();
    $request = makeRequest($c->requester, $c->category);

    $this->actingAs($c->admin)->from("/requests/{$request->request_number}")
        ->post("/requests/{$request->request_number}/assign", ['team_id' => $c->creative->id, 'user_id' => $c->ahmad->id])
        ->assertRedirect("/requests/{$request->request_number}");

    $this->actingAs($c->ahmad)
        ->post("/requests/{$request->request_number}/status", ['to_status' => 'IN_PROGRESS'])
        ->assertRedirect();

    expect($request->fresh()->status->value)->toBe('IN_PROGRESS');
});

it('shows domain rule violations as form errors', function () {
    $c = cast();
    $request = assignedRequest($c);

    $this->actingAs($c->admin)->from("/requests/{$request->request_number}")
        ->post("/requests/{$request->request_number}/status", ['to_status' => 'BLOCKED'])
        ->assertSessionHasErrors('note');
});

it('renders in the users saved language and can switch it', function () {
    $c = cast();

    $this->actingAs($c->requester)->post('/locale', ['locale' => 'id'])->assertRedirect();
    expect($c->requester->fresh()->locale)->toBe('id');

    $this->actingAs($c->requester->fresh())->get('/requests')->assertInertia(fn (Assert $page) => $page
        ->where('locale', 'id')
        ->where('translations', fn ($t) => $t['status.NEW'] === 'Baru' && $t['requests.title'] === 'Permintaan')
    );

    $this->actingAs($c->requester)->post('/locale', ['locale' => 'fr'])->assertSessionHasErrors('locale');
});

it('keeps backend-only messages out of the shared translations', function () {
    $c = cast();

    $this->actingAs($c->requester)->get('/requests')->assertInertia(fn (Assert $page) => $page
        ->where('translations', fn ($t) => collect($t)->keys()->doesntContain(fn ($k) => str_starts_with($k, 'errors.')))
    );
});

it('cannot delete your own account: operational history must keep pointing at a person', function () {
    $c = cast();

    $this->actingAs($c->requester)->delete('/settings/profile', ['password' => 'password'])->assertStatus(405);
});

it('has no public registration', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'x', 'email' => 'x@x.test', 'password' => 'password', 'password_confirmation' => 'password'])->assertNotFound();
});

it('blocks a deactivated user in the web app too', function () {
    $c = cast();
    $c->requester->forceFill(['is_active' => false])->save();

    $this->actingAs($c->requester)->get('/requests')->assertRedirect('/login');
    $this->assertGuest();
});

it('refuses to sign in a deactivated account at the login form', function () {
    $c = cast();
    $c->requester->forceFill(['is_active' => false])->save();

    $this->post('/login', ['email' => $c->requester->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('keeps the two paths honest: the web list and the API list agree', function () {
    $c = cast();
    assignedRequest($c);
    makeRequest($c->otherRequester, $c->category);

    $api = collect($this->actingAs($c->creativeLead, 'sanctum')->getJson('/api/v1/requests')->json('data'))
        ->pluck('request_number')->all();

    expect($api)->not->toBeEmpty();

    $this->actingAs($c->creativeLead)->get('/requests')->assertInertia(fn (Assert $page) => $page
        ->where('requests.data', fn ($rows) => collect($rows)->pluck('request_number')->all() === $api)
    );
});
