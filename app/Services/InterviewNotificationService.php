<?php

namespace App\Services;

use App\Models\Employer;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;

/** Ported from backend/Services/InterviewNotificationService.php — APP_URL constant becomes config('app.url'). */
class InterviewNotificationService
{
    private const TITLES = [
        'scheduled' => 'Interview Scheduled',
        'rescheduled' => 'Interview Rescheduled',
        'cancelled' => 'Interview Cancelled',
        'completed' => 'Interview Completed',
    ];

    private const INTROS = [
        'scheduled' => 'We are pleased to inform you that an interview has been scheduled',
        'rescheduled' => 'Your interview has been rescheduled',
        'cancelled' => 'We regret to inform you that your interview has been cancelled',
        'completed' => 'Thank you for completing your interview',
    ];

    public function notify(int $alumniUserId, int $employerUserId, string $jobTitle, string $action, array $interviewData): array
    {
        $title = self::TITLES[$action] ?? 'Interview Update';
        $intro = self::INTROS[$action] ?? "There's an update regarding your interview";

        $employer = (new Employer())->contactDetailsByUserId($employerUserId);
        $companyName = $employer['company_name'] ?? 'The Company';

        (new Notification())->create(
            $alumniUserId,
            'interview_'.$action,
            "{$title} for {$jobTitle} at {$companyName}",
            "You have an interview update for {$jobTitle} at {$companyName}. Please check your email and messages for complete details."
        );

        $alumniEmail = (new User())->findEmailById($alumniUserId);
        $employerEmail = (new User())->findEmailById($employerUserId);

        $formattedDate = !empty($interviewData['interview_date'])
            ? (new \DateTime($interviewData['interview_date']))->format('F j, Y \a\t g:i A')
            : 'To be determined';

        $messageSent = false;
        if ($alumniEmail && $employerEmail) {
            $body = $this->plainTextMessage($action, $jobTitle, $companyName, $formattedDate, $interviewData);
            $messageSent = (new Message())->insertPair($employerEmail, $alumniEmail, $title, $body, 'employer', 'alumni');
        }

        $emailSent = false;
        if ($alumniEmail) {
            $emailSent = (new MailService())->send(
                $alumniEmail,
                $alumniEmail,
                "{$title}: {$jobTitle} at {$companyName}",
                $this->emailBody($action, $title, $intro, $jobTitle, $companyName, $formattedDate, $interviewData),
                $this->plainTextMessage($action, $jobTitle, $companyName, $formattedDate, $interviewData)
            );
        }

        return ['email_sent' => $emailSent, 'message_sent' => $messageSent];
    }

    private function emailBody(string $action, string $title, string $intro, string $jobTitle, string $companyName, string $formattedDate, array $data): string
    {
        $type = htmlspecialchars($data['interview_type'] ?? '');
        $location = !empty($data['location']) ? '<p><strong>Location/Link:</strong> '.htmlspecialchars($data['location']).'</p>' : '';
        $duration = !empty($data['duration']) ? '<p><strong>Duration:</strong> '.htmlspecialchars((string) $data['duration']).' minutes</p>' : '';
        $notes = !empty($data['notes']) ? '<p><strong>Notes:</strong> '.htmlspecialchars($data['notes']).'</p>' : '';

        $body = "<p>{$intro} for the position of ".htmlspecialchars($jobTitle).' at '.htmlspecialchars($companyName).'.</p>'
            .'<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:16px 0;">'
            ."<p style=\"margin:0 0 6px;\"><strong>Date &amp; Time:</strong> {$formattedDate}</p>"
            ."<p style=\"margin:0;\"><strong>Interview Type:</strong> {$type}</p>{$location}{$duration}{$notes}"
            .'</div>'
            .($action === 'cancelled' ? '<p>We apologize for any inconvenience. We will contact you if another opportunity becomes available.</p>' : '');

        return MailService::wrap($title, $body, 'View in LSPU EIS', config('app.url').'/login');
    }

    private function plainTextMessage(string $action, string $jobTitle, string $companyName, string $formattedDate, array $data): string
    {
        return match ($action) {
            'scheduled' => "Your interview for {$jobTitle} at {$companyName} has been scheduled for {$formattedDate}.\n\nInterview type: {$data['interview_type']}\nLocation/Link: ".($data['location'] ?? '')."\n",
            'rescheduled' => "Your interview for {$jobTitle} at {$companyName} has been rescheduled to {$formattedDate}.\n\nInterview type: {$data['interview_type']}\nLocation/Link: ".($data['location'] ?? '')."\n",
            'cancelled' => "Your interview for {$jobTitle} at {$companyName} has been cancelled.\n\nWe apologize for any inconvenience.",
            'completed' => "Your interview for {$jobTitle} at {$companyName} has been marked as completed.\n\nWe will review your application and contact you soon.",
            default => "There's an update regarding your interview for {$jobTitle} at {$companyName}.",
        };
    }
}
