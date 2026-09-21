<?php

/**
 * Concurrent readers of the Alumni Location map (service level) while bulk "imports" write into the same tables.
 * Scratch database only. MEASURES per-operation latency (p50/p95/p99/max) in two phases: readers alone, then readers +
 * writers. It does NOT go through HTTP/PHP-FPM (`php artisan serve` is single-threaded, so it cannot serve 20 clients).
 *
 *   DB_PORT=3399 DB_DATABASE=lspu_eis_perf CACHE_STORE=database \
 *     php tests/Performance/alumni_map_concurrency.php [--readers=20] [--writers=2] [--seconds=40]
 *
 * Reader loop (what a dashboard user does): open the map (cached dataset + viewport slice), open a location, then click
 * Next / Previous through its alumni. Writer loop: insert alumni in chunks, the shape of an import chunk (user +
 * alumni + ~40% experience rows, one transaction per chunk) - a simulation of import write pressure, not the importer.
 */
$opts = [];
foreach ($argv as $a) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $a, $m)) {
        $opts[$m[1]] = $m[2];
    }
}
$worker = $opts['worker'] ?? null;
$seconds = (int) ($opts['seconds'] ?? 40);

if ($worker === null) {
    exit(driver((int) ($opts['readers'] ?? 20), (int) ($opts['writers'] ?? 2), $seconds));
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AlumniLocationViewer;
use App\Services\AlumniMapService;
use Illuminate\Support\Facades\DB;

if (DB::connection()->getDatabaseName() === 'lspu_eis') {
    fwrite(STDERR, "Refusing to run against the real database.\n");
    exit(1);
}

$deadline = microtime(true) + $seconds;

if ($worker === 'reader') {
    $svc = new AlumniMapService();
    $viewer = new AlumniLocationViewer();
    $locs = DB::select('SELECT city, province, COUNT(*) n FROM alumni WHERE city NOT LIKE ? GROUP BY city, province ORDER BY n DESC LIMIT 40', ['Brgy.%']);
    $svc->dataset(null);                                   // warm-up: not measured
    foreach ($locs as $l) {
        $viewer->at(null, $l->city, $l->province, null, 0);
    }
    $lat = ['map' => [], 'open' => [], 'next' => []];
    $t = static fn (callable $f) => (function () use ($f) {
        $s = microtime(true);
        $f();

        return (microtime(true) - $s) * 1000;
    })();
    while (microtime(true) < $deadline) {
        $bbox = mt_rand(0, 1) ? ['north' => 14.7, 'south' => 13.6, 'east' => 121.9, 'west' => 120.7] : null;
        $lat['map'][] = $t(fn () => AlumniMapService::slice($svc->dataset(null), $bbox, $bbox ? 10 : 6));
        $l = $locs[array_rand($locs)];
        $lat['open'][] = $t(fn () => $viewer->at(null, $l->city, $l->province, null, 0));
        $i = mt_rand(0, max(0, (int) $l->n - 25));
        for ($k = 0; $k < 20 && microtime(true) < $deadline; $k++) {   // Next x10 then Previous x10
            $i += $k < 10 ? 1 : -1;
            $lat['next'][] = $t(fn () => $viewer->at(null, $l->city, $l->province, null, max(0, $i)));
            usleep(mt_rand(20000, 80000));                                   // human think time between clicks
        }
    }
    echo json_encode($lat);
    exit(0);
}

if ($worker === 'writer') {
    $rows = 0;
    $chunks = 0;
    $worst = 0.0;
    // ids above everything already there (earlier runs leave rows behind), in a private range per writer
    $base = max((int) DB::table('alumni')->max('alumni_id'), (int) DB::table('user')->max('user_id'), 20000000) + 1 + (int) ($opts['id'] ?? 0) * 10000000;
    while (microtime(true) < $deadline) {
        $s = microtime(true);
        $from = $base + $rows + 1;
        $to = $from + 4999;
        DB::transaction(function () use ($from, $to) {
            DB::statement("INSERT INTO user (user_id, email, password, user_role, status)
                SELECT seq, CONCAT('w', seq, '@example.test'), 'x', 'alumni', 'Active' FROM seq_{$from}_to_{$to}");
            DB::statement("INSERT INTO alumni (alumni_id, user_id, first_name, middle_name, last_name, contact, gender, civil_status, city, province, college, course, verification_document, campus_id, year_graduated, created_at)
                SELECT seq, seq, CONCAT('W', seq MOD 500), '', CONCAT('L', seq MOD 20000), '0', 'F', 'Single', CONCAT('City', 1 + seq MOD 1500), 'Laguna', 'College 1', CONCAT('BS Program ', seq MOD 40), 'x', 1 + seq MOD 6, 2020, NOW()
                FROM seq_{$from}_to_{$to}");
            DB::statement("INSERT INTO alumni_experience (alumni_id, title, company, start_date, current, employment_sector)
                SELECT seq, 'Job', 'Company', '2023-01-01', 1, 'Services' FROM seq_{$from}_to_{$to} WHERE seq MOD 10 < 4");
        });
        $ms = (microtime(true) - $s) * 1000;
        $worst = max($worst, $ms);
        $rows += 5000;
        $chunks++;
    }
    echo json_encode(['rows' => $rows, 'chunks' => $chunks, 'worst_chunk_ms' => round($worst)]);
    exit(0);
}

/** @return int exit code */
function driver(int $readers, int $writers, int $seconds): int
{
    $php = PHP_BINARY;
    $self = __FILE__;
    $run = static function (int $r, int $w) use ($php, $self, $seconds): array {
        $procs = [];
        $spawn = static function (string $kind, int $id) use ($php, $self, $seconds, &$procs) {
            $p = proc_open([$php, $self, "--worker={$kind}", "--seconds={$seconds}", "--id={$id}"], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $procs[] = [$kind, $p, $pipes];
        };
        for ($i = 0; $i < $r; $i++) {
            $spawn('reader', $i);
        }
        for ($i = 0; $i < $w; $i++) {
            $spawn('writer', $i);
        }
        $lat = ['map' => [], 'open' => [], 'next' => []];
        $wr = ['rows' => 0, 'chunks' => 0, 'worst_chunk_ms' => 0];
        foreach ($procs as [$kind, $p, $pipes]) {
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            proc_close($p);
            $j = json_decode($out, true);
            if (!is_array($j)) {
                fwrite(STDERR, "worker {$kind} failed: ".substr($err.$out, 0, 300)."\n");
                continue;
            }
            if ($kind === 'reader') {
                foreach ($j as $op => $vals) {
                    array_push($lat[$op], ...$vals);
                }
            } else {
                $wr['rows'] += $j['rows'];
                $wr['chunks'] += $j['chunks'];
                $wr['worst_chunk_ms'] = max($wr['worst_chunk_ms'], $j['worst_chunk_ms']);
            }
        }

        return [$lat, $wr];
    };
    $pct = static function (array $v, float $p): float {
        if (!$v) {
            return 0.0;
        }
        sort($v);

        return round($v[(int) min(count($v) - 1, floor($p * count($v)))], 1);
    };
    $report = static function (string $title, array $lat, array $wr) use ($pct): void {
        echo "\n{$title}\n";
        printf("  %-34s %8s %8s %8s %8s %8s\n", 'operation', 'count', 'p50 ms', 'p95 ms', 'p99 ms', 'max ms');
        foreach (['map' => 'open map (dataset + viewport slice)', 'open' => 'View alumni (first alumnus)', 'next' => 'Next / Previous click'] as $op => $label) {
            printf("  %-34s %8d %8s %8s %8s %8s\n", $label, count($lat[$op]), $pct($lat[$op], .5), $pct($lat[$op], .95), $pct($lat[$op], .99), $pct($lat[$op], 1));
        }
        if ($wr['chunks']) {
            printf("  writers: %s alumni rows inserted (%d chunks of 5,000), slowest chunk %d ms\n", number_format($wr['rows']), $wr['chunks'], $wr['worst_chunk_ms']);
        }
    };

    echo "Phase A: {$readers} readers, no writers, {$seconds}s\n";
    [$a, $wa] = $run($readers, 0);
    $report("A) {$readers} readers alone", $a, $wa);
    echo "\nPhase B: {$readers} readers + {$writers} import-style writers, {$seconds}s\n";
    [$b, $wb] = $run($readers, $writers);
    $report("B) {$readers} readers + {$writers} writers", $b, $wb);

    return 0;
}
