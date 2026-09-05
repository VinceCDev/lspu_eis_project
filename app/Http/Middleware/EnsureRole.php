<?php

namespace App\Http\Middleware;

use App\Services\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ported from Auth::requireRole()/requireAnyRole() in the original app.
 * Usage: ->middleware('role:superadmin') or ->middleware('role:admin,superadmin').
 * Also runs the "must be logged in" check first, same as the original
 * (requireRole() called requireLogin() internally).
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!Auth::check()) {
            if (EnforceSessionTimeout::isApiRequest($request)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            return redirect('/login');
        }

        if (!in_array(Auth::role(), $roles, true)) {
            if (EnforceSessionTimeout::isApiRequest($request)) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }

            // Was: redirect('/login') — confusing for a user who IS logged
            // in (ISO 25010 remediation: silently bouncing an authenticated
            // user back to the login form with no explanation looked like a
            // login/session bug rather than a permissions one). They remain
            // just as blocked from the page either way; only the message
            // changes.
            return response()->view('errors.403', [], 403);
        }

        return $next($request);
    }
}
