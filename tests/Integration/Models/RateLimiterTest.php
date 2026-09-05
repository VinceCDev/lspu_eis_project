<?php

namespace Tests\Integration\Models;

use App\Models\RateLimiter;
use Tests\TestCase;

/** Ported from tests/Integration/Models/RateLimiterTest.php — no mysqli usage, needed only the base TestCase swap. */
class RateLimiterTest extends TestCase
{
    private RateLimiter $limiter;
    private string $bucket;
    private string $identifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->limiter = new RateLimiter();
        $this->bucket = '__phpunit_test_bucket__';
        $this->identifier = 'test-'.uniqid();
    }

    protected function tearDown(): void
    {
        $this->limiter->clear($this->bucket, $this->identifier);
        parent::tearDown();
    }

    public function testAllowsAttemptsUnderTheLimit(): void
    {
        $this->limiter->hit($this->bucket, $this->identifier);
        $this->limiter->hit($this->bucket, $this->identifier);

        $this->assertFalse($this->limiter->tooManyAttempts($this->bucket, $this->identifier, 3, 60));
    }

    public function testBlocksOnceLimitIsReached(): void
    {
        $this->limiter->hit($this->bucket, $this->identifier);
        $this->limiter->hit($this->bucket, $this->identifier);
        $this->limiter->hit($this->bucket, $this->identifier);

        $this->assertTrue($this->limiter->tooManyAttempts($this->bucket, $this->identifier, 3, 60));
    }

    public function testDifferentIdentifiersDoNotShareALimit(): void
    {
        $otherIdentifier = 'other-'.uniqid();

        $this->limiter->hit($this->bucket, $this->identifier);
        $this->limiter->hit($this->bucket, $this->identifier);
        $this->limiter->hit($this->bucket, $this->identifier);

        $this->assertTrue($this->limiter->tooManyAttempts($this->bucket, $this->identifier, 3, 60));
        $this->assertFalse($this->limiter->tooManyAttempts($this->bucket, $otherIdentifier, 3, 60));

        $this->limiter->clear($this->bucket, $otherIdentifier);
    }

    public function testDifferentBucketsDoNotShareALimit(): void
    {
        $otherBucket = '__phpunit_test_bucket_2__';

        $this->limiter->hit($this->bucket, $this->identifier);
        $this->limiter->hit($this->bucket, $this->identifier);
        $this->limiter->hit($this->bucket, $this->identifier);

        $this->assertTrue($this->limiter->tooManyAttempts($this->bucket, $this->identifier, 3, 60));
        $this->assertFalse($this->limiter->tooManyAttempts($otherBucket, $this->identifier, 3, 60));

        $this->limiter->clear($otherBucket, $this->identifier);
    }

    public function testClearResetsTheCounter(): void
    {
        $this->limiter->hit($this->bucket, $this->identifier);
        $this->limiter->hit($this->bucket, $this->identifier);
        $this->limiter->hit($this->bucket, $this->identifier);
        $this->assertTrue($this->limiter->tooManyAttempts($this->bucket, $this->identifier, 3, 60));

        $this->limiter->clear($this->bucket, $this->identifier);

        $this->assertFalse($this->limiter->tooManyAttempts($this->bucket, $this->identifier, 3, 60));
    }
}
