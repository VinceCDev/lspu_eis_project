// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

test.describe('Admin dashboard — real browser investigation', () => {
  test('dashboard loads, cards and charts render, and console/network are captured', async ({ page }) => {
    const consoleErrors = [];
    const failedRequests = [];
    page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
    page.on('pageerror', (err) => consoleErrors.push('PAGE ERROR: ' + err.message));
    page.on('requestfailed', (req) => failedRequests.push(`${req.method()} ${req.url()} — ${req.failure()?.errorText}`));
    page.on('response', (res) => { if (res.status() >= 400) failedRequests.push(`${res.status()} ${res.url()}`); });

    await loginAs(page, 'admin');
    await page.waitForLoadState('networkidle');

    // Cards/charts render (canvas elements exist and have non-zero size —
    // an actual rendered Chart.js canvas, not just a placeholder div)
    const canvases = page.locator('canvas');
    const canvasCount = await canvases.count();
    console.log('CANVAS COUNT:', canvasCount);
    if (canvasCount > 0) {
      const box = await canvases.first().boundingBox();
      console.log('FIRST CANVAS BOUNDING BOX:', JSON.stringify(box));
    }

    console.log('CONSOLE ERRORS:', JSON.stringify(consoleErrors));
    console.log('FAILED REQUESTS:', JSON.stringify(failedRequests));

    await page.screenshot({ path: 'tests/Browser/screenshots/admin-dashboard.png', fullPage: true });

    expect(canvasCount).toBeGreaterThan(0);
  });

  test('DRILLDOWN INVESTIGATION: clicking a chart segment — does anything visible happen?', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });
    page.on('pageerror', (err) => consoleErrors.push('PAGE ERROR: ' + err.message));

    await loginAs(page, 'admin');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000); // chart animation/data fetch settle

    // Confirmed via source inspection in the prior remediation pass:
    // showDrilldown() sets drilldown.active = true and computes
    // drilldown.data, but the Blade template has no element bound to
    // drilldown.active and no #drilldownChart canvas. Verify that against
    // the REAL rendered DOM now, not just the source.
    const drilldownBoundElements = await page.locator('[class*="drilldown"], #drilldownChart').count();
    console.log('ELEMENTS REFERENCING drilldown IN THE RENDERED DOM:', drilldownBoundElements);

    // Try to actually trigger it: click on one of the college/location/
    // sector chart canvases (whichever exist) and see if any new element
    // appears afterward.
    const beforeElementCount = await page.locator('body *').count();
    const canvases = page.locator('canvas');
    const canvasCount = await canvases.count();
    if (canvasCount > 0) {
      const box = await canvases.first().boundingBox();
      if (box) {
        // Click near the chart's data area, not just its corner
        await page.mouse.click(box.x + box.width / 2, box.y + box.height / 2);
        await page.waitForTimeout(500);
      }
    }
    const afterElementCount = await page.locator('body *').count();
    const afterDrilldownElements = await page.locator('#drilldownChart').count();

    console.log('DOM ELEMENT COUNT BEFORE CLICK:', beforeElementCount);
    console.log('DOM ELEMENT COUNT AFTER CLICK:', afterElementCount);
    console.log('#drilldownChart PRESENT AFTER CLICK:', afterDrilldownElements);
    console.log('CONSOLE ERRORS AFTER CLICK:', JSON.stringify(consoleErrors));

    await page.screenshot({ path: 'tests/Browser/screenshots/admin-dashboard-after-chart-click.png', fullPage: true });

    // The finding: no #drilldownChart exists to show anything, and no JS
    // error is thrown either (renderDrilldownChart() guards on the canvas
    // existing) — confirms "unfinished, not broken" from the prior pass
    // with real DOM evidence instead of source-only inspection.
    expect(afterDrilldownElements).toBe(0);
    expect(consoleErrors.filter((e) => /drilldown/i.test(e)).length).toBe(0);
  });
});
