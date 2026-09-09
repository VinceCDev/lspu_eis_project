<?php
/**
 * Ported from frontend/views/layouts/admin.php. Content wrapping uses
 * Blade's @section('content')/@yield('content') instead of the original's
 * $content variable; partials still go through App\Core\View::partial()
 * (see app/Core/View.php) so every page view could be copied unchanged.
 */
$title = $title ?? 'LSPU EIS';
$active = $active ?? '';

/**
 * Cache-bust app-owned JS/CSS with the file's mtime so a deploy is picked
 * up immediately — nginx serves /assets with "Cache-Control: immutable",
 * so without this a changed admin_*.js keeps serving stale from the browser
 * until a hard refresh.
 */
$assetV = static function (string $rel): string {
    $abs = public_path($rel);
    $v = is_file($abs) ? (string) filemtime($abs) : '1';

    return asset($rel).'?v='.$v;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    @include('partials.session_guard')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="icon" type="image/png" href="<?php echo asset('assets/images/logo-sm.png'); ?>">
    <?php /* Start the solid-icon webfont download in parallel with the CSS
             (it is otherwise only discovered after all.min.css parses, which
             left every fas/fa- icon blank for the font's download time).
             Only fa-solid-900 is preloaded — every above-the-fold icon in
             the sidebar/header is `fas`; brands/regular are not. */ ?>
    <link rel="preload" as="font" type="font/woff2" crossorigin href="<?php echo asset('assets/vendor/fontawesome/webfonts/fa-solid-900.woff2'); ?>">
    <link rel="stylesheet" href="<?php echo asset('assets/vendor/fontawesome/css/all.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('assets/vendor/tailwind/tailwind.css'); ?>">
    <?php if (!empty($pageCss)) { ?>
    <link rel="stylesheet" href="<?php echo $assetV('assets/css/'.$pageCss); ?>">
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

    <?php /* defer: don't block HTML parsing; still execute in order
             (vue -> lib-loader -> page script) before DOMContentLoaded. */ ?>
    <script defer src="<?php echo asset('assets/vendor/vue/vue.global.prod.js'); ?>"></script>
    <script defer src="<?php echo $assetV('assets/js/lib-loader.js'); ?>"></script>
    <?php echo $extraScripts ?? ''; ?>
    <?php if (!empty($pageJs)) { ?>
    <script defer src="<?php echo $assetV('assets/js/'.$pageJs); ?>"></script>
    <?php } ?>
</body>
</html>
