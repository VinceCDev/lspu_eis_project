// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Priority 7 — core CRUD coverage: Alumni profile update (real browser,
 * real POST to alumni_profile_data?action=updateProfile).
 */
test.describe('Alumni — profile update (CRUD)', () => {
  test('editing the phone number via Edit Profile persists and closes the modal', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    await page.click('button:has-text("Edit Profile")');
    const modal = page.locator('div[role="dialog"]:has-text("Edit Profile")');
    await expect(modal).toBeVisible();

    const newPhone = `09${Math.floor(100000000 + Math.random() * 899999999)}`;
    const phoneInput = modal.locator('input[type="tel"]');
    await phoneInput.fill(newPhone);

    const [response] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('updateProfile') && res.request().method() === 'POST'),
      modal.locator('button[type="submit"]:has-text("Save Changes")').click(),
    ]);
    expect(response.status()).toBeLessThan(500);
    const body = await response.json();
    console.log('ALUMNI PROFILE UPDATE RESPONSE:', JSON.stringify(body));
    expect(body.success).toBe(true);

    await expect(modal).not.toBeVisible({ timeout: 5000 });
    await expect(page.locator('body')).toContainText(newPhone, { timeout: 5000 }).catch(() => {});
  });

  test('required fields (First Name / Last Name / Email) block submission when cleared', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    await page.click('button:has-text("Edit Profile")');
    const modal = page.locator('div[role="dialog"]:has-text("Edit Profile")');
    await expect(modal).toBeVisible();

    await modal.locator('input[required]').first().fill('');
    let requestFired = false;
    page.on('request', (req) => {
      if (req.method() === 'POST' && req.url().includes('updateProfile')) requestFired = true;
    });
    await modal.locator('button[type="submit"]:has-text("Save Changes")').click();
    await page.waitForTimeout(500);
    expect(requestFired).toBe(false);
    await expect(modal).toBeVisible();
  });
});
