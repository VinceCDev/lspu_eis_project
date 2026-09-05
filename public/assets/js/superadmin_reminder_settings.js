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
                    settings: {
                        business_hours_start: '9',
                        business_hours_end: '18',
                        timezone: 'Asia/Manila',
                        frequency_minutes: '1',
                        max_reminders_per_day: '3',
                        email_enabled: true,
                        sms_enabled: true,
                        email_subject: 'LSPU EIS - Automated Reminder',
                        email_message: 'Hello! This is your automated reminder from LSPU Employment and Information System. Please check your account for any updates, job opportunities, or important notifications. Stay connected with your alma mater!',
                        sms_message: 'LSPU EIS Reminder: Check your account for updates and job opportunities. Stay connected with your alma mater!'
                    },
                    profile: {
                        profile_pic: '',
                        name: '',
                    }
                }
            },
            mounted() {
                this.applyDarkMode();
                document.addEventListener('click', this.handleClickOutsideProfile);
                this.loadSettings(); // Load reminder settings
                fetch('admin_profile?action=details')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.profile) {
                            this.profile = data.profile;
                        }
                    });
                // Add resize event listener for responsive sidebar
                window.addEventListener('resize', this.handleResize);
            },
            beforeUnmount() {
                // Clean up event listeners
                window.removeEventListener('resize', this.handleResize);
            },
            watch: {
                darkMode(val) {
                    this.applyDarkMode();
                }
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
                    this.$nextTick(() => {
                        this.applyDarkMode();
                    });
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
                confirmLogout() {
                    this.showLogoutModal = true;
                },
                logout() {
                    window.location.href = 'logout';
                },
                showNotification(message, type = 'success') {
                    const id = this.notificationId++;
                    this.notifications.push({ id, type, message });
                    setTimeout(() => this.removeNotification(id), 3000); // Auto-dismiss after 3 seconds
                },
                removeNotification(id) {
                    this.notifications = this.notifications.filter(n => n.id !== id);
                },
                async loadSettings() {
                    try {
                        const response = await fetch('/superadmin_reminder_settings?action=settings');
                        const data = await response.json();
                        if (data.success && data.settings) {
                            this.settings = {
                                ...this.settings,
                                ...data.settings
                            };
                            // Convert string values to boolean for checkboxes
                            this.settings.email_enabled = data.settings.email_enabled === '1';
                            this.settings.sms_enabled = data.settings.sms_enabled === '1';
                        }
                    } catch (error) {
                        console.error('Error loading settings:', error);
                        this.showNotification('Failed to load settings', 'error');
                    }
                },
                async saveSettings() {
                    try {
                        const formData = {
                            business_hours_start: this.settings.business_hours_start,
                            business_hours_end: this.settings.business_hours_end,
                            timezone: this.settings.timezone,
                            frequency_minutes: this.settings.frequency_minutes,
                            max_reminders_per_day: this.settings.max_reminders_per_day,
                            email_enabled: this.settings.email_enabled,
                            sms_enabled: this.settings.sms_enabled,
                            email_subject: this.settings.email_subject,
                            email_message: this.settings.email_message,
                            sms_message: this.settings.sms_message
                        };

                        const response = await fetch('/superadmin_reminder_settings?action=updateSettings', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(formData)
                        });

                        const data = await response.json();
                        
                        if (data.success) {
                            this.showNotification('Settings updated successfully!', 'success');
                        } else {
                            this.showNotification(data.message || 'Failed to update settings', 'error');
                        }
                    } catch (error) {
                        console.error('Error saving settings:', error);
                        this.showNotification('Failed to save settings', 'error');
                    }
                }
            }
        }).mount('#app');