<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEmploymentImport;
use App\Models\ImportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Scheduled every minute. Recovers imports whose worker died (no heartbeat for import.lease_seconds): they go back to
 * "queued" with a fresh queue job and resume from their last committed chunk. With --purge, also deletes the stored
 * workbooks of finished imports older than import.keep_days.
 */
class ImportsReapCommand extends Command
{
    protected $signature = 'imports:reap {--purge : also delete stored workbooks of finished imports older than import.keep_days}';

    protected $description = 'Re-queue imports whose worker died; optionally purge old uploaded files.';

    public function handle(): int
    {
        foreach (ImportJob::reap() as $id) {
            ProcessEmploymentImport::dispatch($id);
            $this->line("re-queued import #{$id}");
        }

        if ($this->option('purge')) {
            $old = DB::table('import_jobs')->whereIn('status', [ImportJob::COMPLETED, ImportJob::FAILED, ImportJob::CANCELLED])
                ->where('updated_at', '<', now()->subDays((int) config('import.keep_days', 7)))->where('file_path', '<>', '')->get(['id', 'file_path']);
            foreach ($old as $row) {
                Storage::disk('local')->delete($row->file_path);
                DB::table('import_jobs')->where('id', $row->id)->update(['file_path' => '']);
            }
            $this->line('purged '.count($old).' stored file(s)');
        }

        return self::SUCCESS;
    }
}
