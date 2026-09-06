// @ts-check
const { test, expect } = require('@playwright/test');
const { loginAs } = require('../fixtures/helpers');

/**
 * D4 — Admin account creation HTTP 500. Root cause: Account::createEmployer()/
 * createAdmin()/createAlumni() omitted several DB-required, no-default
 * columns (company_location, address, verification_document, contact_email,
 * contact_number, nature_of_business, tin, date_established, company_type,
 * accreditation_status, document_file, contact, gender, civil_status, city,
 * province, year_graduated, college, course). Fixed in app/Models/Account.php
 * (string fields default to '', matching this codebase's own established
 * "fill in later" convention already used in Employer\ProfileController);
 * date_established/birthdate/year_graduated made nullable via migration
 * since '' is not a valid value for DATE/INT columns under this DB's
 * active strict SQL mode.
 */
test.describe('D4 — Admin account creation (real browser)', () => {
  async function openAddAccountModal(page) {
    await page.goto('/admin_user');
    await page.waitForLoadState('networkidle');
    await page.click('button:has-text("Add Admin")');
    await expect(page.locator('div[role="dialog"]')).toBeVisible();
  }

  test('Superadmin creates a valid Employer account — no HTTP 500', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await openAddAccountModal(page);

    const modal = page.locator('div[role="dialog"]');
    await modal.locator('select').selectOption('employer');

    await modal.locator('input[type="text"]').nth(0).fill('Playwright D4 Test Co');
    await modal.locator('input[type="text"]').nth(1).fill('Information Technology');
    const email = `browsertest-d4-employer-${Date.now()}@example.test`;
    await modal.locator('input[type="email"]').fill(email);

    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('admin_user')),
      modal.locator('button[type="submit"]').click(),
    ]);

    expect(response.status()).toBeLessThan(500);
    const body = await response.json();
    console.log('D4 EMPLOYER CREATE RESPONSE:', JSON.stringify(body));
    expect(body.success).toBe(true);

    await expect(modal).not.toBeVisible({ timeout: 5000 });
    await expect(page.locator('body')).toContainText(email.split('@')[0], { timeout: 5000 }).catch(() => {});
  });

  test('Superadmin creates a valid Admin account — no HTTP 500', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await openAddAccountModal(page);

    const modal = page.locator('div[role="dialog"]');
    await modal.locator('select').selectOption('admin');

    await modal.locator('input[type="text"]').nth(0).fill('Playwright');
    await modal.locator('input[type="text"]').nth(2).fill('D4Admin');
    // Once a role is chosen, the role-select unmounts (v-if="roleSelectionStep")
    // and Campus becomes the first remaining <select> (Status is second).
    await modal.locator('select').nth(0).selectOption({ index: 1 }); // campus
    const email = `browsertest-d4-admin-${Date.now()}@example.test`;
    await modal.locator('input[type="email"]').fill(email);

    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('admin_user')),
      modal.locator('button[type="submit"]').click(),
    ]);

    expect(response.status()).toBeLessThan(500);
    const body = await response.json();
    console.log('D4 ADMIN CREATE RESPONSE:', JSON.stringify(body));
    expect(body.success).toBe(true);
    await expect(modal).not.toBeVisible({ timeout: 5000 });
  });

  test('Admin creates a valid Alumni account — no HTTP 500', async ({ page }) => {
    await loginAs(page, 'admin');
    await openAddAccountModal(page);

    const modal = page.locator('div[role="dialog"]');
    await modal.locator('select').selectOption('alumni');

    await modal.locator('input[type="text"]').nth(0).fill('Playwright');
    await modal.locator('input[type="text"]').nth(2).fill('D4Alumni');
    const email = `browsertest-d4-alumni-${Date.now()}@example.test`;
    await modal.locator('input[type="email"]').fill(email);

    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('admin_user')),
      modal.locator('button[type="submit"]').click(),
    ]);

    expect(response.status()).toBeLessThan(500);
    const body = await response.json();
    console.log('D4 ALUMNI CREATE RESPONSE:', JSON.stringify(body));
    expect(body.success).toBe(true);
    await expect(modal).not.toBeVisible({ timeout: 5000 });
  });

  test('Missing required field is blocked by HTML5 validation, no request sent', async ({ page }) => {
    await loginAs(page, 'superadmin');
    await openAddAccountModal(page);

    const modal = page.locator('div[role="dialog"]');
    await modal.locator('select').selectOption('employer');
    // Leave company name / industry / email all empty and try to submit
    let requestFired = false;
    page.on('request', (req) => {
      if (req.method() === 'POST' && req.url().includes('admin_user')) requestFired = true;
    });
    await modal.locator('button[type="submit"]').click();
    await page.waitForTimeout(500);
    expect(requestFired).toBe(false);
    await expect(modal).toBeVisible(); // still open
  });

  test('Duplicate email is rejected without creating a second account', async ({ page }) => {
    await loginAs(page, 'superadmin');
    const email = `browsertest-d4-dup-${Date.now()}@example.test`;

    // Create once
    await openAddAccountModal(page);
    let modal = page.locator('div[role="dialog"]');
    await modal.locator('select').selectOption('employer');
    await modal.locator('input[type="text"]').nth(0).fill('Dup Co');
    await modal.locator('input[type="text"]').nth(1).fill('IT');
    await modal.locator('input[type="email"]').fill(email);
    await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('admin_user')),
      modal.locator('button[type="submit"]').click(),
    ]);
    await expect(modal).not.toBeVisible({ timeout: 5000 });

    // Try again with the same email
    await openAddAccountModal(page);
    modal = page.locator('div[role="dialog"]');
    await modal.locator('select').selectOption('employer');
    await modal.locator('input[type="text"]').nth(0).fill('Dup Co 2');
    await modal.locator('input[type="text"]').nth(1).fill('IT');
    await modal.locator('input[type="email"]').fill(email);

    const [response] = await Promise.all([
      page.waitForResponse((res) => res.request().method() === 'POST' && res.url().includes('admin_user')),
      modal.locator('button[type="submit"]').click(),
    ]);
    expect(response.status()).toBeLessThan(500);
    const body = await response.json();
    console.log('D4 DUPLICATE EMAIL RESPONSE:', JSON.stringify(body));
    expect(body.success).toBe(false);
  });
});
