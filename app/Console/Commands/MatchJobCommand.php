<?php

namespace App\Console\Commands;

use App\Models\Job;
use App\Services\JobMatchService;
use Illuminate\Console\Command;

/**
 * Artisan port of backend/cli/match_job.php. Launched fire-and-forget by
 * JobPostingController right after a new job is created — scoring every
 * alumnus via sequential Gemini calls can take minutes with a large roster,
 * far past a web request's execution time limit, so this runs detached.
 */
class MatchJobCommand extends Command
{
    protected $signature = 'job:match {job_id}';
    protected $description = 'Scores every alumnus against a job posting and notifies matches (run detached, not inline).';

    public function handle(): int
    {
        $jobId = (int) $this->argument('job_id');
        if ($jobId <= 0) {
            $this->error('match_job: missing or invalid job_id argument');

            return 1;
        }

        $job = (new Job())->detailsWithCompanyById($jobId);
        if (!$job || empty($job['jobDetails'])) {
            error_log("match_job: job {$jobId} not found, skipping alumni matching");

            return 1;
        }

        $details = $job['jobDetails'];

        try {
            (new JobMatchService())->notifyMatchingAlumni(
                $jobId,
                $details['title'],
                $details['requirements'],
                $details['qualifications']
            );
            error_log("match_job: finished matching alumni for job {$jobId}");
        } catch (\Throwable $e) {
            error_log("match_job: matching failed for job {$jobId}: ".$e->getMessage());

            return 1;
        }

        return 0;
    }
}
