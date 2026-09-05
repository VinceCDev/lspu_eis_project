<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\Application;
use App\Models\Interview;
use App\Models\Job;
use App\Services\Auth;
use App\Services\InterviewNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Employer/InterviewController.php. */
class InterviewController extends Controller
{
    public function index()
    {
        return view('employer.interview', [
            'title' => 'Interviews | LSPU - EIS',
            'active' => 'employer_interview',
            'pageCss' => 'employer_job.css',
            'pageJs' => 'employer_interview.js',
        ]);
    }

    public function list(): JsonResponse
    {
        return response()->json(['success' => true, 'interviews' => (new Interview())->allForEmployer((int) Auth::user()['user_id'])]);
    }

    public function candidates(): JsonResponse
    {
        return response()->json(['success' => true, 'candidates' => (new Application())->candidatesForEmployer((int) Auth::user()['user_id'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->all();
        $result = (new Interview())->scheduleForApplication(
            (int) ($data['application_id'] ?? 0),
            (int) Auth::user()['user_id'],
            $data
        );

        if (!$result) {
            return response()->json(['success' => false, 'message' => 'Invalid application']);
        }

        return response()->json(array_merge(['success' => true, 'message' => 'Interview scheduled successfully'], $result));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->all();
        $interviewId = (int) ($data['interview_id'] ?? 0);

        if (!$interviewId) {
            return response()->json(['success' => false, 'message' => 'Interview ID is required']);
        }

        $ok = (new Interview())->updateForEmployer($interviewId, (int) Auth::user()['user_id'], $data);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Interview updated successfully' : 'Interview not found, access denied, or no fields to update']);
    }

    public function cancel(Request $request): JsonResponse
    {
        $interviewId = (int) $request->input('interview_id', 0);

        if (!$interviewId) {
            return response()->json(['success' => false, 'message' => 'Interview ID is required']);
        }

        $ok = (new Interview())->cancelForEmployer($interviewId, (int) Auth::user()['user_id']);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Interview cancelled successfully' : 'Interview not found or access denied']);
    }

    public function notify(Request $request): JsonResponse
    {
        $data = $request->all();
        $alumniId = (int) ($data['alumni_id'] ?? 0);
        $jobId = (int) ($data['job_id'] ?? 0);
        $action = $data['action'] ?? 'scheduled';

        if (!$alumniId || !$jobId) {
            return response()->json(['success' => false, 'message' => 'Alumni ID and Job ID are required']);
        }

        $alumni = (new Alumni())->findByAlumniId($alumniId);
        if (!$alumni) {
            return response()->json(['success' => false, 'message' => 'Alumni not found']);
        }

        $userId = (int) Auth::user()['user_id'];
        $jobTitle = (new Job())->titleByIdForEmployer($jobId, $userId);
        if (!$jobTitle) {
            return response()->json(['success' => false, 'message' => 'Job not found or access denied']);
        }

        if (!(new Application())->existsForAlumniJob($alumniId, $jobId)) {
            return response()->json(['success' => false, 'message' => 'This alumnus has not applied to this job']);
        }

        $result = (new InterviewNotificationService())->notify((int) $alumni['user_id'], $userId, $jobTitle, $action, $data);

        return response()->json(array_merge(['success' => true, 'message' => 'Interview notification sent'], $result));
    }
}
