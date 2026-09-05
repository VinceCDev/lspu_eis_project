<?php

namespace App\Http\Controllers\Superadmin;

use App\Concerns\ConvertsUploadedFile;
use App\Core\Uploader;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from backend/Controllers/Superadmin/LandingEditorController.php. */
class LandingEditorController extends Controller
{
    use ConvertsUploadedFile;

    public function index()
    {
        return view('superadmin.landing_editor', [
            'title' => 'Landing Page | LSPU - EIS',
            'active' => 'superadmin_landing_editor',
            'pageJs' => 'superadmin_landing_editor.js',
            'settings' => (new SiteSetting())->all(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $badge = trim($request->input('landing_hero_badge', ''));
        $headline = trim($request->input('landing_hero_headline', ''));
        $subtext = trim($request->input('landing_hero_subtext', ''));

        if ($badge === '' || $headline === '' || $subtext === '') {
            return response()->json(['success' => false, 'message' => 'Badge, headline, and subtext are all required.']);
        }

        $settingsModel = new SiteSetting();
        $settings = [
            'landing_hero_badge' => $badge,
            'landing_hero_headline' => $headline,
            'landing_hero_subtext' => $subtext,
        ];

        if ($request->hasFile('hero_image')) {
            $filename = Uploader::store($this->fileToArray($request->file('hero_image')), 'landing');
            if (!$filename) {
                return response()->json(['success' => false, 'message' => 'Hero image upload failed.']);
            }
            $settings['landing_hero_image'] = $filename;
        }

        $settingsModel->save($settings);

        return response()->json(['success' => true, 'message' => 'Landing page updated.']);
    }
}
