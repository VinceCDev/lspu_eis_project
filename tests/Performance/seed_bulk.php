<?php
/**
 * Historical-data generator for scalability tests (set-based SQL, no framework boot).
 *
 *   php tests/Performance/seed_bulk.php --target=1000000 [--chunk=100000] [--port=3391] [--db=lspu_eis_perf]
 *
 * Tops the `alumni` table up to --target rows. Each alumnus gets: 1 `user` row, 1 `alumni`
 * row, 1 `alumni_education` row, ~60% a current `alumni_experience` row (+5% a second, ended
 * job), ~2% an `applications` row. Distribution is deliberately NOT uniform:
 *   - 6 real campuses weighted 30/20/20/20/5/5 %, real campus_programs.php course lists
 *   - graduation years 2010-2025 (the "decade of history")
 *   - 300 city/province pairs with a Zipf-like skew (a few big hubs, long tail)
 *   - 150 job titles, 3000 companies
 * Refuses to run against anything but a database whose name contains "perf".
 */

$opt = getopt('', ['target:', 'chunk::', 'port::', 'db::', 'host::']);
$target = (int) ($opt['target'] ?? 10000);
$chunk = (int) ($opt['chunk'] ?? 100000);
$db = $opt['db'] ?? 'lspu_eis_perf';
if (!str_contains($db, 'perf')) {
    fwrite(STDERR, "Refusing: database name must contain 'perf'.\n");
    exit(1);
}
$pdo = new PDO('mysql:host='.($opt['host'] ?? '127.0.0.1').';port='.($opt['port'] ?? 3391).";dbname=$db;charset=utf8mb4", 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
]);
$pdo->exec('SET SESSION cte_max_recursion_depth = 2000000, foreign_key_checks = 0, unique_checks = 1, sql_mode = "NO_ENGINE_SUBSTITUTION"');

$q = fn (string $sql) => $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$one = fn (string $sql) => $pdo->query($sql)->fetchColumn();
$campusIds = array_map('intval', array_column($q('SELECT campus_id, name FROM campus ORDER BY campus_id'), 'campus_id'));
$campusNames = array_column($q('SELECT campus_id, name FROM campus ORDER BY campus_id'), 'name', 'campus_id');
if (count($campusIds) !== 6) {
    fwrite(STDERR, "Expected 6 campuses (run CampusSeeder).\n");
    exit(1);
}

/* ---------- reference tables (idempotent) ---------- */
if (!$one("SHOW TABLES LIKE 'perf_prog'")) {
    $pdo->exec('CREATE TABLE perf_prog (campus_id INT, idx INT, cnt INT, college VARCHAR(100), course VARCHAR(100), PRIMARY KEY (campus_id, idx))');
    $pdo->exec('CREATE TABLE perf_place (idx INT PRIMARY KEY, city VARCHAR(100), province VARCHAR(100))');
    $pdo->exec('CREATE TABLE perf_name (kind CHAR(1), idx INT, name VARCHAR(60), PRIMARY KEY (kind, idx))');
    $pdo->exec('CREATE TABLE perf_title (idx INT PRIMARY KEY, title VARCHAR(255))');
    $pdo->exec('CREATE TABLE perf_company (idx INT PRIMARY KEY, company VARCHAR(255))');

    $cfg = require dirname(__DIR__, 2).'/app/Config/campus_programs.php';
    $ins = $pdo->prepare('INSERT INTO perf_prog VALUES (?,?,?,?,?)');
    foreach ($campusIds as $cid) {
        $list = [];
        foreach ($cfg[$campusNames[$cid]] ?? [] as $college => $courses) {
            foreach ($courses as $c) {
                $list[] = [$college, $c];
            }
        }
        foreach ($list as $i => [$college, $course]) {
            $ins->execute([$cid, $i, count($list), $college, $course]);
        }
    }

    $provinces = ['Laguna', 'Quezon', 'Batangas', 'Cavite', 'Rizal', 'Metro Manila', 'Bulacan', 'Pampanga', 'Cebu', 'Davao del Sur'];
    $cities = ['San Pablo City', 'Santa Cruz', 'Calamba', 'Los Baños', 'Biñan', 'Cabuyao', 'Nagcarlan', 'Liliw', 'Siniloan', 'Pagsanjan', 'Lumban', 'Kalayaan', 'Famy', 'Mabitac', 'Pakil', 'Paete', 'Pangil', 'Magdalena', 'Majayjay', 'Rizal', 'Victoria', 'Alaminos', 'Bay', 'Calauan', 'Lucena', 'Tiaong', 'Candelaria', 'Sariaya', 'Lopez', 'Gumaca', 'Batangas City', 'Lipa', 'Tanauan', 'Dasmariñas', 'Bacoor', 'Imus', 'Antipolo', 'Taytay', 'Quezon City', 'Manila'];
    $ip = $pdo->prepare('INSERT INTO perf_place VALUES (?,?,?)');
    for ($i = 0; $i < 300; $i++) {
        $ip->execute([$i, $cities[$i % count($cities)].($i >= count($cities) ? ' '.intdiv($i, count($cities)) : ''), $provinces[($i * 7) % count($provinces)]]);
    }
    $first = ['Maria', 'Juan', 'Jose', 'Ana', 'Mark', 'John', 'Liza', 'Carlo', 'Nicole', 'Ryan', 'Angel', 'Kevin', 'Joy', 'Paul', 'Grace', 'James', 'Jane', 'Mike', 'Rose', 'Ken'];
    $last = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Tomas', 'Andres', 'Castillo', 'Flores', 'Villanueva', 'Ramos', 'Aquino', 'Navarro', 'Salazar', 'Dela Cruz', 'Gonzales', 'Perez'];
    $in = $pdo->prepare('INSERT INTO perf_name VALUES (?,?,?)');
    for ($i = 0; $i < 100; $i++) {
        $in->execute(['F', $i, $first[$i % 20].($i >= 20 ? ' '.chr(65 + intdiv($i, 20)).'.' : '')]);
        $in->execute(['L', $i, $last[$i % 20].($i >= 20 ? '-'.chr(64 + intdiv($i, 20)) : '')]);
    }
    $roles = ['Software Developer', 'Web Developer', 'Accountant', 'Teacher', 'Nurse', 'Sales Associate', 'Customer Service Rep', 'Encoder', 'Admin Assistant', 'Engineer', 'Technician', 'Chef', 'Front Desk Officer', 'Police Officer', 'Bank Teller', 'HR Officer', 'Marketing Associate', 'Farmer', 'Analyst', 'Supervisor'];
    $it = $pdo->prepare('INSERT INTO perf_title VALUES (?,?)');
    for ($i = 0; $i < 150; $i++) {
        $it->execute([$i, $roles[$i % 20].($i >= 20 ? ' '.chr(64 + intdiv($i, 20)) : '')]);
    }
    $pdo->beginTransaction();
    $ic = $pdo->prepare('INSERT INTO perf_company VALUES (?,?)');
    for ($i = 0; $i < 3000; $i++) {
        $ic->execute([$i, "Company $i Corp."]);
    }
    $pdo->commit();
}

/* ---------- employers/jobs (small, once) ---------- */
$pass = password_hash('Perf#1234', PASSWORD_BCRYPT, ['cost' => 10]);
if ((int) $one('SELECT COUNT(*) FROM jobs') === 0) {
    $pdo->exec("INSERT INTO user (email,password,user_role,status) SELECT CONCAT('employer', n, '@seed.invalid'), '$pass', 'employer', 'Active' FROM (WITH RECURSIVE s AS (SELECT 1 n UNION ALL SELECT n+1 FROM s WHERE n<300) SELECT n FROM s) t");
    $pdo->exec("INSERT INTO employer (user_id,company_name,company_location,contact_email,contact_number,industry_type,nature_of_business,tin,company_type,accreditation_status,document_file) SELECT user_id, CONCAT('Employer ', user_id), 'Laguna', email, '0917', 'IT', 'Services', '000', 'Private', 'Accredited', '' FROM user WHERE user_role='employer'");
    $pdo->exec("INSERT INTO jobs (employer_id,title,type,location,status,created_at,description,requirements,qualifications,employer_question) SELECT e.user_id, CONCAT('Job ', n), 'Full-time', 'Laguna', 'Active', CURDATE() - INTERVAL (n % 200) DAY, 'desc','req','qual','' FROM employer e JOIN (WITH RECURSIVE s AS (SELECT 1 n UNION ALL SELECT n+1 FROM s WHERE n<4) SELECT n FROM s) k");
}
$jobMin = (int) $one('SELECT MIN(job_id) FROM jobs');
$jobCnt = (int) $one('SELECT COUNT(*) FROM jobs');

/* ---------- admins: 1 per campus + superadmin (idempotent) ---------- */
foreach ($campusIds as $i => $cid) {
    $email = 'admin.campus'.($i + 1).'@perf.invalid';
    if (!$one("SELECT COUNT(*) FROM user WHERE email='$email'")) {
        $pdo->exec("INSERT INTO user (email,password,user_role,status,last_login) VALUES ('$email','$pass','admin','Active',NOW())");
        $uid = (int) $pdo->lastInsertId();
        $pdo->exec("INSERT INTO administrator (user_id,first_name,last_name,campus_id,address) VALUES ($uid,'Admin','Campus".($i + 1)."',$cid,'x')");
    }
}
if (!$one("SELECT COUNT(*) FROM user WHERE email='super@perf.invalid'")) {
    $pdo->exec("INSERT INTO user (email,password,user_role,status,last_login) VALUES ('super@perf.invalid','$pass','superadmin','Active',NOW())");
    $uid = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO administrator (user_id,first_name,last_name,campus_id,address) VALUES ($uid,'Super','Admin',NULL,'x')");
}
$pdo->exec("REPLACE INTO site_settings (setting_key, setting_value) VALUES ('notify_new_account_email','0')");

/* ---------- alumni top-up ---------- */
$current = (int) $one('SELECT COUNT(*) FROM alumni');
echo "alumni now: $current, target: $target\n";
$H = 'MOD(u.user_id * 2654435761, 4294967296)';
while ($current < $target) {
    $n = min($chunk, $target - $current);
    $t0 = microtime(true);
    $pdo->beginTransaction();
    $u0 = (int) $one('SELECT COALESCE(MAX(user_id),0)+1 FROM user');
    $pdo->exec("INSERT INTO user (email,password,user_role,status,created_at)
        SELECT CONCAT('perf.', $u0 + n - 1, '@seed.invalid'), '$pass', 'alumni', 'Active', NOW() - INTERVAL (n % 3650) DAY
        FROM (WITH RECURSIVE s AS (SELECT 1 n UNION ALL SELECT n+1 FROM s WHERE n<$n) SELECT n FROM s) t");
    $u1 = (int) $one('SELECT MAX(user_id) FROM user');
    // campus by weight: 30/20/20/20/5/5
    $cmap = 'CASE WHEN h%100<30 THEN '.$campusIds[0].' WHEN h%100<50 THEN '.$campusIds[1].' WHEN h%100<70 THEN '.$campusIds[2].' WHEN h%100<90 THEN '.$campusIds[3].' WHEN h%100<95 THEN '.$campusIds[4].' ELSE '.$campusIds[5].' END';
    $pdo->exec("INSERT INTO alumni (user_id,first_name,middle_name,last_name,birthdate,contact,gender,civil_status,city,province,year_graduated,college,course,campus_id,verification_document,created_at)
        SELECT b.user_id, f.name, NULL, l.name, MAKEDATE(1985 + b.h % 15, 1 + b.h % 300), CONCAT('0917', LPAD(b.h % 10000000, 7, '0')),
               IF(b.h % 2, 'Male', 'Female'), 'Single', pl.city, pl.province, 2010 + (b.h DIV 7919) % 16,
               pr.college, pr.course, b.cid, '', MAKEDATE(2010 + (b.h DIV 7919) % 16, 1) + INTERVAL 150 + (b.h DIV 31) % 200 DAY
        FROM (SELECT user_id, h, $cmap AS cid FROM (SELECT u.user_id, $H AS h FROM user u WHERE u.user_id BETWEEN $u0 AND $u1) x) b
        JOIN perf_prog pr ON pr.campus_id = b.cid AND pr.idx = (b.h DIV 100) % pr.cnt
        JOIN perf_place pl ON pl.idx = FLOOR(300 * POW(((b.h DIV 13) % 10000) / 10000, 2.5))
        JOIN perf_name f ON f.kind='F' AND f.idx = (b.h DIV 17) % 100
        JOIN perf_name l ON l.kind='L' AND l.idx = (b.h DIV 19) % 100");
    $pdo->exec("INSERT INTO alumni_education (alumni_id,degree,school,end_date,current)
        SELECT alumni_id, course, 'Laguna State Polytechnic University', MAKEDATE(year_graduated, 152), 0 FROM alumni WHERE user_id BETWEEN $u0 AND $u1");
    $st = "ELT(1 + (MOD(a.user_id*2654435761,4294967296) DIV 5) % 10, 'Regular','Regular','Contractual','Contractual','Probational','Probationary','Self-Employed','Self-employed','',NULL)";
    $pdo->exec("INSERT INTO alumni_experience (alumni_id,title,company,start_date,end_date,current,description,location_of_work,employment_status,employment_sector)
        SELECT a.alumni_id, t.title, c.company, MAKEDATE(a.year_graduated, 200) + INTERVAL (a.user_id % 300) DAY, NULL, 1,
               CONCAT('Industry: Services\nMonthly income: ', 10000 + (a.user_id % 40) * 1000, '\nCompany address: Laguna\nImported from Data on Employment report.'),
               IF(a.user_id % 10 = 0, 'Abroad', 'Local'), $st, IF(a.user_id % 3 = 0, 'Government', 'Private')
        FROM alumni a
        JOIN perf_title t ON t.idx = (MOD(a.user_id*2654435761,4294967296) DIV 17) % 150
        JOIN perf_company c ON c.idx = (MOD(a.user_id*2654435761,4294967296) DIV 19) % 3000
        WHERE a.user_id BETWEEN $u0 AND $u1 AND a.user_id % 10 < 6");
    // ~5% of alumni also have an earlier, ended job
    $pdo->exec("INSERT INTO alumni_experience (alumni_id,title,company,start_date,end_date,current,description,location_of_work,employment_status,employment_sector)
        SELECT a.alumni_id, t.title, c.company, MAKEDATE(a.year_graduated, 100), MAKEDATE(a.year_graduated, 300), 0, 'Previous job', 'Local', 'Contractual', 'Private'
        FROM alumni a
        JOIN perf_title t ON t.idx = (a.user_id DIV 7) % 150
        JOIN perf_company c ON c.idx = (a.user_id DIV 11) % 3000
        WHERE a.user_id BETWEEN $u0 AND $u1 AND a.user_id % 20 = 3");
    $pdo->exec("INSERT INTO applications (alumni_id,job_id,status,applied_at)
        SELECT a.alumni_id, $jobMin + (a.user_id % $jobCnt), 'Pending', NOW() - INTERVAL (a.user_id % 400) DAY
        FROM alumni a WHERE a.user_id BETWEEN $u0 AND $u1 AND a.user_id % 50 = 7");
    $pdo->commit();
    $current += $n;
    printf("  +%d alumni in %.1fs  (total %d)\n", $n, microtime(true) - $t0, $current);
}

/* ---------- alignment_cache warm (so the test measures steady state, never the live Gemini API) ---------- */
$labels = ['Highly Aligned', 'Moderately Aligned', 'Slightly Aligned', 'Not Aligned'];
$courses = array_column($q('SELECT DISTINCT course FROM perf_prog'), 'course');
$titles = array_column($q('SELECT title FROM perf_title'), 'title');
$pdo->beginTransaction();
$ia = $pdo->prepare('INSERT IGNORE INTO alignment_cache (cache_key, course, job_title, label) VALUES (?,?,?,?)');
foreach ($courses as $ci => $course) {
    foreach ($titles as $ti => $title) {
        $ia->execute([md5(strtolower(trim($course)).'|'.strtolower(trim($title))), $course, $title, $labels[($ci + $ti) % 4]]);
    }
}
$pdo->commit();

foreach (['user', 'alumni', 'alumni_education', 'alumni_experience', 'applications'] as $t) {
    $q("ANALYZE TABLE $t");
}
foreach ($q("SELECT table_name t, table_rows r, ROUND(data_length/1048576) data_mb, ROUND(index_length/1048576) idx_mb FROM information_schema.tables WHERE table_schema='$db' AND table_name IN ('user','alumni','alumni_education','alumni_experience','applications') ORDER BY table_rows DESC") as $r) {
    printf("  %-20s rows~%-9s data %5s MB  index %5s MB\n", $r['t'], $r['r'], $r['data_mb'], $r['idx_mb']);
}
