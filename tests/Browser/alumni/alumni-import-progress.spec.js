// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');
const { loginAs } = require('../fixtures/helpers');

test.setTimeout(90000);

/**
 * "Import from Employment Report" is a QUEUED import: the upload only stores the file and returns an import id; the page then polls
 * ?action=importStatus and shows Queued -> Importing (n / total, %) -> Completed (summary). Real 25-row import, rows tagged
 * @impstream.test.
 *
 * Needs the 2026_09_22 migration applied (`php artisan migrate`). With QUEUE_CONNECTION=sync (the dev default) the job runs inside the
 * upload request, so the run is already complete when the first status arrives and no intermediate % is observable; with a real
 * queue worker running (`php artisan queue:work database_long --queue=imports`) the % moves and the last assertion also applies.
 */
test('queued import shows its progress and finishes with the summary', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });

  // capture the % label whenever Vue updates it
  const pcts = [];
  await page.exposeFunction('__pct', (v) => pcts.push(v));

  await loginAs(page, 'superadmin');
  await page.goto('/superadmin_alumni');
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(1500);

  await page.locator('button:has-text("Import")').first().click();
  const modal = page.locator('div[role="dialog"]:has-text("Import from Employment Report")');
  await expect(modal).toBeVisible();
  await modal.locator('select').selectOption({ index: 1 });
  await modal.locator('input[type="number"]').fill('2023');
  await modal.locator('input[type="file"]').setInputFiles(path.resolve(__dirname, '../fixtures/import-stream-25.csv'));

  // sample the visible % text every 150ms in the page itself
  await page.evaluate(() => {
    window.__pi = setInterval(() => {
      const el = [...document.querySelectorAll('[role="dialog"] span')].find((s) => /^\d+%$/.test(s.textContent.trim()));
      if (el) window.__pct(parseInt(el.textContent));
    }, 150);
  });

  await modal.getByRole('button', { name: /Upload & import/ }).click();
  await expect(modal.locator('text=imported').first()).toBeVisible({ timeout: 60000 });
  await page.evaluate(() => clearInterval(window.__pi));

  const distinct = [...new Set(pcts)].sort((a, b) => a - b);
  const summary = (await modal.innerText()).replace(/\s+/g, ' ');
  console.log('% values observed:', distinct.join(', '));
  console.log('result:', summary.slice(0, 140));

  expect(errors).toEqual([]);
  expect(summary).toMatch(/25\s+imported/);
  if (process.env.IMPORT_QUEUE_WORKER === '1') {
    expect(distinct.some((v) => v > 0 && v < 100)).toBe(true); // a real intermediate % (only observable when a worker does the import)
  }
});
