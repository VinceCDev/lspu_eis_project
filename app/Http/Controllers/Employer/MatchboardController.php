<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\JobMatch;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;

/** Ported from backend/Controllers/Employer/MatchboardController.php. */
class MatchboardController extends Controller
{
    public function index()
    {
        return view('employer.matchboard', [
            'title' => 'Matchboard | LSPU - EIS',
            'active' => 'employer_matchboard',
            'pageCss' => 'employer_job.css',
            'pageJs' => 'employer_leaderboard.js',
        ]);
    }

    public function list(): JsonResponse
    {
        $matches = (new JobMatch())->allForEmployer((int) Auth::user()['user_id']);

        return response()->json(['success' => true, 'data' => $matches, 'count' => count($matches)]);
    }
}
