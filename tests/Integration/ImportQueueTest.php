<?php

namespace Tests\Integration;

use App\Models\ImportJob;
use App\Services\EmploymentReportImporter;
use App\Services\ImportAborted;
use App\Services\ReportingSummary;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Queue-import safety properties, against the real configured database (needs the 2026_09_22 migration applied):
 *   - a run that dies / loses its lease mid-import can be resumed from its checkpoint with no lost and no duplicated rows;
 *   - even a resume from a STALE checkpoint (chunks already committed) creates no duplicates;
 *   - at most import.max_concurrent imports are "processing"; the others wait, in FIFO order;
 *   - a worker that no longer owns an import cannot write to it (and its chunk rolls back).
 * Fixtures carry the tag "zzqueue" and are removed in tearDown().
 */
class ImportQueueTest extends TestCase
{
    private const TAG = 'zzqueue';

    private int $campusId;
    private string $run;
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['import.chunk_size' => 100]);
        $this->run = bin2hex(random_bytes(3));
        $this->campusId = (int) DB::table('campus')->insertGetId(['name' => 'ZZ Queue Test '.$this->run, 'type' => 'Regular']);
        DB::table('import_jobs')->where('filename', 'like', self::TAG.'%')->delete();
    }

    protected function tearDown(): void
    {
        $ids = DB::table('user')->where('email', 'like', self::TAG.'.'.$this->run.'.%')->pluck('user_id')->all();
        foreach (array_chunk($ids, 500) as $chunk) {
            DB::table('alumni_experience')->whereIn('alumni_id', DB::table('alumni')->whereIn('user_id', $chunk)->pluck('alumni_id'))->delete();
            DB::table('alumni_education')->whereIn('alumni_id', DB::table('alumni')->whereIn('user_id', $chunk)->pluck('alumni_id'))->delete();
            DB::table('alumni')->whereIn('user_id', $chunk)->delete();
            DB::table('user')->whereIn('user_id', $chunk)->delete();
        }
        DB::table('rpt_state')->where('campus_id', $this->campusId)->delete();
        foreach (['rpt_program', 'rpt_metric', 'rpt_title', 'rpt_title_all', 'rpt_location'] as $t) {
            DB::table($t)->where('campus_id', $this->campusId)->delete();
        }
        DB::table('campus')->where('campus_id', $this->campusId)->delete();
        DB::table('import_jobs')->where('filename', 'like', self::TAG.'%')->delete();
        foreach ($this->files as $f) {
            @unlink($f);
        }
        ReportingSummary::flushMemo();
        parent::tearDown();
    }

    /** A workbook in the layout the importer reads: header row, sub-header row, then $rows graduates. */
    private function workbook(int $rows, int $startAt = 1): string
    {
        $ss = new Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('CCS');
        $sh->fromArray(['LSPU Data on Employment'], null, 'A1');
        $sh->fromArray(['No.', 'Program Name', 'Name of Graduates', 'Gender', 'Date of Graduation', 'Date Hired', 'Status of Employment After Graduation',
            'Sector', 'Location of Employment', 'Average Monthly Income', 'Company & Position', 'Type of Industry', 'Company Address', 'Employed-Aligned',
            'Contact Number', 'E-mail'], null, 'A2');
        $sh->fromArray(array_fill(0, 16, ''), null, 'A3');
        for ($i = 0; $i < $rows; ++$i) {
            $n = $startAt + $i;
            $sh->fromArray([
                $n, 'BSIT', sprintf('DELA CRUZ%d, JUAN %d', $n, $n), $n % 2 ? 'M' : 'F', 'June 1, 2023', 'July 1, 2023', $n % 5 === 0 ? 'Unemployed' : 'Regular',
                'Private', 'Local', '20000', "ACME Inc./Developer {$n}", 'IT', 'Calamba, Laguna', 'Yes', '09171234567', self::TAG.'.'.$this->run.'.'.$n.'@example.test',
            ], null, 'A'.(3 + 1 + $i));
        }
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.self::TAG.'_'.$this->run.'_'.$rows.'_'.$startAt.'.xlsx';
        (new Xlsx($ss))->save($path);
        $this->files[] = $path;

        return $path;
    }

    private function importedCount(): int
    {
        return (int) DB::table('alumni')->where('campus_id', $this->campusId)->count();
    }

    public function testRunThatDiesMidImportResumesWithoutLosingOrDuplicatingRows(): void
    {
        $file = $this->workbook(450);   // 5 chunks of 100

        // Run 1: the worker "dies" (lease lost) when the 3rd chunk tries to checkpoint -> that chunk rolls back.
        $saved = null;
        $calls = 0;
        try {
            (new EmploymentReportImporter())->import($file, $this->campusId, 2023, null, 'xlsx', null, function (array $state) use (&$saved, &$calls) {
                if (++$calls === 3) {
                    throw new ImportAborted('lease lost');
                }
                $saved = $state;
            });
            $this->fail('expected the run to abort');
        } catch (ImportAborted) {
        }

        $this->assertSame(200, $this->importedCount(), 'chunks 1-2 committed; chunk 3 rolled back with its checkpoint');
        $this->assertSame(200, $saved['result']['imported']);

        // Run 2 (another worker): resumes from the saved checkpoint.
        $final = (new EmploymentReportImporter())->import($file, $this->campusId, 2023, null, 'xlsx', $saved, static function (array $s) {});

        $this->assertSame(450, $this->importedCount(), 'every source row is present exactly once');
        $this->assertSame(450, $final['imported']);
        $this->assertSame(0, $final['skipped']);
        $this->assertSame(450, DB::table('user')->where('email', 'like', self::TAG.'.'.$this->run.'.%')->count());
        $dupes = DB::table('alumni')->where('campus_id', $this->campusId)->select('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->get();
        $this->assertCount(0, $dupes, 'no alumnus twice');
    }

    public function testResumeFromAStaleCheckpointNeverDuplicates(): void
    {
        $file = $this->workbook(250);

        // Whole file imported; but the "crashed" run only managed to persist the FIRST checkpoint (crash between commit and checkpoint).
        $states = [];
        (new EmploymentReportImporter())->import($file, $this->campusId, 2023, null, 'xlsx', null, function (array $s) use (&$states) {
            $states[] = $s;
        });
        $this->assertSame(250, $this->importedCount());

        $stale = $states[0];
        $again = (new EmploymentReportImporter())->import($file, $this->campusId, 2023, null, 'xlsx', $stale, static function (array $s) {});

        $this->assertSame(250, $this->importedCount(), 'nothing was imported twice');
        $this->assertGreaterThan(0, $again['skipped'], 'the already-committed rows were recognised and skipped');
        $this->assertSame(250, DB::table('user')->where('email', 'like', self::TAG.'.'.$this->run.'.%')->count());
    }

    public function testConcurrencyLimitFifoAndLeaseTakeover(): void
    {
        config(['import.max_concurrent' => 2, 'import.lease_seconds' => 120]);
        $mk = fn (string $n) => ImportJob::create(['campus_id' => $this->campusId, 'uploaded_by' => 1, 'filename' => self::TAG.$n, 'file_path' => 'imports/none.xlsx', 'file_ext' => 'xlsx']);
        [$a, $b, $c, $d] = [$mk('A'), $mk('B'), $mk('C'), $mk('D')];

        // a later import may not jump the queue while earlier ones are waiting
        $this->assertSame('busy', ImportJob::claim($c)[0], 'C must wait its turn behind A and B');

        [$sa, $ta] = ImportJob::claim($a);
        [$sb, $tb] = ImportJob::claim($b);
        $this->assertSame(['claimed', 'claimed'], [$sa, $sb]);
        $this->assertSame('busy', ImportJob::claim($c)[0], 'only 2 imports may be processing');
        $this->assertSame('busy', ImportJob::claim($d)[0]);
        $this->assertSame('gone', ImportJob::claim($a)[0], 'a live worker already owns A');
        $this->assertSame(1, ImportJob::queuePosition(ImportJob::find($c)));
        $this->assertSame(2, ImportJob::queuePosition(ImportJob::find($d)));

        // A finishes -> C (next in line) may start, D still waits
        ImportJob::complete($a, $ta, ['imported' => 0, 'skipped' => 0]);
        $this->assertSame('busy', ImportJob::claim($d)[0], 'D is behind C');
        $this->assertSame('claimed', ImportJob::claim($c)[0]);

        // B's worker dies: its heartbeat goes stale. B is older than D, so B gets the free slot first (takeover with a new token)...
        DB::table('import_jobs')->where('id', $b)->update(['heartbeat_at' => now()->subMinutes(10)]);
        $this->assertSame('gone', ImportJob::claim($c)[0], 'C is already running');
        $this->assertSame('busy', ImportJob::claim($d)[0], 'B (older, stale) is served before D');
        [$sb2, $tb2] = ImportJob::claim($b);
        $this->assertSame('claimed', $sb2);
        $this->assertNotSame($tb, $tb2);

        // ...and the dead worker, if it ever wakes up, no longer owns the import: its next checkpoint (which the importer runs
        // inside the chunk transaction) must abort.
        $this->expectException(ImportAborted::class);
        ImportJob::checkpoint($b, $tb, null, 10, 100);
    }

    public function testReapRequeuesAnImportWhoseWorkerStoppedHeartbeating(): void
    {
        $id = ImportJob::create(['campus_id' => $this->campusId, 'uploaded_by' => 1, 'filename' => self::TAG.'R', 'file_path' => 'imports/none.xlsx', 'file_ext' => 'xlsx']);
        ImportJob::claim($id, true);
        $this->assertNotContains($id, ImportJob::reap(), 'a live worker is left alone');
        DB::table('import_jobs')->where('id', $id)->update(['heartbeat_at' => now()->subMinutes(10)]);
        $this->assertContains($id, ImportJob::reap());
        $this->assertSame(ImportJob::QUEUED, ImportJob::find($id)->status);
    }

    public function testCancelStopsTheWorkerAtItsNextCheckpoint(): void
    {
        $id = ImportJob::create(['campus_id' => $this->campusId, 'uploaded_by' => 1, 'filename' => self::TAG.'X', 'file_path' => 'imports/none.xlsx', 'file_ext' => 'xlsx']);
        [, $token] = ImportJob::claim($id, true);
        ImportJob::checkpoint($id, $token, null, 5, 10);          // owned: fine (also when nothing changed)
        ImportJob::checkpoint($id, $token, null, 5, 10);
        $this->assertTrue(ImportJob::cancel($id));
        $this->expectException(ImportAborted::class);
        ImportJob::checkpoint($id, $token, null, 6, 10);
    }

    public function testSummaryTablesMatchTheLiveQueriesForANewCampus(): void
    {
        config(['import.chunk_size' => 100]);
        (new EmploymentReportImporter())->import($this->workbook(230), $this->campusId, 2023, null, 'xlsx');

        (new ReportingSummary())->rebuild($this->campusId);
        ReportingSummary::flushMemo();

        $figures = function (bool $summary) {
            config(['reporting.use_summary' => $summary]);
            ReportingSummary::flushMemo();
            $svc = new \App\Services\ReportService(null, null, $this->campusId);
            $dash = new \App\Models\DashboardStats($this->campusId);

            return [
                'report' => $svc->summary(),
                'breakdowns' => $dash->chartBreakdowns(),
                'map' => $dash->alumniMap(),
                'titles' => $dash->currentCourseJobTitleCounts(),
            ];
        };
        $canon = function ($v) use (&$canon) {
            if (!is_array($v)) {
                return $v;
            }
            $v = array_map($canon, $v);
            array_is_list($v) ? usort($v, static fn ($x, $y) => strcmp(json_encode($x), json_encode($y))) : ksort($v);

            return $v;
        };

        $live = $canon($figures(false));
        $sum = $canon($figures(true));
        foreach ($live as $name => $liveValue) {
            if (is_array($liveValue) && !array_is_list($liveValue)) {
                foreach ($liveValue as $k => $v) {
                    $this->assertEquals($v, $sum[$name][$k], "figure {$name}.{$k} differs between the summary tables and the live query");
                }
            } else {
                $this->assertEquals($liveValue, $sum[$name], "figure {$name} differs between the summary tables and the live query");
            }
        }
        $this->assertSame(230, (new \App\Models\Report($this->campusId))->alumniCount());
    }

    public function testImportedFieldsMatchTheSourceRow(): void
    {
        $file = $this->workbook(3, 5);   // rows numbered 5, 6, 7
        $res = (new EmploymentReportImporter())->import($file, $this->campusId, 2023, null, 'xlsx');
        $this->assertSame(3, $res['imported']);

        $email = self::TAG.'.'.$this->run.'.5@example.test';   // source row 5: odd => 'M', 5 % 5 === 0 => Unemployed
        $a = DB::table('alumni')->join('user', 'user.user_id', '=', 'alumni.user_id')->where('user.email', $email)->first();
        $this->assertNotNull($a);
        $this->assertSame('Juan 5', $a->first_name);
        $this->assertSame('Dela Cruz5', $a->last_name);
        $this->assertSame('Male', $a->gender);
        $this->assertSame('BS Information Technology', $a->course);
        $this->assertSame('College of Computer Studies', $a->college);
        $this->assertSame(2023, (int) $a->year_graduated);
        $this->assertSame($this->campusId, (int) $a->campus_id);
        $this->assertSame('alumni', $a->user_role);
        $this->assertSame('Active', $a->status);
        $this->assertSame('!', $a->password, 'no per-row bcrypt: the stored value can never match a password');
        $this->assertSame(1, DB::table('alumni_education')->where('alumni_id', $a->alumni_id)->where('degree', 'BS Information Technology')->count());

        // row 5 is "Unemployed" -> no job row; row 6 is employed -> one current job with the source's company / position / status
        $this->assertSame(0, DB::table('alumni_experience')->where('alumni_id', $a->alumni_id)->count());
        $b = DB::table('alumni')->join('user', 'user.user_id', '=', 'alumni.user_id')->where('user.email', self::TAG.'.'.$this->run.'.6@example.test')->first();
        $exp = DB::table('alumni_experience')->where('alumni_id', $b->alumni_id)->get();
        $this->assertCount(1, $exp);
        $this->assertSame('Developer 6', $exp[0]->title);
        $this->assertSame('ACME Inc.', $exp[0]->company);
        $this->assertSame('Regular', $exp[0]->employment_status);
        $this->assertSame('Private', $exp[0]->employment_sector);
        $this->assertSame('Local', $exp[0]->location_of_work);
        $this->assertSame(1, (int) $exp[0]->current);
    }
}
