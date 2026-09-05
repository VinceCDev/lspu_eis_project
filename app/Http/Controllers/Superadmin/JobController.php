<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employer;
use App\Models\Job;
use App\Services\Auth;
use App\Services\JobMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Superadmin/JobController.php. */
class JobController extends Controller
{
    public function index()
    {
        return view('superadmin.job', [
            'title' => 'Jobs | LSPU - EIS',
            'active' => 'superadmin_job',
            'pageCss' => 'admin_job.css',
            'pageJs' => 'superadmin_job.js',
        ]);
    }

    public function list(): JsonResponse
    {
        return response()->json((new Job())->allWithEmployer());
    }

    /**
     * Page-at-a-time variant of list() for large job tables — returns
     * {jobs, total} instead of the full unbounded array. Additive: list()
     * above is untouched so the current frontend keeps working unchanged
     * until it's updated to call this instead.
     */
    public function paginatedList(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
        $search = trim((string) $request->query('search', ''));
        $offset = ($page - 1) * $perPage;

        $job = new Job();

        return response()->json([
            'success' => true,
            'jobs' => $job->allWithEmployerPaginated($perPage, $offset, $search),
            'total' => $job->countWithEmployer($search),
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    public function companies(): JsonResponse
    {
        return response()->json((new Employer())->allOrdered());
    }

    public function store(Request $request): JsonResponse
    {
        $fields = ['employer_id', 'title', 'type', 'work_setup', 'classification', 'location', 'status', 'description', 'requirements', 'qualifications'];
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
        $jobId = null;

        try {
            if ($isUpdate) {
                $job->update((int) $request->input('job_id'), $data);
            } else {
                $jobId = $job->create($data);
            }
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        if (!$isUpdate) {
            (new JobMatchService())->notifyMatchingAlumni($jobId, $data['title'], $data['requirements'], $data['qualifications']);
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

        $ok = (new Job())->delete((int) $jobId);
        if ($ok) {
            (new AuditLog())->log((int) Auth::user()['user_id'], Auth::user()['email'] ?? null, Auth::role(), 'delete_job', 'job', (int) $jobId, 'Deleted job posting.');
        }

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Job deleted successfully.' : 'Delete failed.',
        ]);
    }
}
