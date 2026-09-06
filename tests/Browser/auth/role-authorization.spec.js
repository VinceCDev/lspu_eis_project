// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

test.describe('Role authorization — real browser, direct URL access', () => {
  test('unauthenticated direct access to a protected page redirects to /login', async ({ page }) => {
    const response = await page.goto('/admin_dashboard');
    expect(page.url()).toContain('/login');
    expect(response.status()).toBeLessThan(400); // redirect resolved to a normal 200 login page
  });

  test('alumni hitting an admin-only URL directly gets a real 403 page, not a redirect to /login', async ({ page }) => {
    await loginAs(page, 'alumni');
    const response = await page.goto('/admin_dashboard');

    expect(response.status()).toBe(403);
    expect(page.url()).toContain('/admin_dashboard'); // did NOT redirect to /login
    await expect(page.locator('body')).toContainText(/access denied|forbidden|permission/i);

    await page.screenshot({ path: 'tests/Browser/screenshots/403-page.png' });
  });

  test('employer hitting a superadmin-only URL directly gets 403', async ({ page }) => {
    await loginAs(page, 'employer');
    const response = await page.goto('/superadmin_job');
    expect(response.status()).toBe(403);
    expect(page.url()).not.toContain('/login');
  });

  test('admin CAN reach admin_dashboard, and superadmin CAN reach both admin and superadmin pages', async ({ page }) => {
    await loginAs(page, 'admin');
    let response = await page.goto('/admin_dashboard');
    expect(response.status()).toBe(200);
    await page.goto('/logout');

    await loginAs(page, 'superadmin');
    response = await page.goto('/admin_dashboard');
    expect(response.status()).toBe(200);
    response = await page.goto('/superadmin_job');
    expect(response.status()).toBe(200);
  });

  test('403 page CSS actually loads (regression check for the earlier /frontend/ path bug)', async ({ page }) => {
    await loginAs(page, 'alumni');
    const cssResponses = [];
    page.on('response', (res) => {
      if (res.url().includes('tailwind.css')) cssResponses.push({ url: res.url(), status: res.status() });
    });
    await page.goto('/admin_dashboard'); // 403 for alumni
    await page.waitForLoadState('networkidle');
    console.log('CSS RESPONSES ON 403 PAGE:', JSON.stringify(cssResponses));
    expect(cssResponses.length).toBeGreaterThan(0);
    for (const r of cssResponses) expect(r.status).toBe(200);
  });
});
