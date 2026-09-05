<?php

namespace App\Http\Controllers\Alumni;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/** Ported from backend/Controllers/Alumni/NotificationController.php. */
class NotificationController extends Controller
{
    public function index()
    {
        return view('alumni.notification', [
            'title' => 'Notifications | LSPU - EIS',
            'active' => 'notification',
            'pageCss' => 'notification.css',
            'pageJs' => 'notif.js',
        ]);
    }

    public function list(): JsonResponse
    {
        return response()->json(['success' => true, 'notifications' => (new Notification())->allForUser((int) Auth::user()['user_id'])]);
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json(['success' => true, 'unread_count' => (new Notification())->unreadCountForUser((int) Auth::user()['user_id'])]);
    }

    public function update(Request $request): JsonResponse
    {
        $action = $request->input('action', '');
        $model = new Notification();
        $userId = (int) Auth::user()['user_id'];

        if ($action === 'mark_all_read') {
            return response()->json(['success' => $model->markAllRead($userId)]);
        }

        if ($action === 'mark_one_read') {
            $id = (int) $request->input('id', 0);
            if (!$id) {
                return response()->json(['success' => false, 'message' => 'Missing notification id.']);
            }

            return response()->json(['success' => $model->markOneRead($id, $userId)]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid request.']);
    }

    public function markTutorialCompleted(): JsonResponse
    {
        $ok = (new User())->markTutorialCompleted((int) Auth::user()['user_id']);
        if ($ok) {
            Session::put('tutorial_completed', true);
        }

        return response()->json(['success' => $ok]);
    }
}
