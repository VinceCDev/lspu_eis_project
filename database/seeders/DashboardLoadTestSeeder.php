<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DEVELOPMENT-ONLY load generator for dashboard performance testing.
 *
 *   php artisan db:seed --class=DashboardLoadTestSeeder            # default: top up to 10,000 alumni
 *   ALUMNI_TARGET=50000  php artisan db:seed --class=DashboardLoadTestSeeder
 *   ALUMNI_TARGET=100000 php artisan db:seed --class=DashboardLoadTestSeeder
 *
 * Inserts synthetic user + alumni + alumni_experience rows until `alumni`
 * reaches ALUMNI_TARGET. All synthetic users have emails ending in
 * `@loadtest.invalid` and status 'Inactive' so they are easy to find and
 * delete:
 *
 *   php artisan db:seed --class=DashboardLoadTestSeeder   # (with ALUMNI_PURGE=1 to remove them)
 *
 * Refuses to run when APP_ENV=production.
 */
class DashboardLoadTestSeeder extends Seeder
{
    private const EMAIL_SUFFIX = '@loadtest.invalid';

    private const COLLEGES = [
        'College of Computer Studies' => ['BS Information Technology', 'BS Computer Science', 'BS Information System'],
        'College of Business Administration and Accountancy' => ['BS Accountancy', 'BS Business Administration', 'BS Office Administration'],
        'College of Arts and Sciences' => ['BS Biology', 'BS Psychology', 'BS Mathematics'],
        'College of Teacher Education' => ['Bachelor of Elementary Education', 'Bachelor of Secondary Education', 'Bachelor of Physical Education'],
        'College of Engineering' => ['BS Civil Engineering', 'BS Electrical Engineering', 'BS Computer Engineering'],
        'College of Criminal Justice Education' => ['BS Criminology'],
        'College of Industrial Technology' => ['BS Industrial Technology'],
        'College of International Hospitality and Tourism Management' => ['BS Hospitality Management', 'BS Tourism Management'],
    ];

    private const CITIES = [
        ['San Pablo City', 'Laguna'], ['Santa Cruz', 'Laguna'], ['Calamba', 'Laguna'], ['Los Baños', 'Laguna'],
        ['Biñan', 'Laguna'], ['Cabuyao', 'Laguna'], ['Nagcarlan', 'Laguna'], ['Liliw', 'Laguna'],
        ['Lucena', 'Quezon'], ['Tiaong', 'Quezon'], ['Candelaria', 'Quezon'], ['Sariaya', 'Quezon'],
        ['Batangas City', 'Batangas'], ['Lipa', 'Batangas'], ['Tanauan', 'Batangas'],
        ['Dasmariñas', 'Cavite'], ['Bacoor', 'Cavite'], ['Imus', 'Cavite'],
        ['Antipolo', 'Rizal'], ['Taytay', 'Rizal'],
    ];

    // Exact casings that already exist in real data — using a variant
    // casing here would make GROUP BY employment_status (ci collation)
    // surface a plan-dependent representative and break comparisons.
    private const STATUSES = ['Regular', 'Contractual', 'Probational', 'Self-Employed'];
    private const SECTORS = ['Private', 'Government'];
    private const LOCATIONS = ['Local', 'Abroad'];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('Refusing to run in production.');

            return;
        }

        if (filter_var(env('ALUMNI_PURGE', false), FILTER_VALIDATE_BOOL)) {
            $this->purge();

            return;
        }

        $target = (int) env('ALUMNI_TARGET', 10000);
        $current = (int) DB::table('alumni')->count();
        $this->command->info("alumni rows now: {$current}; target: {$target}");

        if ($current >= $target) {
            $this->command->info('Already at or above target — nothing to do.');

            return;
        }

        $campusIds = DB::table('campus')->pluck('campus_id')->all();
        if (empty($campusIds)) {
            $campusIds = [1, 2, 3, 4, 5, 6];
        }

        $collegeCourses = [];
        foreach (self::COLLEGES as $college => $courses) {
            foreach ($courses as $c) {
                $collegeCourses[] = [$college, $c];
            }
        }

        $toInsert = $target - $current;
        $batchSize = 1000;
        $ncc = count($collegeCourses);
        $ncid = count($campusIds);
        $ncity = count(self::CITIES);
        $nstatus = count(self::STATUSES);
        $seq = (int) DB::table('user')->max('user_id') + 1;
        $now = now()->toDateTimeString();
        $bar = $this->command->getOutput()->createProgressBar($toInsert);
        $bar->start();

        for ($done = 0; $done < $toInsert; $done += $batchSize) {
            $n = min($batchSize, $toInsert - $done);
            $tag = 'ltb'.$seq;   // unique per batch, used to read the rows back

            // 1. users
            $users = [];
            for ($i = 0; $i < $n; $i++) {
                $users[] = [
                    'email' => $tag.'_'.$i.self::EMAIL_SUFFIX,
                    'password' => '$2y$10$abcdefghijklmnopqrstuv',
                    'user_role' => 'alumni', 'status' => 'Inactive', 'created_at' => $now,
                ];
            }
            DB::table('user')->insert($users);

            // read back the REAL ids (no auto-increment-contiguity assumption)
            $userIds = DB::table('user')
                ->where('email', 'like', $tag.'\_%'.self::EMAIL_SUFFIX)
                ->orderBy('user_id')->pluck('user_id')->all();

            // 2. alumni — one per user, keyed to that user's real id
            $alumni = [];
            foreach ($userIds as $k => $uid) {
                $g = $seq + $k;
                [$college, $course] = $collegeCourses[$g % $ncc];
                [$city, $province] = self::CITIES[$g % $ncity];
                $alumni[] = [
                    'user_id' => $uid,
                    'first_name' => 'Load', 'middle_name' => 'T', 'last_name' => 'Test'.$g,
                    'birthdate' => null, 'contact' => '', 'gender' => ($k % 2) ? 'Male' : 'Female',
                    'civil_status' => 'Single', 'city' => $city, 'province' => $province,
                    'year_graduated' => 2018 + ($g % 7),
                    'college' => $college, 'course' => $course,
                    'campus_id' => $campusIds[$g % $ncid],
                    'verification_document' => '', 'created_at' => $now,
                ];
            }
            DB::table('alumni')->insert($alumni);

            // read back alumni ids for exactly these users
            $alumniIds = DB::table('alumni')->whereIn('user_id', $userIds)
                ->orderBy('user_id')->pluck('alumni_id')->all();

            // 3. experience — ~65% employed, a few ended
            $exp = [];
            foreach ($alumniIds as $k => $aid) {
                $g = $seq + $k;
                $r = $g % 100;
                if ($r >= 65) {
                    continue;
                }
                $ended = $r < 8;
                $exp[] = [
                    'alumni_id' => $aid,
                    'title' => 'Staff', 'company' => 'LoadTest Corp',
                    'start_date' => (2019 + ($g % 6)).'-06-01',
                    'end_date' => $ended ? (2020 + ($g % 5)).'-05-01' : null,
                    'current' => $ended ? 0 : 1,
                    'description' => null,
                    'location_of_work' => self::LOCATIONS[$g % 2],
                    'employment_status' => self::STATUSES[$g % $nstatus],
                    'employment_sector' => self::SECTORS[$g % 2],
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            if (!empty($exp)) {
                DB::table('alumni_experience')->insert($exp);
            }

            $seq += $n;
            $bar->advance($n);
        }

        $bar->finish();
        $this->command->newLine(2);
        $this->command->info('alumni rows now: '.DB::table('alumni')->count()
            .'; alumni_experience rows now: '.DB::table('alumni_experience')->count());
    }

    private function purge(): void
    {
        $total = DB::table('user')->where('email', 'like', '%'.self::EMAIL_SUFFIX)->count();
        $this->command->warn("Purging {$total} load-test users (cascades to alumni + alumni_experience)...");

        do {
            $deleted = DB::table('user')
                ->where('email', 'like', '%'.self::EMAIL_SUFFIX)
                ->limit(2000)
                ->delete();
        } while ($deleted > 0);

        $this->command->info('Done. alumni rows now: '.DB::table('alumni')->count());
    }
}
