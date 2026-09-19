<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Reference data always; demo accounts only on a local machine.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
