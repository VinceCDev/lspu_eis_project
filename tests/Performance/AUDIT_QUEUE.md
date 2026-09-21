# EIS scalability - queue imports + summary tables (follow-up to AUDIT.md)

Rig: Windows laptop, scratch MySQL 9.6 (buffer pool 384 MB, redo 1 GB), private Apache (16 threads), stack pinned to 4 E-cores, synthetic data.
Labels: **MEASURED** = observed here. **NOT TESTED** = not run. Nothing below is extrapolated unless marked.

## A. What changed

| Area | Change |
|---|---|
| Import | `POST importEmploymentReport` only stores the file, inserts an `import_jobs` row and dispatches `ProcessEmploymentImport`; returns immediately. Worker streams the workbook, writes chunks of `IMPORT_CHUNK_SIZE` (1000) in one short transaction each, and saves the resume checkpoint **inside that same transaction**. |
| Concurrency | `ImportJob::claim()` allows at most `IMPORT_WORKERS` (default 2) imports "processing"; the rest stay queued, FIFO. Lease + heartbeat: a dead worker's import is re-queued by `imports:reap` and resumed from its checkpoint; the old worker is fenced by `run_token`. |
| Retry safety | Resume from checkpoint; even a stale checkpoint cannot duplicate (`user.email` UNIQUE + existence check per chunk). Tests: `tests/Integration/ImportQueueTest.php`. |
| Progress | `?action=importStatus` (one PK read), `importList`, `importCancel`. UI polls 2-5 s, pauses when hidden, works after closing the browser. |
| Summary tables | `rpt_program`, `rpt_metric`, `rpt_title`, `rpt_title_all`, `rpt_location`, `rpt_state` (+ per-campus applications counts). Rebuilt per campus by `RebuildReportingSummary` (READ COMMITTED, temp tables, one short DELETE+INSERT swap). Dashboard/Reports read them. Live queries remain as fallback (`REPORTING_SUMMARY=false`). |
| Cache | `HeavyCache` keeps single-builder + stale-while-revalidate; invalidation is now per scope (`campus:N`, `all`) and fired when a campus's summary finishes rebuilding. |
| Alumni page | Server-side pagination, prefix name search (`alumni(last_name,first_name)`, `alumni(first_name)`), filters, capped totals, `STRAIGHT_JOIN` browse query. |
| Other | `GeminiClient` skips the network call when no key; PDO 65,535-placeholder bug fixed by sub-batching INSERTs; `docker/entrypoint.sh` fails the deploy on a failed migration and only marks `database/schema.baseline.txt` migrations as applied; compose adds `queue-imports`, `queue-summaries`, `scheduler`, `--innodb-redo-log-capacity=1G`. |

## B. Before -> after (MEASURED, same rig)

| | Before (AUDIT.md) | After |
|---|---|---|
| 1 x 100K import | OOM at 77 s, 0 rows | 40-50 s quiet; 60-115 s with 20-50 readers; PHP peak 40-66 MB |
| 6 x 100K (600K) | none finished, PHP RSS 6 GB | 6/6 done in 267-520 s (5 runs), 2 processing at once, 4 queued |
| Cold dashboard stats 1M / 2M | 98.5 s / >180 s (timeout) | 0.25 s / 0.58 s |
| Cold report summary 1M | 40 s | 0.64 s (year filter 0.34 s) |
| Cold dashboard 1.6M | - | 0.29 s |
| Alumni list | unbounded, 500 s at ~150K/campus | paged, 1-3 ms browse; deep page 0.08 s |
| Search rare term 1M | 7 s | 1 ms; common term p95 1-3.7 s (1M-2M) |

Summary vs live equivalence at 1M: **354 figures, 43 scopes, all identical** (`results/summary_equivalence_1m_full.md`). Import reconciliation: 6 files x 100,000 rows -> 599,997 imported + 3 skipped in-file duplicates; `rpt_program` totals equal `COUNT(*)` per campus.

## C. Concurrency (MEASURED; `results/logs/`)

| Run | Readers | Imports done | Reader p50 / p95 / p99 | Errors |
|---|---|---|---|---|
| B cold (final) | 20 | 6/6 in 267 s | 39 ms / 183 ms / 3.0 s | 0 |
| B warm | 20 | 6/6 in 314 s | 65 ms / 506 ms / 4.2 s | 0 |
| W cold, before list fix | 50 | 6/6 in 457 s | 43 ms / 3.8 s / 37.8 s | 0 |
| W cold, final | 50 | 6/6 in 312 s | 43 ms / 1.9 s / 21.8 s | 0 |
| W warm | 50 | 6/6 in 520 s | 41 ms / 4.4 s / 42 s | 0 |
| A cold (memory-starved laptop) | 10 | 1/1 in 449 s | 292 ms / 1.8 s / 7.0 s | 1 x HTTP 500 (`nav:settings`, cause not diagnosed) |

mysqld averaged 2.3 of 4 cores during 6 imports; workers 0.2-0.3. Tail latency at 50 readers is Apache's 16 workers queueing (uploads' POST waited 10-19 s). Timings on this laptop swing 2-5x with memory pressure (free RAM 0.2-4.8 GB); treat runs as relative, not absolute.

Chunk size (100K alone): 500 = 62-91 s, 1000 = 41-107 s, 2000 = 44-76 s, 5000 = 40 s. Noise > differences; kept 1000. Name indexes: +5% write time, redo 118-132 MB vs 112-116 MB. **innodb redo capacity 100 MB (MySQL 8.0 default) made the same import 160 s vs 48 s** -> set to 1G in compose.

## D. Growth (MEASURED, cold app cache, summary tables)

| | 1M | 1.6M | 2M | 5M (before last two fixes) |
|---|---|---|---|---|
| dash:stats super | 252 ms | 285 ms | 578 ms | 12.0 s |
| report summary | 637 ms | 630 ms | 742 ms | 4.0 s |
| mixed 10 users p95 | 82 ms | 291 ms | 247 ms | 715 ms (2 timeouts) |
| summary rebuild / campus (idle) | 10-40 s | - | 30-73 s | 63-429 s |

At 5M the cold dashboard was dominated by `applications JOIN alumni` (5-6 s) and a 170K-row title sum (2.2 s). Both were fixed afterwards (totals card 19 ms; alignment 139 ms measured in a profile script). **The 5M ladder was NOT re-run after those fixes**, so the full 5M page numbers are unverified. Still slow at 5M with a 384 MB buffer pool: alumni-map popup (33 s p95) and common-name search (70 s p95). Buffer pool is ~1/15 of the 5M working set; 4-8 GB is recommended but was NOT tested.

## E. Remaining bottlenecks / not tested

- Summary rebuild is O(campus size): 10-40 s at 1M, up to 7 min at 5M, up to 340 s under import contention. Summary lags an import by ~1-3 min (measured 64-156 s).
- Common-name search totals and the alumni-map popup at 5M; totals cap left at 10,000 (a 1,000 cap was proposed, not applied).
- Web tier: 16 php-fpm workers saturate at ~50 concurrent users. DB CPU-bound at 6 imports.
- Sync SMTP (<=200 mails) inside the worker; Gemini live calls when a key is set.
- NOT TESTED: Docker/nginx/php-fpm/MySQL 8.0 on Linux, real LSPU data, browser render time, big buffer pool, real SMTP/Gemini, production `SHOW INDEX FROM alumni` (no prod access: run `php artisan reporting:verify`).
- The earlier `AlignmentServiceTest` failure is pre-existing (fails identically on HEAD; its mock predates `preload()`); untouched.

## F. Deployment

1. `git pull && docker compose up -d --build` - entrypoint runs `migrate` (fails the deploy if it fails), `reporting:verify`, queues the first summary build.
2. Services: `queue-imports` x `IMPORT_WORKERS`, `queue-summaries`, `scheduler` (see DEPLOY-VPS.md). Env: `QUEUE_CONNECTION=database`, `IMPORT_WORKERS`, `IMPORT_CHUNK_SIZE`, `IMPORT_MAX_UPLOAD_MB`, `REPORTING_SUMMARY`.
3. DB: redo capacity 1G, buffer pool as large as the VPS allows. Storage: shared `storage-data` volume (uploads land in `storage/app/private/imports`, deleted after success, purged after 7 days if failed).
4. New migrations: `2026_09_22_000000` (queue/import/summary tables), `2026_09_22_000100` (name indexes), `2026_09_21_000000` (reporting indexes; ADD INDEX on 1M+ rows takes minutes). No Redis needed.
5. Dev DB needs `php artisan migrate`; Playwright import spec updated for the queued flow.

## G. Rollback

- Reports/Dashboard: `REPORTING_SUMMARY=false` (live queries again, slow at 1M+).
- Imports: `QUEUE_CONNECTION=sync` runs the job inside the request (old behaviour, old limits) - UI still works.
- Code: revert the commit; migrations are additive, tables can stay (`migrate:rollback` drops them; `import_jobs` history is lost).
