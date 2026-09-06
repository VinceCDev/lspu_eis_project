// @ts-check
const path = require('path');
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Employer Profile "Update Company Logo" modal — reported bug: the modal
 * stayed open after a successful logo change. Root cause: public/assets/js/
 * employer_profile.js defined `saveLogo()` (and `handleLogoUpload()`) TWICE
 * in the same Vue options object. JavaScript object literals silently keep
 * only the LAST definition, so the real, active saveLogo() was the second
 * one — which posts to the server correctly, but on success set
 * `this.showLogoModal = false` instead of `this.showPhotoModal = false`
 * (the actual flag the modal's `v-if` reads). showLogoModal was leftover
 * dead state from an earlier refactor, never read anywhere in the template,
 * so the assignment was a no-op and the modal never closed.
 *
 * Fixed by removing the dead first saveLogo()/handleLogoUpload() pair and
 * the unused showLogoModal state, and correcting the surviving saveLogo()
 * to flip showPhotoModal. Also found and fixed a second bug in the same
 * file: the duplicate-key issue had likewise erased the preview-generation
 * logic (createObjectURL/FileReader) from the effective handleDocumentUpload,
 * so the Document modal's preview <iframe>/<img> never had a real source —
 * restored it, and added the missing `frame-src 'self' blob:` CSP directive
 * that the PDF <iframe> preview needs (it isn't covered by img-src).
 */
test.describe('Employer Profile modals — close-after-success', () => {
  test('Update Company Logo modal closes and shows the new logo after a successful save', async ({ page }) => {
    await loginAs(page, 'employer');
    await page.goto('/employer_profile');
    await page.waitForLoadState('networkidle');

    await page.locator('div:has(> i.fa-camera)').first().click();
    const modal = page.locator('div[role="dialog"]:has-text("Update Company Logo")');
    await expect(modal).toBeVisible();

    await modal.locator('input[type="file"][accept="image/*"]').setInputFiles(
      path.join(__dirname, '..', 'fixtures', 'files', 'test-avatar.png')
    );
    await expect(modal.locator('img[alt="New Logo Preview"]')).toBeVisible();

    await Promise.all([
      page.waitForResponse((res) => res.url().includes('updateLogo')),
      modal.locator('button:has-text("Update Logo")').click(),
    ]);

    await expect(modal).not.toBeVisible({ timeout: 5000 });
    expect(await page.locator('div[role="dialog"]:has-text("Update Company Logo")').count()).toBe(0);
  });

  test('Update Document modal: preview renders for an uploaded image and modal closes on save', async ({ page }) => {
    await loginAs(page, 'employer');
    await page.goto('/employer_profile');
    await page.waitForLoadState('networkidle');

    const cspViolations = [];
    page.on('console', (msg) => {
      if (msg.type() === 'error' && /content security policy|refused to (load|frame)/i.test(msg.text())) {
        cspViolations.push(msg.text());
      }
    });

    await page.locator('button:has-text("Change"), button:has-text("Upload")').first().click();
    const modal = page.locator('div[role="dialog"]:has-text("Change Company Document")');
    await expect(modal).toBeVisible();

    await modal.locator('input[type="file"]').setInputFiles(
      path.join(__dirname, '..', 'fixtures', 'files', 'test-avatar.png')
    );
    await expect(modal.locator('img[alt="Document Preview"]')).toBeVisible({ timeout: 5000 });

    await Promise.all([
      page.waitForResponse((res) => res.url().includes('updateDocument')),
      modal.locator('button:has-text("Save Document")').click(),
    ]);
    await expect(modal).not.toBeVisible({ timeout: 5000 });

    expect(cspViolations).toEqual([]);
  });
});
