<?php

namespace App\Jobs;

use App\Services\ReportingSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Rebuilds the Dashboard/Reports summary rows of ONE campus partition (see ReportingSummary).
 *
 * ShouldBeUniqueUntilProcessing: at most one rebuild per campus is WAITING in the queue (a burst of imports/edits collapses
 * into one), while a new one may queue up as soon as a rebuild starts running - so data that changes during a rebuild is
 * always covered by the next one. The build itself takes a per-campus lock as well (two workers never rebuild the same rows).
 */
class RebuildReportingSummary implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 1800;

    public int $uniqueFor = 1800;

    public function __construct(public int $partition)
    {
        $this->onConnection(config('queue.default') === 'sync' ? 'sync' : 'database')->onQueue('summaries');
    }

    public function uniqueId(): string
    {
        return (string) $this->partition;
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $lock = Cache::lock('rpt:rebuild:'.$this->partition, 1800);
        if (!$lock->get()) {
            $this->release(15);   // another worker is rebuilding this campus right now

            return;
        }
        try {
            (new ReportingSummary())->rebuild($this->partition);
        } finally {
            $lock->release();
        }
    }
}
