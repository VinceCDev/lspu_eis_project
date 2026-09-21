<?php

namespace App\Console\Commands;

use App\Services\ReportingSummary;
use Illuminate\Console\Command;

/** Scheduled every minute: queues a rebuild for every summary partition that is stale, never built, or built on an earlier day. */
class ReportingRefreshCommand extends Command
{
    protected $signature = 'reporting:refresh';

    protected $description = 'Queue rebuilds of stale dashboard/report summary partitions.';

    public function handle(): int
    {
        if (ReportingSummary::refreshApplicationsIfDue()) {
            $this->line('refreshed application counts');
        }
        foreach (ReportingSummary::duePartitions() as $id) {
            ReportingSummary::dispatchRebuild($id);
            $this->line("queued rebuild of campus {$id}");
        }

        return self::SUCCESS;
    }
}
