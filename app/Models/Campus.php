<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/Campus.php — see User.php's docblock for the porting approach. */
class Campus
{
    use LegacyQueries;

    public function all(): array
    {
        return $this->selectAll('SELECT campus_id, name, type FROM campus ORDER BY type, name');
    }

    public function findById(int $campusId): ?array
    {
        return $this->selectOne('SELECT campus_id, name, type FROM campus WHERE campus_id = ? LIMIT 1', [$campusId]);
    }
}
