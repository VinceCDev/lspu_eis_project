<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/SuccessStory.php — see User.php's docblock for the porting approach. */
class SuccessStory
{
    use LegacyQueries;

    public function allWithAuthor(): array
    {
        return $this->selectAll('SELECT ss.*, a.first_name, a.middle_name, a.last_name, a.profile_pic, u.email
            FROM success_stories ss
            JOIN alumni a ON ss.user_id = a.user_id
            JOIN user u ON ss.user_id = u.user_id
            ORDER BY ss.created_at DESC');
    }

    public function publishedForPublic(int $limit = 6): array
    {
        $rows = $this->selectAll("SELECT ss.*, a.first_name, a.middle_name, a.last_name, a.profile_pic, u.email
            FROM success_stories ss
            JOIN alumni a ON ss.user_id = a.user_id
            JOIN user u ON ss.user_id = u.user_id
            WHERE ss.status = 'published'
            ORDER BY ss.created_at DESC
            LIMIT ?", [$limit]);

        return array_map(static fn ($row) => [
            'story_id' => $row['story_id'],
            'user_id' => $row['user_id'],
            'title' => $row['title'],
            'content' => $row['content'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'author_full_name' => trim($row['first_name'].' '.$row['middle_name'].' '.$row['last_name']),
            'author_email' => $row['email'],
            'profile_picture' => $row['profile_pic'],
        ], $rows);
    }

    public function create(int $userId, string $title, string $content, string $status): void
    {
        $this->insert('INSERT INTO success_stories (user_id, title, content, status, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())', [$userId, $title, $content, $status]);
    }

    public function allForUser(int $userId): array
    {
        return $this->selectAll('SELECT * FROM success_stories WHERE user_id = ? ORDER BY created_at DESC', [$userId]);
    }

    public function findById(int $storyId): ?array
    {
        return $this->selectOne('SELECT * FROM success_stories WHERE story_id = ? LIMIT 1', [$storyId]);
    }

    public function createForUser(int $userId, string $title, string $content): int
    {
        return $this->insertGetId('INSERT INTO success_stories (user_id, title, content, status) VALUES (?, ?, ?, ?)', [$userId, $title, $content, 'draft']);
    }

    /** Alumni self-edit: title/content only, status is preserved (admin-controlled). */
    public function updateForUser(int $storyId, int $userId, string $title, string $content): bool
    {
        return $this->runUpdate('UPDATE success_stories SET title = ?, content = ? WHERE story_id = ? AND user_id = ?', [$title, $content, $storyId, $userId]) > 0;
    }

    public function deleteForUser(int $storyId, int $userId): bool
    {
        return $this->runDelete('DELETE FROM success_stories WHERE story_id = ? AND user_id = ?', [$storyId, $userId]) > 0;
    }

    public function update(int $storyId, int $userId, string $title, string $content, string $status): bool
    {
        return $this->runUpdate('UPDATE success_stories SET user_id=?, title=?, content=?, status=?, updated_at=NOW() WHERE story_id=?', [$userId, $title, $content, $status, $storyId]) >= 0;
    }

    public function delete(int $storyId): bool
    {
        return $this->runDelete('DELETE FROM success_stories WHERE story_id = ?', [$storyId]) >= 0;
    }
}
