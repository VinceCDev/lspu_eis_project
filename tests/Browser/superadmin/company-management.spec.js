// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Final targeted validation pass — Superadmin Company/Employer management
 * (view/edit/delete). Uses the shared browsertest-employer fixture for
 * View + Edit (value restored after) and a disposable, timestamp-tagged
 * employer account (created through admin_user's "Add Account" flow — the
 * same legitimate-creation pattern as admin/account-creation.spec.js,
 * which only superadmin may use for the employer role) for Delete.
 */
test.describe('Superadmin — Company management (view/edit/delete)', () => {
  test('View: opens the company detail modal with real profile data', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await page.goto('/superadmin_company');
    await page.waitForLoadState('networkidle');

    // Attached only after the target page has settled — attaching earlier
    // (right after loginAs()) can pick up requests Firefox reports as
    // "failed" simply because this goto() cancelled the login destination
    // page's own in-flight background requests, a navigation artifact, not
    // a real app failure.
    const failedRequests = [];
    page.on('requestfailed', (req) => failedRequests.push(req.url()));

    await page.fill('input[placeholder="Search company..."]', 'Browser Test Company');
    await page.waitForTimeout(500);

    const row = page.locator('table tbody tr', { hasText: 'Browser Test Company' }).first();
    await expect(row).toBeVisible();
    await row.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("View")').first().click();

    const modal = page.locator('div[role="dialog"]:has-text("Browser Test Company")').first();
    await expect(modal).toBeVisible({ timeout: 5000 });

    expect(failedRequests, `Unexpected failed requests: ${failedRequests.join(', ')}`).toEqual([]);
  });

  test('Edit: updates a field via the real form submission and persists after reload', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await page.goto('/superadmin_company');
    await page.waitForLoadState('networkidle');
    await page.fill('input[placeholder="Search company..."]', 'Browser Test Company');
    await page.waitForTimeout(500);

    const row = page.locator('table tbody tr', { hasText: 'Browser Test Company' }).first();
    await expect(row).toBeVisible();
    await row.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Edit")').first().click();

    const modal = page.locator('div[role="dialog"]:has-text("company-name-input"), div[role="dialog"]').filter({ has: page.locator('#company-name-input') });
    await expect(modal).toBeVisible();

    const marker = `QA${Date.now()}`;
    const nameInput = modal.locator('#company-name-input');
    const originalName = await nameInput.inputValue();
    await nameInput.fill(`${originalName} ${marker}`);

    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('superadmin_company')),
      modal.locator('button[type="submit"]').click(),
    ]);
    expect(response.status()).toBeLessThan(500);
    const body = await response.json();
    console.log('EDIT COMPANY RESPONSE:', JSON.stringify(body));
    expect(body.success).toBe(true);
    await expect(modal).not.toBeVisible({ timeout: 5000 });

    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.fill('input[placeholder="Search company..."]', marker);
    await page.waitForTimeout(500);
    await expect(page.locator('table tbody tr', { hasText: marker })).toBeVisible({ timeout: 10000 });

    // Restore the original company name.
    const restoredRow = page.locator('table tbody tr', { hasText: marker }).first();
    await restoredRow.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Edit")').first().click();
    const restoreModal = page.locator('div[role="dialog"]').filter({ has: page.locator('#company-name-input') });
    await expect(restoreModal).toBeVisible();
    await restoreModal.locator('#company-name-input').fill(originalName);
    await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('superadmin_company')),
      restoreModal.locator('button[type="submit"]').click(),
    ]);
    await expect(restoreModal).not.toBeVisible({ timeout: 5000 });
  });

  test('Delete: removes a disposable employer account and it disappears from the list', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await page.goto('/admin_user');
    await page.waitForLoadState('networkidle');
    await page.click('button:has-text("Add Admin")');
    const addModal = page.locator('div[role="dialog"]');
    await expect(addModal).toBeVisible();
    await addModal.locator('select').selectOption('employer');
    const companyName = `QA Delete Co ${Date.now()}`;
    await addModal.locator('input[type="text"]').nth(0).fill(companyName);
    await addModal.locator('input[type="text"]').nth(1).fill('Information Technology');
    const email = `browsertest-superadmin-company-del-${Date.now()}@example.test`;
    await addModal.locator('input[type="email"]').fill(email);
    const [createResp] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('admin_user')),
      addModal.locator('button[type="submit"]').click(),
    ]);
    expect((await createResp.json()).success).toBe(true);
    await expect(addModal).not.toBeVisible({ timeout: 5000 });

    await page.goto('/superadmin_company');
    await page.waitForLoadState('networkidle');
    const row = page.locator('table tbody tr', { hasText: companyName }).first();
    await expect(row).toBeVisible({ timeout: 10000 });
    await row.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Delete")').first().click();

    const confirmModal = page.locator('div[role="dialog"]:has-text("Delete")').last();
    await expect(confirmModal).toBeVisible();
    const [delResponse] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('superadmin_company') && res.request().method() === 'DELETE'),
      confirmModal.locator('button:has-text("Delete")').last().click(),
    ]);
    expect(delResponse.status()).toBeLessThan(500);
    const delBody = await delResponse.json();
    console.log('DELETE COMPANY RESPONSE:', JSON.stringify(delBody));
    expect(delBody.success).toBe(true);

    await page.reload();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('table tbody tr', { hasText: companyName })).toHaveCount(0);
  });
});
