// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

test.setTimeout(120000);

/**
 * "Employment Status per Program" — open EVERY campus modal and report each
 * chart's per-status totals. Real-data campus (Sta. Cruz / campus 4) must show
 * non-zero bars for Probational / Contractual / Regular / Self-employed /
 * Employed (Other), not just Unemployed.
 */
test('Employment Status per Program modal — every campus, real bars?', async ({ page }) => {
  const errs = [];
  page.on('pageerror', (e) => errs.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errs.push(m.text()); });

  await loginAs(page, 'superadmin');
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2500);

  const buttons = page.locator('button:has(.fa-map-marker-alt)');
  const n = await buttons.count();
  console.log('\n===== campus buttons:', n, '=====');

  // At least one campus modal must show a non-Unemployed bar with real data —
  // proves the employment_status canonicalisation fold reaches the chart.
  let anyEmployedBar = false;

  for (let i = 0; i < n; i++) {
    const label = (await buttons.nth(i).innerText()).replace(/\s+/g, ' ').trim();
    await buttons.nth(i).click();
    await page.waitForTimeout(1200);

    const data = await page.evaluate(() => {
      const cv = document.querySelector('canvas[id^="employmentStatusCampusChart_"]');
      if (!cv) return { error: 'no modal canvas' };
      const chart = (window.Chart && window.Chart.getChart) ? window.Chart.getChart(cv) : null;
      if (!chart) return { error: 'no Chart instance' };
      const ds = chart.data.datasets.map((d) => ({
        label: d.label,
        sum: (d.data || []).reduce((a, b) => a + (b || 0), 0),
      }));
      return { programCount: chart.data.labels.length, datasets: ds };
    });
    console.log(`\n-- ${label} --`);
    console.log(JSON.stringify(data));
    if (data.datasets) {
      const employed = data.datasets.filter((d) => d.label !== 'Unemployed').reduce((a, d) => a + d.sum, 0);
      if (employed > 0) anyEmployedBar = true;
    }

    // close the modal by clicking its backdrop overlay
    await page.locator('.fixed.inset-0.bg-black.bg-opacity-50').click({ position: { x: 5, y: 5 } }).catch(() => {});
    await page.waitForTimeout(500);
  }

  console.log('\npage errors:', JSON.stringify(errs));
  await page.screenshot({ path: 'tests/Browser/screenshots/diag-modal.png', fullPage: true });

  expect(errs, 'no console/page errors').toEqual([]);
  expect(anyEmployedBar, 'at least one campus modal shows a non-Unemployed bar').toBe(true);
});
