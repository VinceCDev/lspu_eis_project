<?php

namespace App\Http\Middleware;

use App\Services\Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ported from Auth::requireLogin() in the original app. Route-group
 * middleware instead of a call at the top of every controller method.
 */
class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            if (EnforceSessionTimeout::isApiRequest($request)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            return redirect('/login');
        }

        return $next($request);
    }
}
