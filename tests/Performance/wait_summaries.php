<?php
/**
 * Waits until every summary partition is fresh (built after its last change) and the `summaries` queue is empty, then prints how
 * long the summary lagged behind each import (import completed_at -> rpt_state.built_at). Perf DB only.
 *   php tests/Performance/wait_summaries.php [--timeout=600] [--since=<import_jobs.id>]
 */
$o = getopt('', ['timeout::', 'since::']);
$db = getenv('DB_DATABASE') ?: 'lspu_eis_perf';
if (!str_contains($db, 'perf')) {
    exit("refusing: not a perf DB\n");
}
$pdo = new PDO('mysql:host=127.0.0.1;port='.(getenv('DB_PORT') ?: 3391).";dbname=$db", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$deadline = time() + (int) ($o['timeout'] ?? 600);
$t0 = time();
do {
    $stale = (int) $pdo->query("SELECT COUNT(*) FROM rpt_state WHERE built_at IS NULL OR dirty_at > build_started_at")->fetchColumn();
    $queued = (int) $pdo->query("SELECT COUNT(*) FROM queue_jobs WHERE queue = 'summaries'")->fetchColumn();
    if ($stale === 0 && $queued === 0) {
        break;
    }
    sleep(2);
} while (time() < $deadline);
printf("summaries settled after %ds (stale partitions=%d, queued rebuilds=%d)\n", time() - $t0, $stale, $queued);

$since = (int) ($o['since'] ?? 0);
$rows = $pdo->query("SELECT i.id, i.campus_id, i.status, i.total_rows, i.successful_rows, i.failed_rows, i.created_at, i.started_at, i.completed_at, s.built_at, s.build_ms
                     FROM import_jobs i LEFT JOIN rpt_state s ON s.campus_id = i.campus_id WHERE i.id > $since ORDER BY i.id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $wait = $r['started_at'] ? strtotime($r['started_at']) - strtotime($r['created_at']) : null;
    $run = ($r['started_at'] && $r['completed_at']) ? strtotime($r['completed_at']) - strtotime($r['started_at']) : null;
    $lag = ($r['completed_at'] && $r['built_at']) ? round(strtotime($r['built_at']) - strtotime($r['completed_at']), 1) : null;
    printf("import #%d campus=%s %s rows=%s ok=%s skipped=%s queue_wait=%ss run=%ss summary_lag_after_completion=%ss (rebuild %sms)\n",
        $r['id'], $r['campus_id'], $r['status'], $r['total_rows'], $r['successful_rows'], $r['failed_rows'], $wait ?? '-', $run ?? '-', $lag ?? '-', $r['build_ms']);
}
$f = (int) $pdo->query('SELECT COUNT(*) FROM failed_jobs')->fetchColumn();
echo "failed_jobs: $f\n";
