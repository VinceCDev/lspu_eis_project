<?php
/** @var string $active current page slug, e.g. 'superadmin_job', used to highlight the matching link */
$active = $active ?? '';
$linkClass = function (string $page) use ($active) {
    return $active === $page
        ? 'flex items-center px-6 py-3 mx-2 rounded-lg bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 hover:bg-blue-500/20 dark:hover:bg-blue-500/30 transition-colors duration-200 border-l-4 border-blue-500 dark:border-blue-400'
        : 'flex items-center px-6 py-3 mx-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors duration-200';
};
// Pages shared by admin and superadmin are reachable under both an admin_*
// and a superadmin_* route (same controller); this picks the one matching
// the current user so the address bar reflects who's actually browsing.
$rolePrefix = \App\Core\Auth::role() === 'superadmin' ? 'superadmin' : 'admin';
?>
<div v-if="sidebarActive" class="fixed top-0 left-0 bottom-0 w-[280px] bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 shadow-xl z-50 transition-all duration-300 ease-in-out transform md:translate-x-0" :class="{'-translate-x-full': !sidebarActive && isMobile}">
    <div class="bg-white dark:bg-slate-700 shadow-sm h-[70px] border-b border-slate-200 dark:border-gray-700">
        <div class="flex items-center h-full px-6 mx-auto max-w-7xl">
            <a href="admin_dashboard" class="flex items-center">
                <img src="<?= asset('assets/images/logo.png') ?>" alt="Logo" class="w-12 h-12 mr-4 rounded-lg bg-white p-1 shadow-md ring-1 ring-slate-200/50 dark:bg-slate-700 dark:ring-slate-600/50">
                <span class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight">LSPU EIS</span>
            </a>
            <button class="md:hidden ml-auto p-2 rounded-full hover:bg-slate-100/50 dark:hover:bg-slate-700/50 transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-blue-500/30" @click="toggleSidebar">
                <i class="fas fa-times text-xl text-slate-600 dark:text-slate-300"></i>
            </button>
        </div>
    </div>

    <div class="overflow-y-auto pt-4 pb-20 h-[calc(100%-64px)] scrollbar-thin scrollbar-thumb-slate-300 scrollbar-track-slate-100 dark:scrollbar-thumb-slate-600 dark:scrollbar-track-slate-800/50">
        <div class="px-6 py-2 mb-2">
            <span class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 tracking-wider">Main</span>
        </div>

        <a href="<?= $rolePrefix ?>_dashboard" class="<?= $linkClass($rolePrefix.'_dashboard') ?>">
            <i class="fas fa-tachometer-alt w-5 mr-3 text-center text-blue-500 dark:text-blue-400"></i>
            <span class="font-medium">Dashboard</span>
        </a>

        <?php if (\App\Core\Auth::role() === 'superadmin'): ?>
        <a href="superadmin_job" class="<?= $linkClass('superadmin_job') ?>" @click="handleNavClick">
            <i class="fas fa-briefcase w-5 mr-3 text-center text-emerald-500 dark:text-emerald-400"></i>
            <span class="font-medium">Jobs</span>
        </a>
        <?php endif; ?>

        <a href="<?= $rolePrefix ?>_applicant" class="<?= $linkClass($rolePrefix.'_applicant') ?>" @click="handleNavClick">
            <i class="fas fa-users w-5 mr-3 text-center text-amber-500 dark:text-amber-400"></i>
            <span class="font-medium">Applicants</span>
        </a>

        <?php if (\App\Core\Auth::role() === 'superadmin'): ?>
        <div class="mx-2 mb-1">
            <button @click="companiesDropdownOpen = !companiesDropdownOpen" class="flex items-center w-full px-6 py-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors duration-200">
                <i class="fas fa-building w-5 mr-3 text-center text-purple-500 dark:text-purple-400"></i>
                <span class="font-medium">Companies</span>
                <i class="fas fa-chevron-down ml-auto text-xs transition-transform duration-200" :class="{'rotate-180': companiesDropdownOpen}"></i>
            </button>
            <div class="overflow-hidden transition-all duration-300 ease-in-out" :style="companiesDropdownOpen ? 'max-height: 100px' : 'max-height: 0'">
                <a href="superadmin_company" class="block py-2 pl-14 pr-6 mx-2 rounded-lg text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors duration-200" @click="handleNavClick">Manage Companies</a>
                <a href="superadmin_company_pending" class="flex items-center py-2 pl-14 pr-6 mx-2 rounded-lg text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors duration-200" @click="handleNavClick">
                    <span>Pending Companies</span>
                    <span id="sidebar-badge-superadmin_company_pending" class="hidden ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1"></span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="mx-2 mb-1">
            <button @click="alumniDropdownOpen = !alumniDropdownOpen" class="flex items-center w-full px-6 py-3 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors duration-200">
                <i class="fas fa-user-graduate w-5 mr-3 text-center text-cyan-500 dark:text-cyan-400"></i>
                <span class="font-medium">Alumni</span>
                <i class="fas fa-chevron-down ml-auto text-xs transition-transform duration-200" :class="{'rotate-180': alumniDropdownOpen}"></i>
            </button>
            <div class="overflow-hidden transition-all duration-300 ease-in-out" :style="alumniDropdownOpen ? 'max-height: 100px' : 'max-height: 0'">
                <a href="<?= $rolePrefix ?>_alumni" class="block py-2 pl-14 pr-6 mx-2 rounded-lg text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors duration-200" @click="handleNavClick">Manage Alumni</a>
                <a href="<?= $rolePrefix ?>_alumni_pending" class="flex items-center py-2 pl-14 pr-6 mx-2 rounded-lg text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors duration-200" @click="handleNavClick">
                    <span>Pending Alumni</span>
                    <span id="sidebar-badge-<?= $rolePrefix ?>_alumni_pending" class="hidden ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1"></span>
                </a>
            </div>
        </div>

        <a href="<?= $rolePrefix ?>_user" class="<?= $linkClass($rolePrefix.'_user') ?>">
            <i class="fas fa-user-shield w-5 mr-3 text-center text-red-500 dark:text-red-400"></i>
            <span class="font-medium">Accounts</span>
        </a>

        <a href="<?= $rolePrefix ?>_reports" class="<?= $linkClass($rolePrefix.'_reports') ?>" @click="handleNavClick">
            <i class="fas fa-chart-bar w-5 mr-3 text-center text-blue-500 dark:text-blue-400"></i>
            <span class="font-medium">Reports</span>
        </a>

        <a href="<?= $rolePrefix ?>_message" class="<?= $linkClass($rolePrefix.'_message') ?>" @click="handleNavClick">
            <i class="fas fa-envelope w-5 mr-3 text-center text-pink-500 dark:text-pink-400"></i>
            <span class="font-medium">Messages</span>
        </a>

        <?php if (\App\Core\Auth::role() === 'superadmin'): ?>
        <a href="superadmin_audit_logs" class="<?= $linkClass('superadmin_audit_logs') ?>" @click="handleNavClick">
            <i class="fas fa-history w-5 mr-3 text-center text-slate-500 dark:text-slate-400"></i>
            <span class="font-medium">Audit Logs</span>
        </a>
        <?php endif; ?>

        <a href="<?= $rolePrefix ?>_settings" class="<?= $linkClass($rolePrefix.'_settings') ?>" @click="handleNavClick">
            <i class="fas fa-cog w-5 mr-3 text-center text-slate-500 dark:text-slate-400"></i>
            <span class="font-medium">Settings</span>
        </a>
    </div>
</div>
<div v-if="sidebarActive && isMobile" class="fixed inset-0 bg-black bg-opacity-40 z-40 md:hidden" @click="toggleSidebar"></div>
<script>
    (function () {
        // Mirrors the header notification bell's timing note: this runs before Vue's
        // initial mount replaces the sidebar markup, so badge lookups are deferred to
        // 'load' and re-queried by id rather than cached.
        var CURRENT = <?= json_encode($active) ?>;
        var STORAGE_PREFIX = 'sidebar_badge_dismissed_';

        var ROLE_PREFIX = <?= json_encode($rolePrefix) ?>;
        var modules = [
            { key: ROLE_PREFIX + '_alumni_pending', endpoint: ROLE_PREFIX + '_alumni_pending?action=pendingList', listKey: 'alumni' },
            { key: 'superadmin_company_pending', endpoint: 'superadmin_company_pending?action=pendingList', listKey: null }
        ];

        function countFrom(data, listKey) {
            if (listKey) {
                return (data && data.success && Array.isArray(data[listKey])) ? data[listKey].length : 0;
            }
            return Array.isArray(data) ? data.length : 0;
        }

        function updateBadge(cfg) {
            var badge = document.getElementById('sidebar-badge-' + cfg.key);
            if (!badge) return;

            fetch(cfg.endpoint)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var count = countFrom(data, cfg.listKey);
                    var dismissedKey = STORAGE_PREFIX + cfg.key;

                    if (CURRENT === cfg.key) {
                        localStorage.setItem(dismissedKey, String(count));
                        badge.classList.add('hidden');
                        return;
                    }

                    var dismissed = parseInt(localStorage.getItem(dismissedKey) || '0', 10);
                    if (count > dismissed) {
                        badge.textContent = count > 99 ? '99+' : String(count);
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                })
                .catch(function () {});
        }

        window.addEventListener('load', function () {
            modules.forEach(updateBadge);
        });
    })();
</script>
