<?php

/**
 * Builds a SYNTHETIC alumni data set in a SCRATCH database to measure the Alumni Location map at scale.
 * Never point this at the real database: it inserts millions of fake rows.
 *
 *   php tests/Performance/seed_alumni_map_scale.php <database> [alumni=1000000] [--host=127.0.0.1] [--port=3307]
 *
 * The database must already contain the app schema (database/schema.sql + the reporting migration). Shape of the data
 * (chosen to be harsh, not flattering):
 *   - 1,500 real-looking "City, Province" places with a heavily skewed population (the biggest holds ~9% of all alumni),
 *     each with a cached coordinate, spread over the Philippines;
 *   - ~12% of alumni carry a dirty free-text city ("Brgy. 123 City17") that has NO coordinate - like the real data;
 *   - 6 campuses, 40 programs, ~40% currently employed.
 */
$db = $argv[1] ?? null;
$total = (int) ($argv[2] ?? 1000000);
$host = '127.0.0.1';
$port = 3307;
foreach ($argv as $a) {
    if (str_starts_with($a, '--host=')) { $host = substr($a, 7); }
    if (str_starts_with($a, '--port=')) { $port = (int) substr($a, 7); }
}
if (!$db || $db === 'lspu_eis') {
    fwrite(STDERR, "Refusing to run: give a scratch database name (not the real 'lspu_eis').\n");
    exit(1);
}

$m = new mysqli($host, 'root', '', $db, $port);
$m->query('SET unique_checks=0, foreign_key_checks=0, autocommit=1');
$run = static function (string $sql) use ($m): void {
    if (!$m->query($sql)) {
        fwrite(STDERR, "SQL error: {$m->error}\n".substr($sql, 0, 300)."\n");
        exit(1);
    }
};
$t0 = microtime(true);

foreach (['alumni_experience', 'alumni', 'user', 'location_geocode_cache', 'campus'] as $t) {
    $run("DELETE FROM {$t}");
}
for ($c = 1; $c <= 6; $c++) {
    $run("INSERT INTO campus (campus_id, name, type) VALUES ({$c}, 'Perf Campus {$c}', 'Regular')");
}

// 1,500 places + their coordinates (a box around the Philippines)
$provinces = ['Laguna', 'Quezon', 'Batangas', 'Cavite', 'Rizal', 'Bulacan', 'Pampanga', 'Cebu', 'Davao del Sur', 'Iloilo'];
$run('DROP TEMPORARY TABLE IF EXISTS perf_loc');
$run('CREATE TEMPORARY TABLE perf_loc (id INT PRIMARY KEY, city VARCHAR(100), province VARCHAR(100), lat DOUBLE, lng DOUBLE)');
$run("INSERT INTO perf_loc SELECT seq, CONCAT('City', seq), ELT(1 + seq MOD 10, '".implode("','", $provinces)."'),
      ROUND(6 + RAND() * 14, 5), ROUND(117 + RAND() * 9, 5) FROM seq_1_to_1500");
$run("INSERT INTO location_geocode_cache (location_key, lat, lng) SELECT CONCAT(city, ', ', province), lat, lng FROM perf_loc");

$batch = 100000;
for ($from = 1; $from <= $total; $from += $batch) {
    $to = min($total, $from + $batch - 1);
    // users
    $run("INSERT INTO user (user_id, email, password, user_role, status)
          SELECT 5000000 + seq, CONCAT('perf', seq, '@example.test'), 'x', 'alumni', 'Active' FROM seq_{$from}_to_{$to}");
    // alumni: skewed place (id = 1 + floor(1500 * u^3)), 12% dirty free-text city
    $run("INSERT INTO alumni (alumni_id, user_id, first_name, middle_name, last_name, contact, gender, civil_status, city, province,
                              college, course, verification_document, campus_id, year_graduated, created_at)
          SELECT 5000000 + s.seq, 5000000 + s.seq, CONCAT('F', s.seq MOD 500), '', CONCAT('L', s.seq MOD 20000), '0', 'F', 'Single',
                 IF(s.seq MOD 100 < 12, CONCAT('Brgy. ', s.seq MOD 9000, ' ', l.city), l.city), l.province,
                 CONCAT('College ', s.seq MOD 8), CONCAT('BS Program ', s.seq MOD 40), 'x', 1 + s.seq MOD 6, 2015 + s.seq MOD 11, NOW()
          FROM (SELECT seq, 1 + FLOOR(1500 * POW(RAND(), 3)) AS loc FROM seq_{$from}_to_{$to}) s
          JOIN perf_loc l ON l.id = s.loc");
    // ~40% currently employed
    $run("INSERT INTO alumni_experience (alumni_id, title, company, start_date, current, employment_sector)
          SELECT 5000000 + seq, CONCAT('Job ', seq MOD 300), CONCAT('Company ', seq MOD 5000), '2023-01-01', 1, 'Services'
          FROM seq_{$from}_to_{$to} WHERE seq MOD 10 < 4");
    fprintf(STDERR, "\r%d / %d alumni (%.0fs)", $to, $total, microtime(true) - $t0);
}
$run('ANALYZE TABLE alumni, alumni_experience, user, location_geocode_cache');
fprintf(STDERR, "\nDone in %.0fs\n", microtime(true) - $t0);
