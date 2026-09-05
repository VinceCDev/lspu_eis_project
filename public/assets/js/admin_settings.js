const { createApp } = Vue;

createApp({
    data() {
        const heroSettings = window.__heroSettings || {};
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
            isSuperadmin: window.__isSuperadmin || false,
            activeTab: 'account',
            passwordForm: {
                current_password: '',
                new_password: '',
                confirm_password: '',
            },
            passwordSaving: false,
            notificationTypes: window.__notificationTypes || {},
            notificationPrefs: window.__notificationPreferences || {},
            notifSaving: false,
            twoFactorEnabled: window.__twoFactorEnabled || false,
            twoFactorSaving: false,
            passwordPolicy: window.__passwordPolicy || { min_length: 10, require_uppercase: true, require_number: true, require_symbol: true },
            passwordPolicySaving: false,
            // Reminder Settings tab
            reminderSettings: {
                business_hours_start: '9',
                business_hours_end: '18',
                timezone: 'Asia/Manila',
                frequency_minutes: '1',
                max_reminders_per_day: '3',
                email_enabled: true,
                sms_enabled: true,
                email_subject: 'LSPU EIS - Automated Reminder',
                email_message: 'Hello! This is your automated reminder from LSPU Employment and Information System. Please check your account for any updates, job opportunities, or important notifications. Stay connected with your alma mater!',
                sms_message: 'LSPU EIS Reminder: Check your account for updates and job opportunities. Stay connected with your alma mater!',
            },
            reminderSaving: false,
            // System Info tab
            systemInfoLoading: true,
            userCounts: {},
            pendingCounts: {},
            recentRegistrations: [],
            databaseSizeBytes: 0,
            uploadsSizeBytes: {},
            serverInfo: {},
            // Landing Page tab
            landingForm: {
                landing_hero_badge: heroSettings.landing_hero_badge || '',
                landing_hero_headline: heroSettings.landing_hero_headline || '',
                landing_hero_subtext: heroSettings.landing_hero_subtext || '',
            },
            landingImagePreview: heroSettings.landing_hero_image ? ('/uploads/landing/' + heroSettings.landing_hero_image) : null,
            landingHeroImageFile: null,
            landingSaving: false,
        };
    },
    mounted() {
        this.applyDarkMode();
        document.addEventListener('click', this.handleClickOutsideProfile);
        fetch('admin_profile?action=details')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.profile) {
                    this.profile = data.profile;
                }
            });
        if (this.isSuperadmin) {
            this.loadReminderSettings();
            this.fetchSystemStats();
        }
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
    computed: {
        passwordRequirementsText() {
            const p = this.passwordPolicy;
            const parts = [`at least ${p.min_length} characters`];
            if (p.require_uppercase) parts.push('an uppercase letter');
            if (p.require_number) parts.push('a number');
            if (p.require_symbol) parts.push('a symbol');
            const last = parts.pop();
            return 'Must be ' + (parts.length ? parts.join(', ') + ', and ' + last : last) + '.';
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
        async changePassword() {
            if (this.passwordForm.new_password !== this.passwordForm.confirm_password) {
                this.showNotification('New password and confirmation do not match.', 'error');
                return;
            }
            this.passwordSaving = true;
            try {
                const response = await fetch('/admin_settings?action=changePassword', {
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
        async saveNotificationPreferences() {
            this.notifSaving = true;
            try {
                const response = await fetch('/admin_settings?action=updateNotificationPreferences', {
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
        async toggleTwoFactor(e) {
            const desired = e.target.checked;
            this.twoFactorSaving = true;
            try {
                const response = await fetch('/admin_settings?action=toggleTwoFactor', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ enabled: desired }),
                });
                const data = await response.json();
                if (data.success) {
                    this.twoFactorEnabled = desired;
                    this.showNotification(data.message, 'success');
                } else {
                    e.target.checked = this.twoFactorEnabled;
                    this.showNotification(data.message || 'Failed to update two-factor authentication.', 'error');
                }
            } catch (error) {
                e.target.checked = this.twoFactorEnabled;
                this.showNotification('Failed to update two-factor authentication.', 'error');
            } finally {
                this.twoFactorSaving = false;
            }
        },
        async savePasswordPolicy() {
            this.passwordPolicySaving = true;
            try {
                const response = await fetch('/admin_settings?action=updatePasswordPolicy', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        password_min_length: this.passwordPolicy.min_length,
                        password_require_uppercase: this.passwordPolicy.require_uppercase,
                        password_require_number: this.passwordPolicy.require_number,
                        password_require_symbol: this.passwordPolicy.require_symbol,
                    }),
                });
                const data = await response.json();
                if (data.success) {
                    this.showNotification('Password requirements updated.', 'success');
                } else {
                    this.showNotification(data.message || 'Failed to update password requirements.', 'error');
                }
            } catch (error) {
                this.showNotification('Failed to update password requirements.', 'error');
            } finally {
                this.passwordPolicySaving = false;
            }
        },
        async loadReminderSettings() {
            try {
                const response = await fetch('/superadmin_reminder_settings?action=settings');
                const data = await response.json();
                if (data.success && data.settings) {
                    this.reminderSettings = { ...this.reminderSettings, ...data.settings };
                    this.reminderSettings.email_enabled = data.settings.email_enabled === '1';
                    this.reminderSettings.sms_enabled = data.settings.sms_enabled === '1';
                }
            } catch (error) {
                this.showNotification('Failed to load reminder settings.', 'error');
            }
        },
        async saveReminderSettings() {
            this.reminderSaving = true;
            try {
                const response = await fetch('/superadmin_reminder_settings?action=updateSettings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.reminderSettings),
                });
                const data = await response.json();
                if (data.success) {
                    this.showNotification('Reminder settings updated successfully!', 'success');
                } else {
                    this.showNotification(data.message || 'Failed to update reminder settings.', 'error');
                }
            } catch (error) {
                this.showNotification('Failed to save reminder settings.', 'error');
            } finally {
                this.reminderSaving = false;
            }
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
        async fetchSystemStats() {
            this.systemInfoLoading = true;
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
                this.systemInfoLoading = false;
            }
        },
        onLandingImageChange(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.landingHeroImageFile = file;
            this.landingImagePreview = URL.createObjectURL(file);
        },
        async saveLanding() {
            this.landingSaving = true;
            try {
                const formData = new FormData();
                formData.append('landing_hero_badge', this.landingForm.landing_hero_badge);
                formData.append('landing_hero_headline', this.landingForm.landing_hero_headline);
                formData.append('landing_hero_subtext', this.landingForm.landing_hero_subtext);
                if (this.landingHeroImageFile) {
                    formData.append('hero_image', this.landingHeroImageFile);
                }

                const response = await fetch('/superadmin_landing_editor?action=update', {
                    method: 'POST',
                    body: formData,
                });
                const data = await response.json();
                if (data.success) {
                    this.showNotification('Landing page updated!', 'success');
                    this.landingHeroImageFile = null;
                } else {
                    this.showNotification(data.message || 'Failed to update landing page.', 'error');
                }
            } catch (error) {
                this.showNotification('Failed to update landing page.', 'error');
            } finally {
                this.landingSaving = false;
            }
        },
    },
}).mount('#app');
