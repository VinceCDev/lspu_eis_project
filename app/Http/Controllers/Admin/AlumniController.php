<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessEmploymentImport;
use App\Models\Alumni;
use App\Models\AuditLog;
use App\Models\ImportJob;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Auth;
use App\Services\MailService;
use App\Services\ReportingSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

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

    /** Largest alumni set the unpaginated list()/pendingList() endpoints will build (see guardUnboundedList()). */
    private const MAX_UNPAGINATED = 25000;

    /**
     * list() ships every alumnus of the campus (plus skills/education/experience/resume) as ONE JSON document, built in PHP
     * memory. Measured: 28 MB at 30k rows, HTTP 500 (memory) after 28 s at 150k and 122 s at 300k, and the query it runs
     * competes with every other user. Refuse early with an actionable message instead; paginatedList() is the scalable path.
     */
    private function guardUnboundedList(string $status): ?JsonResponse
    {
        $campusId = Auth::role() === 'superadmin' ? null : Auth::campusId();
        $total = (new Alumni())->countByStatus($status, $campusId);
        if ($total > self::MAX_UNPAGINATED) {
            return response()->json([
                'success' => false,
                'message' => 'There are '.number_format($total).' alumni records - too many to load at once. Use search/filters (paged list).',
                'total' => $total,
            ], 413);
        }

        return null;
    }

    public function list(): JsonResponse
    {
        if ($blocked = $this->guardUnboundedList('Active')) {
            return $blocked;
        }

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
        if (Session::isStarted()) {
            Session::save();   // read-only endpoint: don't hold the session lock while querying
        }
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min(100, max(1, (int) $request->query('per_page', '25')));
        $search = trim((string) $request->query('search', ''));
        $offset = ($page - 1) * $perPage;
        $campusId = Auth::role() === 'superadmin' ? null : Auth::campusId();
        $status = in_array($request->query('status'), ['Active', 'Inactive', 'Pending'], true) ? (string) $request->query('status') : 'Active';
        $filters = [
            'campus_id' => Auth::role() === 'superadmin' ? (int) $request->query('campus_id', '0') : 0,
            'college' => trim((string) $request->query('college', '')),
            'course' => trim((string) $request->query('course', '')),
            'year' => (int) $request->query('year', '0'),
        ];

        // OFFSET n reads and discards n rows: past this the honest answer is "narrow it down" (search / filters).
        if ($offset > self::MAX_OFFSET) {
            return response()->json([
                'success' => false,
                'message' => 'That page is too deep to load directly. Use search or the campus / college / course filters to narrow the list.',
            ], 422);
        }

        $alumniModel = new Alumni();
        $alumni = $alumniModel->allByStatusPaginated($status, $campusId, $perPage, $offset, $search, $filters);

        // The total is only a display number. Unfiltered 'Active' totals come from the summary build (an exact COUNT over a
        // 1M-row join measured 5 s); everything else is counted live but capped ("10,000+"), cached for a minute when there
        // is no search term.
        $narrowed = $search !== '' || $filters['college'] !== '' || $filters['course'] !== '' || $filters['year'] > 0;
        $scopeCampus = $campusId ?? ($filters['campus_id'] > 0 ? $filters['campus_id'] : null);
        $total = $status === 'Active' && !$narrowed ? ReportingSummary::activeUsers($scopeCampus) : null;
        $capped = false;
        if ($total === null) {
            $count = fn () => $alumniModel->countByStatus($status, $campusId, $search, $filters, self::COUNT_CAP);
            $total = $search === ''
                ? (int) Cache::remember('alumni_count:'.md5(json_encode([$status, $campusId, $filters])), 60, $count)
                : $count();
            $capped = $total > self::COUNT_CAP;   // live counts stop at the cap; the summary total above is exact
        }

        return response()->json([
            'success' => true,
            'alumni' => $this->withDetails($alumniModel, $alumni),
            'total' => $total,
            'total_capped' => $capped,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /** Live totals stop counting here and are shown as "1,000+" (an exact count of a broad search is thousands of random row lookups: 10 s at 1M, ~70 s cold at 5M with a small buffer pool). */
    private const COUNT_CAP = 1000;

    /** Deepest OFFSET the paged list will serve (see paginatedList()). */
    private const MAX_OFFSET = 100000;

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
     * Bulk-import graduates from an LSPU "Data on Employment" (tracer) Excel file. Non-superadmin admins can only import
     * into their own campus.
     *
     * The request only STORES the file and QUEUES the import, then returns immediately (HTTP 202-style JSON with the import
     * id). A queue worker (App\Jobs\ProcessEmploymentImport) does the parsing/inserting in chunks, with progress, retries
     * and a limit on how many imports run at once; the browser polls importStatus() and does not have to stay connected.
     * (Previously this held one PHP worker - and the browser connection - open for the whole import: 100k rows did not fit
     * in memory or in the 600 s limit, and 6 concurrent uploads starved every other page.)
     */
    public function importEmploymentReport(Request $request)
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return response()->json(['success' => false, 'message' => 'No file was uploaded.']);
        }

        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            return response()->json(['success' => false, 'message' => 'Please upload an .xlsx, .xls or .csv file.']);
        }
        $maxMb = (int) config('import.max_upload_mb', 40);
        if ($file->getSize() > $maxMb * 1024 * 1024) {
            return response()->json(['success' => false, 'message' => "File is too large (max {$maxMb} MB). Split it by year or program."]);
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

        $limit = (int) config('import.max_pending_per_campus', 5);
        if (ImportJob::pendingCount($campusId) >= $limit) {
            return response()->json([
                'success' => false,
                'message' => "This campus already has {$limit} imports waiting or running. Wait for one to finish (or cancel it) before uploading another.",
            ], 429);
        }

        $stored = $file->storeAs((string) config('import.directory', 'imports'), (string) Str::uuid().'.'.$ext, 'local');
        if (!$stored) {
            return response()->json(['success' => false, 'message' => 'The file could not be saved on the server. Please try again.'], 500);
        }

        $importId = ImportJob::create([
            'campus_id' => $campusId,
            'uploaded_by' => (int) Auth::user()['user_id'],
            'filename' => mb_substr($file->getClientOriginalName(), 0, 255),
            'file_path' => $stored,
            'file_ext' => $ext,
            'file_size' => (int) $file->getSize(),
            'year_graduated' => $year,
        ]);

        // The session lock is not needed any more; release it so this admin's other requests are not held.
        if (Session::isStarted()) {
            Session::save();
        }

        try {
            ProcessEmploymentImport::dispatch($importId);   // QUEUE_CONNECTION=sync (dev): runs right here, as before
        } catch (\Throwable $e) {
            report($e);
            ImportJob::fail($importId, 'Could not queue the import: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'The import could not be queued. Please try again.'], 500);
        }

        $row = ImportJob::find($importId);
        $import = ImportJob::present($row);

        return response()->json([
            'success' => true,
            'queued' => $row->status === ImportJob::QUEUED,
            'import_id' => $importId,
            'import' => $import,
            'message' => $row->status === ImportJob::COMPLETED
                ? "Imported {$import['successful_rows']} of {$import['processed_rows']} rows."
                : 'Upload received - your import is queued and will keep running even if you close this page.',
        ], 202);
    }

    /** May the current admin see/cancel this import? (superadmin: any; admin: their own campus) */
    private function importFor(int $id): ?object
    {
        $row = $id > 0 ? ImportJob::find($id) : null;
        if (!$row) {
            return null;
        }
        if (Auth::role() !== 'superadmin' && (int) $row->campus_id !== (int) Auth::campusId()) {
            return null;
        }

        return $row;
    }

    /**
     * Progress of one import: a single primary-key read of `import_jobs` (the worker writes it once per chunk), so polling
     * costs the database next to nothing regardless of file size. Also releases the session lock first.
     */
    public function importStatus(Request $request): JsonResponse
    {
        if (Session::isStarted()) {
            Session::save();
        }
        $row = $this->importFor((int) $request->query('id', '0'));
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Import not found.'], 404);
        }

        return response()->json(['success' => true, 'import' => ImportJob::present($row)]);
    }

    /** The 10 most recent imports (this campus, or every campus for a superadmin) - survives closing the browser. */
    public function importList(): JsonResponse
    {
        if (Session::isStarted()) {
            Session::save();
        }
        $q = DB::table('import_jobs')->orderByDesc('id')->limit(10);
        if (Auth::role() !== 'superadmin') {
            $q->where('campus_id', Auth::campusId());
        }

        return response()->json([
            'success' => true,
            'imports' => $q->get()->map(fn ($r) => ImportJob::present($r))->all(),
        ]);
    }

    public function importCancel(Request $request): JsonResponse
    {
        $row = $this->importFor((int) $request->input('id', $request->query('id', '0')));
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Import not found.'], 404);
        }
        $ok = ImportJob::cancel((int) $row->id);
        if ($ok) {
            (new AuditLog())->log((int) Auth::user()['user_id'], Auth::user()['email'] ?? null, Auth::role(), 'cancel_import', 'alumni', null, "Cancelled import #{$row->id} ({$row->filename}).");
        }

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Import cancelled. Rows already imported were kept.' : 'This import has already finished.',
        ]);
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
