<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/GeocodeCache.php — see User.php's docblock for the porting approach. */
class GeocodeCache
{
    use LegacyQueries;

    /** @return array<string, array{lat: float, lng: float}> keyed by location_key, for whichever of $keys are already cached */
    public function getMany(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        // Chunked: at bulk-import scale the free-text city column yields well over PDO's 65,535-placeholder limit of
        // distinct "City, Province" keys, and one giant IN() would fail outright.
        $out = [];
        foreach (array_chunk(array_values($keys), 5000) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            foreach ($this->selectAll("SELECT location_key, lat, lng FROM location_geocode_cache WHERE location_key IN ({$placeholders})", $chunk) as $row) {
                $out[$row['location_key']] = ['lat' => (float) $row['lat'], 'lng' => (float) $row['lng']];
            }
        }

        return $out;
    }

    public function set(string $locationKey, float $lat, float $lng): void
    {
        $this->insert('INSERT INTO location_geocode_cache (location_key, lat, lng) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE lat = VALUES(lat), lng = VALUES(lng)', [$locationKey, $lat, $lng]);
    }
}
