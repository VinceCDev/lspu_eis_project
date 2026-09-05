<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Admin/MessageController.php. */
class MessageController extends Controller
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

    public function list(): JsonResponse
    {
        $mailbox = (new Message())->mailboxForEmail(Auth::user()['email']);

        return response()->json(array_merge(['success' => true], $mailbox));
    }

    public function send(Request $request): JsonResponse
    {
        $receiver = $request->input('receiver_email', '');
        $subject = $request->input('subject', '');
        $body = $request->input('message', '');
        $role = $request->input('role', '');

        if (!$receiver || !$subject || !$body) {
            return response()->json(['success' => false, 'message' => 'All fields are required.']);
        }

        $senderEmail = Auth::user()['email'];
        $messageId = (new Message())->send($senderEmail, $receiver, $subject, $body, $role);
        $ok = $messageId > 0;

        if ($ok) {
            $this->notifyReceiver($senderEmail, $receiver, $subject, $messageId);
        }

        return response()->json(['success' => $ok, 'message' => $ok ? 'Message sent successfully.' : 'Failed to send message.']);
    }

    public function updateFolder(Request $request): JsonResponse
    {
        $id = (int) $request->input('id', 0);
        $folder = $request->input('folder', '');
        if (!$id || !$folder) {
            return response()->json(['success' => false, 'message' => 'Missing id or folder.']);
        }

        $ok = (new Message())->updateFolder($id, $folder, Auth::user()['email']);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Message updated.' : 'Failed to update message.']);
    }

    public function markRead(Request $request): JsonResponse
    {
        $id = (int) $request->input('id', 0);
        if (!$id) {
            return response()->json(['success' => false, 'message' => 'Missing id.']);
        }

        $ok = (new Message())->markRead($id, Auth::user()['email']);

        return response()->json(['success' => $ok]);
    }

    private function notifyReceiver(string $senderEmail, string $receiverEmail, string $subject, int $messageId): void
    {
        if ($receiverEmail === $senderEmail) {
            return;
        }

        $receiverUser = (new User())->findByEmail($receiverEmail);
        if (!$receiverUser) {
            return;
        }

        (new Notification())->create(
            (int) $receiverUser['user_id'],
            'message',
            'New message from '.$senderEmail,
            '',
            null,
            $messageId
        );
    }
}
