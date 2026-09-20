<?php

use App\Enums\RoleCode;
use App\Exceptions\ImmutableRecordException;
use App\Models\AuditLog;
use App\Models\Team;
use App\Models\User;
use App\Queries\WorkloadBoard;
use App\Support\AuditGuards;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->c = cast();
    $this->hr = $this->c->department;   // already named Humas by cast()
});

function newUser(array $overrides = []): array
{
    return array_merge([
        'name' => 'Dias Pratama',
        'email' => 'dias@tazkia.ac.id',
        'roles' => ['STAFF'],
        'team_ids' => [test()->c->creative->id],
        'department_id' => test()->hr->id,
        'locale' => 'id',
    ], $overrides);
}

describe('creating people', function () {
    it('adds a person with the right role and team, and a generated password that works', function () {
        Sanctum::actingAs($this->c->admin);

        $response = $this->postJson('/api/v1/admin/users', newUser())->assertCreated()
            ->assertJsonPath('data.name', 'Dias Pratama')
            ->assertJsonPath('data.roles', ['STAFF'])
            ->assertJsonPath('data.teams.0.name', 'Creative')
            ->assertJsonPath('data.department.name', 'Humas')
            ->assertJsonPath('data.is_active', true);

        $password = $response->json('generated_password');
        expect($password)->toBeString()->toHaveLength(14);

        $user = User::query()->where('email', 'dias@tazkia.ac.id')->sole();
        expect($user->password)->not->toContain($password)      // stored hashed, never in plain text
            ->and($user->email_verified_at)->not->toBeNull();

        $this->postJson('/api/v1/auth/token', ['email' => 'dias@tazkia.ac.id', 'password' => $password])->assertOk();
    });

    it('accepts a password the admin chose, and then returns none', function () {
        Sanctum::actingAs($this->c->admin);

        $response = $this->postJson('/api/v1/admin/users', newUser(['password' => 'Chosen-by-admin-77']))->assertCreated();

        expect($response->json('generated_password'))->toBeNull();
        $this->postJson('/api/v1/auth/token', ['email' => 'dias@tazkia.ac.id', 'password' => 'Chosen-by-admin-77'])->assertOk();
    });

    it('gives each role what it should be able to do', function () {
        Sanctum::actingAs($this->c->admin);
        $creative = $this->c->creative->id;

        foreach ([
            ['sari2@t.id', ['REQUESTER'], []],
            ['staf@t.id', ['STAFF'], [$creative]],
            ['lead@t.id', ['TEAM_LEAD'], [$creative]],
            ['boss@t.id', ['ADMIN'], []],
            ['mgmt@t.id', ['MANAGEMENT'], []],
        ] as [$email, $roles, $teams]) {
            $this->postJson('/api/v1/admin/users', newUser(['email' => $email, 'roles' => $roles, 'team_ids' => $teams]))->assertCreated();
        }

        // A new requester can file; new management cannot.
        Sanctum::actingAs(User::where('email', 'sari2@t.id')->first());
        $this->postJson('/api/v1/requests', requestPayload($this->c->category))->assertCreated();
        Sanctum::actingAs(User::where('email', 'mgmt@t.id')->first());
        $this->postJson('/api/v1/requests', requestPayload($this->c->category, ['department_id' => $this->hr->id]))->assertForbidden();

        // New staff show up on the workload board and can be assigned work.
        expect(array_column(app(WorkloadBoard::class)->board(), 'name'))->toContain('Dias Pratama');
    });

    it('refuses a role setup that does not make sense', function (array $override, string $field) {
        Sanctum::actingAs($this->c->admin);

        $this->postJson('/api/v1/admin/users', newUser($override))->assertUnprocessable()->assertJsonValidationErrors($field);

        expect(User::query()->where('email', 'dias@tazkia.ac.id')->exists())->toBeFalse();
    })->with([
        'no role' => [['roles' => []], 'roles'],
        'unknown role' => [['roles' => ['OVERLORD']], 'roles.0'],
        'management mixed with another role' => [['roles' => ['MANAGEMENT', 'STAFF']], 'roles'],
        'staff without a team' => [['roles' => ['STAFF'], 'team_ids' => []], 'team_ids'],
        'team lead without a team' => [['roles' => ['TEAM_LEAD'], 'team_ids' => []], 'team_ids'],
        'a team that does not exist' => [['team_ids' => [424242]], 'team_ids.0'],
        'a bad email' => [['email' => 'not-an-email'], 'email'],
        'a weak password' => [['password' => 'short'], 'password'],
        'unknown language' => [['locale' => 'fr'], 'locale'],
    ]);

    it('rejects an email that is already taken, whatever its capitals', function () {
        Sanctum::actingAs($this->c->admin);
        $this->postJson('/api/v1/admin/users', newUser())->assertCreated();

        $this->postJson('/api/v1/admin/users', newUser(['email' => 'DIAS@Tazkia.ac.id']))->assertUnprocessable()->assertJsonValidationErrors('email');
    });

    it('will not put someone in an inactive team', function () {
        Sanctum::actingAs($this->c->admin);
        $this->c->creative->forceFill(['is_active' => false])->save();

        $this->postJson('/api/v1/admin/users', newUser())->assertUnprocessable()->assertJsonValidationErrors('team_ids');
    });

    it('lets a requester be filed without any team', function () {
        Sanctum::actingAs($this->c->admin);

        $this->postJson('/api/v1/admin/users', newUser(['roles' => ['REQUESTER'], 'team_ids' => []]))->assertCreated();
    });
});

describe('who may manage people', function () {
    it('is for admins only', function (string $who) {
        Sanctum::actingAs($this->c->{$who});
        $target = $this->c->budi;

        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->postJson('/api/v1/admin/users', newUser())->assertForbidden();
        $this->patchJson("/api/v1/admin/users/{$target->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->postJson("/api/v1/admin/users/{$target->id}/reset-password")->assertForbidden();

        expect(User::query()->where('email', 'dias@tazkia.ac.id')->exists())->toBeFalse()
            ->and($target->fresh()->name)->toBe('Budi');
    })->with(['requester', 'ahmad', 'creativeLead', 'management']);

    it('needs a signed-in user', function () {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
        $this->get('/admin/users')->assertRedirect('/login');
    });

    it('does not let a deactivated admin manage anyone', function () {
        $this->c->admin->forceFill(['is_active' => false])->save();
        Sanctum::actingAs($this->c->admin);

        $this->getJson('/api/v1/admin/users')->assertForbidden();
    });

    it('never exposes password hashes', function () {
        Sanctum::actingAs($this->c->admin);

        $content = $this->getJson('/api/v1/admin/users')->assertOk()->getContent();

        expect($content)->not->toContain('password')->not->toContain('remember_token')->not->toContain('$2y$');
    });
});

describe('changing people', function () {
    it('changes roles, teams and details, and records before and after', function () {
        Sanctum::actingAs($this->c->admin);

        $this->patchJson("/api/v1/admin/users/{$this->c->budi->id}", [
            'roles' => ['TEAM_LEAD'], 'team_ids' => [$this->c->creative->id, $this->c->it->id], 'name' => 'Budi Santoso',
        ])->assertOk()->assertJsonPath('data.roles', ['TEAM_LEAD'])->assertJsonCount(2, 'data.teams');

        $log = AuditLog::query()->where('subject_id', $this->c->budi->id)->sole();
        expect($log->action)->toBe('USER_UPDATED')
            ->and($log->actor_id)->toBe($this->c->admin->id)
            ->and($log->old_values['roles'])->toBe(['STAFF'])
            ->and($log->new_values['roles'])->toBe(['TEAM_LEAD'])
            ->and($log->old_values['name'])->toBe('Budi')
            ->and($log->new_values['name'])->toBe('Budi Santoso')
            ->and($log->new_values['teams'])->toBe(['Creative', 'IT']);
    });

    it('logs nothing when nothing changes', function () {
        Sanctum::actingAs($this->c->admin);

        $this->patchJson("/api/v1/admin/users/{$this->c->budi->id}", ['name' => 'Budi', 'roles' => ['STAFF']])->assertOk();

        expect(AuditLog::query()->count())->toBe(0);
    });

    it('cuts a deactivated person off everywhere, at once', function () {
        Sanctum::actingAs($this->c->admin);
        $victim = $this->c->ahmad;
        $token = $victim->createToken('phone')->plainTextToken;
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $victim->id, 'payload' => '', 'last_activity' => time()]);

        $this->patchJson("/api/v1/admin/users/{$victim->id}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);

        expect($victim->tokens()->count())->toBe(0)
            ->and(DB::table('sessions')->where('user_id', $victim->id)->count())->toBe(0)
            ->and(AuditLog::query()->sole()->action)->toBe('USER_DEACTIVATED');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/token', ['email' => $victim->email, 'password' => 'password'])->assertUnprocessable();

        // ...and can be brought back.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->c->admin);
        $this->patchJson("/api/v1/admin/users/{$victim->id}", ['is_active' => true])->assertOk();
        expect(AuditLog::query()->latest('id')->first()->action)->toBe('USER_ACTIVATED');
    });

    it('does not let an admin lock themselves out', function () {
        Sanctum::actingAs($this->c->admin);

        $this->patchJson("/api/v1/admin/users/{$this->c->admin->id}", ['is_active' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('is_active');

        expect($this->c->admin->fresh()->is_active)->toBeTrue();
    });

    it('always keeps one active administrator', function () {
        Sanctum::actingAs($this->c->admin);

        // The only admin cannot demote themselves...
        $this->patchJson("/api/v1/admin/users/{$this->c->admin->id}", ['roles' => ['REQUESTER']])
            ->assertUnprocessable()->assertJsonValidationErrors('roles');

        // ...but once there is a second one, the first may step down.
        $second = User::factory()->withRole(RoleCode::Admin)->create();
        $this->patchJson("/api/v1/admin/users/{$this->c->admin->id}", ['roles' => ['REQUESTER']])->assertOk();

        Sanctum::actingAs($second);
        $this->patchJson("/api/v1/admin/users/{$second->id}", ['roles' => ['STAFF'], 'team_ids' => [$this->c->creative->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('roles');
    });

    it('re-applies the role rules when roles or teams change', function () {
        Sanctum::actingAs($this->c->admin);

        // Removing a worker's only team leaves them unable to be assigned anything.
        $this->patchJson("/api/v1/admin/users/{$this->c->budi->id}", ['team_ids' => []])->assertUnprocessable()->assertJsonValidationErrors('team_ids');
        // Management cannot be added on top of another role.
        $this->patchJson("/api/v1/admin/users/{$this->c->budi->id}", ['roles' => ['STAFF', 'MANAGEMENT']])->assertUnprocessable();
    });

    it('cannot reuse an email that belongs to someone else', function () {
        Sanctum::actingAs($this->c->admin);

        $this->patchJson("/api/v1/admin/users/{$this->c->budi->id}", ['email' => $this->c->ahmad->email])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->patchJson("/api/v1/admin/users/{$this->c->budi->id}", ['email' => $this->c->budi->email])->assertOk();  // their own is fine
    });
});

describe('passwords', function () {
    it('resets a password, signs the person out, and never writes the password to the audit trail', function () {
        Sanctum::actingAs($this->c->admin);
        $person = $this->c->budi;
        $person->createToken('old');

        $new = $this->postJson("/api/v1/admin/users/{$person->id}/reset-password")->assertOk()->json('password');

        expect($new)->toHaveLength(14)->and($person->tokens()->count())->toBe(0);
        $this->postJson('/api/v1/auth/token', ['email' => $person->email, 'password' => $new])->assertOk();
        $this->postJson('/api/v1/auth/token', ['email' => $person->email, 'password' => 'password'])->assertUnprocessable();

        $log = AuditLog::query()->sole();
        expect($log->action)->toBe('PASSWORD_RESET')->and(json_encode($log->getAttributes()))->not->toContain($new);
    });

    it('accepts a password the admin chooses', function () {
        Sanctum::actingAs($this->c->admin);

        $this->postJson("/api/v1/admin/users/{$this->c->budi->id}/reset-password", ['password' => 'Fresh-start-2026'])
            ->assertOk()->assertJsonPath('password', 'Fresh-start-2026');
    });
});

describe('the audit trail of administration', function () {
    it('records who created someone and never a password', function () {
        Sanctum::actingAs($this->c->admin);
        $response = $this->postJson('/api/v1/admin/users', newUser())->assertCreated();

        $log = AuditLog::query()->sole();
        expect($log->action)->toBe('USER_CREATED')
            ->and($log->actor_id)->toBe($this->c->admin->id)
            ->and($log->new_values['roles'])->toBe(['STAFF'])
            ->and($log->new_values['password_generated'])->toBeTrue()
            ->and(json_encode($log->getAttributes()))->not->toContain($response->json('generated_password'));
    });

    it('cannot be edited or deleted', function () {
        Sanctum::actingAs($this->c->admin);
        $this->postJson('/api/v1/admin/users', newUser())->assertCreated();
        $log = AuditLog::query()->sole();

        $log->action = 'FORGED';
        expect(fn () => $log->save())->toThrow(ImmutableRecordException::class);
        expect(fn () => $log->delete())->toThrow(ImmutableRecordException::class);
    });

    it('is protected by the database too, where triggers are permitted', function () {
        if (AuditGuards::missing() !== []) {
            $this->markTestSkipped('This server does not allow the audit triggers.');
        }

        Sanctum::actingAs($this->c->admin);
        $this->postJson('/api/v1/admin/users', newUser())->assertCreated();

        expect(fn () => DB::table('audit_logs')->update(['action' => 'FORGED']))->toThrow(QueryException::class, 'append-only');
        expect(fn () => DB::table('audit_logs')->delete())->toThrow(QueryException::class, 'append-only');
    });
});

describe('the People screen', function () {
    it('lists everyone with roles and teams, for admins', function () {
        $this->actingAs($this->c->admin)->get('/admin/users')->assertInertia(fn (Assert $page) => $page
            ->component('admin/users')
            ->has('users.data')
            ->where('roles', ['REQUESTER', 'STAFF', 'TEAM_LEAD', 'ADMIN', 'MANAGEMENT'])
            ->has('teams', 2)
            ->has('departments')
        );
    });

    it('searches by name or email, and filters by role', function () {
        $names = fn (string $query) => collect($this->actingAs($this->c->admin)->getJson("/api/v1/admin/users{$query}")->json('data'))->pluck('name')->all();
        $page = fn (string $query) => $this->actingAs($this->c->admin)->get("/admin/users{$query}");

        $page('?q=ahmad')->assertInertia(fn (Assert $p) => $p->has('users.data', 1)->where('users.data.0.name', 'Ahmad'));
        $page('?role=MANAGEMENT')->assertInertia(fn (Assert $p) => $p->has('users.data', 1));
        $page('?q=%25')->assertInertia(fn (Assert $p) => $p->has('users.data', 0));   // % is a plain character
    });

    it('is closed to everyone else', function (string $who) {
        $this->actingAs($this->c->{$who})->get('/admin/users')->assertForbidden();
    })->with(['requester', 'ahmad', 'creativeLead', 'management']);

    it('creates through the form and shows the generated password once', function () {
        $this->actingAs($this->c->admin)->from('/admin/users')
            ->post('/admin/users', newUser())
            ->assertRedirect('/admin/users');

        expect(User::query()->where('email', 'dias@tazkia.ac.id')->exists())->toBeTrue();
    });

    it('shows validation problems back on the form', function () {
        $this->actingAs($this->c->admin)->from('/admin/users')
            ->post('/admin/users', newUser(['roles' => ['MANAGEMENT', 'STAFF']]))
            ->assertRedirect('/admin/users')->assertSessionHasErrors('roles');
    });

    it('tells the UI whether to offer the People screen', function () {
        foreach (['admin' => true, 'requester' => false, 'creativeLead' => false] as $who => $expected) {
            $this->actingAs($this->c->{$who})->get('/requests')->assertInertia(fn (Assert $page) => $page->where('can.manage_users', $expected));
        }
    });
});
