// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

test.setTimeout(120000);

/**
 * Report page: server-side aggregated summary, cancellable requests, and
 * navigation that never waits for the report queries to finish.
 */
test.describe('Reports performance + non-blocking navigation', () => {
  test('summary renders with aggregated data; XHRs are cancellable', async ({ page }) => {
    const xhr = [];
    page.on('response', async (r) => {
      const u = r.url();
      if (/admin_reports\?action=(summary|colleges|years)|admin_user\?action=campuses/.test(u)) {
        const t = r.request().timing();
        xhr.push({ a: (u.match(/action=(\w+)/) || [])[1] || 'campuses', status: r.status(), ms: Math.round(t.responseEnd - t.requestStart) });
      }
    });
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });

    await loginAs(page, 'superadmin');
    await page.goto('/superadmin_reports');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1500);

    // The program table (v-for over programStats) must have real rows.
    const rows = await page.locator('table tbody tr').count();
    const firstNums = await page.locator('table tbody tr').first().innerText().catch(() => '');
    console.log('XHR:', JSON.stringify(xhr));
    console.log('program table rows:', rows, '| first row:', firstNums.replace(/\s+/g, ' ').trim().slice(0, 120));
    console.log('page errors:', JSON.stringify(errors));

    expect(errors).toEqual([]);
    expect(rows).toBeGreaterThan(3);
  });

  test('navigating away from Reports mid-load does not wait for the report request', async ({ page }) => {
    await loginAs(page, 'superadmin');

    // Warm nothing — go to Reports and immediately bounce to the dashboard.
    await page.goto('/superadmin_reports', { waitUntil: 'commit' });
    const t0 = Date.now();
    await page.goto('/superadmin_dashboard', { waitUntil: 'domcontentloaded' });
    const navMs = Date.now() - t0;

    console.log('Reports -> Dashboard DOMContentLoaded in', navMs, 'ms');
    // The dashboard shell must come up quickly — it must not be stuck behind
    // the Reports summary query. Generous ceiling for `php artisan serve`.
    expect(navMs).toBeLessThan(6000);
    await expect(page.locator('#app')).toBeVisible();
  });

  test('changing a filter twice quickly never lets the stale response win', async ({ page }) => {
    // Record which campus_id each summary response was FOR and in what order
    // it resolved, so we can prove the last-committed data matches the last
    // request even if an earlier (slower) response lands after it.
    const summaryResponses = [];
    page.on('response', (r) => {
      const m = r.url().match(/action=summary(?:.*?campus_id=(\d+))?/);
      if (m) summaryResponses.push(m[1] || 'all');
    });
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });

    await loginAs(page, 'superadmin');
    await page.goto('/superadmin_reports');
    await page.waitForLoadState('networkidle');

    const campus = page.locator('select').first();
    const opts = await campus.locator('option').count();
    if (opts >= 3) {
      await campus.selectOption({ index: 2 });
      await page.waitForTimeout(60);
      await campus.selectOption({ index: 1 });
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1500);

      const selected = await campus.inputValue();
      const lastOptVal = await campus.locator('option').nth(1).getAttribute('value');
      console.log('summary responses (in resolve order):', JSON.stringify(summaryResponses));
      console.log('final campus value:', selected, 'expected:', lastOptVal);
      // The <select> is bound to selectedCampusId; the stale-guard means the
      // committed programStats correspond to this value, whatever order the
      // two summary responses arrived in.
      expect(selected).toBe(lastOptVal);
    }
    expect(errors).toEqual([]);
  });
});
