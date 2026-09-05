<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline security response headers (ISO/IEC 25010 remediation).
 *
 * CSP note: this app has no build step — every page is a Blade view whose
 * inline `@verbatim` template markup is compiled BY VUE, IN THE BROWSER, at
 * runtime (the full Vue build, not the runtime-only/precompiled one), and
 * relies on inline `<script>`/`<style>` blocks throughout. That combination
 * requires 'unsafe-eval' (Vue's in-browser template compiler uses
 * `new Function(...)`) and 'unsafe-inline' for both script-src and
 * style-src — a strict CSP would break every page. Tightening script-src
 * further would require moving to Vue's precompiled/runtime-only build and
 * externalizing every inline script, which is a frontend build-pipeline
 * change out of scope for this pass. The other directives below (object-src,
 * frame-ancestors, base-uri, form-action, and the img/font/connect
 * allowlists) still meaningfully restrict what a successful XSS or a
 * compromised script could do, even with script-src this loose.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net",
            "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net",
            "img-src 'self' data: https://*.tile.openstreetmap.org",
            "connect-src 'self' https://psgc.gitlab.io https://www.wikidata.org https://auth.emsicloud.com https://emsiservices.com https://universities.hipolabs.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]));

        // HSTS is meaningless (and, per spec, ignored by browsers) over
        // plain HTTP — only send it once a request has actually arrived
        // over HTTPS, so local HTTP development is unaffected.
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
