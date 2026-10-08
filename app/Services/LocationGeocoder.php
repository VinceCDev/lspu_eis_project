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

    /** Most Nominatim calls spent on one location before giving up. */
    private const MAX_ATTEMPTS = 6;

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
            .urlencode($location.', Philippines').'&countrycodes=ph';

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

    /** Cache / cluster key for a location: "City, Province", or just the one part that is filled in. */
    public static function key(?string $city, ?string $province): string
    {
        return implode(', ', array_filter([trim((string) $city), trim((string) $province)], static fn ($p) => $p !== ''));
    }

    /** Philippine provinces, so "Rizal Laguna" / "Candelaria Quezon" (no comma) can be split into town + province. */
    private const PROVINCES = [
        'Abra', 'Agusan del Norte', 'Agusan del Sur', 'Aklan', 'Albay', 'Antique', 'Apayao', 'Aurora', 'Basilan', 'Bataan',
        'Batanes', 'Batangas', 'Benguet', 'Biliran', 'Bohol', 'Bukidnon', 'Bulacan', 'Cagayan', 'Camarines Norte',
        'Camarines Sur', 'Camiguin', 'Capiz', 'Catanduanes', 'Cavite', 'Cebu', 'Cotabato', 'Davao de Oro', 'Davao del Norte',
        'Davao del Sur', 'Davao Occidental', 'Davao Oriental', 'Dinagat Islands', 'Eastern Samar', 'Guimaras', 'Ifugao',
        'Ilocos Norte', 'Ilocos Sur', 'Iloilo', 'Isabela', 'Kalinga', 'La Union', 'Laguna', 'Lanao del Norte', 'Lanao del Sur',
        'Leyte', 'Maguindanao del Norte', 'Maguindanao del Sur', 'Marinduque', 'Masbate', 'Metro Manila', 'Misamis Occidental',
        'Misamis Oriental', 'Mountain Province', 'Negros Occidental', 'Negros Oriental', 'Northern Samar', 'Nueva Ecija',
        'Nueva Vizcaya', 'Occidental Mindoro', 'Oriental Mindoro', 'Palawan', 'Pampanga', 'Pangasinan', 'Quezon', 'Quirino',
        'Rizal', 'Romblon', 'Samar', 'Sarangani', 'Siquijor', 'Sorsogon', 'South Cotabato', 'Southern Leyte', 'Sultan Kudarat',
        'Sulu', 'Surigao del Norte', 'Surigao del Sur', 'Tarlac', 'Tawi-Tawi', 'Zambales', 'Zamboanga del Norte',
        'Zamboanga del Sur', 'Zamboanga Sibugay',
    ];

    /** Abbreviations alumni type in the address fields. */
    private const ABBREVIATIONS = [
        '/\bSPC\b/i' => 'San Pablo City',
        '/\bQC\b/i' => 'Quezon City',
        '/\bSta\.?(?=\s)/i' => 'Santa',
        '/\bSto\.?(?=\s)/i' => 'Santo',
        '/\bSn\.?(?=\s)/i' => 'San',
    ];

    /** Words that describe a spot inside a town (not the town itself) - dropped before searching. */
    private const ADDRESS_WORDS = '/\b(brgy|bgy|barangay|purok|sitio|zone|blk|block|lot|phase|st|street|interior|ave|avenue|road|rd|subd|subdivision|village|compound)\b\.?|[#\d]+/i';

    /**
     * "Rizal Laguna" -> ["Rizal", "Laguna"]: a piece that ends in a known province and has more text before it. Without
     * this, Nominatim reads the comma-less "Rizal Laguna" as Rizal Park in Manila. Longest province name wins.
     *
     * @return string[]
     */
    private function splitTrailingProvince(string $piece): array
    {
        $best = null;
        foreach (self::PROVINCES as $province) {
            if (preg_match('/^(.+?)[\s,]+'.preg_quote($province, '/').'$/iu', $piece, $m)
                && ($best === null || mb_strlen($province) > mb_strlen($best[1]))) {
                $best = [trim($m[1]), $province];
            }
        }

        return $best === null ? [$piece] : [$best[0], $best[1]];
    }

    /**
     * Best-effort coordinates for a messy City + Province pair. Alumni fill these fields inconsistently: the whole
     * address in one field, the two swapped, the town typed in the province box, "SPC" for San Pablo City... so the
     * two fields are treated as one comma-separated address: [..., municipality/city, province]. Tries, in order
     *   1. "municipality, province"
     *   2. the municipality with leading words dropped ("Paliparan Calauan" -> "Calauan"), each with the province
     *   3. the last piece alone, then with leading words dropped ("72interior St. Bagong Pook SPC" -> "San Pablo City")
     * and gives up after MAX_ATTEMPTS Nominatim calls (usage policy: 1 request/second, so each call sleeps).
     *
     * @return array{lat: float, lng: float, level: string}|null level = 'place' | 'city' | 'province'
     */
    public function lookupWithFallback(string $city, string $province): ?array
    {
        $pieces = [];
        foreach ([$city, $province] as $field) {
            foreach (explode(',', $field) as $part) {
                $clean = trim(preg_replace('/\s+/', ' ', preg_replace(self::ADDRESS_WORDS, ' ', preg_replace(array_keys(self::ABBREVIATIONS), array_values(self::ABBREVIATIONS), $part))));
                foreach ($this->splitTrailingProvince($clean) as $piece) {
                    if (mb_strlen($piece) >= 3 && !in_array(mb_strtolower($piece), array_map('mb_strtolower', $pieces), true)) {
                        $pieces[] = $piece;
                    }
                }
            }
        }
        if ($pieces === []) {
            return null;
        }

        $last = $pieces[count($pieces) - 1];
        $muni = count($pieces) >= 2 ? $pieces[count($pieces) - 2] : null;
        $dropLeading = static function (string $text): array {
            $words = preg_split('/\s+/', $text);
            $out = [];
            for ($i = 1; $i < count($words); $i++) {
                $tail = implode(' ', array_slice($words, $i));
                if (mb_strlen($tail) >= 4) {
                    $out[] = $tail;
                }
            }

            return $out;
        };

        $queries = [];
        if ($muni !== null) {
            $queries[] = ["{$muni}, {$last}", 'place'];
            foreach ($dropLeading($muni) as $tail) {
                $queries[] = ["{$tail}, {$last}", 'city'];
            }
        }
        $queries[] = [$last, $muni === null ? 'place' : 'province'];
        foreach ($dropLeading($last) as $tail) {
            $queries[] = [$tail, 'city'];
        }

        $tried = [];
        $calls = 0;
        foreach ($queries as [$query, $level]) {
            $q = mb_strtolower($query);
            if (isset($tried[$q])) {
                continue;
            }
            if ($calls >= self::MAX_ATTEMPTS) {
                break;
            }
            $tried[$q] = true;
            if ($calls++ > 0) {
                usleep(self::SLEEP_MICROSECONDS);
            }
            if (($at = $this->lookup($query)) !== null) {
                return $at + ['level' => $level];
            }
        }

        return null;
    }
}
