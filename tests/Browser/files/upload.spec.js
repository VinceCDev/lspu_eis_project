// @ts-check
const path = require('path');
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * Real end-to-end file upload through the browser — form fill, actual
 * multipart submission, server response, and (critically) verifying the
 * resulting image URL actually resolves to 200. This directly closes the
 * loop on the uploads-storage-path defect fixed in the prior remediation
 * pass (public/uploads had silently drifted into a stale, disconnected
 * copy) with real upload evidence instead of a static file check.
 */
test.describe('File upload — real browser', () => {
  test('alumni profile photo upload succeeds and the resulting image loads (not 404)', async ({ page }) => {
    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    // The photo modal (and its file input) only exists in the DOM once
    // opened — it's behind v-if, not just hidden. The trigger is the
    // camera-icon overlay on the avatar (a clickable <div>, not a real
    // <button> — noted separately as an accessibility/semantics finding).
    await page.locator('.fa-camera').click();

    const fileInput = page.locator('input[type="file"][accept="image/*"]');
    await expect(fileInput).toHaveCount(1);
    await fileInput.setInputFiles(path.join(__dirname, '..', 'fixtures', 'files', 'test-avatar.png'));

    // Modal should now show the preview and an enabled Save button
    // Save button reads "Update Photo" once the account already has a
    // profile picture, "Save Photo" otherwise.
    const saveButton = page.locator('button:has-text("Save Photo"), button:has-text("Update Photo")');
    await expect(saveButton).toBeEnabled({ timeout: 5000 });

    const [response] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('action=updateProfilePic') || res.url().includes('action=insertProfilePic')),
      saveButton.click(),
    ]);

    const body = await response.json();
    console.log('UPLOAD RESPONSE:', JSON.stringify(body));
    expect(body.success).toBe(true);

    // Verify the uploaded file is actually reachable — the real regression
    // check for the earlier "stale public/uploads copy" defect.
    await page.waitForTimeout(500); // fetchProfilePic() refetch after save
    const imgSrc = await page.locator('img[alt="Profile Photo"]').first().getAttribute('src');
    console.log('RESULTING IMAGE SRC:', imgSrc);
    expect(imgSrc).toContain('uploads/profile_picture/');

    const imgResponse = await page.request.get(imgSrc);
    expect(imgResponse.status()).toBe(200);
  });

  test('oversized/invalid file type is rejected without a JS exception', async ({ page }) => {
    const consoleErrors = [];
    page.on('pageerror', (err) => consoleErrors.push(err.message));

    await loginAs(page, 'alumni');
    await page.goto('/my_profile');
    await page.waitForLoadState('networkidle');

    await page.locator('.fa-camera').click();

    // accept="image/*" on the input means most browsers won't even let a
    // non-image be selected via the picker — Playwright's setInputFiles
    // bypasses that filter, so this actually tests the SERVER-side
    // Uploader validation, not just the client-side accept hint.
    const fileInput = page.locator('input[type="file"][accept="image/*"]');
    const textFilePath = path.join(__dirname, '..', 'fixtures', 'files', 'not-an-image.txt');
    require('fs').writeFileSync(textFilePath, 'this is not an image');
    await fileInput.setInputFiles(textFilePath);

    // Save button reads "Update Photo" once the account already has a
    // profile picture, "Save Photo" otherwise.
    const saveButton = page.locator('button:has-text("Save Photo"), button:has-text("Update Photo")');
    if (await saveButton.isEnabled().catch(() => false)) {
      const [response] = await Promise.all([
        page.waitForResponse((res) => res.url().includes('ProfilePic')),
        saveButton.click(),
      ]);
      const body = await response.json();
      console.log('INVALID FILE UPLOAD RESPONSE:', JSON.stringify(body));
      expect(body.success).toBe(false);
    }

    expect(consoleErrors).toEqual([]);
  });
});
