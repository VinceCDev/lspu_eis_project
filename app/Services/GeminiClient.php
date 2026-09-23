<?php

namespace App\Services;

/** Ported from backend/Services/GeminiClient.php — Env::get() calls become Laravel's env(). */
class GeminiClient
{
    public function generate(string $prompt): string
    {
        $apiKey = trim((string) env('GEMINI_API_KEY', ''));
        if ($apiKey === '') {
            return '';   // not configured: callers already fall back to the local classifier; don't make a pointless (403) network call per row/report
        }
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';

        $data = ['contents' => [['parts' => [['text' => $prompt]]]]];

        // The API returns transient 503s under load; retry a couple of times
        // before giving up, since a single failed call silently turns into a
        // "not a match" for that alumni in the caller.
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            // Measured live: gemini-flash-latest's own Server-Timing regularly reads ~9-11s, i.e. right at the edge of
            // a 10s cURL timeout — that made a normal, successful-but-slow reply read as a failure and get retried
            // (up to 3x per call), which is what let a handful of live lookups blow past PHP's execution limit
            // (see ReportController::beforeSlowClassification()). 20s gives real replies headroom to land as a hit.
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-goog-api-key: '.$apiKey,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response && $httpCode === 200) {
                $json = json_decode($response, true);

                return $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            }

            error_log("Gemini API call failed (attempt {$attempt}, HTTP {$httpCode}): ".($response ?: 'no response'));

            // A 4xx won't succeed on retry within the same second — only
            // retry on genuinely transient cases (no response, or a 5xx).
            if ($httpCode >= 400 && $httpCode < 500) {
                break;
            }

            if ($attempt < 3) {
                usleep(500000);
            }
        }

        return '';
    }
}
