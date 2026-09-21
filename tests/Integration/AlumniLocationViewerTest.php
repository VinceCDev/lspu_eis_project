<?php

namespace Tests\Integration;

use App\Models\DashboardStats;
use App\Services\AlumniLocationViewer;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The map's one-alumnus-at-a-time viewer against the real configured database: same set, count and employment status
 * as the map popup's summary and the previous paginated list; stable order; boundaries; program filter; campus scope.
 * Fixtures live in a made-up location ("Zzmap City"/"Zzmap Province") and are removed in tearDown().
 */
class AlumniLocationViewerTest extends TestCase
{
    private const CITY = 'Zzmap City';
    private const PROVINCE = 'Zzmap Province';

    private string $run;
    private int $campusA;
    private int $campusB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->run = bin2hex(random_bytes(3));
        $this->campusA = (int) DB::table('campus')->insertGetId(['name' => 'ZZ Map A '.$this->run, 'type' => 'Regular']);
        $this->campusB = (int) DB::table('campus')->insertGetId(['name' => 'ZZ Map B '.$this->run, 'type' => 'Regular']);
    }

    protected function tearDown(): void
    {
        $ids = DB::table('user')->where('email', 'like', 'zzmap.'.$this->run.'.%')->pluck('user_id')->all();
        $alumniIds = DB::table('alumni')->whereIn('user_id', $ids)->pluck('alumni_id')->all();
        DB::table('alumni_experience')->whereIn('alumni_id', $alumniIds)->delete();
        DB::table('alumni')->whereIn('user_id', $ids)->delete();
        DB::table('user')->whereIn('user_id', $ids)->delete();
        DB::table('campus')->whereIn('campus_id', [$this->campusA, $this->campusB])->delete();
        parent::tearDown();
    }

    /** @return int alumni_id */
    private function alumnus(string $first, string $last, string $course, int $campusId, bool $employed = false): int
    {
        static $n = 0;
        $n++;
        $userId = (int) DB::table('user')->insertGetId([
            'email' => "zzmap.{$this->run}.{$n}@example.test",
            'password' => 'x',
            'user_role' => 'alumni',
            'status' => 'Active',
        ]);
        $alumniId = (int) DB::table('alumni')->insertGetId([
            'user_id' => $userId, 'first_name' => $first, 'last_name' => $last, 'contact' => '0', 'gender' => 'F',
            'civil_status' => 'Single', 'city' => self::CITY, 'province' => self::PROVINCE, 'college' => 'College of Zz',
            'course' => $course, 'verification_document' => 'x', 'campus_id' => $campusId, 'year_graduated' => 2023,
        ]);
        if ($employed) {
            DB::table('alumni_experience')->insert([
                'alumni_id' => $alumniId, 'title' => 'Teacher', 'company' => 'Zz School', 'start_date' => '2023-01-01',
                'current' => 1, 'employment_sector' => 'Education',
            ]);
        }

        return $alumniId;
    }

    /** @return array<int, array<string, mixed>> every alumnus at the fixture location, via the viewer, in order */
    private function walk(?int $campusId, ?string $course = null): array
    {
        $viewer = new AlumniLocationViewer();
        $out = [];
        for ($i = 0; $i < 50; $i++) {
            $r = $viewer->at($campusId, self::CITY, self::PROVINCE, $course, $i);
            if ($r['alumnus'] === null) {
                break;
            }
            $out[] = $r['alumnus'];
        }

        return $out;
    }

    public function testWalksEveryAlumnusOnceInNameOrderWithATiebreak(): void
    {
        $c = $this->alumnus('Zed', 'Cruz', 'BS Zz', $this->campusA);
        $a2 = $this->alumnus('Ana', 'Abad', 'BS Zz', $this->campusA);
        $a1 = $this->alumnus('Ana', 'Abad', 'BS Zz', $this->campusA);   // same name: alumni_id decides
        $b = $this->alumnus('Ben', 'Abad', 'BS Yy', $this->campusA);

        $ids = array_column($this->walk(null), 'alumni_id');

        $this->assertSame([$a2, $a1, $b, $c], $ids);        // Abad Ana, Abad Ana (by id), Abad Ben, Cruz Zed
        $this->assertCount(4, array_unique($ids));            // never repeats, never skips
        $this->assertSame($ids, array_column($this->walk(null), 'alumni_id'), 'the sequence is identical on every walk');
    }

    public function testTheSequenceStaysStableWhileNewAlumniAreInserted(): void
    {
        $b = $this->alumnus('Ben', 'Baca', 'BS Zz', $this->campusA);
        $c = $this->alumnus('Cai', 'Cruz', 'BS Zz', $this->campusA);
        $viewer = new AlumniLocationViewer();
        $this->assertSame($b, $viewer->at(null, self::CITY, self::PROVINCE, null, 0)['alumnus']['alumni_id']);

        // an import adds someone who sorts BEFORE everybody: a live OFFSET query would now return them at index 0
        // and shift Ben/Cai down by one (a repeat or a skip while the user is clicking Next)
        $this->alumnus('Aaa', 'Abad', 'BS Zz', $this->campusA);

        $this->assertSame($b, $viewer->at(null, self::CITY, self::PROVINCE, null, 0)['alumnus']['alumni_id']);
        $this->assertSame($c, $viewer->at(null, self::CITY, self::PROVINCE, null, 1)['alumnus']['alumni_id']);
        $this->assertSame(2, $viewer->at(null, self::CITY, self::PROVINCE, null, 1)['total']);
    }

    public function testTheCachedOrderingIsPlainTextSoTheDatabaseCacheDriverCanStoreIt(): void
    {
        // production runs CACHE_STORE=database (text column): a raw-binary value fails there with "Incorrect string value"
        $this->alumnus('Ana', 'Abad', 'BS Zz', $this->campusA);
        (new AlumniLocationViewer())->at(null, self::CITY, self::PROVINCE, null, 0);

        $cached = \Illuminate\Support\Facades\Cache::get('alumni_viewer_ids:'.md5(json_encode([null, self::CITY, self::PROVINCE, null])));

        $this->assertIsString($cached);
        $this->assertTrue(mb_check_encoding($cached, 'UTF-8'));
        $this->assertSame($cached, preg_replace('/[^A-Za-z0-9+\/=]/', '', $cached), 'base64 alphabet only');
    }

    public function testTotalIsAlwaysReturnedAndPastTheEndIsNull(): void
    {
        $this->alumnus('Ana', 'Abad', 'BS Zz', $this->campusA);
        $this->alumnus('Ben', 'Baca', 'BS Zz', $this->campusA);
        $viewer = new AlumniLocationViewer();

        $this->assertSame(2, $viewer->at(null, self::CITY, self::PROVINCE, null, 0)['total']);
        $this->assertSame(2, $viewer->at(null, self::CITY, self::PROVINCE, null, 1)['total']);
        $this->assertNotNull($viewer->at(null, self::CITY, self::PROVINCE, null, 1)['alumnus']);
        $this->assertNull($viewer->at(null, self::CITY, self::PROVINCE, null, 2)['alumnus'], 'index == total is past the end');
        $this->assertNull($viewer->at(null, self::CITY, self::PROVINCE, null, 999)['alumnus']);
        $this->assertSame(0, $viewer->at(null, self::CITY, self::PROVINCE, null, -5)['index'], 'a negative index is clamped');
    }

    public function testProgramFilterNarrowsTheSet(): void
    {
        $this->alumnus('Ana', 'Abad', 'BS Zz', $this->campusA);
        $this->alumnus('Ben', 'Baca', 'BS Yy', $this->campusA);
        $this->alumnus('Cai', 'Cruz', 'BS Zz', $this->campusA);

        $viewer = new AlumniLocationViewer();
        $this->assertSame(2, $viewer->at(null, self::CITY, self::PROVINCE, 'BS Zz', 0)['total']);
        $this->assertSame(1, $viewer->at(null, self::CITY, self::PROVINCE, 'BS Yy', 0)['total']);
        $this->assertSame(['Ana Abad', 'Cai Cruz'], array_column($this->walk(null, 'BS Zz'), 'name'));
    }

    public function testAdminCampusScopeHidesOtherCampuses(): void
    {
        $mine = $this->alumnus('Ana', 'Abad', 'BS Zz', $this->campusA);
        $this->alumnus('Ben', 'Baca', 'BS Zz', $this->campusB);

        $this->assertSame([$mine], array_column($this->walk($this->campusA), 'alumni_id'));
        $this->assertCount(2, $this->walk(null), 'superadmin (no campus) sees both');
    }

    public function testMatchesTheMapSummaryAndThePreviousPaginatedList(): void
    {
        $this->alumnus('Ana', 'Abad', 'BS Zz', $this->campusA, true);
        $this->alumnus('Ben', 'Baca', 'BS Zz', $this->campusA, false);
        $this->alumnus('Cai', 'Cruz', 'BS Yy', $this->campusA, true);

        $stats = new DashboardStats($this->campusA);
        $cluster = $stats->alumniMap()[self::CITY.', '.self::PROVINCE];
        $old = $stats->alumniAtLocation(self::CITY, self::PROVINCE, 20, 0);
        $walk = $this->walk($this->campusA);

        // same alumni, same order, same details the popup list showed
        $this->assertSame($cluster['count'], count($walk));
        $this->assertSame($old['total'], count($walk));
        // (the old list left a double space when there was no middle name; the viewer collapses it)
        $this->assertSame(
            array_map(fn ($n) => preg_replace('/\s+/', ' ', $n), array_column($old['alumni'], 'name')),
            array_column($walk, 'name')
        );
        $this->assertSame(array_column($old['alumni'], 'status'), array_column($walk, 'status'));
        $this->assertSame(array_column($old['alumni'], 'course'), array_column($walk, 'course'));
        // ...and the employed count in the summary equals the number of "Employed" alumni the viewer shows
        $this->assertSame($cluster['employed'], count(array_filter($walk, fn ($a) => $a['status'] === 'Employed')));

        $employed = array_values(array_filter($walk, fn ($a) => $a['status'] === 'Employed'))[0];
        $this->assertSame('Teacher', $employed['work_details']['title']);
        $this->assertSame('Zz School', $employed['work_details']['company']);
        $this->assertSame('Education', $employed['work_details']['sector']);
    }
}
