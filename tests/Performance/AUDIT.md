# LSPU EIS — Performance & Scalability Audit (Dashboard, Reports, Bulk Import)

**Scope:** Admin/Superadmin Dashboard and Reports, the Alumni list/search, and the "Data on Employment" bulk import — under 6-campus, 100K-records-per-upload, 1M+ historical-record conditions.
**Method:** profile first → load-test second → find the bottleneck third → optimise fourth → re-run the same tests fifth. Everything below is labelled **[MEASURED]** (observed in this audit), **[ESTIMATED]** (arithmetic from measured values) or **[THEORETICAL]** (reasoned from code/config, not observed). Nothing is invented.

---

## 0. The answers (short)

| # | Question | Current build (before) | Optimised build (after) |
|---|---|---|---|
| 1 | One admin uploads 100,000 records without slowing everyone else? | **No.** A 100K file dies with an out-of-memory fatal after 77 s and imports **0 rows** [MEASURED]. A file the parser can survive imports at ≈16 rows/s, so the 600 s request limit stops it after ≈9–10K rows [MEASURED rate → ESTIMATED limit]. | **Yes.** 100K rows in **43 s** (2,350 rows/s); other users' P95 stayed **48 ms** [MEASURED, 1M-row DB, 4 CPUs]. |
| 2 | Six admins upload 100K each at the same time? | **No.** 6 × 50K files (the most the old parser can read): none finished. Warm cache: 9,314 rows imported in 6 min, PHP memory **5.95 GB**, free RAM down to 72 MB. Cold cache: ≈1,300 rows in 5 min, 6.4 GB, 27 of 102 reader requests timed out [MEASURED]. | **Yes, with one caveat.** 6/6 finished in **147–170 s** when the dashboards were cached; when the DB was also busy rebuilding cold dashboards, 5/6 finished in 537–596 s and the 6th hit the 600 s script limit at 98.9 % [MEASURED]. |
| 3 | Usable while 600,000 records are importing? | **Not applicable / no.** The old build cannot import 600K rows: at the measured 26 rows/s (6 uploads together) it would need ≈6 h. Readers stayed fast only because the imports were crawling (warm cache: P50 36 ms, P95 1.9 s, P99 15 s, 4 timeouts) [MEASURED + ESTIMATED]. | **Yes** for cached pages with 10 readers: 1,861 requests during the import window, **P50 74 ms / P95 3.4 s / P99 13 s, 0 errors, 0 timeouts**. **Worst case (20 readers + 6 imports, warm): P50 43 ms but P95 40 s / P99 53 s**, 0 errors — 6 imports hold 6 of 16 workers for ≈150 s and MySQL runs at 3.0 of 4 cores, so ≈10 % of requests (even cached and no-DB pages) queue for 30–50 s [MEASURED]. Not for cold dashboards/searches (see 4). |
| 4 | Dashboard & Reports responsive with 1M+ historical records? | **Only when cached.** Cached: 25–90 ms. Cache miss: dashboard stats **98 s** at 1M, > 180 s at 2M; report summary 40 s at 1M [MEASURED]. | **Cached: yes. Cold: no.** Cold compute is still 20–77 s at 1M (campus admin 20 s, superadmin 77 s, single user) because the SQL work is unchanged — the fix hides it behind a stampede-safe stale-while-revalidate cache. Real fix = pre-aggregated summary tables (P1) [MEASURED]. |
| 5 | What happens to dashboards/reports during imports? | **Depends on the cache.** Cold cache: reader throughput collapses 7.2 → 1.0 → 0.3 req/s with 0 → 1 → 6 imports and timeouts 3 → 12 → 27 (88 with 20 readers) — a cold-rebuild stampede plus memory pressure. **Warm cache: readers are largely unaffected** — 1 upload: P95 191 ms over 3,930 requests; 6 uploads: P95 1.9 s, P99 15 s, 4 timeouts [MEASURED]. The old importer is gentle on the DB only because it is 100× slower. | Warm cache, 6 × 100K at once: P95 3.4 s, P99 13 s, 0 errors (the DB is busier for ≈3 minutes instead of crawling for hours) [MEASURED]. Cold cache: same rebuild cost as before. |
| 6 | Current scalability limit? | See §5. Cold dashboard usable to ≈100K alumni; degraded at 500K; broken at 1M; import limited to ≈10K rows/request. | Import: 600K rows in ≈2.5 min (6 × 100K at once) [MEASURED]; but 6 concurrent web-tier imports + 20 active readers on 4 vCPU already produce a 40 s P95 tail → run imports in a worker pool of ≤ 2. Reads: bounded by the cold-compute cost (≈1M alumni ≈ 1 min per rebuild). |
| 7 | What must change? | §7 (P0–P3) and the ready-to-apply patch `tests/Performance/optimizations.patch`. | |

---

## 1. Test environment — what was and was not tested

**Real, observed:** the app's own code and SQL, real HTTP through a multi-threaded Apache/PHP 8.2 with **16 worker threads (= prod `pm.max_children = 16`)**, sessions and cache in the database and `QUEUE_CONNECTION=sync` (as `docker-compose.yml`), a scratch **MySQL 9.6** loaded with the project's `schema.sql`, `innodb_buffer_pool_size = 384 MB` (as `docker-compose.yml`), the real importer fed real `.xlsx` files in the "Data on Employment" layout.

| Item | Test rig | Production (`docker-compose.yml`, `DEPLOY-VPS.md`) | Effect on conclusions |
|---|---|---|---|
| Hardware | Windows 11 laptop, i5-1334U (2 P + 8 E cores), 15.7 GB. Concurrency scenarios pinned the web+DB stack to **4 logical E-cores** to approximate the 4-vCPU KVM; the load generator ran on other cores | Hostinger KVM 4 (4 vCPU) | Absolute times differ; **ratios and failure modes transfer**. The dataset ladder (§3.1) ran on 12 threads, unpinned |
| DB | MySQL **9.6.0** | MySQL **8.0** | Same optimizer family; EXPLAIN plans matched what 8.0 produces for these query shapes, but 8.0 was not run |
| Web | Apache 2.4 mod_php (threads) | nginx + php-fpm (processes) | Same 16-way concurrency; Windows-only artefacts (JIT, dotenv race, Blade compile race) were neutralised or discarded |
| Data | Synthetic, **not** LSPU data: 6 real campuses (30/20/20/20/5/5 %), real `campus_programs.php`, 2010–2025 graduation years, skewed city list, 60 % with a current job | Real LSPU data | Realistic shape; real cardinalities/skews may move absolute numbers |
| Email / Gemini | Disabled (`notify_new_account_email=0`, no API key) so no real mail or API calls were made | Enabled by default (see risks R7, R8) | Their cost is **[THEORETICAL]** here |

**Not tested:** 5 M rows (see below), Redis/queue workers (none exist in the project), a real browser (Chart.js/Leaflet render time — "time until data is visible" below = the slowest of the page's parallel API calls; frontend rendering was **not** measured), production network/nginx, MySQL 8.0 binaries, a 2 GB+ buffer pool (recommended, not tested).
**5 M rows:** not run. At 2 M rows (1.5 GB of data+indexes vs a 384 MB buffer pool) every cold dashboard call already exceeded the 180 s client timeout, so a 5 M run could only have produced more timeouts; 5 M is **[ESTIMATED]** at ≥ 3× the 2 M cost.
**Data quality of this audit:** the laptop went into standby three times (battery, 3-min idle policy) — every run whose sampler showed a clock gap was **discarded and repeated**; runs still marked in the tables are labelled. Other desktop apps (browser, video call) added noise; the index A/B test (§3.4) was therefore done in one session with invisible indexes so that noise cancels.

---

## 2. Current architecture (how it works today)

```
Browser ──► nginx ──► php-fpm (16 workers) ──► Laravel ──► LegacyDispatcher (?action=…) ──► Controller
                                                                                            │
   Import  POST /admin_alumni?action=importEmploymentReport ── same request, same worker ───┤
   Dashboard  GET  …_dashboard?action=stats | employmentStatusByCampus | courseWorkAlignment ┤
   Reports    GET  …_reports?action=summary | fullData (export)                             ▼
                                                              Model raw SQL (DashboardStats / Report / Alumni) ──► MySQL 8 (384 MB pool)
                                        cache = `cache` table, session = `sessions` table, queue = sync (none)
```

* **Import** (`EmploymentReportImporter`): the whole workbook is loaded with PhpSpreadsheet (every cell an object), then **per graduate**: `SELECT` (email exists) → `BEGIN` → `INSERT user` → `INSERT alumni` → `INSERT education` → `INSERT experience` → `COMMIT`, plus one **bcrypt `password_hash()`**. It runs inside the HTTP request (`set_time_limit(600)`), streaming NDJSON progress. Up to 200 credential e-mails are sent synchronously over SMTP. Nothing invalidates the dashboard/report caches afterwards.
* **Dashboard** (`DashboardStats`): 17 aggregate queries for `stats` (each `alumni ⋈ alumni_experience`, `COUNT(DISTINCT …)`, `GROUP BY course/college/location/sector`), 3 for programs-by-campus, 1 that pulls **every current job row into PHP** for the alignment widget. Cached with `Cache::remember` 120 s / 300 s / 600 s — **no lock, no stale serving**.
* **Reports** (`Report`, `ReportService`): `summary` = 8 aggregate queries + Gemini alignment lookups (cached in `alignment_cache`); `fullData` (export) = **one row per alumnus** built in PHP memory and returned as one JSON document; the industry classifier can call the live Gemini API.
* **Alumni page**: calls `?action=list` = **every alumnus of the campus** (+ skills/education/experience/resume, 4 more `IN (…)` queries) as one JSON; search/filter/pagination happen in the browser. A server-side `paginatedList` exists but the UI never calls it.
* **Fresh-deploy hazard** (`docker/entrypoint.sh`): after importing `schema.sql` it records **every migration file as already applied**. `schema.sql` does not contain the `2026_09_09` reporting indexes, so a freshly deployed database silently lacks them. *Check production:* `SHOW INDEX FROM alumni;` — expect `alumni_course_college_index`, `alumni_city_province_course_index`, `alumni_year_graduated_index`.

---

## 3. Measured results

### 3.1 Dataset scalability (read side, superadmin unless stated; cache **empty** = worst case) [MEASURED]

Single-user cold times with `?perf=1` profiling; "mixed" = 10 concurrent users (dashboard ×4, reports ×3, search ×2, location ×1, navigation ×2…, cold start, 90–100 s).

| endpoint | L10k | L100k | L500k | L1m | L2m |
|---|---:|---:|---:|---:|---:|
| dash:stats (cold, superadmin) | 521ms | 3.5s | 39.4s | 98.5s | 180.0s |
| dash:employmentStatusByCampus (cold) | 155ms | 1.1s | 8.8s | 22.0s | 39.9s |
| dash:courseWorkAlignment (cold) | 122ms | 635ms | 3.5s | 9.1s | 9.9s |
| dash:stats (cold, campus admin) | 418ms | 2.3s | 20.7s | 45.0s | 225.5s |
| dashboard cached (warm) all 4 calls, max | 88ms | 83ms | 76ms | 89ms | 128.6s |
| rep:summary (cold, superadmin) | 432ms | 1.4s | 16.1s | 40.0s | 34.4s |
| rep:summary(filter year) (superadmin) | 86ms | 358ms | 4.5s | 10.0s | 24.6s |
| rep:colleges + years (cold) | 169ms | 186ms | 518ms | 827ms | 1.1s |
| rep:summary warm | 36ms | 26ms | 36ms | 57ms | 84ms |
| alumni map popup (alumniAtLocation) p95 | 58ms | 161ms | 726ms | 1.8s | 2.7s |
| search paginatedList(q) p95 | 102ms | 771ms | 4.0s | 17.2s | 26.5s |
| pagination paginatedList(page) p95 | 89ms | 619ms | 3.5s | 7.1s | 12.3s |
| alumni:list(all) campus-1 (UI path) | 173ms | 1.2s | 28.3s | 121.7s | 4.1s |
|   ... response size | 3 MB | 28 MB | 0 MB (5xx=1) | 0 MB (5xx=1) | 0 MB (5xx=1) |
| rep:fullData export campus-1 | 1.3s | 6.1s | 36.2s | 63.6s | 123.0s |

### errors on unbounded endpoints (5xx / timeout)
L10k   alumni_list_c1   5xx=0 timeouts=0 max=173ms bytes~1742KB
L10k   export_c1        5xx=0 timeouts=0 max=1.3s bytes~3619KB
L100k  alumni_list_c1   5xx=0 timeouts=0 max=1.2s bytes~14494KB
L100k  export_c1        5xx=1 timeouts=0 max=6.1s bytes~0KB
L500k  alumni_list_c1   5xx=1 timeouts=0 max=28.3s bytes~36KB
L500k  export_c1        5xx=1 timeouts=0 max=36.2s bytes~0KB
L1m    alumni_list_c1   5xx=1 timeouts=0 max=121.7s bytes~36KB
L1m    export_c1        5xx=1 timeouts=0 max=63.6s bytes~0KB
L2m    alumni_list_c1   5xx=1 timeouts=0 max=4.1s bytes~36KB
L2m    export_c1        5xx=1 timeouts=0 max=123.0s bytes~0KB

### mixed concurrent workload (10 VUs, cold cache start)
| dataset | reqs | req/s | avg | p50 | p95 | p99 | max | 5xx | timeouts |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| L10k | 1012 | 21.25 | 44ms | 39ms | 76ms | 123ms | 643ms | 0 | 0 |
| L100k | 1893 | 20.6 | 115ms | 60ms | 278ms | 938ms | 6.9s | 0 | 0 |
| L500k | 1371 | 14.82 | 420ms | 48ms | 1.0s | 9.8s | 39.2s | 0 | 0 |
| L1m | 484 | 4.83 | 2.4s | 43ms | 15.2s | 65.5s | 95.7s | 0 | 0 |
| L2m | 377 | 2.65 | 3.9s | 31ms | 26.8s | 120.0s | 120.0s | 3 | 4 |

* `dash:stats` > 180 s at 2M is the client timeout (censored). At 2M `courseWorkAlignment` returned HTTP 500 (out of memory) and the dashboard "warm" column is meaningless because the cache never finished warming.
* Query counts are constant (dash:stats = 17 queries at 10K and at 1M): **no N+1** in the profiled paths; latency grows because each query scans more rows.

| Resources during the 10-user mixed run | 100K | 500K | 1M |
|---|---:|---:|---:|
| mysqld CPU (avg / max cores busy) | 1.2 / 6.3 | 3.1 / 7.3 | **5.7 / 7.5** |
| PHP RSS max | 248 MB | 883 MB | 1.6 GB |
| MySQL threads running (avg / max) | 3.6 / 15 | 6.2 / 16 | 10.9 / 17 |
| Buffer-pool disk page reads per run | 1 | 236 | **45,868** (hit ratio min 98.15 %) |
| Rows read by InnoDB | 25 M | – | **221 M** (126 M by full scans) |

Working set (2M alumni): user 340 MB, alumni 550 MB, alumni_education 250 MB, alumni_experience 320 MB (data+index) ≈ 1.5 GB against a 384 MB pool.

**Where the latency comes from** (dash:stats at 1M, superadmin, cold): total 98.5 s = **DB 98.3 s (99.8 %)**, PHP ≈ 0.2 s, payload 133 KB. The statements cost: 4 × "location/sector × course" join+sort ≈ 3 s each, program×status ≈ 3 s ×2, anti-join ≈ 2.7 s, college graduates/employed ≈ 4.2 s, alumni map ≈ 6.6 s, … (EXPLAIN ANALYZE: `Nested loop … loops=1e+6`, `Sort … rows=600236`, `Table scan on <temporary>`; `tests/Performance/results/explain_1m_baseline.md`).

### 3.2 Bulk-import performance [MEASURED]

| | Current importer | Optimised importer |
|---|---:|---:|
| 1,000 rows (unpinned, idle box) | **61.6 s** = 16 rows/s (PHP CPU 53.8 s, SQL 2.2 s) | **0.6 s** |
| SQL statements per row | 4.84 (1 SELECT, 3.84 INSERT, + BEGIN/COMMIT) | 0.02 |
| Cost split | bcrypt 53.4 ms × 1,000 = **87 % of wall time and ≈ 99 % of PHP CPU**; SQL 3.6 % | multi-row INSERT, 500 rows / transaction |
| Parser memory | 12.8 KB/row: 20K → 254 MB, 50K → 676 MB, 70K → 898 MB, **100K → fatal (1 GB)** | flat: **≤ 250 MB** for 100K rows |
| 100K rows, single upload, 1M-row DB, 4 CPUs | **fatal after 77 s, 0 rows**, HTML error page inside the NDJSON stream | **100,000 / 100,000 in 43 s** |
| Request time limit (600 s) | reached at ≈9.7K rows [ESTIMATED = 600 s ÷ 61.6 s × 1,000] | not reached for a single upload |
| Data written | – | **identical** row-by-row to the old importer for the same workbook (email, status, names, dates, contact, gender, city, province, year, college, course, campus, education row, job row) [MEASURED, 1,005-row diff = 0] |

### 3.3 Concurrency scenarios (1M-row DB, stack pinned to 4 vCPU, 10 readers unless stated) [MEASURED]

Readers = 3 superadmin dashboards, 1 campus dashboard, 2 superadmin + 1 campus reports, 2 searches, 1 map popup, 2 navigation. "before" uses 50K-row files (the largest the old parser can read; 100K dies), "after" uses 100K-row files. `before_*R*` runs started with an empty cache; `…w` runs are pre-warmed (steady state). Reader latency for upload runs is measured **inside the window in which at least one import was running**. After-build runs use a 900 s client timeout, before-build runs 120 s (so before-build latencies are *capped* at 120 s).

| run | uploads | alumni rows imported | requests (req/s) | dash:stats p95 | rep:summary p95 | all p50 | all p95 | all p99 | 5xx | timeouts | mysqld cores (avg) | PHP RSS max | deadlocks | notes |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---|
| before_50k_R0 | - | 0 | 1978 (7.2) | 114.2s | 150ms | 34ms | 1.7s | 34.6s | 0 | 3 | 3.5 | 1138 MB | 0 |  |
| before_50k_R1 | 0/1 finished | 3 | 313 (0.97) | 120.0s | 120.0s | 157ms | 90.6s | 120.0s | 0 | 12 | 2.2 | 2329 MB | 0 |  |
| before_50k_R6 | 6 started, none finished when the harness left | 1,341 | 102 (0.26) | 120.0s | 120.0s | 49.2s | 120.0s | 120.0s | 0 | 27 | 1.5 | 6387 MB | 0 |  |
| before_50k_W6 | 6 started, none finished when the harness left | 5,057 | 171 (0.41) | 120.0s | 120.0s | 120.0s | 120.0s | 120.0s | 0 | 88 | 1.1 | 7735 MB | 0 |  |
| before_50k_U1x100k | 0/1 finished | 0 | 144 (2.33) | - | - | 25ms | 48ms | 69ms | 0 | 0 | 0.1 | 3532 MB | 0 |  |
| before_50k_R1w | 1 started, none finished when the harness left | 2,498 | 3930 (16.09) | 58ms | 71ms | 28ms | 191ms | 1.9s | 0 | 0 | 2.2 | 1871 MB | 0 |  |
| before_50k_R6w | 6 started, none finished when the harness left | 9,314 | 2596 (7.24) | 253ms | 11.7s | 36ms | 1.9s | 15.3s | 0 | 4 | 1.4 | 5953 MB | 0 |  |
| after_100k_R0 | - | 0 | 308 (1.24) | 120.0s | 120.0s | 264ms | 106.5s | 120.0s | 7 | 11 | 2.0 | 182 MB | 0 | HTTP 500s = Blade view-compile race on the first hits of a fresh Apache (harness artefact) |
| after_100k_R0w | - | 0 | 3107 (20.31) | 50ms | 56ms | 26ms | 492ms | 1.7s | 0 | 0 | 1.6 | 113 MB | 0 |  |
| after_100k_R1 | 1/1 finished (238-238s) | 100,000 | 291 (0.98) | 292.9s | 240.4s | 172ms | 96.4s | 240.4s | 0 | 0 | 1.9 | 193 MB | 0 |  |
| after_100k_R1w | 1/1 finished (46-46s) | 100,000 | 2298 (14.99) | 66ms | 82ms | 34ms | 178ms | 5.1s | 0 | 0 | 2.6 | 131 MB | 0 |  |
| after_100k_R3 | 3/3 finished (264-288s) | 299,999 | 344 (0.94) | 304.4s | 274.8s | 226ms | 73.6s | 274.8s | 0 | 3 | 2.0 | 221 MB | 0 |  |
| after_100k_R6 | 5/6 finished (537-596s) | 598,497 | 105 (0.29) | 303.5s | 119.9s | 617ms | 122.3s | 303.2s | 0 | 3 | 1.9 | 230 MB | 2 |  |
| after_100k_R6w | 6/6 finished (147-170s) | 599,997 | 1864 (6.72) | 212ms | 7.6s | 74ms | 3.4s | 13.2s | 0 | 0 | 2.6 | 249 MB | 0 |  |
| after_100k_W6 | 6/6 finished (244-307s) | 599,997 | 128 (0.33) | 319.5s | 284.2s | 12.6s | 270.7s | 284.2s | 0 | 0 | 2.9 | 234 MB | 1 | **INVALID: machine slept** |
| after_100k_W6w | 6/6 finished (139-164s) | 599,997 | 948 (3.55) | 37.5s | 50.8s | 43ms | 40.2s | 53.0s | 0 | 0 | 3.0 | 205 MB | 0 |  |
| after_100k_U1x100k | 1/1 finished (43-43s) | 100,000 | 376 (2.49) | - | - | 25ms | 48ms | 50ms | 0 | 0 | 0.4 | 205 MB | 0 |  |

Reading the table:
* **before, cold cache (R0 → R1 → R6 → W6):** throughput 7.2 → 1.0 → 0.3 → 0.4 req/s; PHP memory 1.1 → 2.3 → 6.4 → 7.7 GB (free RAM fell to 32 MB); **no import finished**; rows imported in 5–7 min: 3 / ≈1,300 / ≈5,000. The workers are pinned by parsers holding ≈1 GB each while MySQL is busy with 10+ concurrent cold aggregations (`Threads_running` 11–18).
* **before, warm cache (R1w, R6w):** readers stay fast (P95 191 ms / 1.9 s) but PHP memory reaches 1.9 / **5.95 GB**, free RAM 72 MB, and only 2,498 / 9,314 rows were imported in 4–6 min (10 and 26 rows/s) — on a 16 GB VPS that also hosts MySQL this is the out-of-memory scenario.
* **after / R3, R6, R6w:** all imports finish; `Innodb_rows_inserted` ≈ 800 K (3 uploads) / 695 K (6 uploads) with only 417–1,359 commits; PHP memory ≤ 250 MB.
* **deadlocks** (increase of InnoDB `lock_deadlocks` during the run): 0 in every before-run; after-build: R6 = 2, W6 = 1, none in R1 / R3 / R6w. They are absorbed by the row-by-row retry (all imports finished; ≤ 2 rows per 100K skipped as duplicate placeholder e-mails — the old importer's per-row existence check would skip the same rows). Cumulative row-lock wait: 215 s in the cold 6-upload run, 5 s in the warm one.
* The **cold-start** after-build runs (R0/R1/R3/R6/W6) are still slow for the *readers* — the cold dashboard rebuild costs the same 45–100+ s of DB time, and it competes with the import. That is the un-fixed compute cost (P1), not the import.

### 3.4 Index findings (EXPLAIN ANALYZE + controlled A/B at 1M) [MEASURED]

Method: same session, same data, MySQL 8 *invisible indexes* toggled (S0 = none, S1 = the 2026_09_09 set, S2 = + my covering candidates), min of 2 rounds, `tests/Performance/index_ab.php`.

| Statement | S0 (no idx) | with reporting indexes |
|---|---:|---:|
| `SELECT course, college FROM alumni GROUP BY …` | 906 ms | **12 ms** (`alumni_course_college_index`, covering, ordered) |
| campus program list (`campus_id IN (…) GROUP BY campus_id, course, college`) | 838 ms | **39 ms** (`campus_id, course, college`) |
| dashboard alumni map (`GROUP BY city, province, course`) | 7.3 s | **4.0 s** |
| report job-title aggregate, year filter | 1.6 s | **0.29 s** (`year_graduated`) |
| **Total time of all statements in the workload** | 80.1 s | **73.2 s (−9 %)** |
| `alumni_experience (alumni_id,current,end_date)` and the 5-column covering `(current, alumni_id, location_of_work, employment_sector, employment_status)` | – | **no measurable gain → rejected** |

Conclusions: indexes fix a handful of statements but **do not fix the dashboard** — the cost is the join + `COUNT(DISTINCT)` + sort over ≈600K rows, which no index removes. One apparent 588 → 2,739 ms "regression" in the A/B was retracted after a per-index toggle test showed 2.6–2.8 s regardless of which index was visible (buffer-pool noise). *Do not add indexes blindly:* each index costs one extra B-tree insert per imported row and 15–25 MB at 1M rows.

Other plan findings (EXPLAIN ANALYZE, 1M): search `LIKE '%term%'` over 5 columns scans `user` (999,891 rows) then probes `alumni` per row — 4.5 s + another 4 s for `COUNT(*)`; `alumniAtLocation` counts/orders without the `(city,province,…)` index; year/campus filters scan the whole table without `year_graduated`.

### 3.5 Cache behaviour [MEASURED]

* One full rebuild of every superadmin payload ≈ **41 s of DB time** at 1M, single-threaded on an idle machine (stats: chartBreakdowns 24.2 s + map 6.6 s + totals 0.7 s; byCampus 7.6 s; alignment 2.2 s), and a campus admin's set ≈ 16 s each (≈ 95 s for all six campuses). With TTLs of 120–600 s and no lock, every expiry lets **all concurrent users rebuild in parallel** (10-user cold start: MySQL 5.7 cores, `nav:settings` P99 23 s although it touches no aggregate).
* Imports never invalidate the caches, so new graduates stay invisible for up to 10 min.
* Cache candidates and what invalidates them are in §6.

---

## 4. Bottlenecks (root causes, not symptoms)

| # | Bottleneck | Evidence | Where |
|---|---|---|---|
| B1 | **`password_hash()` per imported row** (bcrypt cost 10, 53 ms) | 87 % of import wall time, ≈ 99 % of PHP CPU; 16 rows/s | `EmploymentReportImporter::importRecord` |
| B2 | **Whole workbook in memory** (PhpSpreadsheet cell objects) | 12.8 KB/row; OOM at 100K; 6 parses = 6.4–7.7 GB | `run()`/`sheetGrid()` |
| B3 | **Row-at-a-time writes**: SELECT + tx + 4 INSERT + COMMIT per row (fsync per commit) | 4.84 stmts/row | same |
| B4 | **Import runs inside the web request** (`sync` queue, 600 s limit, worker held) | request dies at 600 s; 6 imports = 6 of 16 workers | controller |
| B5 | **Dashboard/report aggregates recomputed from the raw tables** (join + `COUNT(DISTINCT)` + filesort over 0.6–1 M rows) | 99.8 % of latency is DB; 98 s at 1M | `DashboardStats`, `Report` |
| B6 | **No cache stampede protection, short TTL, no stale serving** | 10 users ⇒ 10 parallel rebuilds; MySQL 5.7 cores | controllers |
| B7 | **Unbounded endpoints**: alumni `list` (28 MB at 30K rows), `fullData`, `currentCourseJobTitles` (650K rows to PHP) | HTTP 500 from 100K–500K alumni | AlumniController, ReportController, DashboardStats |
| B8 | **Duplicate work**: the location and sector "graduates" and "employed" queries are identical (4 of 17 statements) | plan text identical | `DashboardStats::coursesPerLocation/Sector` |
| B9 | **Search/pagination by `LIKE '%x%'` + `COUNT(*)` on a join** | 4.5 s + 4 s at 1M, 17 s P95 under load | `Alumni::statusSearchClause` |
| B10 | **Buffer pool 384 MB vs 1.5 GB working set** | 45,868 disk reads/run at 1M | `docker-compose.yml` |
| B11 | **Missing reporting indexes on fresh deploys** | entrypoint marks migrations applied | `docker/entrypoint.sh` |

## 5. Crash / timeout risks and the scalability limit

| Risk | Status | Detail |
|---|---|---|
| R1 Application crash / PHP fatal | **[MEASURED]** | 100K-row import → memory fatal at 77 s, 0 rows, HTML error page inside the stream (UI shows a generic failure). `alumni list`, `fullData`, `courseWorkAlignment` → HTTP 500 at 100K–2M alumni |
| R2 Memory exhaustion | **[MEASURED]** | 6 concurrent imports: 6.4 GB (W6: 7.7 GB, 20 readers), free RAM down to 32 MB on a 15.7 GB machine. A 16 GB VPS running MySQL + PHP would swap or OOM-kill |
| R3 Request timeout | **[MEASURED/ESTIMATED]** | client timeouts 3 → 12 → 27 → 88 with 0 → 1 → 6 → 6+20 users; import capped at ≈9.7K rows by `set_time_limit(600)`; nginx `fastcgi_read_timeout 600` |
| R4 PHP worker exhaustion | **[MEASURED as symptom]** | before: 6 imports + long reads occupy the 16 workers (120 s client timeouts). After (fast imports): 6 imports still hold 6 of 16 workers for ≈150 s; with 20 active readers the P95 of *every* endpoint — even `nav:settings` — rose to 28–40 s in bursts (W6w). In nginx the same state surfaces as 502/504 **[THEORETICAL — nginx not in the rig]** |
| R5 DB connection exhaustion | not reached | peak 17 connections vs `max_connections = 151` [MEASURED] |
| R6 Locks / deadlocks | **[MEASURED]** | none in the old importer's runs; 0–2 deadlocks per run in the batched importer (only when 6 imports ran while the DB was also rebuilding cold dashboards), all recovered by the row-by-row retry (see 3.3). Row-lock waits ≈ 150–170 per 1,000 requests already at 10K (probably the per-request `sessions` update — not isolated) |
| R7 Synchronous e-mail in the import request | **[THEORETICAL]** | default `notify_new_account_email='1'` ⇒ up to 200 SMTP round-trips (TLS to Gmail) inside the request. Not run (mail disabled) |
| R8 Live Gemini API in report paths | **[MEASURED presence, THEORETICAL cost]** | `fullData` made real outbound calls (HTTP 403 without a key); with a key, up to 20 industry calls × 3 attempts × 10 s timeout, plus ≤ 10 alignment lookups per request |
| R9 Abandoned requests keep running | **[MEASURED]** | when a client disconnects during the parse phase PHP keeps parsing/importing (no output ⇒ no abort check) — the 6 "zombie" imports polluted the next benchmark until Apache was restarted |
| R10 Silent partial imports | **[MEASURED]** | each row committed separately; a killed import leaves the file half-imported with no summary |

**Scalability limit** (cold cache, the situation right after a cache expiry or import):
* **Import:** ≈9–10K rows per request [ESTIMATED from measured 16 rows/s]; > ≈80K rows/file cannot even be parsed [MEASURED: 70K ok, 100K fatal].
* **Dashboard/Reports:** comfortable ≤ 100K alumni (3.5 s cold); degraded at 500K (39 s); unusable at 1M (98 s) and beyond (>180 s at 2M) [MEASURED]. With everything cached the pages are fast at any size, but the cache is rebuilt from scratch on every expiry.
* **Alumni page:** 28 MB response at 30K rows per campus; HTTP 500 at ≈150K rows per campus [MEASURED].
* **Upload + reads:** with warm caches, one or six old-style uploads barely slowed readers on this rig (P95 191 ms / 1.9 s) — the harm of the old design is that imports **cannot complete**, hold 1–1.5 GB of RAM each and pin worker threads; with a cold cache the same uploads coincide with a reader collapse (7.2 → 1.0 req/s) that is dominated by the cache stampede [MEASURED].

---

## 6. Recommended architecture (6 campuses × 100K uploads + 1M+ history + concurrent users)

```
Admin ──► web (php-fpm) ──► validate file, store it, INSERT import_jobs(status=queued)  ──► returns job id in < 1 s
                              ▲                                              │
   UI polls /import_status?id ┘                                              ▼
                                           queue worker(s) [separate container, max 2 concurrent imports]
                                             stream-read xlsx/csv → 500-row batches → multi-row INSERT in 1 tx
                                             update import_jobs(progress); on success: HeavyCache::markStale()
                                                                                          │
   Dashboard / Reports (php-fpm)  ── read only ──►  payload cache (Redis or `cache` table) ◄── refresh job (scheduler, every 10 min + after import)
                                                       stale-while-revalidate, single builder, never computed on the request path
                                                                                          │
                                                                          summary tables (see below)  ◄── rebuilt by the refresh job
```

1. **Imports off the web tier.** Real queue worker (Laravel queue on Redis; note the project's `jobs` table is *job postings*, so use `queue_jobs`), 1–2 concurrent imports, progress in `import_jobs`. Removes the 600 s limit, frees the 16 web workers, survives client disconnects (R9, R10) and can resume.
2. **Streaming reader + batched writes** (implemented, §7 P0): constant memory, 500-row transactions, no per-row bcrypt.
3. **Summary tables for Dashboard/Reports.** Rebuild small tables (`agg_program_status(campus_id, college, course, employment_status, is_active, n)`, `agg_location_course`, `agg_sector_course`, `agg_map(city, province, course, n, employed)`, `agg_program_year`) with the *existing* SQL in a scheduled job (≈41 s at 1M, off the request path); dashboards and reports then read ≤ tens of thousands of rows: **O(groups), not O(alumni)**. Invalidation: after each import and every 10 min. Expected: cold page = milliseconds at any size.
4. **Cache layer:** Redis (or keep the DB cache) with `HeavyCache` semantics (single builder, stale-while-revalidate). Cache: dashboard totals, campus/program/employment stats, yearly aggregates, map clusters, report summaries. Do **not** cache: per-user lists, search results.
5. **Server-side pagination for the Alumni page** (`paginatedList` exists) + FULLTEXT (ngram) or prefix search instead of `LIKE '%x%'`; drop the per-page `COUNT(*)` or cache it.
6. **Server-side export** (queued job → CSV/XLSX file → download link) instead of `fullData` JSON; Excel itself caps at 1,048,576 rows.
7. **MySQL sizing:** `innodb_buffer_pool_size` 4–8 GB on a 16 GB VPS (not 384 MB) [THEORETICAL — not tested], `innodb_flush_log_at_trx_commit` unchanged; MySQL and PHP must not fight for RAM.
8. **Ops:** a `scheduler` + `worker` service in `docker-compose.yml`; slow-query log on; alert on `Threads_running`, worker saturation, queue depth.

---

## 7. Priority fixes

Status legend: ✅ implemented + measured in this audit (patch `tests/Performance/optimizations.patch`, 14 files, applies cleanly to HEAD) · 📝 recommended, not implemented.

**P0 — before production use at LSPU scale**

| Fix | Status | Proof |
|---|---|---|
| Importer: streaming xlsx/csv reader (`SpreadsheetRowStream`) | ✅ | 100K rows in ≤ 250 MB; 6 concurrent imports 249 MB total (was 7.7 GB) |
| Importer: 500-row multi-row INSERT in one transaction, one existence query per batch, row-by-row fallback | ✅ | 4.84 → 0.02 statements/row; 16 → 2,350 rows/s; DB content identical |
| Importer: no bcrypt for accounts that are never e-mailed (unusable hash `'!'` — same practical effect as the old random, discarded password); bcrypt only for the ≤ 200 e-mailed rows | ✅ | removes 87 % of the import time |
| Cache: `HeavyCache` — single builder, stale-while-revalidate, `markStale()` after import, TTL 600 s/keep 6 h | ✅ | 16/16 endpoints identical payloads; 6 uploads + 10 readers, P95 3.4 s, 0 errors |
| Guard the unbounded endpoints (alumni `list` > 25K → 413 with message; `fullData` > 50K alumni → 422 with message) | ✅ | prevents the 28–120 s then HTTP 500 |
| Ensure the reporting indexes exist (new idempotent migration + `schema.sql` updated) | ✅ | fixes B11; verified idempotent both ways |
| Run imports in a real queue worker with concurrency ≤ 2 (removes the 600 s limit — the 6th of 6 concurrent 100K uploads hit it at 98.9 % — and stops imports occupying web workers: P95 40 s tail in W6w) | 📝 | design in §6; the measured evidence is W6w vs R6w |

**P1 — before handling 1M+ records**
* Summary/aggregate tables + scheduled refresh (§6.3) — the only fix that removes the 45–100 s cold compute. 📝
* Alumni page: server-side pagination/filters, FULLTEXT/prefix search, cached totals. 📝
* `innodb_buffer_pool_size` 4–8 GB. 📝
* Queue/worker + scheduler containers in compose; async export. 📝
* Take Gemini and SMTP out of request paths (background enrichment; e-mails queued). 📝

**P2:** dedupe of the duplicate location/sector queries ✅ (17 → 15 statements); grouped alignment query instead of 650K PHP rows ✅; chunked `AlignmentCache::getMany` (PDO 65,535-placeholder limit once distinct job titles grow) ✅; cache total counts for search; import lock per campus to serialise same-campus imports; `import_jobs` audit trail. 

**P3:** partition `audit_logs`/`login_logs`; read replica for reports; materialised yearly history that never changes (previous years' aggregates cached forever).

## 8. Before → After for each optimisation

```
Importer read      : PhpSpreadsheet load whole workbook → 12.8 KB/row, OOM at 100K → SpreadsheetRowStream (XMLReader) → flat memory
                     expected: 100K rows in ≤ 250 MB | verify: tests/Performance/import_probe.php (peak_mem_mb), 6 concurrent uploads RSS
Importer write     : SELECT + BEGIN + 4 INSERT + COMMIT + bcrypt per row → 500-row batches, 1 tx, bcrypt only when e-mailed
                     expected: ≥ 50× faster | verify: import_probe.php (rows_per_s, sql_per_row) + row-level diff of both DBs
Cache              : Cache::remember (N parallel rebuilds, hard expiry) → HeavyCache (1 builder, SWR, markStale)
                     expected: no stampede, no user-visible rebuild | verify: scenarios.sh R6w (P95 during upload), compare_payloads.mjs
Alignment widget   : 650K rows into PHP → GROUP BY counts (thousands of rows) | verify: explain_queries.php, memory 4 GB→small
Location/sector    : 4 identical joins → 2 | verify: PerfProfiler X-Perf-Query-Count 17 → 15
Unbounded endpoints: 28–120 s then HTTP 500 → immediate 413/422 with guidance | verify: curl …?action=list at 1M
Indexes            : missing on fresh deploy → migration + schema.sql | verify: SHOW INDEX; index_ab.php
Summary tables (P1): 98 s cold → milliseconds (expected) | verify: ladder.sh at 1M/2M after implementation
```

## 9. What was proven vs what remains

* **Proven** (identical output, measured): importer equivalence (1,005 rows, 0 diffs), 16/16 dashboard/report endpoints identical payloads, 6 new unit tests + the existing suite unchanged (one pre-existing failing test, `AlignmentServiceTest::testClassifyAggregatesCountsAndPercentagesPerCourse`, fails identically on the untouched code).
* **Not proven:** anything at 5 M rows; behaviour on nginx/php-fpm/MySQL 8.0/Linux; real LSPU data; browser render times; effect of a large buffer pool; SMTP and Gemini costs; the P1 summary-table design (specified, not built).
* **Known limitation of the optimised build:** cold-cache dashboard/report rebuilds are as slow as before (45–77 s at 1M on this rig); the first request after a deploy or an empty cache still waits for the single builder. Pre-warm after deploy/import, or build the summary tables.

## 10. Reproduce everything (`tests/Performance/`)

```bash
source tests/Performance/env.sh           # isolates the app on a scratch MySQL :3391 (never touches :3307)
tests/Performance/start_stack.sh          # scratch MySQL + private Apache (16 threads) on :8091
php tests/Performance/seed_bulk.php --target=1000000       # historical data (set-based, ≈10 min for 1M)
python tests/Performance/make_workbook.py --rows 100000 --tag c1 --out C:/lspu_perf/files/c1_100k.xlsx
php tests/Performance/import_probe.php --file=… --campus=7 --year=2023 [--dry]   # importer profile
tests/Performance/ladder.sh L1m 90                          # read-side suite at the current size
DUR=240 tests/Performance/scenarios.sh before R0 R1 R6w    # concurrency scenarios (before|after, R0 R1 R3 R6 W6 U6 U1x100k, suffix w = warm)
php tests/Performance/explain_queries.php --analyze --out=…      # every dashboard/report/search statement with EXPLAIN ANALYZE
php tests/Performance/index_ab.php                          # controlled index A/B (invisible indexes)
node tests/Performance/compare_payloads.mjs                 # old vs new endpoints must return identical JSON
powershell -File tests/Performance/keep_awake.ps1           # laptops: stop Modern Standby from freezing a run
git apply tests/Performance/optimizations.patch             # the optimised build (14 files)
```
