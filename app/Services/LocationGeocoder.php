<?php

namespace App\Services;

/**
 * Nominatim lookups for the alumni location map. Extracted from
 * Admin\DashboardController so the background command (map:geocode-locations)
 * and the legacy `geocode` action share one implementation. Nothing on the
 * Dashboard's request path calls this any more — the map only reads coordinates
 * that are already in location_geocode_cache.
 */
class LocationGeocoder
{
    /** Nominatim's usage policy: at most ~1 request/second. */
    public const SLEEP_MICROSECONDS = 1100000;

    /** Cheap filter: a real "City, Province" has no house numbers, street or barangay tokens. */
    public function looksLikePlace(string $s): bool
    {
        if ($s === '' || mb_strlen($s) > 80 || preg_match('/\d/', $s)) {
            return false;
        }

        return !preg_match('/\b(brgy|barangay|purok|sitio|blk|block|lot|phase|st\.?|street|ave|avenue|subd|subdivision|#)\b/i', $s);
    }

    /** @return array{lat: float, lng: float}|null */
    public function lookup(string $location): ?array
    {
        $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q='
            .urlencode($location.', Philippines');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'LSPU-EIS-AlumniMap/1.0',
        ]);
        $response = curl_exec($ch);
        $ok = $response !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);

        if (!$ok) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data[0]['lat']) || empty($data[0]['lon'])) {
            return null;
        }

        return ['lat' => (float) $data[0]['lat'], 'lng' => (float) $data[0]['lon']];
    }

    /**
     * Best-effort coordinates for a free-text "city" + province. Tries the exact place first, then progressively
     * coarser guesses so a place Nominatim doesn't know ("San Veronica SPC") still lands on the map at its
     * municipality/city, and as a last resort at the province:
     *   1. "City, Province"
     *   2. each comma-separated part of the city (address style "Brgy X, San Pablo City"), street/barangay/number
     *      words stripped, last part first
     *   3. the province alone
     * Sleeps between Nominatim calls (usage policy), so one call may take a few seconds.
     *
     * @return array{lat: float, lng: float, level: string}|null level = 'place' | 'city' | 'province'
     */
    public function lookupWithFallback(string $city, string $province): ?array
    {
        $city = trim($city);
        $province = trim($province);
        $tried = [];
        $attempt = function (string $query, string $level) use (&$tried): ?array {
            $q = mb_strtolower($query);
            if ($query === '' || isset($tried[$q])) {
                return null;
            }
            $tried[$q] = true;
            if ($tried !== [$q => true]) {
                usleep(self::SLEEP_MICROSECONDS);
            }
            $at = $this->lookup($query);

            return $at === null ? null : $at + ['level' => $level];
        };

        if ($city !== '' && $province !== '' && ($hit = $attempt("{$city}, {$province}", 'place'))) {
            return $hit;
        }

        $parts = array_reverse(array_filter(array_map('trim', explode(',', $city))));
        foreach ($parts as $part) {
            $clean = trim(preg_replace('/\b(brgy|barangay|purok|sitio|blk|block|lot|phase|st|street|ave|avenue|subd|subdivision)\b\.?|[#\d]+/i', ' ', $part));
            $clean = trim(preg_replace('/\s+/', ' ', $clean));
            if (mb_strlen($clean) >= 3 && ($hit = $attempt($province !== '' ? "{$clean}, {$province}" : $clean, 'city'))) {
                return $hit;
            }
        }

        if ($province !== '' && ($hit = $attempt($province, 'province'))) {
            return $hit;
        }

        return null;
    }
}
