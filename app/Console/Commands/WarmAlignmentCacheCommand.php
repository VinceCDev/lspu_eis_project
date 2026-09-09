<?php

namespace App\Console\Commands;

use App\Models\AlignmentCache;
use App\Services\AlignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pre-computes course/job-title alignment labels into `alignment_cache` so
 * the Dashboard and Reports never have to call Gemini inline. Run this once
 * after a large alumni import (the web request path only classifies a
 * handful of new pairs per page and falls back to a local guess for the
 * rest, so the cache would otherwise fill in very slowly).
 *
 *   php artisan alignment:warm
 */
class WarmAlignmentCacheCommand extends Command
{
    protected $signature = 'alignment:warm {--limit=0 : Stop after N Gemini calls (0 = no limit)}';

    protected $description = 'Populate alignment_cache for every course/job-title pair currently in the data.';

    public function handle(): int
    {
        if (trim((string) env('GEMINI_API_KEY', '')) === '') {
            $this->warn('GEMINI_API_KEY is not set — the app already falls back to a local keyword'
                .' classifier for alignment, so there is nothing to warm. Set the key and re-run'
                .' if you want AI-graded labels.');

            return self::SUCCESS;
        }

        $pairs = DB::select(
            "SELECT DISTINCT a.course, e.title AS job_title
             FROM alumni a
             JOIN alumni_experience e ON e.alumni_id = a.alumni_id
             WHERE a.course <> '' AND e.title <> '' AND e.title <> 'Not specified'"
        );

        $cache = new AlignmentCache();
        $service = new AlignmentService();
        $service->allowUnlimitedLookups();

        $limit = (int) $this->option('limit');
        $done = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar(count($pairs));
        $bar->start();

        foreach ($pairs as $p) {
            if ($cache->get($p->course, $p->job_title) !== null) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $service->classifyOne($p->course, $p->job_title); // persists to cache
            $done++;
            $bar->advance();

            if ($limit > 0 && $done >= $limit) {
                break;
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Classified {$done} new pair(s); {$skipped} already cached; ".count($pairs)." total.");

        return self::SUCCESS;
    }
}
