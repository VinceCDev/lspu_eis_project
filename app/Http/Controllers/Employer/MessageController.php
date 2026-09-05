<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Concerns\BaseMessageController;
use App\Models\Account;
use Illuminate\Http\JsonResponse;

/**
 * Ported from backend/Controllers/Employer/MessageController.php. Shared
 * mailbox logic (send/list/updateFolder/markRead) lives in
 * BaseMessageController — this class only supplies what's actually
 * role-specific: the page view/config and which accounts an employer is
 * allowed to message.
 */
class MessageController extends BaseMessageController
{
    public function index()
    {
        return view('employer.messages', [
            'title' => 'Messages | LSPU - EIS',
            'active' => 'employer_messages',
            'pageCss' => 'employer_message.css',
            'pageJs' => 'employer_messages.js',
            'extraHead' => '<link href="'.asset('assets/vendor/quill/quill.snow.css').'" rel="stylesheet">',
            'extraScripts' => '<script src="'.asset('assets/vendor/quill/quill.min.js').'"></script>'
                .'<script src="'.asset('assets/vendor/file-saver/FileSaver.min.js').'"></script>',
        ]);
    }

    public function contacts(): JsonResponse
    {
        return response()->json(['success' => true, 'accounts' => (new Account())->messageableForEmployer()]);
    }
}
