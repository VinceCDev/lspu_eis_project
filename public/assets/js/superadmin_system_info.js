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
                name: '',
            },
            loading: true,
            userCounts: {},
            pendingCounts: {},
            recentRegistrations: [],
            databaseSizeBytes: 0,
            uploadsSizeBytes: {},
            serverInfo: {},
        };
    },
    mounted() {
        this.applyDarkMode();
        document.addEventListener('click', this.handleClickOutsideProfile);
        this.fetchStats();
        fetch('admin_profile?action=details')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.profile) {
                    this.profile = data.profile;
                }
            });
        window.addEventListener('resize', this.handleResize);
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
    },
    watch: {
        darkMode(val) {
            this.applyDarkMode();
        },
    },
    methods: {
        toggleSidebar() {
            this.sidebarActive = !this.sidebarActive;
        },
        handleNavClick() {
            if (this.isMobile) {
                this.sidebarActive = false;
            }
        },
        handleResize() {
            this.isMobile = window.innerWidth < 768;
            if (window.innerWidth >= 768) {
                this.sidebarActive = true;
            } else {
                this.sidebarActive = false;
            }
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
        logout() {
            window.location.href = 'logout';
        },
        showNotification(message, type = 'success') {
            const id = this.notificationId++;
            this.notifications.push({ id, type, message });
            setTimeout(() => this.removeNotification(id), 3000);
        },
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        },
        formatBytes(bytes) {
            if (!bytes) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
        },
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString.replace(' ', 'T'));
            if (isNaN(date)) return dateString;
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        },
        async fetchStats() {
            this.loading = true;
            try {
                const response = await fetch('/superadmin_system_info?action=stats');
                const data = await response.json();
                if (data.success) {
                    this.userCounts = data.user_counts;
                    this.pendingCounts = data.pending_counts;
                    this.recentRegistrations = data.recent_registrations;
                    this.databaseSizeBytes = data.database_size_bytes;
                    this.uploadsSizeBytes = data.uploads_size_bytes;
                    this.serverInfo = data.server_info;
                } else {
                    this.showNotification('Failed to load system info.', 'error');
                }
            } catch (error) {
                this.showNotification('Failed to load system info.', 'error');
            } finally {
                this.loading = false;
            }
        },
    },
}).mount('#app');
