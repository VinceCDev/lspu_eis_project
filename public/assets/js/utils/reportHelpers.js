/**
 * Pure helpers behind the admin/superadmin Reports page's Excel exports and
 * summary table — pulled out of admin_reports.js (a 2000+ line file) so
 * this logic is unit-testable and has exactly one definition. Same
 * dual-mode loading as jobFilters.js: a plain <script> tag exposes
 * `window.ReportHelpers` for the Vue component, and `module.exports` lets
 * Vitest `require()` it directly — nothing about how admin_reports.js loads
 * today changes.
 */
function calculatePercentage(part, total) {
    if (total === 0) return 0;
    return Math.round((part / total) * 100);
}

// Mirrors DashboardStats::COLLEGE_ABBREVIATIONS on the backend. Must cover
// every college name that can actually appear, and must not let two
// different colleges collapse onto the same abbreviation — Excel worksheet
// names have to be unique or workbook.addWorksheet() throws, which used to
// kill this export outright once enough colleges existed to collide on the
// old "first 3 letters" fallback (they all start with "College of...", so
// several distinct colleges all fell back to "COL").
const COLLEGE_ABBREVIATIONS = {
    'College of Computer Studies': 'CCS',
    'College of Business Administration and Accountancy': 'CBAA',
    'College of Arts and Sciences': 'CAS',
    'College of Teacher Education': 'CTE',
    'College of Engineering': 'COE',
    'College of Agriculture': 'CA',
    'College of Criminal Justice Education': 'CCJE',
    'College of Industrial Technology': 'CIT',
    'College of International Hospitality and Tourism Management': 'CIHTM',
    'College of Nursing and Allied Health': 'CNAH',
    'College of Fisheries': 'CF',
    'College of Food Nutrition and Dietetics': 'CFND',
};

function getCollegeAbbreviation(collegeName) {
    if (COLLEGE_ABBREVIATIONS[collegeName]) return COLLEGE_ABBREVIATIONS[collegeName];

    // Unmapped college: derive initials from significant words instead of
    // "first 3 letters", so an unlisted college doesn't collide with every
    // other unlisted one under the same "COL" prefix.
    const skipWords = new Set(['of', 'and', 'the', 'for']);
    const initials = collegeName.split(/\s+/)
        .filter(word => word && !skipWords.has(word.toLowerCase()))
        .map(word => word[0].toUpperCase())
        .join('');
    return initials || collegeName.substring(0, 3).toUpperCase();
}

const ReportHelpers = { calculatePercentage, getCollegeAbbreviation };

if (typeof window !== 'undefined') {
    window.ReportHelpers = ReportHelpers;
}
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ReportHelpers;
}
