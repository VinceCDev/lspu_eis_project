// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');
const { loginAs } = require('../fixtures/helpers');

test.setTimeout(90000);
const FIX = (f) => path.resolve(__dirname, '../fixtures/', f);

/**
 * "Can't upload a picture in the User Account module."
 * Create an admin account with a photo (PNG / JPG / WEBP) and assert the
 * saved row actually carries an uploads/profile_picture/... path.
 */
for (const ext of ['png', 'jpg', 'webp']) {
  test(`add admin with ${ext.toUpperCase()} photo stores profile_pic`, async ({ page }) => {
    const email = `pic.${ext}.${Date.now()}@phototest.local`;
    let storeBody = '';
    page.on('response', async (r) => {
      if (/admin_user\?action=store/.test(r.url())) { try { storeBody = await r.text(); } catch (e) {} }
    });

    await loginAs(page, 'superadmin');
    await page.goto('/superadmin_user');
    await page.waitForLoadState('networkidle');

    await page.getByRole('button', { name: 'Add Admin' }).click();
    const modal = page.locator('[role="dialog"]');
    await expect(modal).toBeVisible();

    // Step 1: role picker
    await modal.locator('select').first().selectOption('admin');

    // Step 2: the form
    const form = modal.locator('form');
    await expect(form).toBeVisible();
    await form.locator('input[type="text"]').nth(0).fill('Photo');          // First Name
    await form.locator('input[type="text"]').nth(2).fill('Tester ' + ext);  // Last Name (middle name is nth(1))
    // Campus select (superadmin must choose) — the only <select> inside the form
    await form.locator('select').first().selectOption({ index: 1 });
    await form.locator('input[type="email"]').fill(email);
    await form.locator('input[type="file"]').setInputFiles(FIX(`test-avatar.${ext}`));

    await form.getByRole('button', { name: 'Add Admin' }).click();
    await page.waitForResponse(/admin_user\?action=store/);
    await page.waitForTimeout(800);

    console.log(`[${ext}] store response:`, storeBody.slice(0, 250));
    const store = JSON.parse(storeBody || '{}');
    expect(store.success, `store succeeded (msg: ${store.message || ''})`).toBe(true);

    // Read the accounts list back and find our row.
    const listBody = await page.evaluate(async () => {
      const r = await fetch('/admin_user?action=list');
      return r.text();
    });
    const row = (JSON.parse(listBody).accounts || []).find((a) => a.email === email);
    console.log(`[${ext}] saved row profile_pic:`, row && row.profile_pic);
    expect(row, 'account is in the list').toBeTruthy();
    expect(String(row.profile_pic || '')).toMatch(/uploads\/profile_picture\//);
  });
}

test('a non-image file is rejected with a clear message (not silently)', async ({ page }) => {
  const email = `pic.bad.${Date.now()}@phototest.local`;

  await loginAs(page, 'superadmin');
  await page.goto('/superadmin_user');
  await page.waitForLoadState('networkidle');
  await page.getByRole('button', { name: 'Add Admin' }).click();
  const modal = page.locator('[role="dialog"]');
  await modal.locator('select').first().selectOption('admin');
  const form = modal.locator('form');
  await form.locator('input[type="text"]').nth(0).fill('Bad');
  await form.locator('input[type="text"]').nth(2).fill('Upload');
  await form.locator('select').first().selectOption({ index: 1 });
  await form.locator('input[type="email"]').fill(email);
  // A real file that is not a supported image (the import fixture CSV).
  await form.locator('input[type="file"]').setInputFiles(FIX('dummy-import.csv'));

  const [resp] = await Promise.all([
    page.waitForResponse(/admin_user\?action=store/),
    form.getByRole('button', { name: 'Add Admin' }).click(),
  ]);
  const store = await resp.json();
  console.log('bad-upload response:', JSON.stringify(store));

  expect(store.success).toBe(false);
  expect(String(store.message)).toMatch(/photo not uploaded/i);
});
