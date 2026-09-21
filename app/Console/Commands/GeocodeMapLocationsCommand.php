<?php

namespace App\Console\Commands;

use App\Models\Campus;
use App\Models\DashboardStats;
use App\Models\GeocodeCache;
use App\Services\LocationGeocoder;
use App\Services\ReportingSummary;
use App\Support\HeavyCache;
use Illuminate\Console\Command;

/**
 * Fills location_geocode_cache for alumni locations that have no coordinates yet, so the Dashboard map never has to
 * geocode while a user waits (the old path did up to 8 sequential Nominatim calls inside the page's request).
 *
 * Most-populated locations first, place-like strings only, at most --limit lookups per run at Nominatim's 1 req/s.
 * Scheduled hourly (routes/console.php); safe to run by hand and to re-run.
 */
class GeocodeMapLocationsCommand extends Command
{
    protected $signature = 'map:geocode-locations {--limit=20 : Max Nominatim lookups this run}';

    protected $description = 'Geocode alumni locations missing from the map cache (background; the Dashboard never does this itself).';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $geocoder = new LocationGeocoder();
        $cache = new GeocodeCache();

        // alumniMap() returns clusters keyed "City, Province" with their alumni count.
        $clusters = (new DashboardStats(null))->alumniMap();
        $known = $cache->getMany(array_keys($clusters));

        $todo = [];
        foreach ($clusters as $key => $c) {
            if (!isset($known[$key]) && $geocoder->looksLikePlace($key)) {
                $todo[$key] = $c['count'];
            }
        }
        arsort($todo);

        $this->info(count($todo).' place-like location(s) without coordinates; geocoding up to '.$limit.'.');

        $found = 0;
        $tried = 0;
        foreach (array_keys($todo) as $key) {
            if ($tried >= $limit) {
                break;
            }
            $coords = $geocoder->lookup($key);
            $tried++;
            if ($coords !== null) {
                $cache->set($key, $coords['lat'], $coords['lng']);
                $found++;
                $this->line("  + {$key}  ({$coords['lat']}, {$coords['lng']})");
            } else {
                $this->line("  - {$key}  (not found)");
            }
            usleep(LocationGeocoder::SLEEP_MICROSECONDS);
        }

        if ($found > 0) {
            // New pins => the cached map payloads (every campus scope + "all") are out of date.
            $scopes = [ReportingSummary::scopeFor(null)];
            foreach ((new Campus())->all() as $campus) {
                $scopes[] = ReportingSummary::scopeFor((int) $campus['campus_id']);
            }
            HeavyCache::markStale($scopes);
        }

        $this->info("Done: {$found}/{$tried} geocoded.");

        return self::SUCCESS;
    }
}
