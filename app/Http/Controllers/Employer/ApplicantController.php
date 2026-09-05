<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\Application;
use App\Models\Employer;
use App\Models\Interview;
use App\Models\Notification;
use App\Services\Auth;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Employer/ApplicantController.php. */
class ApplicantController extends Controller
{
    public function index()
    {
        return view('employer.applicants', [
            'title' => 'Applicants | LSPU - EIS',
            'active' => 'employer_applicants',
            'pageCss' => 'employer_applicants.css',
            'pageJs' => 'employer_applicants.js',
        ]);
    }

    public function list(): JsonResponse
    {
        $applications = (new Application())->allForEmployerWithDetails((int) Auth::user()['user_id']);

        return response()->json(['applications' => $applications]);
    }

    /**
     * Flushes a JSON response to the client immediately then keeps executing
     * (interview placeholder, notification, status email) — same
     * respondEarly() trick as the original app, which also runs under
     * Apache mod_php (no fastcgi_finish_request there either — the
     * ob_end_flush()+flush() alone is what makes the client's fetch()
     * resolve without waiting on the SMTP call that follows).
     */
    public function updateStatus(Request $request)
    {
        $applicationId = (int) $request->input('application_id', 0);
        $status = $request->input('status', '');

        if (!$applicationId || $status === '') {
            return response()->json(['success' => false, 'message' => 'Missing application_id or status.']);
        }

        $employerUserId = (int) Auth::user()['user_id'];
        $applicationModel = new Application();

        $ok = $applicationModel->updateStatusForEmployer($applicationId, $employerUserId, $status);
        if (!$ok) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to update this application.']);
        }

        $this->respondEarly(['success' => true]);

        if ($status === 'Interview') {
            $interviewModel = new Interview();
            if (!$interviewModel->existsForApplication($applicationId)) {
                $interviewModel->scheduleForApplication($applicationId, $employerUserId, [
                    'interview_date' => date('Y-m-d', strtotime('+3 days')).' 09:00:00',
                    'duration' => 30,
                    'interview_type' => 'Video Call',
                    'location' => '',
                    'status' => 'Scheduled',
                    'notes' => 'Auto-created when marked for interview — update the date, time, and details.',
                ]);
            }
        }

        $employer = (new Employer())->contactDetailsByUserId($employerUserId);
        $companyName = $employer['company_name'] ?? '';

        $alumniUserId = $applicationModel->alumniUserIdForApplication($applicationId);
        if ($alumniUserId) {
            $details = match ($status) {
                'Hired' => "Congratulations! You have been hired for the position at {$companyName}.",
                'Rejected' => "We regret to inform you that you were not selected for the position at {$companyName}.",
                'Interview' => "You have been invited for an interview for the position at {$companyName}.",
                default => 'Your application status has been updated to '.htmlspecialchars($status)." at {$companyName}.",
            };

            $jobId = $applicationModel->jobIdForApplication($applicationId);
            (new Notification())->create($alumniUserId, 'application', 'Your application status has been updated', $details, $jobId);

            if (in_array($status, ['Hired', 'Rejected', 'Interview'], true)) {
                $this->sendStatusEmail($alumniUserId, $status, $companyName, $employer['contact_email'] ?? '', $employer['contact_number'] ?? '');
            }
        }

        return null;
    }

    private function respondEarly(array $data): void
    {
        if (ob_get_level() === 0) {
            ob_start();
        }
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($data);
        header('Content-Length: '.ob_get_length());
        header('Connection: close');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        ob_end_flush();
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    private function sendStatusEmail(int $alumniUserId, string $status, string $companyName, string $contactEmail, string $contactNumber): void
    {
        $contact = (new Alumni())->contactByUserId($alumniUserId);
        if (!$contact) {
            return;
        }

        $recipient = $contact['email'] ?: $contact['secondary_email'];
        if (!$recipient) {
            return;
        }

        $name = htmlspecialchars($contact['first_name'].' '.$contact['last_name']);
        $company = htmlspecialchars($companyName);

        [$subject, $heading, $intro] = match ($status) {
            'Hired' => ['Congratulations! You have been hired', "Congratulations, {$name}!", "<p>You have been <strong>hired</strong> for the position you applied for at <b>{$company}</b>.</p>"],
            'Interview' => ['Interview Invitation', 'Interview Invitation', "<p>Dear {$name},</p><p>Congratulations! You have been selected for an <strong>interview</strong> for the position at <b>{$company}</b>.</p>"],
            default => ['Application Update', 'Application Update', "<p>Dear {$name},</p><p>Thank you for your application. We regret to inform you that you have not been selected for the position at <b>{$company}</b> at this time.</p>"],
        };

        $body = $intro
            .'<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:16px 0;">'
            .'<p style="margin:0 0 6px;"><strong>Company Contact</strong></p>'
            .'<p style="margin:0 0 4px;">Email: <a href="mailto:'.htmlspecialchars($contactEmail).'" style="color:#00A0E9;">'.htmlspecialchars($contactEmail).'</a></p>'
            .'<p style="margin:0;">Phone: '.htmlspecialchars($contactNumber).'</p>'
            .'</div>'
            ."<p>Best regards,<br>{$company} - LSPU EIS Team</p>";

        (new MailService())->send($recipient, $name, $subject, MailService::wrap($heading, $body, 'Login to LSPU EIS', config('app.url').'/login'));
    }
}
