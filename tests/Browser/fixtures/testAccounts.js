// Dedicated ISO/IEC 25010 browser-testing accounts — NOT real user/production
// credentials. Created directly in the dev database (see the remediation
// report for the exact setup script) with the "browsertest-" email prefix
// so they're easy to identify and remove. Password is test-only.
module.exports = {
  PASSWORD: 'BrowserTest!2026',
  alumni: { email: 'browsertest-alumni@example.test', role: 'alumni', homePath: '/home' },
  employer: { email: 'browsertest-employer@example.test', role: 'employer', homePath: '/employer_dashboard' },
  admin: { email: 'browsertest-admin@example.test', role: 'admin', homePath: '/admin_dashboard' },
  superadmin: { email: 'browsertest-superadmin@example.test', role: 'superadmin', homePath: '/superadmin_dashboard' },
};
