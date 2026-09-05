<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Interview;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Employer/DashboardController.php. */
class DashboardController extends Controller
{
    public function index()
    {
        return view('employer.dashboard', [
            'title' => 'Employer Dashboard | LSPU - EIS',
            'active' => 'employer_dashboard',
            'pageCss' => 'employer_dashboard.css',
            'pageJs' => 'employer_dashboard.js',
            'extraScripts' => '<script src="'.asset('assets/vendor/chartjs/chart.min.js').'"></script>',
        ]);
    }

    public function interviewCount(): JsonResponse
    {
        return response()->json(['success' => true, 'total_interviews' => (new Interview())->countForEmployer((int) Auth::user()['user_id'])]);
    }

    public function statusCounts(): JsonResponse
    {
        return response()->json(['success' => true, 'status_counts' => (new Application())->statusCountsForEmployer((int) Auth::user()['user_id'])]);
    }

    public function hiredCount(): JsonResponse
    {
        return response()->json(['success' => true, 'hired_count' => (new Application())->hiredCountForEmployer((int) Auth::user()['user_id'])]);
    }

    public function interviewsByDate(Request $request): JsonResponse
    {
        $date = $request->query('date', '');
        if ($date === '') {
            return response()->json(['success' => false, 'message' => 'Date parameter required']);
        }

        $interviews = (new Interview())->forEmployerOnDate((int) Auth::user()['user_id'], $date);

        return response()->json(['success' => true, 'interviews' => $interviews]);
    }
}
