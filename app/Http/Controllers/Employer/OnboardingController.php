<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Models\Notification;
use App\Models\Onboarding;
use App\Services\Auth;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Employer/OnboardingController.php. */
class OnboardingController extends Controller
{
    public function index()
    {
        return view('employer.onboarding', [
            'title' => 'Onboarding | LSPU - EIS',
            'active' => 'employer_onboarding',
            'pageCss' => 'employer_job.css',
            'pageJs' => 'employer_onboarding.js',
        ]);
    }

    public function hired(): JsonResponse
    {
        return response()->json(['success' => true, 'hired_applicants' => (new Onboarding())->hiredApplicantsForEmployer((int) Auth::user()['user_id'])]);
    }

    public function data(): JsonResponse
    {
        $model = new Onboarding();
        $employerId = (int) Auth::user()['user_id'];

        return response()->json([
            'success' => true,
            'checklists' => $model->checklistsForEmployer($employerId),
            'onboarding_data' => $model->progressForEmployer($employerId),
        ]);
    }

    public function details(Request $request): JsonResponse
    {
        $onboardingId = (int) $request->query('onboarding_id', '0');
        if (!$onboardingId) {
            return response()->json(['success' => false, 'message' => 'Onboarding ID is required']);
        }

        $details = (new Onboarding())->detailsById($onboardingId, (int) Auth::user()['user_id']);
        if (!$details) {
            return response()->json(['success' => false, 'message' => 'Onboarding record not found']);
        }

        return response()->json(['success' => true, 'onboarding_details' => $details]);
    }

    public function items(): JsonResponse
    {
        return response()->json(['success' => true, 'checklist_items' => (new Onboarding())->checklistItemsForEmployer((int) Auth::user()['user_id'])]);
    }

    public function saveChecklist(Request $request): JsonResponse
    {
        $data = $request->all();
        if (empty($data['title'])) {
            return response()->json(['success' => false, 'message' => 'Checklist title is required']);
        }

        $checklistId = (new Onboarding())->saveChecklist((int) Auth::user()['user_id'], $data['title'], $data['description'] ?? '', $data['items'] ?? []);

        return response()->json(['success' => true, 'message' => 'Checklist saved successfully', 'checklist_id' => $checklistId]);
    }

    public function updateChecklist(Request $request): JsonResponse
    {
        $data = $request->all();
        if (empty($data['checklist_id']) || empty($data['title'])) {
            return response()->json(['success' => false, 'message' => 'Checklist ID and title are required']);
        }

        $ok = (new Onboarding())->updateChecklist((int) $data['checklist_id'], (int) Auth::user()['user_id'], $data['title'], $data['description'] ?? '', $data['items'] ?? []);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Checklist updated successfully' : 'Failed to update checklist']);
    }

    public function deleteChecklist(Request $request): JsonResponse
    {
        $data = $request->all();
        if (empty($data['checklist_id'])) {
            return response()->json(['success' => false, 'message' => 'Checklist ID is required']);
        }

        $result = (new Onboarding())->deleteChecklist((int) $data['checklist_id'], (int) Auth::user()['user_id']);

        return response()->json(['success' => $result['ok'], 'message' => $result['message']]);
    }

    public function updateItem(Request $request): JsonResponse
    {
        $data = $request->all();
        if (!isset($data['onboarding_id'], $data['item_id'])) {
            return response()->json(['success' => false, 'message' => 'Onboarding ID and Item ID are required']);
        }

        $ok = (new Onboarding())->updateChecklistItem((int) $data['onboarding_id'], (int) $data['item_id'], !empty($data['is_completed']), (int) Auth::user()['user_id']);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Checklist item updated successfully' : 'Error updating checklist item']);
    }

    public function assign(Request $request)
    {
        $data = $request->all();
        if (!isset($data['application_id'], $data['checklist_id'])) {
            return response()->json(['success' => false, 'message' => 'Missing required fields']);
        }

        $applicationId = (int) $data['application_id'];
        $checklistId = (int) $data['checklist_id'];
        $employerUserId = (int) Auth::user()['user_id'];

        $onboarding = new Onboarding();
        $ok = $onboarding->assignChecklist($applicationId, $checklistId, $employerUserId);

        // The alumni email below is the same slow SMTP call that used to make
        // applicant status updates feel frozen — respond first, notify after.
        $this->respondEarly(['success' => $ok, 'message' => $ok ? 'Checklist assigned successfully' : 'Failed to assign checklist']);

        if ($ok) {
            $this->notifyChecklistAssigned($onboarding, $applicationId, $checklistId, $employerUserId);
        }

        return null;
    }

    public function markComplete(Request $request)
    {
        $data = $request->all();
        if (!isset($data['onboarding_id'])) {
            return response()->json(['success' => false, 'message' => 'Onboarding ID is required']);
        }

        $onboardingId = (int) $data['onboarding_id'];
        $employerUserId = (int) Auth::user()['user_id'];

        $onboarding = new Onboarding();
        $ok = $onboarding->markComplete($onboardingId, $employerUserId);

        $this->respondEarly(['success' => $ok, 'message' => $ok ? 'Onboarding marked as complete' : 'Error marking onboarding as complete']);

        if ($ok) {
            $this->notifyOnboardingComplete($onboarding, $onboardingId, $employerUserId);
        }

        return null;
    }

    private function notifyChecklistAssigned(Onboarding $onboarding, int $applicationId, int $checklistId, int $employerUserId): void
    {
        $contact = $onboarding->alumniContactForApplication($applicationId);
        if (!$contact) {
            return;
        }

        $employer = (new Employer())->contactDetailsByUserId($employerUserId);
        $companyName = $employer['company_name'] ?? '';
        $checklistTitle = $onboarding->checklistTitle($checklistId) ?? 'Onboarding Checklist';

        (new Notification())->create(
            (int) $contact['user_id'],
            'system',
            'A new onboarding checklist has been assigned to you',
            "\"{$checklistTitle}\" was assigned to you by {$companyName}. Please complete the required items."
        );

        $recipient = $contact['email'] ?: $contact['secondary_email'];
        if (!$recipient) {
            return;
        }

        $name = htmlspecialchars($contact['first_name'].' '.$contact['last_name']);
        $company = htmlspecialchars($companyName);

        $itemsHtml = '<ul style="margin:0;padding-left:20px;">';
        foreach ($onboarding->itemsForChecklist($checklistId) as $item) {
            $text = htmlspecialchars($item['item_text']);
            $required = $item['is_required'] ? ' <strong style="color:#dc2626;">(Required)</strong>' : '';
            $itemsHtml .= "<li style=\"margin-bottom:6px;\">{$text}{$required}</li>";
        }
        $itemsHtml .= '</ul>';

        $body = "<p>You have a new onboarding checklist to complete for your position at <b>{$company}</b>.</p>"
            .'<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:16px 0;">'
            .'<p style="margin:0 0 10px;font-weight:bold;">'.htmlspecialchars($checklistTitle).'</p>'
            .$itemsHtml
            .'</div>'
            .'<p>Please complete these items as soon as possible.</p>';

        $html = MailService::wrap("Hello {$name},", $body, 'Login to LSPU EIS', config('app.url').'/login');
        (new MailService())->send($recipient, $name, "Onboarding Checklist: {$checklistTitle}", $html);
    }

    private function notifyOnboardingComplete(Onboarding $onboarding, int $onboardingId, int $employerUserId): void
    {
        $applicationId = $onboarding->applicationIdForOnboarding($onboardingId, $employerUserId);
        if (!$applicationId) {
            return;
        }

        $contact = $onboarding->alumniContactForApplication($applicationId);
        if (!$contact) {
            return;
        }

        $employer = (new Employer())->contactDetailsByUserId($employerUserId);
        $companyName = $employer['company_name'] ?? '';

        (new Notification())->create(
            (int) $contact['user_id'],
            'system',
            'Onboarding completed',
            "Your onboarding at {$companyName} has been marked complete. Welcome aboard!"
        );

        $recipient = $contact['email'] ?: $contact['secondary_email'];
        if (!$recipient) {
            return;
        }

        $name = htmlspecialchars($contact['first_name'].' '.$contact['last_name']);
        $company = htmlspecialchars($companyName);

        $itemsHtml = '';
        $details = $onboarding->detailsById($onboardingId, $employerUserId);
        if ($details && !empty($details['checklist_items'])) {
            $itemsHtml = '<ul style="margin:0;padding-left:20px;">';
            foreach ($details['checklist_items'] as $item) {
                $text = htmlspecialchars($item['item_text']);
                $itemsHtml .= '<li style="margin-bottom:6px;"><span style="color:#16a34a;">&#10003;</span> '.$text.'</li>';
            }
            $itemsHtml .= '</ul>';
        }

        $body = "<p>Great news — your onboarding checklist at <b>{$company}</b> has been marked <strong>complete</strong>.</p>"
            .($itemsHtml !== '' ? '<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:16px 0;">'
                .'<p style="margin:0 0 10px;font-weight:bold;">Completed items</p>'
                .$itemsHtml
                .'</div>' : '')
            .'<p>Welcome aboard!</p>';
        $html = MailService::wrap("Congratulations, {$name}!", $body, 'Login to LSPU EIS', config('app.url').'/login');
        (new MailService())->send($recipient, $name, 'Onboarding Complete', $html);
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

    public function saveNotes(Request $request): JsonResponse
    {
        $data = $request->all();
        if (!isset($data['onboarding_id'])) {
            return response()->json(['success' => false, 'message' => 'Onboarding ID is required']);
        }

        $ok = (new Onboarding())->saveNotes((int) $data['onboarding_id'], $data['notes'] ?? '', (int) Auth::user()['user_id']);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Notes saved successfully' : 'Error saving notes']);
    }

    public function sendWelcomeEmail(Request $request): JsonResponse
    {
        $data = $request->all();
        if (!isset($data['application_id'], $data['email'], $data['name'])) {
            return response()->json(['success' => false, 'message' => 'Missing required fields']);
        }

        $onboarding = new Onboarding();
        $applicationId = (int) $data['application_id'];
        $userId = (int) Auth::user()['user_id'];

        $employer = (new Employer())->contactDetailsByUserId($userId);
        if (!$employer) {
            return response()->json(['success' => false, 'message' => 'Employer details not found']);
        }

        $summary = $onboarding->applicationSummary($applicationId, $userId);
        if (!$summary) {
            return response()->json(['success' => false, 'message' => 'Application details not found']);
        }

        $companyName = $employer['company_name'];
        $html = $this->welcomeEmailHtml($companyName, $summary['first_name'], $summary['last_name'], $summary['title'], Auth::user()['email']);
        $alt = "Hello {$summary['first_name']} {$summary['last_name']},\n\nWelcome to {$companyName}!\n\nWe are thrilled to welcome you as our new {$summary['title']}.\n\nYou will receive onboarding instructions shortly. Please complete your employee profile and review company policies.\n\nBest regards,\nThe {$companyName} Team";

        $sent = (new MailService())->send($data['email'], $data['name'], "Welcome to {$companyName}!", $html, $alt);
        if (!$sent) {
            return response()->json(['success' => false, 'message' => 'Failed to send email']);
        }

        $onboarding->recordWelcomeEmailSent($applicationId);

        return response()->json(['success' => true, 'message' => 'Welcome email sent successfully']);
    }

    private function welcomeEmailHtml(string $companyName, string $firstName, string $lastName, string $jobTitle, string $employerEmail): string
    {
        $companyName = htmlspecialchars($companyName);
        $firstName = htmlspecialchars($firstName);
        $jobTitle = htmlspecialchars($jobTitle);
        $employerEmail = htmlspecialchars($employerEmail);

        return MailService::wrap(
            "Welcome to {$companyName}, {$firstName}!",
            "<p>We are thrilled to welcome you to <strong>{$companyName}</strong> as our new <strong>{$jobTitle}</strong>!</p>"
                .'<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:16px 0;">'
                .'<p style="margin:0 0 6px;"><strong>Next steps</strong></p>'
                .'<p style="margin:0;">You will receive onboarding instructions shortly. Please complete your employee profile, review company policies and benefits, and prepare for your first day orientation.</p>'
                .'</div>'
                ."<p>Your hiring manager: {$companyName} Hiring Team (<a href=\"mailto:{$employerEmail}\" style=\"color:#00A0E9;\">{$employerEmail}</a>)</p>"
                ."<p>Best regards,<br>The {$companyName} Team</p>"
        );
    }
}
