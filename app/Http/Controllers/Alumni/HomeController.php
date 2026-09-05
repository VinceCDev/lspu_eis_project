<?php

namespace App\Http\Controllers\Alumni;

use App\Core\Uploader;
use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\Application;
use App\Models\Employer;
use App\Models\Job;
use App\Models\Notification;
use App\Models\SavedJob;
use App\Models\User;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/** Ported from backend/Controllers/Alumni/HomeController.php. Role gating handled by route middleware. */
class HomeController extends Controller
{
    public function index(Request $request)
    {
        if ($request->query('job_id') !== null && is_numeric($request->query('job_id'))) {
            Session::put('highlight_job_id', $request->query('job_id'));
        }

        return view('alumni.home', [
            'title' => 'Home | LSPU - EIS',
            'active' => 'home',
            'pageCss' => 'home.css',
            'pageJs' => 'home.js',
            'extraHead' => '<link rel="stylesheet" href="'.asset('assets/vendor/leaflet/leaflet.css').'">',
            'extraScripts' => '<script src="'.asset('assets/vendor/leaflet/leaflet.js').'"></script>'
                .'<script src="'.asset('assets/js/utils/jobFilters.js').'"></script>'
                .'<script>window.USER_ID = '.json_encode((int) Auth::user()['user_id']).';</script>',
        ]);
    }

    public function jobs(): JsonResponse
    {
        return response()->json((new Job())->allActiveWithEmployer());
    }

    public function savedJobs(): JsonResponse
    {
        return response()->json(['savedJobIds' => (new SavedJob())->idsForUser((int) Auth::user()['user_id'])]);
    }

    public function toggleSave(Request $request): JsonResponse
    {
        $jobId = (int) $request->input('job_id', 0);
        $action = $request->input('action', 'save');

        if (!$jobId) {
            return response()->json(['success' => false, 'message' => 'Invalid job ID']);
        }

        $model = new SavedJob();
        $userId = (int) Auth::user()['user_id'];
        $ok = $action === 'unsave' ? $model->unsave($userId, $jobId) : $model->save($userId, $jobId);

        return response()->json(['success' => $ok, 'message' => $ok ? ($action === 'unsave' ? 'Job unsaved!' : 'Job saved!') : 'Failed to '.$action.' job']);
    }

    public function hiredCompanies(): JsonResponse
    {
        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'hired_companies' => []]);
        }

        return response()->json(['success' => true, 'hired_companies' => (new Application())->hiredEmployerIdsForAlumni($alumniId)]);
    }

    public function employers(): JsonResponse
    {
        return response()->json((new Employer())->allOrdered());
    }

    public function employerDetails(Request $request): JsonResponse
    {
        $employerId = (int) $request->query('employer_id', '0');
        if (!$employerId) {
            return response()->json(['success' => false, 'message' => 'Missing employer_id']);
        }

        $employer = (new Employer())->findByUserId($employerId);
        if (!$employer) {
            return response()->json(['success' => false, 'message' => 'Employer not found']);
        }

        return response()->json(['success' => true, 'data' => $employer]);
    }

    public function apply(Request $request): JsonResponse
    {
        $jobId = (int) $request->input('job_id', 0);
        if (!$jobId) {
            return response()->json(['success' => false, 'message' => 'Job ID required']);
        }

        $alumni = new Alumni();
        $userId = (int) Auth::user()['user_id'];
        $alumniId = $alumni->alumniIdByUserId($userId);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found']);
        }

        $application = new Application();
        if ($application->existsForAlumniJob($alumniId, $jobId)) {
            return response()->json(['success' => false, 'message' => 'Already applied']);
        }

        $coverLetterMode = $request->input('cover_letter_mode', 'skip');
        $coverLetterText = null;
        $coverLetterFile = null;
        if ($coverLetterMode === 'write') {
            $coverLetterText = trim($request->input('cover_letter_text', '')) ?: null;
        } elseif ($coverLetterMode === 'upload' && $request->hasFile('cover_letter_file')) {
            $file = $request->file('cover_letter_file');
            $coverLetterFile = Uploader::store([
                'name' => $file->getClientOriginalName(),
                'type' => $file->getClientMimeType(),
                'tmp_name' => $file->getPathname(),
                'error' => $file->getError(),
                'size' => $file->getSize(),
            ], 'cover_letters');
        }

        $questionMeta = (new Job())->questionMetaById($jobId);
        $applicationAnswer = trim($request->input('employer_question_answer', '')) ?: null;
        if ($questionMeta && $questionMeta['employer_question'] !== '' && $questionMeta['employer_question_required'] && !$applicationAnswer) {
            return response()->json(['success' => false, 'message' => 'Please answer the employer question before submitting.']);
        }

        if (!$application->createForAlumni($alumniId, $jobId, $coverLetterText, $coverLetterFile, $applicationAnswer)) {
            return response()->json(['success' => false, 'message' => 'Failed to apply']);
        }

        // A job an alumnus has now applied to no longer belongs in their
        // "to consider" Saved Jobs list.
        (new SavedJob())->unsave($userId, $jobId);

        $jobTitle = (new Job())->titleById($jobId) ?? 'the job';
        $notification = new Notification();
        $notification->create(
            $userId,
            'application',
            'You successfully applied for a job.',
            "You have applied for the position of {$jobTitle}. Please wait for further announcement.",
            $jobId
        );

        $employerUserId = (new Job())->employerIdByJobId($jobId);
        if ($employerUserId) {
            $applicantName = trim(($alumni->detailsByUserId($userId)['name'] ?? '')) ?: 'An alumnus';
            $notification->create(
                $employerUserId,
                'new_application',
                'New applicant for your job posting',
                "{$applicantName} applied for the position of {$jobTitle}.",
                $jobId
            );
        }

        return response()->json(['success' => true]);
    }

    public function checkNewUser(): JsonResponse
    {
        $createdAt = (new User())->createdAtById((int) Auth::user()['user_id']);
        if (!$createdAt) {
            return response()->json(['success' => false, 'is_new_user' => false]);
        }

        $now = new \DateTime();
        $created = new \DateTime($createdAt);
        $days = $now->diff($created)->days;
        $isToday = $now->format('Y-m-d') === $created->format('Y-m-d');

        return response()->json([
            'success' => true,
            'is_new_user' => $days <= 7 || $isToday,
            'account_age_days' => $days,
            'created_at' => $createdAt,
            'is_today' => $isToday,
        ]);
    }

    public function clearHighlight(): JsonResponse
    {
        Session::forget('highlight_job_id');

        return response()->json(['success' => true]);
    }
}
