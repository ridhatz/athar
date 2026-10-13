<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * The real Tazkia teams and who sits in them, loaded with placeholder @athar.test addresses.
 *
 * The people are real; the e-mail addresses are NOT. They exist so the roster can be tried out
 * before anyone's real address is known — Athar mails notifications to whatever is on record, so
 * putting a guessed address here would send real work mail to the wrong place. Replace them from
 * the Pengguna screen (or re-point this file) before this roster is used in earnest.
 *
 * Shares DemoDataSeeder's stance: one publicly known password, so it refuses to run anywhere but
 * APP_ENV=local. Real accounts are made with `php artisan athar:create-user`.
 */
class TazkiaOrgSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /** @var array<string, string> team name => what the team does */
    private const TEAMS = [
        'Admisi' => 'Penerimaan mahasiswa baru',
        'Edu Consultant' => 'Konsultasi dan pendampingan calon mahasiswa',
        'Media' => 'Konten, dokumentasi, dan media',
        'Kerjasama & CDC' => 'Kemitraan dan Career Development Center',
    ];

    /**
     * name, e-mail local part, teams.
     *
     * Everyone starts as STAFF so they can be assigned work. Nobody is a TEAM_LEAD yet, which
     * means no team can review its own output — promote the leads from the Pengguna screen.
     *
     * @var array<int, array{0: string, 1: string, 2: array<int, string>}>
     */
    private const PEOPLE = [
        ['Sadam', 'sadam', ['Admisi']],
        ['Hadi', 'hadi', ['Admisi']],
        // The one person who sits in two teams.
        ['Usnaeni', 'usnaeni', ['Admisi', 'Edu Consultant']],
        ['Hasan Zaki', 'hasan', ['Admisi']],

        ['Siti Nurfadilah', 'siti', ['Edu Consultant']],
        ['Rumaisha', 'rumaisha', ['Edu Consultant']],
        ['Kivia', 'kivia', ['Edu Consultant']],
        ['Qanita Sabrina', 'qanita', ['Edu Consultant']],

        ['Herdy', 'herdy', ['Media']],
        ['Dias', 'dias', ['Media']],
        ['Ridha', 'ridha', ['Media']],

        ['Melvina', 'melvina', ['Kerjasama & CDC']],
        ['Irma Amalia', 'irma', ['Kerjasama & CDC']],
        ['Syifani', 'syifani', ['Kerjasama & CDC']],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('TazkiaOrgSeeder only runs when APP_ENV=local.');
        }

        foreach (self::TEAMS as $name => $description) {
            Team::query()->firstOrCreate(['name' => $name], ['description' => $description]);
        }

        foreach (self::PEOPLE as [$name, $handle, $teams]) {
            $user = User::query()->updateOrCreate(['email' => $handle.'@athar.test'], [
                'name' => $name,
                'password' => Hash::make(self::PASSWORD),
                'locale' => 'id',
                'email_verified_at' => now(),
            ]);
            $user->is_active = true;
            $user->save();

            $user->grantRole(RoleCode::Staff);

            foreach ($teams as $team) {
                $user->joinTeam(Team::query()->where('name', $team)->firstOrFail());
            }
        }
    }
}
