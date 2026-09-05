<?php

namespace Tests\Integration\Models;

use App\Models\Application;
use App\Models\Job;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Ported from tests/Integration/Models/ApplicationTest.php. */
class ApplicationTest extends TestCase
{
    private Application $model;
    private int $alumniId;
    private int $jobId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Application();
        $this->alumniId = $this->firstAlumniId();

        $employerId = $this->firstEmployerUserId();
        $this->jobId = (new Job())->createForEmployer($employerId, [
            'title' => '__phpunit_application_test__'.uniqid(),
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
        ]);
    }

    protected function tearDown(): void
    {
        $this->model->deleteForAlumni($this->alumniId, $this->jobId);
        DB::delete('DELETE FROM jobs WHERE job_id = ?', [$this->jobId]);
        parent::tearDown();
    }

    public function testExistsForAlumniJobIsFalseBeforeApplying(): void
    {
        $this->assertFalse($this->model->existsForAlumniJob($this->alumniId, $this->jobId));
    }

    public function testCreateForAlumniThenExistsForAlumniJobIsTrue(): void
    {
        $this->model->createForAlumni($this->alumniId, $this->jobId, 'My cover letter', null, 'My answer');

        $this->assertTrue($this->model->existsForAlumniJob($this->alumniId, $this->jobId));
    }

    public function testAppliedJobsForAlumniIncludesTheCoverLetterAndAnswer(): void
    {
        $this->model->createForAlumni($this->alumniId, $this->jobId, 'My cover letter', null, 'My answer');

        $applied = $this->model->appliedJobsForAlumni($this->alumniId);
        $match = current(array_filter($applied, fn ($j) => (int) $j['job_id'] === $this->jobId));

        $this->assertNotFalse($match, 'the newly-applied job should appear in appliedJobsForAlumni()');
        $this->assertSame('My cover letter', $match['cover_letter_text']);
        $this->assertSame('My answer', $match['application_answer']);
        $this->assertSame('Pending', $match['application_status']);
    }

    public function testDeleteForAlumniRemovesTheApplication(): void
    {
        $this->model->createForAlumni($this->alumniId, $this->jobId, null, null, null);
        $this->assertTrue($this->model->existsForAlumniJob($this->alumniId, $this->jobId));

        $this->model->deleteForAlumni($this->alumniId, $this->jobId);

        $this->assertFalse($this->model->existsForAlumniJob($this->alumniId, $this->jobId));
    }

    private function firstAlumniId(): int
    {
        $row = DB::selectOne('SELECT alumni_id FROM alumni LIMIT 1');
        if (!$row) {
            $this->markTestSkipped('No alumni exists in this database.');
        }

        return (int) $row->alumni_id;
    }

    private function firstEmployerUserId(): int
    {
        $row = DB::selectOne("SELECT user_id FROM user WHERE user_role = 'employer' LIMIT 1");
        if (!$row) {
            $this->markTestSkipped('No employer account exists in this database.');
        }

        return (int) $row->user_id;
    }
}
