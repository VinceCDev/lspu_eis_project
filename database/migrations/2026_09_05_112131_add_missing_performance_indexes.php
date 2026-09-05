<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive-only: adds indexes for columns identified by the performance
 * audit as full-table-scan hot spots (admin/superadmin dashboards, jobs
 * list, reminder cron). No columns, tables, or data are touched — this
 * only speeds up existing WHERE/JOIN/ORDER BY/GROUP BY lookups on tables
 * that predate this project's migrations (schema was previously
 * unversioned), so each index is guarded against already existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('alumni', 'alumni_college_index', function (Blueprint $table) {
            $table->index('college');
        });
        $this->addIndexIfMissing('alumni', 'alumni_created_at_index', function (Blueprint $table) {
            $table->index('created_at');
        });

        $this->addIndexIfMissing('alumni_experience', 'alumni_experience_current_end_date_index', function (Blueprint $table) {
            $table->index(['current', 'end_date']);
        });

        $this->addIndexIfMissing('jobs', 'jobs_status_index', function (Blueprint $table) {
            $table->index('status');
        });
        $this->addIndexIfMissing('jobs', 'jobs_created_at_index', function (Blueprint $table) {
            $table->index('created_at');
        });

        $this->addIndexIfMissing('applications', 'applications_applied_at_index', function (Blueprint $table) {
            $table->index('applied_at');
        });

        $this->addIndexIfMissing('user', 'user_user_role_status_index', function (Blueprint $table) {
            $table->index(['user_role', 'status']);
        });

        $this->addIndexIfMissing('audit_logs', 'audit_logs_entity_type_index', function (Blueprint $table) {
            $table->index('entity_type');
        });

        // reminder_logs already has single-column indexes on recipient, status,
        // and sent_at individually — this adds the composite the daily-limit
        // check actually needs (recipient + status + sent_at range together).
        $this->addIndexIfMissing('reminder_logs', 'reminder_logs_recipient_status_sent_at_index', function (Blueprint $table) {
            $table->index(['recipient', 'status', 'sent_at']);
        });
    }

    public function down(): void
    {
        $this->dropIndexIfExists('alumni', 'alumni_college_index');
        $this->dropIndexIfExists('alumni', 'alumni_created_at_index');
        $this->dropIndexIfExists('alumni_experience', 'alumni_experience_current_end_date_index');
        $this->dropIndexIfExists('jobs', 'jobs_status_index');
        $this->dropIndexIfExists('jobs', 'jobs_created_at_index');
        $this->dropIndexIfExists('applications', 'applications_applied_at_index');
        $this->dropIndexIfExists('user', 'user_user_role_status_index');
        $this->dropIndexIfExists('audit_logs', 'audit_logs_entity_type_index');
        $this->dropIndexIfExists('reminder_logs', 'reminder_logs_recipient_status_sent_at_index');
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
