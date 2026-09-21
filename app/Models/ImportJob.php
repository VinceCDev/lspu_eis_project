<?php

namespace App\Models;

use App\Services\ImportAborted;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * One uploaded "Data on Employment" workbook and its processing state (table `import_jobs`).
 *
 *   queued ──claim──> processing ──> completed
 *      ^                  │  └────> failed      (retries exhausted / unrecoverable)
 *      └── re-queued ─────┘  └────> cancelled   (by an admin; rows already imported stay)
 *
 * Concurrency limit + crash recovery both live here, not in the number of worker processes:
 *  - claim() lets at most config('import.max_concurrent') imports be "processing" at once (FIFO among the waiting ones);
 *    the others stay queued.
 *  - a worker owns an import through `run_token` and keeps it alive with `heartbeat_at` (written by every checkpoint).
 *    If the heartbeat goes stale the import is re-queued and a new worker resumes from `checkpoint`; the old worker's
 *    next checkpoint fails the `run_token` match and aborts (inside its transaction, so its chunk is rolled back).
 */
class ImportJob
{
    public const QUEUED = 'queued';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    public const ACTIVE = [self::QUEUED, self::PROCESSING];

    public static function create(array $data): int
    {
        return (int) DB::table('import_jobs')->insertGetId($data + [
            'status' => self::QUEUED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function find(int $id): ?object
    {
        return DB::table('import_jobs')->where('id', $id)->first() ?: null;
    }

    /** Queued + processing imports of a campus (null = all campuses). */
    public static function pendingCount(?int $campusId): int
    {
        $q = DB::table('import_jobs')->whereIn('status', self::ACTIVE);
        if ($campusId !== null) {
            $q->where('campus_id', $campusId);
        }

        return (int) $q->count();
    }

    /**
     * Tries to start (or take over) an import.
     *
     * @return array{0:string,1:?string} ['claimed', token] | ['busy', null] (capacity full / not its turn: retry later)
     *                                   | ['gone', null] (finished, cancelled, or another live worker owns it)
     */
    public static function claim(int $id, bool $ignoreCapacity = false): array
    {
        $max = (int) config('import.max_concurrent', 2);
        $lease = (int) config('import.lease_seconds', 120);

        // The check-and-set is serialised by a short cache lock (held for a few milliseconds).
        return Cache::lock('imports:claim', 15)->block(10, function () use ($id, $max, $lease, $ignoreCapacity) {
            $row = self::find($id);
            if (!$row) {
                return ['gone', null];
            }
            $cutoff = now()->subSeconds($lease);
            $stale = fn ($r) => $r->status === self::PROCESSING && ($r->heartbeat_at === null || Carbon::parse($r->heartbeat_at)->lt($cutoff));

            if (!($row->status === self::QUEUED || $stale($row))) {
                return ['gone', null];
            }

            if (!$ignoreCapacity) {
                $active = (int) DB::table('import_jobs')
                    ->where('status', self::PROCESSING)->where('id', '<>', $id)->where('heartbeat_at', '>=', $cutoff)->count();
                if ($active >= $max) {
                    return ['busy', null];
                }
                // FIFO: only the oldest waiting imports may take the free slots (a released job re-enters the queue behind others)
                $ahead = (int) DB::table('import_jobs')->where('id', '<', $id)
                    ->where(function ($q) use ($cutoff) {
                        $q->where('status', self::QUEUED)
                            ->orWhere(fn ($q2) => $q2->where('status', self::PROCESSING)->where(fn ($q3) => $q3->whereNull('heartbeat_at')->orWhere('heartbeat_at', '<', $cutoff)));
                    })->count();
                if ($ahead >= $max - $active) {
                    return ['busy', null];
                }
            }

            $token = bin2hex(random_bytes(16));
            DB::table('import_jobs')->where('id', $id)->update([
                'status' => self::PROCESSING,
                'run_token' => $token,
                'heartbeat_at' => now()->format('Y-m-d H:i:s.v'),
                'started_at' => $row->started_at ?? now(),
                'attempts' => DB::raw('attempts + 1'),
                'error_message' => null,
                'updated_at' => now(),
            ]);

            return ['claimed', $token];
        });
    }

    /**
     * Persists a checkpoint (or just a heartbeat). Throws ImportAborted when this worker no longer owns the import - which
     * the importer raises inside the chunk's transaction, rolling that chunk back.
     *
     * @param  array{sheet?:int,row?:int,done?:int,total?:int,email_quota?:int,result?:array}|null  $state
     */
    public static function checkpoint(int $id, string $token, ?array $state, int $processed, int $total): void
    {
        $fields = [
            'processed_rows' => $processed,
            'heartbeat_at' => now()->format('Y-m-d H:i:s.v'),
            'updated_at' => now(),
        ];
        if ($total > 0) {
            $fields['total_rows'] = max($total, $processed);
        }
        if ($state !== null) {
            $r = $state['result'];
            $fields['successful_rows'] = (int) ($r['imported'] ?? 0);
            $fields['failed_rows'] = (int) ($r['skipped'] ?? 0);
            $fields['checkpoint'] = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }

        $owned = DB::table('import_jobs')->where('id', $id)->where('run_token', $token)->where('status', self::PROCESSING);
        // MySQL reports CHANGED rows: an update that writes identical values (same millisecond, nothing new to record) returns 0
        // although we still own the import - so 0 is only "lost" when the ownership check also fails.
        if ($owned->update($fields) === 0 && !DB::table('import_jobs')->where('id', $id)->where('run_token', $token)->where('status', self::PROCESSING)->exists()) {
            throw new ImportAborted("import #{$id} is no longer owned by this worker (cancelled or taken over)");
        }
    }

    public static function complete(int $id, string $token, array $summary): void
    {
        DB::table('import_jobs')->where('id', $id)->where('run_token', $token)->update([
            'status' => self::COMPLETED,
            'processed_rows' => DB::raw('GREATEST(total_rows, processed_rows)'),
            'successful_rows' => (int) $summary['imported'],
            'failed_rows' => (int) $summary['skipped'],
            'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'checkpoint' => null,
            'run_token' => null,
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** A recoverable error: back to "queued" (the queue retries it and resumes from the checkpoint). */
    public static function requeueAfterError(int $id, string $token, \Throwable $e): void
    {
        DB::table('import_jobs')->where('id', $id)->where('run_token', $token)->update([
            'status' => self::QUEUED,
            'run_token' => null,
            'heartbeat_at' => null,
            'error_message' => mb_substr($e->getMessage(), 0, 1000),
            'updated_at' => now(),
        ]);
    }

    public static function fail(int $id, string $message): void
    {
        DB::table('import_jobs')->where('id', $id)->whereIn('status', self::ACTIVE)->update([
            'status' => self::FAILED,
            'run_token' => null,
            'error_message' => mb_substr($message, 0, 1000),
            'failed_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Cancels a queued/processing import. Rows already imported stay; a running worker stops at its next chunk. */
    public static function cancel(int $id): bool
    {
        return DB::table('import_jobs')->where('id', $id)->whereIn('status', self::ACTIVE)->update([
            'status' => self::CANCELLED,
            'run_token' => null,
            'completed_at' => now(),
            'updated_at' => now(),
        ]) > 0;
    }

    /**
     * Imports whose worker died (heartbeat older than the lease) go back to "queued"; ids of imports that need a
     * (new) queue job are returned. Also returns "queued" imports untouched for a long time, whose job may have been lost.
     *
     * @return int[]
     */
    public static function reap(): array
    {
        $cutoff = now()->subSeconds((int) config('import.lease_seconds', 120));
        $ids = [];

        $dead = DB::table('import_jobs')->where('status', self::PROCESSING)
            ->where(fn ($q) => $q->whereNull('heartbeat_at')->orWhere('heartbeat_at', '<', $cutoff))->pluck('id');
        foreach ($dead as $id) {
            DB::table('import_jobs')->where('id', $id)->where('status', self::PROCESSING)->update([
                'status' => self::QUEUED, 'run_token' => null, 'heartbeat_at' => null, 'updated_at' => now(),
                'error_message' => 'The worker stopped responding; the import was re-queued and resumes from its last checkpoint.',
            ]);
            $ids[] = (int) $id;
        }

        $lost = DB::table('import_jobs')->where('status', self::QUEUED)->where('updated_at', '<', now()->subMinutes(15))->pluck('id');
        foreach ($lost as $id) {
            DB::table('import_jobs')->where('id', $id)->update(['updated_at' => now()]);
            $ids[] = (int) $id;
        }

        return array_values(array_unique($ids));
    }

    /** 1-based place in line among WAITING imports (the ones already processing are not "in line"); 0 when not waiting. */
    public static function queuePosition(object $row): int
    {
        if ($row->status !== self::QUEUED) {
            return 0;
        }

        return 1 + (int) DB::table('import_jobs')->where('id', '<', $row->id)->where('status', self::QUEUED)->count();
    }

    /** The JSON the UI polls: small, no file paths. */
    public static function present(object $row): array
    {
        $total = (int) $row->total_rows;
        $done = (int) $row->processed_rows;
        $pct = match (true) {
            $row->status === self::COMPLETED => 100,
            $total > 0 => min(99, (int) floor($done * 100 / $total)),
            default => 0,
        };
        $out = [
            'id' => (int) $row->id,
            'campus_id' => $row->campus_id !== null ? (int) $row->campus_id : null,
            'filename' => $row->filename,
            'status' => $row->status,
            'total_rows' => $total,
            'processed_rows' => $done,
            'successful_rows' => (int) $row->successful_rows,
            'failed_rows' => (int) $row->failed_rows,
            'percent' => $pct,
            'attempts' => (int) $row->attempts,
            'queue_position' => self::queuePosition($row),
            'error_message' => $row->error_message,
            'created_at' => $row->created_at,
            'started_at' => $row->started_at,
            'completed_at' => $row->completed_at,
            'failed_at' => $row->failed_at,
        ];
        if ($row->status === self::COMPLETED && $row->summary) {
            $out['summary'] = json_decode($row->summary, true);
        }

        return $out;
    }
}
