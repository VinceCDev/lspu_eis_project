<?php

namespace App\Services;

use App\Models\AlignmentCache;

class AlignmentService
{
    private const LABELS = ['Highly Aligned', 'Moderately Aligned', 'Slightly Aligned', 'Not Aligned'];

    private GeminiClient $gemini;
    private AlignmentCache $cache;

    /** @var array<string, string> cache_key => label, warmed by preload() to skip a DB round-trip per classifyOne() call */
    private array $memo = [];

    public function __construct(?GeminiClient $gemini = null, ?AlignmentCache $cache = null)
    {
        $this->gemini = $gemini ?? new GeminiClient();
        $this->cache = $cache ?? new AlignmentCache();
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

        $this->memo += $this->cache->getMany($pairs);
    }

    /**
     * @param array<int, array{course: string, title: string}> $courseJobPairs
     */
    public function classify(array $courseJobPairs): array
    {
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

        $cached = $this->cache->get($course, $jobTitle);
        if ($cached !== null) {
            $this->memo[$key] = $cached;

            return $cached;
        }

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
                // Not persisted to the DB cache (see comment above), but still
                // memoized for the rest of this request so a repeated pair
                // doesn't retry the same failing Gemini call in a loop.
                $this->memo[$key] = 'Not Aligned';

                return 'Not Aligned';
            }
            $label = 'Not Aligned';
        }

        $this->cache->set($course, $jobTitle, $label);
        $this->memo[$key] = $label;

        return $label;
    }
}
