<?php

namespace App\Http\Controllers\Employer;

use App\Concerns\ConvertsUploadedFile;
use App\Core\Uploader;
use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Employer/ProfileController.php. */
class ProfileController extends Controller
{
    use ConvertsUploadedFile;

    private const TEXT_FIELDS = [
        'company_name', 'company_location', 'contact_email', 'contact_number',
        'industry_type', 'nature_of_business', 'tin', 'date_established',
        'company_type', 'accreditation_status',
    ];

    public function index()
    {
        return view('employer.profile', [
            'title' => 'Company Profile | LSPU - EIS',
            'active' => 'employer_profile',
            'pageCss' => 'employer_profile.css',
            'pageJs' => 'employer_profile.js',
            'extraScripts' => '<script src="'.asset('assets/js/utils/fileHelpers.js').'"></script>',
        ]);
    }

    public function details(): JsonResponse
    {
        $employer = (new Employer())->findByUserId((int) Auth::user()['user_id']);
        if (!$employer) {
            return response()->json(['success' => true, 'profile' => self::emptyProfile()]);
        }

        // `company_logo` in the DB is inconsistent across rows: some store a bare filename,
        // others already include the 'uploads/logos/' path (legacy data). Strip any existing
        // prefix before rebuilding the URL so it isn't doubled up either way.
        $logoUrl = $employer['company_logo']
            ? '/uploads/logos/'.basename($employer['company_logo'])
            : '';
        $profile = [
            'employer_id' => $employer['employer_id'],
            'user_id' => $employer['user_id'],
            'name' => $employer['company_name'],
            'company_name' => $employer['company_name'],
            'company_logo' => $logoUrl,
            'profile_pic' => $logoUrl,
            'company_location' => $employer['company_location'],
            'contact_email' => $employer['contact_email'],
            'contact_number' => $employer['contact_number'],
            'industry_type' => $employer['industry_type'],
            'nature_of_business' => $employer['nature_of_business'],
            'tin' => $employer['tin'],
            'date_established' => $employer['date_established'],
            'company_type' => $employer['company_type'],
            'accreditation_status' => $employer['accreditation_status'],
            'document_file' => $employer['document_file'] ? '/uploads/documents/'.$employer['document_file'] : '',
        ];

        return response()->json(['success' => true, 'profile' => $profile]);
    }

    private static function emptyProfile(): array
    {
        return [
            'employer_id' => '', 'user_id' => '', 'name' => '', 'company_name' => '',
            'company_logo' => '', 'profile_pic' => '', 'company_location' => '', 'contact_email' => '',
            'contact_number' => '', 'industry_type' => '', 'nature_of_business' => '', 'tin' => '',
            'date_established' => '', 'company_type' => '', 'accreditation_status' => '', 'document_file' => '',
        ];
    }

    public function deleteLogo(): JsonResponse
    {
        $userId = (int) Auth::user()['user_id'];
        $employer = (new Employer())->findByUserId($userId);
        if ($employer && $employer['company_logo']) {
            $path = Uploader::basePath('logos/'.$employer['company_logo']);
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $ok = (new Employer())->update($userId, ['company_logo' => '']);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Logo deleted successfully.' : 'Failed to delete logo.']);
    }

    public function update(Request $request): JsonResponse
    {
        $fields = [];
        foreach (self::TEXT_FIELDS as $field) {
            if ($request->has($field)) {
                $fields[$field] = $request->input($field);
            }
        }

        $ok = (new Employer())->update((int) Auth::user()['user_id'], $fields);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Profile updated successfully' : 'Failed to update profile']);
    }

    public function updateLogo(Request $request): JsonResponse
    {
        if (!$request->hasFile('company_logo')) {
            return response()->json(['success' => false, 'message' => 'No logo file provided']);
        }

        $filename = Uploader::storeNamed($this->fileToArray($request->file('company_logo')), 'logos', ['image/jpeg', 'image/png', 'image/gif']);
        if (!$filename) {
            return response()->json(['success' => false, 'message' => 'Invalid logo file']);
        }

        $ok = (new Employer())->update((int) Auth::user()['user_id'], ['company_logo' => $filename]);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Logo updated successfully' : 'Failed to update logo']);
    }

    public function updateDocument(Request $request): JsonResponse
    {
        if (!$request->hasFile('document_file')) {
            return response()->json(['success' => false, 'message' => 'No document file provided']);
        }

        $filename = Uploader::storeNamed($this->fileToArray($request->file('document_file')), 'documents', ['image/jpeg', 'image/png', 'application/pdf']);
        if (!$filename) {
            return response()->json(['success' => false, 'message' => 'Invalid document file']);
        }

        $ok = (new Employer())->update((int) Auth::user()['user_id'], ['document_file' => $filename]);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Document updated successfully' : 'Failed to update document']);
    }

    public function deleteDocument(): JsonResponse
    {
        $userId = (int) Auth::user()['user_id'];
        $employer = (new Employer())->findByUserId($userId);
        if ($employer && $employer['document_file']) {
            $path = Uploader::basePath('documents/'.$employer['document_file']);
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $ok = (new Employer())->update($userId, ['document_file' => '']);

        return response()->json(['success' => $ok, 'message' => $ok ? 'Document deleted successfully' : 'Failed to delete document']);
    }
}
