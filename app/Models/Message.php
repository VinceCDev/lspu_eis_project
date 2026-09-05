<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/Message.php — see User.php's docblock for the porting approach. */
class Message
{
    use LegacyQueries;

    /** Inbox/Sent/Important/Trash for a mailbox owner. */
    public function mailboxForEmail(string $userEmail): array
    {
        $inbox = $this->selectAll("SELECT * FROM messages WHERE receiver_email = ? AND (folder IS NULL OR folder = 'inbox') ORDER BY created_at DESC", [$userEmail]);
        $sent = $this->selectAll("SELECT * FROM messages WHERE sender_email = ? AND (folder IS NULL OR folder = 'sent') ORDER BY created_at DESC", [$userEmail]);
        $important = $this->selectAll("SELECT * FROM messages WHERE (receiver_email = ? OR sender_email = ?) AND folder = 'important' ORDER BY created_at DESC", [$userEmail, $userEmail]);
        $trash = $this->selectAll("SELECT * FROM messages WHERE (receiver_email = ? OR sender_email = ?) AND folder = 'trash' ORDER BY created_at DESC", [$userEmail, $userEmail]);

        // Every folder's badge flags unvisited messages, not the total
        // sitting in the folder — "unvisited" means the receiver hasn't
        // opened it (their side) or the sender hasn't opened their own copy
        // (their side), depending on which of the two this viewer is.
        $annotate = function (array $rows) use ($userEmail): array {
            foreach ($rows as &$row) {
                $row['unvisited'] = $row['receiver_email'] === $userEmail
                    ? !((bool) $row['is_read'])
                    : !((bool) $row['sender_read']);
            }

            return $rows;
        };
        $countUnvisited = fn (array $rows): int => count(array_filter($rows, fn ($r) => $r['unvisited']));

        $inbox = $annotate($inbox);
        $sent = $annotate($sent);
        $important = $annotate($important);
        $trash = $annotate($trash);

        return [
            'inbox' => $inbox, 'sent' => $sent, 'important' => $important, 'trash' => $trash,
            'inbox_count' => $countUnvisited($inbox), 'sent_count' => $countUnvisited($sent),
            'important_count' => $countUnvisited($important), 'trash_count' => $countUnvisited($trash),
        ];
    }

    /**
     * @return int the new message's id, or 0 on failure
     *
     * sender_read is set to 1 on insert — the sender composing and sending
     * a message has, by definition, already "seen" their own copy of it.
     */
    public function send(string $senderEmail, string $receiverEmail, string $subject, string $body, string $role): int
    {
        try {
            return $this->insertGetId('INSERT INTO messages (sender_email, receiver_email, subject, message, role, sender_read, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())', [
                $senderEmail, $receiverEmail, $subject, $body, $role,
            ]);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Marks a message visited for whichever side of it the viewer is on.
     */
    public function markRead(int $id, string $viewerEmail): bool
    {
        return $this->runUpdate('UPDATE messages SET
                is_read = CASE WHEN receiver_email = ? THEN 1 ELSE is_read END,
                sender_read = CASE WHEN sender_email = ? THEN 1 ELSE sender_read END
            WHERE id = ? AND (sender_email = ? OR receiver_email = ?)', [$viewerEmail, $viewerEmail, $id, $viewerEmail, $viewerEmail]) > 0;
    }

    /** Only the sender or receiver of the message may move it between folders. */
    public function updateFolder(int $id, string $folder, string $ownerEmail): bool
    {
        return $this->runUpdate('UPDATE messages SET folder = ? WHERE id = ? AND (sender_email = ? OR receiver_email = ?)', [$folder, $id, $ownerEmail, $ownerEmail]) > 0;
    }

    /**
     * Inserts the sender's "sent" copy and the receiver's "inbox" copy of a
     * message.
     */
    public function insertPair(string $senderEmail, string $receiverEmail, string $subject, string $body, string $senderRole, string $receiverRole): bool
    {
        $now = date('Y-m-d H:i:s');

        $ok1 = $this->insert('INSERT INTO messages (sender_email, receiver_email, subject, message, role, folder, sender_read, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
            $senderEmail, $receiverEmail, $subject, $body, $senderRole, 'sent', 1, $now,
        ]);

        $ok2 = $this->insert('INSERT INTO messages (sender_email, receiver_email, subject, message, role, folder, sender_read, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
            $senderEmail, $receiverEmail, $subject, $body, $receiverRole, 'inbox', 0, $now,
        ]);

        return $ok1 && $ok2;
    }
}
