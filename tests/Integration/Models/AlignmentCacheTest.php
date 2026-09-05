<?php

namespace Tests\Integration\Models;

use App\Models\AlignmentCache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ported from tests/Integration/Models/AlignmentCacheTest.php. The real
 * DB-backed half of the alignment-consistency fix — AlignmentServiceTest
 * mocks this class; this exercises the actual table it reads/writes.
 */
class AlignmentCacheTest extends TestCase
{
    private AlignmentCache $cache;
    private string $course = '__phpunit_test_course__';
    private string $jobTitle = '__phpunit_test_job__';

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new AlignmentCache();
    }

    protected function tearDown(): void
    {
        DB::delete('DELETE FROM alignment_cache WHERE course = ?', [$this->course]);
        parent::tearDown();
    }

    public function testGetReturnsNullForAnUncachedPair(): void
    {
        $this->assertNull($this->cache->get($this->course, $this->jobTitle));
    }

    public function testSetThenGetReturnsTheStoredLabel(): void
    {
        $this->cache->set($this->course, $this->jobTitle, 'Highly Aligned');

        $this->assertSame('Highly Aligned', $this->cache->get($this->course, $this->jobTitle));
    }

    public function testSetOverwritesAPreviousLabelForTheSamePair(): void
    {
        $this->cache->set($this->course, $this->jobTitle, 'Highly Aligned');
        $this->cache->set($this->course, $this->jobTitle, 'Not Aligned');

        $this->assertSame('Not Aligned', $this->cache->get($this->course, $this->jobTitle));
    }

    public function testLookupIsCaseInsensitiveOnBothCourseAndJobTitle(): void
    {
        $this->cache->set($this->course, $this->jobTitle, 'Moderately Aligned');

        $this->assertSame(
            'Moderately Aligned',
            $this->cache->get(strtoupper($this->course), strtoupper($this->jobTitle))
        );
    }
}
