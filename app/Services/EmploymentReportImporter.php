<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Imports graduates from an LSPU "Data on Employment" (graduate tracer)
 * workbook into `alumni` (+ a seed `alumni_education` row for the LSPU
 * degree and an `alumni_experience` row for the current job when one is
 * given). Every graduate also gets a `user` row (role alumni) so the
 * alumni record has an owner.
 *
 * The workbooks are messy: one sheet per college, plus summary / pivot /
 * template sheets, two-row headers, subtotal rows, and a right-side
 * summary pivot. Rather than assume fixed columns, this reads EVERY sheet,
 * finds the "Name of Graduates" header row on it (merging the two header
 * rows), maps columns by label, and skips any sheet without that header.
 *
 * Campus and graduation year are supplied by the caller (chosen in the
 * import dialog) — not read from the sheet — so one file is imported for
 * one campus/year at a time. Only the per-graduate fields (name, gender,
 * program, email, job, …) come from the sheet.
 */
class EmploymentReportImporter
{
    /** header label (regex) => canonical field key */
    private const FIELDS = [
        'seq' => '/^(no\.?|#|count)$/i',
        'program' => '/program\s*name|^program$|^course$/i',
        'name' => '/name of graduate/i',
        'gender' => '/^gender$|^sex$/i',
        'grad_date' => '/date of graduation/i',
        'hired_date' => '/date hired/i',
        'status_after' => '/status of employment after/i',
        'sector' => '/^sector/i',
        'location' => '/^location of employment/i',
        'income' => '/average monthly income/i',
        'company_pos' => '/company\s*&\s*position|company and position/i',
        'industry' => '/type of industry|nature of work/i',
        'company_addr' => '/company address/i',
        'relevance' => '/employed[- ]aligned|relevance of employment/i',
        'contact' => '/contact number|contact no|mobile/i',
        'email' => '/e-?mail/i',
        'civil_status' => '/civil status/i',
        'birthday' => '/birth\s?day|birth\s?date/i',
        'home_address' => '/home address|permanent address/i',
        'green_job' => '/green\s?jobs?/i',
    ];

    /** @var array<string,string> normalised program code => canonical course name */
    private array $programMap = [];

    /** @var array<string,string> upper course name => college */
    private array $courseToCollege = [];

    private bool $sendCredentialEmails;

    /** Rows written per transaction / multi-row INSERT / checkpoint (config('import.chunk_size')). */
    private int $batchSize = 1000;

    /** Rows per INSERT statement (see insertInParts()). */
    private const INSERT_ROWS = 1000;

    /** Upper bound for the human-readable lists kept in the result (errors, skipped rows, warnings). */
    private const DETAIL_CAP = 100;

    /** Max credential e-mails sent synchronously per import (each is a live SMTP call). */
    private const EMAIL_CAP = 200;

    /**
     * Stored for imported accounts that are NOT e-mailed a password. password_verify() can never match it, which is
     * exactly what the old random-and-discarded password gave (nobody ever learned it) - without the ~50 ms of
     * bcrypt CPU per row that made a 100k import take ~100 minutes. Those graduates activate via "Forgot password".
     */
    private const UNUSABLE_PASSWORD = '!';

    /** @var array<int, array<string,mixed>> rows waiting for the next multi-row write */
    private array $batch = [];

    /** @var array<string,true> e-mails already queued in this run (the DB can't see them until the batch flushes) */
    private array $seenEmails = [];

    private int $emailQuota = self::EMAIL_CAP;

    /** @var array<string,true> warning texts already recorded (one per distinct message) */
    private array $warnSeen = [];

    /**
     * Queue-job support. $resume = the state saved by the last checkpoint {sheet,row,done,email_quota,result}: sheets before
     * it are skipped and the current sheet continues after `row`. $checkpoint runs INSIDE each chunk's write transaction, so
     * the chunk and its resume point commit (or roll back) together. It may throw ImportAborted (lease lost / cancelled).
     */
    private ?array $resume = null;
    private $checkpoint = null;
    /** @var array{0:int,1:int} [sheet index, row number] of the row being handled - everything up to it is in the batch/DB */
    private array $cursor = [0, 0];

    /** Called with ['phase','done','total','imported'] as rows are processed — the controller streams these to the browser. */
    private $onProgress = null;
    private int $progressTotal = 0;
    private int $progressDone = 0;
    private float $progressLastFlush = 0.0;

    private array $result = [
        'graduate_rows' => 0,
        'imported' => 0,
        'skipped' => 0,
        'placeholder_emails' => 0,
        'experience_rows' => 0,
        'emailed' => 0,
        'sheets' => [],
        'warnings' => [],
        'errors' => [],
        'skipped_details' => [],
        'samples' => [],
    ];

    public function __construct()
    {
        $this->sendCredentialEmails = (new SiteSetting())->newAccountEmailEnabled();
        if (function_exists('config')) {
            $this->batchSize = max(50, min(10000, (int) config('import.chunk_size', 1000)));
        }

        foreach ((require dirname(__DIR__).'/Config/import_codes.php')['program'] as $code => $name) {
            $this->programMap[$this->normCode($code)] = $name;
        }

        foreach (require dirname(__DIR__).'/Config/campus_programs.php' as $colleges) {
            foreach ($colleges as $college => $courses) {
                foreach ($courses as $course) {
                    $this->courseToCollege[mb_strtoupper($course)] = $college;
                }
            }
        }
    }

    /**
     * @param  callable|null  $onProgress  fn(array{phase:string,done:int,total:int,imported:int}): void
     * @param  string|null  $clientExtension  original upload extension (PHP temp files have none)
     */
    public function import(string $path, ?int $campusId, ?int $year, ?callable $onProgress = null, ?string $clientExtension = null, ?array $resume = null, ?callable $checkpoint = null): array
    {
        $this->onProgress = $onProgress;
        $this->checkpoint = $checkpoint;
        if ($resume !== null && isset($resume['result'])) {
            $this->resume = $resume;
            $this->result = $resume['result'] + $this->result;
            $this->progressDone = (int) ($resume['done'] ?? 0);
            $this->emailQuota = (int) ($resume['email_quota'] ?? $this->emailQuota);
            foreach ($this->result['warnings'] as $w) {
                $this->warnSeen[$w] = true;
            }
        }

        return $this->run($path, $campusId, $year, false, $clientExtension);
    }

    private function publishProgress(string $phase, bool $force = false): void
    {
        if ($this->onProgress === null) {
            return;
        }
        $now = microtime(true);
        if (!$force && ($now - $this->progressLastFlush) < 0.2) {
            return;
        }
        $this->progressLastFlush = $now;
        ($this->onProgress)([
            'phase' => $phase,
            'done' => $this->progressDone,
            'total' => $this->progressTotal,
            'imported' => $this->result['imported'],
        ]);
    }

    /** Parse + map every row but write nothing (testing / pre-import preview). */
    public function preview(string $path, ?int $campusId = null, ?int $year = null, ?string $clientExtension = null): array
    {
        return $this->run($path, $campusId, $year, true, $clientExtension);
    }

    private function run(string $path, ?int $campusId, ?int $year, bool $dryRun, ?string $clientExtension = null): array
    {
        @ini_set('memory_limit', '1024M');
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }

        $this->publishProgress('reading', true);

        // .xlsx / .csv stream row by row in constant memory (a 100k-row workbook used to need >1 GB in PhpSpreadsheet).
        // Legacy .xls still goes through PhpSpreadsheet.
        $sheets = [];
        $legacyBook = null;
        if (SpreadsheetRowStream::supports($path) || in_array(strtolower((string) $clientExtension), ['xlsx', 'csv'], true)) {
            $stream = SpreadsheetRowStream::open($path, $clientExtension);
            foreach ($stream->sheets() as $sh) {
                $ref = $sh['ref'];
                $sheets[] = ['name' => $sh['name'], 'rows' => $sh['rows'], 'open' => static fn () => $stream->rows($ref)];
            }
        } else {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setIncludeCharts(false);
            $legacyBook = $reader->load($path);
            foreach ($legacyBook->getAllSheets() as $sheet) {
                $grid = $this->sheetGrid($sheet);
                $sheets[] = ['name' => $sheet->getTitle(), 'rows' => count($grid), 'open' => static fn () => $grid];
            }
        }

        // Progress denominator = estimated rows across sheets (sheet <dimension>); corrected to exact at the end.
        foreach ($sheets as $sh) {
            $this->progressTotal += $sh['rows'];
        }
        $this->publishProgress('importing', true);

        $resumeSheet = $this->resume['sheet'] ?? -1;
        foreach ($sheets as $idx => $sh) {
            if ($idx < $resumeSheet) {
                continue;   // finished before the checkpoint
            }
            $this->processSheet($idx, $sh['name'], ($sh['open'])(), $campusId, $year, $dryRun);
        }
        $this->flushBatch();
        unset($legacyBook);

        $this->progressTotal = max($this->progressTotal, $this->progressDone);
        $this->progressDone = $this->progressTotal;
        $this->publishProgress('done', true);

        $this->result['errors'] = array_slice($this->result['errors'], 0, 100);
        $this->result['warnings'] = array_slice(array_values(array_unique($this->result['warnings'])), 0, 100);
        $this->result['skipped_details'] = array_slice($this->result['skipped_details'], 0, self::DETAIL_CAP);
        $this->result['samples'] = array_slice($this->result['samples'], 0, 25);

        return $this->result;
    }

    /**
     * Finds the header in the first rows, then streams the rest. The header scan needs random access to ~45 rows only.
     *
     * @param  iterable<int, string[]>  $rows  1-based row number => cells
     */
    private function processSheet(int $sheetIdx, string $title, iterable $rows, ?int $campusId, ?int $year, bool $dryRun): void
    {
        $resumeRow = ($this->resume !== null && ($this->resume['sheet'] ?? -1) === $sheetIdx) ? (int) ($this->resume['row'] ?? 0) : 0;
        $gen = (static function () use ($rows) {
            yield from $rows;
        })();

        $buffer = [];
        while ($gen->valid() && $gen->key() <= 45) {
            $buffer[$gen->key()] = $gen->current();
            $gen->next();
        }
        $last = $buffer === [] ? 0 : max(array_keys($buffer));
        for ($i = 1; $i <= $last; ++$i) {
            $buffer[$i] ??= [];   // blank rows exist in the old grid too; the header heuristics look one row ahead
        }
        ksort($buffer);

        $map = $this->findHeaderMap($buffer);
        if ($map === null) {
            $this->result['sheets'][$title] = 'no graduate header — skipped';

            return;
        }

        $count = 0;
        $handle = function (int $rowNo, array $cells) use ($sheetIdx, $resumeRow, $map, $title, $campusId, $year, $dryRun, &$count): void {
            if ($rowNo <= $map['_data_from'] || $rowNo <= $resumeRow) {
                return;   // header, or already imported before the checkpoint (progressDone was restored from it)
            }
            $this->cursor = [$sheetIdx, $rowNo];
            $this->progressDone++;
            $this->publishProgress('importing');

            $rec = $this->readRecord($cells, $map);
            if (!$this->isGraduate($rec)) {
                return;
            }
            $count++;
            $this->result['graduate_rows']++;
            try {
                $this->importRecord("{$title}!{$rowNo}", $rec, $campusId, $year, $dryRun);
            } catch (\Throwable $e) {
                if ($e instanceof ImportAborted) {
                    throw $e;
                }
                $this->result['skipped']++;
                $this->note('errors', "{$title}!{$rowNo}: ".$e->getMessage());
            }
        };

        foreach ($buffer as $rowNo => $cells) {
            $handle($rowNo, $cells);
        }
        unset($buffer);
        while ($gen->valid()) {
            $handle($gen->key(), $gen->current());
            $gen->next();
        }
        $this->result['sheets'][$title] = $resumeRow > 0 ? "{$count} graduate rows (after resume)" : "{$count} graduate rows";
    }

    /** Appends to a capped human-readable list; the matching counters keep counting past the cap. */
    private function note(string $list, string $text): void
    {
        if (count($this->result[$list]) < self::DETAIL_CAP) {
            $this->result[$list][] = $text;
        }
    }

    private function warn(string $text): void
    {
        if (!isset($this->warnSeen[$text]) && count($this->result['warnings']) < self::DETAIL_CAP) {
            $this->warnSeen[$text] = true;
            $this->result['warnings'][] = $text;
        }
    }

    /* ------------------------------------------------------------------ *
     *  Sheet reading + header detection
     * ------------------------------------------------------------------ */

    /** @return array<int, array<int,string>> 1-based row => 0-based col => displayed value */
    private function sheetGrid($sheet): array
    {
        $lastRow = $sheet->getHighestDataRow();
        $lastCol = min(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), 40);
        $endCol = Coordinate::stringFromColumnIndex($lastCol);

        // rangeToArray is far cheaper than getCell() per cell.
        // args: nullValue, calculateFormulas=true (so pivot totals don't break
        // text cells), formatData=true (keeps "July 5, 2023" etc. as shown),
        // returnCellRef=false. Re-key to real 1-based row numbers.
        $grid = [];
        $rowNo = 0;
        foreach ($sheet->rangeToArray("A1:{$endCol}{$lastRow}", null, true, true, false) as $cells) {
            $grid[++$rowNo] = array_map(static fn ($v) => trim((string) ($v ?? '')), array_values($cells));
        }

        return $grid;
    }

    /**
     * Finds the header row (the one carrying "Name of Graduates") and
     * merges it with the row below for the grouped sub-headers. Returns
     * ['field' => colIndex, ..., '_data_from' => rowNo] or null.
     */
    private function findHeaderMap(array $rows): ?array
    {
        foreach ($rows as $rowNo => $cells) {
            if ($rowNo > 25) {
                break;
            }
            $hasName = false;
            foreach ($cells as $v) {
                if ($v !== '' && preg_match(self::FIELDS['name'], $v)) {
                    $hasName = true;
                    break;
                }
            }
            if (!$hasName) {
                continue;
            }

            $second = $rows[$rowNo + 1] ?? [];
            $map = [];
            $matched = 0;
            $colCount = max(count($cells), count($second));
            for ($c = 0; $c < $colCount; $c++) {
                $label = trim($cells[$c] ?? '');
                $label2 = trim($second[$c] ?? '');
                foreach (self::FIELDS as $field => $rx) {
                    if (isset($map[$field])) {
                        continue;
                    }
                    if (($label !== '' && preg_match($rx, $label)) || ($label2 !== '' && preg_match($rx, $label2))) {
                        $map[$field] = $c;
                        $matched++;
                        break;
                    }
                }
            }

            if ($matched >= 4 && isset($map['name'])) {
                $map['_data_from'] = $rowNo + 1; // header is 2 rows; data starts after

                // "Name of Graduates" is usually a merged header over an
                // unlabelled running-number column + the real names column.
                // If the mapped column is mostly integers and the next one
                // holds names, shift.
                $nameCol = $map['name'];
                $numeric = 0;
                $textNext = 0;
                $seen = 0;
                for ($rr = $rowNo + 2; $rr <= $rowNo + 16 && isset($rows[$rr]); $rr++) {
                    $a = trim($rows[$rr][$nameCol] ?? '');
                    $b = trim($rows[$rr][$nameCol + 1] ?? '');
                    if ($a === '' && $b === '') {
                        continue;
                    }
                    $seen++;
                    if (ctype_digit($a)) {
                        $numeric++;
                    }
                    if (preg_match('/\p{L}.*,|\p{L}{3,}/u', $b)) {
                        $textNext++;
                    }
                }
                if ($seen > 0 && $numeric >= $seen * 0.6 && $textNext >= $seen * 0.5) {
                    $map['seq'] = $nameCol;
                    $map['name'] = $nameCol + 1;
                }

                return $map;
            }
        }

        return null;
    }

    /** @return array<string,string> field => raw value */
    private function readRecord(array $cells, array $map): array
    {
        $rec = [];
        foreach ($map as $field => $col) {
            if ($field[0] === '_') {
                continue;
            }
            $rec[$field] = trim($cells[$col] ?? '');
        }
        // green job can drift with the pivot — scan the tail if unmapped/blank
        if (($rec['green_job'] ?? '') === '') {
            for ($i = count($cells) - 1; $i >= 20; $i--) {
                $v = strtoupper(trim($cells[$i] ?? ''));
                if ($v === 'YES' || $v === 'NO') {
                    $rec['green_job'] = ucfirst(strtolower($v));
                    break;
                }
            }
        }

        return $rec;
    }

    private function isGraduate(array $rec): bool
    {
        $name = $rec['name'] ?? '';
        if ($name === '' || mb_strlen($name) < 3) {
            return false;
        }
        if (preg_match('/^(BACHELOR|MASTER|DOCTOR|DIPLOMA|ASSOCIATE|TOTAL|SUB\s*-?\s*TOTAL)\b/i', $name)) {
            return false;
        }
        if (preg_match('/^\d+\s*(REG|REGISTERED|GRAD|GRADUATES?)\b/i', $name)) {
            return false;
        }
        if (preg_match(self::FIELDS['name'], $name)) {   // the header label itself
            return false;
        }
        // real people rows are "LAST, FIRST ..." or at least contain letters
        if (!preg_match('/\p{L}/u', $name)) {
            return false;
        }
        // subtotal rows like "99 REG" land in seq, name is blank — already out.
        // reject a bare number that slipped into name
        if (preg_match('/^[\d.\s]+$/', $name)) {
            return false;
        }

        return true;
    }

    /* ------------------------------------------------------------------ *
     *  Record import
     * ------------------------------------------------------------------ */

    private function importRecord(string $ref, array $rec, ?int $campusId, ?int $forcedYear, bool $dryRun): void
    {
        [$firstName, $middleName, $lastName] = $this->splitName($rec['name'] ?? '');
        if ($firstName === '' && $lastName === '') {
            $this->skip($ref, $rec['name'] ?? '?', 'could not read a name');

            return;
        }

        $gender = $this->normalizeGender($rec['gender'] ?? '');
        [$gradYearParsed, $gradDate] = $this->parseGraduationDate($rec['grad_date'] ?? '');
        $gradYear = $forcedYear ?: $gradYearParsed;
        if ($gradDate === null && $gradYear) {
            $gradDate = sprintf('%04d-06-01', $gradYear);
        }
        $hiredDate = $this->parseLooseDate($rec['hired_date'] ?? '');
        $birthdate = $this->parseLooseDate($rec['birthday'] ?? '', true);

        [$course, $college, $known] = $this->resolveProgram($rec['program'] ?? '');
        if (!$known && ($rec['program'] ?? '') !== '') {
            $this->warn("Unknown program \"{$rec['program']}\" — kept as-is, college blank.");
        }

        $email = mb_strtolower(trim($rec['email'] ?? ''));
        $placeholder = false;
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = $this->placeholderEmail($firstName, $lastName, $gradYear, $ref);
            $placeholder = true;
        }
        if (isset($this->seenEmails[$email]) || ($dryRun && $this->emailExists($email))) {
            $this->skip($ref, "{$lastName}, {$firstName}", "email already registered ({$email})");

            return;
        }
        $this->seenEmails[$email] = true;   // real-import existence check happens once per batch in flushBatch()

        [$company, $position] = $this->splitCompanyPosition($rec['company_pos'] ?? '');
        $statusAfter = trim($rec['status_after'] ?? '');
        $sector = $this->normalizeSector($rec['sector'] ?? '');
        $location = $this->normalizeLocation($rec['location'] ?? '');
        $contact = $this->normalizeContact($rec['contact'] ?? '');
        [$city, $province] = $this->splitCityProvince($rec['home_address'] ?? '');
        $civilStatus = $this->normalizeCivilStatus($rec['civil_status'] ?? '');

        $expStart = $hiredDate ?: ($gradYear ? sprintf('%04d-07-01', $gradYear) : null);
        $wantExperience = $company !== '' && $expStart !== null && stripos($statusAfter, 'unemploy') === false;

        if ($dryRun) {
            $this->result['imported']++;
            if ($placeholder) {
                $this->result['placeholder_emails']++;
            }
            if ($wantExperience) {
                $this->result['experience_rows']++;
            }
            $this->result['samples'][] = [
                'row' => $ref,
                'name' => trim("{$firstName} {$middleName} {$lastName}"),
                'gender' => $gender,
                'email' => $email.($placeholder ? ' (placeholder)' : ''),
                'contact' => $contact,
                'birthdate' => $birthdate,
                'year_graduated' => $gradYear,
                'campus_id' => $campusId,
                'course' => $course,
                'college' => $college,
                'city' => $city,
                'province' => $province,
                'job' => $wantExperience ? trim("{$position} @ {$company}")." [{$statusAfter}/{$sector}/{$location}, from {$expStart}]" : '—',
            ];

            return;
        }

        $descParts = array_filter([
            ($rec['industry'] ?? '') !== '' ? "Industry: {$rec['industry']}" : null,
            ($rec['relevance'] ?? '') !== '' ? "Relevance: {$rec['relevance']}" : null,
            ($rec['income'] ?? '') !== '' ? "Monthly income: {$rec['income']}" : null,
            ($rec['green_job'] ?? '') !== '' ? "Green job: {$rec['green_job']}" : null,
            ($rec['company_addr'] ?? '') !== '' ? "Company address: {$rec['company_addr']}" : null,
            'Imported from Data on Employment report.',
        ]);

        // Bulk-email safety valve (unchanged): honour the "New Account Emails" setting, cap at EMAIL_CAP.
        $emailable = !$placeholder && $this->sendCredentialEmails && $this->emailQuota > 0;
        if ($emailable) {
            --$this->emailQuota;
        }
        $plainPassword = $emailable ? bin2hex(random_bytes(5)) : null;

        $this->batch[] = [
            'ref' => $ref, 'email' => $email, 'placeholder' => $placeholder, 'plain' => $plainPassword,
            'user' => [
                'email' => $email,
                'password' => $plainPassword !== null ? password_hash($plainPassword, PASSWORD_DEFAULT) : self::UNUSABLE_PASSWORD,
                'user_role' => 'alumni',
                'status' => $placeholder ? 'Inactive' : 'Active',
            ],
            'alumni' => [
                'first_name' => $firstName,
                'middle_name' => $middleName !== '' ? $middleName : null,
                'last_name' => $lastName,
                'birthdate' => $birthdate,
                'contact' => $contact,
                'gender' => $gender,
                'civil_status' => $civilStatus,
                'city' => $city,
                'province' => $province,
                'year_graduated' => $gradYear,
                'college' => $college,
                'course' => $course,
                'campus_id' => $campusId,
                'verification_document' => '',
            ],
            'education' => $course !== '' ? [
                'degree' => $course,
                'school' => 'Laguna State Polytechnic University',
                'start_date' => null,
                'end_date' => $gradDate,
                'current' => 0,
            ] : null,
            'experience' => $wantExperience ? [
                'title' => $position !== '' ? $position : 'Not specified',
                'company' => $company,
                'start_date' => $expStart,
                'end_date' => null,
                'current' => 1,
                'description' => implode("\n", $descParts),
                'location_of_work' => $location,
                'employment_status' => $statusAfter !== '' ? $statusAfter : null,
                'employment_sector' => $sector,
            ] : null,
            'name' => trim("{$firstName} {$lastName}"),
        ];

        if (count($this->batch) >= $this->batchSize) {
            $this->flushBatch();
        }
    }

    /**
     * Writes the queued graduates: ONE existence query, then multi-row INSERTs inside ONE short transaction
     * (was: 1 SELECT + 1 transaction + 3-4 single-row INSERTs + 1 bcrypt per graduate).
     *
     * Queue-job safety: the resume checkpoint is written INSIDE that same transaction, so a chunk and the pointer to the next
     * chunk commit atomically - a crash or a lost lease can neither lose nor repeat a chunk. Should a retry ever re-read rows
     * that ARE committed (e.g. a fallback path), the existence check above skips them, and `user.email` is UNIQUE, so nothing
     * is imported twice. If a batch fails (e.g. a concurrent import took one of the e-mails) it is retried row by row so
     * only the bad row is skipped.
     */
    private function flushBatch(): void
    {
        if ($this->batch === []) {
            return;
        }
        $batch = $this->batch;
        $this->batch = [];

        $existing = [];
        foreach (array_chunk(array_column($batch, 'email'), 1000) as $emails) {
            foreach (DB::table('user')->whereIn('email', $emails)->pluck('email') as $e) {
                $existing[mb_strtolower($e)] = true;
            }
        }
        $todo = [];
        foreach ($batch as $row) {
            if (isset($existing[$row['email']])) {
                $this->skip($row['ref'], $row['name'], "email already registered ({$row['email']})");
            } else {
                $todo[] = $row;
            }
        }
        if ($todo === []) {
            $this->saveCheckpoint();

            return;
        }

        $delta = [
            'imported' => count($todo),
            'placeholder_emails' => count(array_filter($todo, static fn ($r) => $r['placeholder'])),
            'experience_rows' => count(array_filter($todo, static fn ($r) => $r['experience'] !== null)),
        ];

        $fellBack = false;
        try {
            DB::transaction(function () use ($todo, $delta) {
                $this->writeRows($todo);
                $this->saveCheckpoint($delta);
            }, 3);   // 3 attempts: a deadlock against a concurrent import is retried instead of failing the chunk
            $written = $todo;
        } catch (ImportAborted $e) {
            throw $e;
        } catch (\Throwable) {
            $fellBack = true;
            $written = [];
            foreach ($todo as $row) {
                try {
                    DB::transaction(fn () => $this->writeRows([$row]));
                    $written[] = $row;
                } catch (\Throwable $e) {
                    $this->result['skipped']++;
                    $this->note('errors', "{$row['ref']}: ".$e->getMessage());
                }
            }
        }

        foreach ($written as $row) {
            $this->result['imported']++;
            if ($row['placeholder']) {
                $this->result['placeholder_emails']++;
            }
            if ($row['experience'] !== null) {
                $this->result['experience_rows']++;
            }
        }
        if ($fellBack) {
            $this->saveCheckpoint();
        }
        foreach ($written as $row) {
            if ($row['plain'] !== null) {
                $this->sendCredentialEmail($row['email'], $row['name'], $row['plain']);
            }
        }
    }

    /** Hands the resume point + counters to the queue job (inside the chunk's transaction). $delta = counters not yet applied. */
    private function saveCheckpoint(array $delta = []): void
    {
        if ($this->checkpoint === null) {
            return;
        }
        $result = $this->result;
        foreach ($delta as $key => $n) {
            $result[$key] += $n;
        }
        ($this->checkpoint)([
            'sheet' => $this->cursor[0],
            'row' => $this->cursor[1],
            'done' => $this->progressDone,
            'total' => $this->progressTotal,
            'email_quota' => $this->emailQuota,
            'result' => $result,
        ]);
    }

    private function writeRows(array $rows): void
    {
        $now = now();
        $stamp = static fn (array $r): array => $r + ['created_at' => $now];

        $this->insertInParts('user', array_map(static fn ($r) => $stamp($r['user']), $rows));
        $userIds = DB::table('user')->whereIn('email', array_column($rows, 'email'))->pluck('user_id', 'email');

        $this->insertInParts('alumni', array_map(fn ($r) => $stamp($r['alumni'] + ['user_id' => $userIds[$r['email']]]), $rows));
        $alumniIds = DB::table('alumni')->whereIn('user_id', $userIds->values()->all())->pluck('alumni_id', 'user_id');

        $edu = [];
        $exp = [];
        foreach ($rows as $r) {
            $aid = $alumniIds[$userIds[$r['email']]];
            if ($r['education'] !== null) {
                $edu[] = $stamp($r['education'] + ['alumni_id' => $aid]);
            }
            if ($r['experience'] !== null) {
                $exp[] = $r['experience'] + ['alumni_id' => $aid, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        $this->insertInParts('alumni_education', $edu);
        $this->insertInParts('alumni_experience', $exp);
    }

    /**
     * Multi-row INSERTs of at most INSERT_ROWS rows. PDO allows 65,535 bound parameters per statement: `alumni` has 16 columns, so
     * one 5,000-row statement (80,000 parameters) fails - measured: a 5,000-row chunk made EVERY chunk fall back to the row-by-row
     * path and the 100k import did not finish in 600 s. Sub-batching makes any chunk size safe.
     */
    private function insertInParts(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::INSERT_ROWS) as $part) {
            DB::table($table)->insert($part);
        }
    }

    private function sendCredentialEmail(string $email, string $name, string $plainPassword): void
    {
        try {
            (new MailService())->send(
                $email,
                $name,
                'Your LSPU EIS Alumni Account',
                MailService::wrap(
                    'Welcome, '.htmlspecialchars($name).'!',
                    '<p>Your alumni account has been created by the administrator and is already active.</p>'
                        .'<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:16px 0;">'
                        ."<p style=\"margin:0 0 6px;\"><strong>Email:</strong> {$email}</p>"
                        ."<p style=\"margin:0;\"><strong>Password:</strong> {$plainPassword}</p>"
                        .'</div>'
                        .'<p>For security, please change your password after logging in.</p>',
                    'Login to LSPU EIS',
                    config('app.url').'/login'
                )
            );
            $this->result['emailed']++;
        } catch (\Throwable $e) {
            $this->warn("Could not email {$email}: ".$e->getMessage());
        }
    }

    /* ------------------------------------------------------------------ *
     *  Field parsers
     * ------------------------------------------------------------------ */

    /** "DELA CRUZ, MARY ROSE R." => ["Mary Rose", "R.", "Dela Cruz"] */
    private function splitName(string $raw): array
    {
        $raw = trim(preg_replace('/\s+/', ' ', $raw));
        if ($raw === '') {
            return ['', '', ''];
        }
        if (str_contains($raw, ',')) {
            [$last, $given] = array_map('trim', explode(',', $raw, 2));
        } else {
            $parts = explode(' ', $raw);
            $last = array_pop($parts);
            $given = implode(' ', $parts);
        }

        $middle = '';
        $tokens = array_values(array_filter(explode(' ', $given), static fn ($t) => $t !== ''));
        if (count($tokens) > 1) {
            $lastTok = end($tokens);
            if (preg_match('/^[A-Za-zÑñ]\.?$/u', $lastTok)) {
                $middle = rtrim($lastTok, '.').'.';
                array_pop($tokens);
            }
        }

        return [$this->titleCase(implode(' ', $tokens)), mb_strtoupper($middle), $this->titleCase($last)];
    }

    private function titleCase(string $s): string
    {
        $s = trim(mb_strtolower($s));

        return $s === '' ? '' : mb_convert_case($s, MB_CASE_TITLE, 'UTF-8');
    }

    private function normalizeGender(string $v): string
    {
        $v = mb_strtoupper(trim($v));

        return match (true) {
            $v === 'M' || str_starts_with($v, 'MALE') => 'Male',
            $v === 'F' || str_starts_with($v, 'FEMALE') => 'Female',
            default => '',
        };
    }

    private function normalizeSector(string $v): string
    {
        return match (true) {
            stripos($v, 'gov') !== false => 'Government',
            stripos($v, 'priv') !== false => 'Private',
            trim($v) === '' => '',
            default => trim($v),
        };
    }

    private function normalizeLocation(string $v): string
    {
        return match (true) {
            stripos($v, 'abroad') !== false || stripos($v, 'overseas') !== false => 'Abroad',
            stripos($v, 'local') !== false => 'Local',
            default => trim($v),
        };
    }

    private function normalizeCivilStatus(string $v): string
    {
        $v = trim($v);
        foreach (['Single', 'Married', 'Widowed', 'Separated', 'Divorced'] as $s) {
            if (strcasecmp($v, $s) === 0) {
                return $s;
            }
        }

        return $v;
    }

    private function normalizeContact(string $v): string
    {
        $d = preg_replace('/\D+/', '', $v);
        if ($d === '') {
            return '';
        }
        if (str_starts_with($d, '63') && strlen($d) === 12) {
            $d = '0'.substr($d, 2);
        } elseif (strlen($d) === 10 && $d[0] === '9') {
            $d = '0'.$d;
        }

        return substr($d, 0, 20);
    }

    /**
     * Real Excel date cells come back as a numeric serial when the sheet is
     * read data-only. Convert those; leave text values alone.
     */
    private function fromExcelSerial(string $v): string
    {
        if (preg_match('/^\d{4,6}(\.\d+)?$/', $v)) {
            $n = (float) $v;
            if ($n >= 20000 && $n <= 60000) {   // ~1954 .. ~2064
                try {
                    return ExcelDate::excelToDateTimeObject($n)->format('Y-m-d');
                } catch (\Throwable) {
                    // fall through
                }
            }
        }

        return $v;
    }

    /** "July 5, 2023" => [2023, "2023-07-05"] */
    private function parseGraduationDate(string $v): array
    {
        $v = $this->fromExcelSerial(trim($v));
        if ($v === '') {
            return [null, null];
        }
        foreach (['F j, Y', 'F j Y', 'M j, Y', 'M j Y', 'j F Y', 'Y-m-d', 'm/d/Y', 'd/m/Y'] as $fmt) {
            $d = \DateTime::createFromFormat('!'.$fmt, $v);
            $err = \DateTime::getLastErrors();
            if ($d instanceof \DateTime && (!$err || ($err['warning_count'] === 0 && $err['error_count'] === 0))) {
                $y = (int) $d->format('Y');
                if ($y >= 1960 && $y <= (int) date('Y') + 1) {
                    return [$y, $d->format('Y-m-d')];
                }
            }
        }
        if (preg_match('/\b(19|20)\d{2}\b/', $v, $m)) {
            return [(int) $m[0], null];
        }

        return [null, null];
    }

    private function parseLooseDate(string $v, bool $isBirthday = false): ?string
    {
        $v = $this->fromExcelSerial(trim(preg_replace('/\s+/', ' ', $v)));
        if ($v === '' || strlen($v) < 4) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {   // already ISO (from a serial)
            return $isBirthday && substr($v, 0, 4) === date('Y') ? null : $v;
        }
        if (preg_match('/^(19|20)\d{2}$/', $v)) {
            return $isBirthday ? null : "{$v}-01-01";
        }
        foreach (['F Y', 'M Y'] as $fmt) {
            $d = \DateTime::createFromFormat('!'.$fmt, $v);
            if ($d instanceof \DateTime && $this->yearOk((int) $d->format('Y'))) {
                return $d->format('Y-m-01');
            }
        }
        foreach (['F j Y', 'F j, Y', 'M j Y', 'M j, Y', 'j F Y', 'j M Y'] as $fmt) {
            $d = \DateTime::createFromFormat('!'.$fmt, $v);
            if ($d instanceof \DateTime && $this->yearOk((int) $d->format('Y'))) {
                return $d->format('Y-m-d');
            }
        }
        if (preg_match('#^(\d{1,4})[/\-.](\d{1,2})[/\-.](\d{1,4})$#', $v, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $c = (int) $m[3];
            if (strlen($m[1]) === 4) {
                [$y, $mo, $day] = [$a, $b, $c];
            } elseif (strlen($m[3]) === 4) {
                $y = $c;
                if ($a > 12 && $b <= 12) {
                    [$day, $mo] = [$a, $b];
                } elseif ($b > 12 && $a <= 12) {
                    [$mo, $day] = [$a, $b];
                } else {
                    [$day, $mo] = [$a, $b];
                }
            } else {
                return null;
            }
            if ($this->yearOk($y) && $mo >= 1 && $mo <= 12 && $day >= 1 && $day <= 31 && checkdate($mo, $day, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $day);
            }
        }

        return null;
    }

    private function yearOk(int $y): bool
    {
        return $y >= 1940 && $y <= (int) date('Y') + 1;
    }

    private function splitCompanyPosition(string $v): array
    {
        $v = trim($v);
        if ($v === '') {
            return ['', ''];
        }
        $pos = strrpos($v, '/');
        if ($pos === false) {
            return [$v, ''];
        }

        return [trim(substr($v, 0, $pos)), trim(substr($v, $pos + 1))];
    }

    private function splitCityProvince(string $v): array
    {
        $v = trim($v);
        if ($v === '') {
            return ['', ''];
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $v)), static fn ($p) => $p !== ''));
        $n = count($parts);
        if ($n >= 2) {
            return [substr($parts[$n - 2], 0, 100), substr($parts[$n - 1], 0, 100)];
        }

        return ['', substr($parts[0] ?? '', 0, 100)];
    }

    /* ------------------------------------------------------------------ *
     *  Lookups
     * ------------------------------------------------------------------ */

    private function normCode(string $s): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim($s)));
    }

    /** @return array{0:string,1:string,2:bool} [course, college, known] */
    private function resolveProgram(string $code): array
    {
        $raw = trim($code);
        if ($raw === '') {
            return ['', '', false];
        }
        $course = $this->programMap[$this->normCode($raw)] ?? null;
        // maybe the sheet already spelled the full course name
        if ($course === null && isset($this->courseToCollege[mb_strtoupper($raw)])) {
            $course = $raw;
        }
        $known = $course !== null;
        if ($course === null) {
            $course = $raw;
        }
        $college = $this->courseToCollege[mb_strtoupper($course)] ?? '';

        return [$course, $college, $known && $college !== ''];
    }

    private function emailExists(string $email): bool
    {
        return DB::table('user')->where('email', $email)->exists();
    }

    private function placeholderEmail(string $first, string $last, ?int $year, string $ref): string
    {
        $slug = static fn (string $s): string => preg_replace('/[^a-z0-9]+/', '', mb_strtolower($s));
        $local = trim($slug($last).'.'.$slug($first), '.') ?: 'graduate';

        return "import.{$local}.".($year ?: '').'.'.$slug($ref).'@no-reply.lspu.invalid';
    }

    private function skip(string $ref, string $who, string $reason): void
    {
        $this->result['skipped']++;
        $this->note('skipped_details', "{$ref} ({$who}): {$reason}");
    }
}
