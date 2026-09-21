<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Indexes for the Alumni page's name search (prefix match: `last_name LIKE 'x%' OR first_name LIKE 'x%'`).
 *
 * Measured at 1M alumni (tests/Performance/results/index_ab_names.md): a rare-name search went 1,110 ms (full primary-key scan,
 * the OR cannot use any index) -> 1 ms (index-merge union of these two). Cost: two narrow B-tree entries per imported alumnus
 * (see the import benchmark in AUDIT_QUEUE.md) and ~30 MB of storage per million rows.
 *
 * Idempotent: an index with the same leading columns is left alone.
 */
return new class extends Migration
{
    private const INDEXES = [
        'alumni_last_name_first_name_index' => ['last_name', 'first_name'],
        'alumni_first_name_index' => ['first_name'],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $cols) {
            if (!$this->hasLeadingColumns($cols)) {
                DB::statement('ALTER TABLE `alumni` ADD INDEX `'.$name.'` (`'.implode('`, `', $cols).'`)');
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            $exists = DB::table('information_schema.statistics')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->where('table_name', 'alumni')->where('index_name', $name)->exists();
            if ($exists) {
                DB::statement("ALTER TABLE `alumni` DROP INDEX `{$name}`");
            }
        }
    }

    private function hasLeadingColumns(array $wanted): bool
    {
        $byIndex = [];
        foreach (DB::table('information_schema.statistics')
            ->where('table_schema', DB::connection()->getDatabaseName())->where('table_name', 'alumni')
            ->orderBy('index_name')->orderBy('seq_in_index')->get(['index_name as idx', 'column_name as col']) as $r) {
            $byIndex[$r->idx][] = $r->col;
        }
        foreach ($byIndex as $cols) {
            if (array_slice($cols, 0, count($wanted)) === $wanted) {
                return true;
            }
        }

        return false;
    }
};
