@verbatim
<?php
// Pages shared by admin and superadmin are reachable under both an admin_*
// and a superadmin_* route (same controller); this picks the one matching
// the current user so the address bar reflects who's actually browsing.
$rolePrefix = \App\Core\Auth::role() === 'superadmin' ? 'superadmin' : 'admin';
?>
<transition
    enter-active-class="slide-enter-active"
    enter-from-class="slide-enter-from"
    leave-active-class="slide-leave-active"
    leave-to-class="slide-leave-to"
>
    <div v-if="notifications.length > 0" @click="removeNotification(notifications[0].id)"
        :class="[
            'notification-toast cursor-pointer fixed top-4 right-4 z-[9999] max-w-sm w-full pointer-events-auto',
            notifications[0].type === 'success' ? 'bg-green-100 border-green-500 text-green-700 dark:bg-green-900 dark:border-green-700 dark:text-green-100' : '',
            notifications[0].type === 'error' ? 'bg-red-100 border-red-500 text-red-700 dark:bg-red-900 dark:border-red-700 dark:text-red-100' : '',
            notifications[0].type === 'info' ? 'bg-blue-100 border-blue-500 text-blue-700 dark:bg-blue-900 dark:border-blue-700 dark:text-blue-100' : '',
            'border-l-4 p-4 rounded shadow-lg'
        ]">
        <div class="flex items-center">
            <i v-if="notifications[0].type === 'success'" class="fas fa-check-circle text-green-500 dark:text-green-300 mr-3"></i>
            <i v-if="notifications[0].type === 'error'" class="fas fa-exclamation-circle text-red-500 dark:text-red-300 mr-3"></i>
            <i v-if="notifications[0].type === 'info'" class="fas fa-info-circle text-blue-500 dark:text-blue-300 mr-3"></i>
            <div>
                <p class="font-medium">{{ notifications[0].message }}</p>
            </div>
        </div>
    </div>
</transition>

<header class="fixed top-0 left-0 right-0 h-[70px] bg-white dark:bg-gray-700 shadow-md z-40 flex items-center px-4">
    <div class="flex items-center justify-between w-full">
        <button class="md:hidden text-gray-600 dark:text-gray-300 p-1" @click="toggleSidebar">
            <i class="fas fa-bars text-xl"></i>
        </button>
        <div class="flex items-center space-x-4 ml-auto">
            <div class="relative">
                <button id="admin-notif-bell" type="button" class="relative text-gray-600 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400 transition-colors" title="Notifications">
                    <i class="fas fa-bell text-xl"></i>
                    <span id="admin-notif-badge" class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1"></span>
                </button>
                <div id="admin-notif-panel" class="hidden absolute right-0 mt-2 w-80 max-w-[90vw] bg-white dark:bg-gray-600 rounded-md shadow-lg z-50 border border-gray-200 dark:border-gray-500 origin-top-right">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-500">
                        <span class="font-semibold text-gray-800 dark:text-gray-100">Notifications</span>
                        <button id="admin-notif-mark-all" type="button" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Mark all as read</button>
                    </div>
                    <div id="admin-notif-list" class="max-h-96 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-500">
                        <div class="px-4 py-6 text-center text-sm text-gray-400">Loading...</div>
                    </div>
                    <a href="<?= $rolePrefix ?>_notification" class="block text-center py-2.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:bg-gray-50 dark:hover:bg-gray-500 border-t border-gray-200 dark:border-gray-500">See more</a>
                </div>
            </div>
            <div class="relative profile-dropdown-wrapper">
                <div class="cursor-pointer flex items-center" @click="toggleProfileDropdown()">
                    <img :src="profile.profile_pic || '<?= asset('assets/images/logo-sm.png') ?>'" alt="Profile" class="w-10 h-10 rounded-full border-2 border-gray-200 dark:border-gray-500">
                    <span class="ml-2 font-medium text-gray-700 dark:text-gray-200">{{ profile.name ? profile.name.split(' ')[0] : 'Admin' }}</span>
                    <i class="fas fa-chevron-down ml-2 text-xs transition-transform duration-200 text-gray-700 dark:text-gray-200" :class="{'rotate-180': profileDropdownOpen}"></i>
                </div>
                <transition
                    enter-active-class="dropdown-enter-active"
                    enter-from-class="dropdown-enter-from"
                    enter-to-class="dropdown-enter-to"
                    leave-active-class="dropdown-leave-active"
                    leave-from-class="dropdown-leave-from"
                    leave-to-class="dropdown-leave-to"
                >
                    <div v-if="profileDropdownOpen" class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-600 rounded-md shadow-lg py-1 z-50 border border-gray-200 dark:border-gray-500 transform origin-top-right">
                        <div class="flex items-center justify-between px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-500 cursor-pointer" @click="toggleDarkMode">
                            <div class="flex items-center">
                                <i class="fas fa-sun mr-3 theme-light" v-if="!darkMode"></i>
                                <i class="fas fa-moon mr-3 theme-dark" v-if="darkMode"></i>
                                <span class="text-sm">Theme</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer" @click.stop>
                                <input type="checkbox" class="sr-only peer" v-model="darkMode">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-500 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-400 peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                        <a class="flex items-center px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-500" href="<?= $rolePrefix ?>_profile">
                            <i class="fas fa-user mr-3"></i> Profile
                        </a>
                        <?php if (\App\Core\Auth::role() === 'superadmin'): ?>
                        <a href="superadmin_success_stories" class="flex items-center px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-500">
                            <i class="fas fa-book-open mr-3"></i>Success Stories
                        </a>
                        <?php endif; ?>
                        <a class="flex items-center px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-500" href="forgot_password">
                            <i class="fas fa-key mr-3"></i> Forgot Password
                        </a>
                        <div class="border-t border-gray-200 dark:border-gray-500 my-1"></div>
                        <a class="flex items-center px-4 py-2 text-gray-800 dark:text-gray-200 hover:bg-red-100 dark:hover:bg-red-500 hover:text-red-400 dark:hover:text-red-200" href="#" role="button" @click.prevent="showLogoutModal = true">
                            <i class="fas fa-sign-out-alt mr-3"></i> Logout
                        </a>
                    </div>
                </transition>
            </div>
        </div>
    </div>
</header>
<script>
    (function () {
        // Vue mounts on <body id="app"> and replaces this partial's static markup with
        // freshly rendered DOM right after this script runs, so any element reference or
        // listener captured here at parse time ends up pointing at detached nodes. Every
        // lookup below is therefore re-queried on demand via el(), never cached.
        var ROUTE = 'admin_notification';

        function el(id) { return document.getElementById('admin-notif-' + id); }

        var icons = {
            application: 'fas fa-briefcase',
            new_application: 'fas fa-user-plus',
            registration: 'fas fa-user-check',
            hired: 'fas fa-handshake',
            password: 'fas fa-key',
            system: 'fas fa-cog',
            job_match: 'fas fa-bolt',
            message: 'fas fa-envelope'
        };
        var loaded = false;

        function escapeHtml(s) {
            var div = document.createElement('div');
            div.textContent = s || '';
            return div.innerHTML;
        }

        var ROLE_PREFIX = <?= json_encode($rolePrefix) ?>;

        function targetFor(n) {
            var msg = (n.message || '').toLowerCase();
            if (n.type === 'registration') {
                if (msg.indexOf('alumni') !== -1) return ROLE_PREFIX + '_alumni_pending';
                if (msg.indexOf('employer') !== -1) return 'superadmin_company_pending';
            }
            if (n.type === 'password') return ROLE_PREFIX + '_settings';
            if (n.type === 'message' && n.message_id) return ROLE_PREFIX + '_message?message_id=' + n.message_id;
            return null;
        }

        function timeAgo(dateStr) {
            var d = new Date(dateStr.replace(' ', 'T'));
            var diff = Math.floor((Date.now() - d.getTime()) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }

        function refreshBadge() {
            fetch(ROUTE + '?action=unreadCount')
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var badge = el('badge');
                    if (!badge) return;
                    if (d.success && d.unread_count > 0) {
                        badge.textContent = d.unread_count > 99 ? '99+' : d.unread_count;
                        badge.classList.remove('hidden');
                    } else {
                        badge.classList.add('hidden');
                    }
                })
                .catch(function () {});
        }

        function renderList(items) {
            var list = el('list');
            if (!list) return;
            if (!items.length) {
                list.innerHTML = '<div class="px-4 py-6 text-center text-sm text-gray-400">No notifications yet</div>';
                return;
            }
            list.innerHTML = items.slice(0, 8).map(function (n) {
                var icon = icons[n.type] || 'fas fa-bell';
                var unreadClass = n.read ? '' : 'bg-blue-50 dark:bg-blue-900/30';
                return '<div class="notif-item flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-500 ' + unreadClass + '" data-id="' + n.id + '">'
                    + '<i class="' + icon + ' text-blue-500 dark:text-blue-400 mt-0.5"></i>'
                    + '<div class="min-w-0 flex-1">'
                    + '<p class="text-sm font-medium text-gray-800 dark:text-gray-100 truncate">' + escapeHtml(n.message) + '</p>'
                    + '<p class="text-xs text-gray-400 mt-0.5">' + timeAgo(n.time) + '</p>'
                    + '</div>'
                    + (n.read ? '' : '<span class="w-2 h-2 rounded-full bg-blue-500 mt-1.5 flex-shrink-0"></span>')
                    + '</div>';
            }).join('');

            list.querySelectorAll('.notif-item').forEach(function (item, idx) {
                item.addEventListener('click', function () {
                    var id = item.getAttribute('data-id');
                    var n = items[idx];
                    fetch(ROUTE + '?action=update', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=mark_one_read&id=' + encodeURIComponent(id)
                    }).then(function () {
                        item.classList.remove('bg-blue-50', 'dark:bg-blue-900/30');
                        var dot = item.querySelector('.bg-blue-500');
                        if (dot) dot.remove();
                        refreshBadge();
                        var target = targetFor(n);
                        if (target) window.location.href = target;
                    });
                });
            });
        }

        function loadList() {
            var list = el('list');
            if (list) list.innerHTML = '<div class="px-4 py-6 text-center text-sm text-gray-400">Loading...</div>';
            fetch(ROUTE + '?action=list')
                .then(function (r) { return r.json(); })
                .then(function (d) { renderList(d.success ? d.notifications : []); })
                .catch(function () {
                    var l = el('list');
                    if (l) l.innerHTML = '<div class="px-4 py-6 text-center text-sm text-gray-400">Failed to load</div>';
                });
        }

        document.addEventListener('click', function (e) {
            var bell = el('bell');
            var panel = el('panel');
            if (!bell || !panel) return;

            if (bell.contains(e.target)) {
                e.stopPropagation();
                var isHidden = panel.classList.contains('hidden');
                panel.classList.toggle('hidden');
                if (isHidden) {
                    loaded = true;
                    loadList();
                }
                return;
            }

            if (!panel.contains(e.target)) {
                panel.classList.add('hidden');
            }
        });

        document.addEventListener('click', function (e) {
            var markAllBtn = el('mark-all');
            if (!markAllBtn || !markAllBtn.contains(e.target)) return;
            e.stopPropagation();
            fetch(ROUTE + '?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=mark_all_read'
            }).then(function () {
                loadList();
                refreshBadge();
            });
        });

        // Deferred to 'load' so it runs after Vue's initial mount has replaced this
        // partial's static markup, otherwise it would update a badge node Vue is about
        // to discard.
        window.addEventListener('load', refreshBadge);
    })();
</script>
@endverbatim
