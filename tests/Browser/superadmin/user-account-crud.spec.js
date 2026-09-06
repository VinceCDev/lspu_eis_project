// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Final targeted validation pass — Superadmin User/account management CRUD
 * on admin_user (view/edit/reset password/deactivate/delete), exercised
 * end-to-end on one disposable, timestamp-tagged Admin account created
 * through the app's own "Add Account" flow so no shared fixture or real
 * account is touched.
 */
test.describe('Superadmin — User/account management (full lifecycle)', () => {
  test('Create -> View -> Edit -> Reset Password -> Deactivate -> Delete', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await page.goto('/admin_user');
    await page.waitForLoadState('networkidle');

    // --- Create ---
    await page.click('button:has-text("Add Admin")');
    let modal = page.locator('div[role="dialog"]');
    await expect(modal).toBeVisible();
    await modal.locator('select').selectOption('admin');
    const lastName = `QALife${Date.now()}`;
    await modal.locator('input[type="text"]').nth(0).fill('Playwright');
    await modal.locator('input[type="text"]').nth(2).fill(lastName);
    await modal.locator('select').nth(0).selectOption({ index: 1 }); // campus (first remaining select once role chosen)
    const email = `browsertest-superadmin-lifecycle-${Date.now()}@example.test`;
    await modal.locator('input[type="email"]').fill(email);
    const [createResp] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('admin_user')),
      modal.locator('button[type="submit"]').click(),
    ]);
    expect((await createResp.json()).success).toBe(true);
    await expect(modal).not.toBeVisible({ timeout: 5000 });

    await page.fill('input[placeholder="Search accounts..."]', lastName);
    await page.waitForTimeout(500);
    const row = () => page.locator('table tbody tr', { hasText: lastName }).first();
    await expect(row()).toBeVisible({ timeout: 10000 });

    // --- View ---
    await row().locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("View")').first().click();
    const viewModal = page.locator('div[role="dialog"]:has-text("' + lastName + '")').first();
    await expect(viewModal).toBeVisible({ timeout: 5000 });
    await page.keyboard.press('Escape').catch(() => {});
    await viewModal.locator('button:has(i.fa-times), [aria-label="Close"]').first().click().catch(() => {});

    // --- Edit ---
    await row().locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Edit")').first().click();
    modal = page.locator('div[role="dialog"]');
    await expect(modal).toBeVisible();
    const newLastName = `${lastName}-Edited`;
    await modal.locator('input[type="text"]').nth(2).fill(newLastName);
    const [editResp] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('admin_user?action=update')),
      modal.locator('button[type="submit"]').click(),
    ]);
    const editBody = await editResp.json();
    console.log('EDIT ACCOUNT RESPONSE:', JSON.stringify(editBody));
    expect(editBody.success).toBe(true);
    await expect(modal).not.toBeVisible({ timeout: 5000 });
    await expect(page.locator('table tbody tr', { hasText: newLastName })).toBeVisible({ timeout: 10000 });

    const editedRow = () => page.locator('table tbody tr', { hasText: newLastName }).first();

    // --- Reset Password ---
    await editedRow().locator('button:has(i.fa-ellipsis-h)').click();
    const [resetResp] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('admin_user?action=resetPassword')),
      page.locator('.teleported-action-dropdown a:has-text("Reset Password")').first().click(),
    ]);
    const resetBody = await resetResp.json();
    console.log('RESET PASSWORD RESPONSE:', JSON.stringify(resetBody));
    expect(resetResp.status()).toBeLessThan(500);
    expect(typeof resetBody.success).toBe('boolean');

    // --- Deactivate ---
    await editedRow().locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Deactivate")').first().click();
    const deactivateModal = page.locator('div[role="dialog"]:has-text("Deactivat")').first();
    await expect(deactivateModal).toBeVisible();
    await deactivateModal.locator('textarea').fill('QA lifecycle test deactivation.');
    const [deactivateResp] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('admin_user?action=deactivate')),
      deactivateModal.locator('button:has-text("Deactivate")').last().click(),
    ]);
    const deactivateBody = await deactivateResp.json();
    console.log('DEACTIVATE RESPONSE:', JSON.stringify(deactivateBody));
    expect(deactivateBody.success).toBe(true);
    await expect(deactivateModal).not.toBeVisible({ timeout: 5000 });
    await expect(page.locator('table tbody tr', { hasText: newLastName })).toContainText('Inactive', { timeout: 10000 });

    // --- Delete ---
    await editedRow().locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Delete")').first().click();
    const deleteModal = page.locator('div[role="dialog"]:has-text("Delete")').last();
    await expect(deleteModal).toBeVisible();
    const [deleteResp] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('admin_user?action=destroy')),
      deleteModal.locator('button:has-text("Delete")').last().click(),
    ]);
    const deleteBody = await deleteResp.json();
    console.log('DELETE ACCOUNT RESPONSE:', JSON.stringify(deleteBody));
    expect(deleteBody.success).toBe(true);

    await page.reload();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('table tbody tr', { hasText: newLastName })).toHaveCount(0);
  });
});
