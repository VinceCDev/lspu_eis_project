<?php

namespace App\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Thin helpers over Laravel's DB facade that let every ported model keep
 * its original raw SQL text (mysqli's `?` placeholders are the same
 * placeholder style PDO/Laravel's query builder uses) while dropping the
 * bind_param() type-string ceremony and manual stmt open/close/fetch loops.
 * This keeps each port mechanical and verifiable against the original
 * query, rather than re-deriving each query in Eloquent's fluent syntax.
 */
trait LegacyQueries
{
    protected function selectOne(string $sql, array $params = []): ?array
    {
        $row = DB::selectOne($sql, $params);

        return $row ? (array) $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    protected function selectAll(string $sql, array $params = []): array
    {
        return array_map(static fn ($row) => (array) $row, DB::select($sql, $params));
    }

    protected function insertGetId(string $sql, array $params = []): int
    {
        DB::insert($sql, $params);

        return (int) DB::getPdo()->lastInsertId();
    }

    protected function insert(string $sql, array $params = []): bool
    {
        return DB::insert($sql, $params);
    }

    /**
     * Runs an UPDATE and returns the number of affected rows. Named
     * runUpdate/runDelete (not update/delete) so they never collide with a
     * ported model's own public update()/delete() method of the same name
     * (e.g. Employer::update(), Employer::delete()).
     */
    protected function runUpdate(string $sql, array $params = []): int
    {
        return DB::update($sql, $params);
    }

    /** Runs a DELETE and returns the number of affected rows. */
    protected function runDelete(string $sql, array $params = []): int
    {
        return DB::delete($sql, $params);
    }
}
