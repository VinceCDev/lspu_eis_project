<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Superadmin/AuditLogController.php. */
class AuditLogController extends Controller
{
    private const PER_PAGE = 10;

    public function index()
    {
        return view('superadmin.audit_logs', [
            'title' => 'Audit Logs | LSPU - EIS',
            'active' => 'superadmin_audit_logs',
            'pageJs' => 'superadmin_audit_logs.js',
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page_num', '1'));
        $filters = [
            'action' => $request->query('action_filter', ''),
            'entity_type' => $request->query('entity_type', ''),
            'search' => $request->query('search', ''),
            'date_from' => $request->query('date_from', ''),
            'date_to' => $request->query('date_to', ''),
        ];

        $model = new AuditLog();
        $total = $model->count($filters);
        $logs = $model->all($filters, self::PER_PAGE, self::PER_PAGE * ($page - 1));

        return response()->json([
            'success' => true,
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => self::PER_PAGE,
            'total_pages' => (int) ceil($total / self::PER_PAGE),
        ]);
    }

    public function actions(): JsonResponse
    {
        return response()->json(['success' => true, 'actions' => (new AuditLog())->distinctActions()]);
    }
}
