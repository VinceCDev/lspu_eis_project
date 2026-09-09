<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\Report;

class ReportService
{
    /**
     * industryDetermination()/industryAnalysis() call Gemini once per row
     * with no cache. After a large alumni import that is thousands of
     * synchronous API calls per report load — enough to hang the page and
     * starve php-fpm. Cap the live calls; past the cap (or once Gemini has
     * clearly failed) fall back to the local keyword classifier.
     */
    private const MAX_GEMINI_CALLS = 20;

    private Report $report;
    private GeminiClient $gemini;
    private AlignmentService $alignment;
    private ?int $campusId;
    private ?string $college;

    private int $geminiCalls = 0;

    private int $geminiFailures = 0;

    private bool $geminiDown = false;

    public function __construct(?Report $report = null, ?GeminiClient $gemini = null, ?int $campusId = null, ?string $college = null, ?int $yearGraduated = null, ?AlignmentService $alignment = null)
    {
        $this->report = $report ?? new Report($campusId, $college, $yearGraduated);
        $this->gemini = $gemini ?? new GeminiClient();
        $this->alignment = $alignment ?? new AlignmentService($this->gemini);
        $this->campusId = $campusId;
        $this->college = ($college !== null && $college !== '') ? $college : null;
    }

    /**
     * Budget-limited Gemini call. Returns '' when the per-request cap is
     * reached or Gemini looks unreachable, so callers can use a local
     * classifier instead of blocking on hundreds of API round-trips.
     */
    private function askGemini(string $prompt): string
    {
        if ($this->geminiDown || $this->geminiCalls >= self::MAX_GEMINI_CALLS) {
            return '';
        }
        $this->geminiCalls++;

        $out = trim($this->gemini->generate($prompt));
        if ($out === '' && ++$this->geminiFailures >= 2) {
            $this->geminiDown = true;
        }

        return $out;
    }

    public function summary(): array
    {
        $courseStats = [];

        // Graduate + employed tallies straight from SQL (one row per
        // course/college), instead of pulling one row per alumnus (100k+
        // rows) into PHP just to count them.
        foreach ($this->report->programEmploymentAggregate() as $row) {
            $program = Report::normalizeProgram($row['college'], $row['course']);
            $key = $program.'|'.$row['college'];
            if (!isset($courseStats[$key])) {
                $courseStats[$key] = [
                    'course' => $program,
                    'college' => $row['college'],
                    'total_graduates' => 0,
                    'employed_count' => 0,
                    'related_job_count' => 0,
                ];
            }
            $courseStats[$key]['total_graduates'] += (int) $row['total_graduates'];
            $courseStats[$key]['employed_count'] += (int) $row['employed_count'];
        }

        // "Job match rate" needs the alignment label, which isn't expressible
        // in SQL. Classify each DISTINCT (course, title) once — cached — and
        // multiply by its count, rather than calling classifyOne() once per
        // alumnus.
        $titleCounts = $this->report->employedJobTitleCounts();
        $this->alignment->preload(array_map(
            static fn (array $row) => ['course' => $row['course'] ?? '', 'title' => $row['job_title'] ?? ''],
            $titleCounts
        ));
        foreach ($titleCounts as $row) {
            $program = Report::normalizeProgram($row['college'], $row['course']);
            $key = $program.'|'.$row['college'];
            if (!isset($courseStats[$key])) {
                continue;
            }
            if ($this->classifyAlignmentLabel($row['course'], $row['job_title']) === 'aligned') {
                $courseStats[$key]['related_job_count'] += (int) $row['cnt'];
            }
        }

        foreach ($this->zeroFillPrograms(array_keys($courseStats)) as $key => $stats) {
            $courseStats[$key] = $stats;
        }

        $programStats = [];
        foreach ($courseStats as $stats) {
            $employedPct = $stats['total_graduates'] > 0 ? round(($stats['employed_count'] / $stats['total_graduates']) * 100, 2) : 0;
            $relatedPct = $stats['employed_count'] > 0 ? round(($stats['related_job_count'] / $stats['employed_count']) * 100, 2) : 0;

            $programStats[] = array_merge($stats, [
                'employed_percentage' => $employedPct,
                'related_percentage' => $relatedPct,
                'match_rate' => $relatedPct,
            ]);
        }

        $employedCount = $this->report->employedCount();
        $unemployedCount = $this->report->unemployedCount();
        $totalAlumni = $employedCount + $unemployedCount;

        $statusStats = [];
        if ($employedCount > 0) {
            $statusStats[] = ['employment_status_category' => 'Employed', 'count' => $employedCount, 'percentage' => $totalAlumni > 0 ? round(($employedCount / $totalAlumni) * 100, 2) : 0];
        }
        if ($unemployedCount > 0) {
            $statusStats[] = ['employment_status_category' => 'Unemployed', 'count' => $unemployedCount, 'percentage' => $totalAlumni > 0 ? round(($unemployedCount / $totalAlumni) * 100, 2) : 0];
        }

        $totalEmployed = array_sum(array_column($programStats, 'employed_count'));
        $totalRelated = array_sum(array_column($programStats, 'related_job_count'));

        return [
            'success' => true,
            'program_stats' => $programStats,
            'sector_stats' => $this->report->sectorStats(),
            'location_stats' => $this->report->locationStats(),
            'status_stats' => $statusStats,
            'total_alumni' => $totalAlumni,
            'overall_match_rate' => $totalEmployed > 0 ? round(($totalRelated / $totalEmployed) * 100, 2) : 0,
            'total_employed' => $totalEmployed,
            'total_related_jobs' => $totalRelated,
        ];
    }

    /**
     * Zero-count rows for every officially offered program (per the campus
     * scope currently in effect) that has no alumni yet, so the report shows
     * the full picture instead of only programs someone has already
     * registered under. Skips any key already present in $existingKeys.
     */
    private function zeroFillPrograms(array $existingKeys): array
    {
        $campusPrograms = require __DIR__.'/../Config/campus_programs.php';
        $campusNames = $this->campusId !== null
            ? array_filter([$this->campusNameById($this->campusId)])
            : array_keys($campusPrograms);

        $fill = [];
        foreach ($campusNames as $campusName) {
            if (!isset($campusPrograms[$campusName])) {
                continue;
            }
            foreach ($campusPrograms[$campusName] as $college => $courses) {
                if ($this->college !== null && $college !== $this->college) {
                    continue;
                }
                foreach ($courses as $course) {
                    $program = Report::normalizeProgram($college, $course);
                    $key = $program.'|'.$college;
                    if (isset($fill[$key]) || in_array($key, $existingKeys, true)) {
                        continue;
                    }
                    $fill[$key] = [
                        'course' => $program,
                        'college' => $college,
                        'total_graduates' => 0,
                        'employed_count' => 0,
                        'related_job_count' => 0,
                    ];
                }
            }
        }

        return $fill;
    }

    private function campusNameById(int $campusId): ?string
    {
        foreach ((new Campus())->all() as $campus) {
            if ((int) $campus['campus_id'] === $campusId) {
                return $campus['name'];
            }
        }

        return null;
    }

    public function fullReportData(): array
    {
        // Warm AlignmentService once for every distinct (course, job title) in
        // scope. Without this, employmentSummary() and detailedEmployment()
        // call classifyAlignmentLabel() -> classifyOne() per alumnus, each a
        // separate alignment_cache SELECT (measured: 856 duplicate queries at
        // ~4k alumni). Same fix as the dashboard's 849 -> 2.
        $this->alignment->preload(array_map(
            static fn (array $row) => ['course' => $row['course'] ?? '', 'title' => $row['job_title'] ?? ''],
            $this->report->distinctCourseJobTitlePairs()
        ));

        return [
            'success' => true,
            'employment_summary' => $this->employmentSummary(),
            'employment_sector_summary' => $this->groupedByCollegeCourse($this->report->sectorSummaryRows(), 'employment_sector', ['Government', 'Private', 'Self-employed', 'Others']),
            'location_summary' => $this->groupedByCollegeCourse($this->report->locationSummaryRows(), 'location_of_work', ['Local', 'Abroad', 'Others']),
            'employment_status_summary' => $this->groupedByCollegeCourse($this->report->statusSummaryRows(), 'employment_status', ['Regular', 'Probational', 'Contractual', 'Others']),
            'industry_determination' => $this->industryDetermination(),
            'detailed_employment' => $this->detailedEmployment(),
            'industry_analysis' => $this->industryAnalysis(),
        ];
    }

    private function employmentSummary(): array
    {
        $collegeStats = [];
        foreach ($this->report->employmentSummaryRows() as $row) {
            $college = $row['college'];
            $course = $row['course'];
            if (!isset($collegeStats[$college][$course])) {
                $collegeStats[$college][$course] = ['course' => $course, 'college' => $college, 'total_graduates' => 0, 'employed_count' => 0, 'related_job_count' => 0];
            }
            ++$collegeStats[$college][$course]['total_graduates'];

            if (in_array($row['employment_status'], ['Employed', 'Probational', 'Regular'], true)) {
                ++$collegeStats[$college][$course]['employed_count'];
                if (!empty($row['job_title']) && $this->classifyAlignmentLabel($row['course'], $row['job_title']) === 'aligned') {
                    ++$collegeStats[$college][$course]['related_job_count'];
                }
            }
        }

        $out = [];
        $collegeKeys = array_keys($collegeStats);
        foreach ($collegeStats as $college => $courses) {
            $out[] = ['college' => $college, 'course' => '', 'total_graduates' => '', 'employed_count' => '', 'employment_rate' => '', 'related_job_count' => '', 'match_rate' => '', 'is_header' => true];
            foreach ($courses as $c) {
                $employmentRate = round(($c['employed_count'] / $c['total_graduates']) * 100, 2);
                $matchRate = $c['employed_count'] > 0 ? round(($c['related_job_count'] / $c['employed_count']) * 100, 2) : 0;
                $out[] = ['college' => '', 'course' => $c['course'], 'total_graduates' => $c['total_graduates'], 'employed_count' => $c['employed_count'], 'employment_rate' => $employmentRate, 'related_job_count' => $c['related_job_count'], 'match_rate' => $matchRate, 'is_header' => false];
            }
            if (end($collegeKeys) !== $college) {
                $out[] = ['college' => '', 'course' => '', 'total_graduates' => '', 'employed_count' => '', 'employment_rate' => '', 'related_job_count' => '', 'match_rate' => '', 'is_header' => false];
            }
        }

        return $out;
    }

    private function groupedByCollegeCourse(array $rows, string $keyField, array $columns): array
    {
        $stats = [];
        foreach ($rows as $row) {
            $college = $row['college'];
            $course = $row['course'];
            $key = $row[$keyField] ?: 'Unknown';
            $stats[$college][$course][$key] = (int) $row['count'];
        }

        $blank = array_fill_keys(array_merge(['College', 'Course'], $columns), '');

        $out = [];
        foreach ($stats as $college => $courses) {
            $out[] = array_merge($blank, ['College' => $college]);
            foreach ($courses as $course => $values) {
                $row = array_merge($blank, ['College' => '', 'Course' => $course]);
                foreach ($columns as $col) {
                    $row[$col] = $values[$col] ?? 0;
                }
                $out[] = $row;
            }
            $out[] = $blank;
        }

        return $out;
    }

    private function industryDetermination(): array
    {
        $stats = [];
        foreach ($this->report->industryDeterminationRows() as $row) {
            $college = $row['college'];
            $course = $row['course'];
            $gender = $row['gender'];

            if (!isset($stats[$college][$course])) {
                $stats[$college][$course] = ['Male' => 0, 'Female' => 0, 'industries' => []];
            }
            if (isset($stats[$college][$course][$gender])) {
                ++$stats[$college][$course][$gender];
            }

            if (!empty($row['job_title']) || !empty($row['company'])) {
                $prompt = "Given the job title: '{$row['job_title']}' and company: '{$row['company']}', determine the industry sector. Choose from: Technology/IT, Healthcare, Finance/Banking, Education, Manufacturing, Retail, Government, Non-profit, Hospitality/Tourism, Transportation, Construction, Media/Entertainment, Energy, Agriculture, Other. Only return the industry name.";
                $industry = $this->askGemini($prompt);
                if ($industry === '') {
                    $industry = $this->classifyIndustryLocally($row['job_title'] ?? '', $row['company'] ?? '', null);
                }
                if ($industry !== '') {
                    $stats[$college][$course]['industries'][] = $industry;
                }
            }
        }

        $out = [];
        foreach ($stats as $college => $courses) {
            $out[] = ['College' => $college, 'Course' => '', 'Male' => '', 'Female' => '', 'Total' => '', 'Industries' => ''];
            foreach ($courses as $course => $data) {
                $out[] = [
                    'College' => '',
                    'Course' => $course,
                    'Male' => $data['Male'],
                    'Female' => $data['Female'],
                    'Total' => $data['Male'] + $data['Female'],
                    'Industries' => implode(', ', array_unique($data['industries'])),
                ];
            }
            $out[] = ['College' => '', 'Course' => '', 'Male' => '', 'Female' => '', 'Total' => '', 'Industries' => ''];
        }

        return $out;
    }

    private function detailedEmployment(): array
    {
        $blank = array_fill_keys([
            'College', 'Course', 'Section', 'Alumni ID', 'User ID', 'Full Name', 'Birthdate', 'Contact', 'Gender',
            'Civil Status', 'Address', 'Year Graduated', 'Verification Document', 'Created At', 'Profile Pic', 'Email',
            'Experience ID', 'Job Title', 'Company', 'Start Date', 'End Date', 'Current', 'Job Description',
            'Location of Work', 'Employment Status', 'Employment Sector', 'Exp Created At', 'Exp Updated At', 'Job Related',
            'Green Jobs', 'Date Hired', 'Industry',
        ], '');

        $grouped = [];
        foreach ($this->report->detailedEmploymentRows() as $row) {
            $jobRelated = '';
            $greenJobs = '';
            $industry = '';
            if (!empty($row['title'])) {
                $jobRelated = $this->classifyAlignmentLabel($row['course'], $row['title']) === 'aligned' ? 'Yes' : 'No';
                $greenJobs = $this->isGreenJobLocally($row['title'], $row['description']) ? 'Yes' : 'No';
                $industry = $this->classifyIndustryLocally($row['title'], $row['company'], $row['description']);
            }

            $fullName = implode(' ', array_filter([$row['first_name'], $row['middle_name'], $row['last_name']]));
            $address = implode(', ', array_filter([$row['city'], $row['province']]));

            $grouped[$row['college']][$row['course']][] = array_merge($blank, [
                'College' => $row['college'], 'Course' => $row['course'], 'Section' => 'Complete Details',
                'Alumni ID' => $row['alumni_id'], 'User ID' => $row['user_id'], 'Full Name' => $fullName,
                'Birthdate' => $row['birthdate'], 'Contact' => $row['contact'], 'Gender' => $row['gender'],
                'Civil Status' => $row['civil_status'], 'Address' => $address, 'Year Graduated' => $row['year_graduated'],
                'Verification Document' => $row['verification_document'], 'Created At' => $row['created_at'],
                'Profile Pic' => $row['profile_pic'], 'Email' => $row['email'], 'Experience ID' => $row['experience_id'],
                'Job Title' => $row['title'], 'Company' => $row['company'], 'Start Date' => $row['start_date'],
                'End Date' => $row['end_date'], 'Current' => $row['current'], 'Job Description' => $row['description'],
                'Location of Work' => $row['location_of_work'], 'Employment Status' => $row['employment_status'],
                'Employment Sector' => $row['employment_sector'], 'Exp Created At' => $row['exp_created_at'],
                'Exp Updated At' => $row['exp_updated_at'], 'Job Related' => $jobRelated,
                'Green Jobs' => $greenJobs, 'Date Hired' => $row['start_date'], 'Industry' => $industry,
            ]);
        }

        $out = [];
        foreach ($grouped as $college => $courses) {
            $out[] = array_merge($blank, ['College' => $college]);
            foreach ($courses as $course => $alumniRows) {
                $out[] = array_merge($blank, ['Course' => $course]);
                foreach ($alumniRows as $r) {
                    $out[] = $r;
                }
                $out[] = $blank;
            }
            $out[] = $blank;
        }

        return $out;
    }

    private const STANDARD_INDUSTRIES = [
        'Agriculture, Hunting and Forestry', 'Fishing', 'Mining and Quarrying', 'Manufacturing',
        'Electricity, Gas and Water Supply', 'Construction',
        'Wholesale and Retail Trade, Repair of Motorcycles and Personal Households goods',
        'Hotels and Restaurants', 'IT Industry', 'Transport Storage and Communication',
        'Financial Intermediation', 'Real State, Renting and Business Activities',
        'Public Administration and Defense; Compulsory Social Security', 'Education',
        'Health and Social Work', 'Other Community, Social and Personal Service Activities',
        'Private Households with Employed Persons', 'Extra-territorial Organizations and Bodies',
    ];

    private function industryAnalysis(): array
    {
        $analysis = [];
        $courses = [];

        foreach ($this->report->industryAnalysisRows() as $row) {
            $course = $row['course'];
            if (!in_array($course, $courses, true)) {
                $courses[] = $course;
            }

            $industryList = implode("\n", self::STANDARD_INDUSTRIES);
            $prompt = "Given the job title: '{$row['job_title']}', company: '{$row['company']}', and job description: '{$row['job_description']}', categorize this into one of these industries. Only return the exact industry name from the list:\n\n{$industryList}\n\nOnly return the exact industry name from the list above.";
            $industry = $this->askGemini($prompt);

            if (!in_array($industry, self::STANDARD_INDUSTRIES, true)) {
                $industry = $this->classifyIndustryLocally(
                    $row['job_title'] ?? '',
                    $row['company'] ?? '',
                    $row['job_description'] ?? ''
                );
            }

            if (!isset($analysis[$course][$industry])) {
                $analysis[$course][$industry] = ['M' => 0, 'F' => 0];
            }

            if (in_array($row['gender'], ['M', 'Male'], true)) {
                ++$analysis[$course][$industry]['M'];
            } elseif (in_array($row['gender'], ['F', 'Female'], true)) {
                ++$analysis[$course][$industry]['F'];
            }
        }

        $out = [];
        foreach (self::STANDARD_INDUSTRIES as $industry) {
            $rowData = ['Industry' => $industry];
            foreach ($courses as $course) {
                $male = $analysis[$course][$industry]['M'] ?? 0;
                $female = $analysis[$course][$industry]['F'] ?? 0;
                $rowData["{$course} - Male"] = $male;
                $rowData["{$course} - Female"] = $female;
                $rowData["{$course} - Total"] = $male + $female;
            }
            $out[] = $rowData;
        }

        return $out;
    }

    /** Delegates to AlignmentService — the single, cached source of truth for course/job alignment shared with the Dashboard's "Program Work Alignment" widget. */
    private function classifyAlignmentLabel(string $course, string $jobTitle): string
    {
        return $this->alignment->classifyOne($course, $jobTitle) === 'Not Aligned' ? 'not aligned' : 'aligned';
    }

    private const GREEN_JOB_KEYWORDS = [
        'solar', 'renewable energy', 'renewable', 'wind energy', 'wind turbine', 'sustainab', 'environment',
        'environmental', 'recycl', 'waste management', 'waste treatment', 'organic farming', 'organic agriculture',
        'green building', 'leed', 'conservation', 'clean energy', 'climate', 'ecology', 'ecological', 'reforestation',
        'afforestation', 'forestry management', 'water treatment', 'wastewater', 'pollution control', 'emissions',
        'energy efficiency', 'energy audit', 'biodiversity', 'eco-friendly', 'eco friendly', 'environmental compliance',
        'carbon footprint', 'carbon capture', 'electric vehicle', 'ev charging', 'hybrid vehicle', 'biofuel',
        'biogas', 'geothermal', 'hydropower', 'sustainable agriculture', 'permaculture', 'natural resource',
        'environmental impact', 'environmental science', 'environmental engineer', 'environmental officer',
        'green technology', 'circular economy', 'composting',
    ];

    /** Fast keyword check for whether a job title/description falls under a "green job" (environmental/sustainability-related work). */
    private function isGreenJobLocally(string $jobTitle, ?string $description): bool
    {
        $text = strtolower($jobTitle.' '.($description ?? ''));

        foreach (self::GREEN_JOB_KEYWORDS as $keyword) {
            if (self::textHasKeyword($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Maps job keywords to the same PSA/CHED-style industry categories as
     * STANDARD_INDUSTRIES (the labels the Detailed Report's Excel export and
     * its industry pie chart show), so this fast local classifier and the
     * Gemini-based industryAnalysis() elsewhere in this class agree on naming.
     */
    private const INDUSTRY_KEYWORDS = [
        'IT Industry' => ['software', 'developer', 'programmer', 'information technology', 'network administrator', 'system admin', 'systems administrator', 'database', 'cybersecurity', 'web developer', 'app developer', 'data analyst', 'data scientist', 'devops', 'cloud engineer', 'qa tester', 'quality assurance', 'technical support', 'help desk', 'computer technician', 'it staff', 'it officer'],
        'Health and Social Work' => ['nurse', 'doctor', 'physician', 'medical', 'health', 'hospital', 'clinic', 'pharmacist', 'therapist', 'caregiver', 'healthcare', 'dental', 'laboratory technician', 'medtech', 'radiolog', 'social worker'],
        'Financial Intermediation' => ['bank', 'finance', 'financial', 'accountant', 'accounting', 'auditor', 'bookkeeper', 'cpa', 'treasury', 'investment', 'loan officer', 'credit analyst', 'insurance', 'actuar'],
        'Education' => ['teacher', 'instructor', 'professor', 'faculty', 'school', 'university', 'college', 'tutor', 'academic', 'education', 'training officer', 'trainer'],
        'Public Administration and Defense; Compulsory Social Security' => ['police', 'pnp', 'government', 'municipal', 'city hall', 'barangay', 'public safety', 'fire department', 'military', 'armed forces', 'customs', 'immigration', 'nbi', 'security guard', 'law enforcement'],
        'Manufacturing' => ['engineer', 'engineering', 'manufacturing', 'factory', 'production', 'assembly line', 'plant operator', 'machinist', 'fabrication', 'quality control', 'maintenance technician'],
        'Wholesale and Retail Trade, Repair of Motorcycles and Personal Households goods' => ['retail', 'sales', 'merchandiser', 'store', 'cashier', 'trade', 'wholesale', 'shop', 'marketing'],
        'Hotels and Restaurants' => ['hotel', 'restaurant', 'resort', 'tourism', 'travel agency', 'chef', 'cook', 'housekeeping', 'front desk', 'hospitality', 'food and beverage', 'f&b'],
        'Construction' => ['construction', 'civil engineer', 'architect', 'contractor', 'site engineer', 'foreman', 'builder'],
        'Agriculture, Hunting and Forestry' => ['agriculture', 'farm', 'agricultural', 'fishery', 'fisheries', 'livestock', 'agronomist'],
        'Real State, Renting and Business Activities' => ['bpo', 'call center', 'customer service representative', 'csr', 'customer support', 'real estate', 'business process outsourcing'],
    ];

    /** Fast keyword classification of a job into a broad industry bucket, used for the Detailed Report's industry-distribution chart. */
    private function classifyIndustryLocally(string $jobTitle, ?string $company, ?string $description): string
    {
        $text = strtolower($jobTitle.' '.($company ?? '').' '.($description ?? ''));

        foreach (self::INDUSTRY_KEYWORDS as $industry => $keywords) {
            foreach ($keywords as $keyword) {
                if (self::textHasKeyword($text, $keyword)) {
                    return $industry;
                }
            }
        }

        return 'Other Community, Social and Personal Service Activities';
    }

    /**
     * Whole-word(s) match, not a raw substring check — str_contains would let a short
     * keyword like "it" match inside unrelated words (e.g. "audit", "credit"), which
     * previously misclassified a Staff Auditor role as Technology/IT.
     */
    private static function textHasKeyword(string $text, string $keyword): bool
    {
        return (bool) preg_match('/\b'.preg_quote($keyword, '/').'\b/i', $text);
    }
}
