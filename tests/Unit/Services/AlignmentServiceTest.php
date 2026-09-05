<?php

namespace Tests\Unit\Services;

use App\Models\AlignmentCache;
use App\Services\AlignmentService;
use App\Services\GeminiClient;
use PHPUnit\Framework\TestCase;

/**
 * Covers the bug found during ISO 25010 testing: Reports' "Job Match Rate"
 * and the Dashboard's "Program Work Alignment" widget used to classify the
 * same course/job pair independently, and since the Gemini API is not
 * deterministic call-to-call, the two features could (and did) disagree on
 * the exact same pair. AlignmentService::classifyOne() is now the single
 * source of truth, cached by pair — these tests pin that behavior down.
 */
class AlignmentServiceTest extends TestCase
{
    public function testReturnsCachedLabelWithoutCallingGeminiOnCacheHit(): void
    {
        $gemini = $this->createMock(GeminiClient::class);
        $gemini->expects($this->never())->method('generate');

        $cache = $this->createMock(AlignmentCache::class);
        $cache->method('get')->with('BS Information Technology', 'Software Developer')->willReturn('Highly Aligned');

        $service = new AlignmentService($gemini, $cache);

        $this->assertSame('Highly Aligned', $service->classifyOne('BS Information Technology', 'Software Developer'));
    }

    public function testClassifiesAndCachesOnCacheMiss(): void
    {
        $gemini = $this->createMock(GeminiClient::class);
        $gemini->expects($this->once())->method('generate')->willReturn('Highly Aligned');

        $cache = $this->createMock(AlignmentCache::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())
            ->method('set')
            ->with('BS Information Technology', 'Software Developer', 'Highly Aligned');

        $service = new AlignmentService($gemini, $cache);

        $this->assertSame('Highly Aligned', $service->classifyOne('BS Information Technology', 'Software Developer'));
    }

    public function testFallsBackToNotAlignedWithoutCachingWhenGeminiReturnsEmpty(): void
    {
        // A blank response means the API call failed (unreachable, quota
        // exhausted, etc.) — this must NOT be cached as a real answer, or a
        // transient outage would permanently poison the cache with a wrong
        // "Not Aligned" for a pair that was never actually classified.
        $gemini = $this->createMock(GeminiClient::class);
        $gemini->method('generate')->willReturn('');

        $cache = $this->createMock(AlignmentCache::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->never())->method('set');

        $service = new AlignmentService($gemini, $cache);

        $this->assertSame('Not Aligned', $service->classifyOne('BS Criminology', 'Barista'));
    }

    public function testUnrecognizedNonEmptyLabelIsCachedAsNotAligned(): void
    {
        // A non-empty but off-format reply (rare, but not an API failure)
        // is treated as a real (negative) classification and IS cached —
        // only a genuinely empty response is excluded from caching.
        $gemini = $this->createMock(GeminiClient::class);
        $gemini->method('generate')->willReturn('I cannot answer that.');

        $cache = $this->createMock(AlignmentCache::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())->method('set')->with('BS Accountancy', 'Chef', 'Not Aligned');

        $service = new AlignmentService($gemini, $cache);

        $this->assertSame('Not Aligned', $service->classifyOne('BS Accountancy', 'Chef'));
    }

    public function testClassifyAggregatesCountsAndPercentagesPerCourse(): void
    {
        $gemini = $this->createMock(GeminiClient::class);
        $cache = $this->createMock(AlignmentCache::class);
        $cache->method('get')->willReturnOnConsecutiveCalls('Highly Aligned', 'Not Aligned', 'Not Aligned');

        $service = new AlignmentService($gemini, $cache);

        $result = $service->classify([
            ['course' => 'BS Information Technology', 'title' => 'Software Developer'],
            ['course' => 'BS Information Technology', 'title' => 'Barista'],
            ['course' => 'BS Information Technology', 'title' => 'Cashier'],
        ]);

        $this->assertSame(1, $result['BS Information Technology']['counts']['Highly Aligned']);
        $this->assertSame(2, $result['BS Information Technology']['counts']['Not Aligned']);
        $this->assertEqualsWithDelta(33.33, $result['BS Information Technology']['percentages']['Highly Aligned'], 0.01);
        $this->assertEqualsWithDelta(66.67, $result['BS Information Technology']['percentages']['Not Aligned'], 0.01);
    }
}
