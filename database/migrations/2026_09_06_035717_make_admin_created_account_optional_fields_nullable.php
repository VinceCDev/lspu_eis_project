<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ISO/IEC 25010 remediation — D4 (Admin Account Creation HTTP 500), full
 * fix. The originally-reported columns (employer.company_location,
 * administrator.address) are VARCHAR with no default — those are fixed
 * by simply passing '' from App\Models\Account (an already-established
 * convention: Employer\ProfileController's own "no profile yet" fallback
 * already uses company_location => '').
 *
 * But comprehensive verification (inserting through the real
 * Account::createEmployer()/createAlumni() methods, not just guessing)
 * found 3 more required-with-no-default columns that '' cannot satisfy
 * under this DB's active strict SQL mode (STRICT_TRANS_TABLES,
 * NO_ZERO_DATE, NO_ZERO_IN_DATE): DATE and INT columns reject an empty
 * string as an invalid value, unlike VARCHAR columns. An admin creating an
 * employer/alumni account through the quick-create modal (which does not
 * collect these fields — they're meant to be completed later via the
 * account holder's own profile) has no real date/year to supply, so NULL
 * is the only semantically correct value; these columns need to accept it.
 *
 * Purely additive: NOT NULL -> NULL, no type change, no data touched.
 * Every existing row already has a real value and is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `employer` MODIFY `date_established` DATE NULL DEFAULT NULL');
        DB::statement('ALTER TABLE `alumni` MODIFY `birthdate` DATE NULL DEFAULT NULL');
        DB::statement('ALTER TABLE `alumni` MODIFY `year_graduated` INT(11) NULL DEFAULT NULL');
    }

    /**
     * Reverting to NOT NULL would fail if any row inserted while this
     * migration was active actually has a NULL value in these columns —
     * intentionally left as a manual step (inspect for NULLs, backfill or
     * decide a real default, then alter) rather than an automatic rollback
     * that could silently corrupt data or fail confusingly.
     */
    public function down(): void
    {
        // Intentionally left as a manual step — see class docblock.
    }
};
