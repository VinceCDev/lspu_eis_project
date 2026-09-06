// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { loginAs } = require('../fixtures/helpers');

/**
 * D6 — the alumni profile photo-edit trigger was a clickable <div>, not a
 * real <button> — not keyboard-reachable, no accessible name, no native
 * Enter/Space activation. Fixed to <button type="button" aria-label=...>.
 */
test.describe('D6 — Photo edit trigger accessibility', () => {
  test('is a real, focusable button with an accessible name', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    const trigger = page.locator('button[aria-label="Change profile photo"]');
    await expect(trigger).toBeVisible();
    const tagName = await trigger.evaluate((el) => el.tagName);
    expect(tagName).toBe('BUTTON');
  });

  test('opens the photo modal via keyboard (Tab + Enter), not just click', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    const trigger = page.locator('button[aria-label="Change profile photo"]');
    await trigger.focus();
    await expect(trigger).toBeFocused();

    await page.keyboard.press('Enter');
    await expect(page.locator('div[role="dialog"]:has-text("Update Profile Photo")')).toBeVisible({ timeout: 3000 });
  });

  test('opens via Space key too', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    const trigger = page.locator('button[aria-label="Change profile photo"]');
    await trigger.focus();
    await page.keyboard.press(' ');
    await expect(page.locator('div[role="dialog"]:has-text("Update Profile Photo")')).toBeVisible({ timeout: 3000 });
  });

  test('photo upload workflow still functions after the semantic change', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    await page.locator('button[aria-label="Change profile photo"]').click();
    const fileInput = page.locator('input[type="file"][accept="image/*"]');
    await fileInput.setInputFiles(require('path').join(__dirname, '..', 'fixtures', 'files', 'test-avatar.png'));

    const saveButton = page.locator('button:has-text("Save Photo")');
    await expect(saveButton).toBeEnabled({ timeout: 5000 });
    const [response] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('ProfilePic')),
      saveButton.click(),
    ]);
    const body = await response.json();
    expect(body.success).toBe(true);
  });

  test('axe scan: no new violations introduced by the button conversion', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
    const relevant = results.violations.filter((v) => v.nodes.some((n) => n.html.includes('Change profile photo')));
    console.log('AXE VIOLATIONS TOUCHING THE PHOTO BUTTON:', JSON.stringify(relevant));
    expect(relevant).toEqual([]);
  });
});
