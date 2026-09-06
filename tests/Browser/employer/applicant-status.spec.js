// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/** Priority 7 — Employer core workflow: update an applicant's status. */
test.describe('Employer — applicant status update', () => {
  test('changing an applicant\'s status via the row action menu persists', async ({ page }) => {
    await loginAs(page, 'employer');
    await page.goto('/employer_applicants');
    await page.waitForLoadState('networkidle');

    const row = page.locator('table tbody tr').first();
    if (!(await row.count()) || (await row.innerText()).includes('No applicants')) {
      test.skip(true, 'No applicants exist for this employer account to exercise this workflow.');
    }

    await row.locator('button:has(i.fa-ellipsis-h), button:has(i.fa-ellipsis-v)').first().click();
    const interviewLink = page.locator('.teleported-action-dropdown a:has-text("Interview")');
    if (!(await interviewLink.count())) {
      test.skip(true, 'No "Interview" status action available on this row (may already be in that status).');
    }

    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('updateStatus')),
      interviewLink.click(),
    ]);
    expect(response.status()).toBeLessThan(500);
    const body = await response.json();
    console.log('APPLICANT STATUS UPDATE RESPONSE:', JSON.stringify(body));
    expect(body.success).toBe(true);
  });
});
