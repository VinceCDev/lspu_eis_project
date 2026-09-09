// @ts-check
const { test } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

test.setTimeout(120000);

/**
 * Network + main-thread audit of the Super Admin dashboard, to separate real
 * app-code cost from `php artisan serve` artifacts (no gzip / no cache headers).
 */
test('dashboard performance audit — resources, timing, cache headers', async ({ page }) => {
  const res = [];
  page.on('response', async (r) => {
    const req = r.request();
    if (req.resourceType() === 'xhr' || req.resourceType() === 'fetch') return; // handled below
    let len = 0;
    try { len = (await r.body()).length; } catch (e) { /* redirect / no body */ }
    const h = r.headers();
    res.push({
      url: r.url().replace(/^https?:\/\/[^/]+/, ''),
      type: req.resourceType(),
      status: r.status(),
      bytes: len,
      cache: h['cache-control'] || '(none)',
      enc: h['content-encoding'] || '(none)',
    });
  });

  const xhr = [];
  page.on('response', async (r) => {
    const t = r.request().resourceType();
    if (t !== 'xhr' && t !== 'fetch') return;
    const timing = r.request().timing();
    xhr.push({
      url: r.url().replace(/^https?:\/\/[^/]+/, '').slice(0, 80),
      status: r.status(),
      ms: Math.round(timing.responseEnd - timing.requestStart),
    });
  });

  await loginAs(page, 'superadmin');
  await page.waitForLoadState('load');
  await page.waitForTimeout(1000);

  // Reset capture so we measure ONLY the dashboard, not the login page.
  res.length = 0;
  xhr.length = 0;
  const t0 = Date.now();
  await page.reload({ waitUntil: 'load' });
  const loadMs = Date.now() - t0;
  await page.waitForTimeout(9000); // let the sequential loader + charts + map settle

  const perf = await page.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0] || {};
    const paints = {};
    performance.getEntriesByType('paint').forEach((p) => { paints[p.name] = Math.round(p.startTime); });
    const lcp = performance.getEntriesByType('largest-contentful-paint').pop();
    let longTasks = 0, longTaskMs = 0;
    performance.getEntriesByType('longtask').forEach((lt) => { longTasks++; longTaskMs += lt.duration; });
    return {
      domContentLoaded: Math.round(nav.domContentLoadedEventEnd || 0),
      loadEvent: Math.round(nav.loadEventEnd || 0),
      firstContentfulPaint: paints['first-contentful-paint'] || null,
      largestContentfulPaint: lcp ? Math.round(lcp.startTime) : null,
      longTasks, longTaskMs: Math.round(longTaskMs),
    };
  });

  // sort static resources by transfer size
  res.sort((a, b) => b.bytes - a.bytes);
  const total = res.reduce((a, r) => a + r.bytes, 0);
  const chartLoaded = res.some((r) => /chart(\.min)?\.js/.test(r.url));
  const leafletLoaded = res.some((r) => /leaflet\.js/.test(r.url));

  console.log('\n================ DASHBOARD PERF AUDIT ================');
  console.log('login->load wall time:', loadMs, 'ms');
  console.log('perf entries:', JSON.stringify(perf, null, 2));
  console.log('\n-- static resources (top 20 by bytes) --');
  res.slice(0, 20).forEach((r) => console.log(
    `  ${String(r.bytes).padStart(7)}  ${r.status}  enc=${r.enc.padEnd(10)} cc=${r.cache.slice(0, 28).padEnd(28)} ${r.type.padEnd(10)} ${r.url.slice(0, 70)}`
  ));
  console.log('\n  total static bytes:', total, `(${Math.round(total / 1024)} KiB)`);
  console.log('  chart.min.js loaded on initial dashboard:', chartLoaded);
  console.log('  leaflet.js loaded on initial dashboard:', leafletLoaded);
  console.log('\n-- XHR --');
  xhr.forEach((x) => console.log(`  ${String(x.ms).padStart(6)}ms  ${x.status}  ${x.url}`));
  console.log('====================================================\n');

  await page.screenshot({ path: 'tests/Browser/screenshots/diag-perf.png', fullPage: false });
});
