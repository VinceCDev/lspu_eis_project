// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * ISO/IEC 25010 frontend/UI browser testing phase. This app has no build
 * step (Vue is loaded per-page via <script> tags and compiles its
 * @verbatim Blade templates in the browser at runtime — see
 * app/Http/Middleware/SecurityHeaders.php's docblock from the previous
 * remediation pass for why CSP allows unsafe-eval), so there is nothing
 * for Playwright to build — baseURL just needs a running `php artisan serve`.
 */
module.exports = defineConfig({
  testDir: './tests/Browser',
  fullyParallel: false, // shared MySQL test data (uniqid-tagged rows) — avoid cross-test races
  forbidOnly: !!process.env.CI,
  retries: 0,
  workers: 1,
  reporter: [['html', { open: 'never' }], ['list']],
  // 45s (not Playwright's 30s default): this app runs on `php artisan
  // serve`, a single-threaded dev server (documented in an earlier
  // remediation pass) — a test that navigates through several real pages
  // back-to-back can genuinely queue behind itself under that constraint.
  // Confirmed via a real run: the same page-sweep spec that timed out at
  // 30s when 4 role-sweeps ran back-to-back in one invocation passed
  // cleanly (zero errors) when the timeout was long enough for the
  // single-threaded server to catch up. Not a frontend defect.
  timeout: 45000,
  use: {
    baseURL: 'http://127.0.0.1:8000',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
    { name: 'webkit', use: { ...devices['Desktop Safari'] } },
  ],
});
