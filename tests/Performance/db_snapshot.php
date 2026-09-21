<?php
/**
 * Keeps scenario runs comparable: remember the dataset boundary, and delete everything an import added afterwards.
 *
 *   php tests/Performance/db_snapshot.php snap    C:/lspu_perf/snap.json     # remember max ids
 *   php tests/Performance/db_snapshot.php restore C:/lspu_perf/snap.json     # delete rows above those ids
 *   php tests/Performance/db_snapshot.php trim    1000000                    # shrink `alumni` to the first N (drops the newer ones)
 *   php tests/Performance/db_snapshot.php stats                              # row counts
 * Perf DB only (name must contain "perf").
 */
[$_, $cmd, $arg] = $argv + [null, 'stats', null];
$db = getenv('DB_DATABASE') ?: 'lspu_eis_perf';
if (!str_contains($db, 'perf')) {
    exit("refusing: not a perf DB\n");
}
$pdo = new PDO('mysql:host=127.0.0.1;port='.(getenv('DB_PORT') ?: 3391).";dbname=$db", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET SESSION foreign_key_checks = 0');
$tables = ['user' => 'user_id', 'alumni' => 'alumni_id', 'alumni_education' => 'education_id', 'alumni_experience' => 'experience_id', 'applications' => 'application_id', 'audit_logs' => 'id'];
$max = fn (string $t, string $k) => (int) $pdo->query("SELECT COALESCE(MAX($k),0) FROM $t")->fetchColumn();

if ($cmd === 'snap') {
    $s = [];
    foreach ($tables as $t => $k) {
        $s[$t] = $max($t, $k);
    }
    file_put_contents($arg, json_encode($s));
    echo "snap: ".json_encode($s)."\n";
} elseif ($cmd === 'restore') {
    $s = json_decode(file_get_contents($arg), true);
    foreach (array_reverse($tables, true) as $t => $k) {
        $n = $pdo->exec("DELETE FROM $t WHERE $k > {$s[$t]}");
        echo "  $t: removed $n rows\n";
    }
} elseif ($cmd === 'trim') {
    $keep = (int) $arg;
    $row = $pdo->query("SELECT alumni_id, user_id FROM alumni ORDER BY alumni_id LIMIT 1 OFFSET ".($keep - 1))->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        exit("fewer than $keep alumni\n");
    }
    foreach (['alumni_experience' => 'alumni_id', 'alumni_education' => 'alumni_id', 'applications' => 'alumni_id', 'alumni' => 'alumni_id'] as $t => $k) {
        do {
            $n = $pdo->exec("DELETE FROM $t WHERE $k > {$row['alumni_id']} LIMIT 200000");
        } while ($n > 0);
        echo "  $t trimmed\n";
    }
    do {
        $n = $pdo->exec("DELETE FROM user WHERE user_role='alumni' AND user_id > {$row['user_id']} LIMIT 200000");
    } while ($n > 0);
    foreach (['user', 'alumni', 'alumni_education', 'alumni_experience', 'applications'] as $t) {
        $pdo->query("ANALYZE TABLE $t")->fetchAll();
    }
}
foreach ($tables as $t => $k) {
    echo sprintf("%-20s %10s\n", $t, number_format((int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn()));
}
