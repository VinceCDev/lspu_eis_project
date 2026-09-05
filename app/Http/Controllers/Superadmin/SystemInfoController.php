<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SystemInfo;
use Illuminate\Http\JsonResponse;

/** Ported from backend/Controllers/Superadmin/SystemInfoController.php. */
class SystemInfoController extends Controller
{
    public function index()
    {
        return view('superadmin.system_info', [
            'title' => 'System Info | LSPU - EIS',
            'active' => 'superadmin_system_info',
            'pageJs' => 'superadmin_system_info.js',
        ]);
    }

    public function stats(): JsonResponse
    {
        $model = new SystemInfo();

        return response()->json([
            'success' => true,
            'user_counts' => $model->userCountsByRole(),
            'pending_counts' => $model->pendingCounts(),
            'recent_registrations' => $model->recentRegistrations(10),
            'database_size_bytes' => $model->databaseSizeBytes(),
            'uploads_size_bytes' => $model->uploadsSizeBytes(),
            'server_info' => $model->serverInfo(),
        ]);
    }
}
