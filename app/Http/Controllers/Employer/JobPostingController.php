<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Employer/JobPostingController.php. */
class JobPostingController extends Controller
{
    public function index()
    {
        return view('employer.jobposting', [
            'title' => 'Job Postings | LSPU - EIS',
            'active' => 'employer_jobposting',
            'pageCss' => 'employer_job.css',
            'pageJs' => 'employer_jobposting.js',
        ]);
    }

    public function list(): JsonResponse
    {
        return response()->json((new Job())->whereEmployer((int) Auth::user()['user_id']));
    }

    /** Lightweight job_id+title pairs, used by pickers (interview scheduling, onboarding assignment). */
    public function titles(): JsonResponse
    {
        $jobs = array_map(
            static fn (array $j) => ['job_id' => $j['job_id'], 'title' => $j['title']],
            (new Job())->whereEmployer((int) Auth::user()['user_id'])
        );

        return response()->json(['success' => true, 'jobs' => $jobs]);
    }

    public function store(Request $request): JsonResponse
    {
        $employerId = (int) Auth::user()['user_id'];
        $fields = ['title', 'type', 'work_setup', 'classification', 'location', 'status', 'description', 'requirements', 'qualifications'];
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $request->input($field, '');
        }
        $data['salary'] = $request->input('salary', '');
        $data['employer_question'] = trim($request->input('employer_question', ''));
        $data['employer_question_required'] = ($data['employer_question'] !== '' && in_array($request->input('employer_question_required', ''), ['1', 'true'], true)) ? 1 : 0;

        foreach ($fields as $field) {
            if ($data[$field] === '') {
                return response()->json(['success' => false, 'message' => "Missing field: $field"]);
            }
        }

        $job = new Job();
        $isUpdate = $request->filled('job_id');

        if ($isUpdate) {
            $ok = $job->updateForEmployer((int) $request->input('job_id'), $employerId, $data);
            if (!$ok) {
                return response()->json(['success' => false, 'message' => 'Job not found or access denied.']);
            }
        } else {
            $jobId = $job->createForEmployer($employerId, $data);
            $this->dispatchAlumniMatching($jobId);
        }

        return response()->json([
            'success' => true,
            'message' => $isUpdate ? 'Job updated successfully.' : 'Job created successfully.',
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $jobId = $request->input('job_id');

        if (!$jobId) {
            return response()->json(['success' => false, 'message' => 'Missing job_id']);
        }

        $ok = (new Job())->deleteForEmployer((int) $jobId, (int) Auth::user()['user_id']);

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Job deleted successfully.' : 'Job not found or access denied.',
        ]);
    }

    /**
     * Scoring every alumnus against a new job via sequential Gemini calls
     * can take far longer than this request's execution time allows —
     * dispatched as a detached `php artisan job:match` process (see
     * App\Console\Commands\MatchJobCommand) instead of running inline.
     */
    private function dispatchAlumniMatching(int $jobId): void
    {
        $php = $this->phpCliBinary();
        $artisan = base_path('artisan');
        $nullDevice = stripos(PHP_OS, 'WIN') === 0 ? 'NUL' : '/dev/null';

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $nullDevice, 'w'],
            2 => ['file', $nullDevice, 'w'],
        ];

        $process = @proc_open([$php, $artisan, 'job:match', (string) $jobId], $descriptors, $pipes, base_path(), null, ['bypass_shell' => true]);

        if (is_resource($process)) {
            fclose($pipes[0]);
            // Deliberately NOT calling proc_close() — that blocks until the
            // child exits, defeating the point of running this detached.
        }
    }

    /**
     * PHP_BINARY is only reliable under the CLI SAPI — under Apache's
     * mod_php, it resolves to httpd.exe itself. Locate the real php.exe
     * relative to the document root's XAMPP install instead.
     */
    private function phpCliBinary(): string
    {
        if ($configured = env('PHP_CLI_BINARY')) {
            return $configured;
        }

        $binaryName = stripos(PHP_OS, 'WIN') === 0 ? 'php.exe' : 'php';
        $candidates = [];

        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $candidates[] = dirname($_SERVER['DOCUMENT_ROOT']).DIRECTORY_SEPARATOR.'php'.DIRECTORY_SEPARATOR.$binaryName;
        }
        // Last-resort fallback for the common local XAMPP layout — only
        // reached if PHP_CLI_BINARY isn't set and DOCUMENT_ROOT-relative
        // detection above didn't find a real binary.
        $candidates[] = 'C:\\xampp\\php\\'.$binaryName;

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $binaryName;
    }
}
