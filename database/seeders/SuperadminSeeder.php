<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates the default super administrator account.
 *
 * Idempotent: if a user with the target email already exists, nothing is
 * changed — so it is safe to run on every deploy.
 *
 * Credentials come from the environment (SUPERADMIN_EMAIL /
 * SUPERADMIN_PASSWORD) with sane defaults. Passwords are hashed with
 * password_hash(PASSWORD_DEFAULT) to match how the rest of this app
 * (App\Http\Controllers\*\AuthController) creates and verifies them.
 *
 * `last_login` is pre-set to now and two-factor is left disabled so the
 * very first sign-in does not trigger the "first login / stale login"
 * forced-2FA path in Shared\AuthController::login(), which would need a
 * working mailer to deliver the code.
 */
class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'superadmin@lspu.edu.ph');
        $password = env('SUPERADMIN_PASSWORD', 'ChangeMe!Super2026');

        if (DB::table('user')->where('email', $email)->exists()) {
            $this->command?->info("Superadmin '{$email}' already exists — skipping.");

            return;
        }

        $now = now();

        $userId = DB::table('user')->insertGetId([
            'email' => $email,
            'secondary_email' => null,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'user_role' => 'superadmin',
            'status' => 'Active',
            'created_at' => $now,
            'last_login' => $now,
            'two_factor_enabled' => 0,
            'two_factor_method' => 'email',
        ]);

        // Matching administrator row (campus_id NULL = all campuses). The
        // login query LEFT JOINs this table, and some admin screens read a
        // display name from it.
        DB::table('administrator')->insert([
            'user_id' => $userId,
            'first_name' => 'Super',
            'middle_name' => null,
            'last_name' => 'Administrator',
            'gender' => null,
            'contact' => null,
            'position' => 'Super Administrator',
            'department' => null,
            'campus_id' => null,
            'profile_pic' => null,
            'status' => 'Active',
            'created_at' => $now,
            'updated_at' => $now,
            'address' => 'LSPU',
        ]);

        $this->command?->warn("Superadmin created: {$email} — CHANGE THIS PASSWORD after first login.");
    }
}
