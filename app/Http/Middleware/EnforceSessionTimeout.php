<?php

namespace App\Http\Middleware;

use App\Services\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ported from the original app's Auth::enforceTimeout() call in index.php,
 * run globally so every request gets the same idle-timeout check the old
 * app applied right after session_start().
 */
class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::enforceTimeout()) {
            if (self::isApiRequest($request)) {
                return response()->json(['success' => false, 'message' => 'Session expired. Please log in again.'], 401);
            }

            return redirect('/login?expired=1');
        }

        return $next($request);
    }

    public static function isApiRequest(Request $request): bool
    {
        return $request->has('action')
            || strtolower((string) $request->header('X-Requested-With')) === 'xmlhttprequest'
            || str_contains((string) $request->header('Accept'), 'application/json');
    }
}
