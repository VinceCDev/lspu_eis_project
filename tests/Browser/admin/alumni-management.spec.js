// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Final targeted validation pass — Admin Alumni management workflows
 * (view/edit/delete) that had no dedicated browser coverage yet. Uses:
 *  - the shared browsertest-alumni fixture for View + Edit (edited value
 *    is restored at the end so other suites relying on that fixture's
 *    original contact number aren't affected)
 *  - a disposable, timestamp-tagged alumni account (created through the
 *    app's own admin_user "Add Alumni" flow — the same legitimate-creation
 *    pattern already used by admin/account-creation.spec.js) for Delete,
 *    so no shared fixture data is destroyed.
 */
test.describe('Admin — Alumni management (view/edit/delete)', () => {
  test('View: opens the alumni detail modal with real profile data', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', (msg) => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });

    await loginAs(page, 'admin');
    await page.goto('/admin_alumni');
    await page.waitForLoadState('networkidle');

    // Attached only after the target page has settled — attaching earlier
    // (right after loginAs()) can pick up requests Firefox reports as
    // "failed" simply because this goto() cancelled the login destination
    // page's own in-flight background requests, a navigation artifact, not
    // a real app failure.
    const failedRequests = [];
    page.on('requestfailed', (req) => failedRequests.push(req.url()));

    const row = page.locator('table tbody tr', { hasText: 'Browser Test Alumni' }).first();
    await expect(row).toBeVisible();
    await row.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("View"), div[v-if] a:has-text("View")').first().click();

    const viewModal = page.locator('div[role="dialog"][data-modal="view-modal"], div[data-modal="view-modal"]').first();
    // Fall back to any visible dialog containing the alumni's name if the
    // data-modal attribute isn't present on this view.
    const modal = (await viewModal.count()) ? viewModal : page.locator('div[role="dialog"]:has-text("Browser Test Alumni")').first();
    await expect(modal).toBeVisible({ timeout: 5000 });
    await expect(modal).toContainText('browsertest-alumni@example.test');

    expect(failedRequests, `Unexpected failed requests: ${failedRequests.join(', ')}`).toEqual([]);
    console.log('VIEW ALUMNI console errors:', consoleErrors);
  });

  test('Edit: updates a field via the real form submission and persists after reload', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin_alumni');
    await page.waitForLoadState('networkidle');

    const row = page.locator('table tbody tr', { hasText: 'Browser Test Alumni' }).first();
    await expect(row).toBeVisible();
    await row.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Edit")').first().click();

    const modal = page.locator('div[data-modal="alumni-modal"]');
    await expect(modal).toBeVisible();

    const marker = `QA-${Date.now()}`;
    // Last Name is the 3rd text input in the form (First, Middle, Last).
    const textInputs = modal.locator('input[type="text"]');
    const originalLastName = await textInputs.nth(2).inputValue();
    await textInputs.nth(2).fill(`${originalLastName}-${marker}`);

    // FINDING: opening Edit re-fetches the City/Municipality list for the
    // stored province from the live PSGC API (fetchCities()), and the
    // stored city string isn't guaranteed to exact-match a freshly fetched
    // option — when it doesn't, the required <select> is left blank,
    // failing HTML5 validation and silently blocking every save attempt
    // with no visible app-level error. Re-selecting a valid city here
    // works around it for this test; the underlying fragility is reported
    // as a real gap in Admin Alumni Edit.
    const citySelect = modal.locator('select').nth(4); // Gender, College, Program, Province, City
    await expect(citySelect.locator('option').nth(1)).toBeAttached({ timeout: 10000 });
    await citySelect.selectOption({ index: 1 });

    // updateAlumni() (admin_alumni.js) sends a PUT, not a POST.
    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'PUT' && res.url().includes('admin_alumni')),
      modal.locator('button[type="submit"]').click(),
    ]);
    expect(response.status()).toBeLessThan(500);
    const body = await response.json();
    console.log('EDIT ALUMNI RESPONSE:', JSON.stringify(body));
    expect(body.success).toBe(true);
    await expect(modal).not.toBeVisible({ timeout: 5000 });

    // Reload and confirm the new value actually persisted server-side, not
    // just optimistically rendered.
    await page.reload();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('table tbody tr', { hasText: marker })).toBeVisible({ timeout: 10000 });

    // Restore the original last name so the shared fixture stays clean for
    // every other suite that depends on it.
    const restoredRow = page.locator('table tbody tr', { hasText: marker }).first();
    await restoredRow.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Edit")').first().click();
    const restoreModal = page.locator('div[data-modal="alumni-modal"]');
    await expect(restoreModal).toBeVisible();
    await restoreModal.locator('input[type="text"]').nth(2).fill(originalLastName);
    const restoreCitySelect = restoreModal.locator('select').nth(4);
    await expect(restoreCitySelect.locator('option').nth(1)).toBeAttached({ timeout: 10000 });
    await restoreCitySelect.selectOption({ index: 1 });
    await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'PUT' && res.url().includes('admin_alumni')),
      restoreModal.locator('button[type="submit"]').click(),
    ]);
    await expect(restoreModal).not.toBeVisible({ timeout: 5000 });
  });

  test('Delete: removes a disposable alumni account and it disappears from the list', async ({ page }) => {
    // Create the disposable alumni through admin_alumni's OWN "Add Alumni"
    // flow (AlumniController::create), not admin_user's quick-create —
    // admin_user's alumni path never assigns a campus_id (a real, separate
    // gap noted in the final report), which would make the new row
    // invisible on this campus-scoped page and give a false BLOCKED result.
    await loginAs(page, 'admin');
    await page.goto('/admin_alumni');
    await page.waitForLoadState('networkidle');
    await page.click('button:has-text("Add Alumni")');
    const addModal = page.locator('div[data-modal="alumni-modal"]');
    await expect(addModal).toBeVisible();

    const lastName = `QADel${Date.now()}`;
    await addModal.locator('input[type="text"]').nth(0).fill('Playwright'); // first name
    await addModal.locator('input[type="text"]').nth(2).fill(lastName); // last name
    const email = `browsertest-admin-alumni-del-${Date.now()}@example.test`;
    await addModal.locator('input[type="email"]').first().fill(email);
    // Non-superadmin admin form omits the Campus select (locked to the
    // admin's own campus), so select order is: Gender, College, Program,
    // Province, City, Status.
    const selects = addModal.locator('select');
    await selects.nth(0).selectOption('Male'); // gender
    await addModal.locator('input[type="text"]').nth(3).fill('2024'); // year graduated (4th text input)
    await selects.nth(1).selectOption({ index: 1 }); // college
    await selects.nth(2).selectOption({ index: 1 }); // program/course
    await selects.nth(3).selectOption({ label: 'Laguna' }); // province
    await page.waitForTimeout(1500); // fetchCities() hits the real PSGC API
    await selects.nth(4).selectOption({ index: 1 }); // city/municipality

    const [createResp] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('admin_alumni')),
      addModal.locator('button[type="submit"]').click(),
    ]);
    const createBody = await createResp.json();
    console.log('CREATE DISPOSABLE ALUMNI RESPONSE:', JSON.stringify(createBody));
    expect(createBody.success).toBe(true);
    await expect(addModal).not.toBeVisible({ timeout: 5000 });

    // Now delete it from the same Admin Alumni management page.
    await page.reload();
    await page.waitForLoadState('networkidle');
    const row = page.locator('table tbody tr', { hasText: lastName }).first();
    await expect(row).toBeVisible({ timeout: 10000 });
    await row.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Delete"), div[v-if] a:has-text("Delete")').first().click();

    const confirmModal = page.locator('div[role="dialog"]:has-text("Delete")').last();
    await expect(confirmModal).toBeVisible();
    const [delResponse] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('admin_alumni') && res.request().method() === 'DELETE'),
      confirmModal.locator('button:has-text("Delete")').last().click(),
    ]);
    expect(delResponse.status()).toBeLessThan(500);
    const delBody = await delResponse.json();
    console.log('DELETE ALUMNI RESPONSE:', JSON.stringify(delBody));
    expect(delBody.success).toBe(true);

    await page.reload();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('table tbody tr', { hasText: lastName })).toHaveCount(0);
  });
});
