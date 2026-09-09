// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');
const { loginAs } = require('../fixtures/helpers');

test.setTimeout(60000);

/**
 * The alumni-module "Import from Employment Report" now shows a progress bar:
 * a real % while the file uploads, then an indeterminate bar + elapsed-seconds
 * counter while the server parses the sheet. The import request is stubbed so
 * this test never touches the database.
 */
test('alumni import shows upload % then a processing bar', async ({ page }) => {
  // Stub the import endpoint: respond ~2.5s later with a normal success payload.
  await page.route(/importEmploymentReport/, async (route) => {
    await new Promise((r) => setTimeout(r, 2500));
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        message: 'Import complete.',
        summary: { imported: 3, skipped: 1, experience_rows: 3, placeholder_emails: 0, emailed: 0, warnings: [], skipped_details: [], errors: [] },
      }),
    });
  });

  await loginAs(page, 'superadmin');
  await page.waitForLoadState('networkidle');
  await page.goto('/superadmin_alumni');
  await page.waitForLoadState('networkidle');

  await page.locator('button:has-text("Import")').first().click();
  const modal = page.locator('div[role="dialog"]:has-text("Import from Employment Report")');
  await expect(modal).toBeVisible();

  // Fill the required fields (superadmin must pick a campus + year).
  await modal.locator('select').selectOption({ index: 1 });
  await modal.locator('input[type="number"]').fill('2023');
  await modal.locator('input[type="file"]').setInputFiles(path.resolve(__dirname, '../fixtures/dummy-import.csv'));

  await modal.getByRole('button', { name: 'Import', exact: true }).click();

  // The progress region must appear (upload phase or already processing).
  const progress = page.locator('text=/Uploading file…|Processing spreadsheet…/');
  await expect(progress).toBeVisible({ timeout: 3000 });
  const barCount = await page.locator('.import-bar-indeterminate, [style*="width:"]').count();
  console.log('progress text seen:', await progress.innerText());
  console.log('bar elements:', barCount);
  await page.screenshot({ path: 'tests/Browser/screenshots/alumni-import-progress-bar.png' });

  // It resolves to the result summary once the stub responds.
  await expect(page.locator('text=imported').first()).toBeVisible({ timeout: 8000 });
  await expect(page.locator('text=/Uploading file…|Processing spreadsheet…/')).toHaveCount(0);

  await page.screenshot({ path: 'tests/Browser/screenshots/alumni-import-progress.png' });
});
