<?php

namespace App\Core;

use App\Services\Auth as AuthService;
use Illuminate\Support\Facades\Session;

/**
 * Compatibility shim for the original app's backend/Core/Auth.php — kept
 * only for the two static calls views still make directly under this
 * fully-qualified class name (`\App\Core\Auth::role()`,
 * `\App\Core\Auth::csrfToken()`), so those ~50 copied view files didn't
 * need touching during the Blade conversion pass. role() delegates to the
 * real App\Services\Auth; csrfToken() is a vestigial field the login/
 * signup forms still render but that Laravel's CSRF protection (Origin/
 * Referer, see App\Http\Middleware\RejectCrossOriginPost) doesn't check.
 */
class Auth
{
    public static function role(): ?string
    {
        return AuthService::role();
    }

    public static function csrfToken(): string
    {
        if (!Session::has('csrf_token')) {
            Session::put('csrf_token', bin2hex(random_bytes(32)));
        }

        return Session::get('csrf_token');
    }
}
