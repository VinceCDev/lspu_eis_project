<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\AuditLog;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Auth;
use App\Services\EmploymentReportImporter;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/** Ported from backend/Controllers/Admin/AlumniController.php. */
class AlumniController extends Controller
{
    public function index()
    {
        return view('admin.alumni', [
            'title' => 'Alumni | LSPU - EIS',
            'active' => Auth::role() === 'superadmin' ? 'superadmin_alumni' : 'admin_alumni',
            'pageCss' => 'admin_alumni.css',
            'pageJs' => 'admin_alumni.js',
        ]);
    }

    public function pending()
    {
        return view('admin.alumni_pending', [
            'title' => 'Pending Alumni | LSPU - EIS',
            'active' => Auth::role() === 'superadmin' ? 'superadmin_alumni_pending' : 'admin_alumni_pending',
            'pageCss' => 'admin_alumni_pending.css',
            'pageJs' => 'admin_alumni_pending.js',
        ]);
    }

    public function list(): JsonResponse
    {
        $alumniModel = new Alumni();
        $alumni = Auth::role() === 'superadmin'
            ? $alumniModel->allByStatus('Active')
            : $alumniModel->allByStatusForCampus('Active', Auth::campusId());

        return response()->json(['success' => true, 'alumni' => $this->withDetails($alumniModel, $alumni)]);
    }

    /**
     * Page-at-a-time variant of list() for large alumni tables — returns
     * {alumni, total} instead of the full unbounded array. Additive: list()
     * above is untouched so the current frontend keeps working unchanged
     * until it's updated to call this instead.
     */
    public function paginatedList(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));
        $search = trim((string) $request->query('search', ''));
        $offset = ($page - 1) * $perPage;
        $campusId = Auth::role() === 'superadmin' ? null : Auth::campusId();

        $alumniModel = new Alumni();
        $alumni = $alumniModel->allByStatusPaginated('Active', $campusId, $perPage, $offset, $search);

        return response()->json([
            'success' => true,
            'alumni' => $this->withDetails($alumniModel, $alumni),
            'total' => $alumniModel->countByStatus('Active', $campusId, $search),
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    public function pendingList(): JsonResponse
    {
        $alumniModel = new Alumni();
        $alumni = Auth::role() === 'superadmin'
            ? $alumniModel->allByStatus('Pending')
            : $alumniModel->allByStatusForCampus('Pending', Auth::campusId());

        return response()->json(['success' => true, 'alumni' => $this->withDetails($alumniModel, $alumni)]);
    }

    private function withDetails(Alumni $alumniModel, array $alumni): array
    {
        $ids = array_map(fn ($row) => (int) $row['alumni_id'], $alumni);
        $details = $alumniModel->detailsForAlumniIds($ids);

        foreach ($alumni as &$row) {
            $id = (int) $row['alumni_id'];
            $row['skills'] = $details['skills'][$id] ?? [];
            $row['education'] = $details['education'][$id] ?? [];
            $row['experience'] = $details['experience'][$id] ?? [];
            $row['resume'] = $details['resume'][$id] ?? null;
        }

        return $alumni;
    }

    public function education(Request $request): JsonResponse
    {
        [$ok, $alumniId, $error] = $this->readableAlumniId($request);
        if (!$ok) {
            return $error;
        }

        return response()->json(['success' => true, 'education' => (new Alumni())->educationByAlumniId($alumniId)]);
    }

    public function skills(Request $request): JsonResponse
    {
        [$ok, $alumniId, $error] = $this->readableAlumniId($request);
        if (!$ok) {
            return $error;
        }

        return response()->json(['success' => true, 'skills' => (new Alumni())->skillsByAlumniId($alumniId)]);
    }

    public function experience(Request $request): JsonResponse
    {
        [$ok, $alumniId, $error] = $this->readableAlumniId($request);
        if (!$ok) {
            return $error;
        }

        return response()->json(['success' => true, 'experience' => (new Alumni())->experienceByAlumniId($alumniId)]);
    }

    public function resume(Request $request): JsonResponse
    {
        [$ok, $alumniId, $error] = $this->readableAlumniId($request);
        if (!$ok) {
            return $error;
        }

        $resume = (new Alumni())->resumeByAlumniId($alumniId);
        if ($resume) {
            $resume['url'] = 'uploads/resumes/'.$resume['file_name'];
        }

        return response()->json(['success' => true, 'resume' => $resume]);
    }

    /**
     * Validates the ?alumni_id= query param, campus-scoping it for plain
     * admins. Returns [true, alumniId, null] or [false, null, JsonResponse].
     */
    private function readableAlumniId(Request $request): array
    {
        $alumniId = (int) $request->query('alumni_id', '0');
        if (!$alumniId) {
            return [false, null, response()->json(['success' => false, 'message' => 'Missing alumni_id'])];
        }

        $alumni = (new Alumni())->findByAlumniId($alumniId);
        if (!$alumni) {
            return [false, null, response()->json(['success' => false, 'message' => 'Alumni not found.'])];
        }

        if (!Auth::sameCampus(isset($alumni['campus_id']) ? (int) $alumni['campus_id'] : null)) {
            return [false, null, response()->json(['success' => false, 'message' => 'Forbidden: resource belongs to a different campus.'], 403)];
        }

        return [true, $alumniId, null];
    }

    public function approve(Request $request): JsonResponse
    {
        $alumniId = $request->input('alumni_id');

        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Missing alumni_id']);
        }

        $alumni = (new Alumni())->findByAlumniId((int) $alumniId);
        if (!$alumni) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        if (Auth::role() !== 'superadmin' && (int) ($alumni['campus_id'] ?? 0) !== Auth::campusId()) {
            return response()->json(['success' => false, 'message' => 'Forbidden: alumni belongs to a different campus.']);
        }

        $ok = (new Alumni())->approve((int) $alumni['user_id']);
        if ($ok) {
            (new AuditLog())->log((int) Auth::user()['user_id'], Auth::user()['email'] ?? null, Auth::role(), 'approve_alumni', 'alumni', (int) $alumni['user_id'], 'Approved alumni registration.');
            (new MailService())->send(
                $alumni['email'],
                trim($alumni['first_name'].' '.$alumni['last_name']),
                'Your LSPU EIS Account Has Been Approved',
                $this->approvalEmailBody($alumni['first_name'])
            );
        }

        return response()->json(['success' => $ok, 'message' => $ok ? 'Alumni approved.' : 'Approval failed.']);
    }

    private function approvalEmailBody(string $firstName): string
    {
        $safeName = htmlspecialchars($firstName);

        return MailService::wrap(
            "You're approved, {$safeName}!",
            '<p>Great news — your alumni account has been reviewed and approved.</p>'
                .'<p>You now have full access to job listings, employer connections, and all other alumni features.</p>',
            'Login to LSPU EIS',
            config('app.url').'/login'
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        $alumniId = $request->input('alumni_id');

        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Missing alumni_id']);
        }

        $alumniModel = new Alumni();
        $existing = $alumniModel->findByAlumniId((int) $alumniId);
        if (!$existing) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        if (Auth::role() !== 'superadmin' && (int) ($existing['campus_id'] ?? 0) !== Auth::campusId()) {
            return response()->json(['success' => false, 'message' => 'Forbidden: alumni belongs to a different campus.']);
        }

        $ok = $alumniModel->deleteByAlumniId((int) $alumniId);
        if ($ok) {
            (new AuditLog())->log((int) Auth::user()['user_id'], Auth::user()['email'] ?? null, Auth::role(), 'delete_alumni', 'alumni', (int) $alumniId, 'Deleted alumni account.');
        }

        return response()->json(['success' => $ok, 'message' => $ok ? 'Alumni deleted successfully.' : 'Delete failed.']);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->isMethod('PUT')) {
            return $this->update($request->all());
        }

        return $this->create($request->all());
    }

    /**
     * Bulk-import graduates from an LSPU "Data on Employment" (tracer) Excel
     * file. Non-superadmin admins can only import into their own campus.
     */
    public function importEmploymentReport(Request $request): JsonResponse
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return response()->json(['success' => false, 'message' => 'No file was uploaded.']);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            return response()->json(['success' => false, 'message' => 'Please upload an .xlsx, .xls or .csv file.']);
        }
        if ($file->getSize() > 15 * 1024 * 1024) {
            return response()->json(['success' => false, 'message' => 'File is too large (max 15 MB).']);
        }

        $campusId = Auth::role() === 'superadmin'
            ? ((int) $request->input('campus_id') ?: null)
            : Auth::campusId();
        if (!$campusId) {
            return response()->json(['success' => false, 'message' => 'Please choose a campus for this import.']);
        }

        $year = (int) $request->input('year') ?: null;
        if ($year !== null && ($year < 1960 || $year > (int) date('Y') + 1)) {
            return response()->json(['success' => false, 'message' => 'That graduation year looks wrong.']);
        }

        $tmp = $file->getRealPath() ?: storage_path('app/'.$file->store('tmp'));

        // Optional progress token: the frontend polls importProgress with it
        // for a live % while this (synchronous) import runs.
        $token = preg_replace('/[^A-Za-z0-9_]/', '', (string) $request->input('import_token', ''));
        $token = $token !== '' ? substr($token, 0, 64) : null;

        try {
            $summary = (new EmploymentReportImporter())->import($tmp, $campusId, $year, $token);

            (new AuditLog())->log(
                (int) Auth::user()['user_id'],
                Auth::user()['email'] ?? null,
                Auth::role(),
                'import_employment_report',
                'alumni',
                null,
                "Imported {$summary['imported']} alumni from an employment report ({$summary['skipped']} skipped)."
            );
        } catch (\Throwable $e) {
            report($e);
            if ($token !== null) {
                Cache::forget(EmploymentReportImporter::progressKey($token));
            }

            return response()->json([
                'success' => false,
                'message' => 'Import failed: '.$e->getMessage(),
            ]);
        }

        if ($token !== null) {
            Cache::forget(EmploymentReportImporter::progressKey($token));
        }

        return response()->json([
            'success' => true,
            'message' => "Imported {$summary['imported']} of {$summary['graduate_rows']} graduate rows.",
            'summary' => $summary,
        ]);
    }

    /** Live progress for an in-flight employment-report import (polled by the frontend). */
    public function importProgress(Request $request): JsonResponse
    {
        $token = preg_replace('/[^A-Za-z0-9_]/', '', (string) $request->query('token', ''));
        if ($token === '') {
            return response()->json(['phase' => 'unknown', 'done' => 0, 'total' => 0]);
        }

        return response()->json(
            Cache::get(EmploymentReportImporter::progressKey(substr($token, 0, 64)))
                ?: ['phase' => 'reading', 'done' => 0, 'total' => 0]
        );
    }

    private function create(array $data): JsonResponse
    {
        $required = ['email', 'first_name', 'last_name', 'gender', 'year_graduated', 'college', 'course', 'province', 'city', 'status'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return response()->json(['success' => false, 'message' => 'Missing required field: '.$field]);
            }
        }

        $userModel = new User();
        if ($userModel->emailExists($data['email'])) {
            return response()->json(['success' => false, 'message' => 'Email already registered.']);
        }

        $randomPassword = bin2hex(random_bytes(4));
        $userId = $userModel->create(
            $data['email'],
            $data['secondary_email'] ?? null,
            password_hash($randomPassword, PASSWORD_DEFAULT),
            'alumni',
            $data['status']
        );

        $campusId = Auth::role() === 'superadmin'
            ? (isset($data['campus_id']) ? (int) $data['campus_id'] : null)
            : Auth::campusId();

        (new Alumni())->register($userId, [
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'] ?? '',
            'last_name' => $data['last_name'],
            'birthdate' => empty($data['birthdate']) ? null : $data['birthdate'],
            'contact' => $data['contact'] ?? '',
            'gender' => $data['gender'],
            'civil_status' => $data['civil_status'] ?? '',
            'city' => $data['city'],
            'province' => $data['province'],
            'year_graduated' => $data['year_graduated'],
            'college' => $data['college'],
            'course' => $data['course'],
            'campus_id' => $campusId,
        ], '');

        $credentialsEmailSent = false;
        if ((new SiteSetting())->newAccountEmailEnabled()) {
            (new MailService())->send(
                $data['email'],
                $data['first_name'].' '.$data['last_name'],
                'Your LSPU EIS Alumni Account',
                MailService::wrap(
                    'Welcome, '.htmlspecialchars($data['first_name']).'!',
                    '<p>Your alumni account has been created by the administrator and is already active.</p>'
                        .'<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:16px 0;">'
                        ."<p style=\"margin:0 0 6px;\"><strong>Email:</strong> {$data['email']}</p>"
                        ."<p style=\"margin:0;\"><strong>Password:</strong> {$randomPassword}</p>"
                        .'</div>'
                        .'<p>For security, please change your password after logging in.</p>',
                    'Login to LSPU EIS',
                    config('app.url').'/login'
                )
            );
            $credentialsEmailSent = true;
        }

        return response()->json([
            'success' => true,
            'message' => $credentialsEmailSent
                ? 'Alumni added and credentials sent via email.'
                : "Alumni added. Credential email is turned off — temporary password: {$randomPassword}",
        ]);
    }

    private function update(array $data): JsonResponse
    {
        $alumniId = $data['alumni_id'] ?? null;
        if (!$alumniId) {
            return response()->json(['success' => false, 'message' => 'Missing alumni_id']);
        }

        $alumniModel = new Alumni();
        $existing = $alumniModel->findByAlumniId((int) $alumniId);
        if (!$existing) {
            return response()->json(['success' => false, 'message' => 'Alumni not found.']);
        }

        if (Auth::role() !== 'superadmin' && (int) ($existing['campus_id'] ?? 0) !== Auth::campusId()) {
            return response()->json(['success' => false, 'message' => 'Forbidden: alumni belongs to a different campus.']);
        }

        $userFieldNames = ['email', 'secondary_email', 'status'];
        $alumniFieldNames = ['first_name', 'middle_name', 'last_name', 'gender', 'year_graduated', 'college', 'course', 'province', 'city'];
        if (Auth::role() === 'superadmin') {
            $alumniFieldNames[] = 'campus_id';
        }

        $userFields = [];
        foreach ($userFieldNames as $field) {
            if (isset($data[$field])) {
                $userFields[$field] = $data[$field];
            }
        }

        $alumniFields = [];
        foreach ($alumniFieldNames as $field) {
            if (isset($data[$field])) {
                $alumniFields[$field] = $data[$field];
            }
        }

        $alumniModel->updateProfile((int) $alumniId, (int) $existing['user_id'], $userFields, $alumniFields);

        return response()->json(['success' => true, 'message' => 'Alumni updated successfully.']);
    }
}
