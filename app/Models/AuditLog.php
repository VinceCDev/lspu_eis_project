<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/AuditLog.php — see User.php's docblock for the porting approach. */
class AuditLog
{
    use LegacyQueries;

    public function log(?int $userId, ?string $userEmail, ?string $userRole, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $this->insert('INSERT INTO audit_logs (user_id, user_email, user_role, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
            $userId, $userEmail, $userRole, $action, $entityType, $entityId, $description, $ip,
        ]);
    }

    public function all(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = 'SELECT id, user_id, user_email, user_role, action, entity_type, entity_id, description, ip_address, created_at FROM audit_logs';
        if ($where) {
            $sql .= ' WHERE '.implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        return $this->selectAll($sql, $params);
    }

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($filters);

        $sql = 'SELECT COUNT(*) AS total FROM audit_logs';
        if ($where) {
            $sql .= ' WHERE '.implode(' AND ', $where);
        }

        return (int) ($this->selectOne($sql, $params)['total'] ?? 0);
    }

    private function buildFilters(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(description LIKE ? OR user_email LIKE ? OR action LIKE ?)';
            $like = '%'.$filters['search'].'%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['date_from'].' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['date_to'].' 23:59:59';
        }

        return [$where, $params];
    }

    public function distinctActions(): array
    {
        $rows = $this->selectAll('SELECT DISTINCT action FROM audit_logs ORDER BY action');

        return array_map(static fn ($row) => $row['action'], $rows);
    }
}
