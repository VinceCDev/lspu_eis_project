<?php

namespace App\Models;

use App\Concerns\LegacyQueries;
use Illuminate\Support\Facades\DB;

/** Ported from backend/Models/SystemInfo.php — see User.php's docblock for the porting approach. */
class SystemInfo
{
    use LegacyQueries;

    public function userCountsByRole(): array
    {
        $counts = ['alumni' => 0, 'employer' => 0, 'admin' => 0, 'superadmin' => 0];
        foreach ($this->selectAll('SELECT user_role, COUNT(*) as count FROM user GROUP BY user_role') as $row) {
            $counts[$row['user_role']] = (int) $row['count'];
        }

        return $counts;
    }

    public function pendingCounts(): array
    {
        $counts = ['alumni' => 0, 'employer' => 0];
        foreach ($this->selectAll("SELECT user_role, COUNT(*) as count FROM user WHERE status = 'Pending' GROUP BY user_role") as $row) {
            if (array_key_exists($row['user_role'], $counts)) {
                $counts[$row['user_role']] = (int) $row['count'];
            }
        }

        return $counts;
    }

    public function recentRegistrations(int $limit = 10): array
    {
        $sql = "SELECT u.user_id, u.email, u.user_role, u.status, u.created_at,
                       COALESCE(a.first_name, e.company_name, ad.first_name) as display_name
                FROM user u
                LEFT JOIN alumni a ON a.user_id = u.user_id
                LEFT JOIN employer e ON e.user_id = u.user_id
                LEFT JOIN administrator ad ON ad.user_id = u.user_id
                ORDER BY u.created_at DESC
                LIMIT ".((int) $limit);

        return $this->selectAll($sql);
    }

    public function databaseSizeBytes(): int
    {
        $row = $this->selectOne('SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = DATABASE()');

        return (int) ($row['size'] ?? 0);
    }

    /** @return array<string,int> byte totals per uploads subfolder */
    public function uploadsSizeBytes(): array
    {
        $base = \App\Core\Uploader::basePath();
        if (!is_dir($base)) {
            $base = public_path('uploads');
        }
        $totals = [];

        if (!is_dir($base)) {
            return $totals;
        }

        foreach (scandir($base) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $base.'/'.$entry;
            if (!is_dir($path)) {
                continue;
            }

            $size = 0;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $size += $file->getSize();
            }
            $totals[$entry] = $size;
        }

        return $totals;
    }

    public function serverInfo(): array
    {
        return [
            'php_version' => phpversion(),
            'mysql_version' => DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        ];
    }
}
