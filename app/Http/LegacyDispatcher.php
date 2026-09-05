<?php

namespace App\Http;

use Illuminate\Http\Request;

/**
 * Moved out of routes/web.php into an autoloaded class — a plain function
 * declared inside routes/web.php gets redeclared (fatal error) once that
 * file is required more than once in the same PHP process, which happens
 * routinely across a PHPUnit run (each test booting a fresh Application
 * re-requires the route file). A class method is autoloaded once via
 * PSR-4 instead, avoiding the redeclaration entirely.
 */
class LegacyDispatcher
{
    /**
     * Instantiates $controllerClass and invokes the action named by
     * ?action= (or $defaultAction when ?action= is absent or literally
     * "index" — mirrors the original Router::dispatch()'s exact rule),
     * auto-injecting Request into the target method via Laravel's
     * container call resolver so both no-arg (index()) and Request-typed
     * (list(Request $request)) action signatures work unchanged.
     */
    public static function handle(string $controllerClass, string $defaultAction, Request $request)
    {
        $action = $request->query('action', 'index');
        $method = ($action !== 'index' && $action !== '') ? $action : $defaultAction;

        if (!class_exists($controllerClass) || !method_exists($controllerClass, $method)) {
            abort(404);
        }

        $reflection = new \ReflectionMethod($controllerClass, $method);
        if (!$reflection->isPublic()) {
            abort(404);
        }

        return app()->call([app()->make($controllerClass), $method], ['request' => $request]);
    }
}
