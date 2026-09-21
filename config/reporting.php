<?php

/**
 * Dashboard / Reports aggregate ("summary") tables - see App\Services\ReportingSummary.
 */
return [
    /* Master switch. false = every page runs its original live GROUP BY queries (rollback path). */
    'use_summary' => filter_var(env('REPORTING_SUMMARY', true), FILTER_VALIDATE_BOOL),

    /* A dirty partition is rebuilt at most this often (seconds), so a burst of profile edits triggers one rebuild. */
    'min_rebuild_interval' => (int) env('REPORTING_MIN_REBUILD_INTERVAL', 60),

    /* HeavyCache windows (seconds): served without any refresh / served stale while one refresh runs. */
    'cache_fresh' => (int) env('REPORTING_CACHE_FRESH', 600),
    'cache_keep' => (int) env('REPORTING_CACHE_KEEP', 21600),
];
