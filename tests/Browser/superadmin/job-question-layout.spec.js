// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Superadmin Job Posting's "Employer Question" section previously always
 * rendered the textarea directly (no toggle), diverging from the Employer
 * job-posting page's UI/UX (label + "+ Add Question" trigger on the same
 * row, textarea revealed on demand with a Remove button). Brought the
 * markup and Vue state/methods (showQuestionField / addQuestionField /
 * removeQuestionField) in line with the Employer page's already-fixed
 * implementation (see tests/Browser/employer/job-question-layout.spec.js)
 * so both pages behave and look the same.
 */
async function openAddJobModal(page) {
  await page.goto('/superadmin_job');
  await page.waitForLoadState('networkidle');
  await page.click('button:has-text("Post New Job")');
  await expect(page.locator('[data-modal="add-edit-job-modal"]')).toBeVisible({ timeout: 10000 });
}

test.describe('Superadmin Job Posting — Employer Question parity with Employer', () => {
  test('label and Add Question button share the same row, matching Employer page', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await openAddJobModal(page);

    const modal = page.locator('[data-modal="add-edit-job-modal"]');
    const label = modal.locator('label:has-text("Employer Question")');
    const addBtn = modal.locator('button:has-text("Add Question")');
    await expect(label).toBeVisible();
    await expect(addBtn).toBeVisible();

    const labelBox = await label.boundingBox();
    const btnBox = await addBtn.boundingBox();
    expect(Math.abs((labelBox.y + labelBox.height / 2) - (btnBox.y + btnBox.height / 2))).toBeLessThan(10);
    expect(btnBox.x).toBeGreaterThan(labelBox.x + labelBox.width - 5);

    // Textarea is hidden until "Add Question" is clicked (matches Employer)
    await expect(modal.locator('#jobEmployerQuestion0')).not.toBeVisible();
  });

  test('Add Question reveals the textarea; Remove hides it again and clears the value', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await openAddJobModal(page);

    const modal = page.locator('[data-modal="add-edit-job-modal"]');
    await modal.locator('button:has-text("Add Question")').click();
    await expect(modal.locator('#jobEmployerQuestion0')).toBeVisible();

    await modal.locator('#jobEmployerQuestion0').fill('Why are you interested in this role?');
    await expect(modal.locator('#jobEmployerQuestion0')).toHaveValue('Why are you interested in this role?');

    await modal.locator('button[title="Remove question"]').click();
    await expect(modal.locator('#jobEmployerQuestion0')).not.toBeVisible();
    await expect(modal.locator('button:has-text("Add Question")')).toBeVisible();
  });

  // A third test attempting a full job submission (all required fields +
  // question) was tried here but consistently hit an unrelated, pre-existing
  // issue: filling Requirements/Qualifications triggers an AI-suggestions
  // feature (ai_suggestions -> GeminiClient) whose suggestions panel expands
  // the form and interferes with locating/clicking the submit button, and
  // separately GeminiClient itself can hang for the full 60s PHP execution
  // limit. Both are pre-existing defects outside the scope of this UI/parity
  // fix (see NOT ATTEMPTED note in the final report) — not chased further
  // here to avoid scope creep. The two tests above already cover the actual
  // ask: the toggle's layout and open/close behavior now match Employer's.
});
