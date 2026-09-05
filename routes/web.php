<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Ported from backend/routes.php in the original app. The original's
 * Router::dispatch() let a single page slug reach ANY public method on its
 * mapped controller via ?action=methodName (defaulting to the page's
 * registered default action when ?action= is absent or 'index') — many
 * controller actions (list/store/update/destroy/...) were never given
 * their own named route, only reached this way. Recreating that exact
 * mechanism here (see legacyDispatch() below) preserves every one of
 * those action endpoints without re-deriving a RESTful route per method,
 * which the original migration plan flagged as unacceptable risk for a
 * same-pass port.
 *
 * @var array<string, array{0: class-string, 1: string}> page slug => [controller class, default action]
 */
$pages = [
    // Shared
    'ai_suggestions' => [\App\Http\Controllers\Shared\AiSuggestionController::class, 'jobSuggestions'],
    'geocode_proxy' => [\App\Http\Controllers\Shared\GeocodeController::class, 'autocomplete'],
    'api_reminder' => [\App\Http\Controllers\Shared\ReminderCronController::class, 'run'],
    'login' => [\App\Http\Controllers\Shared\AuthController::class, 'loginPage'],
    'logout' => [\App\Http\Controllers\Shared\AuthController::class, 'logout'],
    'forgot_password' => [\App\Http\Controllers\Shared\AuthController::class, 'forgotPasswordPage'],
    'reset_password' => [\App\Http\Controllers\Shared\AuthController::class, 'resetPasswordPage'],
    'employer_forgot_password' => [\App\Http\Controllers\Shared\AuthController::class, 'forgotPasswordPage'],
    'employer_terms' => [\App\Http\Controllers\Shared\PageController::class, 'employerTerms'],
    'alumni_terms' => [\App\Http\Controllers\Shared\PageController::class, 'alumniTerms'],
    'user_type' => [\App\Http\Controllers\Shared\PageController::class, 'userType'],
    'landing' => [\App\Http\Controllers\Shared\PageController::class, 'landing'],

    // Alumni
    'signup' => [\App\Http\Controllers\Alumni\AuthController::class, 'signupPage'],
    'notification' => [\App\Http\Controllers\Alumni\NotificationController::class, 'index'],
    'alumni_profile_data' => [\App\Http\Controllers\Alumni\ProfileController::class, 'details'],
    'home' => [\App\Http\Controllers\Alumni\HomeController::class, 'index'],
    'my_application' => [\App\Http\Controllers\Alumni\MyApplicationController::class, 'index'],
    'alumni_success_stories' => [\App\Http\Controllers\Alumni\SuccessStoryController::class, 'list'],
    'my_profile' => [\App\Http\Controllers\Alumni\MyProfileController::class, 'index'],
    'message' => [\App\Http\Controllers\Alumni\MessageController::class, 'index'],

    // Employer
    'employer_signup' => [\App\Http\Controllers\Employer\AuthController::class, 'signupPage'],
    'employer_login' => [\App\Http\Controllers\Employer\AuthController::class, 'loginPage'],
    'employer_dashboard' => [\App\Http\Controllers\Employer\DashboardController::class, 'index'],
    'employer_applicants' => [\App\Http\Controllers\Employer\ApplicantController::class, 'index'],
    'employer_interview' => [\App\Http\Controllers\Employer\InterviewController::class, 'index'],
    'employer_jobposting' => [\App\Http\Controllers\Employer\JobPostingController::class, 'index'],
    'employer_matchboard' => [\App\Http\Controllers\Employer\MatchboardController::class, 'index'],
    'employer_onboarding' => [\App\Http\Controllers\Employer\OnboardingController::class, 'index'],
    'employer_profile' => [\App\Http\Controllers\Employer\ProfileController::class, 'index'],
    'employer_settings' => [\App\Http\Controllers\Employer\SettingsController::class, 'index'],
    'employer_messages' => [\App\Http\Controllers\Employer\MessageController::class, 'index'],
    'employer_notification' => [\App\Http\Controllers\Employer\NotificationController::class, 'index'],

    // Admin (also reachable under a superadmin_* URL, same controller)
    'admin_dashboard' => [\App\Http\Controllers\Admin\DashboardController::class, 'index'],
    'superadmin_dashboard' => [\App\Http\Controllers\Admin\DashboardController::class, 'index'],
    'admin_applicant' => [\App\Http\Controllers\Admin\ApplicantController::class, 'index'],
    'superadmin_applicant' => [\App\Http\Controllers\Admin\ApplicantController::class, 'index'],
    'admin_alumni' => [\App\Http\Controllers\Admin\AlumniController::class, 'index'],
    'superadmin_alumni' => [\App\Http\Controllers\Admin\AlumniController::class, 'index'],
    'admin_alumni_pending' => [\App\Http\Controllers\Admin\AlumniController::class, 'pending'],
    'superadmin_alumni_pending' => [\App\Http\Controllers\Admin\AlumniController::class, 'pending'],
    'admin_user' => [\App\Http\Controllers\Admin\UserController::class, 'index'],
    'superadmin_user' => [\App\Http\Controllers\Admin\UserController::class, 'index'],
    'admin_profile' => [\App\Http\Controllers\Admin\ProfileController::class, 'index'],
    'superadmin_profile' => [\App\Http\Controllers\Admin\ProfileController::class, 'index'],
    'admin_reports' => [\App\Http\Controllers\Admin\ReportController::class, 'index'],
    'superadmin_reports' => [\App\Http\Controllers\Admin\ReportController::class, 'index'],
    'admin_message' => [\App\Http\Controllers\Admin\MessageController::class, 'index'],
    'superadmin_message' => [\App\Http\Controllers\Admin\MessageController::class, 'index'],
    'admin_notification' => [\App\Http\Controllers\Admin\NotificationController::class, 'index'],
    'superadmin_notification' => [\App\Http\Controllers\Admin\NotificationController::class, 'index'],
    'admin_settings' => [\App\Http\Controllers\Admin\SettingsController::class, 'index'],
    'superadmin_settings' => [\App\Http\Controllers\Admin\SettingsController::class, 'index'],

    // Superadmin-only
    'superadmin_job' => [\App\Http\Controllers\Superadmin\JobController::class, 'index'],
    'superadmin_company' => [\App\Http\Controllers\Superadmin\CompanyController::class, 'index'],
    'superadmin_company_pending' => [\App\Http\Controllers\Superadmin\CompanyController::class, 'pending'],
    'superadmin_reminder_settings' => [\App\Http\Controllers\Superadmin\ReminderSettingsController::class, 'index'],
    'superadmin_success_stories' => [\App\Http\Controllers\Superadmin\SuccessStoryController::class, 'index'],
    'superadmin_audit_logs' => [\App\Http\Controllers\Superadmin\AuditLogController::class, 'index'],
    'superadmin_system_info' => [\App\Http\Controllers\Superadmin\SystemInfoController::class, 'index'],
    'superadmin_landing_editor' => [\App\Http\Controllers\Superadmin\LandingEditorController::class, 'index'],
];

/** Page slugs requiring no authentication at all. */
$public = [
    'login', 'logout', 'forgot_password', 'reset_password', 'employer_forgot_password',
    'employer_terms', 'alumni_terms', 'user_type', 'landing', 'signup', 'employer_signup',
    'employer_login', 'geocode_proxy', 'api_reminder',
];

$alumniOnly = ['notification', 'alumni_profile_data', 'home', 'my_application', 'alumni_success_stories', 'my_profile', 'message'];
$employerOnly = ['employer_dashboard', 'employer_applicants', 'employer_interview', 'employer_jobposting', 'employer_matchboard', 'employer_onboarding', 'employer_profile', 'employer_settings', 'employer_messages', 'employer_notification'];
$adminOrSuperadmin = [
    'admin_dashboard', 'superadmin_dashboard', 'admin_applicant', 'superadmin_applicant',
    'admin_alumni', 'superadmin_alumni', 'admin_alumni_pending', 'superadmin_alumni_pending',
    'admin_user', 'superadmin_user', 'admin_profile', 'superadmin_profile',
    'admin_reports', 'superadmin_reports', 'admin_message', 'superadmin_message',
    'admin_notification', 'superadmin_notification', 'admin_settings', 'superadmin_settings',
];
$superadminOnly = [
    'superadmin_job', 'superadmin_company', 'superadmin_company_pending', 'superadmin_reminder_settings',
    'superadmin_success_stories', 'superadmin_audit_logs', 'superadmin_system_info', 'superadmin_landing_editor',
];
$employerAdminSuperadmin = ['ai_suggestions'];

foreach ($pages as $page => [$controllerClass, $defaultAction]) {
    $route = Route::any("/{$page}", function (Request $request) use ($controllerClass, $defaultAction) {
        return \App\Http\LegacyDispatcher::handle($controllerClass, $defaultAction, $request);
    })->name($page);

    if (in_array($page, $public, true)) {
        continue;
    }
    if (in_array($page, $alumniOnly, true)) {
        $route->middleware('role:alumni');
    } elseif (in_array($page, $employerOnly, true)) {
        $route->middleware('role:employer');
    } elseif (in_array($page, $adminOrSuperadmin, true)) {
        $route->middleware('role:admin,superadmin');
    } elseif (in_array($page, $superadminOnly, true)) {
        $route->middleware('role:superadmin');
    } elseif (in_array($page, $employerAdminSuperadmin, true)) {
        $route->middleware('role:employer,admin,superadmin');
    }
}

Route::get('/', fn () => redirect('/landing'));
