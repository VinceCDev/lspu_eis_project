// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

test.setTimeout(120000);
// `php artisan serve` is single-threaded and shared with everything else running on the machine: allow slow responses.
const slowExpect = expect.configure({ timeout: 15000 });

/**
 * Picks a mapped location satisfying `want` whose pin does not share a spot with any other location (several locations
 * can geocode to the same coordinate and markercluster then groups them until the max zoom, so they have no
 * standalone icon to click).
 */
async function pickIsolatedLocation(page, wantSrc) {
  return page.evaluate(async (src) => {
    const want = new Function('l', `return (${src})(l)`);
    const d = await (await fetch('/admin_dashboard?action=alumniMap')).json();
    const near = (a, b) => Math.abs(a.lat - b.lat) < 0.002 && Math.abs(a.lng - b.lng) < 0.002;
    return d.locations.find((l) => want(l) && d.locations.filter((o) => near(o, l)).length === 1) || null;
  }, wantSrc.toString());
}

/**
 * Dashboard > Alumni Location map: "View alumni" opens ONE alumnus at a time with Next / Previous / Back to Map,
 * without re-creating the map and without shipping the whole location.
 */
test.describe('alumni map — single-alumnus viewer', () => {
  test('Next / Previous / boundaries / Back to Map keep the map state', async ({ page }) => {
    const viewerRequests = [];
    const legacyListRequests = [];
    page.on('request', (r) => {
      if (r.url().includes('action=alumniViewer')) viewerRequests.push(r.url());
      if (r.url().includes('action=alumniAtLocation')) legacyListRequests.push(r.url());
    });

    await loginAs(page, 'superadmin');
    await page.waitForFunction(() => document.querySelector('#app')?._vnode?.component?.proxy?._alumniMap, null, { timeout: 30000 });

    // pick a small mapped location (3..8 alumni) from the same API the map uses
    const loc = await pickIsolatedLocation(page, (l) => l.count >= 3 && l.count <= 8);
    test.skip(!loc, 'needs an isolated geocoded location with 3-8 alumni');

    // zoom the real map onto it so the pin is a single marker, then open its popup
    await page.evaluate(({ lat, lng }) => {
      const map = document.querySelector('#app')._vnode.component.proxy._alumniMap.map;
      map.setView([lat, lng], 16, { animate: false });
    }, loc);
    const pin = page.locator(`#alumniMap .leaflet-marker-icon[title="${loc.city}, ${loc.province}"]`);
    await slowExpect(pin).toBeVisible({ timeout: 15000 });
    await pin.click();

    const viewBtn = page.locator('.amp-view');
    await slowExpect(viewBtn).toContainText(`View alumni (${loc.count})`);

    // opening a popup makes Leaflet pan the map to fit it - wait until that animation is over before recording the view
    const readView = () => page.evaluate(() => {
      const map = document.querySelector('#app')._vnode.component.proxy._alumniMap.map;
      const c = map.getCenter();
      return { lat: +c.lat.toFixed(5), lng: +c.lng.toFixed(5), zoom: map.getZoom() };
    });
    let before = await readView();
    for (let i = 0; i < 20; i++) {
      await page.waitForTimeout(200);
      const now = await readView();
      if (JSON.stringify(now) === JSON.stringify(before)) break;
      before = now;
    }

    // ---- open: Alumni 1 of N, Previous disabled, exactly one alumnus rendered
    const t0 = Date.now();
    await viewBtn.click();
    const pos = page.locator('.amv-pos');
    await slowExpect(pos).toHaveText(`Alumni 1 of ${loc.count}`);
    await slowExpect(page.locator('.amv-name')).toHaveCount(1);
    console.log(`View alumni -> first alumnus visible: ${Date.now() - t0} ms`);
    const prev = page.getByRole('button', { name: /Previous/ });
    const next = page.getByRole('button', { name: /Next/ });
    await slowExpect(prev).toBeDisabled();
    await slowExpect(next).toBeEnabled();
    const name1 = await page.locator('.amv-name').innerText();

    // ---- Next
    const t1 = Date.now();
    await next.click();
    await slowExpect(pos).toHaveText(`Alumni 2 of ${loc.count}`);
    console.log(`Next -> visible: ${Date.now() - t1} ms`);
    const name2 = await page.locator('.amv-name').innerText();
    expect(name2).not.toBe('');
    await slowExpect(prev).toBeEnabled();

    // ---- Previous returns to the same alumnus (stable order), keyboard works too
    await prev.click();
    await slowExpect(pos).toHaveText(`Alumni 1 of ${loc.count}`);
    expect(await page.locator('.amv-name').innerText()).toBe(name1);
    await page.keyboard.press('ArrowRight');
    await slowExpect(pos).toHaveText(`Alumni 2 of ${loc.count}`);
    expect(await page.locator('.amv-name').innerText()).toBe(name2);

    // ---- walk to the last record: Next disabled there, never "N+1 of N"
    for (let i = 3; i <= loc.count; i++) {
      await next.click();
      await slowExpect(pos).toHaveText(`Alumni ${i} of ${loc.count}`);
    }
    await slowExpect(next).toBeDisabled();
    await slowExpect(prev).toBeEnabled();

    // ---- Back to Map: viewer gone, map untouched (same centre + zoom, popup pin still there)
    await page.getByRole('button', { name: /Back to Map/ }).click();
    await slowExpect(page.locator('.amv-panel')).toHaveCount(0);
    expect(await readView()).toEqual(before);

    // one request per alumnus (+ at most one prefetch beyond the visited ones), never a whole-location list
    expect(legacyListRequests).toHaveLength(0);
    expect(viewerRequests.length).toBeLessThanOrEqual(loc.count + 1);
    console.log(`alumniViewer requests for ${loc.count} alumni: ${viewerRequests.length}`);
  });

  test('a program line in the popup narrows the viewer to that program', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await page.waitForFunction(() => document.querySelector('#app')?._vnode?.component?.proxy?._alumniMap, null, { timeout: 30000 });
    const loc = await pickIsolatedLocation(page, (l) => (l.top_courses || []).length && l.count > l.top_courses[0].count && l.count <= 40);
    test.skip(!loc, 'needs an isolated location with more than one program');
    const course = loc.top_courses[0];

    await page.evaluate(({ lat, lng }) => {
      document.querySelector('#app')._vnode.component.proxy._alumniMap.map.setView([lat, lng], 16, { animate: false });
    }, loc);
    await page.locator(`#alumniMap .leaflet-marker-icon[title="${loc.city}, ${loc.province}"]`).click({ timeout: 15000 });
    await page.locator('.amp-course', { hasText: course.course }).first().click();
    await slowExpect(page.locator('.amv-pos')).toHaveText(`Alumni 1 of ${course.count}`);
    await slowExpect(page.locator('.amv-where')).toContainText(course.course);
    // every alumnus shown belongs to that program
    await slowExpect(page.locator('.amv-sub')).toContainText(course.course);
  });
});
