<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deployment check: are the indexes the reporting / import paths rely on really there, and (with --data) does each summary
 * partition agree with the raw tables? Exit code 1 when something is missing - docker/entrypoint.sh runs this after migrating.
 *
 *   php artisan reporting:verify            indexes + tables
 *   php artisan reporting:verify --data     ... and compare rpt_program totals with COUNT(*) of alumni per campus
 */
class ReportingVerifyCommand extends Command
{
    protected $signature = 'reporting:verify {--data : also compare summary totals with the raw tables}';

    protected $description = 'Verify the reporting indexes / summary tables exist (and optionally that the summary matches the data).';

    /** table => list of column lists; each must be the leading columns of some index */
    private const REQUIRED_INDEXES = [
        'alumni' => [
            ['campus_id', 'year_graduated'],
            ['campus_id', 'course', 'college'],
            ['course', 'college'],
            ['city', 'province', 'course'],
            ['year_graduated'],
            ['created_at'],
        ],
        'alumni_experience' => [['alumni_id']],
        'user' => [['email']],
    ];

    private const REQUIRED_TABLES = ['queue_jobs', 'failed_jobs', 'import_jobs', 'rpt_program', 'rpt_metric', 'rpt_title', 'rpt_title_all', 'rpt_location', 'rpt_state'];

    public function handle(): int
    {
        $bad = 0;
        $db = DB::connection()->getDatabaseName();

        foreach (self::REQUIRED_TABLES as $t) {
            $ok = DB::table('information_schema.tables')->where('table_schema', $db)->where('table_name', $t)->exists();
            $this->line(sprintf('  %-7s table %s', $ok ? 'ok' : 'MISSING', $t));
            $bad += $ok ? 0 : 1;
        }

        foreach (self::REQUIRED_INDEXES as $table => $wanted) {
            $byIndex = [];
            foreach (DB::table('information_schema.statistics')->where('table_schema', $db)->where('table_name', $table)
                ->orderBy('index_name')->orderBy('seq_in_index')->get(['index_name as idx', 'column_name as col']) as $r) {
                $byIndex[$r->idx][] = $r->col;
            }
            foreach ($wanted as $cols) {
                $found = null;
                foreach ($byIndex as $name => $have) {
                    if (array_slice($have, 0, count($cols)) === $cols) {
                        $found = $name;
                        break;
                    }
                }
                $this->line(sprintf('  %-7s index %s(%s)%s', $found ? 'ok' : 'MISSING', $table, implode(',', $cols), $found ? "  via {$found}" : ''));
                $bad += $found ? 0 : 1;
            }
        }

        if ($this->option('data') && $bad === 0) {
            foreach (DB::table('rpt_state')->orderBy('campus_id')->get() as $s) {
                $id = (int) $s->campus_id;
                $raw = $id === 0 ? DB::table('alumni')->whereNull('campus_id')->count() : DB::table('alumni')->where('campus_id', $id)->count();
                $sum = (int) DB::table('rpt_program')->where('campus_id', $id)->sum('alumni_count');
                $state = $s->built_at === null ? 'never built' : ($raw === $sum ? 'matches' : 'DIFFERS (rows added since the last build?)');
                $this->line(sprintf('  campus %d: alumni=%s summary=%s built_at=%s -> %s', $id, number_format($raw), number_format($sum), $s->built_at ?? '-', $state));
            }
        }

        $this->line($bad === 0 ? 'reporting:verify OK' : "reporting:verify FAILED ({$bad} problem(s))");

        return $bad === 0 ? self::SUCCESS : self::FAILURE;
    }
}
