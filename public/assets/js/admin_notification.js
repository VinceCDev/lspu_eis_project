const { createApp } = Vue;

createApp({
    data() {
        return {
            sidebarActive: window.innerWidth >= 768,
            companiesDropdownOpen: false,
            systemDropdownOpen: false,
            alumniDropdownOpen: false,
            profileDropdownOpen: false,
            darkMode: localStorage.getItem('darkMode') === 'true' ||
                (localStorage.getItem('darkMode') === null &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches),
            showLogoutModal: false,
            isMobile: window.innerWidth < 768,
            notifications: [],
            notificationId: 0,
            profile: {
                profile_pic: '',
                name: ''
            },

            loading: true,
            notificationsList: [],
            notificationIcons: {
                application: 'fas fa-briefcase',
                new_application: 'fas fa-user-plus',
                registration: 'fas fa-user-check',
                hired: 'fas fa-handshake',
                password: 'fas fa-key',
                system: 'fas fa-cog',
                job_match: 'fas fa-bolt',
                message: 'fas fa-envelope'
            }
        };
    },
    computed: {
        allRead() {
            return this.notificationsList.every(n => n.read);
        }
    },
    mounted() {
        this.applyDarkMode();
        window.addEventListener('resize', this.handleResize);
        document.addEventListener('click', this.handleClickOutsideProfile);
        this.fetchProfile();
        this.fetchNotifications();
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
    },
    watch: {
        darkMode() {
            this.applyDarkMode();
        }
    },
    methods: {
        toggleSidebar() {
            this.sidebarActive = !this.sidebarActive;
        },
        handleResize() {
            this.isMobile = window.innerWidth < 768;
            this.sidebarActive = window.innerWidth >= 768;
        },
        handleNavClick() {
            if (this.isMobile) this.sidebarActive = false;
        },
        logout() {
            window.location.href = 'logout';
        },
        toggleDarkMode() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('darkMode', this.darkMode.toString());
            this.applyDarkMode();
        },
        applyDarkMode() {
            if (this.darkMode) {
                document.documentElement.classList.add('dark');
                document.body.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
                document.body.classList.remove('dark');
            }
        },
        toggleProfileDropdown() {
            this.profileDropdownOpen = !this.profileDropdownOpen;
        },
        handleClickOutsideProfile(event) {
            if (this.profileDropdownOpen && !event.target.closest('.profile-dropdown-wrapper')) {
                this.profileDropdownOpen = false;
            }
        },
        showNotification(message, type = 'success') {
            const id = this.notificationId++;
            this.notifications.push({ id, type, message });
            setTimeout(() => this.removeNotification(id), 3000);
        },
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        },
        async fetchProfile() {
            try {
                const response = await fetch('admin_profile?action=details', { credentials: 'include' });
                const data = await response.json();
                this.profile = data.success ? data.profile : { profile_pic: null, name: 'Admin' };
            } catch (error) {
                this.profile = { profile_pic: null, name: 'Admin' };
            }
        },

        formatTime(date) {
            const now = new Date();
            const d = new Date(date);
            const diffInSeconds = Math.floor((now - d) / 1000);
            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
            if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} days ago`;
            return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        async fetchNotifications() {
            try {
                this.loading = true;
                const res = await fetch('admin_notification?action=list');
                const data = await res.json();
                this.notificationsList = data.success ? data.notifications : [];
            } catch (error) {
                this.notificationsList = [];
            } finally {
                this.loading = false;
            }
        },
        async markNotificationAsRead(notification) {
            if (!notification.read) {
                notification.read = true;
                try {
                    await fetch('admin_notification?action=update', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=mark_one_read&id=${encodeURIComponent(notification.id)}`
                    });
                } catch (e) {
                    notification.read = false;
                }
            }

            const target = this.notificationTarget(notification);
            if (target) window.location.href = target;
        },
        notificationTarget(notification) {
            // Shared admin/superadmin pages are reachable under both an admin_*
            // and a superadmin_* route; stay on whichever one is currently loaded.
            const rolePrefix = window.location.pathname.includes('superadmin') ? 'superadmin' : 'admin';
            const message = (notification.message || '').toLowerCase();
            if (notification.type === 'registration') {
                if (message.includes('alumni')) return rolePrefix + '_alumni_pending';
                if (message.includes('employer')) return 'superadmin_company_pending';
            }
            if (notification.type === 'password') return rolePrefix + '_settings';
            if (notification.type === 'message' && notification.message_id) return rolePrefix + '_message?message_id=' + notification.message_id;
            return null;
        },
        markAllAsRead() {
            this.notificationsList.forEach(n => n.read = true);
            fetch('admin_notification?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=mark_all_read`
            });
        }
    }
}).mount('#app');
