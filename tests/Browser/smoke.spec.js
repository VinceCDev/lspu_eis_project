// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Proof-of-concept smoke test: is a real browser actually launching and
 * loading the real, live application? Written and run FIRST, before
 * building out the rest of the browser test suite, specifically so a
 * failure here is caught immediately rather than discovered after writing
 * dozens of tests against a browser that never actually worked.
 */
test('landing page actually loads in a real browser', async ({ page }) => {
  const consoleErrors = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });
  const pageErrors = [];
  page.on('pageerror', (err) => pageErrors.push(err.message));

  const response = await page.goto('/landing');
  expect(response.status()).toBe(200);

  await expect(page).toHaveTitle(/LSPU/i);

  console.log('CONSOLE ERRORS:', JSON.stringify(consoleErrors));
  console.log('PAGE ERRORS:', JSON.stringify(pageErrors));
});
