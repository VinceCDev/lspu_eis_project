<?php

namespace App\Http\Controllers\Alumni;

use App\Http\Controllers\Controller;
use App\Models\SuccessStory;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Alumni/SuccessStoryController.php. */
class SuccessStoryController extends Controller
{
    public function list(): JsonResponse
    {
        return response()->json(['success' => true, 'stories' => (new SuccessStory())->allForUser((int) Auth::user()['user_id'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $title = $request->input('title', '');
        $content = $request->input('content', '');
        if (empty($title) || empty($content)) {
            return response()->json(['success' => false, 'message' => 'Title and content are required']);
        }

        $model = new SuccessStory();
        $id = $model->createForUser((int) Auth::user()['user_id'], $title, $content);

        return response()->json(['success' => true, 'story' => $model->findById($id)]);
    }

    public function update(Request $request): JsonResponse
    {
        $storyId = (int) $request->input('story_id', 0);
        $title = $request->input('title', '');
        $content = $request->input('content', '');
        if (!$storyId || empty($title) || empty($content)) {
            return response()->json(['success' => false, 'message' => 'All fields are required']);
        }

        $model = new SuccessStory();
        $ok = $model->updateForUser($storyId, (int) Auth::user()['user_id'], $title, $content);
        if (!$ok) {
            return response()->json(['success' => false, 'message' => 'Error updating story']);
        }

        return response()->json(['success' => true, 'story' => $model->findById($storyId)]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $storyId = (int) $request->input('story_id', 0);
        if (!$storyId) {
            return response()->json(['success' => false, 'message' => 'Story ID is required']);
        }

        $ok = (new SuccessStory())->deleteForUser($storyId, (int) Auth::user()['user_id']);

        return response()->json(['success' => $ok, 'message' => $ok ? null : 'Error deleting story']);
    }
}
