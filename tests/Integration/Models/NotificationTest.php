<?php

namespace Tests\Integration\Models;

use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Ported from tests/Integration/Models/NotificationTest.php. */
class NotificationTest extends TestCase
{
    private Notification $model;
    private int $userId;
    private string $type;
    private string $realNotificationType = 'system';
    private string $testMessage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Notification();
        $this->userId = $this->firstUserId();
        $this->type = '__phpunit_test_type__';
        $this->testMessage = '__phpunit_test_message__'.uniqid();
    }

    protected function tearDown(): void
    {
        DB::delete("DELETE FROM notification_preferences WHERE user_id = ? AND type IN (?, ?)", [$this->userId, $this->type, $this->realNotificationType]);
        DB::delete('DELETE FROM notifications WHERE user_id = ? AND message = ?', [$this->userId, $this->testMessage]);
        parent::tearDown();
    }

    public function testIsEnabledDefaultsToTrueWhenNeverSet(): void
    {
        $this->assertTrue($this->model->isEnabled($this->userId, $this->type));
    }

    public function testSetPreferenceFalseThenIsEnabledReturnsFalse(): void
    {
        $this->model->setPreference($this->userId, $this->type, false);

        $this->assertFalse($this->model->isEnabled($this->userId, $this->type));
    }

    public function testSetPreferenceCanBeToggledBackOn(): void
    {
        $this->model->setPreference($this->userId, $this->type, false);
        $this->model->setPreference($this->userId, $this->type, true);

        $this->assertTrue($this->model->isEnabled($this->userId, $this->type));
    }

    public function testCreateInsertsWhenTypeIsEnabled(): void
    {
        $this->model->create($this->userId, $this->realNotificationType, $this->testMessage);

        $this->assertSame(1, $this->countNotifications());
    }

    public function testCreateDoesNothingWhenTypeIsDisabled(): void
    {
        $this->model->setPreference($this->userId, $this->realNotificationType, false);

        $this->model->create($this->userId, $this->realNotificationType, $this->testMessage);

        $this->assertSame(0, $this->countNotifications());
    }

    public function testMarkOneReadOnlyAffectsTheOwningUser(): void
    {
        $this->model->create($this->userId, $this->realNotificationType, $this->testMessage);
        $id = $this->latestNotificationId();

        $result = $this->model->markOneRead($id, $this->userId + 999999);

        $this->assertFalse($result, "marking another user's notification as read must fail");
    }

    public function testMarkOneReadSucceedsForTheOwningUser(): void
    {
        $this->model->create($this->userId, $this->realNotificationType, $this->testMessage);
        $id = $this->latestNotificationId();

        $result = $this->model->markOneRead($id, $this->userId);

        $this->assertTrue($result);
    }

    private function countNotifications(): int
    {
        $row = DB::selectOne('SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND message = ?', [$this->userId, $this->testMessage]);

        return (int) $row->c;
    }

    private function latestNotificationId(): int
    {
        $row = DB::selectOne('SELECT id FROM notifications WHERE user_id = ? AND message = ? ORDER BY id DESC LIMIT 1', [$this->userId, $this->testMessage]);

        return (int) $row->id;
    }

    private function firstUserId(): int
    {
        $row = DB::selectOne('SELECT user_id FROM user LIMIT 1');
        if (!$row) {
            $this->markTestSkipped('No user exists in this database.');
        }

        return (int) $row->user_id;
    }
}
