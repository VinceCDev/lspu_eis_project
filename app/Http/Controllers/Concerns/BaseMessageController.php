<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared message-mailbox behavior for Admin\MessageController and
 * Employer\MessageController (ISO 25010 remediation, item 13 — the two
 * were ~95% line-for-line duplicates). index() (view/page config) and
 * contacts() (which accounts a role is allowed to message) are the only
 * two things that actually differ per role, so those stay abstract; every
 * other mailbox action (send/list/updateFolder/markRead + the notification
 * side-effect) was byte-identical between the two and lives here once.
 *
 * Superadmin's message page reuses Admin\MessageController directly
 * (routes/web.php maps 'superadmin_message' to the same controller class),
 * so this base class is shared across three roles, not two.
 */
abstract class BaseMessageController extends Controller
{
    abstract public function index();

    abstract public function contacts(): JsonResponse;

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

    protected function notifyReceiver(string $senderEmail, string $receiverEmail, string $subject, int $messageId): void
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
