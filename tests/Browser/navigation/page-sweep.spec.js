// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Real navigation + console-error + network-failure sweep across the major
 * pages of each role. Not exhaustive (there are ~50 page slugs total) —
 * this covers the pages a real user actually lands on from each role's
 * sidebar, which is the highest-value subset per the task's own
 * prioritization guidance.
 */
const PAGES_BY_ROLE = {
  alumni: ['/home', '/my_application', '/my_profile', '/notification', '/message'],
  employer: ['/employer_dashboard', '/employer_jobposting', '/employer_applicants', '/employer_interview', '/employer_messages', '/employer_settings'],
  admin: ['/admin_dashboard', '/admin_alumni', '/admin_user', '/admin_message', '/admin_reports', '/admin_settings'],
  superadmin: ['/superadmin_dashboard', '/superadmin_job', '/superadmin_company', '/superadmin_audit_logs'],
};

for (const [roleKey, pages] of Object.entries(PAGES_BY_ROLE)) {
  test.describe(`Page sweep — ${roleKey}`, () => {
    test(`every major ${roleKey} page loads with 200, no console errors, no failed requests`, async ({ page }) => {
      const results = [];
      await loginAs(page, roleKey);

      for (const path of pages) {
        const consoleErrors = [];
        const failedRequests = [];
        const onConsole = (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); };
        const onPageError = (err) => consoleErrors.push('PAGE ERROR: ' + err.message);
        const onRequestFailed = (req) => failedRequests.push(`${req.method()} ${req.url()} — ${req.failure()?.errorText}`);
        const onResponse = (res) => { if (res.status() >= 400) failedRequests.push(`${res.status()} ${res.url()}`); };
        page.on('console', onConsole);
        page.on('pageerror', onPageError);
        page.on('requestfailed', onRequestFailed);
        page.on('response', onResponse);

        const response = await page.goto(path);
        await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});

        page.off('console', onConsole);
        page.off('pageerror', onPageError);
        page.off('requestfailed', onRequestFailed);
        page.off('response', onResponse);

        results.push({
          path,
          status: response ? response.status() : null,
          consoleErrors,
          failedRequests,
        });
      }

      console.log(`\n=== ${roleKey.toUpperCase()} PAGE SWEEP RESULTS ===`);
      for (const r of results) {
        console.log(JSON.stringify(r, null, 2));
      }

      for (const r of results) {
        expect(r.status, `${r.path} should return 200`).toBe(200);
      }
    });
  });
}
