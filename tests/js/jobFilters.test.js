import { describe, it, expect } from 'vitest';
const { postedDays, matchesDatePosted } = require('../../public/assets/js/utils/jobFilters.js');

describe('postedDays', () => {
    it('returns "Posted today" for a job posted right now', () => {
        expect(postedDays(new Date().toISOString())).toBe('Posted today');
    });

    it('returns "Posted 1 day ago" (singular) for a job posted yesterday', () => {
        const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString();
        expect(postedDays(yesterday)).toBe('Posted 1 day ago');
    });

    it('returns "Posted N days ago" (plural) for older jobs', () => {
        const fiveDaysAgo = new Date(Date.now() - 5 * 24 * 60 * 60 * 1000).toISOString();
        expect(postedDays(fiveDaysAgo)).toBe('Posted 5 days ago');
    });

    it('returns an empty string when there is no created_at', () => {
        expect(postedDays(null)).toBe('');
        expect(postedDays('')).toBe('');
    });
});

describe('matchesDatePosted', () => {
    it('matches a job posted well within the window', () => {
        const twoDaysAgo = new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString();
        expect(matchesDatePosted(twoDaysAgo, 7)).toBe(true);
    });

    it('matches a job posted exactly at the boundary (inclusive)', () => {
        const sevenDaysAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString();
        expect(matchesDatePosted(sevenDaysAgo, 7)).toBe(true);
    });

    it('does not match a job posted just outside the window', () => {
        const eightDaysAgo = new Date(Date.now() - 8 * 24 * 60 * 60 * 1000).toISOString();
        expect(matchesDatePosted(eightDaysAgo, 7)).toBe(false);
    });

    it('returns false when there is no created_at, rather than matching everything', () => {
        // The "Date Posted" filter option value is a string like "7"; make
        // sure the loose (Number(maxDays)) comparison doesn't accidentally
        // treat a missing date as a match.
        expect(matchesDatePosted(null, 7)).toBe(false);
        expect(matchesDatePosted('', 30)).toBe(false);
    });

    it('accepts maxDays as the string values the <select> options actually send', () => {
        const threeDaysAgo = new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString();
        expect(matchesDatePosted(threeDaysAgo, '7')).toBe(true);
        expect(matchesDatePosted(threeDaysAgo, '1')).toBe(false);
    });
});
