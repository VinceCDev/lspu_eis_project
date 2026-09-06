// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { loginAs } = require('../fixtures/helpers');

/**
 * Automated accessibility scanning via axe-core. This catches objectively
 * detectable violations (missing labels, ARIA misuse, contrast ratios,
 * missing alt text, heading structure) — it does NOT replace screen-reader
 * testing, full keyboard-only walkthroughs, or human cognitive-accessibility
 * review, which remain MANUAL TEST REQUIRED regardless of these results.
 */
test.describe('Automated accessibility scan (axe-core)', () => {
  const pages = [
    { name: 'landing', path: '/landing', auth: null },
    { name: 'login', path: '/login', auth: null },
    { name: 'admin-dashboard', path: '/admin_dashboard', auth: 'admin' },
    { name: 'alumni-home', path: '/home', auth: 'alumni' },
  ];

  for (const { name, path, auth } of pages) {
    test(`${name} — axe scan`, async ({ page }) => {
      if (auth) {
        await loginAs(page, auth);
      }
      await page.goto(path);
      await page.waitForLoadState('networkidle');

      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .analyze();

      const summary = results.violations.map((v) => ({
        id: v.id,
        impact: v.impact,
        help: v.help,
        nodeCount: v.nodes.length,
      }));

      console.log(`\n=== AXE VIOLATIONS: ${name} ===`);
      console.log(JSON.stringify(summary, null, 2));

      // Not asserting zero violations (a strict gate would be premature
      // given this app's existing legacy markup) — reporting count and
      // detail is the actual deliverable for this phase. Critical/serious
      // violations are flagged loudly here so they aren't lost in the log.
      const critical = results.violations.filter((v) => v.impact === 'critical' || v.impact === 'serious');
      if (critical.length > 0) {
        console.log(`*** ${critical.length} CRITICAL/SERIOUS violation(s) on ${name} ***`);
      }
    });
  }
});
