<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\SuccessStory;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Shared/PageController.php. */
class PageController extends Controller
{
    public function employerTerms()
    {
        return view('shared.employer_terms');
    }

    public function alumniTerms()
    {
        return view('shared.alumni_terms');
    }

    public function userType()
    {
        return view('shared.user_type');
    }

    public function landing()
    {
        return view('shared.landing', ['hero' => (new SiteSetting())->all()]);
    }

    public function publicSuccessStories(): JsonResponse
    {
        return response()->json(['success' => true, 'stories' => (new SuccessStory())->publishedForPublic()]);
    }

    public function contact(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('name', ''));
        $age = trim((string) $request->input('age', ''));
        $email = trim((string) $request->input('email', ''));
        $message = trim((string) $request->input('message', ''));

        if (!$name || !$age || !$email || !$message) {
            return response()->json(['success' => false, 'message' => 'All fields are required']);
        }

        if (!is_numeric($age) || $age < 1 || $age > 120) {
            return response()->json(['success' => false, 'message' => 'Please enter a valid age (1-120)']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['success' => false, 'message' => 'Invalid email format']);
        }

        $htmlBody = MailService::wrap(
            'New Contact Form Submission',
            '<p>'.nl2br(htmlspecialchars($message)).'</p>'
                .'<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:16px 0;">'
                .'<p style="margin:0 0 4px;"><strong>From:</strong> '.htmlspecialchars($name).'</p>'
                .'<p style="margin:0 0 4px;"><strong>Age:</strong> '.htmlspecialchars($age).'</p>'
                .'<p style="margin:0;"><strong>Email:</strong> '.htmlspecialchars($email).'</p>'
                .'</div>'
        );
        $altBody = "Dear Sir/Ma'am,\n\n{$message}\n\nBest regards,\n{$name}\nAge: {$age}\nEmail: {$email}";

        $sent = (new MailService())->send('lspueis@gmail.com', 'Allen Cristal', 'LSPU-EIS Contact Form Submission', $htmlBody, $altBody, $email, $name);

        return response()->json($sent
            ? ['success' => true, 'message' => 'Message sent successfully! We will get back to you soon.']
            : ['success' => false, 'message' => 'Failed to send message. Please try again later.']);
    }
}
