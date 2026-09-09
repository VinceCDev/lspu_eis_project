// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');
const { loginAs } = require('../fixtures/helpers');

test.setTimeout(90000);

/**
 * "Import from Employment Report" streams newline-delimited JSON
 * ({phase,done,total} ticks, then a final {success,message,summary}) so the
 * % moves in real time on one connection. Real 25-row import, rows tagged
 * @impstream.test.
 *
 * (The progressive flush itself is covered by a curl check in the PR notes;
 * here we assert the browser wires it up: a non-zero % is shown and the run
 * completes with the streamed summary.)
 */
test('streamed import shows a moving % and finishes with the summary', async ({ page }) => {
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

  await modal.getByRole('button', { name: 'Import', exact: true }).click();
  await expect(modal.locator('text=imported').first()).toBeVisible({ timeout: 40000 });
  await page.evaluate(() => clearInterval(window.__pi));

  const distinct = [...new Set(pcts)].sort((a, b) => a - b);
  const summary = (await modal.innerText()).replace(/\s+/g, ' ');
  console.log('% values observed:', distinct.join(', '));
  console.log('result:', summary.slice(0, 140));

  expect(errors).toEqual([]);
  expect(summary).toMatch(/25\s+imported/);
  expect(distinct.some((v) => v > 0 && v < 100)).toBe(true); // a real intermediate %
});
