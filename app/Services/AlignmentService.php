<?php

namespace App\Services;

use App\Models\AlignmentCache;

class AlignmentService
{
    private const LABELS = ['Highly Aligned', 'Moderately Aligned', 'Slightly Aligned', 'Not Aligned'];

    /**
     * Cap the number of live Gemini calls a single web request may make.
     * After a large alumni import there can be hundreds of never-seen
     * (course, job) pairs; classifying them all inline (each Gemini call is
     * ~1s, up to ~30s on failure) hangs the page and starves php-fpm. Past
     * the cap we fall back to a local keyword guess (not persisted) so the
     * page still renders; run `php artisan alignment:warm` to fill the
     * cache properly offline.
     */
    private const MAX_LIVE_LOOKUPS = 10;

    private GeminiClient $gemini;
    private AlignmentCache $cache;

    /** @var array<string, string> cache_key => label, warmed by preload() to skip a DB round-trip per classifyOne() call */
    private array $memo = [];

    /** @var array<string, true> keys preload() has already looked up (hit OR miss) — a miss here must NOT trigger a per-pair cache->get() */
    private array $checked = [];

    private int $liveLookups = 0;

    private int $geminiFailures = 0;

    private bool $geminiDown = false;

    private bool $unlimited = false;

    public function __construct(?GeminiClient $gemini = null, ?AlignmentCache $cache = null)
    {
        $this->gemini = $gemini ?? new GeminiClient();
        $this->cache = $cache ?? new AlignmentCache();
    }

    /** Lift the per-request Gemini cap — for the offline `alignment:warm` command only. */
    public function allowUnlimitedLookups(): void
    {
        $this->unlimited = true;
    }

    /**
     * Batch-warms the in-memory cache for a whole set of course/job-title
     * pairs in one query, instead of classifyOne() hitting the DB once per
     * pair — Reports' summary() classifies thousands of rows per page load
     * (measured at 7+ seconds before this), almost all of them already
     * cached from a prior run.
     *
     * @param array<int, array{course: string, title?: string, job_title?: string}> $courseJobPairs
     */
    public function preload(array $courseJobPairs): void
    {
        $pairs = [];
        foreach ($courseJobPairs as $row) {
            $title = $row['title'] ?? $row['job_title'] ?? '';
            if ($row['course'] === '' || $title === '') {
                continue;
            }
            $pairs[] = [$row['course'], $title];
        }

        if (!$pairs) {
            return;
        }

        foreach ($pairs as [$course, $title]) {
            $this->checked[AlignmentCache::key($course, $title)] = true;
        }
        $this->memo += $this->cache->getMany($pairs);
    }

    /**
     * @param array<int, array{course: string, title: string}> $courseJobPairs
     */
    public function classify(array $courseJobPairs): array
    {
        // One batched cache lookup instead of a DB round-trip per pair
        // (thousands of rows after a bulk import).
        $this->preload($courseJobPairs);

        $alignment = [];

        foreach ($courseJobPairs as $row) {
            $course = $row['course'];
            $title = $row['title'];

            if (!isset($alignment[$course])) {
                $alignment[$course] = [
                    'counts' => array_fill_keys(self::LABELS, 0),
                    'percentages' => array_fill_keys(self::LABELS, 0),
                ];
            }

            ++$alignment[$course]['counts'][$this->classifyOne($course, $title)];
        }

        foreach ($alignment as &$data) {
            $total = array_sum($data['counts']);
            if ($total > 0) {
                foreach (self::LABELS as $label) {
                    $data['percentages'][$label] = round(($data['counts'][$label] / $total) * 100, 2);
                }
            }
        }
        unset($data);

        return $alignment;
    }

    /**
     * The single source of truth for classifying one course/job-title pair.
     * Cached by (course, job title) so the SAME pair always resolves to the
     * SAME label everywhere it's used (Dashboard's "Program Work Alignment"
     * widget and Reports' "Job Match Rate" both call this) — the Gemini API
     * is not deterministic call-to-call, so without caching, the same pair
     * could classify differently on every page load.
     */
    public function classifyOne(string $course, string $jobTitle): string
    {
        $key = AlignmentCache::key($course, $jobTitle);
        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        // Only hit the DB per-pair when preload() hasn't already covered this
        // key (a preload miss stays a miss — no N+1 of one SELECT per pair).
        if (!isset($this->checked[$key])) {
            $cached = $this->cache->get($course, $jobTitle);
            if ($cached !== null) {
                $this->memo[$key] = $cached;

                return $cached;
            }
        }

        // Past the per-request budget, or once Gemini has clearly failed
        // this request, don't call it — return a local keyword guess
        // (memoized, not persisted).
        if (($this->geminiDown || $this->liveLookups >= self::MAX_LIVE_LOOKUPS) && !$this->unlimited) {
            $guess = $this->localGuess($course, $jobTitle);
            $this->memo[$key] = $guess;

            return $guess;
        }
        $this->liveLookups++;

        $prompt = "Given the course: '{$course}' and the job title: '{$jobTitle}', classify the alignment as one of: Highly Aligned, Moderately Aligned, Slightly Aligned, Not Aligned. Only return the label.";
        $raw = $this->gemini->generate($prompt);
        $label = preg_replace('/[^A-Za-z ]/', '', $raw);

        if (!in_array($label, self::LABELS, true)) {
            // Don't persist a fallback caused by an empty/failed API response
            // (e.g. Gemini unreachable) — that would permanently poison the
            // cache with "Not Aligned" for a pair that was never actually
            // classified. A genuinely off-format-but-non-empty reply is rare
            // enough to just treat the same way, unlogged.
            if ($raw === '') {
                // Gemini unreachable / no API key. After a couple of these,
                // stop calling it for the rest of the request so the page
                // doesn't spend ~30s per pair on curl timeouts.
                if (!$this->unlimited && ++$this->geminiFailures >= 2) {
                    $this->geminiDown = true;
                }
                $guess = $this->localGuess($course, $jobTitle);
                $this->memo[$key] = $guess; // memoized, not persisted

                return $guess;
            }
            $label = 'Not Aligned';
        }

        $this->cache->set($course, $jobTitle, $label);
        $this->memo[$key] = $label;

        return $label;
    }

    /**
     * Cheap keyword-based alignment guess for when Gemini is unavailable or
     * over the per-request budget. Conservative: "Moderately Aligned" when
     * the job clearly sits in the course's domain, else "Not Aligned".
     */
    private const DOMAIN_KEYWORDS = [
        'information technology|computer science|information system|computer engineering' =>
            ['developer', 'programmer', 'software', 'web', ' it ', 'it ', 'network', 'system', 'systems', 'database', 'data analyst', 'data scientist', 'qa', 'quality assurance', 'technical support', 'help desk', 'devops', 'cloud', 'cybersecurity', 'computer', 'technology', 'application', 'tech support', 'encoder', 'programmer analyst'],
        'accountancy|accounting|financial management' =>
            ['account', 'accounting', 'audit', 'auditor', 'bookkeep', 'finance', 'financial', 'tax', 'cpa', 'treasury', 'billing', 'payroll', 'credit', 'cost analyst'],
        'business administration|office administration|entrepreneur|marketing management' =>
            ['admin', 'administrative', 'office', 'clerk', 'secretary', 'business', 'sales', 'marketing', 'manager', 'supervisor', 'coordinator', 'associate', 'staff', 'virtual assistant', 'operations', 'customer service', 'encoder', 'analyst', 'hr', 'human resource', 'recruit', 'procurement', 'logistics', 'merchandiser', 'bpo', 'call center'],
        'tourism|hospitality|hotel and restaurant' =>
            ['hotel', 'restaurant', 'resort', 'tourism', 'tour', 'travel', 'guest', 'front desk', 'food', 'beverage', 'f&b', 'chef', 'cook', 'barista', 'housekeeping', 'cabin crew', 'flight attendant', 'reservation', 'concierge', 'server', 'waiter', 'events', 'catering', 'hospitality'],
        'education|secondary education|elementary education|teacher|early childhood|technology and livelihood|technical-vocational' =>
            ['teacher', 'instructor', 'tutor', 'professor', 'faculty', 'school', 'education', 'lecturer', 'trainer', 'academic', 'principal', 'guidance'],
        'criminology|criminal justice' =>
            ['police', 'pnp', 'security', 'criminolog', 'investigator', 'law enforcement', 'jail', 'bjmp', 'bfp', 'agent', 'officer', 'patrol', 'forensic', 'custodial'],
        'engineering|industrial technology|biosystems' =>
            ['engineer', 'engineering', 'technician', 'draft', 'cad', 'design', 'construction', 'maintenance', 'production', 'plant', 'quality control', 'electrical', 'mechanical', 'civil', 'electronics', 'instrumentation', 'estimator', 'surveyor', 'operator', 'fabrication', 'automotive'],
        'psychology' =>
            ['psycholog', 'hr', 'human resource', 'guidance', 'counsel', 'recruit', 'talent', 'wellness', 'behavior', 'assessment', 'research assistant'],
        'nursing|nutrition|dietetics|allied health' =>
            ['nurse', 'nursing', 'health', 'hospital', 'clinic', 'caregiver', 'medical', 'dietitian', 'nutrition', 'patient', 'care', 'therap', 'midwife', 'phlebotom'],
        'biology|chemistry|mathematics|physics' =>
            ['laboratory', 'lab ', 'research', 'analyst', 'quality', 'microbiolog', 'chemist', 'quality control', 'quality assurance', 'science', 'researcher', 'statistician', 'actuar'],
        'agriculture|fisheries|agri' =>
            ['agriculture', 'agricultural', 'farm', 'fishery', 'fisheries', 'livestock', 'agronom', 'crop', 'aquacultur', 'extension', 'technician'],
        'broadcasting|communication' =>
            ['broadcast', 'media', 'journalist', 'reporter', 'production', 'content', 'video', 'radio', 'television', 'communication', 'social media', 'writer', 'editor', 'creative'],
    ];

    private function localGuess(string $course, string $jobTitle): string
    {
        $c = ' '.mb_strtolower($course).' ';
        $j = ' '.mb_strtolower($jobTitle).' ';

        foreach (self::DOMAIN_KEYWORDS as $coursePatterns => $jobKeywords) {
            if (!preg_match('/'.$coursePatterns.'/', $c)) {
                continue;
            }
            foreach ($jobKeywords as $kw) {
                if (str_contains($j, $kw)) {
                    return 'Moderately Aligned';
                }
            }

            return 'Not Aligned';
        }

        return 'Not Aligned';
    }
}
