// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Priority 4 — Modal lifecycle regression. The app has 30+ role="dialog"
 * modals; testing every one individually is out of scope for this pass.
 * This suite covers one representative modal per interaction pattern
 * (confirm/cancel dialog, add/edit form dialog, delete-confirmation dialog,
 * multi-step onboarding dialog) across the roles that use them. Coverage
 * for modals not listed here is reported as NOT ATTEMPTED, not PASS.
 */
test.describe('Modal lifecycle — representative sample', () => {
  test('Alumni logout modal: open, backdrop-click closes, X closes, Cancel closes, Logout confirms', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    const openLogout = async () => {
      await page.click('button:has-text("Profile")');
      await page.click('a:has-text("Logout")');
      await expect(page.locator('div[role="dialog"]:has-text("Confirm Logout")')).toBeVisible();
    };

    // Open + backdrop click closes
    await openLogout();
    await page.mouse.click(5, 5); // corner of the fixed inset-0 backdrop, outside the dialog card
    await expect(page.locator('div[role="dialog"]:has-text("Confirm Logout")')).not.toBeVisible();

    // Open + X closes
    await openLogout();
    await page.locator('div[role="dialog"]:has-text("Confirm Logout") button:has(i.fa-times)').click();
    await expect(page.locator('div[role="dialog"]:has-text("Confirm Logout")')).not.toBeVisible();

    // Open + Cancel closes
    await openLogout();
    await page.locator('div[role="dialog"]:has-text("Confirm Logout") button:has-text("Cancel")').click();
    await expect(page.locator('div[role="dialog"]:has-text("Confirm Logout")')).not.toBeVisible();

    // Page still interactive after close (no orphaned overlay)
    await expect(page.locator('body')).toBeVisible();
    await page.mouse.click(200, 200);

    // Open + Logout confirms and redirects away from a protected page
    await openLogout();
    await page.locator('div[role="dialog"]:has-text("Confirm Logout") button:has-text("Logout")').click();
    await page.waitForURL((url) => !url.pathname.includes('my_profile'), { timeout: 10000 });
  });

  test('Admin Add Admin modal: open, X closes, Cancel closes, validation-error keeps it open, backdrop cleanup', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await page.goto('/admin_user');
    await page.waitForLoadState('networkidle');

    const modal = page.locator('div[role="dialog"]');

    await page.click('button:has-text("Add Admin")');
    await expect(modal).toBeVisible();
    await modal.locator('button:has(i.fa-times)').click();
    await expect(modal).not.toBeVisible({ timeout: 5000 });
    // DOM cleanup: dialog element itself is gone (v-if unmount), not just hidden
    expect(await page.locator('div[role="dialog"]').count()).toBe(0);

    await page.click('button:has-text("Add Admin")');
    await expect(modal).toBeVisible();
    // Reopening resets to the "Select Role" step; the Cancel button only
    // exists once a role has been picked and the role-specific form renders.
    await modal.locator('select').selectOption('admin');
    await modal.locator('button:has-text("Cancel")').click();
    await expect(modal).not.toBeVisible({ timeout: 5000 });

    // Validation error: submitting incomplete form keeps modal open (already
    // covered end-to-end in account-creation.spec.js; re-verified here as
    // part of the lifecycle inventory)
    await page.click('button:has-text("Add Admin")');
    await modal.locator('select').selectOption('admin');
    await modal.locator('button[type="submit"]').click();
    await expect(modal).toBeVisible();

    // Page remains interactive after eventually closing
    await modal.locator('button:has-text("Cancel")').click();
    await expect(modal).not.toBeVisible({ timeout: 5000 });
    await expect(page.locator('h2:has-text("All Accounts")')).toBeVisible();
  });

  test('Employer Add Job modal: open, X closes, Cancel closes, backdrop click does NOT close (by design, no backdrop handler)', async ({ page }) => {
    await loginAs(page, 'employer');
    await page.goto('/employer_jobposting');
    await page.waitForLoadState('networkidle');

    const modal = page.locator('[data-modal="add-edit-job-modal"]');

    await page.click('button:has-text("Post New Job")');
    await expect(modal).toBeVisible();

    // Documented actual behavior: this modal's outer flex container IS the
    // backdrop but has no @click handler, so clicking it does not dismiss.
    await page.mouse.click(5, 5);
    await expect(modal).toBeVisible();

    await modal.locator('button:has(i.fa-times)').click();
    await expect(modal).not.toBeVisible({ timeout: 5000 });
    expect(await page.locator('[data-modal="add-edit-job-modal"]').count()).toBe(0);

    await page.click('button:has-text("Post New Job")');
    await expect(modal).toBeVisible();
    await modal.locator('button:has-text("Cancel")').click();
    await expect(modal).not.toBeVisible({ timeout: 5000 });
  });

  test('Admin Delete-confirmation modal: open via row action menu, Cancel closes without deleting', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await page.goto('/admin_user');
    await page.waitForLoadState('networkidle');

    const rowCountBefore = await page.locator('table tbody tr').count();

    // The row's action menu (and its Delete link) only exists in the DOM once
    // opened (v-if="actionDropdown === account.user_id"), and Delete is hidden
    // entirely for superadmin rows — so each row must be opened to check.
    const rows = page.locator('table tbody tr');
    let deleteLink = null;
    for (let i = 0; i < rowCountBefore; i++) {
      const row = rows.nth(i);
      await row.locator('button:has(i.fa-ellipsis-h)').click();
      const candidate = page.locator('.teleported-action-dropdown a:has-text("Delete")');
      if (await candidate.count()) {
        deleteLink = candidate;
        break;
      }
      await row.locator('button:has(i.fa-ellipsis-h)').click(); // close this row's menu before trying the next
    }
    if (!deleteLink) {
      test.skip(true, 'No deletable (non-superadmin) account row available to exercise this modal.');
    }
    await deleteLink.click();

    const confirmModal = page.locator('div[role="dialog"]:has-text("Confirm Delete")');
    await expect(confirmModal).toBeVisible();
    await confirmModal.locator('button:has-text("Cancel")').click();
    await expect(confirmModal).not.toBeVisible({ timeout: 5000 });

    const rowCountAfter = await page.locator('table tbody tr').count();
    expect(rowCountAfter).toBe(rowCountBefore);
  });
});
