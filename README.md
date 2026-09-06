# LSPU EIS — Employment Information System

A Laravel 12 port of LSPU's Employment Information System (EIS): a job-matching and career-tracking portal connecting **Alumni**, **Employers**, **Admins**, and **Superadmins** on a single platform.

> This is a **port**, not a rewrite. The original application's backend was a hand-rolled PHP router/controller stack (`?action=` dispatching, raw SQL) and a Vue 3 frontend compiled in-browser at runtime. This project re-hosts that exact behavior inside the Laravel framework (routing, middleware, container, artisan) while deliberately preserving the original request/response contract, database schema, and frontend — see [Architecture](#architecture) for why, and what that trades off.

## Table of Contents

- [About the project](#about-the-project)
- [User roles](#user-roles)
- [Architecture](#architecture)
- [Tech stack](#tech-stack)
- [Requirements](#requirements)
- [Installation (local development)](#installation-local-development)
- [Configuration](#configuration)
- [Running the app](#running-the-app)
- [Testing](#testing)
- [Deployment](#deployment)
- [Security](#security)
- [ISO/IEC 25010 quality characteristics](#isoiec-25010-quality-characteristics)
- [Scheduled / maintenance jobs](#scheduled--maintenance-jobs)
- [CI/CD](#cicd)
- [Project structure](#project-structure)
- [Known limitations](#known-limitations)
- [Guide for the next developer](#guide-for-the-next-developer)

## About the project

LSPU EIS helps Laguna State Polytechnic University track its alumni's employment outcomes and connect them with partner employers. Core features include:

- **Alumni**: profile & resume management, job search/filtering (with map-based location search), applying to jobs (multi-step wizard: personal info, education, skills, experience, resume, cover letter, employer-defined questions), tracking application status, messaging employers, notifications, success stories.
- **Employer**: company profile & verification, job postings (with per-job custom "Employer Questions"), applicant review/status pipeline, interview scheduling, an AI-assisted "Matchboard" that ranks alumni against a job posting, onboarding checklists for hired applicants, messaging.
- **Admin**: manage alumni accounts (view/edit/delete) and pending employer verification, review/delete applications platform-wide, generate/export/email employment reports, admin/alumni account creation, own-profile/settings management, scoped to their own campus.
- **Superadmin**: everything Admin can do but campus-unscoped, plus employer/company management (view/edit/delete/approve), job postings platform-wide, admin/employer/alumni account management (the only role that can create or manage Employer accounts), the audit log, reminder settings, success stories, landing-page content editing, and system info.

All four roles share one codebase and one database; access is separated entirely by session role, not by separate applications.

## User roles

| Role | Typical entry point | Notes |
|---|---|---|
| Alumni | `/login` → `/home` | Self-registers via `/signup`, subject to admin approval workflows |
| Employer | `/employer_login` → `/employer_dashboard` | Self-registers via `/employer_signup`; company verification document reviewed by Superadmin |
| Admin | `/login` → `/admin_dashboard` | Created by a Superadmin; scoped to one campus |
| Superadmin | `/login` → `/superadmin_dashboard` | Highest privilege, not campus-scoped; shares most views/controllers with Admin (see [Architecture](#architecture)) |

### What each role can actually do

Derived from `routes/web.php`'s page map and each controller — the authoritative source if this drifts out of date.

**Alumni** (`Alumni\*Controller`, pages under `/home`, `/my_application`, `/my_profile`, `/message`, `/notification`, `/alumni_success_stories`)
- Browse/search/filter job postings (map-based location search via Leaflet + a geocoding proxy)
- Apply to a job through a multi-step wizard (personal info → education → skills → experience → resume → cover letter → any employer-defined custom questions)
- Track their own applications and status changes
- Manage their own profile: personal info, education, skills, work experience, resume, profile photo
- Message employers directly; receive notifications
- View alumni success stories

**Employer** (`Employer\*Controller`, pages under `/employer_dashboard`, `/employer_jobposting`, `/employer_applicants`, `/employer_interview`, `/employer_matchboard`, `/employer_onboarding`, `/employer_profile`, `/employer_settings`, `/employer_messages`)
- Create/edit/delete job postings, each with any number of custom "Employer Questions" applicants must answer
- Review applicants per job, change an applicant's status through the pipeline (Pending → Interview → Hired/Rejected, etc.)
- Schedule and track interviews
- Use the AI-assisted **Matchboard** (`App\Services\JobMatchService` + `GeminiClient`) to have Gemini score every alumnus against a job posting
- Manage onboarding checklists for hired applicants
- Manage company profile (logo, verification document, company info) — subject to Superadmin approval before the account is fully active
- Message alumni; manage account settings

**Admin** (`Admin\*Controller`, pages under `/admin_dashboard`, `/admin_alumni`, `/admin_alumni_pending`, `/admin_applicant`, `/admin_user`, `/admin_reports`, `/admin_profile`, `/admin_settings`, `/admin_message`, `/admin_notification`) — everything below is scoped to the admin's own `campus_id`
- Alumni account management: view, edit, delete (`AlumniController`); review/approve accounts pending verification (`admin_alumni_pending`)
- Application/applicant management: view and delete application records platform-wide within their campus (`ApplicantController`)
- Account management (`admin_user` / `UserController`): create/edit/delete/deactivate/reset-password for Admin and Alumni accounts (**not** Employer — that's Superadmin-only, enforced server-side)
- Reports (`ReportController`): employment summary/detailed/industry-analysis views, Excel export, and emailing a report to a recipient
- Own profile and account settings (password, 2FA, notification preferences)
- **Cannot** manage Employer/company records at all — there is no "Admin employer management" page; that capability exists only under Superadmin (`superadmin_company`)

**Superadmin** (`Superadmin\*Controller` plus every `Admin\*Controller` page, unscoped by campus) — has every Admin capability above across *all* campuses, plus:
- Company/Employer management (`superadmin_company`, `superadmin_company_pending` / `CompanyController`): view/edit/delete, approve pending employer registrations
- Job management platform-wide (`superadmin_job` / `Superadmin\JobController`)
- The only role that can create, edit, or delete Employer accounts (via `admin_user`)
- Audit log (`superadmin_audit_logs` / `AuditLogController`): searchable, filterable record of `create_account` / `update_account` / `deactivate_account` / `delete_account` / `approve_employer` / `delete_employer` and similar actions, each logged with the acting user, action, target entity, and a human-readable description
- Reminder settings (`superadmin_reminder_settings`), success stories management (`superadmin_success_stories`), landing-page content editor (`superadmin_landing_editor`), and system info (`superadmin_system_info`)

## Architecture

A few decisions are intentional and load-bearing — worth understanding before changing them:

- **No Eloquent for domain data.** Domain tables are queried with raw SQL through the `App\Concerns\LegacyQueries` trait (`selectOne`, `selectAll`, `insertGetId`, `insert`, `runUpdate`, `runDelete`), matching the original app's data-access layer 1:1. Laravel's `migrations/` here only carry a handful of **incremental schema patches** made during the port (new indexes, new tables, nullable-column fixes) — they do **not** create the schema from scratch. You need the original application's database already populated (see [Installation](#installation-local-development)).
- **`?action=` dispatching, not RESTful routes.** `App\Http\LegacyDispatcher::handle()` routes a page slug (e.g. `employer_jobposting`) plus an `?action=methodName` query parameter straight to a controller method of the same name — the exact mechanism the original app used, so every one of its endpoints (list/store/update/destroy/custom actions) keeps working under any HTTP method without hand-deriving a REST route per action. See `routes/web.php` for the full slug → controller map.
- **Session-based custom auth**, not Laravel's `Auth` facade/guards — `App\Services\Auth` is a near-verbatim port of the original's static session wrapper, so every ported controller's auth checks needed minimal changes.
- **Vue 3 with no build step.** Every page is a Blade view whose `@verbatim` block is valid Vue 3 template markup, compiled **in the browser at runtime** via the full (non-precompiled) Vue UMD build loaded from a `<script>` tag. This is why `<teleport>` and other Vue 3 built-ins work with zero extra tooling, and also why the CSP has to allow `unsafe-eval`/`unsafe-inline` (see [Security](#security)).
- **MySQL/MariaDB in strict SQL mode.** `STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE` is expected — code that inserts "not yet provided" values follows the convention: empty string `''` for VARCHAR columns, real `NULL` (never `''`/the literal string `"null"`) for nullable DATE/INT columns.

## Tech stack

**Backend**
- PHP 8.2+, Laravel 12
- MySQL / MariaDB (raw SQL access layer, no ORM for domain data)
- PHPMailer (transactional email — verification, password reset, 2FA codes, notifications)
- PhpSpreadsheet (Excel export/import)
- Google Gemini API (`GEMINI_API_KEY`) — used by the Employer Matchboard's AI-assisted alumni-to-job scoring (`App\Services\JobMatchService` / `GeminiClient`)

**Frontend**
- Vue 3 (UMD build, in-browser template compilation — no bundler for app JS)
- Tailwind CSS (precompiled once via the Tailwind CLI — the only build step in the project)
- Bootstrap 5 + Bootstrap Icons, Font Awesome
- Leaflet (map-based job location search), Chart.js (dashboards), Quill (rich text), jsPDF / html2canvas / ExcelJS / SheetJS (exports)

**Tooling / QA**
- PHPUnit (backend unit/feature/integration tests)
- PHPStan (static analysis)
- Laravel Pint (code style)
- Playwright (end-to-end browser tests — Chromium, Firefox, WebKit)
- Vitest + ESLint (frontend JS unit tests / linting)

## Requirements

- PHP >= 8.2 with the extensions Laravel 12 needs (`pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd` or `imagick` recommended for uploads)
- Composer 2.x
- MySQL 5.7+/MariaDB 10.3+ (with strict mode enabled)
- Node.js 18+ and npm (for the Tailwind CLI build and Playwright/Vitest tooling — **not** used to bundle the app's runtime JS)
- A local web server for development — this project is commonly run under XAMPP/WAMP or via `php artisan serve`

## Installation (local development)

1. **Clone and install PHP dependencies**

   ```bash
   git clone <repo-url> lspu_eis_laravel
   cd lspu_eis_laravel
   composer install
   ```

2. **Install frontend tooling** (only needed to rebuild Tailwind's compiled CSS and to run Playwright/Vitest — the app itself ships its JS/CSS assets already in `public/assets/`)

   ```bash
   npm install
   npx playwright install   # first time only, downloads browser binaries for e2e tests
   ```

3. **Environment file**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Then edit `.env` — at minimum set `APP_URL`, the `DB_*` connection, and mail credentials. See [Configuration](#configuration) for every app-specific variable.

4. **Database**

   This app expects the **original LSPU EIS schema** to already exist (users, alumni, employer, jobs, applications, and related tables) — the Laravel `migrations/` directory only contains incremental patches layered on top of that schema, it will not create it from scratch. A structure-only baseline (`database/schema.sql` — tables, columns, indexes, foreign keys; **no data**) is included in this repo so a fresh setup doesn't need a real data dump just to get the app running:

   ```bash
   mysql -u root -p your_database_name < database/schema.sql
   php artisan migrate
   ```

   If you're standing up against real/existing data instead, import that dump first (skip `schema.sql`) and go straight to `php artisan migrate`.

   Make sure the database/session is running in **strict SQL mode** (`STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE`) — this app's insert code relies on it to catch bad NULL/date values the same way the original app's database did.

5. **Uploads directory**

   Uploaded files (resumes, profile pictures, verification documents, company logos) are served directly from `public/uploads/` by default — no symlink is used (see the docblock in `app/Core/Uploader.php` for why). Ensure that directory exists and is writable by the web server:

   ```bash
   mkdir -p public/uploads
   ```

   To relocate uploads outside the public web root (recommended for production), set `UPLOADS_PATH` in `.env` — see [Configuration](#configuration).

6. **Build the compiled CSS** (only needed if you change `public/assets/css/tailwind-src.css`; a compiled copy is already checked in)

   ```bash
   npm run build:css
   ```

## Configuration

Beyond Laravel's standard `.env` keys (`APP_*`, `DB_*`, `SESSION_*`, `MAIL_*`, `CACHE_STORE`, `QUEUE_CONNECTION`), this app reads the following app-specific variables:

| Variable | Purpose | Default |
|---|---|---|
| `GEMINI_API_KEY` | Google Gemini API key used by the Employer Matchboard's AI alumni-to-job scoring | none — matching degrades/fails without it |
| `UPLOADS_PATH` | Absolute path for uploaded files (resumes, photos, documents). Set this to a path **outside** `public/` in production so uploads survive redeploys and aren't served with directory listing | `public_path('uploads')` |
| `BACKUP_DIR` | Absolute path (outside the project directory) where `php artisan backup:run` writes its database + uploads backup | a sibling directory of the project root |
| `BACKUP_RETENTION_DAYS` | How many days of backups `backup:run` keeps before pruning older ones | `30` |
| `PHP_CLI_BINARY` | Absolute path to the PHP CLI binary, used where the app shells out to `php` (e.g. dispatching the detached job-matching command) | resolved from `PATH` |

Session/auth-relevant settings worth knowing (not `.env`-configurable, defined in code):

- Idle session timeout: 30 minutes server-side (`App\Services\Auth::IDLE_TIMEOUT_SECONDS`), enforced on every request by `EnforceSessionTimeout` middleware.
- Password hashing: PHP's native `password_hash()`/`PASSWORD_DEFAULT` (bcrypt).

## Running the app

Composer ships a convenience script that starts everything a full dev session needs (app server, queue worker, log tailing, and CSS/asset watching) concurrently:

```bash
composer dev
```

Or run the app server alone:

```bash
php artisan serve
```

> **Note:** `php artisan serve` is a single-threaded development server — concurrent requests genuinely queue behind each other. This is expected in local dev and is specifically accounted for in the Playwright test timeouts; don't mistake it for a bug. Use a real web server (Apache/Nginx + PHP-FPM) for anything beyond solo local development.

For XAMPP: point Apache's vhost/document root at this project's `public/` directory, or place the project under `htdocs` and browse to `http://localhost/lspu_eis_laravel/public/`.

## Testing

**PHP (backend) tests:**

```bash
composer test          # PHPUnit — Unit / Feature / Integration suites under tests/
composer analyse        # PHPStan static analysis
./vendor/bin/pint        # Code style check/fix
```

**Frontend JS unit tests:**

```bash
npm test                # Vitest, tests/js/
npm run lint             # ESLint over public/assets/js and tests/js
```

**End-to-end browser tests (Playwright):**

```bash
php artisan serve        # in one terminal — Playwright expects http://127.0.0.1:8000
npm run test:browser      # in another terminal
```

Notes on the e2e suite (`tests/Browser/`):
- Runs single-worker, not fully parallel — tests share MySQL test data (rows tagged with `uniqid()`), so concurrent runs would race each other.
- Covers accessibility, admin, alumni, employer, superadmin, auth, dashboards, modals, navigation, responsive layouts, security headers, and error pages, across Chromium/Firefox/WebKit.
- A results report is written to `playwright-report/` after each run (`npx playwright show-report` to view it).

## Deployment

There is no framework-specific deployment magic here beyond a standard Laravel app, plus the app-specific pieces below.

1. **Provision**: PHP 8.2+, MySQL/MariaDB (strict mode), Composer, and a production web server (Nginx/Apache + PHP-FPM). Node/npm are **not** required on the production server — Tailwind's compiled CSS output is committed to `public/assets/vendor/tailwind/`.
2. **Get the code onto the server** and run:
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.example .env   # then fill in production values
   php artisan key:generate
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
3. **Database**: as in local install, the target database needs the base schema before running `php artisan migrate --force` (which only layers the incremental patches on top) — import real production data if you have it, or `database/schema.sql` for a structure-only baseline.
4. **Uploads**: set `UPLOADS_PATH` to a writable directory **outside** the document root (or at least outside anything served with directory listing enabled), and make sure it's writable by the web server user. Since there's no symlink involved, the web server must be able to read directly from wherever `UPLOADS_PATH` points if uploads need to be publicly reachable by URL — route/serve them accordingly (a dedicated location block, or a controller-served download endpoint) rather than assuming `public/` access.
5. **`APP_URL` must be exactly right.** `RejectCrossOriginPost` middleware (this app's CSRF replacement — see [Security](#security)) compares every POST's `Origin`/`Referer` against `config('app.url')`; a mismatched scheme/host/port will reject every real form submission and AJAX POST in the app.
6. **Queue worker**: `QUEUE_CONNECTION=database` by default — run `php artisan queue:work` under a process supervisor (systemd/Supervisor) in production; `composer dev`'s `queue:listen` is dev-only.
7. **Scheduler**: point cron at `php artisan schedule:run` every minute if you rely on Laravel's scheduler for any of the maintenance commands below instead of calling them ad hoc.
8. **HTTPS**: put the app behind TLS. `SecurityHeaders` middleware only emits `Strict-Transport-Security` when the request already arrives over HTTPS — HSTS does nothing until the site is actually served securely.
9. **Set `APP_DEBUG=false` and `APP_ENV=production`** — the boilerplate default in `.env.example` has debug mode on, which leaks stack traces/config values if left enabled in production.

## Security

This app underwent a dedicated ISO/IEC 25010-driven security remediation pass. Highlights, and why each exists:

- **Security response headers** (`App\Http\Middleware\SecurityHeaders`): `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, a Content-Security-Policy, and conditional HSTS (sent only over HTTPS, per spec).
  - The CSP's `script-src`/`style-src` include `unsafe-eval`/`unsafe-inline` — a direct consequence of the no-build-step, in-browser-compiled Vue 3 architecture (see [Architecture](#architecture)), not an oversight. The other directives (`object-src 'none'`, `frame-ancestors 'self'`, `base-uri 'self'`, `form-action 'self'`, and scoped `img-src`/`font-src`/`connect-src` allowlists) still meaningfully constrain what a successful XSS could do.
- **CSRF protection without Laravel's default token mechanism**: `App\Http\Middleware\RejectCrossOriginPost` replaces `ValidateCsrfToken` app-wide. The existing frontend JS never sends Laravel's `_token`/`X-CSRF-TOKEN`, so it checks `Origin`/`Referer` against the app's own origin instead — same protection, zero frontend changes required. A POST with **neither** header present is rejected by default (fail-closed).
- **Role-based access control**: `App\Http\Middleware\EnsureRole` (aliased as `role:...`) gates every role-scoped route group in `routes/web.php`. An authenticated user hitting a route outside their role gets a proper 403 page, not a confusing bounce back to `/login`.
- **Idle session timeout**: enforced server-side on every request (`EnforceSessionTimeout`), independent of any cookie expiry the browser might not honor.
- **Two-factor email verification**: required on login for accounts that either have 2FA enabled or have been inactive long enough to warrant re-verification; codes are compared with `hash_equals()` to avoid timing attacks.
- **Rate limiting** (`App\Models\RateLimiter`) on authentication, AI suggestion, and geocoding endpoints to blunt brute-force/credential-stuffing and abuse of third-party-backed features.
- **Upload validation** (`App\Core\Uploader`): extension allowlist plus magic-byte/MIME verification (not just trusting the client-supplied extension), 5 MB size cap, and a fixed set of allowed types (`jpg`, `jpeg`, `png`, `gif`, `pdf`, `doc`, `docx`).
- **Password storage**: `password_hash()` / `PASSWORD_DEFAULT` (bcrypt), never reversible encryption or a legacy hash.
- **Data retention (RA 10173 — Philippine Data Privacy Act)**: `php artisan accounts:purge-stale` deletes alumni/employer accounts that never got past `Pending` status once they exceed a retention window; active accounts are never touched by this job.
- **No secrets in the frontend**: the Gemini API key and mail credentials are read server-side only (`GeminiClient`, `MailService`); the browser never sees them.

If you discover a security vulnerability in this application, please report it privately to the project maintainer rather than opening a public issue.

## ISO/IEC 25010 quality characteristics

This app has gone through several ISO/IEC 25010-driven remediation and validation passes. Rather than a paper checklist, each characteristic below points at concrete, verifiable evidence already in this codebase — code to read, commands to run, or tests to execute.

**Functional Suitability** — every role's core workflow (see [What each role can actually do](#what-each-role-can-actually-do)) is covered by real, running browser tests (`tests/Browser/`), not just unit-level assertions — e.g. `tests/Browser/admin/alumni-management.spec.js` and `tests/Browser/superadmin/user-account-crud.spec.js` exercise full create → view → edit → delete lifecycles against the real UI and a real database, not mocks.

**Performance Efficiency** — paginated list endpoints for large tables (`AlumniController::paginatedList()`, `CompanyController::paginatedList()`, `AuditLogController::list()`) instead of unbounded `SELECT *`; server-side caching tables (`dashboard_stats_cache`, `alignment_cache` for job-matching text, `location_geocode_cache` for the map search) to avoid recomputing or re-querying third-party APIs on every request; the AI job-matching pass (`job:match`) always runs detached via a queued/backgrounded artisan command rather than inline in a web request, since scoring a large roster can take minutes.

**Compatibility** — the Playwright suite runs across three browser engines (Chromium, Firefox, WebKit — see `playwright.config.js`'s `projects`), and `tests/Browser/responsive/viewports.spec.js` checks 5 breakpoints (1920/1366/768/390/412px) for zero horizontal overflow. The CSP (`SecurityHeaders` middleware) explicitly allowlists the third-party origins this app actually integrates with (OpenStreetMap tiles, PSGC address API, EMSI/Lightcast skills API, Universities API) rather than blocking or silently failing against them.

**Usability** — `tests/Browser/accessibility/axe-scan.spec.js` runs an automated axe-core scan (WCAG 2 A/AA) against key pages; `tests/Browser/accessibility/d6-photo-button.spec.js` documents one concrete fix (a clickable `<div>` converted to a real, keyboard-operable `<button>` with an accessible name). Action-menu dropdowns across every management table were fixed to render via Vue 3 `<teleport to="body">` so they always overlay fully instead of clipping inside a scrollable table — see the `.teleported-action-dropdown` pattern used throughout `resources/views/*/*.blade.php`.

**Reliability** — `EnforceSessionTimeout` middleware enforces a hard server-side idle timeout independent of client-side cookie expiry; `php artisan backup:run` provides a pure-PHP (no `exec()`/`mysqldump` dependency) database + uploads backup that works even where shell-out is disabled; the audit log gives every account/company mutation a durable, queryable trail; strict SQL mode is relied on deliberately (not disabled) so bad data fails loudly at the query level instead of silently corrupting state.

**Security** — see the dedicated [Security](#security) section above for the full list (CSP, the `RejectCrossOriginPost` CSRF replacement, RBAC via `EnsureRole`, 2FA, rate limiting, upload validation, RA 10173 data retention). `composer audit` and `npm audit --omit=dev` are both expected to report zero vulnerabilities in production dependencies as a baseline (see [CI/CD](#cicd)).

**Maintainability** — one controller namespace per role (`Admin/`, `Alumni/`, `Employer/`, `Superadmin/`, `Shared/`) keeps role-specific logic from tangling together; `App\Concerns\LegacyQueries` centralizes the raw-SQL access pattern so every Model reads the same way; `phpstan.neon` + `composer analyse` (0 errors is the expected baseline) catches type errors before they reach production; 100+ PHPUnit tests across Unit/Feature/Integration give a regression net for backend changes.

**Portability** — no JS bundler/build step for the app's own runtime code (Vue loads and compiles its templates in-browser), so there's nothing to rebuild when moving between environments beyond `composer install`/`npm install` and `.env` configuration; file uploads are relocatable via a single `UPLOADS_PATH` env var instead of a hardcoded path; the app runs unmodified under XAMPP, `php artisan serve`, or a production PHP-FPM + Nginx/Apache stack (see [Deployment](#deployment)).

## Scheduled / maintenance jobs

| Command | Purpose |
|---|---|
| `php artisan backup:run` (`composer backup`) | Pure-PHP database + `uploads/` backup written outside the project directory (works even where `exec()`/`mysqldump` shelling out is disabled, e.g. shared hosting). |
| `php artisan accounts:purge-stale` (`composer purge-stale-accounts`) | Deletes alumni/employer accounts still `Pending` past the retention window (RA 10173 compliance). |
| `php artisan job:match {job_id}` | Scores every alumnus against a job posting via Gemini and notifies matches. Launched fire-and-forget by the job-posting controller right after a job is created (scoring a large roster can take minutes — well past a web request's execution budget), so it always runs detached rather than inline. |

Wire these into `routes/console.php`'s scheduler (`Schedule::command(...)`) or your OS's cron/Task Scheduler if you want them running automatically rather than triggered manually/by the app.

## CI/CD

**There is no CI/CD pipeline configured in this repository yet** (no `.github/workflows/`, no other CI config file) — every check described in this README (`composer test`, `composer analyse`, `composer audit`, `npm audit`, the Playwright suite) is currently run manually by whoever is making the change. If you're setting one up, this is what a pipeline for this app needs to run, in the same order used during this project's own validation passes:

1. `composer install` (add `--no-dev --optimize-autoloader` only for a deploy job, not a test job)
2. `npm install` (needed for Playwright/Vitest tooling and the Tailwind CLI, not for the app's own runtime JS)
3. `npx playwright install --with-deps` (first run only / cache between runs — downloads browser binaries)
4. `composer test` — PHPUnit; expected baseline is **all tests passing** (102 as of this pass; grows as coverage grows)
5. `composer analyse` — PHPStan; expected baseline is **0 errors**
6. `composer audit` and `npm audit --omit=dev` — expected baseline is **0 vulnerabilities** in production dependencies. (`npm audit` *without* `--omit=dev` currently reports pre-existing vulnerabilities in vendored dev-only frontend libraries — `jspdf`, `quill`, `xlsx`, `exceljs` — used only by test tooling and export features with no server-side trust boundary; track them but don't treat them as a hard CI gate the same way a production-dependency finding would be)
7. A real MySQL/MariaDB service (strict SQL mode enabled) with the legacy schema pre-loaded, plus `php artisan migrate`, then `php artisan serve &` before the browser suite
8. `npx playwright test --project=chromium` (Chromium is the fast/primary gate); Firefox and WebKit runs are valuable but slower — consider running Chromium on every push and the full 3-browser matrix on a schedule or before merging to the main branch, rather than on every commit
9. Publish the Playwright HTML report (`playwright-report/`) as a build artifact so a failure's trace/screenshot/video is inspectable without re-running locally

Two things specific to this app that a pipeline needs to account for, both already handled in `playwright.config.js`: the suite runs **single-worker, not parallel** (`fullyParallel: false`, `workers: 1`) because tests share MySQL rows tagged with timestamps rather than isolated transactions, and the default test timeout is a generous 45s because `php artisan serve` is single-threaded and requests can genuinely queue behind each other under back-to-back navigation — don't "fix" either of these by re-enabling parallelism or shortening the timeout without addressing the underlying data-isolation/single-threaded-server constraints first.

## Project structure

```
app/
  Concerns/LegacyQueries.php     # raw-SQL helper trait used by (almost) every Model
  Core/Uploader.php               # file upload validation (extension + magic bytes)
  Console/Commands/               # backup, stale-account purge, AI job matching
  Http/
    LegacyDispatcher.php          # ?action= -> controller method routing
    Controllers/
      Admin/ Alumni/ Employer/ Shared/ Superadmin/   # one namespace per role
    Middleware/                   # role gate, CSRF replacement, session timeout, security headers
  Models/                         # raw-SQL data access classes (not Eloquent)
  Services/                       # Auth, MailService, GeminiClient, JobMatchService, RateLimiter, ...
resources/views/                  # Blade views; @verbatim blocks are Vue 3 templates
public/assets/
  js/                              # per-page Vue 3 app scripts (no bundler)
  css/tailwind-src.css             # Tailwind source (compiled -> vendor/tailwind/tailwind.css)
  vendor/                          # vendored frontend libraries (Bootstrap, Chart.js, Leaflet, ...)
public/uploads/                   # default uploaded-file location (see UPLOADS_PATH)
routes/web.php                    # page-slug -> [controller, default action] map + role middleware groups
database/migrations/              # incremental patches on top of the pre-existing legacy schema
tests/
  Browser/                        # Playwright e2e suites, by role/feature area
  Unit/ Feature/ Integration/     # PHPUnit suites
  js/                              # Vitest unit tests for frontend JS
```

## Known limitations

- **Not a from-scratch schema via migrations alone**: Laravel's `migrations/` here don't create the schema — but `database/schema.sql` (structure-only, no data) is included so a fresh setup can bootstrap without needing a real data dump; see [Installation](#installation-local-development).
- **No JS bundler for the app's own frontend**: Vue templates compile in-browser at runtime, which is why the CSP can't be tightened to disallow `unsafe-eval`/`unsafe-inline` without a separate frontend rebuild effort.
- **`php artisan serve` is single-threaded**: fine for solo local development, but concurrent requests will visibly queue — use PHP-FPM behind a real web server for anything with concurrent users, including multi-worker test runs.
- **Third-party API dependency**: the Matchboard's AI ranking feature requires a valid `GEMINI_API_KEY` and network access to Google's Gemini API; without it, that specific feature degrades rather than the whole app failing.

## Guide for the next developer

If you're picking this project up cold, read in this order:

1. **[Architecture](#architecture) first, always.** The four bullets there (no Eloquent, `?action=` dispatching, custom session auth, no-build-step Vue) are not incidental — they're why this codebase looks different from a typical Laravel app, and misreading them as "technical debt to clean up" is the single most common way to break something here. Don't introduce Eloquent models for domain tables, don't add RESTful routes alongside the `?action=` ones for existing pages, and don't reach for Laravel's `Auth` facade — follow the existing pattern in the surrounding code.
2. **Trace one full feature top-to-bottom before changing anything.** Pick a small one — e.g. Admin Alumni delete: `resources/views/admin/alumni.blade.php`'s dropdown → `public/assets/js/admin_alumni.js`'s `deleteAlumni()` → `routes/web.php`'s `admin_alumni` slug → `App\Http\Controllers\Admin\AlumniController::destroy()` → `App\Models\Alumni`. That round trip is the shape of almost everything else in the app.
3. **Every page has three files, not one**: a Blade view (`resources/views/<role>/<page>.blade.php`, whose `@verbatim` block is a Vue 3 template), a page-specific JS file (`public/assets/js/<page>.js`, a `createApp({...})` call), and a controller (`app/Http/Controllers/<Role>/<Page>Controller.php`). Changing behavior almost always means touching at least two of the three.
4. **When you find a bug, check for the duplicate-key trap first.** More than once in this codebase's history, a JS object literal defined the same method name twice — JavaScript silently keeps only the *second* definition, so the first one (and anything only it referenced, like a variable or a piece of logic) becomes dead code with no error anywhere. If a function "obviously should be running" but isn't, grep the file for its name before assuming the bug is elsewhere.
5. **Any DATE/INT column can reject `''` and the literal string `"null"`.** This database runs in strict SQL mode on purpose. When a form doesn't collect a value for a nullable date/numeric column, pass real PHP `null`, not `''` or `'0000-00-00'` — this exact mistake has caused multiple HTTP 500s across this codebase's history (see `AlumniController::create()`'s `birthdate` handling for a fixed example, and the `App\Concerns\LegacyQueries`-based Models generally for the established convention: `''` for "not yet provided" VARCHAR, real `null` for DATE/INT).
6. **A status/flag living in two places is a bug waiting to happen.** `Account::setStatus()` is a cautionary tale worth reading once: for a while, deactivating an Admin account wrote to `administrator.status` while login and the account list both read `user.status` — the UI kept showing the account as Active, and a "deactivated" admin could still log in, because the write and the read never agreed on which column was authoritative. Before adding a second place to store something that's already tracked somewhere else, check every place that reads it.
7. **Never trust that a form's `required` fields are actually populated after a re-fetch.** Several edit modals in this app re-fetch a dependent dropdown's options after opening (e.g. Admin Alumni Edit re-fetches City/Municipality options from a live third-party API keyed off the stored Province) — if the freshly fetched option list doesn't contain an exact string match for the previously stored value, the `<select>` silently renders with nothing selected, HTML5 `required` validation blocks the submit, and the user sees no app-level error at all, just a native browser tooltip. If you're adding a form field backed by an async-loaded option list, make sure a stored value that no longer round-trips through that list fails loudly, not silently.
8. **When you seed test data directly into the database for a Playwright test, clean it up in the same file.** A disposable job/application/account row that isn't deleted when the test finishes doesn't just linger harmlessly — it becomes real data that a *different* test's `.first()`/`.last()` row locator can pick up by accident, especially on any page filtered to the same fixture account. This has broken previously-passing tests before; a test that creates disposable data owns deleting it, in the same file, not as an assumed side effect of some other test's delete flow.
9. **Run the full check sequence from [CI/CD](#cicd) before considering any change done**, since there isn't a pipeline doing it for you yet: `composer test`, `composer analyse`, `composer audit`, `npm audit --omit=dev`, and at minimum the Chromium Playwright project. If you touch anything role-management-, dropdown-, or modal-related, run the full 3-browser Playwright matrix — those are the areas with the most existing coverage and the most history of subtle regressions.
10. **Prefer the smallest fix that addresses the actual root cause.** This codebase has a documented history of bugs caused by defensive-but-wrong workarounds (a broken symlink papered over instead of removed, a silently-swallowed exception) outliving the person who wrote them. When you find something wrong, fix where it's actually wrong — don't add a special case somewhere downstream to compensate for it.
