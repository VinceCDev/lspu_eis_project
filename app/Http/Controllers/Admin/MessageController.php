<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\BaseMessageController;
use App\Models\Account;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;

/**
 * Ported from backend/Controllers/Admin/MessageController.php. Shared
 * mailbox logic (send/list/updateFolder/markRead) lives in
 * BaseMessageController — this class only supplies what's actually
 * role-specific: the page view/config and which accounts an admin (or
 * superadmin, which reuses this same controller — see routes/web.php) is
 * allowed to message.
 */
class MessageController extends BaseMessageController
{
    public function index()
    {
        return view('admin.message', [
            'title' => 'Messages | LSPU - EIS',
            'active' => Auth::role() === 'superadmin' ? 'superadmin_message' : 'admin_message',
            'pageCss' => 'admin_message.css',
            'pageJs' => 'admin_message.js',
            'extraHead' => '<link href="'.asset('assets/vendor/quill/quill.snow.css').'" rel="stylesheet">',
            'extraScripts' => '<script src="'.asset('assets/vendor/quill/quill.min.js').'"></script>'
                .'<script src="'.asset('assets/vendor/file-saver/FileSaver.min.js').'"></script>',
        ]);
    }

    public function contacts(): JsonResponse
    {
        return response()->json(['success' => true, 'accounts' => (new Account())->allAccounts()]);
    }
}
