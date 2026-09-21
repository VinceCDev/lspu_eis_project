<?php

namespace App\Console\Commands;

use App\Services\ReportingSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Builds / rebuilds the Dashboard + Reports summary tables (see App\Services\ReportingSummary).
 *
 *   php artisan reporting:rebuild            queue a rebuild of every campus (a worker on the `summaries` queue runs them)
 *   php artisan reporting:rebuild 3          just campus 3 (0 = alumni without a campus)
 *   php artisan reporting:rebuild --sync     run in this process and print timings (first deploy, benchmarks)
 */
class ReportingRebuildCommand extends Command
{
    protected $signature = 'reporting:rebuild {campus? : campus_id (0 = no campus); omit for all} {--sync : run here instead of queueing}';

    protected $description = 'Rebuild the aggregate tables the Dashboard and Reports pages read.';

    public function handle(): int
    {
        $ids = $this->argument('campus') !== null ? [(int) $this->argument('campus')] : ReportingSummary::allPartitionIds();

        foreach ($ids as $id) {
            if (!$this->option('sync')) {
                DB::table('rpt_state')->updateOrInsert(['campus_id' => $id], ['dirty_at' => now()->format('Y-m-d H:i:s.v')]);
                ReportingSummary::dispatchRebuild($id);
                $this->line("queued rebuild of campus {$id}");

                continue;
            }
            $r = (new ReportingSummary())->rebuild($id);
            $this->line(sprintf('campus %d: %s alumni, %d ms, rows %s', $id, number_format($r['alumni']), $r['ms'], json_encode($r['rows'])));
        }

        return self::SUCCESS;
    }
}
