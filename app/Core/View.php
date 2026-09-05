<?php

namespace App\Core;

/**
 * Compatibility shim for the original app's backend/Core/View.php, kept
 * only for `View::partial()` — every copied Blade view still calls this
 * exact same way (`App\Core\View::partial('admin/header', [...])`) so
 * partial includes didn't need touching in every one of the ~50 ported
 * view files during the Blade conversion pass. Backed by Laravel's real
 * view()/Blade rendering underneath.
 */
class View
{
    public static function partial(string $partial, array $data = []): void
    {
        echo view('partials.'.str_replace('/', '.', $partial), $data)->render();
    }
}
