const { createApp } = Vue;

createApp({
    data() {
        const settings = window.__heroSettings || {};
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
            form: {
                landing_hero_badge: settings.landing_hero_badge || '',
                landing_hero_headline: settings.landing_hero_headline || '',
                landing_hero_subtext: settings.landing_hero_subtext || '',
            },
            imagePreview: settings.landing_hero_image ? ('/uploads/landing/' + settings.landing_hero_image) : null,
            heroImageFile: null,
            saving: false,
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
        onImageChange(e) {
            const file = e.target.files[0];
            if (!file) return;
            this.heroImageFile = file;
            this.imagePreview = URL.createObjectURL(file);
        },
        async save() {
            this.saving = true;
            try {
                const formData = new FormData();
                formData.append('landing_hero_badge', this.form.landing_hero_badge);
                formData.append('landing_hero_headline', this.form.landing_hero_headline);
                formData.append('landing_hero_subtext', this.form.landing_hero_subtext);
                if (this.heroImageFile) {
                    formData.append('hero_image', this.heroImageFile);
                }

                const response = await fetch('/superadmin_landing_editor?action=update', {
                    method: 'POST',
                    body: formData,
                });
                const data = await response.json();
                if (data.success) {
                    this.showNotification('Landing page updated!', 'success');
                    this.heroImageFile = null;
                } else {
                    this.showNotification(data.message || 'Failed to update landing page.', 'error');
                }
            } catch (error) {
                this.showNotification('Failed to update landing page.', 'error');
            } finally {
                this.saving = false;
            }
        },
    },
}).mount('#app');
