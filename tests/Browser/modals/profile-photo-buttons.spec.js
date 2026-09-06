// @ts-check
const path = require('path');
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Profile photo/logo modal button cleanup, applied consistently across
 * Alumni, Employer, and Admin/Superadmin (admin/profile.blade.php is shared
 * by both roles via the same route+controller):
 *  - No more redundant "Change Photo" button (Alumni) or "Cancel" button —
 *    only Delete Photo (when a photo already exists) + Save/Update Photo,
 *    both flex-1 so they fill the row with no dead space.
 *  - The save button reads "Update Photo" once a photo already exists,
 *    "Save Photo" the first time there's none yet.
 *  - Employer and Admin/Superadmin previously had NO working delete-photo
 *    action at all (Employer's was fully-built but never wired to any
 *    button; Admin/Superadmin had neither a button nor a backend action) —
 *    both are now real, working delete flows with their own confirm modal.
 */
test.describe('Profile photo modal — Delete/Save button cleanup', () => {
  test('Alumni: Save button reads "Update Photo" once a photo exists; no Cancel/Change Photo buttons', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    await page.locator('button[aria-label="Change profile photo"]').click();
    const modal = page.locator('div[role="dialog"]:has-text("Update Profile Photo")');
    await expect(modal).toBeVisible();

    await expect(modal.locator('button:has-text("Cancel")')).toHaveCount(0);
    await expect(modal.locator('button:has-text("Change Photo")')).toHaveCount(0);

    const fileInput = modal.locator('input[type="file"][accept="image/*"]');
    await fileInput.setInputFiles(path.join(__dirname, '..', 'fixtures', 'files', 'test-avatar.png'));
    const saveBtn = modal.locator('button', { hasText: /Save Photo|Update Photo/ });
    await expect(saveBtn).toBeVisible();

    const [response] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('ProfilePic')),
      saveBtn.click(),
    ]);
    expect((await response.json()).success).toBe(true);

    // Re-open: now that a photo exists, the button must say "Update Photo"
    // and a Delete Photo button must be present.
    await page.locator('button[aria-label="Change profile photo"]').click();
    await expect(modal).toBeVisible();
    await expect(modal.locator('button:has-text("Update Photo")')).toBeVisible();
    await expect(modal.locator('button:has-text("Delete Photo")')).toBeVisible();
  });

  test('Employer: logo modal has a working Delete Photo button (was dead code before)', async ({ page }) => {
    await loginAs(page, 'employer');
    await page.goto('/employer_profile');
    await page.waitForLoadState('networkidle');

    await page.locator('div:has(> i.fa-camera)').first().click();
    const modal = page.locator('div[role="dialog"]:has-text("Update Company Logo")');
    await expect(modal).toBeVisible();
    await expect(modal.locator('button:has-text("Cancel")')).toHaveCount(0);

    const deleteBtn = modal.locator('button:has-text("Delete Photo")');
    if (!(await deleteBtn.count())) {
      test.skip(true, 'No existing logo to delete for this account.');
    }
    await deleteBtn.click();
    const confirmModal = page.locator('div[role="dialog"]:has-text("Confirm Delete")');
    await expect(confirmModal).toBeVisible();
    const [response] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('deleteLogo')),
      confirmModal.locator('button:has-text("Delete")').click(),
    ]);
    expect((await response.json()).success).toBe(true);
    await expect(confirmModal).not.toBeVisible({ timeout: 5000 });
  });

  test('Admin/Superadmin: profile photo modal has a working Delete Photo button (built from scratch)', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/admin_profile');
    await page.waitForLoadState('networkidle');

    await page.locator('div:has(> i.fa-camera)').first().click();
    const modal = page.locator('div[role="dialog"]:has-text("Update Profile Photo")');
    await expect(modal).toBeVisible();
    await expect(modal.locator('button:has-text("Cancel")')).toHaveCount(0);

    const fileInput = modal.locator('input[type="file"][accept="image/*"]');
    await fileInput.setInputFiles(path.join(__dirname, '..', 'fixtures', 'files', 'test-avatar.png'));
    const [saveResp] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('updatePhoto')),
      modal.locator('button', { hasText: /Save Photo|Update Photo/ }).click(),
    ]);
    expect((await saveResp.json()).success).toBe(true);

    await page.waitForLoadState('networkidle');
    await page.locator('div:has(> i.fa-camera)').first().click();
    await expect(modal).toBeVisible();
    const deleteBtn = modal.locator('button:has-text("Delete Photo")');
    await expect(deleteBtn).toBeVisible();
    await deleteBtn.click();
    const confirmModal = page.locator('div[role="dialog"]:has-text("Confirm Delete")');
    await expect(confirmModal).toBeVisible();
    const [delResp] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('deletePhoto')),
      confirmModal.locator('button:has-text("Delete")').click(),
    ]);
    expect((await delResp.json()).success).toBe(true);
  });
});
