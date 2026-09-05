<?php

namespace App\Http\Controllers\Alumni;

use App\Concerns\ConvertsUploadedFile;
use App\Core\Uploader;
use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Alumni/ProfileController.php. */
class ProfileController extends Controller
{
    use ConvertsUploadedFile;

    private const IMAGE_MIME = ['image/jpeg', 'image/png', 'image/gif'];
    private const DOC_MIME = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

    public function details(): JsonResponse
    {
        return response()->json(['success' => true, 'profile' => (new Alumni())->detailsByUserId((int) Auth::user()['user_id'])]);
    }

    public function education(): JsonResponse
    {
        return response()->json(['success' => true, 'education' => (new Alumni())->educationByUserId((int) Auth::user()['user_id'])]);
    }

    public function skills(): JsonResponse
    {
        return response()->json(['success' => true, 'skills' => (new Alumni())->skillsByUserId((int) Auth::user()['user_id'])]);
    }

    public function experience(): JsonResponse
    {
        return response()->json(['success' => true, 'experience' => (new Alumni())->experienceByUserId((int) Auth::user()['user_id'])]);
    }

    public function resume(): JsonResponse
    {
        return response()->json(['success' => true, 'resume' => (new Alumni())->resumeByUserId((int) Auth::user()['user_id'])]);
    }

    public function certifications(): JsonResponse
    {
        return response()->json(['success' => true, 'certifications' => (new Alumni())->certificationsByUserId((int) Auth::user()['user_id'])]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $fields = ['first_name', 'middle_name', 'last_name', 'birthdate', 'contact', 'gender', 'civil_status',
            'city', 'province', 'year_graduated', 'college', 'course', 'campus_id', 'email', 'secondary_email'];
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = trim((string) $request->input($field, ''));
        }

        $alumni = new Alumni();
        $userId = (int) Auth::user()['user_id'];
        $ok = $alumni->updateProfileFields($userId, $data);

        if ($ok && !empty($data['email'])) {
            \Illuminate\Support\Facades\Session::put('email', $data['email']);
        }

        return response()->json(['success' => $ok, 'message' => $ok ? null : 'Update failed']);
    }

    public function addEducation(Request $request): JsonResponse
    {
        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        if (!$request->filled('degree') || !$request->filled('school')) {
            return response()->json(['success' => false, 'message' => 'Degree and school are required.']);
        }

        $id = (new Alumni())->addEducation($alumniId, $request->all());

        return response()->json(['success' => true, 'education_id' => $id]);
    }

    public function updateEducation(Request $request): JsonResponse
    {
        $educationId = (int) $request->input('id', 0);
        if (!$educationId || !$request->filled('degree') || !$request->filled('school')) {
            return response()->json(['success' => false, 'message' => 'Education ID, degree, and school are required.']);
        }

        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        $ok = (new Alumni())->updateEducation($educationId, $alumniId, $request->all());

        return response()->json(['success' => $ok]);
    }

    public function deleteEducation(Request $request): JsonResponse
    {
        $educationId = (int) $request->input('id', 0);
        if (!$educationId) {
            return response()->json(['success' => false, 'message' => 'Missing education id.']);
        }

        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        $ok = (new Alumni())->deleteEducation($educationId, $alumniId);

        return response()->json(['success' => $ok]);
    }

    public function addSkill(Request $request): JsonResponse
    {
        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        $name = $request->input('name', '');
        if (!$name) {
            return response()->json(['success' => false, 'message' => 'Skill name is required.']);
        }

        $certificateFile = null;
        if ($request->hasFile('certificate_file') && $request->file('certificate_file')->isValid()) {
            $certificateFile = Uploader::storeNamed($this->fileToArray($request->file('certificate_file')), 'certificates', self::DOC_MIME);
            if (!$certificateFile) {
                return response()->json(['success' => false, 'message' => 'Invalid file type or size.']);
            }
        }

        $id = (new Alumni())->addSkill($alumniId, $name, $request->input('certificate', ''), $certificateFile);

        return response()->json(['success' => true, 'skill_id' => $id, 'certificate_file' => $certificateFile]);
    }

    public function deleteSkill(Request $request): JsonResponse
    {
        $skillId = (int) $request->input('id', 0);
        if (!$skillId) {
            return response()->json(['success' => false, 'message' => 'Skill ID required.']);
        }

        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        $ok = (new Alumni())->deleteSkill($skillId, $alumniId);

        return response()->json(['success' => $ok]);
    }

    public function addCertification(Request $request): JsonResponse
    {
        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        $name = trim((string) $request->input('name', ''));
        if (!$name) {
            return response()->json(['success' => false, 'message' => 'Certification name is required.']);
        }

        $certificateFile = null;
        if ($request->hasFile('certificate_file') && $request->file('certificate_file')->isValid()) {
            $certificateFile = Uploader::storeNamed($this->fileToArray($request->file('certificate_file')), 'certificates', self::DOC_MIME);
            if (!$certificateFile) {
                return response()->json(['success' => false, 'message' => 'Invalid file type or size.']);
            }
        }

        $id = (new Alumni())->addCertification($alumniId, $name, $request->input('issuer', ''), $request->input('issue_date'), $certificateFile);

        return response()->json(['success' => true, 'certification_id' => $id, 'certificate_file' => $certificateFile]);
    }

    public function updateCertification(Request $request): JsonResponse
    {
        $certificationId = (int) $request->input('id', 0);
        $name = trim((string) $request->input('name', ''));
        if (!$certificationId || !$name) {
            return response()->json(['success' => false, 'message' => 'Certification ID and name are required.']);
        }

        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        $alumni = new Alumni();
        $ok = $alumni->updateCertification($certificationId, $alumniId, $name, $request->input('issuer', ''), $request->input('issue_date'));

        $certificateFile = null;
        if ($request->hasFile('certificate_file') && $request->file('certificate_file')->isValid()) {
            $certificateFile = Uploader::storeNamed($this->fileToArray($request->file('certificate_file')), 'certificates', self::DOC_MIME);
            if ($certificateFile) {
                $alumni->updateCertificationFile($certificationId, $alumniId, $certificateFile);
            }
        }

        return response()->json(['success' => $ok, 'certificate_file' => $certificateFile]);
    }

    public function deleteCertification(Request $request): JsonResponse
    {
        $certificationId = (int) $request->input('id', 0);
        if (!$certificationId) {
            return response()->json(['success' => false, 'message' => 'Certification ID required.']);
        }

        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        $ok = (new Alumni())->deleteCertification($certificationId, $alumniId);

        return response()->json(['success' => $ok]);
    }

    public function addExperience(Request $request): JsonResponse
    {
        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        if (!$request->filled('title') || !$request->filled('company') || !$request->filled('start_date')) {
            return response()->json(['success' => false, 'message' => 'Missing required fields.']);
        }

        $id = (new Alumni())->addExperience($alumniId, $request->all());

        return response()->json(['success' => true, 'experience_id' => $id]);
    }

    public function updateExperience(Request $request): JsonResponse
    {
        $experienceId = (int) $request->input('id', 0);
        if (!$experienceId) {
            return response()->json(['success' => false, 'message' => 'Experience ID is required.']);
        }

        $required = ['title', 'company', 'start_date', 'location_of_work', 'employment_status', 'employment_sector'];
        foreach ($required as $field) {
            if (!$request->filled($field)) {
                return response()->json(['success' => false, 'message' => 'Missing required fields.']);
            }
        }

        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        $ok = (new Alumni())->updateExperience($experienceId, $alumniId, $request->all());

        return response()->json(['success' => $ok, 'message' => $ok ? 'Experience updated successfully!' : 'No changes made or experience not found.']);
    }

    public function deleteExperience(Request $request): JsonResponse
    {
        $experienceId = (int) $request->input('id', 0);
        if (!$experienceId) {
            return response()->json(['success' => false, 'message' => 'Missing experience id.']);
        }

        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        $ok = (new Alumni())->deleteExperience($experienceId, $alumniId);

        return response()->json(['success' => $ok]);
    }

    public function insertResume(Request $request): JsonResponse
    {
        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        if (!$request->hasFile('resume') || !$request->file('resume')->isValid()) {
            return response()->json(['success' => false, 'message' => 'No file uploaded or upload error.']);
        }

        $filename = Uploader::storeNamed($this->fileToArray($request->file('resume')), 'resumes', ['application/pdf']);
        if (!$filename) {
            return response()->json(['success' => false, 'message' => 'Only PDF files allowed.']);
        }

        $alumni = new Alumni();
        $id = $alumni->addResume($alumniId, $filename);

        return response()->json(['success' => true, 'resume_id' => $id, 'file_name' => $filename]);
    }

    public function changeResume(Request $request): JsonResponse
    {
        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        $alumni = new Alumni();
        $existing = $alumni->latestResumeRow($alumniId);

        if (!$request->hasFile('resume') || !$request->file('resume')->isValid()) {
            return response()->json(['success' => false, 'message' => 'No file uploaded or upload error.']);
        }

        $filename = Uploader::storeNamed($this->fileToArray($request->file('resume')), 'resumes', ['application/pdf']);
        if (!$filename) {
            return response()->json(['success' => false, 'message' => 'Only PDF files allowed.']);
        }

        if ($existing) {
            $oldPath = Uploader::basePath('resumes/'.$existing['file_name']);
            if ($existing['file_name'] && file_exists($oldPath)) {
                unlink($oldPath);
            }
            $ok = $alumni->replaceResume((int) $existing['resume_id'], $filename);

            return response()->json(['success' => $ok, 'resume_id' => $existing['resume_id'], 'file_name' => $filename]);
        }

        $id = $alumni->addResume($alumniId, $filename);

        return response()->json(['success' => true, 'resume_id' => $id, 'file_name' => $filename]);
    }

    public function deleteResume(): JsonResponse
    {
        $alumniId = (new Alumni())->alumniIdByUserId((int) Auth::user()['user_id']);
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        $alumni = new Alumni();
        $existing = $alumni->latestResumeRow($alumniId);
        if (!$existing) {
            return response()->json(['success' => false, 'message' => 'No resume found.']);
        }

        $path = Uploader::basePath('resumes/'.$existing['file_name']);
        if ($existing['file_name'] && file_exists($path)) {
            unlink($path);
        }

        $ok = $alumni->deleteResume((int) $existing['resume_id']);

        return response()->json(['success' => $ok]);
    }

    public function fetchProfilePic(): JsonResponse
    {
        $pic = (new Alumni())->profilePicByUserId((int) Auth::user()['user_id']);

        return response()->json($pic ? ['success' => true, 'file_name' => $pic] : ['success' => false, 'file_name' => null]);
    }

    public function insertProfilePic(Request $request): JsonResponse
    {
        return $this->saveProfilePic($request, false);
    }

    public function updateProfilePic(Request $request): JsonResponse
    {
        return $this->saveProfilePic($request, true);
    }

    private function saveProfilePic(Request $request, bool $allowReplace): JsonResponse
    {
        $alumni = new Alumni();
        $userId = (int) Auth::user()['user_id'];
        $existing = $alumni->profilePicByUserId($userId);

        if (!$allowReplace && $existing) {
            return response()->json(['success' => false, 'message' => 'Profile picture already exists. Please use update to change it.']);
        }

        if (!$request->hasFile('profile_pic') || !$request->file('profile_pic')->isValid()) {
            return response()->json(['success' => false, 'message' => 'No file uploaded or upload error.']);
        }

        $filename = Uploader::storeNamed($this->fileToArray($request->file('profile_pic')), 'profile_picture', self::IMAGE_MIME);
        if (!$filename) {
            return response()->json(['success' => false, 'message' => 'Only image files allowed.']);
        }

        if ($existing) {
            $oldPath = Uploader::basePath('profile_picture/'.$existing);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $ok = $alumni->updateProfilePicByUserId($userId, $filename);

        return response()->json(['success' => $ok, 'file_name' => $filename]);
    }

    public function deleteProfilePic(): JsonResponse
    {
        $alumni = new Alumni();
        $userId = (int) Auth::user()['user_id'];
        $existing = $alumni->profilePicByUserId($userId);

        if ($existing) {
            $path = Uploader::basePath('profile_picture/'.$existing);
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $ok = $alumni->updateProfilePicByUserId($userId, null);

        return response()->json(['success' => $ok]);
    }

    public function updateVerificationDocument(Request $request): JsonResponse
    {
        $alumni = new Alumni();
        $userId = (int) Auth::user()['user_id'];
        $existing = $alumni->verificationDocumentByUserId($userId);

        if (!$request->hasFile('verification_document') || !$request->file('verification_document')->isValid()) {
            return response()->json(['success' => false, 'message' => 'No file uploaded or upload error.']);
        }

        $filename = Uploader::storeNamed($this->fileToArray($request->file('verification_document')), 'documents', self::DOC_MIME);
        if (!$filename) {
            return response()->json(['success' => false, 'message' => 'Only PDF or image files allowed.']);
        }

        if ($existing) {
            $oldPath = Uploader::basePath('documents/'.$existing);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $ok = $alumni->updateVerificationDocumentByUserId($userId, $filename);

        return response()->json(['success' => $ok, 'file_name' => $filename]);
    }

    public function deleteVerificationDocument(): JsonResponse
    {
        $alumni = new Alumni();
        $userId = (int) Auth::user()['user_id'];
        $existing = $alumni->verificationDocumentByUserId($userId);

        if (!$existing) {
            return response()->json(['success' => false, 'message' => 'No document found to delete']);
        }

        $path = Uploader::basePath('documents/'.$existing);
        if (file_exists($path)) {
            unlink($path);
        }

        $ok = $alumni->updateVerificationDocumentByUserId($userId, '');

        return response()->json(['success' => $ok, 'message' => $ok ? 'Document deleted successfully' : 'Failed to update database']);
    }
}
