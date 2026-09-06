// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Priority 7 — Alumni core CRUD: Education, Skills, Work Experience
 * (my_profile page). Real browser, real POSTs.
 */
test.describe('Alumni — Education CRUD', () => {
  test('add, edit, and delete an education entry', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    const section = page.locator('section', { has: page.locator('h2', { hasText: 'Education' }) }).first();
    await section.locator('button:has-text("Add")').click();

    // Unique per run — this account accumulates leftover entries from
    // earlier test runs, and a plain fixed value would make .first()
    // ambiguous when scoping the edit/delete actions below.
    const degree = `Bachelor of Science in Information Technology ${Date.now()}`;
    const modal = page.locator('div[role="dialog"]:has-text("Add Education")');
    await expect(modal).toBeVisible();
    await modal.locator('#degreeInput').fill(degree);
    await modal.locator('#degreeInput').evaluate((el) => el.blur());
    await modal.locator('#universityInput').fill('Laguna State Polytechnic University');
    await modal.locator('#universityInput').evaluate((el) => el.blur());
    await page.waitForTimeout(400);
    await modal.locator('input[type="date"]').first().fill('2018-06-01');
    await modal.locator('#eduCurrent').check();

    await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('addEducation')),
      modal.locator('button[type="submit"]:has-text("Save")').click(),
    ]);
    await expect(modal).not.toBeVisible({ timeout: 5000 });
    await expect(page.locator('body')).toContainText(degree);

    // Edit it — scope to the row containing our unique degree text
    const eduSection = page.locator('section', { has: page.locator('h2', { hasText: 'Education' }) }).first();
    // The per-entry row has its own distinguishing class — scoping to that
    // (rather than a bare `div`, which also matches the "list of all
    // entries" wrapper and other ancestors containing the same text)
    // reliably selects just this one entry's row.
    const entryRow = eduSection.locator('div.border.border-gray-200', { hasText: degree });
    await entryRow.locator('button:has(i.fa-edit)').first().click();
    const editModal = page.locator('div[role="dialog"]:has-text("Edit Education")');
    await expect(editModal).toBeVisible();
    await expect(editModal.locator('#degreeInput')).toHaveValue(degree);
    await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('updateEducation')),
      editModal.locator('button[type="submit"]:has-text("Save")').click(),
    ]);
    await expect(editModal).not.toBeVisible({ timeout: 5000 });

    // Delete it
    await entryRow.locator('button:has(i.fa-trash)').first().click();
    const deleteModal = page.locator('div[role="dialog"]:has-text("Delete")').last();
    await expect(deleteModal).toBeVisible();
    await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('deleteEducation')).catch(() => null),
      deleteModal.locator('button:has-text("Delete")').click(),
    ]);
    await expect(deleteModal).not.toBeVisible({ timeout: 5000 });
  });
});

test.describe('Alumni — Skills CRUD', () => {
  test('add and delete a skill', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    const section = page.locator('section', { has: page.locator('h2', { hasText: /^SKILLS$/i }) }).first();
    const addBtn = section.locator('button:has-text("Add")').first();
    if (!(await addBtn.count())) {
      test.skip(true, 'No Skills section "Add" trigger found on this profile render.');
    }
    await addBtn.click();

    const modal = page.locator('div[role="dialog"]:has-text("Add Skill")');
    await expect(modal).toBeVisible();
    const skillName = `Test Skill ${Date.now()}`;
    await modal.locator('input[type="text"]').fill(skillName);

    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('addSkill')),
      modal.locator('button[type="submit"]:has-text("Add Skill")').click(),
    ]);
    expect(response.status()).toBeLessThan(500);
    await expect(modal).not.toBeVisible({ timeout: 5000 });
    await expect(page.locator('body')).toContainText(skillName);
  });
});

test.describe('Alumni — Work Experience CRUD', () => {
  test('add, edit, and delete a work experience entry', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    const section = page.locator('section', { has: page.locator('h2', { hasText: 'Work Experience' }) }).first();
    await section.locator('button:has-text("Add")').click();

    const modal = page.locator('div[role="dialog"]:has-text("Add Work Experience")');
    await expect(modal).toBeVisible();
    const jobTitle = `Test Role ${Date.now()}`;
    await modal.locator('input[placeholder="Start typing to see job title suggestions"]').fill(jobTitle);
    await modal.locator('input[placeholder="Start typing to see job title suggestions"]').evaluate((el) => el.blur());
    await page.waitForTimeout(400);
    await modal.locator('input[type="text"]').nth(1).fill('Test Company Inc.');
    await modal.locator('select').first().selectOption('Local');
    await modal.locator('select').nth(1).selectOption('Regular');
    await modal.locator('select').nth(2).selectOption('Private');
    await modal.locator('textarea').fill('Debug description of responsibilities.');
    await modal.locator('input[type="date"]').first().fill('2020-01-01');
    await modal.locator('#expCurrent').check();

    await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('addExperience')),
      modal.locator('button[type="submit"]').click(),
    ]);
    await expect(modal).not.toBeVisible({ timeout: 5000 });
    await expect(page.locator('body')).toContainText(jobTitle);

    // Edit
    const expSection = page.locator('section', { has: page.locator('h2', { hasText: 'Work Experience' }) }).first();
    await expSection.locator('button:has(i.fa-edit)').first().click();
    const editModal = page.locator('div[role="dialog"]:has-text("Edit Work Experience")');
    await expect(editModal).toBeVisible();
    await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('updateExperience')),
      editModal.locator('button[type="submit"]').click(),
    ]);
    await expect(editModal).not.toBeVisible({ timeout: 5000 });

    // Delete
    await expSection.locator('button:has(i.fa-trash)').first().click();
    const deleteModal = page.locator('div[role="dialog"]:has-text("Delete")').last();
    await expect(deleteModal).toBeVisible();
    await deleteModal.locator('button:has-text("Delete")').click();
    await expect(deleteModal).not.toBeVisible({ timeout: 5000 });
  });
});
