/**
 * File-type-to-icon mapping — was duplicated identically in my_profile.js
 * and employer_profile.js (the alumni verification-document card and the
 * employer company-document card use the same icon scheme). Same dual-mode
 * loading as jobFilters.js/reportHelpers.js: `window.FileHelpers` for the
 * existing plain <script> Vue components, `module.exports` for Vitest.
 */
function fileIconClass(filename) {
    const ext = (filename || '').split('.').pop().toLowerCase();
    if (ext === 'pdf') return 'fas fa-file-pdf text-red-500';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'fas fa-file-image text-blue-500';
    if (['doc', 'docx'].includes(ext)) return 'fas fa-file-word text-blue-600';
    return 'fas fa-file-alt text-gray-500';
}

const FileHelpers = { fileIconClass };

if (typeof window !== 'undefined') {
    window.FileHelpers = FileHelpers;
}
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FileHelpers;
}
