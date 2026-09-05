<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ported from backend/cli/backup.php. Backs up the database and uploads/
 * folder to a location OUTSIDE the project directory. Pure-PHP dump (raw
 * SQL via the DB facade, not mysqldump/exec) so it works identically on
 * local XAMPP and on shared hosting where exec()/shell_exec() may be disabled.
 */
class BackupCommand extends Command
{
    protected $signature = 'backup:run';
    protected $description = 'Backs up the database and uploads/ folder outside the project directory.';

    public function handle(): int
    {
        // Default is a sibling of the project directory (not inside it) so a
        // wiped/corrupted project checkout doesn't take its own backups with
        // it — same intent as before, just resolved relative to wherever
        // this project is actually installed instead of a hardcoded
        // developer-machine path.
        $backupDir = rtrim(env('BACKUP_DIR', dirname(base_path()).'/lspu_eis_backups'), '/\\');
        if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
            $this->error("backup: could not create backup directory: {$backupDir}");

            return 1;
        }

        $timestamp = date('Y-m-d_His');
        error_log("backup: starting backup to {$backupDir}");

        try {
            $sqlGzPath = $this->backupDatabase($backupDir, $timestamp);
            $zipPath = $this->backupUploads($backupDir, $timestamp);
            $this->pruneOldBackups($backupDir);

            $this->info("Backup complete:\n  {$sqlGzPath}\n  {$zipPath}");
            error_log("backup: backup complete ({$sqlGzPath}, {$zipPath})");

            return 0;
        } catch (\Throwable $e) {
            $this->error('backup: backup failed: '.$e->getMessage());
            error_log('backup: backup failed: '.$e->getMessage());

            return 1;
        }
    }

    private function backupDatabase(string $backupDir, string $timestamp): string
    {
        $sqlPath = "{$backupDir}/db_{$timestamp}.sql";

        $fh = fopen($sqlPath, 'w');
        if ($fh === false) {
            throw new \RuntimeException("could not open {$sqlPath} for writing");
        }

        fwrite($fh, '-- LSPU EIS database backup — generated '.date('Y-m-d H:i:s')."\n");
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = array_map(static fn ($row) => array_values((array) $row)[0], DB::select('SHOW TABLES'));

        foreach ($tables as $table) {
            $createRow = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
            fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($fh, $createRow['Create Table'].";\n\n");

            $rows = DB::select("SELECT * FROM `{$table}`");
            if (!empty($rows)) {
                $fields = array_keys((array) $rows[0]);
                foreach ($rows as $row) {
                    $row = (array) $row;
                    $values = array_map(
                        fn ($v) => $v === null ? 'NULL' : "'".addslashes((string) $v)."'",
                        $row
                    );
                    fwrite($fh, "INSERT INTO `{$table}` (`".implode('`,`', $fields)."`) VALUES (".implode(',', $values).");\n");
                }
                fwrite($fh, "\n");
            }
        }

        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);

        $gzPath = "{$sqlPath}.gz";
        $gz = gzopen($gzPath, 'w9');
        gzwrite($gz, file_get_contents($sqlPath));
        gzclose($gz);
        unlink($sqlPath);

        return $gzPath;
    }

    private function backupUploads(string $backupDir, string $timestamp): string
    {
        $uploadsDir = \App\Core\Uploader::basePath();
        $zipPath = "{$backupDir}/uploads_{$timestamp}.zip";

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            throw new \RuntimeException("could not create {$zipPath}");
        }

        if (is_dir($uploadsDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($uploadsDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = substr($file->getPathname(), strlen($uploadsDir) + 1);
                    $zip->addFile($file->getPathname(), str_replace('\\', '/', $relativePath));
                }
            }
        }

        $zip->close();

        return $zipPath;
    }

    private function pruneOldBackups(string $backupDir): void
    {
        $retentionDays = (int) env('BACKUP_RETENTION_DAYS', 30);
        $cutoff = strtotime("-{$retentionDays} days");

        foreach (array_merge(glob("{$backupDir}/db_*.sql.gz"), glob("{$backupDir}/uploads_*.zip")) as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                error_log('backup: pruned old backup '.basename($file));
            }
        }
    }
}
