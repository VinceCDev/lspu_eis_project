<?php

namespace Tests\Integration\Models;

use App\Models\SavedJob;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Ported from tests/Integration/Models/SavedJobTest.php. */
class SavedJobTest extends TestCase
{
    private SavedJob $model;
    private int $userId;
    private int $jobId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new SavedJob();
        $this->userId = $this->firstAlumniUserId();
        $this->jobId = $this->firstJobId();

        $this->model->unsave($this->userId, $this->jobId);
    }

    protected function tearDown(): void
    {
        $this->model->unsave($this->userId, $this->jobId);
        parent::tearDown();
    }

    public function testSaveAddsJobToUsersSavedList(): void
    {
        $this->model->save($this->userId, $this->jobId);

        $this->assertContains($this->jobId, $this->model->idsForUser($this->userId));
    }

    public function testSavingTheSameJobTwiceDoesNotError(): void
    {
        $this->assertTrue($this->model->save($this->userId, $this->jobId));
        $this->assertTrue($this->model->save($this->userId, $this->jobId));

        $ids = $this->model->idsForUser($this->userId);
        $this->assertCount(1, array_filter($ids, fn ($id) => $id === $this->jobId));
    }

    public function testUnsaveRemovesJobFromUsersSavedList(): void
    {
        $this->model->save($this->userId, $this->jobId);
        $this->assertContains($this->jobId, $this->model->idsForUser($this->userId));

        $this->model->unsave($this->userId, $this->jobId);

        $this->assertNotContains($this->jobId, $this->model->idsForUser($this->userId));
    }

    public function testUnsavingAJobThatWasNeverSavedDoesNotError(): void
    {
        $this->assertTrue($this->model->unsave($this->userId, $this->jobId));
    }

    private function firstAlumniUserId(): int
    {
        $row = DB::selectOne("SELECT user_id FROM user WHERE user_role = 'alumni' LIMIT 1");
        if (!$row) {
            $this->markTestSkipped('No alumni account exists in this database.');
        }

        return (int) $row->user_id;
    }

    private function firstJobId(): int
    {
        $row = DB::selectOne('SELECT job_id FROM jobs LIMIT 1');
        if (!$row) {
            $this->markTestSkipped('No job exists in this database.');
        }

        return (int) $row->job_id;
    }
}
