<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\SuccessStory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Superadmin/SuccessStoryController.php. */
class SuccessStoryController extends Controller
{
    private const VALID_STATUSES = ['draft', 'published', 'archived'];

    public function index()
    {
        return view('superadmin.success_stories', [
            'title' => 'Success Stories | LSPU - EIS',
            'active' => 'superadmin_success_stories',
            'pageJs' => 'superadmin_success_stories.js',
        ]);
    }

    public function list(): JsonResponse
    {
        return response()->json(['success' => true, 'stories' => (new SuccessStory())->allWithAuthor()]);
    }

    public function authors(): JsonResponse
    {
        return response()->json(['success' => true, 'alumni' => (new Alumni())->allByStatus('Active')]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = (int) $request->input('user_id', 0);
        $title = trim($request->input('title', ''));
        $content = trim($request->input('content', ''));
        $status = $this->normalizeStatus($request->input('status', 'draft'));

        if (!$userId || $title === '' || $content === '') {
            return response()->json(['success' => false, 'message' => 'Missing required fields.']);
        }

        (new SuccessStory())->create($userId, $title, $content, $status);

        return response()->json(['success' => true, 'message' => 'Success story created.']);
    }

    public function update(Request $request): JsonResponse
    {
        $storyId = (int) $request->input('id', 0);
        $userId = (int) $request->input('user_id', 0);
        $title = trim($request->input('title', ''));
        $content = trim($request->input('content', ''));
        $status = $this->normalizeStatus($request->input('status', 'draft'));

        if (!$storyId || !$userId || $title === '' || $content === '') {
            return response()->json(['success' => false, 'message' => 'Missing required fields.']);
        }

        $ok = (new SuccessStory())->update($storyId, $userId, $title, $content, $status);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Success story updated.' : 'Update failed.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $storyId = (int) $request->input('id', 0);

        if (!$storyId) {
            return response()->json(['success' => false, 'message' => 'Missing id']);
        }

        $ok = (new SuccessStory())->delete($storyId);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Success story deleted.' : 'Delete failed.']);
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower($status);

        return in_array($status, self::VALID_STATUSES, true) ? $status : 'draft';
    }
}
