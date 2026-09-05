<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ISO/IEC 25010 remediation — item 8 (database integrity).
 *
 * Found 2 `applications` rows (application_id 2 and 6) whose job_id
 * (1 and 32 respectively) no longer exists in `jobs`, despite `applications`
 * having a real `ON DELETE CASCADE` foreign key on job_id (confirmed via
 * information_schema.REFERENTIAL_CONSTRAINTS — cascade deletes work
 * correctly for any deletion made through the app today).
 *
 * Investigated before touching anything:
 *  - Both rows belong to alumni_id 1 ("Vince Allen Cristal" — the
 *    developer's own local dev/test account, created 2025-07-19).
 *  - `audit_logs` has zero entries for any job deletion at all, let alone
 *    for job_id 1 or 32 — the app's own Job::delete()/deleteForEmployer()
 *    paths always write an audit_logs 'delete_job' entry
 *    (Superadmin\JobController::destroy()), so these jobs were never
 *    removed through tracked application code. They were most likely
 *    removed by a direct SQL statement during local data setup/cleanup
 *    with FK checks temporarily disabled, bypassing the CASCADE.
 *  - Conclusion: leftover local dev/test artifacts referencing jobs that
 *    never existed in the current dataset, not real user/application
 *    history — safe to remove. Down() cannot restore them (their original
 *    column values are hardcoded below from the rows as found, but the
 *    jobs they pointed to are gone, so "down" recreates the same orphaned
 *    rows rather than a meaningful rollback — see down()'s docblock).
 *
 * First attempt at this DELETE failed with a FK violation:
 * `applicant_onboarding` has a RESTRICT (not CASCADE) foreign key on
 * application_id — a deliberate design choice elsewhere in this schema too
 * (see Employer::delete()'s docblock for the same RESTRICT-not-CASCADE
 * reasoning on a different table), protecting onboarding history from
 * silent cascading deletes. application_id=2 has exactly one dependent row:
 * applicant_onboarding.id=2, 100% "completed", with the note field literally
 * "hi" — the same click-through dev test as the application it belongs to,
 * not real onboarding history. Deleting it first, atomically with the
 * application rows, is correct here specifically because it's confirmed
 * throwaway test data tied to the same orphaned, non-cascaded application;
 * this migration does NOT generally bypass the RESTRICT rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::delete('DELETE FROM applicant_onboarding WHERE application_id IN (2, 6)');
            DB::delete('DELETE FROM applications WHERE application_id IN (2, 6) AND job_id NOT IN (SELECT job_id FROM jobs)');
        });
    }

    /**
     * Reinserts the exact rows removed above, for symmetry — but since the
     * jobs they referenced (job_id 1, 32) don't exist, this recreates the
     * same orphaned state being fixed, not a real rollback. Included only
     * so `migrate:rollback` doesn't silently no-op; if you need these rows
     * back, restore from a database backup taken before this migration ran.
     */
    public function down(): void
    {
        DB::transaction(function () {
            DB::table('applications')->insertOrIgnore([
                [
                    'application_id' => 2,
                    'alumni_id' => 1,
                    'job_id' => 1,
                    'status' => 'Hired',
                    'cover_letter_text' => '',
                    'cover_letter_file' => '',
                    'application_answer' => '',
                    'applied_at' => '2025-07-21 20:15:22',
                ],
                [
                    'application_id' => 6,
                    'alumni_id' => 1,
                    'job_id' => 32,
                    'status' => 'Interview',
                    'cover_letter_text' => '',
                    'cover_letter_file' => '',
                    'application_answer' => '',
                    'applied_at' => '2025-08-17 20:59:51',
                ],
            ]);

            DB::table('applicant_onboarding')->insertOrIgnore([
                'id' => 2,
                'application_id' => 2,
                'checklist_id' => 1,
                'completion_percentage' => 100,
                'status' => 'completed',
                'started_at' => '2025-09-08 22:52:56',
                'completed_at' => '2025-09-08 23:17:01',
                'notes' => 'hi',
                'created_at' => '2025-09-08 22:52:56',
                'updated_at' => '2025-09-08 23:17:14',
            ]);
        });
    }
};
