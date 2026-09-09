<?php
/** Ported from frontend/views/layouts/alumni.php — see layouts/admin.blade.php's docblock for the conversion approach. */
$title = $title ?? 'LSPU EIS';
$active = $active ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    @include('partials.session_guard')
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link rel="icon" type="image/png" href="<?= asset('assets/images/logo-sm.png') ?>">
    <link rel="stylesheet" href="<?= asset('assets/vendor/fontawesome/css/all.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/vendor/tailwind/tailwind.css') ?>">
    <?php if (!empty($pageCss)): ?>
    <link rel="stylesheet" href="<?= asset('assets/css/'.$pageCss) ?>">
    <?php endif; ?>
    <?= $extraHead ?? '' ?>
    <style>[v-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200 font-segoe pt-[70px] transition-colors duration-200" id="app" v-cloak>
    <?php App\Core\View::partial('alumni/welcome_modal'); ?>
    <?php App\Core\View::partial('alumni/header', ['active' => $active]); ?>

    <main class="container mx-auto px-4 pt-4 pb-20 min-h-[calc(100vh-80px)] bg-gray-100 dark:bg-gray-900 transition-colors duration-200">
        @yield('content')
    </main>

    <?php App\Core\View::partial('alumni/footer'); ?>
    <?php App\Core\View::partial('alumni/logout_modal'); ?>

    <script src="<?= asset('assets/vendor/vue/vue.global.prod.js') ?>"></script>
    <script src="<?= asset('assets/js/lib-loader.js') ?>"></script>
    <script>
      (function() {
        try {
          var dark = localStorage.getItem('darkMode');
          if (dark === 'true' || (dark === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
          }
        } catch(e){}
      })();
    </script>
    <?= $extraScripts ?? '' ?>
    <?php if (!empty($pageJs)): ?>
    <script src="<?= asset('assets/js/'.$pageJs) ?>"></script>
    <?php endif; ?>
</body>
</html>
