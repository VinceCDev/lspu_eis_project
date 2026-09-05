<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Services\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Admin/ApplicantController.php. */
class ApplicantController extends Controller
{
    public function index()
    {
        return view('admin.applicant', [
            'title' => 'Applicants | LSPU - EIS',
            'active' => Auth::role() === 'superadmin' ? 'superadmin_applicant' : 'admin_applicant',
            'pageCss' => 'admin_applicant.css',
            'pageJs' => 'admin_applicant.js',
        ]);
    }

    public function list(): JsonResponse
    {
        $applications = Auth::role() === 'superadmin'
            ? (new Applicant())->allWithAlumniAndJob()
            : (new Applicant())->allWithAlumniAndJobForCampus(Auth::campusId());

        return response()->json(['applications' => $applications]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $applicationId = $request->input('application_id') ?? $request->input('id');

        if (!$applicationId) {
            return response()->json(['success' => false, 'message' => 'Missing application_id']);
        }

        $applicantModel = new Applicant();
        if (Auth::role() !== 'superadmin' && $applicantModel->campusIdForApplication((int) $applicationId) !== Auth::campusId()) {
            return response()->json(['success' => false, 'message' => 'Forbidden: application belongs to a different campus.']);
        }

        $ok = $applicantModel->delete((int) $applicationId);

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Applicant deleted successfully.' : 'Delete failed.',
        ]);
    }
}
