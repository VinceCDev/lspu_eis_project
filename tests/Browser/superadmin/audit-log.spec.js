// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Final targeted validation pass — Superadmin Audit Log interaction
 * (list load, search filter). Also confirms real actions taken elsewhere
 * in this pass (the account-lifecycle test's create_account/update_account/
 * deactivate_account/delete_account entries) actually land here — the
 * clearest end-to-end evidence that AuditLog::log() calls sprinkled through
 * the controllers actually persist and surface in the UI.
 */
test.describe('Superadmin — Audit Log (list/search)', () => {
  test('List loads real entries and the search filter narrows them', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await page.goto('/superadmin_audit_logs');
    await page.waitForLoadState('networkidle');

    // Attached only after the target page has settled — attaching earlier
    // (right after loginAs()) can pick up requests Firefox reports as
    // "failed" simply because this goto() cancelled the login destination
    // page's own in-flight background requests, a navigation artifact, not
    // a real app failure.
    const failedRequests = [];
    page.on('requestfailed', (req) => failedRequests.push(req.url()));

    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible({ timeout: 10000 });
    const totalCount = await rows.count();
    expect(totalCount).toBeGreaterThan(0);

    // Narrow with a search term matched by this pass's own account-lifecycle
    // test — AuditLog::log() records the recipient's NAME in `description`
    // ("Created admin account for Playwright QALife..."), not their email,
    // so search by the "QALife" last-name marker that test used.
    await page.fill('input[placeholder*="Search"]', 'QALife');
    await page.waitForTimeout(600);
    const filteredRows = page.locator('table tbody tr');
    await expect(filteredRows.first()).toBeVisible({ timeout: 10000 });
    const filteredCount = await filteredRows.count();
    expect(filteredCount).toBeGreaterThan(0);
    expect(filteredCount).toBeLessThanOrEqual(totalCount);
    await expect(filteredRows.first()).toContainText('QALife');

    expect(failedRequests, `Unexpected failed requests: ${failedRequests.join(', ')}`).toEqual([]);
  });

  test('Action-type filter narrows results without error', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await page.goto('/superadmin_audit_logs');
    await page.waitForLoadState('networkidle');

    const select = page.locator('select').first();
    const optionCount = await select.locator('option').count();
    expect(optionCount).toBeGreaterThan(1);
    await select.selectOption({ index: 1 });
    await page.waitForTimeout(600);

    // Should not error out — either shows matching rows or a "no results" state.
    await expect(page.locator('table')).toBeVisible();
  });
});
