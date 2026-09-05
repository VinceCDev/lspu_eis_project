<?php

namespace Tests\Integration\Models;

use App\Models\SuccessStory;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Ported from tests/Integration/Models/SuccessStoryTest.php. */
class SuccessStoryTest extends TestCase
{
    private SuccessStory $model;
    private int $ownerId;
    private int $storyId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new SuccessStory();
        $this->ownerId = $this->firstAlumniUserId();
        $this->storyId = $this->model->createForUser($this->ownerId, '__phpunit_test_story__', 'Original content');
    }

    protected function tearDown(): void
    {
        DB::delete('DELETE FROM success_stories WHERE story_id = ?', [$this->storyId]);
        parent::tearDown();
    }

    public function testUpdateForUserSucceedsForTheOwner(): void
    {
        $ok = $this->model->updateForUser($this->storyId, $this->ownerId, 'Updated title', 'Updated content');

        $this->assertTrue($ok);
        $this->assertSame('Updated title', $this->model->findById($this->storyId)['title']);
    }

    public function testUpdateForUserReportsFailureForANonOwningUser(): void
    {
        $nonOwnerId = $this->ownerId + 999999;

        $ok = $this->model->updateForUser($this->storyId, $nonOwnerId, 'Hijacked title', 'Hijacked content');

        $this->assertFalse($ok, "updating another user's story must report failure, not a false success");
        $this->assertSame('__phpunit_test_story__', $this->model->findById($this->storyId)['title'], 'the content must be unchanged');
    }

    public function testDeleteForUserReportsFailureForANonOwningUser(): void
    {
        $nonOwnerId = $this->ownerId + 999999;

        $ok = $this->model->deleteForUser($this->storyId, $nonOwnerId);

        $this->assertFalse($ok, "deleting another user's story must report failure, not a false success");
        $this->assertNotNull($this->model->findById($this->storyId), 'the story must still exist');
    }

    public function testDeleteForUserSucceedsForTheOwner(): void
    {
        $ok = $this->model->deleteForUser($this->storyId, $this->ownerId);

        $this->assertTrue($ok);
        $this->assertNull($this->model->findById($this->storyId));
    }

    private function firstAlumniUserId(): int
    {
        $row = DB::selectOne("SELECT user_id FROM user WHERE user_role = 'alumni' LIMIT 1");
        if (!$row) {
            $this->markTestSkipped('No alumni account exists in this database.');
        }

        return (int) $row->user_id;
    }
}
