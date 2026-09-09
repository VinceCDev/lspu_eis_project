<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the six canonical LSPU campuses.
 *
 * These names must match the top-level keys in app/Config/campus_programs.php
 * and the `campusCollegeCourses` object in the frontend JS, since alumni /
 * admin campus selection is keyed by name there.
 *
 * Idempotent: does nothing if the `campus` table already has rows, so it is
 * safe on every deploy and safe to run against an existing database.
 */
class CampusSeeder extends Seeder
{
    private const CAMPUSES = [
        ['name' => 'Santa Cruz', 'type' => 'Regular'],
        ['name' => 'Siniloan', 'type' => 'Regular'],
        ['name' => 'Los Baños', 'type' => 'Regular'],
        ['name' => 'San Pablo', 'type' => 'Regular'],
        ['name' => 'Nagcarlan', 'type' => 'Satellite'],
        ['name' => 'Lopez Quezon', 'type' => 'Satellite'],
    ];

    public function run(): void
    {
        if (DB::table('campus')->count() > 0) {
            $this->command?->info('campus table already populated — skipping.');

            return;
        }

        DB::table('campus')->insert(self::CAMPUSES);

        $this->command?->info('Seeded '.count(self::CAMPUSES).' campuses.');
    }
}
