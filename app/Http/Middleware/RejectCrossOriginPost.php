<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ported from Auth::rejectCrossOriginPost()/isCrossOriginPost() in the
 * original app. Registered in place of Laravel's default VerifyCsrfToken —
 * the existing 40 frontend JS files don't send Laravel's _token/
 * X-CSRF-TOKEN, so swapping to Laravel's default would reject every real
 * POST from the untouched frontend. Origin/Referer checking gives the same
 * protection with zero frontend changes: both headers are sent by every
 * modern browser on a same-origin POST, and a cross-site page forging a
 * request here can't fake either one to this app's own origin.
 *
 * Rejects when a header IS present and mismatches, AND when both are
 * absent — a real browser sends at least one of these on every POST (Origin
 * on virtually all modern POSTs; Referer as a fallback even when a privacy
 * setting suppresses Origin), so a request with neither is either a
 * non-browser client or a browser stripping both. This app has no
 * legitimate non-browser POST caller (the one unauthenticated automation
 * endpoint, api_reminder, is GET-only with its own shared-secret check), so
 * default-deny here rather than fail open.
 */
class RejectCrossOriginPost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (self::isCrossOriginPost($request, config('app.url'))) {
            if (EnforceSessionTimeout::isApiRequest($request)) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }

            return response('Forbidden', 403);
        }

        return $next($request);
    }

    public static function isCrossOriginPost(Request $request, string $appUrl): bool
    {
        if (!$request->isMethod('POST')) {
            return false;
        }

        $expected = rtrim($appUrl, '/');
        if ($expected === '') {
            return false;
        }

        // Origin is always just scheme://host:port, never a path — even for
        // an app that lives in a subdirectory like APP_URL here. Comparing
        // it against the full APP_URL (path included) would reject every
        // real same-origin POST a browser ever sends.
        // The origin the request was actually served on (scheme://host:port,
        // non-default port included). Accepting this alongside APP_URL lets
        // the app be reached by IP, by hostname, or behind a proxy that
        // forwards the real Host — a genuine cross-site POST still carries a
        // third-party Origin/Referer that matches neither.
        $self = rtrim($request->getSchemeAndHttpHost(), '/');

        $origin = $request->header('Origin');
        if ($origin !== null) {
            $expectedParts = parse_url($expected);
            $expectedOrigin = ($expectedParts['scheme'] ?? '').'://'.($expectedParts['host'] ?? '')
                .(isset($expectedParts['port']) ? ':'.$expectedParts['port'] : '');

            $origin = rtrim($origin, '/');

            return $origin !== $expectedOrigin && $origin !== $self;
        }

        // Referer includes the full path, so this stays a prefix check.
        $referer = $request->header('Referer');
        if ($referer !== null) {
            return !str_starts_with($referer, $expected.'/') && rtrim($referer, '/') !== $expected
                && !str_starts_with($referer, $self.'/') && rtrim($referer, '/') !== $self;
        }

        // Neither header present — default-deny (see class docblock).
        return true;
    }
}
