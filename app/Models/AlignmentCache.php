<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/AlignmentCache.php — see User.php's docblock for the porting approach. */
class AlignmentCache
{
    use LegacyQueries;

    public function get(string $course, string $jobTitle): ?string
    {
        $key = self::key($course, $jobTitle);

        return $this->selectOne('SELECT label FROM alignment_cache WHERE cache_key = ? LIMIT 1', [$key])['label'] ?? null;
    }

    public function set(string $course, string $jobTitle, string $label): void
    {
        $key = self::key($course, $jobTitle);
        $this->insert('INSERT INTO alignment_cache (cache_key, course, job_title, label) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE label = VALUES(label)', [$key, $course, $jobTitle, $label]);
    }

    /**
     * Batch cache lookup — see backend/Models/AlignmentCache.php's docblock
     * in the original app for why this exists (N+1 query fix).
     *
     * @param array<int, array{0: string, 1: string}> $pairs [course, jobTitle] tuples
     * @return array<string, string> cache_key => label, only for pairs actually cached
     */
    public function getMany(array $pairs): array
    {
        if (empty($pairs)) {
            return [];
        }

        $keys = array_values(array_unique(array_map(static fn (array $p) => self::key($p[0], $p[1]), $pairs)));
        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        $out = [];
        foreach ($this->selectAll("SELECT cache_key, label FROM alignment_cache WHERE cache_key IN ({$placeholders})", $keys) as $row) {
            $out[$row['cache_key']] = $row['label'];
        }

        return $out;
    }

    public static function key(string $course, string $jobTitle): string
    {
        return md5(strtolower(trim($course)).'|'.strtolower(trim($jobTitle)));
    }
}
