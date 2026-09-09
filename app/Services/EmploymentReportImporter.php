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
     */
    public function import(string $path, ?int $campusId, ?int $year, ?callable $onProgress = null): array
    {
        $this->onProgress = $onProgress;

        return $this->run($path, $campusId, $year, false);
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
    public function preview(string $path, ?int $campusId = null, ?int $year = null): array
    {
        return $this->run($path, $campusId, $year, true);
    }

    private function run(string $path, ?int $campusId, ?int $year, bool $dryRun): array
    {
        // Large tracer workbooks (20+ sheets) are heavy to parse.
        @ini_set('memory_limit', '1024M');
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }

        // Keep number formats (so date cells read as "July 5, 2023" etc.),
        // but skip charts to save memory. rangeToArray below is what makes
        // this fast; per-cell reads were the bottleneck.
        $this->publishProgress('reading', true);

        $reader = IOFactory::createReaderForFile($path);
        $reader->setIncludeCharts(false);
        $book = $reader->load($path);

        // First pass: read every sheet's grid + header once, and total up the
        // data rows so the progress bar has a real denominator.
        $pending = [];
        foreach ($book->getAllSheets() as $sheet) {
            $title = $sheet->getTitle();
            $rows = $this->sheetGrid($sheet);
            $map = $this->findHeaderMap($rows);

            if ($map === null) {
                $this->result['sheets'][$title] = 'no graduate header — skipped';

                continue;
            }
            $dataRows = max(0, count($rows) - $map['_data_from']);
            $this->progressTotal += $dataRows;
            $pending[] = [$title, $rows, $map];
        }
        $this->publishProgress('importing', true);

        // Second pass: process rows, ticking progress as we go.
        foreach ($pending as [$title, $rows, $map]) {
            $count = 0;
            foreach ($rows as $rowNo => $cells) {
                if ($rowNo <= $map['_data_from']) {
                    continue;
                }
                $this->progressDone++;
                $this->publishProgress('importing');

                $rec = $this->readRecord($cells, $map);
                if (!$this->isGraduate($rec)) {
                    continue;
                }
                $count++;
                $this->result['graduate_rows']++;
                try {
                    $this->importRecord("{$title}!{$rowNo}", $rec, $campusId, $year, $dryRun);
                } catch (\Throwable $e) {
                    $this->result['skipped']++;
                    $this->result['errors'][] = "{$title}!{$rowNo}: ".$e->getMessage();
                }
            }
            $this->result['sheets'][$title] = "{$count} graduate rows";
        }
        $this->progressDone = $this->progressTotal;
        $this->publishProgress('done', true);

        $this->result['errors'] = array_slice($this->result['errors'], 0, 100);
        $this->result['warnings'] = array_slice(array_values(array_unique($this->result['warnings'])), 0, 100);
        $this->result['skipped_details'] = array_slice($this->result['skipped_details'], 0, 100);
        $this->result['samples'] = array_slice($this->result['samples'], 0, 25);

        return $this->result;
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
            $this->result['warnings'][] = "Unknown program \"{$rec['program']}\" — kept as-is, college blank.";
        }

        $email = mb_strtolower(trim($rec['email'] ?? ''));
        $placeholder = false;
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = $this->placeholderEmail($firstName, $lastName, $gradYear, $ref);
            $placeholder = true;
        }
        if ($this->emailExists($email)) {
            $this->skip($ref, "{$lastName}, {$firstName}", "email already registered ({$email})");

            return;
        }

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

        $plainPassword = bin2hex(random_bytes(5));
        $descParts = array_filter([
            ($rec['industry'] ?? '') !== '' ? "Industry: {$rec['industry']}" : null,
            ($rec['relevance'] ?? '') !== '' ? "Relevance: {$rec['relevance']}" : null,
            ($rec['income'] ?? '') !== '' ? "Monthly income: {$rec['income']}" : null,
            ($rec['green_job'] ?? '') !== '' ? "Green job: {$rec['green_job']}" : null,
            ($rec['company_addr'] ?? '') !== '' ? "Company address: {$rec['company_addr']}" : null,
            'Imported from Data on Employment report.',
        ]);

        DB::transaction(function () use (
            $email, $placeholder, $plainPassword, $firstName, $middleName, $lastName, $birthdate, $contact,
            $gender, $civilStatus, $city, $province, $gradYear, $college, $course, $campusId,
            $company, $position, $expStart, $gradDate, $statusAfter, $sector, $location, $descParts, $wantExperience
        ): void {
            $userId = DB::table('user')->insertGetId([
                'email' => $email,
                'password' => password_hash($plainPassword, PASSWORD_DEFAULT),
                'user_role' => 'alumni',
                'status' => $placeholder ? 'Inactive' : 'Active',
                'created_at' => now(),
            ]);

            $alumniId = DB::table('alumni')->insertGetId([
                'user_id' => $userId,
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
                'created_at' => now(),
            ]);

            if ($course !== '') {
                DB::table('alumni_education')->insert([
                    'alumni_id' => $alumniId,
                    'degree' => $course,
                    'school' => 'Laguna State Polytechnic University',
                    'start_date' => null,
                    'end_date' => $gradDate,
                    'current' => 0,
                    'created_at' => now(),
                ]);
            }

            if ($wantExperience) {
                DB::table('alumni_experience')->insert([
                    'alumni_id' => $alumniId,
                    'title' => $position !== '' ? $position : 'Not specified',
                    'company' => $company,
                    'start_date' => $expStart,
                    'end_date' => null,
                    'current' => 1,
                    'description' => implode("\n", $descParts),
                    'location_of_work' => $location,
                    'employment_status' => $statusAfter !== '' ? $statusAfter : null,
                    'employment_sector' => $sector,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->result['experience_rows']++;
            }
        });

        $this->result['imported']++;
        if ($placeholder) {
            $this->result['placeholder_emails']++;
        }

        // Bulk-email safety valve: honour the "New Account Emails" setting,
        // but stop after 200 so a large import can't time out mid-run
        // sending hundreds of SMTP messages synchronously.
        if (!$placeholder && $this->sendCredentialEmails && $this->result['emailed'] < 200
            && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                $name = trim("{$firstName} {$lastName}");
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
                $this->result['warnings'][] = "Could not email {$email}: ".$e->getMessage();
            }
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
        $this->result['skipped_details'][] = "{$ref} ({$who}): {$reason}";
    }
}
