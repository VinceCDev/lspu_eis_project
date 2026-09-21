<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\ImportJob;
use App\Services\EmploymentReportImporter;
use App\Services\ImportAborted;
use App\Services\ReportingSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Imports one uploaded workbook in the background (queue "imports").
 *
 * The web request only stores the file and creates the `import_jobs` row; everything else happens here:
 * stream the workbook -> chunks of `import.chunk_size` graduates -> one short transaction per chunk (multi-row INSERTs +
 * the resume checkpoint) -> progress/heartbeat in `import_jobs` -> summary rebuild for the campus when done.
 *
 * Safe to run twice / retry: ImportJob::claim() only lets one live worker own an import, at most `import.max_concurrent`
 * imports run at once, and a retry resumes from the last committed chunk instead of starting over.
 */
class ProcessEmploymentImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Real errors tolerated before the import is marked failed (waiting for a free slot is not an error). */
    public int $maxExceptions = 3;

    public int $timeout;

    public function __construct(public int $importId)
    {
        $this->timeout = (int) config('import.job_timeout', 3600);
        // Development / tests run with QUEUE_CONNECTION=sync: the import then simply runs inside the request, as before.
        $this->onConnection(config('queue.default') === 'sync' ? 'sync' : 'database_long')->onQueue('imports');
    }

    /** Waiting behind other imports can take a while; give up only after a day. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDay();
    }

    /** @return int[] seconds between retries after a real error */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $sync = config('queue.default') === 'sync';
        [$state, $token] = ImportJob::claim($this->importId, ignoreCapacity: $sync);

        if ($state === 'gone') {
            return;   // already finished / cancelled / owned by a live worker
        }
        if ($state === 'busy') {
            $this->release(random_int(10, 20));   // all import slots taken (or not this import's turn yet)

            return;
        }

        $import = ImportJob::find($this->importId);
        $path = Storage::disk('local')->path($import->file_path);
        if (!is_file($path)) {
            ImportJob::fail($this->importId, 'The uploaded file is no longer on the server. Please upload it again.');

            return;
        }

        try {
            $summary = $this->runImport($import, $token, $path);
        } catch (ImportAborted) {
            return;   // cancelled, or another worker took over: nothing more to do here
        } catch (\Throwable $e) {
            ImportJob::requeueAfterError($this->importId, $token, $e);
            throw $e;   // the queue retries (backoff) and the next run resumes from the checkpoint
        }

        ImportJob::complete($this->importId, $token, $summary);
        Storage::disk('local')->delete($import->file_path);
        $this->afterCompletion($import, $summary);
    }

    /** Called by the queue when retries are exhausted or the job timed out. */
    public function failed(\Throwable $e): void
    {
        ImportJob::fail($this->importId, 'Import failed: '.$e->getMessage());
    }

    private function runImport(object $import, string $token, string $path): array
    {
        $id = $this->importId;
        $resume = $import->checkpoint ? json_decode($import->checkpoint, true) : null;
        $lastBeat = 0.0;

        return (new EmploymentReportImporter())->import(
            $path,
            $import->campus_id !== null ? (int) $import->campus_id : null,
            $import->year_graduated !== null ? (int) $import->year_graduated : null,
            // Between chunks (e.g. a long stretch of blank/summary rows) keep the lease alive and the numbers moving.
            static function (array $p) use ($id, $token, &$lastBeat): void {
                if (microtime(true) - $lastBeat >= 3.0) {
                    $lastBeat = microtime(true);
                    ImportJob::checkpoint($id, $token, null, (int) $p['done'], (int) $p['total']);
                }
            },
            $import->file_ext,
            $resume,
            // Runs inside each chunk's write transaction (see EmploymentReportImporter::flushBatch()).
            static function (array $state) use ($id, $token, &$lastBeat): void {
                $lastBeat = microtime(true);
                ImportJob::checkpoint($id, $token, $state, (int) $state['done'], (int) $state['total']);
            }
        );
    }

    private function afterCompletion(object $import, array $summary): void
    {
        try {
            $actor = $import->uploaded_by ? DB::table('user')->where('user_id', $import->uploaded_by)->first(['email', 'user_role']) : null;
            (new AuditLog())->log(
                (int) $import->uploaded_by,
                $actor->email ?? null,
                $actor->user_role ?? null,
                'import_employment_report',
                'alumni',
                null,
                "Imported {$summary['imported']} alumni from an employment report ({$summary['skipped']} skipped)."
            );
        } catch (\Throwable $e) {
            report($e);
        }

        // New graduates change every dashboard/report figure of this campus: rebuild its summary rows in the background.
        ReportingSummary::markDirty($import->campus_id !== null ? (int) $import->campus_id : null, rebuildNow: true);
    }
}
