<?php
/**
 * Ported from frontend/views/layouts/admin.php. Content wrapping uses
 * Blade's @section('content')/@yield('content') instead of the original's
 * $content variable; partials still go through App\Core\View::partial()
 * (see app/Core/View.php) so every page view could be copied unchanged.
 */
$title = $title ?? 'LSPU EIS';
$active = $active ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    @include('partials.session_guard')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="icon" type="image/png" href="<?php echo asset('assets/images/logo.png'); ?>">
    <link rel="stylesheet" href="<?php echo asset('assets/vendor/fontawesome/css/all.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('assets/vendor/tailwind/tailwind.css'); ?>">
    <?php if (!empty($pageCss)) { ?>
    <link rel="stylesheet" href="<?php echo asset('assets/css/'.$pageCss); ?>">
    <?php } ?>
    <?php echo $extraHead ?? ''; ?>
    <style>[v-cloak] { display: none !important; }</style>
</head>
<body :class="[darkMode ? 'dark' : '', 'font-sans bg-gray-50 dark:bg-gray-800 min-h-screen']" id="app" v-cloak>
    <?php App\Core\View::partial('admin/logout_modal'); ?>
    <?php App\Core\View::partial('admin/sidebar', ['active' => $active]); ?>
    <?php App\Core\View::partial('admin/header'); ?>

    <main :class="[isMobile ? 'ml-0' : (sidebarActive ? 'ml-[280px]' : 'ml-0'), 'transition-all duration-300 min-h-[calc(100vh-70px)] p-6 pt-lg-5 <?php echo in_array($active, ['admin_message', 'superadmin_message'], true) ? 'mt-[0px]' : 'mt-[70px]'; ?> bg-gray-50 dark:bg-gray-800']">
        <div class="container-fluid max-w-7xl mx-auto">
            @yield('content')
        </div>
    </main>

    <?php App\Core\View::partial('shared/footer'); ?>

    <script src="<?php echo asset('assets/vendor/vue/vue.global.prod.js'); ?>"></script>
    <script src="<?php echo asset('assets/js/lib-loader.js'); ?>"></script>
    <?php echo $extraScripts ?? ''; ?>
    <?php if (!empty($pageJs)) { ?>
    <script src="<?php echo asset('assets/js/'.$pageJs); ?>"></script>
    <?php } ?>
</body>
</html>
