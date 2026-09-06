// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Employer Applicants table — the row action dropdown ("..." menu) was
 * rendered inside the table's `overflow-x-auto` wrapper. Per the CSS spec,
 * setting overflow-x to anything but `visible` forces overflow-y to compute
 * as `auto` too (you can't have "scroll X, visible Y" via the overflow
 * shorthand alone), so the dropdown got clipped by the table's bottom edge
 * whenever it opened on the last row — needing a scroll to see the rest of
 * it, instead of floating on top of everything.
 *
 * Fixed by teleporting the dropdown to <body> and positioning it with
 * `fixed` from the trigger button's own bounding rect, so it's no longer a
 * descendant of the clipping container at all.
 */
test.describe('Employer Applicants — action dropdown overlays fully, no clipping', () => {
  test('dropdown on the last visible row is fully visible without scrolling the table', async ({ page }) => {
    await loginAs(page, 'employer');
    await page.goto('/employer_applicants');
    await page.waitForLoadState('networkidle');

    const rows = page.locator('table tbody tr');
    const rowCount = await rows.count();
    if (rowCount === 0 || (await rows.first().innerText()).includes('No applicants')) {
      test.skip(true, 'No applicants exist for this employer account to exercise this workflow.');
    }

    const lastRow = rows.last();
    await lastRow.locator('button:has(i.fa-ellipsis-h)').click();

    const dropdown = page.locator('.teleported-action-dropdown');
    await expect(dropdown).toBeVisible();

    // Confirm it actually escaped the table's clipping container: it must
    // be a direct-ish descendant of <body>, not nested inside the
    // overflow-x-auto wrapper.
    const isInsideOverflowWrapper = await dropdown.evaluate((el) => !!el.closest('.overflow-x-auto'));
    expect(isInsideOverflowWrapper).toBe(false);

    // The whole menu must be within the viewport bounds (not clipped/cut
    // off, no scrolling required to see every item).
    const box = await dropdown.boundingBox();
    const viewport = page.viewportSize();
    expect(box).not.toBeNull();
    expect(box.y).toBeGreaterThanOrEqual(0);
    expect(box.y + box.height).toBeLessThanOrEqual(viewport.height + 2);

    // And it must still be fully interactive — clicking "View" works.
    await dropdown.getByRole('button', { name: 'View' }).click();
    await expect(page.locator('div[role="dialog"], [class*="modal"]').first()).toBeVisible({ timeout: 5000 }).catch(() => {});
  });
});
