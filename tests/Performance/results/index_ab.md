# Index A/B (same session, invisible-index toggle; min of 2 rounds)

| S0 no idx | S1 +09_09 | S2 +covering | statement |
|---:|---:|---:|---|
| 7283 ms | 4064 ms | 4003 ms | SELECT a.city, a.province, a.course, COUNT(DISTINCT a.alumni_id) AS n, COUNT(DISTINCT CASE WHEN e.current = 1 OR e.end_date IS NULL OR e.end_date >= C |
| 3972 ms | 3728 ms | 3846 ms | SELECT a.*, u.email, u.secondary_email, u.status FROM alumni a JOIN user u ON a.user_id = u.user_id WHERE u.status = ? AND a.campus_id = ? ORDER BY a. |
| 3891 ms | 3148 ms | 3467 ms | SELECT a.course, a.college, COUNT(*) AS total_graduates, SUM(CASE WHEN e.employment_status IS NOT NULL AND e.employment_status <> '' THEN 1 ELSE 0 END |
| 3533 ms | 3152 ms | 3146 ms | SELECT a.college, COUNT(DISTINCT a.alumni_id) AS graduates, COUNT(DISTINCT CASE WHEN e.current = 1 OR e.end_date IS NULL OR e.end_date >= CURDATE() TH |
| 3306 ms | 2801 ms | 2850 ms | SELECT employment_sector, a.course, COUNT(DISTINCT a.alumni_id) as employed FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id WHERE  |
| 3110 ms | 3161 ms | 3500 ms | SELECT a.campus_id, a.course, a.college, e.employment_status, COUNT(DISTINCT a.alumni_id) AS cnt FROM alumni a JOIN alumni_experience e ON e.alumni_id |
| 3053 ms | 2845 ms | 2878 ms | SELECT employment_sector, a.course, COUNT(DISTINCT a.alumni_id) as graduates FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id WHERE |
| 2965 ms | 2935 ms | 2857 ms | SELECT a.course, a.college, e.employment_status, COUNT(DISTINCT a.alumni_id) as cnt FROM alumni a JOIN alumni_experience e ON a.alumni_id = e.alumni_i |
| 2949 ms | 2970 ms | 3057 ms | SELECT location_of_work, a.course, COUNT(DISTINCT a.alumni_id) as graduates FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id WHERE  |
| 2864 ms | 2834 ms | 2730 ms | SELECT location_of_work, a.course, COUNT(DISTINCT a.alumni_id) as employed FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id WHERE e |
| 2782 ms | 2537 ms | 3403 ms | SELECT a.campus_id, a.course, a.college, COUNT(*) AS cnt FROM alumni a LEFT JOIN alumni_experience e ON e.alumni_id = a.alumni_id AND (e.current = 1 O |
| 2767 ms | 3025 ms | 2950 ms | SELECT a.course, a.college, COUNT(*) as cnt FROM alumni a LEFT JOIN alumni_experience e ON e.alumni_id = a.alumni_id AND (e.current = 1 OR e.end_date  |
| 2507 ms | 2459 ms | 2473 ms | SELECT a.course, a.college, e.title AS job_title, COUNT(*) AS cnt FROM alumni a JOIN alumni_experience e ON a.alumni_id = e.alumni_id AND e.current =  |
| 2231 ms | 2005 ms | 2234 ms | SELECT COUNT(*) as count FROM alumni a LEFT JOIN alumni_experience e2 ON e2.alumni_id = a.alumni_id AND e2.employment_status IS NOT NULL AND e2.employ |
| 2127 ms | 1837 ms | 2026 ms | SELECT location_of_work, a.course, COUNT(DISTINCT a.alumni_id) as employed FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id WHERE e |
| 2091 ms | 2024 ms | 2086 ms | SELECT location_of_work, a.course, COUNT(DISTINCT a.alumni_id) as graduates FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id WHERE  |
| 2019 ms | 1872 ms | 2036 ms | SELECT employment_sector, a.course, COUNT(DISTINCT a.alumni_id) as employed FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id WHERE  |
| 2000 ms | 1937 ms | 2146 ms | SELECT employment_sector, a.course, COUNT(DISTINCT a.alumni_id) as graduates FROM alumni_experience e JOIN alumni a ON a.alumni_id = e.alumni_id WHERE |
| 1804 ms | 1667 ms | 2011 ms | SELECT a.course, e.title FROM alumni a JOIN alumni_experience e ON a.alumni_id = e.alumni_id WHERE e.current = 1 AND a.campus_id = 8  |
| 1654 ms | 1616 ms | 1681 ms | SELECT a.course, a.college, e.title AS job_title, COUNT(*) AS cnt FROM alumni a JOIN alumni_experience e ON a.alumni_id = e.alumni_id AND e.current =  |
| 1637 ms | 289 ms | 300 ms | SELECT a.course, a.college, e.title AS job_title, COUNT(*) AS cnt FROM alumni a JOIN alumni_experience e ON a.alumni_id = e.alumni_id AND e.current =  |
| 1477 ms | 1425 ms | 1516 ms | SELECT a.city, a.province, a.course, COUNT(DISTINCT a.alumni_id) AS n, COUNT(DISTINCT CASE WHEN e.current = 1 OR e.end_date IS NULL OR e.end_date >= C |
| 1428 ms | 1338 ms | 1341 ms | SELECT COALESCE(e.location_of_work, 'Not Specified') as location_of_work, COUNT(DISTINCT a.alumni_id) as count FROM alumni a LEFT JOIN alumni_experien |
| 1393 ms | 1378 ms | 1320 ms | SELECT COALESCE(e.employment_sector, 'Not Specified') as employment_sector, COUNT(DISTINCT a.alumni_id) as count FROM alumni a LEFT JOIN alumni_experi |
| 1182 ms | 1110 ms | 1200 ms | SELECT a.college, COUNT(DISTINCT a.alumni_id) AS graduates, COUNT(DISTINCT CASE WHEN e.current = 1 OR e.end_date IS NULL OR e.end_date >= CURDATE() TH |
| 1113 ms | 1011 ms | 1179 ms | SELECT a.course, a.college, e.employment_status, COUNT(DISTINCT a.alumni_id) as cnt FROM alumni a JOIN alumni_experience e ON a.alumni_id = e.alumni_i |
| 1034 ms | 1073 ms | 1139 ms | SELECT COUNT(DISTINCT a.alumni_id) as count FROM alumni a LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id WHERE e.employment_status IS NOT  |
| 945 ms | 913 ms | 733 ms | SELECT a.course, a.college, COUNT(*) AS total_graduates, SUM(CASE WHEN e.employment_status IS NOT NULL AND e.employment_status <> '' THEN 1 ELSE 0 END |
| 906 ms | 12 ms | 12 ms | SELECT course, college FROM alumni GROUP BY course, college |
| 838 ms | 881 ms | 39 ms | SELECT a.campus_id, a.course, a.college FROM alumni a WHERE a.campus_id IN (9,10,7,8,12,11) GROUP BY a.campus_id, a.course, a.college |
| 789 ms | 723 ms | 587 ms | SELECT a.course, a.college, COUNT(*) as cnt FROM alumni a LEFT JOIN alumni_experience e ON e.alumni_id = a.alumni_id AND (e.current = 1 OR e.end_date  |
| 595 ms | 531 ms | 668 ms | SELECT employment_sector, COUNT(DISTINCT alumni_experience.alumni_id) as cnt FROM alumni_experience JOIN alumni a ON a.alumni_id = alumni_experience.a |
| 588 ms | 2739 ms | 2903 ms | SELECT COUNT(*) as total FROM alumni a JOIN user u ON a.user_id = u.user_id WHERE u.status = ? AND a.campus_id = ? |
| 584 ms | 548 ms | 612 ms | SELECT location_of_work, COUNT(DISTINCT alumni_experience.alumni_id) as cnt FROM alumni_experience JOIN alumni a ON a.alumni_id = alumni_experience.al |
| 554 ms | 540 ms | 558 ms | SELECT employment_sector, COUNT(DISTINCT alumni_experience.alumni_id) as cnt FROM alumni_experience WHERE (current = 1 OR (end_date IS NULL OR end_dat |
| 551 ms | 1227 ms | 1249 ms | SELECT COALESCE(e.employment_sector, 'Not Specified') as employment_sector, COUNT(DISTINCT a.alumni_id) as count FROM alumni a LEFT JOIN alumni_experi |
| 536 ms | 900 ms | 904 ms | SELECT COUNT(DISTINCT a.alumni_id) as count FROM alumni a LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id WHERE e.employment_status IS NOT  |
| 529 ms | 545 ms | 534 ms | SELECT location_of_work, COUNT(DISTINCT alumni_experience.alumni_id) as cnt FROM alumni_experience WHERE (current = 1 OR (end_date IS NULL OR end_date |
| 526 ms | 1236 ms | 1223 ms | SELECT COALESCE(e.location_of_work, 'Not Specified') as location_of_work, COUNT(DISTINCT a.alumni_id) as count FROM alumni a LEFT JOIN alumni_experien |
| 513 ms | 408 ms | 592 ms | SELECT COUNT(*) as count FROM alumni a LEFT JOIN alumni_experience e2 ON e2.alumni_id = a.alumni_id AND e2.employment_status IS NOT NULL AND e2.employ |
| 487 ms | 278 ms | 270 ms | SELECT a.alumni_id, a.first_name, a.middle_name, a.last_name, a.profile_pic, a.course, a.college, a.year_graduated FROM alumni a WHERE a.city = ? AND  |
| 478 ms | 310 ms | 306 ms | SELECT a.course, a.college, COUNT(*) AS total_graduates, SUM(CASE WHEN e.employment_status IS NOT NULL AND e.employment_status <> '' THEN 1 ELSE 0 END |
| 396 ms | 182 ms | 180 ms | SELECT COALESCE(e.location_of_work, 'Not Specified') as location_of_work, COUNT(DISTINCT a.alumni_id) as count FROM alumni a LEFT JOIN alumni_experien |
| 390 ms | 391 ms | 89 ms | SELECT course, college FROM alumni WHERE alumni.campus_id = 8 GROUP BY course, college |
| 382 ms | 168 ms | 180 ms | SELECT COALESCE(e.employment_sector, 'Not Specified') as employment_sector, COUNT(DISTINCT a.alumni_id) as count FROM alumni a LEFT JOIN alumni_experi |
| 371 ms | 147 ms | 168 ms | SELECT COUNT(DISTINCT a.alumni_id) as count FROM alumni a LEFT JOIN alumni_experience e ON a.alumni_id = e.alumni_id WHERE e.employment_status IS NOT  |
| 350 ms | 136 ms | 158 ms | SELECT COUNT(*) as count FROM alumni a LEFT JOIN alumni_experience e2 ON e2.alumni_id = a.alumni_id AND e2.employment_status IS NOT NULL AND e2.employ |
| 287 ms | 4 ms | 6 ms | SELECT DISTINCT year_graduated FROM alumni a WHERE year_graduated IS NOT NULL AND year_graduated != '' ORDER BY year_graduated DESC |
| 247 ms | 41 ms | 60 ms | SELECT COUNT(*) AS c FROM alumni a WHERE a.city = ? AND a.province = ? |

**Sum of all statement times:** S0 80.1s, S1 73.2s, S2 75.6s