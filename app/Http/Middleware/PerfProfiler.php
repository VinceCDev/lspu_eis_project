<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * TEMPORARY performance instrumentation. Zero overhead unless switched on:
 *
 *   - set  PERF_DEBUG=1  in .env   (profiles every request), OR
 *   - add  ?perf=1        to any URL (profiles just that request)
 *
 * When on it records, for the request:
 *   X-Perf-Total-Ms      wall time from middleware entry to response
 *   X-Perf-Php-Ms        total - DB time  (rough "PHP/CPU" time)
 *   X-Perf-Db-Ms         summed query execution time
 *   X-Perf-Query-Count   number of SQL statements
 *   X-Perf-Dupe-Count    queries whose exact SQL+bindings ran more than once
 *   X-Perf-Peak-Mem-Mb   peak PHP memory
 *
 * With ?perf=1&perflog=1 it also appends the full query list (SQL, ms,
 * bindings) + the top offenders to storage/logs/perf.log so you can diff
 * runs at 10k / 50k / 100k records.
 *
 * Remove this middleware (and its registration in bootstrap/app.php) once
 * the tuning work is done.
 */
class PerfProfiler
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = filter_var(env('PERF_DEBUG', false), FILTER_VALIDATE_BOOL)
            || $request->boolean('perf');

        if (!$enabled) {
            return $next($request);
        }

        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = [
                'sql' => $q->sql,
                'bindings' => $q->bindings,
                'ms' => round($q->time, 2),
            ];
        });

        $t0 = microtime(true);
        $response = $next($request);
        $totalMs = round((microtime(true) - $t0) * 1000, 1);

        $dbMs = round(array_sum(array_column($queries, 'ms')), 1);
        $count = count($queries);

        $seen = [];
        $dupes = 0;
        foreach ($queries as $q) {
            $sig = $q['sql'].'|'.json_encode($q['bindings']);
            $seen[$sig] = ($seen[$sig] ?? 0) + 1;
        }
        foreach ($seen as $n) {
            if ($n > 1) {
                $dupes += $n - 1;
            }
        }

        $response->headers->add([
            'X-Perf-Total-Ms' => $totalMs,
            'X-Perf-Php-Ms' => round($totalMs - $dbMs, 1),
            'X-Perf-Db-Ms' => $dbMs,
            'X-Perf-Query-Count' => $count,
            'X-Perf-Dupe-Count' => $dupes,
            'X-Perf-Peak-Mem-Mb' => round(memory_get_peak_usage(true) / 1048576, 1),
        ]);

        if ($request->boolean('perflog')) {
            usort($queries, static fn ($a, $b) => $b['ms'] <=> $a['ms']);
            $lines = [
                str_repeat('=', 70),
                sprintf(
                    '[%s] %s %s  total=%sms php=%sms db=%sms queries=%d dupes=%d peakMem=%sMB',
                    date('Y-m-d H:i:s'),
                    $request->method(),
                    $request->fullUrl(),
                    $totalMs,
                    round($totalMs - $dbMs, 1),
                    $dbMs,
                    $count,
                    $dupes,
                    round(memory_get_peak_usage(true) / 1048576, 1)
                ),
                '--- slowest 15 queries ---',
            ];
            foreach (array_slice($queries, 0, 15) as $i => $q) {
                $lines[] = sprintf('%2d. %6sms  %s', $i + 1, $q['ms'], $this->flatten($q['sql'], $q['bindings']));
            }
            @file_put_contents(storage_path('logs/perf.log'), implode("\n", $lines)."\n", FILE_APPEND);
        }

        return $response;
    }

    private function flatten(string $sql, array $bindings): string
    {
        foreach ($bindings as $b) {
            $sql = preg_replace('/\?/', is_numeric($b) ? (string) $b : "'".addslashes((string) $b)."'", $sql, 1);
        }

        return preg_replace('/\s+/', ' ', trim($sql));
    }
}
