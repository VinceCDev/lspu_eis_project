// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Priority 5 — Toast notification layering. Root cause: the toast containers
 * on several pages (alumni home/message/my_application, shared landing, the
 * admin/employer header notification component) used z-[100], the SAME
 * layer as several modals (logout modals) and BELOW the Add/Edit modals
 * (z-[210]/[220]) and the photo-crop modal (z-[300]) — so a toast fired
 * while a modal was open rendered underneath it, invisible to the user.
 *
 * Fix: raised those toast containers to z-[9999], matching the value this
 * codebase already uses for its own loading-spinner overlays and for the
 * one toast implementation (alumni/my_profile.blade.php) that was already
 * layered correctly — not an arbitrary 999999, but the app's own existing
 * "always on top" convention applied consistently. No modal z-index or
 * Vue state/logic was touched.
 */
test.describe('Toast layering above modals', () => {
  test('Admin: error toast (duplicate email, modal stays open) renders above the Add Admin modal', async ({ page }) => {
    await loginAs(page, 'superadmin');
    const email = `browsertest-toast-dup-${Date.now()}@example.test`;

    // Create an account once
    await page.goto('/admin_user');
    await page.waitForLoadState('networkidle');
    await page.click('button:has-text("Add Admin")');
    let modal = page.locator('div[role="dialog"]');
    await modal.locator('select').selectOption('employer');
    await modal.locator('input[type="text"]').nth(0).fill('Toast Layering Co');
    await modal.locator('input[type="text"]').nth(1).fill('IT');
    await modal.locator('input[type="email"]').fill(email);
    await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('admin_user')),
      modal.locator('button[type="submit"]').click(),
    ]);
    await expect(modal).not.toBeVisible({ timeout: 5000 });

    // Try again with the same email — this fires an error toast while the
    // modal stays open (see admin_user.js addAdminSubmit: only success closes it)
    await page.click('button:has-text("Add Admin")');
    modal = page.locator('div[role="dialog"]');
    await modal.locator('select').selectOption('employer');
    await modal.locator('input[type="text"]').nth(0).fill('Toast Layering Co 2');
    await modal.locator('input[type="text"]').nth(1).fill('IT');
    await modal.locator('input[type="email"]').fill(email);

    await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('admin_user')),
      modal.locator('button[type="submit"]').click(),
    ]);

    const toast = page.locator('.notification-toast');
    await expect(toast).toBeVisible({ timeout: 5000 });
    await expect(modal).toBeVisible(); // modal stays open on failure

    // Both are on screen simultaneously — prove the toast is the actual
    // topmost element at its own location, not obscured by the modal.
    const box = await toast.boundingBox();
    expect(box).not.toBeNull();
    const cx = box.x + box.width / 2;
    const cy = box.y + box.height / 2;
    const toastIsTopmost = await page.evaluate(([x, y]) => {
      const top = document.elementFromPoint(x, y);
      const toastEl = document.querySelector('.notification-toast');
      return !!(toastEl && top && toastEl.contains(top));
    }, [cx, cy]);
    expect(toastIsTopmost).toBe(true);

    const toastZ = await toast.evaluate((el) => parseInt(getComputedStyle(el).zIndex, 10));
    const modalZ = await modal.evaluate((el) => parseInt(getComputedStyle(el).zIndex, 10));
    expect(toastZ).toBeGreaterThan(modalZ);
  });
});
