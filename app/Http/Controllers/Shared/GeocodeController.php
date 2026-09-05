<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proxies Geoapify geocoding requests so the API key never reaches the
 * browser. Deliberately unauthenticated (no auth middleware on this route)
 * but rate limited per IP. Ported from backend/Controllers/Shared/GeocodeController.php.
 */
class GeocodeController extends Controller
{
    public function autocomplete(Request $request): JsonResponse
    {
        return $this->proxy($request, 'autocomplete', ['text' => $request->query('text', ''), 'limit' => $request->query('limit', '5')]);
    }

    public function search(Request $request): JsonResponse
    {
        return $this->proxy($request, 'search', ['text' => $request->query('text', '')]);
    }

    private function proxy(Request $request, string $endpoint, array $params): JsonResponse
    {
        $text = trim((string) ($params['text'] ?? ''));
        if ($text === '') {
            return response()->json(['features' => []]);
        }

        $ip = $request->ip() ?? '';
        $rateLimiter = new RateLimiter();
        if ($rateLimiter->tooManyAttempts('geocode', $ip, 30, 60)) {
            return response()->json(['error' => 'Too many requests. Please slow down.'], 429);
        }
        $rateLimiter->hit('geocode', $ip);

        $apiKey = env('GEOAPIFY_API_KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'Geocoding is not configured.'], 500);
        }

        $query = ['text' => $text, 'apiKey' => $apiKey];
        if ($endpoint === 'autocomplete') {
            $limit = (int) ($params['limit'] ?? 5);
            $query['limit'] = max(1, min($limit, 10));
        }

        $url = "https://api.geoapify.com/v1/geocode/{$endpoint}?".http_build_query($query);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            error_log("Geoapify {$endpoint} failed (HTTP {$httpCode}): ".($response ?: 'no response'));

            return response()->json(['error' => 'Geocoding request failed.'], 502);
        }

        $data = json_decode($response, true);

        return response()->json(is_array($data) ? $data : ['features' => []]);
    }
}
