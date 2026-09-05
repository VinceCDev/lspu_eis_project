<?php

namespace App\Services;

/** Ported from backend/Services/SmsService.php — Env::get() calls become Laravel's env(). */
class SmsService
{
    /** @return array{success: bool, message: string} */
    public function send(string $rawPhone, string $message): array
    {
        $phone = preg_replace('/[^0-9+]/', '', $rawPhone);

        if (str_starts_with($phone, '+63')) {
            $phone = substr($phone, 3);
        } elseif (str_starts_with($phone, '63')) {
            $phone = substr($phone, 2);
        } elseif (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        if (strlen($phone) !== 10) {
            return ['success' => false, 'message' => 'Invalid phone number format. Expected 10 digits, got '.strlen($phone).' digits: '.$phone];
        }

        $phone = '63'.$phone;

        $apiUrl = env('SMS_API_URL', '');
        if ($apiUrl === '') {
            return ['success' => false, 'message' => 'SMS API is not configured.'];
        }

        $payload = [
            'secret' => env('SMS_API_KEY', ''),
            'mode' => 'devices',
            'device' => env('SMS_DEVICE', ''),
            'phone' => $phone,
            'message' => $message,
            'sim' => 1,
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'cURL Error: '.$error];
        }

        $result = json_decode($response, true);

        if ($httpCode === 200 && isset($result['status']) && $result['status'] == 200) {
            return ['success' => true, 'message' => 'SMS queued successfully.'];
        }

        return ['success' => false, 'message' => 'HTTP Error: '.$httpCode.' - '.($result['message'] ?? $response)];
    }
}
