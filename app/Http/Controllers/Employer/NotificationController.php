<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Employer/NotificationController.php. */
class NotificationController extends Controller
{
    public function index()
    {
        return view('employer.notification', [
            'title' => 'Notifications | LSPU - EIS',
            'active' => 'employer_notification',
            'pageJs' => 'employer_notification.js',
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
}
