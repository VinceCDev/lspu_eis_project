// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Final targeted validation pass — Admin/Superadmin Settings page. Only
 * Notification Preferences is exercised end-to-end (toggle -> save ->
 * reload -> confirm persisted -> revert) since it's a per-user, fully
 * reversible preference with zero blast radius. Password Policy and
 * Two-Factor toggles are intentionally NOT automated here: both are
 * site-wide/account-wide settings that can change login behavior for
 * every account (password policy) or this fixture's own login flow
 * (2FA) — flipping them from an unattended test run risks locking out
 * or altering behavior for every other suite that logs in afterward.
 * Verified read-only instead; documented as MANUAL TEST REQUIRED in the
 * final report.
 */
test.describe('Admin/Superadmin — Settings', () => {
  test('Notification Preferences: toggle, save, persist after reload, then revert', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin_settings');
    await page.waitForLoadState('networkidle');

    await page.click('button:has-text("Notifications")');
    const firstCheckbox = page.locator('label:has(input[type="checkbox"])').first().locator('input[type="checkbox"]');
    await expect(firstCheckbox).toBeVisible();
    const originalChecked = await firstCheckbox.isChecked();

    await firstCheckbox.setChecked(!originalChecked);
    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('updateNotificationPreferences')),
      page.click('button:has-text("Save")'),
    ]);
    expect(response.status()).toBeLessThan(500);
    const body = await response.json();
    console.log('SAVE NOTIFICATION PREFS RESPONSE:', JSON.stringify(body));
    expect(body.success).toBe(true);

    await page.reload();
    await page.waitForLoadState('networkidle');
    await page.click('button:has-text("Notifications")');
    const reloadedCheckbox = page.locator('label:has(input[type="checkbox"])').first().locator('input[type="checkbox"]');
    await expect(reloadedCheckbox).toBeChecked({ checked: !originalChecked });

    // Revert to the original value so this fixture's preferences are
    // unchanged for any other suite/run.
    await reloadedCheckbox.setChecked(originalChecked);
    await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('updateNotificationPreferences')),
      page.click('button:has-text("Save")'),
    ]);
  });

  test('Security tab: password policy and 2FA controls render with real, current values', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await page.goto('/superadmin_settings');
    await page.waitForLoadState('networkidle');

    await page.click('button:has-text("Security")');
    await expect(page.locator('input[type="number"]')).toBeVisible();
    const minLength = await page.locator('input[type="number"]').inputValue();
    expect(Number(minLength)).toBeGreaterThanOrEqual(8);

    await expect(page.locator('text=Two-Factor Authentication')).toBeVisible();
    await expect(page.locator('input[type="checkbox"][disabled]').first().or(page.locator('label:has-text("Enabled"), label:has-text("Disabled")').first())).toBeVisible();
  });
});
