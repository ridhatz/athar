<?php

namespace App\Console\Commands;

use App\Enums\RoleCode;
use App\Http\Middleware\SetLocale;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * There is no public sign-up, so this is how real accounts come into being until the
 * admin screens exist. The password is asked for interactively: never pass it as an
 * option, where it would end up in shell history.
 */
class CreateUserCommand extends Command
{
    protected $signature = 'athar:create-user
        {email : Sign-in email address}
        {--name= : Full name (asked for if omitted)}
        {--role=* : ADMIN, TEAM_LEAD, STAFF, REQUESTER or MANAGEMENT (repeatable)}
        {--team=* : Team name to join (repeatable)}
        {--department= : Department name}
        {--locale=id : Interface language, id or en}';

    protected $description = 'Create a user account (there is no public registration)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $roles = array_map('strtoupper', (array) $this->option('role'));
        $roles = $roles === [] ? [RoleCode::Requester->value] : $roles;

        $check = Validator::make(
            ['email' => $email, 'roles' => $roles, 'locale' => $this->option('locale')],
            [
                'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'roles' => ['array', 'min:1'],
                'roles.*' => [Rule::in(RoleCode::values())],
                'locale' => [Rule::in(SetLocale::SUPPORTED)],
            ],
        );

        if ($check->fails()) {
            foreach ($check->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $teams = Team::query()->whereIn('name', (array) $this->option('team'))->get();
        $missingTeams = array_diff((array) $this->option('team'), $teams->pluck('name')->all());

        if ($missingTeams !== []) {
            $this->components->error('Unknown team(s): '.implode(', ', $missingTeams).'. Existing: '.Team::query()->pluck('name')->implode(', '));

            return self::FAILURE;
        }

        $department = null;

        if ($this->option('department')) {
            $department = Department::query()->where('name', $this->option('department'))->first();

            if ($department === null) {
                $this->components->error("Unknown department: {$this->option('department')}. Existing: ".Department::query()->pluck('name')->implode(', '));

                return self::FAILURE;
            }
        }

        $name = $this->option('name') ?: text(label: 'Full name', required: true);

        $plain = password(
            label: 'Password',
            required: true,
            validate: fn (string $value) => Validator::make(['password' => $value], [
                'password' => ['required', Password::defaults() ?? Password::min(8)],
            ])->errors()->first('password') ?: null,
        );

        $confirmation = password(label: 'Confirm password', required: true);

        if (! hash_equals($plain, $confirmation)) {
            $this->components->error('The passwords do not match.');

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $plain,
            'department_id' => $department?->id,
            'locale' => $this->option('locale'),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        foreach ($roles as $role) {
            $user->grantRole(RoleCode::from($role));
        }

        foreach ($teams as $team) {
            $user->joinTeam($team);
        }

        $this->components->info("Created {$user->name} <{$user->email}> as ".implode(', ', $roles).($teams->isNotEmpty() ? ' in '.$teams->pluck('name')->implode(', ') : '').'.');

        return self::SUCCESS;
    }
}
