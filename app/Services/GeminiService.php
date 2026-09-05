<?php

namespace App\Services;

/** Ported from backend/Services/GeminiService.php — Env::get() calls become Laravel's env(). */
class GeminiService
{
    public function generateProfessionalSummary(array $profile, array $education, array $experience, array $skills): ?string
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return null;
        }

        $course = trim(($profile['course'] ?? '').' '.($profile['college'] ?? ''));
        $skillNames = implode(', ', array_filter(array_map(static fn ($s) => $s['name'] ?? '', $skills)));

        $educationLines = array_map(static function ($e) {
            return trim(($e['degree'] ?? '').' at '.($e['school'] ?? ''));
        }, $education);

        $experienceLines = array_map(static function ($e) {
            return trim(($e['title'] ?? '').' at '.($e['company'] ?? '').': '.($e['description'] ?? ''));
        }, $experience);

        $prompt = 'Write a concise, professional 3-4 sentence resume "Professional Summary" for a job applicant. '
            .'Do not use markdown, bullet points, or a heading, just the paragraph text. '
            .'Name/course context: '.$course."\n"
            .'Skills: '.$skillNames."\n"
            .'Education: '.implode('; ', array_filter($educationLines))."\n"
            .'Experience: '.implode('; ', array_filter($experienceLines))."\n"
            .'Write it in third person absent (no pronouns like "I" or "he/she"), suitable for a resume, confident and specific, no generic filler.';

        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key='.$apiKey;

        $payload = [
            'contents' => [
                'parts' => [
                    ['text' => $prompt],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.6,
                'maxOutputTokens' => 2048,
            ],
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            error_log('Gemini API error: HTTP '.$httpCode.' '.$response);

            return null;
        }

        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        return $text ? trim($text) : null;
    }
}
