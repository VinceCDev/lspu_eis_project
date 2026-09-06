// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/** Priority 7 — Employer core CRUD: job status change and job delete. */
test.describe('Employer — job status change and delete (CRUD)', () => {
  async function ensureAtLeastOneJob(page) {
    await page.goto('/employer_jobposting');
    await page.waitForLoadState('networkidle');
    if (await page.locator('text=No job postings found').count()) {
      await page.click('button:has-text("Post New Job")');
      const modal = page.locator('[data-modal="add-edit-job-modal"]');
      await expect(modal).toBeVisible();
      await modal.locator('#jobTitle').fill(`Lifecycle Test Job ${Date.now()}`);
      await modal.locator('#jobType').selectOption({ index: 1 });
      await modal.locator('#jobWorkSetup').selectOption({ index: 1 });
      await modal.locator('#jobClassification').selectOption({ index: 1 });
      await modal.locator('#jobLocation').fill('Test City');
      await modal.locator('#jobLocation').evaluate((el) => el.blur());
      await page.waitForTimeout(500);
      await modal.locator('#jobDescription').fill('Seed description.');
      await modal.locator('#jobRequirements').fill('Seed requirements.');
      await modal.locator('#jobQualifications').fill('Seed qualifications.');
      await Promise.all([
        page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('employer_jobposting')),
        modal.locator('button[type="submit"]').click(),
      ]);
      await expect(modal).not.toBeVisible({ timeout: 5000 });
      await page.waitForLoadState('networkidle');
    }
  }

  test('changing a job\'s status via Edit persists (Active -> Closed)', async ({ page }) => {
    await loginAs(page, 'employer');
    await ensureAtLeastOneJob(page);

    const firstRow = page.locator('table tbody tr').first();
    await firstRow.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Edit")').click();

    const modal = page.locator('[data-modal="add-edit-job-modal"]');
    await expect(modal).toBeVisible();
    await modal.locator('#jobStatus').selectOption('Closed');

    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('employer_jobposting')),
      modal.locator('button[type="submit"]').click(),
    ]);
    expect(response.status()).toBeLessThan(500);
    const body = await response.json();
    expect(body.success).toBe(true);
    await expect(modal).not.toBeVisible({ timeout: 5000 });

    // Re-open and confirm the status stuck
    await page.waitForLoadState('networkidle');
    const row = page.locator('table tbody tr').first();
    await row.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Edit")').click();
    const editModal = page.locator('[data-modal="add-edit-job-modal"]');
    await expect(editModal).toBeVisible();
    await expect(editModal.locator('#jobStatus')).toHaveValue('Closed');
    await editModal.locator('button:has-text("Cancel")').click();
  });

  test('deleting a job removes it from the list', async ({ page }) => {
    await loginAs(page, 'employer');
    await page.goto('/employer_jobposting');
    await page.waitForLoadState('networkidle');

    // Always seed a fresh, uniquely-titled job for this test so deletion is
    // unambiguous regardless of what other tests left behind.
    const title = `Delete Test Job ${Date.now()}`;
    await page.click('button:has-text("Post New Job")');
    const addModal = page.locator('[data-modal="add-edit-job-modal"]');
    await expect(addModal).toBeVisible();
    await addModal.locator('#jobTitle').fill(title);
    await addModal.locator('#jobType').selectOption({ index: 1 });
    await addModal.locator('#jobWorkSetup').selectOption({ index: 1 });
    await addModal.locator('#jobClassification').selectOption({ index: 1 });
    await addModal.locator('#jobLocation').fill('Test City');
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

    const row = page.locator('table tbody tr', { hasText: title }).first();
    await expect(row).toBeVisible();
    await row.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Delete")').click();

    const deleteModal = page.locator('div[role="dialog"]:has-text("Delete")').last();
    await expect(deleteModal).toBeVisible();
    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'DELETE' && res.url().includes('employer_jobposting')),
      deleteModal.locator('button:has-text("Delete")').click(),
    ]);
    expect(response.status()).toBeLessThan(500);
    await expect(deleteModal).not.toBeVisible({ timeout: 5000 });
    await expect(page.locator('table tbody tr', { hasText: title })).toHaveCount(0);
  });
});
