<?php

namespace Tests\Unit\Services;

use App\Models\Alumni;
use App\Services\GeminiClient;
use App\Services\JobMatchService;
use App\Services\MailService;
use PHPUnit\Framework\TestCase;

/**
 * extractMatchPercentage() parses Gemini's free-text reply (e.g. "Match
 * (85%)") into the number stored in job_match_leaderboard and used to
 * decide whether an alumnus gets notified (>= 50%) — a parsing miss here
 * means either everyone gets spammed with 0% "matches" or nobody gets
 * notified about a real one.
 */
class JobMatchServiceTest extends TestCase
{
    private function extractMatchPercentage(string $response): int
    {
        $service = new JobMatchService(
            $this->createMock(Alumni::class),
            $this->createMock(MailService::class),
            $this->createMock(GeminiClient::class)
        );

        $method = new \ReflectionMethod(JobMatchService::class, 'extractMatchPercentage');
        $method->setAccessible(true);

        return $method->invoke($service, $response);
    }

    public function testParsesPercentageFromAMatchResponse(): void
    {
        $this->assertSame(85, $this->extractMatchPercentage('Match (85%)'));
    }

    public function testParsesPercentageFromANotAMatchResponse(): void
    {
        $this->assertSame(20, $this->extractMatchPercentage('Not a Match (20%)'));
    }

    public function testParsesPercentageRegardlessOfSurroundingWhitespaceOrCase(): void
    {
        $this->assertSame(100, $this->extractMatchPercentage("  match(100%)  \n"));
    }

    public function testReturnsZeroWhenNoPercentageIsPresent(): void
    {
        $this->assertSame(0, $this->extractMatchPercentage('Match'));
    }

    public function testReturnsZeroForAnEmptyOrFailedApiResponse(): void
    {
        $this->assertSame(0, $this->extractMatchPercentage(''));
    }
}
