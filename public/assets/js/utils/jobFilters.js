/**
 * Pure date-math helpers behind the alumni home page's job listing —
 * pulled out of home.js so this logic can be unit tested (home.js itself
 * is a plain <script>, not an ES module, and can't be imported directly by
 * a test runner). Loaded as a normal <script> tag before home.js, which
 * reads it off `window.JobFilters`; also exported via CommonJS so Vitest
 * (running under Node) can `require()` it directly. Same file, two ways in
 * — nothing about how home.js loads today changes.
 */
function postedDays(createdAt) {
    if (!createdAt) return '';
    const posted = new Date(createdAt);
    const now = new Date();
    const diff = Math.floor((now - posted) / (1000 * 60 * 60 * 24));
    if (diff === 0) return 'Posted today';
    if (diff === 1) return 'Posted 1 day ago';
    return `Posted ${diff} days ago`;
}

function matchesDatePosted(createdAt, maxDays) {
    if (!createdAt) return false;
    const posted = new Date(createdAt);
    const now = new Date();
    const diffDays = Math.floor((now - posted) / (1000 * 60 * 60 * 24));
    return diffDays <= Number(maxDays);
}

const JobFilters = { postedDays, matchesDatePosted };

if (typeof window !== 'undefined') {
    window.JobFilters = JobFilters;
}
if (typeof module !== 'undefined' && module.exports) {
    module.exports = JobFilters;
}
