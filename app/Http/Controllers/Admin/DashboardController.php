<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\DashboardStats;
use App\Models\GeocodeCache;
use App\Models\Report;
use App\Services\AlignmentService;
use App\Services\Auth;
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
            'extraHead' => '<link rel="stylesheet" href="'.asset('assets/vendor/leaflet/leaflet.css').'">',
            'extraScripts' => '<script src="'.asset('assets/vendor/leaflet/leaflet.js').'"></script>'
                .'<script src="'.asset('assets/vendor/chartjs/chart.min.js').'"></script>'
                .'<script>window.IS_SUPERADMIN = '.json_encode(Auth::role() === 'superadmin').';</script>',
        ]);
    }

    public function stats(): JsonResponse
    {
        $campusId = Auth::role() === 'superadmin' ? null : Auth::campusId();

        $payload = Cache::remember(
            'dashboard_stats:'.($campusId ?? 'all'),
            30,
            function () use ($campusId) {
                $stats = new DashboardStats($campusId);

                return array_merge(
                    $stats->chartBreakdowns(),
                    $stats->totalsForCards(),
                    [
                        'alumni_map' => $stats->alumniMap(),
                    ]
                );
            }
        );

        return response()->json($payload);
    }

    public function courseWorkAlignment(): JsonResponse
    {
        $campusId = Auth::role() === 'superadmin' ? null : Auth::campusId();
        $stats = new DashboardStats($campusId);

        if (Session::isStarted()) {
            Session::save();
        }

        set_time_limit(180);

        try {
            $alignment = (new AlignmentService())->classify($stats->currentCourseJobTitles());

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

        $data = Cache::remember($cacheKey, 60, function () use ($isSuperadmin) {
            $campuses = $isSuperadmin
                ? (new Campus())->all()
                : array_filter((new Campus())->all(), fn ($c) => (int) $c['campus_id'] === Auth::campusId());

            $out = [];
            foreach ($campuses as $campus) {
                $campusId = (int) $campus['campus_id'];
                $out[] = [
                    'campus_id' => $campusId,
                    'campus_name' => $campus['name'],
                    'employment_status_per_program' => (new DashboardStats($campusId))->employmentStatusPerProgram(),
                    'colleges' => (new Report($campusId))->distinctColleges(),
                ];
            }

            return $out;
        });

        return response()->json(['success' => true, 'campuses' => $data]);
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

        return response()->json([
            'success' => true,
            'campus_id' => $campusId,
            'campus_name' => $campusName,
            'college' => $college,
            'employment_status_per_program' => (new DashboardStats($campusId, $college))->employmentStatusPerProgram(),
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

            $missing = array_values(array_filter(
                array_diff($locations, array_keys($coordinates)),
                fn (string $loc) => $this->looksLikePlace($loc)
            ));

            if (Session::isStarted()) {
                Session::save();
            }

            $done = 0;
            foreach ($missing as $location) {
                if ($done >= self::MAX_GEOCODE_LOOKUPS) {
                    break;
                }
                $coords = $this->geocodeViaNominatim($location);
                $done++;
                if ($coords !== null) {
                    $cache->set($location, $coords['lat'], $coords['lng']);
                    $coordinates[$location] = $coords;
                }
                usleep(1100000);
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => true, 'coordinates' => $coordinates ?? []]);
        }

        return response()->json(['success' => true, 'coordinates' => $coordinates]);
    }

    /** Cheap filter: a real "City, Province" has no house numbers, street or barangay tokens. */
    private function looksLikePlace(string $s): bool
    {
        if ($s === '' || mb_strlen($s) > 80 || preg_match('/\d/', $s)) {
            return false;
        }

        return !preg_match('/\b(brgy|barangay|purok|sitio|blk|block|lot|phase|st\.?|street|ave|avenue|subd|subdivision|#)\b/i', $s);
    }

    /** @return array{lat: float, lng: float}|null */
    private function geocodeViaNominatim(string $location): ?array
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
