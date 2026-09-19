<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Demo accounts with a shared, publicly known password.
 *
 * Refuses to run anywhere except APP_ENV=local so it can never plant a
 * known-password admin on a real server. Real users: php artisan athar:create-user
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('DemoDataSeeder only runs when APP_ENV=local.');
        }

        $people = [
            // name, email, role, department, team
            ['Admin Athar', 'admin@athar.test', RoleCode::Admin, 'Teknologi Informasi', null],
            ['Agus Pratama', 'agus@athar.test', RoleCode::TeamLead, 'Teknologi Informasi', 'IT'],
            ['Rina Lestari', 'rina@athar.test', RoleCode::TeamLead, 'Humas', 'Creative'],
            ['Ahmad Fauzi', 'ahmad@athar.test', RoleCode::Staff, 'Humas', 'Creative'],
            ['Budi Santoso', 'budi@athar.test', RoleCode::Staff, 'Humas', 'Creative'],
            ['Dewi Anggraini', 'dewi@athar.test', RoleCode::Staff, 'Teknologi Informasi', 'IT'],
            ['Sari Wulandari', 'sari@athar.test', RoleCode::Requester, 'Humas', null],
            ['Hendra Wijaya', 'hendra@athar.test', RoleCode::Requester, 'Akademik', null],
            ['Manajemen Athar', 'manajemen@athar.test', RoleCode::Management, 'Keuangan', null],
        ];

        foreach ($people as [$name, $email, $role, $department, $team]) {
            $user = User::query()->updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make(self::PASSWORD),
                'department_id' => Department::query()->where('name', $department)->value('id'),
                'locale' => 'id',
                'email_verified_at' => now(),
            ]);
            $user->is_active = true;
            $user->save();

            $user->grantRole($role);

            if ($team !== null) {
                $user->joinTeam(Team::query()->where('name', $team)->firstOrFail());
            }
        }
    }
}
