<?php

namespace App\Services;

use App\Models\Alumni;
use Illuminate\Support\Facades\DB;

/** Ported from backend/Services/JobMatchService.php — raw mysqli calls become the DB facade. */
class JobMatchService
{
    private Alumni $alumniModel;
    private MailService $mailService;
    private GeminiClient $gemini;

    public function __construct(?Alumni $alumniModel = null, ?MailService $mailService = null, ?GeminiClient $gemini = null)
    {
        $this->alumniModel = $alumniModel ?? new Alumni();
        $this->mailService = $mailService ?? new MailService();
        $this->gemini = $gemini ?? new GeminiClient();
    }

    /**
     * Scores every alumni against a newly posted job via Gemini, records the
     * result in the leaderboard, and notifies (in-app + email) matching alumni.
     */
    public function notifyMatchingAlumni(int $jobId, string $title, string $requirements, string $qualifications): void
    {
        foreach ($this->alumniModel->allWithSkills() as $alum) {
            $skillsText = !empty($alum['skills']) ? "Skills: {$alum['skills']}" : '';
            $prompt = "Based on the following information, determine if this is a 'Match' or 'Not a Match' for the job.
                Provide only one of these two phrases as your response, followed by the job match percentage in parentheses (e.g., \"Match (85%)\"), with no additional explanation or text.

                Candidate Background:
                College Program: \"{$alum['course']}\"
                Skills: \"{$skillsText}\"
                Job Details:
                Title: \"{$title}\"
                Requirements: \"{$requirements}\"
                Qualifications: \"{$qualifications}\"
                Response:";

            $match = trim($this->gemini->generate($prompt));
            $matchPercentage = $this->extractMatchPercentage($match);

            if (empty($alum['alumni_id'])) {
                error_log("ERROR: alumni_id is empty for user_id: {$alum['user_id']}");
                continue;
            }

            $isMatch = stripos($match, 'match') !== false && stripos($match, 'not a match') === false;
            $notified = ($isMatch && $matchPercentage >= 50) ? 1 : 0;

            DB::insert('INSERT INTO job_match_leaderboard (alumni_id, job_id, match_percentage, notified) VALUES (?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE match_percentage = VALUES(match_percentage), notified = VALUES(notified)', [
                $alum['alumni_id'], $jobId, $matchPercentage, $notified,
            ]);

            if ($isMatch) {
                $this->notifyAlumni($alum, $jobId, $title, $requirements, $qualifications, $matchPercentage);
            }
        }
    }

    private function notifyAlumni(array $alum, int $jobId, string $title, string $requirements, string $qualifications, int $matchPercentage): void
    {
        $notifMessage = 'New job matches your profile!';
        DB::insert('INSERT INTO notifications (user_id, type, message, details, job_id) VALUES (?, ?, ?, ?, ?)', [
            $alum['user_id'], 'job_match', $notifMessage, $notifMessage, $jobId,
        ]);

        $contact = $this->alumniModel->contactByUserId($alum['user_id']);
        $recipient = $contact['email'] ?? null ?: ($contact['secondary_email'] ?? null);

        if (!$recipient) {
            return;
        }

        $subject = "New Job Match: {$title} ({$matchPercentage}%)";
        $body = "<p>We're excited to inform you about a new job opportunity that matches your profile!</p>"
            .'<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:16px 0;">'
            ."<p style=\"margin:0 0 6px;font-size:17px;font-weight:bold;color:#1A1A1A;\">{$title}</p>"
            ."<p style=\"margin:0 0 10px;\"><span style=\"background:#00A0E9;color:#fff;padding:3px 10px;border-radius:12px;font-size:13px;font-weight:bold;\">{$matchPercentage}% Match</span></p>"
            ."<p style=\"margin:0 0 6px;\"><strong>Requirements:</strong><br>{$requirements}</p>"
            ."<p style=\"margin:0;\"><strong>Qualifications:</strong><br>{$qualifications}</p>"
            .'</div>'
            ."<p>Your course: <strong>{$alum['course']}</strong><br>Your skills: <strong>{$alum['skills']}</strong></p>";
        $html = MailService::wrap("Hello {$alum['first_name']} {$alum['last_name']},", $body, 'View Job in LSPU EIS', config('app.url').'/login');

        $this->mailService->send($recipient, "{$alum['first_name']} {$alum['last_name']}", $subject, $html);
        error_log("Match notification sent for user {$alum['user_id']}");
    }

    private function extractMatchPercentage(string $response): int
    {
        if (preg_match('/\((\d+)%\)/', $response, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
