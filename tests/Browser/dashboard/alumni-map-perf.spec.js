// @ts-check
const { test } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

test.setTimeout(180000);

/**
 * MEASUREMENT (not an assertion suite): times the Alumni Location map on the
 * admin dashboard in a real browser so before/after changes can be compared.
 *
 *   npx playwright test tests/Browser/dashboard/alumni-map-perf.spec.js --project=chromium
 *
 * Numbers depend on the machine, the data set and `php artisan serve` (single
 * threaded, no gzip) — compare runs on the same setup, never against another's.
 */
test('alumni map — time to first pin, request timings, marker counts', async ({ page }) => {
  const xhr = [];
  page.on('response', async (r) => {
    const t = r.request().resourceType();
    if (t !== 'xhr' && t !== 'fetch') return;
    let bytes = 0;
    try { bytes = (await r.body()).length; } catch (e) { /* aborted */ }
    const timing = r.request().timing();
    xhr.push({
      url: r.url().replace(/^https?:\/\/[^/]+/, '').slice(0, 70),
      status: r.status(),
      ms: Math.round(timing.responseEnd - timing.requestStart),
      kb: +(bytes / 1024).toFixed(1),
    });
  });

  await loginAs(page, 'superadmin');
  await page.waitForLoadState('load');
  await page.waitForTimeout(500);
  xhr.length = 0;

  // Sample the page from inside: when did each dashboard element first appear?
  await page.addInitScript(() => {
    window.__mapPerf = { firstPin: null, lastPinChange: 0, pinCounts: [], firstChart: null };
    const tick = () => {
      const now = performance.now();
      const pins = document.querySelectorAll('#alumniMap .leaflet-marker-icon').length;
      const g = window.__mapPerf;
      if (pins > 0 && g.firstPin === null) g.firstPin = Math.round(now);
      if (pins !== (g.pinCounts[g.pinCounts.length - 1] || [0, 0])[1]) g.pinCounts.push([Math.round(now), pins]);
      if (document.querySelector('canvas') && g.firstChart === null) g.firstChart = Math.round(now);
      requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  });

  await page.reload({ waitUntil: 'load' });
  // wait until pins stop changing for 4s, or 60s cap
  await page.waitForFunction(() => {
    const g = window.__mapPerf;
    if (!g || g.firstPin === null) return false;
    const last = g.pinCounts[g.pinCounts.length - 1];
    return performance.now() - last[0] > 4000;
  }, null, { timeout: 90000, polling: 500 }).catch(() => {});

  const m = await page.evaluate(() => {
    const g = window.__mapPerf;
    const icons = [...document.querySelectorAll('#alumniMap .leaflet-marker-icon')];
    const positions = new Map();
    icons.forEach((el) => {
      const k = (el.style.transform || '') + '|' + (el.style.left || '') + '|' + (el.style.top || '');
      positions.set(k, (positions.get(k) || 0) + 1);
    });
    const maxStack = Math.max(0, ...positions.values());
    const jsHeap = performance.memory ? Math.round(performance.memory.usedJSHeapSize / 1048576) : null;
    return {
      firstChartMs: g.firstChart,
      firstPinMs: g.firstPin,
      pinCountTimeline: g.pinCounts.slice(0, 12),
      finalPinsInDom: icons.length,
      distinctScreenPositions: positions.size,
      maxPinsStackedOnOneSpot: maxStack,
      jsHeapMB: jsHeap,
    };
  });

  console.log('\n=== ALUMNI MAP PERF ===');
  console.log(JSON.stringify(m, null, 2));
  console.log('--- XHR (ms = request start -> response end) ---');
  xhr.forEach((x) => console.log(`${String(x.ms).padStart(6)} ms  ${String(x.kb).padStart(8)} KB  ${x.status}  ${x.url}`));
});
