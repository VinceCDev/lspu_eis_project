import { describe, it, expect } from 'vitest';
const { fileIconClass } = require('../../public/assets/js/utils/fileHelpers.js');

describe('fileIconClass', () => {
    it('returns the red pdf icon for a .pdf file', () => {
        expect(fileIconClass('resume.pdf')).toBe('fas fa-file-pdf text-red-500');
    });

    it('returns the blue image icon for common image extensions', () => {
        for (const ext of ['jpg', 'jpeg', 'png', 'gif', 'webp']) {
            expect(fileIconClass(`photo.${ext}`)).toBe('fas fa-file-image text-blue-500');
        }
    });

    it('returns the word icon for .doc and .docx files', () => {
        expect(fileIconClass('cover_letter.doc')).toBe('fas fa-file-word text-blue-600');
        expect(fileIconClass('cover_letter.docx')).toBe('fas fa-file-word text-blue-600');
    });

    it('falls back to the generic file icon for an unrecognized extension', () => {
        expect(fileIconClass('archive.zip')).toBe('fas fa-file-alt text-gray-500');
    });

    it('falls back to the generic file icon when given no filename at all', () => {
        expect(fileIconClass('')).toBe('fas fa-file-alt text-gray-500');
        expect(fileIconClass(null)).toBe('fas fa-file-alt text-gray-500');
        expect(fileIconClass(undefined)).toBe('fas fa-file-alt text-gray-500');
    });

    it('is case-insensitive on the extension', () => {
        expect(fileIconClass('SCAN.PDF')).toBe('fas fa-file-pdf text-red-500');
    });
});
