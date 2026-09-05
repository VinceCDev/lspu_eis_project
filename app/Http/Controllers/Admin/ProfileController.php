<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\ConvertsUploadedFile;
use App\Core\Uploader;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Admin/ProfileController.php. */
class ProfileController extends Controller
{
    use ConvertsUploadedFile;

    public function index()
    {
        return view('admin.profile', [
            'title' => 'My Profile | LSPU - EIS',
            'active' => Auth::role() === 'superadmin' ? 'superadmin_profile' : 'admin_profile',
            'pageCss' => 'admin_profile.css',
            'pageJs' => 'admin_profile.js',
        ]);
    }

    public function details(): JsonResponse
    {
        $profile = (new Account())->adminDetailsByUserId((int) Auth::user()['user_id'], Auth::user()['email']);
        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Admin profile not found']);
        }

        $isSuperadmin = Auth::role() === 'superadmin';
        if ($isSuperadmin) {
            $profile['position'] = 'System Administrator';
            $profile['campus_name'] = 'Laguna State Polytechnic University';
        } else {
            $profile['position'] = 'Campus Administrator';
            $profile['campus_name'] = $profile['campus_name'] ?: 'Not assigned';
        }
        $profile['is_superadmin'] = $isSuperadmin;

        return response()->json(['success' => true, 'profile' => $profile]);
    }

    public function update(Request $request): JsonResponse
    {
        $userId = (int) Auth::user()['user_id'];
        $fields = [];
        foreach (['first_name', 'middle_name', 'last_name', 'contact', 'address'] as $field) {
            if ($request->has($field)) {
                $fields[$field] = $request->input($field);
            }
        }
        if ($request->filled('email')) {
            $fields['email'] = $request->input('email');
        }

        (new Account())->updateOwnAdminProfile($userId, $fields);

        return response()->json(['success' => true, 'message' => 'Profile updated successfully.']);
    }

    public function updatePhoto(Request $request): JsonResponse
    {
        $userId = (int) Auth::user()['user_id'];
        $filename = Uploader::store($this->fileToArray($request->file('profile_pic')), 'profile_picture');

        if (!$filename) {
            return response()->json(['success' => false, 'message' => 'Invalid image upload.']);
        }

        $path = 'uploads/profile_picture/'.$filename;
        (new Account())->updateAdminProfilePic($userId, $path);

        return response()->json(['success' => true, 'message' => 'Profile photo updated.', 'profile_pic' => $path]);
    }
}
