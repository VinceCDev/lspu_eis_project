const { createApp } = Vue;

createApp({
    data() {
        return {
            sidebarActive: window.innerWidth >= 768,
            profileDropdownOpen: false,
            darkMode: localStorage.getItem('darkMode') === 'true' ||
                (localStorage.getItem('darkMode') === null &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches),
            showLogoutModal: false,
            isMobile: window.innerWidth < 768,
            notifications: [],
            notificationId: 0,
            employerProfile: { company_name: '', company_logo: '' },
            activeTab: 'account',
            passwordForm: {
                current_password: '',
                new_password: '',
                confirm_password: '',
            },
            passwordSaving: false,
            showDeleteConfirm: false,
            showFinalDeleteConfirm: false,
            deletePassword: '',
            deleteSaving: false,
            notificationTypes: window.__notificationTypes || {},
            notificationPrefs: window.__notificationPreferences || {},
            notifSaving: false,
        };
    },
    mounted() {
        this.applyDarkMode();
        document.addEventListener('click', this.handleClickOutsideProfile);
        this.fetchEmployerProfile();
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
        fetchEmployerProfile() {
            fetch('employer_profile?action=details')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.profile) {
                        this.employerProfile = data.profile;
                    }
                });
        },
        async changePassword() {
            if (this.passwordForm.new_password !== this.passwordForm.confirm_password) {
                this.showNotification('New password and confirmation do not match.', 'error');
                return;
            }
            this.passwordSaving = true;
            try {
                const response = await fetch('/employer_settings?action=changePassword', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.passwordForm),
                });
                const data = await response.json();
                if (data.success) {
                    this.showNotification('Password updated successfully!', 'success');
                    this.passwordForm = { current_password: '', new_password: '', confirm_password: '' };
                } else {
                    this.showNotification(data.message || 'Failed to update password.', 'error');
                }
            } catch (error) {
                this.showNotification('Failed to update password.', 'error');
            } finally {
                this.passwordSaving = false;
            }
        },
        async deleteAccount() {
            this.deleteSaving = true;
            try {
                const response = await fetch('/employer_settings?action=deleteAccount', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ password: this.deletePassword }),
                });
                const data = await response.json();
                if (data.success) {
                    window.location.href = 'login';
                } else {
                    this.showNotification(data.message || 'Failed to delete account.', 'error');
                    this.deleteSaving = false;
                    this.showFinalDeleteConfirm = false;
                }
            } catch (error) {
                this.showNotification('Failed to delete account.', 'error');
                this.deleteSaving = false;
                this.showFinalDeleteConfirm = false;
            }
        },
        async saveNotificationPreferences() {
            this.notifSaving = true;
            try {
                const response = await fetch('/employer_settings?action=updateNotificationPreferences', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.notificationPrefs),
                });
                const data = await response.json();
                if (data.success) {
                    this.showNotification('Notification preferences updated!', 'success');
                } else {
                    this.showNotification(data.message || 'Failed to update preferences.', 'error');
                }
            } catch (error) {
                this.showNotification('Failed to update preferences.', 'error');
            } finally {
                this.notifSaving = false;
            }
        },
    },
}).mount('#app');
