<?php

use App\Enums\RoleCode;
use App\Models\RequestCategory;
use App\Models\User;

function tokenFor(User $user, string $password = 'password'): string
{
    return test()->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => $password])
        ->assertOk()->json('token');
}

it('issues a bearer token that works on the API', function () {
    $user = userWith(RoleCode::Requester);

    $response = $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password', 'device_name' => 'phone'])
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('user.roles', ['REQUESTER']);

    expect($response->json('expires_at'))->not->toBeNull();

    $this->withToken($response->json('token'))->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
});

it('answers a wrong password and an unknown email identically', function () {
    $user = userWith(RoleCode::Requester);

    $wrong = $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'nope'])->assertUnprocessable();
    $unknown = $this->postJson('/api/v1/auth/token', ['email' => 'ghost@athar.test', 'password' => 'nope'])->assertUnprocessable();

    expect($wrong->json('errors'))->toBe($unknown->json('errors'));
});

it('does not issue tokens to deactivated accounts', function () {
    $user = User::factory()->inactive()->create();

    $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
});

it('will not let a password alone bypass two-factor authentication', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password'])->assertForbidden();
});

it('throttles repeated failed sign-ins', function () {
    $user = userWith(RoleCode::Requester);

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();
});

it('revokes the token on logout', function () {
    $user = userWith(RoleCode::Requester);
    $token = tokenFor($user);

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    $this->app['auth']->forgetGuards(); // the next call must re-read the token, not reuse the cached user
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('rejects requests with no token or a bad one', function () {
    $this->getJson('/api/v1/requests')->assertUnauthorized();
    $this->withToken('999|not-a-real-token')->getJson('/api/v1/requests')->assertUnauthorized();
});

it('cuts off a deactivated user who still holds a valid token', function () {
    $user = userWith(RoleCode::Requester);
    $token = tokenFor($user);
    $user->forceFill(['is_active' => false])->save();

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
});

it('lists only active categories, in the caller\'s language', function () {
    $user = userWith(RoleCode::Requester);
    RequestCategory::factory()->create(['name_id' => 'Desain', 'name_en' => 'Design', 'sort_order' => 1]);
    RequestCategory::factory()->inactive()->create(['name_en' => 'Retired']);

    $token = tokenFor($user);

    $this->withToken($token)->withHeader('Accept-Language', 'en')->getJson('/api/v1/categories')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Design');

    $this->withToken($token)->withHeader('Accept-Language', 'id')->getJson('/api/v1/categories')
        ->assertOk()->assertJsonPath('data.0.name', 'Desain');
});

it('lets only those who assign work list assignable people', function () {
    $c = cast();

    foreach ([$c->requester, $c->ahmad, $c->management] as $person) {
        $this->actingAs($person, 'sanctum')->getJson('/api/v1/users')->assertForbidden();
    }

    $names = collect($this->actingAs($c->creativeLead, 'sanctum')->getJson('/api/v1/users?team_id='.$c->creative->id)
        ->assertOk()->json('data'))->pluck('name')->all();

    expect($names)->toContain('Ahmad', 'Budi')->not->toContain('Dewi');
});
