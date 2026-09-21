<?php

namespace Tests\Unit\Support;

use App\Support\HeavyCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HeavyCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function testBuildsOnceAndServesFromCacheWhileFresh(): void
    {
        $calls = 0;
        $build = function () use (&$calls) {
            ++$calls;

            return ['n' => $calls];
        };

        $this->assertSame(['n' => 1], HeavyCache::remember('k1', 600, 3600, $build));
        $this->assertSame(['n' => 1], HeavyCache::remember('k1', 600, 3600, $build));
        $this->assertSame(1, $calls);
    }

    public function testMarkStaleKeepsServingTheOldPayloadInsteadOfRebuildingInline(): void
    {
        $calls = 0;
        $build = function () use (&$calls) {
            ++$calls;

            return ['n' => $calls];
        };
        HeavyCache::remember('k2', 600, 3600, $build);

        $this->travel(2)->seconds();
        HeavyCache::markStale();

        // stale payload is returned immediately; the refresh is deferred until after the response
        $this->assertSame(['n' => 1], HeavyCache::remember('k2', 600, 3600, $build));
        $this->assertSame(1, $calls, 'no inline rebuild for the requesting user');
    }

    public function testExpiredFreshWindowAlsoServesStale(): void
    {
        $calls = 0;
        $build = function () use (&$calls) {
            ++$calls;

            return ['n' => $calls];
        };
        HeavyCache::remember('k3', 10, 3600, $build);
        $this->travel(11)->seconds();

        $this->assertSame(['n' => 1], HeavyCache::remember('k3', 10, 3600, $build));
        $this->assertSame(1, $calls);
    }

    public function testMarkStaleIsTargetedToTheScopesItNames(): void
    {
        $calls = ['a' => 0, 'b' => 0];
        $build = function (string $k) use (&$calls) {
            return function () use (&$calls, $k) {
                return ['n' => ++$calls[$k]];
            };
        };
        HeavyCache::remember('ka', 600, 3600, $build('a'), ['campus:1']);
        HeavyCache::remember('kb', 600, 3600, $build('b'), ['campus:2']);
        $this->travel(2)->seconds();

        HeavyCache::markStale(['campus:1', 'all']);   // what a rebuild of campus 1's summary does

        $deferredBefore = count(app(\Illuminate\Support\Defer\DeferredCallbackCollection::class));
        HeavyCache::remember('kb', 600, 3600, $build('b'), ['campus:2']);
        $this->assertSame($deferredBefore, count(app(\Illuminate\Support\Defer\DeferredCallbackCollection::class)), "campus 2's payload is untouched: no refresh is scheduled for it");

        $this->assertSame(['n' => 1], HeavyCache::remember('ka', 600, 3600, $build('a'), ['campus:1']), 'still served instantly while stale');
        $this->assertSame($deferredBefore + 1, count(app(\Illuminate\Support\Defer\DeferredCallbackCollection::class)), "campus 1's payload gets exactly one refresh");
        $this->assertSame(1, $calls['a'], 'and it was not rebuilt inline');
    }

    public function testGlobalMarkStaleStillInvalidatesEveryPayload(): void
    {
        $build = fn () => ['n' => 1];
        HeavyCache::remember('kg', 600, 3600, $build, ['campus:9']);
        $this->travel(2)->seconds();
        HeavyCache::markStale();

        $before = count(app(\Illuminate\Support\Defer\DeferredCallbackCollection::class));
        HeavyCache::remember('kg', 600, 3600, $build, ['campus:9']);
        $this->assertSame($before + 1, count(app(\Illuminate\Support\Defer\DeferredCallbackCollection::class)));
    }
}
