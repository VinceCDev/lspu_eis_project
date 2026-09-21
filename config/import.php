<?php

/**
 * Bulk "Data on Employment" import settings (see App\Jobs\ProcessEmploymentImport).
 *
 * Every value can be tuned per environment without a code change.
 */
return [
    /*
     * Maximum number of imports processed at the same time, however many queue workers exist. Uploads beyond this wait
     * in the "queued" state. Tested: 2 concurrent 100k imports leave Dashboard/Reports responsive; six at once did not.
     * docker-compose runs this many `imports` workers (deploy.replicas) - keep both in step.
     */
    'max_concurrent' => max(1, (int) env('IMPORT_WORKERS', 2)),

    /* Graduates written per transaction / multi-row INSERT (also the checkpoint interval). */
    'chunk_size' => max(50, min(10000, (int) env('IMPORT_CHUNK_SIZE', 1000))),

    /* A "processing" import whose worker has not heart-beaten for this long is considered dead and is re-queued. */
    'lease_seconds' => (int) env('IMPORT_LEASE_SECONDS', 120),

    /* Hard limit on one import job (seconds). The queue connection's retry_after MUST stay above this. */
    'job_timeout' => (int) env('IMPORT_JOB_TIMEOUT', 3600),

    'max_upload_mb' => (int) env('IMPORT_MAX_UPLOAD_MB', 40),

    /* Queued + processing imports one campus may have at once (guards against a stuck double-click / script). */
    'max_pending_per_campus' => (int) env('IMPORT_MAX_PENDING_PER_CAMPUS', 5),

    /* Uploaded workbooks live here (storage/app/<dir>) until the import finishes; failed ones are kept this many days. */
    'directory' => 'imports',
    'keep_days' => (int) env('IMPORT_KEEP_DAYS', 7),
];
