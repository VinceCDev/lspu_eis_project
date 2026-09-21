<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Queue-backed bulk imports + aggregate ("summary") tables for the Dashboard / Reports pages.
 *
 * - queue_jobs / failed_jobs : Laravel's database queue. NOT the `jobs` table - that one holds job postings.
 * - import_jobs              : one row per uploaded workbook: status, progress, resume checkpoint, lease.
 * - rpt_*                    : pre-aggregated counts (see App\Services\ReportingSummary). Rebuilt per campus by a queue job;
 *                              the pages read these instead of GROUP BY-ing the 1M+ row alumni tables.
 *
 * Every table is created only when missing, so re-running (or running on a database where an operator created a table by
 * hand) is safe.
 */
return new class extends Migration
{
    private const OPTS = ['utf8mb4', 'utf8mb4_general_ci'];

    public function up(): void
    {
        $this->create('queue_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });

        $this->create('failed_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('uuid')->unique();
            $t->text('connection');
            $t->text('queue');
            $t->longText('payload');
            $t->longText('exception');
            $t->timestamp('failed_at')->useCurrent();
        });

        $this->create('import_jobs', function (Blueprint $t) {
            $t->id();
            $t->integer('campus_id')->nullable()->index();
            $t->integer('uploaded_by')->nullable();
            $t->string('filename', 255);
            $t->string('file_path', 500);
            $t->string('file_ext', 8);
            $t->unsignedBigInteger('file_size')->default(0);
            $t->smallInteger('year_graduated')->nullable();
            // queued | processing | completed | failed | cancelled
            $t->string('status', 16)->default('queued');
            $t->unsignedInteger('total_rows')->default(0);
            $t->unsignedInteger('processed_rows')->default(0);
            $t->unsignedInteger('successful_rows')->default(0);
            $t->unsignedInteger('failed_rows')->default(0);
            $t->unsignedSmallInteger('attempts')->default(0);
            // lease: only the worker holding run_token may write checkpoints; a stale heartbeat lets another worker take over
            $t->string('run_token', 40)->nullable();
            $t->dateTime('heartbeat_at', 3)->nullable();
            $t->mediumText('checkpoint')->nullable();   // JSON: {sheet,row,done,result:{...}} - the exact resume point
            $t->mediumText('summary')->nullable();      // JSON: final importer result (samples, skipped rows, warnings)
            $t->text('error_message')->nullable();
            $t->dateTime('started_at')->nullable();
            $t->dateTime('completed_at')->nullable();
            $t->dateTime('failed_at')->nullable();
            $t->dateTime('created_at')->nullable();
            $t->dateTime('updated_at')->nullable();
            $t->index(['status', 'id']);
        });

        // campus_id 0 = alumni without a campus; year_graduated -1 = unknown year (keeps the PK NOT NULL).
        $this->create('rpt_program', function (Blueprint $t) {
            $t->integer('campus_id');
            $t->integer('year_graduated');
            $t->string('college', 100);
            $t->string('course', 100);
            $t->unsignedInteger('alumni_count')->default(0);
            $t->unsignedInteger('created_yesterday')->default(0);
            $t->unsignedInteger('current_rows')->default(0);          // alumni LEFT JOIN current experience: one row per current job (min 1)
            $t->unsignedInteger('employed_current_rows')->default(0); // ... of which the job carries a non-empty employment_status
            $t->unsignedInteger('employed_any')->default(0);          // alumni with any job that has a non-empty employment_status
            $t->unsignedInteger('active_alumni')->default(0);         // alumni with a current / open-ended / not-yet-ended job
            $t->primary(['campus_id', 'year_graduated', 'college', 'course']);
        });

        // metric: active_status|active_location|active_sector|cur_location|cur_sector|any_location|any_sector
        $this->create('rpt_metric', function (Blueprint $t) {
            $t->string('metric', 16);
            $t->integer('campus_id');
            $t->integer('year_graduated');
            $t->string('college', 100);
            $t->string('course', 100);
            $t->string('dim_value', 50);
            $t->unsignedInteger('alumni_count')->default(0);          // DISTINCT alumni
            $t->primary(['metric', 'campus_id', 'year_graduated', 'college', 'course', 'dim_value']);
            $t->index('campus_id');
        });

        $this->create('rpt_title', function (Blueprint $t) {
            $t->integer('campus_id');
            $t->integer('year_graduated');
            $t->string('college', 100);
            $t->string('course', 100);
            $t->string('title', 255);
            $t->unsignedInteger('cnt_current')->default(0);           // current jobs with this title
            $t->unsignedInteger('cnt_employed')->default(0);          // ... with a non-empty status, title and course (report "match rate" input)
            $t->primary(['campus_id', 'year_graduated', 'college', 'course', 'title']);
            $t->index(['year_graduated', 'campus_id']);               // year-filtered reports across campuses
        });

        // Same counts WITHOUT the year: the dashboard's alignment chart and un-filtered reports read this (15x fewer rows than rpt_title).
        $this->create('rpt_title_all', function (Blueprint $t) {
            $t->integer('campus_id');
            $t->string('college', 100);
            $t->string('course', 100);
            $t->string('title', 255);
            $t->unsignedInteger('cnt_current')->default(0);
            $t->unsignedInteger('cnt_employed')->default(0);
            $t->primary(['campus_id', 'college', 'course', 'title']);
        });

        $this->create('rpt_location', function (Blueprint $t) {
            $t->integer('campus_id');
            $t->string('city', 100);
            $t->string('province', 100);
            $t->string('course', 100);
            $t->unsignedInteger('alumni_count')->default(0);
            $t->unsignedInteger('employed_count')->default(0);
            $t->primary(['campus_id', 'city', 'province', 'course']);
        });

        $this->create('rpt_state', function (Blueprint $t) {
            $t->integer('campus_id')->primary();
            $t->dateTime('dirty_at', 3)->nullable();                  // last time something in this campus changed
            $t->dateTime('build_started_at', 3)->nullable();          // data changed after this instant => the summary is stale
            $t->dateTime('built_at', 3)->nullable();
            $t->date('built_on')->nullable();                         // "employed" depends on CURDATE(): rebuilt once per day
            $t->unsignedInteger('build_ms')->default(0);
            $t->unsignedInteger('alumni_rows')->default(0);
            $t->unsignedInteger('active_users')->default(0);          // alumni whose account is 'Active' (the Alumni page's unfiltered total)
            $t->unsignedInteger('applications_total')->default(0);    // job applications by this campus's alumni (refreshed every few minutes)
            $t->unsignedInteger('applications_yesterday')->default(0);
            $t->dateTime('applications_at', 3)->nullable();
        });
    }

    public function down(): void
    {
        foreach (['rpt_state', 'rpt_location', 'rpt_title_all', 'rpt_title', 'rpt_metric', 'rpt_program', 'import_jobs', 'failed_jobs', 'queue_jobs'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function create(string $table, callable $define): void
    {
        if (Schema::hasTable($table)) {
            return;
        }
        Schema::create($table, function (Blueprint $t) use ($define) {
            $t->charset = self::OPTS[0];
            $t->collation = self::OPTS[1];
            $define($t);
        });
    }
};
