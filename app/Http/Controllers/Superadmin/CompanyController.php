<?php

namespace App\Http\Controllers\Superadmin;

use App\Concerns\ConvertsUploadedFile;
use App\Core\Uploader;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employer;
use App\Models\User;
use App\Services\Auth;
use App\Services\MailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Superadmin/CompanyController.php. */
class CompanyController extends Controller
{
    use ConvertsUploadedFile;

    public function index()
    {
        return view('superadmin.company', [
            'title' => 'Companies | LSPU - EIS',
            'active' => 'superadmin_company',
            'pageJs' => 'superadmin_company.js',
        ]);
    }

    public function pending()
    {
        return view('superadmin.company_pending', [
            'title' => 'Pending Companies | LSPU - EIS',
            'active' => 'superadmin_company_pending',
            'pageCss' => 'admin_company_pending.css',
            'pageJs' => 'superadmin_company_pending.js',
        ]);
    }

    public function list(): JsonResponse
    {
        return response()->json((new Employer())->allByStatus('Active'));
    }

    /**
     * Page-at-a-time variant of list() for large company tables — returns
     * {companies, total} instead of the full unbounded array. Additive:
     * list() above is untouched so the current frontend keeps working
     * unchanged until it's updated to call this instead.
     */
    public function paginatedList(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));
        $search = trim((string) $request->query('search', ''));
        $offset = ($page - 1) * $perPage;

        $employer = new Employer();

        return response()->json([
            'success' => true,
            'companies' => $employer->allByStatusPaginated('Active', $perPage, $offset, $search),
            'total' => $employer->countByStatus('Active', $search),
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    public function pendingList(): JsonResponse
    {
        $companies = (new Employer())->allByStatus('Pending');
        foreach ($companies as &$company) {
            $company['company_logo'] = $company['company_logo'] ? '/uploads/logos/'.basename($company['company_logo']) : null;
            $company['document_file'] = $company['document_file'] ? '/uploads/documents/'.basename($company['document_file']) : null;
        }

        return response()->json($companies);
    }

    public function approve(Request $request): JsonResponse
    {
        $userId = $request->input('id');

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Missing id']);
        }

        $ok = (new Employer())->approve((int) $userId);
        if ($ok) {
            (new AuditLog())->log((int) Auth::user()['user_id'], Auth::user()['email'] ?? null, Auth::role(), 'approve_employer', 'employer', (int) $userId, 'Approved employer registration.');

            $email = (new User())->findEmailById((int) $userId);
            $employer = (new Employer())->findByUserId((int) $userId);
            if ($email && $employer) {
                (new MailService())->send(
                    $email,
                    $employer['company_name'],
                    'Your LSPU EIS Employer Account Has Been Approved',
                    $this->approvalEmailBody($employer['company_name'])
                );
            }
        }

        return response()->json(['success' => $ok, 'message' => $ok ? 'Company approved.' : 'Approval failed.']);
    }

    private function approvalEmailBody(string $companyName): string
    {
        $safeName = htmlspecialchars($companyName);

        return MailService::wrap(
            "You're approved, {$safeName}!",
            '<p>Great news — your employer account has been reviewed and approved.</p>'
                .'<p>You now have full access to post job openings and connect with LSPU alumni.</p>',
            'Login to LSPU EIS',
            config('app.url').'/login'
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        $userId = $request->input('id') ?? $request->input('company_id');

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Missing id']);
        }

        $ok = (new Employer())->delete((int) $userId);
        if ($ok) {
            (new AuditLog())->log((int) Auth::user()['user_id'], Auth::user()['email'] ?? null, Auth::role(), 'delete_employer', 'employer', (int) $userId, 'Deleted employer account.');
        }

        return response()->json(['success' => $ok, 'message' => $ok ? 'Company deleted successfully.' : 'Delete failed.']);
    }

    public function store(Request $request): JsonResponse
    {
        $fields = [
            'company_name', 'company_location', 'contact_email', 'contact_number',
            'industry_type', 'nature_of_business', 'tin', 'date_established',
            'company_type', 'accreditation_status', 'email',
        ];
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $request->input($field, '');
        }
        foreach ($fields as $field) {
            if ($data[$field] === '') {
                return response()->json(['success' => false, 'message' => 'Missing required field: '.$field], 400);
            }
        }

        $userModel = new User();
        $employerModel = new Employer();
        $isUpdate = $request->filled('id');

        if ($isUpdate) {
            $userId = (int) $request->input('id');
            $currentEmail = $userModel->findEmailById($userId);

            if ($currentEmail === null) {
                return response()->json(['success' => false, 'message' => 'Employer not found.']);
            }

            if ($data['email'] !== $currentEmail && $userModel->emailExists($data['email'])) {
                return response()->json(['success' => false, 'message' => 'Email already registered.']);
            }

            $employerModel->updateEmail($userId, $data['email']);

            $update = [
                'company_name' => $data['company_name'],
                'company_location' => $data['company_location'],
                'contact_email' => $data['contact_email'],
                'contact_number' => $data['contact_number'],
                'industry_type' => $data['industry_type'],
                'nature_of_business' => $data['nature_of_business'],
                'tin' => $data['tin'],
                'date_established' => $data['date_established'],
                'company_type' => $data['company_type'],
                'accreditation_status' => $data['accreditation_status'],
            ];

            if ($request->hasFile('company_logo')) {
                $logo = Uploader::storeNamed($this->fileToArray($request->file('company_logo')), 'logos', ['image/jpeg', 'image/png']);
                if ($logo) {
                    $update['company_logo'] = $logo;
                }
            }
            if ($request->hasFile('document_file')) {
                $doc = Uploader::storeNamed($this->fileToArray($request->file('document_file')), 'documents', ['image/jpeg', 'image/png', 'application/pdf']);
                if ($doc) {
                    $update['document_file'] = $doc;
                }
            }

            $employerModel->update($userId, $update);

            return response()->json(['success' => true, 'message' => 'Company updated successfully.']);
        }

        if ($userModel->emailExists($data['email'])) {
            return response()->json(['success' => false, 'message' => 'Email already registered.']);
        }

        $companyLogo = $request->hasFile('company_logo')
            ? (Uploader::storeNamed($this->fileToArray($request->file('company_logo')), 'logos', ['image/jpeg', 'image/png']) ?? '')
            : '';
        $documentFile = $request->hasFile('document_file')
            ? (Uploader::storeNamed($this->fileToArray($request->file('document_file')), 'documents', ['image/jpeg', 'image/png', 'application/pdf']) ?? '')
            : '';

        $randomPassword = bin2hex(random_bytes(4));
        $userId = $userModel->create($data['email'], null, password_hash($randomPassword, PASSWORD_DEFAULT), 'employer', 'Active');

        $employerModel->register($userId, [
            'company_name' => $data['company_name'],
            'company_logo' => $companyLogo,
            'company_location' => $data['company_location'],
            'contact_email' => $data['contact_email'],
            'contact_number' => $data['contact_number'],
            'industry_type' => $data['industry_type'],
            'nature_of_business' => $data['nature_of_business'],
            'tin' => $data['tin'],
            'date_established' => $data['date_established'],
            'company_type' => $data['company_type'],
            'accreditation_status' => $data['accreditation_status'],
            'document_file' => $documentFile,
        ]);

        (new MailService())->send(
            $data['email'],
            $data['company_name'],
            'Your Employer Account for LSPU EIS',
            MailService::wrap(
                'Welcome, '.htmlspecialchars($data['company_name']).'!',
                '<p>Your employer account has been created by the administrator.</p>'
                    .'<div style="background:#f1f5f9;border-radius:8px;padding:16px 18px;margin:16px 0;">'
                    ."<p style=\"margin:0 0 6px;\"><strong>Email:</strong> {$data['email']}</p>"
                    ."<p style=\"margin:0;\"><strong>Password:</strong> {$randomPassword}</p>"
                    .'</div>'
                    .'<p>For security, please change your password after logging in.</p>',
                'Login to LSPU EIS',
                config('app.url').'/login'
            )
        );

        return response()->json(['success' => true, 'message' => 'Employer added and credentials sent via email.']);
    }
}
