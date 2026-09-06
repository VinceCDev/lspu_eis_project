// @ts-check
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');
const { loginAs } = require('../fixtures/helpers');

/**
 * Final targeted validation pass — Admin Application management
 * (view/delete). Each test seeds its OWN disposable job+application pair
 * via `php artisan tinker` (self-contained and idempotent across repeated
 * runs — an earlier version of this file seeded once by hand outside the
 * spec, which worked standalone but broke on a second run since the row
 * had already been deleted by the previous run's Delete test) so the
 * shared application used by employer/applicant-status.spec.js is never
 * touched.
 */
function seedDisposableApplication(titleSuffix) {
  const title = `QA Disposable Applicant Job ${titleSuffix}`;
  const php = `
    $jobId = DB::table('jobs')->insertGetId([
      'employer_id' => 1000212,
      'title' => '${title}',
      'type' => 'Full-time',
      'work_setup' => 'Onsite',
      'classification' => 'Information Technology',
      'location' => 'Test City',
      'status' => 'Active',
      'created_at' => now()->toDateString(),
      'description' => 'QA disposable job for admin applicant management test.',
      'requirements' => 'None.',
      'qualifications' => 'None.',
      'employer_question' => '',
      'employer_question_required' => 0,
    ]);
    DB::table('applications')->insert([
      'alumni_id' => 999942,
      'job_id' => $jobId,
      'status' => 'Pending',
      'cover_letter_text' => null,
      'cover_letter_file' => null,
      'application_answer' => null,
      'applied_at' => now(),
    ]);
    echo $jobId;
  `.replace(/\n/g, ' ');
  const jobId = execSync(`php artisan tinker --execute="${php.replace(/"/g, '\\"')}"`, { cwd: process.cwd() })
    .toString()
    .trim();
  return { title, jobId };
}

// admin_applicant's destroy action only removes the `applications` row, not
// the underlying `jobs` row it points at — left uncleaned, disposable job
// rows accumulate in browsertest-employer's job list and get silently
// picked up by OTHER employer/jobposting tests' `.first()`/`.last()` row
// locators (confirmed: it broke employer/job-edit.spec.js and
// employer/job-lifecycle.spec.js by landing an invalid Classification value
// in front of them). Cleaned up in afterEach — NOT inline after an
// assertion — so a failed assertion (e.g. a browser-specific false-positive
// mid-test) can never skip cleanup and leak the row into other tests again
// (confirmed: that exact failure mode happened once already on Firefox).
function deleteJob(jobId) {
  if (!jobId) return;
  execSync(`php artisan tinker --execute="DB::table('applications')->where('job_id', ${jobId})->delete(); DB::table('jobs')->where('job_id', ${jobId})->delete();"`, { cwd: process.cwd() });
}

test.describe('Admin — Application management (view/delete)', () => {
  let currentJobId = null;

  test.afterEach(async () => {
    deleteJob(currentJobId);
    currentJobId = null;
  });

  test('View: opens the applicant detail modal with real application data', async ({ page }) => {
    const { title, jobId } = seedDisposableApplication(`View-${Date.now()}`);
    currentJobId = jobId;

    await loginAs(page, 'admin');
    await page.goto('/admin_applicant');
    await page.waitForLoadState('networkidle');

    // Attached only after the page has settled — attaching earlier (right
    // after loginAs(), whose destination page keeps loading dashboard
    // widgets in the background) picks up requests Firefox reports as
    // "failed" simply because the subsequent goto() cancelled them
    // mid-flight, a browser-specific navigation artifact, not a real
    // app failure (confirmed via trace: the same requests succeed when not
    // interrupted by navigation).
    const failedRequests = [];
    page.on('requestfailed', (req) => failedRequests.push(req.url()));

    await page.fill('input[placeholder="Search applicants..."]', title);
    await page.waitForTimeout(500);

    const row = page.locator('table tbody tr', { hasText: title }).first();
    await expect(row).toBeVisible({ timeout: 10000 });
    await row.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("View")').first().click();

    const modal = page.locator(`div[role="dialog"]:has-text("${title}")`).first();
    await expect(modal).toBeVisible({ timeout: 5000 });

    expect(failedRequests, `Unexpected failed requests: ${failedRequests.join(', ')}`).toEqual([]);
    await modal.locator('button:has(i.fa-times), [aria-label="Close"]').first().click().catch(() => {});
  });

  test('Delete: removes the applicant record and it disappears from the list', async ({ page }) => {
    const { title, jobId } = seedDisposableApplication(`Delete-${Date.now()}`);
    currentJobId = jobId;

    await loginAs(page, 'admin');
    await page.goto('/admin_applicant');
    await page.waitForLoadState('networkidle');
    await page.fill('input[placeholder="Search applicants..."]', title);
    await page.waitForTimeout(500);

    const row = page.locator('table tbody tr', { hasText: title }).first();
    await expect(row).toBeVisible({ timeout: 10000 });
    await row.locator('button:has(i.fa-ellipsis-h)').click();
    await page.locator('.teleported-action-dropdown a:has-text("Delete")').first().click();

    const confirmModal = page.locator('div[role="dialog"]:has-text("Delete")').last();
    await expect(confirmModal).toBeVisible();
    const [response] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('admin_applicant') && res.request().method() === 'DELETE'),
      confirmModal.locator('button:has-text("Delete")').last().click(),
    ]);
    expect(response.status()).toBeLessThan(500);
    const body = await response.json();
    console.log('DELETE APPLICANT RESPONSE:', JSON.stringify(body));
    expect(body.success).toBe(true);

    await page.reload();
    await page.waitForLoadState('networkidle');
    await expect(page.locator('table tbody tr', { hasText: title })).toHaveCount(0);
  });
});
