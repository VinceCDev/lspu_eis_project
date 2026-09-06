// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Employer Question feature — upgraded from a single question per job to
 * any number of questions, each with its own required flag, and the label
 * renamed from "Applicant Question" to "Employer Question" throughout
 * (create/edit form, view-job modal, alumni apply flow, applicant Q&A
 * views). Backed by new job_questions / application_answers tables
 * (migration 2026_09_06_140000).
 */
test.describe('Employer — multiple Employer Questions', () => {
  test('label reads "Employer Question", and multiple questions can be added, saved, and re-opened', async ({ page }) => {
    // Typing into Requirements/Qualifications triggers an unrelated AI-
    // suggestions feature whose dropdown can overlap other buttons in this
    // form; block it at the network level since this test isn't exercising
    // that feature (see the same note in superadmin/job-question-layout.spec.js).
    await page.route('**/ai_suggestions*', (route) => route.abort());

    await loginAs(page, 'employer');
    await page.goto('/employer_jobposting');
    await page.waitForLoadState('networkidle');

    await page.click('button:has-text("Post New Job")');
    const modal = page.locator('[data-modal="add-edit-job-modal"]');
    await expect(modal).toBeVisible();

    await expect(modal.locator('label:has-text("Employer Question")')).toBeVisible();
    await expect(modal.locator('label:has-text("Applicant Question")')).toHaveCount(0);

    // Add three questions
    await modal.locator('button:has-text("Add Question")').click();
    await modal.locator('button:has-text("Add Question")').click();
    await modal.locator('button:has-text("Add Question")').click();
    const textareas = modal.locator('textarea[id^="jobEmployerQuestion"]');
    await expect(textareas).toHaveCount(3);

    await textareas.nth(0).fill('Why are you interested in this role?');
    await textareas.nth(1).fill('What is your expected salary?');
    await textareas.nth(2).fill('When can you start?');
    // Mark the first question as required
    await modal.locator('input[type="checkbox"]').nth(0).check();

    // Fill the rest of the required job fields
    const suffix = Date.now();
    await modal.locator('#jobTitle').fill(`Multi-Question Job ${suffix}`);
    await modal.locator('#jobType').selectOption({ index: 1 });
    await modal.locator('#jobWorkSetup').selectOption({ index: 1 });
    await modal.locator('#jobClassification').selectOption({ index: 1 });
    await modal.locator('#jobLocation').fill('Test City');
    await modal.locator('#jobLocation').evaluate((el) => el.blur());
    await page.waitForTimeout(500);
    await modal.locator('#jobDescription').fill('Test description.');
    await modal.locator('#jobRequirements').fill('Test requirements.');
    await modal.locator('#jobQualifications').fill('Test qualifications.');

    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('employer_jobposting')),
      modal.locator('button[type="submit"]').click(),
    ]);
    expect(response.status()).toBeLessThan(500);
    const body = await response.json();
    expect(body.success).toBe(true);
    await expect(modal).not.toBeVisible({ timeout: 5000 });

    // Re-open the same job for editing and confirm all 3 questions persisted
    await page.waitForLoadState('networkidle');
    const row = page.locator('table tbody tr', { hasText: `Multi-Question Job ${suffix}` }).first();
    await row.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Edit")').click();

    const editModal = page.locator('[data-modal="add-edit-job-modal"]');
    await expect(editModal).toBeVisible();
    const editTextareas = editModal.locator('textarea[id^="jobEmployerQuestion"]');
    await expect(editTextareas).toHaveCount(3);
    await expect(editTextareas.nth(0)).toHaveValue('Why are you interested in this role?');
    await expect(editTextareas.nth(1)).toHaveValue('What is your expected salary?');
    await expect(editTextareas.nth(2)).toHaveValue('When can you start?');
    await expect(editModal.locator('input[type="checkbox"]').nth(0)).toBeChecked();

    // Remove the middle question and save. An unrelated AI-suggestions
    // dropdown (from Requirements/Qualifications) renders its own local
    // fallback suggestions on focus regardless of network access, and can
    // visually overlap this button — {force:true} still clicks whatever's
    // topmost at those screen coordinates (real browser hit-testing), so
    // invoke the button's own click handler directly instead.
    await editModal.locator('button[title="Remove question"]').nth(1).evaluate((el) => el.click());
    await expect(editModal.locator('textarea[id^="jobEmployerQuestion"]')).toHaveCount(2);

    const [editResponse] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('employer_jobposting')),
      editModal.locator('button[type="submit"]').click(),
    ]);
    expect(editResponse.status()).toBeLessThan(500);
    const editBody = await editResponse.json();
    expect(editBody.success).toBe(true);

    // Re-open once more to confirm the removal persisted
    await page.waitForLoadState('networkidle');
    const row2 = page.locator('table tbody tr', { hasText: `Multi-Question Job ${suffix}` }).first();
    await row2.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Edit")').click();
    const editModal2 = page.locator('[data-modal="add-edit-job-modal"]');
    await expect(editModal2).toBeVisible();
    const finalTextareas = editModal2.locator('textarea[id^="jobEmployerQuestion"]');
    await expect(finalTextareas).toHaveCount(2);
    await expect(finalTextareas.nth(0)).toHaveValue('Why are you interested in this role?');
    await expect(finalTextareas.nth(1)).toHaveValue('When can you start?');
  });
});
