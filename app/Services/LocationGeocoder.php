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
}
