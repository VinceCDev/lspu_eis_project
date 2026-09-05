<?php

namespace App\Http\Controllers\Alumni;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Alumni;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Auth;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Alumni/MyProfileController.php. */
class MyProfileController extends Controller
{
    public function index()
    {
        return view('alumni.my_profile', [
            'title' => 'My Profile | LSPU - EIS',
            'active' => 'my_profile',
            'pageCss' => 'my_profile.css',
            'pageJs' => 'my_profile.js',
            'extraScripts' => '<script src="'.asset('assets/vendor/html2canvas/html2canvas.min.js').'"></script>'
                .'<script src="'.asset('assets/js/utils/fileHelpers.js').'"></script>'
                .'<script>window.USER_ID = '.json_encode((int) Auth::user()['user_id']).';</script>',
        ]);
    }

    public function generateSummary(): JsonResponse
    {
        $userId = (int) Auth::user()['user_id'];
        $alumni = new Alumni();

        $summary = (new GeminiService())->generateProfessionalSummary(
            $alumni->detailsByUserId($userId) ?: [],
            $alumni->educationByUserId($userId),
            $alumni->experienceByUserId($userId),
            $alumni->skillsByUserId($userId)
        );

        if (!$summary) {
            return response()->json(['success' => false, 'message' => 'Could not generate a summary right now. Please try again later.']);
        }

        return response()->json(['success' => true, 'summary' => $summary]);
    }

    /**
     * RA 10173 right-to-erasure. Requires the current password as
     * confirmation, since this is irreversible.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $password = (string) $request->input('password', '');

        if ($password === '') {
            return response()->json(['success' => false, 'message' => 'Please enter your password to confirm account deletion.']);
        }

        $userId = (int) Auth::user()['user_id'];
        $userModel = new User();
        $hash = $userModel->passwordHashById($userId);

        if (!$hash || !password_verify($password, $hash)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password.']);
        }

        $email = Auth::user()['email'] ?? null;
        $ok = (new Account())->delete($userId, 'alumni');
        if (!$ok) {
            return response()->json(['success' => false, 'message' => 'Failed to delete account. Please try again or contact support.']);
        }

        (new AuditLog())->log($userId, $email, 'alumni', 'delete_account', 'alumni', $userId, 'Self-service account deletion.');
        Auth::logout();

        return response()->json(['success' => true, 'message' => 'Your account has been permanently deleted.']);
    }
}
