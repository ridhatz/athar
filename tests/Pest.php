<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Actions\Requests\AssignRequest;
use App\Actions\Requests\CreateRequest;
use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\RequestCategory;
use App\Models\ServiceRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\AssertionFailedError;

/** A user holding one role, optionally in a team and a department. */
function userWith(RoleCode $role, ?Team $team = null, ?Department $department = null): User
{
    $factory = User::factory()->withRole($role);

    if ($team !== null) {
        $factory = $factory->inTeam($team);
    }

    return $factory->create(['department_id' => $department?->id]);
}

/** A ready-made cast of characters: the same people the demo seeder creates, but minimal. */
function cast(): object
{
    $department = Department::factory()->create(['name' => 'Humas']);
    $creative = Team::factory()->create(['name' => 'Creative']);
    $it = Team::factory()->create(['name' => 'IT']);

    return (object) [
        'department' => $department,
        'creative' => $creative,
        'it' => $it,
        'category' => RequestCategory::factory()->create(['slug' => 'design', 'name_en' => 'Design', 'name_id' => 'Desain']),
        'admin' => userWith(RoleCode::Admin),
        'management' => userWith(RoleCode::Management),
        'requester' => userWith(RoleCode::Requester, null, $department),
        'otherRequester' => userWith(RoleCode::Requester, null, $department),
        'creativeLead' => userWith(RoleCode::TeamLead, $creative),
        'itLead' => userWith(RoleCode::TeamLead, $it),
        'ahmad' => User::factory()->withRole(RoleCode::Staff)->inTeam($creative)->create(['name' => 'Ahmad']),
        'budi' => User::factory()->withRole(RoleCode::Staff)->inTeam($creative)->create(['name' => 'Budi']),
        'dewi' => User::factory()->withRole(RoleCode::Staff)->inTeam($it)->create(['name' => 'Dewi']),
    ];
}

/** Valid input for creating a request. */
function requestPayload(RequestCategory $category, array $overrides = []): array
{
    return array_merge([
        'title' => 'Poster seminar nasional',
        'description' => 'Butuh poster A3 untuk seminar tanggal 12.',
        'category_id' => $category->id,
        'deadline' => now()->addDays(5)->toIso8601String(),
    ], $overrides);
}

/** Create a request through the real action, as the given requester. */
function makeRequest(User $requester, RequestCategory $category, array $overrides = []): ServiceRequest
{
    return app(CreateRequest::class)->handle($requester, requestPayload($category, $overrides));
}

/** A request already routed to a team and person, via the real assign action. */
function assignedRequest(object $c, ?Team $team = null, ?User $pic = null): ServiceRequest
{
    $request = makeRequest($c->requester, $c->category);

    return app(AssignRequest::class)->handle($c->admin, $request, [
        'team_id' => ($team ?? $c->creative)->id,
        'user_id' => ($pic ?? $c->ahmad)->id,
    ]);
}

/**
 * Assert a callable fails validation on $field (and, optionally, that the message mentions $contains).
 * The exception's own message is always the generic "The given data was invalid",
 * so the useful text has to be read from errors().
 */
function expectValidationError(callable $fn, string $field, ?string $contains = null): void
{
    try {
        $fn();
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);

        if ($contains !== null) {
            expect(implode(' ', $e->errors()[$field]))->toContain($contains);
        }

        return;
    }

    throw new AssertionFailedError("Expected a validation error on [{$field}], but nothing was thrown.");
}
