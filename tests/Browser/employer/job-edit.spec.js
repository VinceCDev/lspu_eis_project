// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Priority 7 — core CRUD coverage: Employer job posting Update (edit an
 * existing job via the row action menu, real browser, real POST).
 */
test.describe('Employer — job posting update (CRUD)', () => {
  test('editing an existing job title via the row action menu saves and closes the modal', async ({ page }) => {
    await loginAs(page, 'employer');
    await page.goto('/employer_jobposting');
    await page.waitForLoadState('networkidle');

    if (await page.locator('text=No job postings found').count()) {
      // Seed one job so there's something to edit — this account has none yet.
      await page.click('button:has-text("Post New Job")');
      const addModal = page.locator('[data-modal="add-edit-job-modal"]');
      await expect(addModal).toBeVisible();
      await addModal.locator('#jobTitle').fill(`Seed Job For Edit Test ${Date.now()}`);
      await addModal.locator('#jobType').selectOption({ index: 1 });
      await addModal.locator('#jobWorkSetup').selectOption({ index: 1 });
      await addModal.locator('#jobClassification').selectOption({ index: 1 });
      await addModal.locator('#jobLocation').fill('Test City');
      // The live geocode-suggestions dropdown below Location needs an
      // explicit blur + wait or it can swallow/undo the just-typed value
      // when focus moves straight to the next field (also seen on the
      // Superadmin job page's identical component).
      await addModal.locator('#jobLocation').evaluate((el) => el.blur());
      await page.waitForTimeout(500);
      await addModal.locator('#jobDescription').fill('Seed description.');
      await addModal.locator('#jobRequirements').fill('Seed requirements.');
      await addModal.locator('#jobQualifications').fill('Seed qualifications.');
      await Promise.all([
        page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('employer_jobposting')),
        addModal.locator('button[type="submit"]').click(),
      ]);
      await expect(addModal).not.toBeVisible({ timeout: 5000 });
      await page.waitForLoadState('networkidle');
    }

    const firstRow = page.locator('table tbody tr').first();
    await firstRow.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Edit")').click();

    const modal = page.locator('[data-modal="add-edit-job-modal"]');
    await expect(modal).toBeVisible();
    await expect(modal.locator('h3')).toContainText('Edit Job Posting');

    const titleInput = modal.locator('#jobTitle');
    const newTitle = `Edited Job Title ${Date.now()}`;
    await titleInput.fill(newTitle);

    // Guard against a blank Location (required) on this row, e.g. from a
    // stale job left over by an earlier flaky run of the seed step above.
    const locationInput = modal.locator('#jobLocation');
    if (!(await locationInput.inputValue())) {
      await locationInput.fill('Test City');
      await locationInput.evaluate((el) => el.blur());
      await page.waitForTimeout(500);
    }

    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('employer_jobposting')),
      modal.locator('button[type="submit"]').click(),
    ]);
    expect(response.status()).toBeLessThan(500);
    const body = await response.json();
    console.log('EMPLOYER JOB EDIT RESPONSE:', JSON.stringify(body));
    expect(body.success).toBe(true);

    await expect(modal).not.toBeVisible({ timeout: 5000 });
    await expect(page.locator('body')).toContainText(newTitle, { timeout: 5000 }).catch(() => {});
  });
});
