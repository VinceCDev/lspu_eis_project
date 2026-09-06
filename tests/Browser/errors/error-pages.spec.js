// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Error pages — real browser', () => {
  test('404 page renders styled (CSS loads) with correct status, no sensitive info', async ({ page }) => {
    const cssResponses = [];
    page.on('response', (res) => {
      if (res.url().includes('tailwind.css')) cssResponses.push(res.status());
    });

    const response = await page.goto('/this_route_does_not_exist_xyz_browser_test');
    expect(response.status()).toBe(404);
    await page.waitForLoadState('networkidle');

    expect(cssResponses).toContain(200);
    await expect(page.locator('body')).toContainText(/404|not found/i);

    // No stack trace / exception class names leaked
    const bodyText = await page.locator('body').innerText();
    expect(bodyText).not.toMatch(/Illuminate\\|Symfony\\|Stack trace|\.php on line/i);

    await page.screenshot({ path: 'tests/Browser/screenshots/404-page.png' });
  });

  test('404 page has a working link back into the application', async ({ page }) => {
    await page.goto('/this_route_does_not_exist_xyz_browser_test');
    const link = page.locator('a', { hasText: /return|home|lspu/i }).first();
    await expect(link).toBeVisible();
    await link.click();
    await page.waitForLoadState('networkidle');
    expect(page.url()).not.toContain('this_route_does_not_exist');
  });
});
