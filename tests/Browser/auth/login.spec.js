// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs, accounts } = require('../fixtures/helpers');

test.describe('Login (real browser, real form)', () => {
  test('valid credentials for each role reach that role\'s home page', async ({ page }) => {
    for (const roleKey of ['alumni', 'employer', 'admin', 'superadmin']) {
      const account = await loginAs(page, roleKey);
      expect(page.url()).toContain(account.homePath);
      // logout between iterations so the next loginAs() starts unauthenticated
      await page.goto('/logout');
    }
  });

  test('invalid password shows an error and does not navigate away from /login', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', accounts.alumni.email);
    await page.fill('input[name="password"]', 'WrongPassword!');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1000); // AJAX round trip, no navigation to wait for
    expect(page.url()).toContain('/login');
    await expect(page.locator('body')).toContainText(/invalid/i);
  });

  test('empty fields do not submit to the server (HTML5 required validation)', async ({ page }) => {
    await page.goto('/login');
    const emailInput = page.locator('input[name="email"]');
    await page.click('button[type="submit"]');
    // required attribute should block submission — still on /login, field
    // reports itself invalid via the constraint validation API
    expect(page.url()).toContain('/login');
    const isValid = await emailInput.evaluate((el) => el.checkValidity());
    expect(isValid).toBe(false);
  });

  test('logout actually ends the session (protected page redirects after)', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/logout');
    await page.waitForURL(/\/login/);
    const response = await page.goto('/home');
    expect(response.url()).toContain('/login');
  });
});
