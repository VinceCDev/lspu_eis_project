// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Employer Question section layout fix — the "Applicant Question (optional)"
 * label and the "+ Add Question" trigger button were stacked (block label,
 * then button below via mt-1). Fixed by wrapping both in a single
 * `flex items-center justify-between` row so they sit on the same horizontal
 * line at desktop/tablet widths, without touching Vue state, colors, or the
 * v-else textarea block.
 *
 * Since then, the feature grew to support any number of questions and the
 * label was renamed "Employer Question" (see multi-question.spec.js) — this
 * file keeps its original job, verifying the row layout across viewports,
 * updated for the new label text and the per-question `#jobEmployerQuestion0`
 * id scheme.
 */
const viewports = [
  { name: 'Desktop 1920x1080', width: 1920, height: 1080 },
  { name: 'Desktop 1366x768', width: 1366, height: 768 },
  { name: 'Tablet 768x1024', width: 768, height: 1024 },
  { name: 'Mobile 390x844', width: 390, height: 844 },
  { name: 'Mobile 412x915', width: 412, height: 915 },
];

async function openAddJobModal(page) {
  await page.goto('/employer_jobposting');
  await page.waitForLoadState('networkidle');
  await page.click('button:has-text("Post New Job")');
  await expect(page.locator('[data-modal="add-edit-job-modal"]')).toBeVisible({ timeout: 10000 });
}

test.describe('Employer Question layout', () => {
  for (const vp of viewports) {
    test(`label and Add Question button share the same row — ${vp.name}`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await loginAs(page, 'employer');
      await openAddJobModal(page);

      const label = page.locator('[data-modal="add-edit-job-modal"] label:has-text("Employer Question")');
      const addBtn = page.locator('[data-modal="add-edit-job-modal"] button:has-text("Add Question")');
      await expect(label).toBeVisible();
      await expect(addBtn).toBeVisible();

      const labelBox = await label.boundingBox();
      const btnBox = await addBtn.boundingBox();
      expect(labelBox).not.toBeNull();
      expect(btnBox).not.toBeNull();

      // Same row: vertical centers must be close together regardless of viewport
      // (both mobile widths keep this row un-stacked per the spec's "MUST be
      // same-row at desktop/tablet widths" — mobile is allowed to stack only
      // if content genuinely can't fit, but at this row's content size it fits).
      const labelMidY = labelBox.y + labelBox.height / 2;
      const btnMidY = btnBox.y + btnBox.height / 2;
      expect(Math.abs(labelMidY - btnMidY)).toBeLessThan(10);

      // Button must be to the right of the label (not wrapped below it)
      expect(btnBox.x).toBeGreaterThan(labelBox.x + labelBox.width - 5);
    });
  }

  test('Add Question still opens the textarea and preserves original behavior', async ({ page }) => {
    await loginAs(page, 'employer');
    await openAddJobModal(page);

    const modal = page.locator('[data-modal="add-edit-job-modal"]');
    await modal.locator('button:has-text("Add Question")').click();

    await expect(modal.locator('#jobEmployerQuestion0')).toBeVisible();
    await modal.locator('#jobEmployerQuestion0').fill('Why are you interested in this role?');
    await expect(modal.locator('#jobEmployerQuestion0')).toHaveValue('Why are you interested in this role?');

    // Remove-question button still functions
    await modal.locator('button[title="Remove question"]').click();
    await expect(modal.locator('#jobEmployerQuestion0')).not.toBeVisible();
    await expect(modal.locator('button:has-text("Add Question")')).toBeVisible();
  });
});
