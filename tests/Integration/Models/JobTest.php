<?php

namespace Tests\Integration\Models;

use App\Models\Job;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Ported from tests/Integration/Models/JobTest.php. */
class JobTest extends TestCase
{
    private const TITLE_PREFIX = '__phpunit_job_test__';

    /** @var int[] */
    private array $createdJobIds = [];

    protected function tearDown(): void
    {
        if ($this->createdJobIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdJobIds), '?'));
            DB::delete("DELETE FROM jobs WHERE job_id IN ($placeholders)", $this->createdJobIds);
        }
        parent::tearDown();
    }

    public function testCloseExpiredClosesOnlyActiveJobsOlderThanCutoff(): void
    {
        $employerId = $this->firstEmployerUserId();
        $oldActiveId = $this->insertJob($employerId, 'Active', 35);
        $recentActiveId = $this->insertJob($employerId, 'Active', 5);
        $oldClosedId = $this->insertJob($employerId, 'Closed', 40);

        (new Job())->closeExpired(30);

        $this->assertSame('Closed', $this->statusOf($oldActiveId), 'a 35-day-old Active job should be auto-closed');
        $this->assertSame('Active', $this->statusOf($recentActiveId), 'a 5-day-old Active job should stay Active');
        $this->assertSame('Closed', $this->statusOf($oldClosedId), 'an already-Closed job should be left as-is');
    }

    public function testCreateForEmployerStoresTheEmployerQuestion(): void
    {
        $employerId = $this->firstEmployerUserId();
        $jobId = (new Job())->createForEmployer($employerId, $this->jobFormData([
            'employer_question' => 'Why do you want this role?',
            'employer_question_required' => 1,
        ]));
        $this->createdJobIds[] = $jobId;

        $meta = (new Job())->questionMetaById($jobId);

        $this->assertSame('Why do you want this role?', $meta['employer_question']);
        $this->assertTrue($meta['employer_question_required']);
    }

    public function testCreateForEmployerAllowsAJobWithNoQuestion(): void
    {
        $employerId = $this->firstEmployerUserId();
        $jobId = (new Job())->createForEmployer($employerId, $this->jobFormData([
            'employer_question' => '',
            'employer_question_required' => 0,
        ]));
        $this->createdJobIds[] = $jobId;

        $meta = (new Job())->questionMetaById($jobId);

        $this->assertSame('', $meta['employer_question']);
        $this->assertFalse($meta['employer_question_required']);
    }

    public function testUpdateForEmployerSucceedsForTheOwningEmployer(): void
    {
        $employerId = $this->firstEmployerUserId();
        $jobId = (new Job())->createForEmployer($employerId, $this->jobFormData(['title' => 'Original Title']));
        $this->createdJobIds[] = $jobId;

        $ok = (new Job())->updateForEmployer($jobId, $employerId, $this->jobFormData(['title' => 'Updated Title']));

        $this->assertTrue($ok);
        $this->assertSame('Updated Title', (new Job())->titleById($jobId));
    }

    public function testUpdateForEmployerIsRejectedForANonOwningEmployer(): void
    {
        $employerId = $this->firstEmployerUserId();
        $otherEmployerId = $this->secondEmployerUserId();
        $jobId = (new Job())->createForEmployer($employerId, $this->jobFormData(['title' => 'Original Title']));
        $this->createdJobIds[] = $jobId;

        $ok = (new Job())->updateForEmployer($jobId, $otherEmployerId, $this->jobFormData(['title' => 'Hijacked Title']));

        $this->assertFalse($ok, "an employer must not be able to update another employer's job posting");
        $this->assertSame('Original Title', (new Job())->titleById($jobId));
    }

    public function testDeleteForEmployerIsRejectedForANonOwningEmployer(): void
    {
        $employerId = $this->firstEmployerUserId();
        $otherEmployerId = $this->secondEmployerUserId();
        $jobId = (new Job())->createForEmployer($employerId, $this->jobFormData());
        $this->createdJobIds[] = $jobId;

        $ok = (new Job())->deleteForEmployer($jobId, $otherEmployerId);

        $this->assertFalse($ok, "an employer must not be able to delete another employer's job posting");
        $this->assertNotNull((new Job())->titleById($jobId), 'the job must still exist');
    }

    /** @return array<string,mixed> */
    private function jobFormData(array $overrides = []): array
    {
        return array_merge([
            'title' => self::TITLE_PREFIX.uniqid(),
            'type' => 'Full-time',
            'work_setup' => 'Onsite',
            'classification' => 'Information & Communication Technology',
            'location' => 'Test City',
            'salary' => '30000',
            'status' => 'Active',
            'description' => 'test',
            'requirements' => 'test',
            'qualifications' => 'test',
            'employer_question' => '',
            'employer_question_required' => 0,
        ], $overrides);
    }

    private function insertJob(int $employerId, string $status, int $daysOld): int
    {
        $title = self::TITLE_PREFIX.uniqid();
        DB::insert(
            'INSERT INTO jobs (employer_id, title, type, work_setup, classification, location, salary, status, description, requirements, qualifications, employer_question, employer_question_required, created_at)
             VALUES (?, ?, "Full-time", "Onsite", "Information & Communication Technology", "Test City", "0", ?, "test", "test", "test", "", 0, NOW() - INTERVAL ? DAY)',
            [$employerId, $title, $status, $daysOld]
        );
        $id = (int) DB::getPdo()->lastInsertId();

        $this->createdJobIds[] = $id;

        return $id;
    }

    private function statusOf(int $jobId): ?string
    {
        $row = DB::selectOne('SELECT status FROM jobs WHERE job_id = ?', [$jobId]);

        return $row ? $row->status : null;
    }

    private function firstEmployerUserId(): int
    {
        $row = DB::selectOne("SELECT user_id FROM user WHERE user_role = 'employer' LIMIT 1");
        if (!$row) {
            $this->markTestSkipped('No employer account exists in this database to attach a test job to.');
        }

        return (int) $row->user_id;
    }

    private function secondEmployerUserId(): int
    {
        $row = DB::selectOne("SELECT user_id FROM user WHERE user_role = 'employer' ORDER BY user_id LIMIT 1 OFFSET 1");
        if (!$row) {
            $this->markTestSkipped('This database needs at least two employer accounts for this test.');
        }

        return (int) $row->user_id;
    }
}
