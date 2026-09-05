<?php

namespace Tests\Integration\Models;

use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Ported from tests/Integration/Models/MessageTest.php. */
class MessageTest extends TestCase
{
    private Message $model;
    private string $senderEmail;
    private string $receiverEmail;
    /** @var int[] */
    private array $createdIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Message();
        $this->senderEmail = 'phpunit-sender-'.uniqid().'@example.test';
        $this->receiverEmail = 'phpunit-receiver-'.uniqid().'@example.test';
    }

    protected function tearDown(): void
    {
        if ($this->createdIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdIds), '?'));
            DB::delete("DELETE FROM messages WHERE id IN ($placeholders)", $this->createdIds);
        }
        parent::tearDown();
    }

    public function testInsertPairCreatesBothSentAndInboxCopies(): void
    {
        $this->model->insertPair($this->senderEmail, $this->receiverEmail, 'Subject', 'Body', 'alumni', 'employer');
        $this->trackMessagesBetween();

        $senderMailbox = $this->model->mailboxForEmail($this->senderEmail);
        $receiverMailbox = $this->model->mailboxForEmail($this->receiverEmail);

        $this->assertCount(1, $senderMailbox['sent']);
        $this->assertCount(1, $receiverMailbox['inbox']);
    }

    public function testNewInboxMessageIsUnvisitedForReceiverButNotForSender(): void
    {
        $this->model->insertPair($this->senderEmail, $this->receiverEmail, 'Subject', 'Body', 'alumni', 'employer');
        $this->trackMessagesBetween();

        $receiverMailbox = $this->model->mailboxForEmail($this->receiverEmail);
        $senderMailbox = $this->model->mailboxForEmail($this->senderEmail);

        $this->assertSame(1, $receiverMailbox['inbox_count']);
        $this->assertTrue($receiverMailbox['inbox'][0]['unvisited']);
        $this->assertSame(0, $senderMailbox['sent_count']);
        $this->assertFalse($senderMailbox['sent'][0]['unvisited']);
    }

    public function testSendMarksTheSendersOwnCopyAsAlreadySeen(): void
    {
        $id = $this->model->send($this->senderEmail, $this->receiverEmail, 'Subject', 'Body', 'alumni');
        $this->createdIds[] = $id;

        $row = $this->fetchMessage($id);
        $this->assertSame(1, (int) $row['sender_read']);
        $this->assertSame(0, (int) $row['is_read'], 'the receiver has not seen it yet');
    }

    public function testMarkReadByReceiverDoesNotTouchTheSendersSide(): void
    {
        $id = $this->model->send($this->senderEmail, $this->receiverEmail, 'Subject', 'Body', 'alumni');
        $this->createdIds[] = $id;
        DB::update('UPDATE messages SET sender_read = 0 WHERE id = ?', [$id]);

        $this->model->markRead($id, $this->receiverEmail);

        $row = $this->fetchMessage($id);
        $this->assertSame(1, (int) $row['is_read'], 'receiver marking it read should set is_read');
        $this->assertSame(0, (int) $row['sender_read'], 'the sender side must be untouched by the receiver opening it');
    }

    public function testMarkReadBySenderDoesNotTouchTheReceiversSide(): void
    {
        $id = $this->model->send($this->senderEmail, $this->receiverEmail, 'Subject', 'Body', 'alumni');
        $this->createdIds[] = $id;

        $this->model->markRead($id, $this->senderEmail);

        $row = $this->fetchMessage($id);
        $this->assertSame(1, (int) $row['sender_read']);
        $this->assertSame(0, (int) $row['is_read'], 'the receiver side must be untouched by the sender re-opening their own sent copy');
    }

    public function testMarkReadByAnUninvolvedEmailDoesNothing(): void
    {
        $id = $this->model->send($this->senderEmail, $this->receiverEmail, 'Subject', 'Body', 'alumni');
        $this->createdIds[] = $id;

        $result = $this->model->markRead($id, 'phpunit-stranger-'.uniqid().'@example.test');

        $this->assertFalse($result);
        $row = $this->fetchMessage($id);
        $this->assertSame(0, (int) $row['is_read']);
    }

    public function testUpdateFolderByAnUninvolvedEmailIsRejected(): void
    {
        $id = $this->model->send($this->senderEmail, $this->receiverEmail, 'Subject', 'Body', 'alumni');
        $this->createdIds[] = $id;

        $result = $this->model->updateFolder($id, 'trash', 'phpunit-stranger-'.uniqid().'@example.test');

        $this->assertFalse($result);
        $row = $this->fetchMessage($id);
        $this->assertNotSame('trash', $row['folder']);
    }

    public function testUpdateFolderByTheReceiverIsAllowed(): void
    {
        $id = $this->model->send($this->senderEmail, $this->receiverEmail, 'Subject', 'Body', 'alumni');
        $this->createdIds[] = $id;
        DB::update("UPDATE messages SET folder = 'inbox' WHERE id = ?", [$id]);

        $result = $this->model->updateFolder($id, 'important', $this->receiverEmail);

        $this->assertTrue($result);
        $this->assertSame('important', $this->fetchMessage($id)['folder']);
    }

    private function trackMessagesBetween(): void
    {
        $rows = DB::select('SELECT id FROM messages WHERE sender_email = ? OR receiver_email = ?', [$this->senderEmail, $this->receiverEmail]);
        foreach ($rows as $row) {
            $this->createdIds[] = (int) $row->id;
        }
    }

    private function fetchMessage(int $id): array
    {
        return (array) DB::selectOne('SELECT * FROM messages WHERE id = ?', [$id]);
    }
}
