<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Makes sure the indexes the Dashboard / Reports / Alumni-map queries benefit from actually exist.
 *
 * Why a NEW migration instead of relying on 2026_09_09_170000_add_reporting_indexes_for_bulk_alumni:
 * docker/entrypoint.sh imports database/schema.sql on a fresh database and then INSERTs a `migrations` row for every
 * migration file present, assuming schema.sql already contains their changes. schema.sql did not contain the 2026_09_09
 * indexes, so a fresh deploy recorded that migration as applied while the indexes were never created. This migration has
 * a new name (so it is pending on such a database) and every step is skipped when an index with the same leading columns
 * already exists, so it is a no-op where the indexes are present (the 09_09 migration also named its indexes differently
 * from the names its own guard checked, so matching by NAME would create duplicates).
 *
 * Each index below was A/B-tested at 1M alumni with MySQL 8 invisible indexes (tests/Performance/index_ab.php); only indexes
 * with a measured win are here. Tested and REJECTED (no measurable gain): alumni_experience (alumni_id,current,end_date),
 * alumni_experience (current,alumni_id,location_of_work,employment_sector,employment_status), and the low-cardinality
 * alumni_experience(employment_status) added by 09_09.
 *
 * Write cost: one extra B-tree insert per alumni row on bulk import (4 narrow indexes on `alumni`); storage ~15-25 MB each at 1M rows.
 */
return new class extends Migration
{
    /** index name => [columns, measured benefit] */
    private const INDEXES = [
        // "SELECT course, college FROM alumni GROUP BY ..." 906 ms -> 12 ms (covering, ordered: no scan, no temp table)
        'alumni_course_college_index' => 'course, college',
        // campus-scoped program list "WHERE campus_id IN (...) GROUP BY campus_id, course, college" 838 ms -> 39 ms
        'alumni_campus_course_college_index' => 'campus_id, course, college',
        // dashboard alumni map GROUP BY city, province, course: 7.3 s -> 4.0 s; alumniAtLocation() WHERE city=? AND province=?
        'alumni_city_province_course_index' => 'city, province, course',
        // Reports year filter (year_graduated = ?): job-title aggregate 1.6 s -> 0.29 s; distinctYears()
        'alumni_year_graduated_index' => 'year_graduated',
        // Reports campus + year filter
        'alumni_campus_id_year_graduated_index' => 'campus_id, year_graduated',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $columns) {
            if (!$this->hasIndexStartingWith('alumni', $columns)) {
                DB::statement("ALTER TABLE `alumni` ADD INDEX `{$name}` ({$columns})");
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            if ($this->named('alumni', $name)) {
                DB::statement("ALTER TABLE `alumni` DROP INDEX `{$name}`");
            }
        }
    }

    /** True when some index on the table already starts with exactly these columns (in order). */
    private function hasIndexStartingWith(string $table, string $columns): bool
    {
        $wanted = array_map('trim', explode(',', $columns));
        $rows = DB::table('information_schema.statistics')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', $table)
            ->orderBy('index_name')->orderBy('seq_in_index')
            ->get(['index_name as idx', 'column_name as col']);

        $byIndex = [];
        foreach ($rows as $r) {
            $byIndex[$r->idx][] = $r->col;
        }
        foreach ($byIndex as $cols) {
            if (array_slice($cols, 0, count($wanted)) === $wanted) {
                return true;
            }
        }

        return false;
    }

    private function named(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', $table)->where('index_name', $index)->exists();
    }
};
