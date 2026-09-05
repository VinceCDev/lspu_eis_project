import { describe, it, expect } from 'vitest';
const { calculatePercentage, getCollegeAbbreviation } = require('../../public/assets/js/utils/reportHelpers.js');

describe('calculatePercentage', () => {
    it('rounds to the nearest whole percent', () => {
        expect(calculatePercentage(1, 3)).toBe(33);
        expect(calculatePercentage(2, 3)).toBe(67);
    });

    it('returns 0 rather than dividing by zero when total is 0', () => {
        expect(calculatePercentage(5, 0)).toBe(0);
    });

    it('returns 100 when part equals total', () => {
        expect(calculatePercentage(10, 10)).toBe(100);
    });
});

describe('getCollegeAbbreviation', () => {
    it('returns the mapped abbreviation for a known college', () => {
        expect(getCollegeAbbreviation('College of Computer Studies')).toBe('CCS');
        expect(getCollegeAbbreviation('College of Business Administration and Accountancy')).toBe('CBAA');
    });

    it('derives initials from significant words for an unmapped college', () => {
        expect(getCollegeAbbreviation('College of Something New')).toBe('CSN');
    });

    it('skips filler words (of/and/the/for) when deriving initials', () => {
        // Without the filler-word skip, this would collide with every other
        // unmapped "College of ..." name on "CO" or similar.
        expect(getCollegeAbbreviation('College of Law and the Arts')).toBe('CLA');
    });

    it('reduces a single-word college name to just its first letter', () => {
        // Known, narrow edge case: a single word has exactly one "initial",
        // so any two single-word college names sharing a first letter WILL
        // collide (e.g. "Veterinary" and "Vocational" would both map to
        // "V"). None of the 12 explicitly-mapped colleges are single-word,
        // so this can't fire today — pinned here so it's a documented
        // limitation rather than a silent surprise if an unmapped
        // single-word college is ever added.
        expect(getCollegeAbbreviation('Veterinary')).toBe('V');
    });

    it('falls back to a 3-letter substring only when there are no usable words at all', () => {
        // Every word is a skip-word, so .join('') is empty and the
        // substring(0, 3) fallback is what actually fires.
        expect(getCollegeAbbreviation('of and the')).toBe('OF ');
    });
});
