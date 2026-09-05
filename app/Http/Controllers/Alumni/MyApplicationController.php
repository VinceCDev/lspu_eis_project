<?php

namespace App\Http\Controllers\Alumni;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\Application;
use App\Models\Job;
use App\Models\SavedJob;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/** Ported from backend/Controllers/Alumni/MyApplicationController.php. */
class MyApplicationController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('job_id') !== null && is_numeric($request->query('job_id'))) {
            Session::put('highlight_application_job_id', $request->query('job_id'));
        }

        return view('alumni.my_application', [
            'title' => 'My Applications | LSPU - EIS',
            'active' => 'my_application',
            'pageCss' => 'my_application.css',
            'pageJs' => 'my_application.js',
            'extraHead' => '<link rel="stylesheet" href="'.asset('assets/vendor/leaflet/leaflet.css').'">',
            'extraScripts' => '<script src="'.asset('assets/vendor/leaflet/leaflet.js').'"></script>'
                .'<script>window.USER_ID = '.json_encode((int) Auth::user()['user_id']).';</script>',
        ]);
    }

    public function appliedJobs(): JsonResponse
    {
        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);

        return response()->json(['appliedJobs' => $alumniId ? (new Application())->appliedJobsForAlumni($alumniId) : []]);
    }

    public function savedJobs(): JsonResponse
    {
        return response()->json(['savedJobs' => (new SavedJob())->withJobDetailsForUser((int) Auth::user()['user_id'])]);
    }

    public function deleteApplication(Request $request): JsonResponse
    {
        $jobId = (int) $request->input('job_id', 0);
        if (!$jobId) {
            return response()->json(['success' => false, 'message' => 'Job ID required.']);
        }

        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        $ok = (new Application())->deleteForAlumni($alumniId, $jobId);

        return response()->json(['success' => $ok, 'message' => $ok ? null : 'Failed to delete application.']);
    }

    public function deleteSavedJob(Request $request): JsonResponse
    {
        $jobId = (int) $request->input('job_id', 0);
        if (!$jobId) {
            return response()->json(['success' => false, 'message' => 'No job_id provided.']);
        }

        $ok = (new SavedJob())->unsave((int) Auth::user()['user_id'], $jobId);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Job unsaved successfully.' : 'Failed to unsave job.']);
    }

    public function checkHiredStatus(Request $request): JsonResponse
    {
        $employerId = (int) $request->input('employer_id', 0);
        if (!$employerId) {
            return response()->json(['success' => false, 'message' => 'Employer ID required']);
        }

        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'is_hired' => false]);
        }

        return response()->json(['success' => true, 'is_hired' => (new Application())->isHiredByEmployer($alumniId, $employerId)]);
    }

    public function jobDetails(Request $request): JsonResponse
    {
        $jobId = (int) $request->query('job_id', '0');
        if (!$jobId) {
            return response()->json(['success' => false, 'message' => 'Job ID required.']);
        }

        $details = (new Job())->detailsWithCompanyById($jobId);
        if (!$details) {
            return response()->json(['success' => false, 'message' => 'Job not found.']);
        }

        return response()->json(array_merge(['success' => true], $details));
    }
}
