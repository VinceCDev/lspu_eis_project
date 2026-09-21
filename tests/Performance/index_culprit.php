<?php
// Which single index causes the regression seen in index_ab.md? Toggle each candidate invisible on its own and time two statements.
$pdo = new PDO('mysql:host=127.0.0.1;port=3391;dbname=lspu_eis_perf', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$q = [
  'COUNT campus join user' => "SELECT COUNT(*) FROM alumni a JOIN user u ON a.user_id = u.user_id WHERE u.status = 'Active' AND a.campus_id = 8",
  'sector stats (all)' => "SELECT COALESCE(e.employment_sector, 'Not Specified') s, COUNT(DISTINCT a.alumni_id) c FROM alumni a LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id WHERE e.employment_status IS NOT NULL AND e.employment_status != '' GROUP BY COALESCE(e.employment_sector, 'Not Specified') ORDER BY c DESC",
];
$idx = [['alumni','alumni_year_graduated_index'],['alumni','alumni_course_college_index'],['alumni','alumni_campus_id_year_graduated_index'],['alumni','alumni_city_province_course_index'],['alumni','alumni_campus_course_college_index'],['alumni_experience','alumni_experience_alumni_id_current_index'],['alumni_experience','alumni_experience_employment_status_index'],['alumni_experience','alumni_experience_alumni_current_end_index'],['alumni_experience','alumni_experience_current_cover_index']];
$time = function ($sql) use ($pdo) { $best = INF; for ($i = 0; $i < 2; $i++) { $t = microtime(true); $pdo->query($sql)->fetchAll(); $best = min($best, microtime(true) - $t); } return round($best * 1000); };
foreach ($idx as [$t, $i]) { $pdo->exec("ALTER TABLE $t ALTER INDEX $i VISIBLE"); }
printf("%-52s %10s %10s\n", 'state', 'COUNT ms', 'sector ms');
printf("%-52s %10d %10d\n", 'all candidate indexes visible', ...array_map($time, array_values($q)));
foreach ($idx as [$t, $i]) {
    $pdo->exec("ALTER TABLE $t ALTER INDEX $i INVISIBLE");
    printf("%-52s %10d %10d\n", "only $i invisible", ...array_map($time, array_values($q)));
    $pdo->exec("ALTER TABLE $t ALTER INDEX $i VISIBLE");
}
foreach ($idx as [$t, $i]) { $pdo->exec("ALTER TABLE $t ALTER INDEX $i INVISIBLE"); }
printf("%-52s %10d %10d\n", 'ALL candidates invisible (baseline)', ...array_map($time, array_values($q)));
foreach ($idx as [$t, $i]) { $pdo->exec("ALTER TABLE $t ALTER INDEX $i VISIBLE"); }
