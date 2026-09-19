<?php

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use App\Support\AuditGuards;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\Hash;

const GOOD_PASSWORD = 'Str0ng-passw0rd!';

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

describe('athar:create-user', function () {
    it('creates a working account with roles, team and department', function () {
        $this->artisan('athar:create-user', [
            'email' => '  Ahmad@Tazkia.AC.ID ',
            '--name' => 'Ahmad Fauzi',
            '--role' => ['staff'],
            '--team' => ['Creative'],
            '--department' => 'Humas',
            '--locale' => 'en',
        ])
            ->expectsQuestion('Password', GOOD_PASSWORD)
            ->expectsQuestion('Confirm password', GOOD_PASSWORD)
            ->assertSuccessful();

        $user = User::query()->where('email', 'ahmad@tazkia.ac.id')->firstOrFail();

        expect($user->name)->toBe('Ahmad Fauzi')
            ->and($user->locale)->toBe('en')
            ->and($user->is_active)->toBeTrue()
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->roleCodes())->toBe([RoleCode::Staff])
            ->and($user->teams->pluck('name')->all())->toBe(['Creative'])
            ->and($user->department->name)->toBe('Humas')
            ->and(Hash::check(GOOD_PASSWORD, $user->password))->toBeTrue()
            ->and($user->password)->not->toContain(GOOD_PASSWORD);

        $this->postJson('/api/v1/auth/token', ['email' => 'ahmad@tazkia.ac.id', 'password' => GOOD_PASSWORD])->assertOk();
    });

    it('defaults to the requester role', function () {
        $this->artisan('athar:create-user', ['email' => 'sari@tazkia.ac.id', '--name' => 'Sari'])
            ->expectsQuestion('Password', GOOD_PASSWORD)
            ->expectsQuestion('Confirm password', GOOD_PASSWORD)
            ->assertSuccessful();

        expect(User::query()->where('email', 'sari@tazkia.ac.id')->firstOrFail()->roleCodes())->toBe([RoleCode::Requester]);
    });

    it('refuses bad input without creating anything', function (array $arguments) {
        User::factory()->create(['email' => 'taken@tazkia.ac.id']);
        $before = User::query()->count();

        $this->artisan('athar:create-user', $arguments + ['--name' => 'X'])->assertFailed();

        expect(User::query()->count())->toBe($before);
    })->with([
        'duplicate email' => [['email' => 'taken@tazkia.ac.id']],
        'not an email' => [['email' => 'not-an-email']],
        'unknown role' => [['email' => 'a@tazkia.ac.id', '--role' => ['OVERLORD']]],
        'unknown team' => [['email' => 'a@tazkia.ac.id', '--team' => ['Nope']]],
        'unknown department' => [['email' => 'a@tazkia.ac.id', '--department' => 'Nope']],
        'unsupported language' => [['email' => 'a@tazkia.ac.id', '--locale' => 'fr']],
    ]);

    it('refuses when the two passwords differ', function () {
        $this->artisan('athar:create-user', ['email' => 'a@tazkia.ac.id', '--name' => 'A'])
            ->expectsQuestion('Password', GOOD_PASSWORD)
            ->expectsQuestion('Confirm password', 'something-else-entirely')
            ->assertFailed();

        expect(User::query()->where('email', 'a@tazkia.ac.id')->exists())->toBeFalse();
    });

    it('lists the valid choices when a team or department is unknown', function () {
        $this->artisan('athar:create-user', ['email' => 'a@tazkia.ac.id', '--name' => 'A', '--team' => ['Nope']])
            ->expectsOutputToContain('Existing: ')
            ->assertFailed();

        expect(Team::query()->count())->toBeGreaterThan(0)->and(Department::query()->count())->toBeGreaterThan(0);
    });
});

describe('athar:audit-guards', function () {
    it('reports the true state of the database triggers', function () {
        $intact = AuditGuards::missing() === [];

        $command = $this->artisan('athar:audit-guards');

        $intact ? $command->assertSuccessful() : $command->assertFailed();
    });

    it('is safe to run --install when nothing is missing', function () {
        if (AuditGuards::missing() !== []) {
            $this->markTestSkipped('This server does not allow the audit triggers; nothing to verify.');
        }

        $before = AuditGuards::present();

        $this->artisan('athar:audit-guards', ['--install' => true])->assertSuccessful();

        expect(AuditGuards::present())->toBe($before);
    });

    // Deliberately no test that DROPs the triggers: CREATE/DROP TRIGGER are DDL, which MySQL commits
    // implicitly. That would tear through the transaction every test runs in and leak data into the
    // next test. The repair path was exercised by hand against the development database instead.
});
