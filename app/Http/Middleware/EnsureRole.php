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

            return redirect('/login');
        }

        return $next($request);
    }
}
