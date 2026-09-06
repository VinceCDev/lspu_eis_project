<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employer Question feature — was a single employer_question/
 * employer_question_required pair of columns on `jobs` (and a single
 * application_answer column on `applications`), allowing only one question
 * per job. Replaced with proper child tables so a job can carry any number
 * of questions, each with its own required flag and its own answer per
 * application.
 *
 * The old columns are left in place (not dropped) — read-only leftovers
 * once the app is updated to use the new tables — so this migration is
 * purely additive and reversible via down(). Existing data is carried
 * forward automatically: each job's old single question becomes question
 * #1 in job_questions, and each application's old single answer is linked
 * to that same question #1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_questions', function ($table) {
            // job_id/application_id below are plain (signed) integer() to
            // match jobs.job_id / applications.application_id, both
            // `int(11)` (signed) in this schema — an unsigned FK column
            // can't reference a signed one (MySQL error 150).
            $table->id();
            $table->integer('job_id');
            $table->text('question_text');
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreign('job_id')->references('job_id')->on('jobs')->onDelete('cascade');
        });

        Schema::create('application_answers', function ($table) {
            $table->id();
            $table->integer('application_id');
            $table->unsignedBigInteger('job_question_id');
            $table->text('answer_text');
            $table->foreign('application_id')->references('application_id')->on('applications')->onDelete('cascade');
            $table->foreign('job_question_id')->references('id')->on('job_questions')->onDelete('cascade');
        });

        // Carry forward every job's existing single question as question #1.
        DB::statement("
            INSERT INTO job_questions (job_id, question_text, is_required, sort_order)
            SELECT job_id, employer_question, employer_question_required, 0
            FROM jobs
            WHERE employer_question IS NOT NULL AND employer_question <> ''
        ");

        // Carry forward every application's existing single answer, linked
        // to the question #1 just created for its job.
        DB::statement("
            INSERT INTO application_answers (application_id, job_question_id, answer_text)
            SELECT a.application_id, jq.id, a.application_answer
            FROM applications a
            INNER JOIN job_questions jq ON jq.job_id = a.job_id AND jq.sort_order = 0
            WHERE a.application_answer IS NOT NULL AND a.application_answer <> ''
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('application_answers');
        Schema::dropIfExists('job_questions');
    }
};
