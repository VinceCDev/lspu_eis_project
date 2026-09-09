// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * DIAGNOSIS: "Employment Status per Program" and "Program Work Alignment"
 * are reported as not displaying. Capture the real browser state.
 */
test.describe('Dashboard broken-component diagnosis', () => {
  test('capture console, XHR bodies, and the two component states', async ({ page }) => {
    const consoleMsgs = [];
    const failed = [];
    const xhr = {};

    page.on('console', (m) => consoleMsgs.push(`[${m.type()}] ${m.text()}`));
    page.on('pageerror', (e) => consoleMsgs.push(`[pageerror] ${e.message}\n${e.stack || ''}`));
    page.on('requestfailed', (r) => failed.push(`${r.method()} ${r.url()} :: ${r.failure()?.errorText}`));
    page.on('response', async (res) => {
      const u = res.url();
      if (/action=(stats|courseWorkAlignment|employmentStatusByCampus)|admin_profile\?action=details/.test(u)) {
        let body = '';
        try { body = await res.text(); } catch (e) { body = '<unreadable>'; }
        const key = u.match(/action=([a-zA-Z]+)/)?.[1] || (u.includes('admin_profile') ? 'profile' : u);
        xhr[key] = { status: res.status(), len: body.length, head: body.slice(0, 400) };
      }
      if (res.status() >= 400) failed.push(`HTTP ${res.status()} ${u}`);
    });

    await loginAs(page, 'superadmin');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000); // let the sequential loader + charts settle

    // ---- Program Work Alignment ----
    const alignCanvas = page.locator('#alignmentChart');
    const alignExists = await alignCanvas.count();
    const alignBox = alignExists ? await alignCanvas.boundingBox() : null;
    // Is anything actually painted on it? (sample the canvas for non-transparent pixels)
    const alignPainted = alignExists ? await page.evaluate(() => {
      const c = document.getElementById('alignmentChart');
      if (!c || !c.getContext) return 'no-context';
      try {
        const ctx = c.getContext('2d');
        const d = ctx.getImageData(0, 0, c.width, c.height).data;
        let nonEmpty = 0;
        for (let i = 3; i < d.length; i += 4) if (d[i] !== 0) nonEmpty++;
        return `${nonEmpty} non-transparent px of ${d.length / 4}`;
      } catch (e) { return 'err:' + e.message; }
    }) : 'no-canvas';

    // ---- Employment Status per Program (campus buttons card) ----
    const spinner = page.locator('.fa-spinner.fa-spin');
    const spinnerVisible = await spinner.first().isVisible().catch(() => false);
    const campusButtons = page.locator('button:has(.fa-map-marker-alt)');
    const campusButtonCount = await campusButtons.count();

    console.log('\n================ DIAGNOSIS ================');
    console.log('XHR responses:', JSON.stringify(xhr, null, 2));
    console.log('FAILED requests:', JSON.stringify(failed, null, 2));
    console.log('CONSOLE (all):');
    consoleMsgs.forEach((m) => console.log('  ', m));
    console.log('\n-- Program Work Alignment --');
    console.log('  #alignmentChart exists:', alignExists, ' box:', JSON.stringify(alignBox), ' painted:', alignPainted);
    console.log('\n-- Employment Status per Program --');
    console.log('  spinner visible:', spinnerVisible, ' campus buttons:', campusButtonCount);
    console.log('==========================================\n');

    await page.screenshot({ path: 'tests/Browser/screenshots/diag-dashboard.png', fullPage: true });
  });
});
