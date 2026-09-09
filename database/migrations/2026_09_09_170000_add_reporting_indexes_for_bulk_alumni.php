<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive-only. After bulk alumni imports the Dashboard and General
 * Reports pages run GROUP BY / WHERE over tens of thousands of alumni and
 * alumni_experience rows; these indexes cover the column combinations
 * those pages actually filter and group on (year filter, program
 * breakdowns, employment-status buckets, current-job joins). Each is
 * guarded so re-running is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('alumni', 'alumni_year_graduated_index', function (Blueprint $table) {
            $table->index('year_graduated');
        });
        $this->addIndexIfMissing('alumni', 'alumni_course_college_index', function (Blueprint $table) {
            $table->index(['course', 'college']);
        });
        $this->addIndexIfMissing('alumni', 'alumni_campus_year_index', function (Blueprint $table) {
            $table->index(['campus_id', 'year_graduated']);
        });

        $this->addIndexIfMissing('alumni_experience', 'alumni_experience_alumni_current_index', function (Blueprint $table) {
            $table->index(['alumni_id', 'current']);
        });
        $this->addIndexIfMissing('alumni_experience', 'alumni_experience_status_index', function (Blueprint $table) {
            $table->index('employment_status');
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('alumni', 'alumni_year_graduated_index');
        $this->dropIndexIfExists('alumni', 'alumni_course_college_index');
        $this->dropIndexIfExists('alumni', 'alumni_campus_year_index');
        $this->dropIndexIfExists('alumni_experience', 'alumni_experience_alumni_current_index');
        $this->dropIndexIfExists('alumni_experience', 'alumni_experience_status_index');
    }

    private function addIndexIfMissing(string $table, string $indexName, callable $callback): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, $callback);
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        return (bool) $connection->selectOne(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );
    }
};
