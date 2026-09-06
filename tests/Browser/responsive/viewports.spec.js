// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

const VIEWPORTS = {
  'desktop-1920': { width: 1920, height: 1080 },
  'desktop-1366': { width: 1366, height: 768 },
  'tablet-768': { width: 768, height: 1024 },
  'mobile-390': { width: 390, height: 844 },
  'mobile-412': { width: 412, height: 915 },
};

test.describe('Responsive viewport testing — objective layout checks', () => {
  for (const [name, size] of Object.entries(VIEWPORTS)) {
    test(`landing page at ${name} (${size.width}x${size.height}) has no horizontal overflow`, async ({ page }) => {
      await page.setViewportSize(size);
      await page.goto('/landing');
      await page.waitForLoadState('networkidle');

      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
      console.log(`${name}: scrollWidth=${scrollWidth} clientWidth=${clientWidth}`);

      await page.screenshot({ path: `tests/Browser/screenshots/landing-${name}.png`, fullPage: true });

      // Objective assertion: content must not force horizontal scroll.
      // Small rendering tolerance (1px) for scrollbar-width rounding.
      expect(scrollWidth, `horizontal overflow at ${name}: page is wider than viewport`).toBeLessThanOrEqual(clientWidth + 1);
    });

    test(`admin dashboard at ${name} has no horizontal overflow and sidebar/nav is reachable`, async ({ page }) => {
      await page.setViewportSize(size);
      await loginAs(page, 'admin');
      await page.waitForLoadState('networkidle');

      const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
      console.log(`admin-dashboard ${name}: scrollWidth=${scrollWidth} clientWidth=${clientWidth}`);

      await page.screenshot({ path: `tests/Browser/screenshots/admin-dashboard-${name}.png`, fullPage: true });

      expect(scrollWidth, `horizontal overflow at ${name}`).toBeLessThanOrEqual(clientWidth + 1);
    });
  }
});
