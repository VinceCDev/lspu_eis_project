<?php

namespace App\Services;

use App\Concerns\LegacyQueries;
use App\Models\AlignmentCache;
use App\Support\HeavyCache;
use Illuminate\Support\Facades\DB;

/**
 * One alumnus at a time for the Dashboard map's "View alumni" browser.
 *
 * The map popup already knows how many alumni a location has; this returns the
 * Nth of them (0-based index) in a fixed order, with the full detail the popup
 * used to show for every alumnus, so the UI can step Next/Previous without
 * ever shipping the whole location. The set matches DashboardStats::alumniMap()
 * (same city/province, same campus scope); an optional course narrows it to one
 * program, exactly like the per-course lines in the popup.
 *
 * Order: last name, first name, then alumni_id as a tiebreak so the sequence
 * can never wobble between requests (the previous list used name order only).
 *
 * How it stays fast at any size: sorting a big location (measured: 77k alumni in one city at 1M rows) costs 100-300 ms,
 * so it is done ONCE per (campus, location, program) and the ordered alumni_ids are cached as a packed binary string
 * (4 bytes each). "The Nth alumnus" is then a constant-time slice of that string plus one primary-key lookup, and the
 * sequence cannot shift under the user while an import inserts rows; the list is refreshed when it goes stale or the
 * campus's imports finish (HeavyCache scopes). A person deleted after the list was cached simply reads as "no longer
 * available".
 */
class AlumniLocationViewer
{
    use LegacyQueries;

    /** Seconds a cached ordering is served untouched / may still be served while it is rebuilt. */
    private const LIST_FRESH = 300;
    private const LIST_KEEP = 1800;

    /**
     * @return array{total: int, index: int, alumnus: array<string, mixed>|null}
     */
    public function at(?int $campusId, string $city, string $province, ?string $course, int $index): array
    {
        $index = max(0, $index);
        $ids = base64_decode($this->orderedIds($campusId, $city, $province, $course), true) ?: '';
        $total = intdiv(strlen($ids), 4);

        $row = null;
        if ($index < $total) {
            $id = unpack('V', $ids, $index * 4)[1];
            $row = $this->selectOne(
                'SELECT a.alumni_id, a.first_name, a.middle_name, a.last_name, a.profile_pic, a.course, a.college, a.year_graduated
                 FROM alumni a WHERE a.alumni_id = ?',
                [$id]
            );
        }

        return ['total' => $total, 'index' => $index, 'alumnus' => $row ? $this->detail($row) : null];
    }

    /** base64 of the packed little-endian uint32 alumni_ids of the location, in viewing order. */
    private function orderedIds(?int $campusId, string $city, string $province, ?string $course): string
    {
        [$where, $params] = $this->scope($campusId, $city, $province, $course);

        return HeavyCache::remember(
            'alumni_viewer_ids:'.md5(json_encode([$campusId, $city, $province, $course])),
            self::LIST_FRESH,
            self::LIST_KEEP,
            static function () use ($where, $params): string {
                $ids = DB::table('alumni as a')
                    ->whereRaw($where, $params)
                    ->orderBy('a.last_name')->orderBy('a.first_name')->orderBy('a.alumni_id')
                    ->pluck('a.alumni_id')
                    ->all();

                return $ids ? base64_encode(pack('V*', ...$ids)) : '';
            },
            [ReportingSummary::scopeFor($campusId)]
        );
    }

    /** @return array{0: string, 1: array<int, mixed>} */
    private function scope(?int $campusId, string $city, string $province, ?string $course): array
    {
        $where = 'a.city = ? AND a.province = ?';
        $params = [$city, $province];
        if ($course !== null && $course !== '') {
            $where .= ' AND a.course = ?';
            $params[] = $course;
        }
        if ($campusId !== null) {
            $where .= ' AND a.campus_id = ?';
            $params[] = $campusId;
        }

        return [$where, $params];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function detail(array $row): array
    {
        // Same "currently employed" predicate as DashboardStats::alumniMap() / alumniAtLocation().
        $exp = $this->selectOne(
            'SELECT title, company, start_date, end_date, description, employment_status, employment_sector, location_of_work
             FROM alumni_experience
             WHERE alumni_id = ? AND (current = 1 OR end_date IS NULL OR end_date >= CURDATE())
             ORDER BY start_date DESC
             LIMIT 1',
            [(int) $row['alumni_id']]
        );

        $work = null;
        if ($exp) {
            // Cache-only lookup: never calls the AI classifier from a map click.
            $relevance = ($row['course'] && $exp['title'])
                ? (new AlignmentCache())->get((string) $row['course'], (string) $exp['title'])
                : null;

            $work = [
                'title' => $exp['title'],
                'company' => $exp['company'],
                'start_date' => $exp['start_date'],
                'end_date' => $exp['end_date'],
                'description' => $exp['description'],
                'employment_status' => $exp['employment_status'],
                'sector' => $exp['employment_sector'],
                'location_of_work' => $exp['location_of_work'],
                'relevance' => $relevance,
            ];
        }

        return [
            'alumni_id' => (int) $row['alumni_id'],
            // collapse the gap left by an empty middle name
            'name' => trim(preg_replace('/\s+/', ' ', $row['first_name'].' '.$row['middle_name'].' '.$row['last_name'])),
            'profile_pic' => $row['profile_pic'] ? 'uploads/profile_picture/'.$row['profile_pic'] : null,
            'course' => $row['course'],
            'college' => $row['college'],
            'year_graduated' => $row['year_graduated'],
            'status' => $exp ? 'Employed' : 'Unemployed',
            'work_details' => $work,
        ];
    }
}
