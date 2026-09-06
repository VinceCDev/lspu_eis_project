const accounts = require('./testAccounts');

/**
 * Logs in through the REAL login form (not a session shortcut) — types
 * into the actual fields, clicks the actual submit button, and waits for
 * the actual client-side redirect the app performs on success.
 */
async function loginAs(page, roleKey) {
  const account = accounts[roleKey];
  await page.goto('/login');
  await page.fill('input[name="email"]', account.email);
  await page.fill('input[name="password"]', accounts.PASSWORD);
  await page.click('button[type="submit"]');
  // waitUntil defaults to 'load', which blocks on every image on the
  // destination page finishing (e.g. alumni home's job-listing logos) —
  // the URL match itself is what we actually care about here.
  await page.waitForURL(new RegExp(account.homePath.replace('/', '')), { timeout: 15000, waitUntil: 'domcontentloaded' });
  return account;
}

module.exports = { loginAs, accounts };
