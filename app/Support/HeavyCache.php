<?php

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Cache for expensive aggregate payloads (dashboard totals, report summaries) that adds what Cache::remember lacks:
 *
 *  1. Single builder on a cold miss. With Cache::remember every concurrent request that misses runs the full
 *     multi-second aggregation itself (measured: 7 dashboard users at 100k alumni => 7 parallel builds, P99 6.8 s;
 *     at 1M => MySQL at 5.7 cores and unrelated pages at 23 s). Here one request builds under an atomic lock and the
 *     others wait for its result.
 *  2. Stale-while-revalidate. After the fresh window, callers are handed the previous payload immediately and ONE
 *     refresh runs after their response has been sent, so nobody waits on a rebuild in steady state.
 *  3. Cheap, targeted invalidation. markStale($scopes) flags every payload that depends on those scopes (a payload
 *     declares its scopes when it is cached, e.g. ['campus:3'] or ['all']) as stale in O(1); it keeps serving the old
 *     numbers instantly while a single refresh per key runs — instead of dropping them and sending the next visitors
 *     into the cold-miss path. A rebuild of campus 3's summary therefore refreshes campus 3's and the "all campuses"
 *     payloads only; the other campuses' cached payloads are untouched.
 */
final class HeavyCache
{
    private const STALE_AFTER_KEY = 'heavycache:stale_after';
    private const GLOBAL_SCOPE = '*';
    private const LOCK_SECONDS = 900;
    private const WAIT_SECONDS = 600;

    /**
     * @param  int  $fresh  seconds a payload is served without any refresh
     * @param  int  $keep  seconds a stale payload may still be served while it is refreshed (>= $fresh)
     * @param  string[]  $scopes  invalidation scopes this payload depends on (see markStale()); the global scope always applies
     */
    public static function remember(string $key, int $fresh, int $keep, callable $build, array $scopes = []): mixed
    {
        $staleKeys = [];
        foreach ([self::GLOBAL_SCOPE, ...$scopes] as $scope) {
            $staleKeys[self::STALE_AFTER_KEY.':'.$scope] = 0;
        }
        // one round trip for the payload, its timestamp and every scope marker
        $got = Cache::many([$key, $key.':created', ...array_keys($staleKeys)]);
        $value = $got[$key] ?? null;
        $created = $got[$key.':created'] ?? null;

        if ($value !== null && $created !== null) {
            $staleAfter = 0;
            foreach (array_keys($staleKeys) as $k) {
                $staleAfter = max($staleAfter, (int) ($got[$k] ?? 0));
            }
            if ($created > $staleAfter && $created + $fresh > time()) {
                return $value;
            }

            defer(function () use ($key, $created, $keep, $build) {
                Cache::lock('heavycache:build:'.$key, self::LOCK_SECONDS)->get(function () use ($key, $created, $keep, $build) {
                    if (Cache::get($key.':created') !== $created) {
                        return;   // someone else already refreshed it
                    }
                    self::store($key, $build(), $keep);
                });
            }, 'heavycache:'.$key);

            return $value;
        }

        try {
            return Cache::lock('heavycache:build:'.$key, self::LOCK_SECONDS)->block(self::WAIT_SECONDS, function () use ($key, $keep, $build) {
                $value = Cache::get($key);
                if ($value !== null && Cache::get($key.':created') !== null) {
                    return $value;   // built by the request we were waiting behind
                }

                return self::store($key, $build(), $keep);
            });
        } catch (LockTimeoutException) {
            return $build();   // the builder is taking unusually long — degrade to computing it ourselves
        }
    }

    /**
     * Flags the payloads of these scopes stale (null = every payload); they keep being served while one refresh per key
     * runs after the next request.
     *
     * @param  string[]|null  $scopes
     */
    public static function markStale(?array $scopes = null): void
    {
        $now = time();
        foreach ($scopes ?? [self::GLOBAL_SCOPE] as $scope) {
            Cache::forever(self::STALE_AFTER_KEY.':'.$scope, $now);
        }
    }

    private static function store(string $key, mixed $value, int $keep): mixed
    {
        Cache::put($key, $value, $keep);
        Cache::put($key.':created', time(), $keep);

        return $value;
    }
}
