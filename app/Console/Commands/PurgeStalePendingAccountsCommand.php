<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ported from backend/cli/purge_stale_pending_accounts.php. RA 10173
 * retention: deletes alumni/employer accounts still 'Pending' after
 * PENDING_ACCOUNT_RETENTION_DAYS days. Active accounts are never touched.
 */
class PurgeStalePendingAccountsCommand extends Command
{
    protected $signature = 'accounts:purge-stale';
    protected $description = 'Purges alumni/employer accounts still Pending past the retention window (RA 10173).';

    public function handle(): int
    {
        $retentionDays = (int) env('PENDING_ACCOUNT_RETENTION_DAYS', 90);

        $staleAccounts = DB::select(
            "SELECT user_id, email, user_role FROM user
             WHERE status = 'Pending' AND user_role IN ('alumni', 'employer')
             AND created_at < (NOW() - INTERVAL ? DAY)",
            [$retentionDays]
        );

        if (empty($staleAccounts)) {
            $this->info("purge_stale_pending_accounts: nothing to purge (retention: {$retentionDays} days).");

            return 0;
        }

        $accountModel = new Account();
        $auditLog = new AuditLog();
        $purged = 0;

        foreach ($staleAccounts as $account) {
            $account = (array) $account;
            $userId = (int) $account['user_id'];
            $ok = $accountModel->delete($userId, $account['user_role']);

            if ($ok) {
                $auditLog->log(null, null, 'system', 'auto_purge_pending_account', $account['user_role'], $userId,
                    "Purged unapproved {$account['user_role']} registration ({$account['email']}) after {$retentionDays} days pending.");
                ++$purged;
            } else {
                $this->error("purge_stale_pending_accounts: failed to delete user_id={$userId} ({$account['email']})");
            }
        }

        $this->info("purge_stale_pending_accounts: purged {$purged} of ".count($staleAccounts).' stale pending account(s).');

        return 0;
    }
}
