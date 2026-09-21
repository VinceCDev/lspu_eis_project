<?php

namespace App\Services;

use App\Models\DashboardStats;
use App\Models\GeocodeCache;
use App\Support\HeavyCache;
use Illuminate\Support\Facades\DB;

/**
 * Data behind the Dashboard's Alumni Location map.
 *
 * A map "pin" is one (city, province) location — never one alumnus — so the
 * number of pins is bounded by the number of distinct, geocoded locations, not
 * by the alumni count. The per-location summaries are built once (from the
 * reporting summary tables when they are ready, else the live GROUP BY), joined
 * with the permanent geocode cache, and held in HeavyCache. Each map request
 * then only slices that cached list by the visible bounds (cheap array filter,
 * no database work), and if a very wide view would still contain more points
 * than the browser should draw, they are merged into grid cells server-side.
 *
 * Nothing here geocodes: locations without a cached coordinate are counted as
 * "unmapped" instead of being drawn on a made-up point.
 */
class AlumniMapService
{
    /** More points than this inside the requested bounds => return grid cells instead. */
    public const MAX_POINTS = 1500;

    /**
     * @return array{locations: array<int, array<string, mixed>>, unmapped: array{locations: int, alumni: int}}
     */
    public function dataset(?int $campusId): array
    {
        $scope = ReportingSummary::scopeFor($campusId);

        return HeavyCache::remember(
            'dashboard_alumni_map:'.($campusId ?? 'all'),
            (int) config('reporting.cache_fresh'),
            (int) config('reporting.cache_keep'),
            fn () => $this->build($campusId),
            [$scope]
        );
    }

    /**
     * @return array{locations: array<int, array<string, mixed>>, unmapped: array{locations: int, alumni: int}}
     */
    private function build(?int $campusId): array
    {
        return ReportingSummary::ready($campusId) ? $this->buildFromSummary($campusId) : $this->buildLive($campusId);
    }

    /**
     * Summary tables ready (the production path). ONE grouped pass over rpt_location, streamed row by row, so memory
     * depends on the number of pins - not on the number of distinct free-text addresses (measured: ~100k distinct
     * "cities" at 1M alumni, almost none of them geocoded, which as PHP arrays cost ~220 MB). The geocode cache is
     * joined by the database; the join is case-insensitive like the GROUP BY that formed the locations, so
     * "SAN PABLO CITY" and "San Pablo City" find the same cached coordinate.
     */
    private function buildFromSummary(?int $campusId): array
    {
        $w = $campusId !== null ? ' AND l.campus_id = ?' : '';
        $bind = $campusId !== null ? [$campusId] : [];

        $rows = DB::cursor(
            "SELECT l.city, l.province, l.course, SUM(l.alumni_count) AS n, SUM(l.employed_count) AS employed, MAX(g.lat) AS lat, MAX(g.lng) AS lng
             FROM rpt_location l
             LEFT JOIN location_geocode_cache g ON g.location_key = CONCAT(l.city, ', ', l.province)
             WHERE 1 = 1{$w}
             GROUP BY l.city, l.province, l.course
             ORDER BY l.city, l.province",
            $bind
        );

        $byLocation = [];
        $locationsSeen = 0;
        $unmappedAlumni = 0;
        $lastKey = null;
        foreach ($rows as $r) {
            $key = mb_strtolower($r->city.'|'.$r->province);
            if ($key !== $lastKey) {
                $locationsSeen++;   // rows arrive ordered by location, so a change of key is a new location
                $lastKey = $key;
            }
            if ($r->lat === null) {
                $unmappedAlumni += (int) $r->n;

                continue;
            }
            $byLocation[$key] ??= [
                'city' => $r->city, 'province' => $r->province,
                'lat' => round((float) $r->lat, 5), 'lng' => round((float) $r->lng, 5),
                'count' => 0, 'employed' => 0, '_courses' => [],
            ];
            $byLocation[$key]['count'] += (int) $r->n;
            $byLocation[$key]['employed'] += (int) $r->employed;
            if ($r->course !== null && $r->course !== '') {
                $byLocation[$key]['_courses'][$r->course] = (int) $r->n;
            }
        }
        $locations = array_map(static fn (array $l) => self::withTopCourses($l), array_values($byLocation));

        return [
            'locations' => $locations,
            'unmapped' => ['locations' => max(0, $locationsSeen - count($locations)), 'alumni' => $unmappedAlumni],
        ];
    }

    /** No summary tables yet (fresh install / rollback switch): the original live GROUP BY, then a PHP join. */
    private function buildLive(?int $campusId): array
    {
        $clusters = (new DashboardStats($campusId))->alumniMap();
        $coords = [];
        foreach ((new GeocodeCache())->getMany(array_keys($clusters)) as $key => $c) {
            $coords[mb_strtolower($key)] = $c;   // the database matches case-insensitively; so must this lookup
        }

        $locations = [];
        $unmappedLocations = 0;
        $unmappedAlumni = 0;
        foreach ($clusters as $key => $c) {
            $at = $coords[mb_strtolower($key)] ?? null;
            if ($at === null) {
                $unmappedLocations++;
                $unmappedAlumni += $c['count'];

                continue;
            }
            $locations[] = [
                'city' => $c['city'],
                'province' => $c['province'],
                'lat' => round($at['lat'], 5),
                'lng' => round($at['lng'], 5),
                'count' => $c['count'],
                'employed' => $c['employed'],
                'top_courses' => $c['top_courses'],
            ];
        }

        return ['locations' => $locations, 'unmapped' => ['locations' => $unmappedLocations, 'alumni' => $unmappedAlumni]];
    }

    /**
     * @param  array<string, mixed>  $location  with a '_courses' course => count map
     * @return array<string, mixed>
     */
    private static function withTopCourses(array $location): array
    {
        arsort($location['_courses']);
        $top = [];
        foreach (array_slice($location['_courses'], 0, 3, true) as $course => $n) {
            $top[] = ['course' => $course, 'count' => $n];
        }
        unset($location['_courses']);
        $location['top_courses'] = $top;

        return $location;
    }

    /**
     * Slice a dataset to the visible bounds and, when the slice is still too big to draw, merge it into grid cells.
     *
     * @param  array{locations: array<int, array<string, mixed>>, unmapped: array{locations: int, alumni: int}}  $dataset
     * @param  array{north: float, south: float, east: float, west: float}|null  $bounds  null = everything
     * @return array{mode: string, in_view: int, locations: array<int, array<string, mixed>>}
     */
    public static function slice(array $dataset, ?array $bounds, int $zoom, int $maxPoints = self::MAX_POINTS): array
    {
        $points = $dataset['locations'];
        if ($bounds !== null) {
            $points = array_values(array_filter($points, static fn (array $p) => $p['lat'] <= $bounds['north']
                && $p['lat'] >= $bounds['south']
                && $p['lng'] <= $bounds['east']
                && $p['lng'] >= $bounds['west']));
        }

        if (count($points) <= $maxPoints) {
            return ['mode' => 'points', 'in_view' => count($points), 'locations' => $points];
        }

        return ['mode' => 'aggregate', 'in_view' => count($points), 'locations' => self::aggregate($points, $zoom)];
    }

    /**
     * Merge points into square cells whose size shrinks as the zoom grows, so zooming in splits cells apart until the
     * slice is small enough to be sent as individual locations.
     *
     * @param  array<int, array<string, mixed>>  $points
     * @return array<int, array{lat: float, lng: float, count: int, employed: int, locations: int}>
     */
    public static function aggregate(array $points, int $zoom): array
    {
        // ~64 screen pixels per cell at a 256px tile: 360 degrees / (256 * 2^zoom) * 64
        $cell = 360 / (2 ** max(0, min(19, $zoom))) * 0.25;
        $cells = [];
        foreach ($points as $p) {
            $k = floor($p['lat'] / $cell).':'.floor($p['lng'] / $cell);
            $cells[$k] ??= ['lat' => 0.0, 'lng' => 0.0, 'count' => 0, 'employed' => 0, 'locations' => 0];
            $w = max(1, (int) $p['count']);
            // count-weighted centroid, accumulated as sums and divided once below
            $cells[$k]['lat'] += $p['lat'] * $w;
            $cells[$k]['lng'] += $p['lng'] * $w;
            $cells[$k]['count'] += $w;
            $cells[$k]['employed'] += (int) $p['employed'];
            $cells[$k]['locations']++;
        }

        $out = [];
        foreach ($cells as $c) {
            $out[] = [
                'lat' => round($c['lat'] / $c['count'], 5),
                'lng' => round($c['lng'] / $c['count'], 5),
                'count' => $c['count'],
                'employed' => $c['employed'],
                'locations' => $c['locations'],
            ];
        }

        return $out;
    }
}
