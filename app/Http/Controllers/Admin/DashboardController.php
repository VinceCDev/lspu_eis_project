<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\DashboardStats;
use App\Models\GeocodeCache;
use App\Services\AlignmentService;
use App\Services\AlumniLocationViewer;
use App\Services\AlumniMapService;
use App\Services\Auth;
use App\Services\LocationGeocoder;
use App\Services\ReportingSummary;
use App\Support\HeavyCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

/** Ported from backend/Controllers/Admin/DashboardController.php. */
class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'title' => (Auth::role() === 'superadmin' ? 'Super Administrator Dashboard' : 'Administrator Dashboard').' | LSPU - EIS',
            'active' => Auth::role() === 'superadmin' ? 'superadmin_dashboard' : 'admin_dashboard',
            'pageJs' => 'admin_dashboard.js',
            'extraHead' => '<link rel="stylesheet" href="'.asset('assets/vendor/leaflet/leaflet.css').'">'
                .'<link rel="stylesheet" href="'.asset('assets/vendor/leaflet-markercluster/MarkerCluster.css').'">'
                .'<link rel="stylesheet" href="'.asset('assets/vendor/leaflet-markercluster/MarkerCluster.Default.css').'">'
                .'<link rel="stylesheet" href="'.asset('assets/css/alumni_map.css').'?v='.(@filemtime(public_path('assets/css/alumni_map.css')) ?: 0).'">',
            // Chart.js (~200 KB) and Leaflet (~148 KB) are NOT needed to
            // render the dashboard shell — they are lazy-loaded on demand by
            // admin_dashboard.js (via LibLoader) once the stats/map data is
            // ready, so the sidebar/header/icons paint without waiting for
            // them. Only the tiny per-request flag is inlined here.
            'extraScripts' => '<script>window.IS_SUPERADMIN = '.json_encode(Auth::role() === 'superadmin').';</script>'
                // Alumni Location map controller (Leaflet itself + markercluster stay lazy via LibLoader). Deferred, and
                // versioned by mtime like the page script so a deploy is never served from a stale browser cache.
                .'<script defer src="'.asset('assets/js/alumni_map.js').'?v='.(@filemtime(public_path('assets/js/alumni_map.js')) ?: 0).'"></script>',
        ]);
    }

    public function stats(): JsonResponse
    {
        $campusId = Auth::role() === 'superadmin' ? null : Auth::campusId();

        // Cache miss = build from the summary tables (milliseconds), not from a scan of the alumni tables (98 s at 1M).
        $payload = HeavyCache::remember(
            'dashboard_stats:'.($campusId ?? 'all'),
            (int) config('reporting.cache_fresh'),
            (int) config('reporting.cache_keep'),
            function () use ($campusId) {
                $stats = new DashboardStats($campusId);

                // The alumni location map is NOT part of this payload: it has its own cached, viewport-aware
                // endpoint (alumniMap) so the charts don't wait for it and it doesn't ride along in every stats fetch.
                return array_merge(
                    $stats->chartBreakdowns(),
                    $stats->totalsForCards()
                );
            },
            [ReportingSummary::scopeFor($campusId)]
        );

        return response()->json($payload);
    }

    public function courseWorkAlignment(): JsonResponse
    {
        $campusId = Auth::role() === 'superadmin' ? null : Auth::campusId();

        // Release the session lock before the (possibly slow) first build so
        // other requests from the same admin aren't blocked behind it.
        if (Session::isStarted()) {
            Session::save();
        }

        set_time_limit(180);

        try {
            $alignment = HeavyCache::remember(
                'dashboard_course_work_alignment:'.($campusId ?? 'all'),
                (int) config('reporting.cache_fresh'),
                (int) config('reporting.cache_keep'),
                fn () => (new AlignmentService())->classify((new DashboardStats($campusId))->currentCourseJobTitleCounts()),
                [ReportingSummary::scopeFor($campusId)]
            );

            return response()->json(['success' => true, 'course_work_alignment' => $alignment]);
        } catch (\Throwable $e) {
            error_log('courseWorkAlignment failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not load course-work alignment right now.']);
        }
    }

    public function employmentStatusByCampus(): JsonResponse
    {
        $isSuperadmin = Auth::role() === 'superadmin';
        $cacheKey = 'dashboard_employment_status_by_campus:'.($isSuperadmin ? 'all' : Auth::campusId());

        $data = HeavyCache::remember($cacheKey, (int) config('reporting.cache_fresh'), (int) config('reporting.cache_keep'), function () use ($isSuperadmin) {
            // C4: authorised campus list first, then ONE grouped pass for all
            // of them instead of a 4-query loop per campus.
            $campuses = $isSuperadmin
                ? (new Campus())->all()
                : array_values(array_filter((new Campus())->all(), fn ($c) => (int) $c['campus_id'] === Auth::campusId()));

            $campusIds = array_map(static fn ($c) => (int) $c['campus_id'], $campuses);
            if (empty($campusIds)) {
                return [];
            }

            $stats = new DashboardStats();
            $byCampusPrograms = $stats->employmentStatusPerProgramByCampus($campusIds);
            $byCampusColleges = $stats->collegesByCampus($campusIds);

            $out = [];
            foreach ($campuses as $campus) {
                $campusId = (int) $campus['campus_id'];
                $out[] = [
                    'campus_id' => $campusId,
                    'campus_name' => $campus['name'],
                    'employment_status_per_program' => $byCampusPrograms[$campusId] ?? [],
                    'colleges' => $byCampusColleges[$campusId] ?? [],
                ];
            }

            return $out;
        }, [ReportingSummary::scopeFor($isSuperadmin ? null : Auth::campusId())]);

        return response()->json(['success' => true, 'campuses' => $data]);
    }

    /**
     * C1: paginated alumni for a single map location, fetched on demand when
     * a marker popup is opened. Replaces shipping every located alumnus in
     * the `stats` payload.
     */
    public function alumniAtLocation(Request $request): JsonResponse
    {
        $campusId = Auth::role() === 'superadmin' ? null : Auth::campusId();
        $city = trim((string) $request->query('city', ''));
        $province = trim((string) $request->query('province', ''));

        if ($city === '' || $province === '') {
            return response()->json(['success' => false, 'message' => 'city and province are required.']);
        }

        $perPage = 20;
        $page = max(1, (int) $request->query('page', 1));

        $result = (new DashboardStats($campusId))
            ->alumniAtLocation($city, $province, $perPage, ($page - 1) * $perPage);

        return response()->json(['success' => true] + $result);
    }

    /**
     * Alumni Location map data for the visible viewport. Optional query: north, south, east, west (degrees) and zoom;
     * without bounds every mapped location is returned. Reads only cached data — never geocodes.
     */
    public function alumniMap(Request $request): JsonResponse
    {
        $campusId = Auth::role() === 'superadmin' ? null : Auth::campusId();

        $bounds = null;
        $b = array_map(static fn ($k) => $request->query($k), ['north', 'south', 'east', 'west']);
        if (count(array_filter($b, 'is_numeric')) === 4) {
            $bounds = ['north' => (float) $b[0], 'south' => (float) $b[1], 'east' => (float) $b[2], 'west' => (float) $b[3]];
        }
        $zoom = max(0, min(19, (int) $request->query('zoom', '10')));

        // Read-only endpoint: don't keep other requests from this admin waiting on the session lock.
        if (Session::isStarted()) {
            Session::save();
        }

        $dataset = (new AlumniMapService())->dataset($campusId);
        $slice = AlumniMapService::slice($dataset, $bounds, $zoom);

        return response()->json([
            'success' => true,
            'mode' => $slice['mode'],
            'in_view' => $slice['in_view'],
            'total_mapped' => count($dataset['locations']),
            'unmapped' => $dataset['unmapped'],
            'locations' => $slice['locations'],
        ]);
    }

    /**
     * One alumnus of a map location (0-based `index`), in a fixed order, plus the location's total; `course` narrows to
     * one program. See AlumniLocationViewer.
     */
    public function alumniViewer(Request $request): JsonResponse
    {
        $campusId = Auth::role() === 'superadmin' ? null : Auth::campusId();
        $city = trim((string) $request->query('city', ''));
        $province = trim((string) $request->query('province', ''));

        if ($city === '' || $province === '') {
            return response()->json(['success' => false, 'message' => 'city and province are required.']);
        }

        if (Session::isStarted()) {
            Session::save();
        }

        $result = (new AlumniLocationViewer())->at(
            $campusId,
            $city,
            $province,
            ($course = trim((string) $request->query('course', ''))) === '' ? null : $course,
            (int) $request->query('index', '0')
        );

        return response()->json(['success' => true] + $result);
    }

    public function collegeEmploymentStatus(Request $request): JsonResponse
    {
        $campusId = Auth::role() === 'superadmin'
            ? (int) $request->query('campus_id', '0')
            : Auth::campusId();
        $college = $request->query('college', '');

        if (!$campusId || $college === '') {
            return response()->json(['success' => false, 'message' => 'campus_id and college are required.']);
        }

        $campusName = null;
        foreach ((new Campus())->all() as $campus) {
            if ((int) $campus['campus_id'] === $campusId) {
                $campusName = $campus['name'];
                break;
            }
        }

        // was uncached: every college click re-ran three GROUP BY joins over the campus's alumni
        $perProgram = HeavyCache::remember(
            'dashboard_college_status:'.$campusId.':'.md5($college),
            (int) config('reporting.cache_fresh'),
            (int) config('reporting.cache_keep'),
            fn () => (new DashboardStats($campusId, $college))->employmentStatusPerProgram(),
            [ReportingSummary::scopeFor($campusId)]
        );

        return response()->json([
            'success' => true,
            'campus_id' => $campusId,
            'campus_name' => $campusName,
            'college' => $college,
            'employment_status_per_program' => $perProgram,
        ]);
    }

    /**
     * Server-side geocoding for the alumni location map, backed by a
     * permanent DB cache.
     */
    /**
     * Nominatim requires ~1 request/second and times out slowly, so a page
     * that just gained hundreds of never-seen locations (e.g. after a bulk
     * alumni import) would otherwise spend minutes here and 500. Cap the
     * live lookups per request; the rest stay uncached and fill in over
     * later loads. Obviously-not-a-place strings are skipped outright.
     */
    private const MAX_GEOCODE_LOOKUPS = 8;

    public function geocode(Request $request): JsonResponse
    {
        $locations = array_values(array_unique(array_filter(array_map('trim', $request->input('locations', [])))));

        if (empty($locations)) {
            return response()->json(['success' => true, 'coordinates' => []]);
        }

        try {
            $cache = new GeocodeCache();
            $coordinates = $cache->getMany($locations);

            $geocoder = new LocationGeocoder();
            $missing = array_values(array_filter(
                array_diff($locations, array_keys($coordinates)),
                fn (string $loc) => $geocoder->looksLikePlace($loc)
            ));

            if (Session::isStarted()) {
                Session::save();
            }

            $done = 0;
            foreach ($missing as $location) {
                if ($done >= self::MAX_GEOCODE_LOOKUPS) {
                    break;
                }
                $coords = $geocoder->lookup($location);
                $done++;
                if ($coords !== null) {
                    $cache->set($location, $coords['lat'], $coords['lng']);
                    $coordinates[$location] = $coords;
                }
                usleep(LocationGeocoder::SLEEP_MICROSECONDS);
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => true, 'coordinates' => $coordinates ?? []]);
        }

        return response()->json(['success' => true, 'coordinates' => $coordinates]);
    }
}
