// @ts-check
const path = require('path');
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Priority 6 — CSP blob: image preview fix. Employer Profile's logo-upload
 * preview uses `URL.createObjectURL(file)`, which produces a `blob:` URL.
 * The CSP `img-src` directive (app/Http/Middleware/SecurityHeaders.php) only
 * allowed 'self', data:, and the OpenStreetMap tile host — no blob: — so the
 * browser blocked the preview <img> from loading and logged a CSP violation.
 * Fixed by adding `blob:` to img-src only; no other source was broadened and
 * the CSP header itself was not removed.
 */
test.describe('CSP blob: image preview', () => {
  test('Employer Profile: logo preview renders via blob: URL with zero CSP violations', async ({ page }) => {
    const cspViolations = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error' && /content security policy|refused to load/i.test(msg.text())) {
        cspViolations.push(msg.text());
      }
    });

    await loginAs(page, 'employer');
    await page.goto('/employer_profile');
    await page.waitForLoadState('networkidle');

    await page.locator('div:has(> i.fa-camera)').first().click();
    await expect(page.locator('div[role="dialog"]:has-text("Update Company Logo")')).toBeVisible();

    const fileInput = page.locator('input[type="file"][accept="image/*"]');
    await fileInput.setInputFiles(path.join(__dirname, '..', 'fixtures', 'files', 'test-avatar.png'));

    const preview = page.locator('img[alt="New Logo Preview"]');
    await expect(preview).toBeVisible({ timeout: 5000 });

    const src = await preview.getAttribute('src');
    expect(src).toMatch(/^blob:/);

    // The image element must have actually decoded the blob (naturalWidth>0),
    // not just be present in the DOM with a blocked/broken src.
    const naturalWidth = await preview.evaluate((img) => img.naturalWidth);
    expect(naturalWidth).toBeGreaterThan(0);

    expect(cspViolations).toEqual([]);
  });
});
