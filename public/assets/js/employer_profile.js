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
                showEditModal: false,
                profile: {
                    company_name: '',
                    company_logo: '',
                    company_location: '',
                    contact_email: '',
                    contact_number: '',
                    industry_type: '',
                    nature_of_business: '',
                    tin: '',
                    date_established: '',
                    company_type: '',
                    accreditation_status: '',
                    document_file: ''
                },
                editForm: {},
                showPhotoModal: false,
                newLogoPreview: null,
                showLogoModal: false,
                showDeleteLogoModal: false,
                newLogoFile: null,
                showDocumentModal: false,
                showDeleteDocumentModal: false,
                newDocumentFile: null,
                newDocumentName: '',
                newDocumentPreview: null
            }
        },
        mounted() {
            this.applyDarkMode();
            window.addEventListener('resize', this.handleResize);
            document.addEventListener('click', this.handleClickOutsideProfile);
            // Fetch employer details
            fetch('employer_profile?action=details')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.profile) {
                        this.profile = data.profile;
                    }
                });
        },
        watch: {
            darkMode(val) {
                this.applyDarkMode();
            }
        },
        computed: {
            // The shared employer header partial reads employerProfile.company_logo /
            // company_name; every other employer page defines this separately, but here
            // it's identical to `profile` so just alias it instead of duplicating state.
            employerProfile() {
                return this.profile;
            }
        },
        methods: {
            toggleSidebar() {
                this.sidebarActive = !this.sidebarActive;
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
            handleResize() {
                this.isMobile = window.innerWidth < 768;
                if (window.innerWidth >= 768) {
                    this.sidebarActive = true;
                } else {
                    this.sidebarActive = false;
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
            editProfile() {
                this.editForm = { ...this.profile };
                this.showEditModal = true;
            },
            saveProfile() {
                const formData = new FormData();
                // Add all text fields
                for (const key in this.editForm) {
                    if (this.editForm.hasOwnProperty(key) && key !== 'company_logo') {
                        formData.append(key, this.editForm[key]);
                    }
                }
                // Add logo file if changed
                if (this.editForm.company_logo instanceof File) {
                    formData.append('company_logo', this.editForm.company_logo);
                }
                fetch('employer_profile?action=update', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('Profile updated successfully!', 'success');
                        this.closeEditModal();
                        // Refresh profile data
                        fetch('employer_profile?action=details')
                            .then(res => res.json())
                            .then(data => {
                                if (data.success && data.profile) {
                                    this.profile = data.profile;
                                }
                            });
                    } else {
                        this.showNotification(data.message || 'Failed to update profile.', 'error');
                    }
                })
                .catch(() => {
                    this.showNotification('Failed to update profile.', 'error');
                });
            },
            closeEditModal() {
                this.showEditModal = false;
                this.editForm = {};
            },
            openPhotoUpload() {
                this.showPhotoModal = true;
                this.newLogoPreview = null;
            },
            handleLogoUpload(event) {
                const file = event.target.files[0];
                if (file) {
                    this.newLogoPreview = URL.createObjectURL(file);
                    this.editForm.company_logo = file;
                }
            },
            handleDocumentUpload(event) {
                const file = event.target.files[0];
                if (file) {
                    this.newDocumentFile = file;
                    this.newDocumentName = file.name;
                    const ext = file.name.split('.').pop().toLowerCase();
                    if (ext === 'pdf') {
                        this.newDocumentPreview = URL.createObjectURL(file);
                    } else if (["jpg","jpeg","png","gif","bmp","webp"].includes(ext)) {
                        const reader = new FileReader();
                        reader.onload = (ev) => { this.newDocumentPreview = ev.target.result; };
                        reader.readAsDataURL(file);
                    } else {
                        this.newDocumentPreview = null;
                    }
                }
            },
            saveLogo() {
                if (this.newLogoPreview) {
                    this.profile.company_logo = this.newLogoPreview;
                    this.showNotification('Company logo updated successfully!', 'success');
                    this.closePhotoModal();
                } else {
                    this.showNotification('Please select a new logo to update.', 'info');
                }
            },
            closePhotoModal() {
                this.showPhotoModal = false;
                this.newLogoPreview = null;
            },
            handleLogoUpload(event) {
                const file = event.target.files[0];
                if (file) {
                    this.newLogoFile = file;
                    this.newLogoPreview = URL.createObjectURL(file);
                }
            },
            saveLogo() {
                if (!this.newLogoFile) return;
                const formData = new FormData();
                formData.append('company_logo', this.newLogoFile);
                fetch('employer_profile?action=updateLogo', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('Logo updated!', 'success');
                        this.showLogoModal = false;
                        this.newLogoFile = null;
                        this.newLogoPreview = null;
                        this.refreshProfile();
                    } else {
                        this.showNotification(data.message || 'Failed to update logo.', 'error');
                    }
                })
                .catch(() => {
                    this.showNotification('Failed to update logo.', 'error');
                });
            },
            confirmDeleteLogo() {
                this.showDeleteLogoModal = true;
            },
            deleteLogo() {
                fetch('employer_profile?action=deleteLogo', { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('Logo deleted!', 'success');
                        this.showDeleteLogoModal = false;
                        this.refreshProfile();
                    } else {
                        this.showNotification(data.message || 'Failed to delete logo.', 'error');
                    }
                })
                .catch(() => {
                    this.showNotification('Failed to delete logo.', 'error');
                });
            },
            handleDocumentUpload(event) {
                const file = event.target.files[0];
                if (file) {
                    this.newDocumentFile = file;
                    this.newDocumentName = file.name;
                }
            },
            saveDocument() {
                if (!this.newDocumentFile) return;
                const formData = new FormData();
                formData.append('document_file', this.newDocumentFile);
                fetch('employer_profile?action=updateDocument', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('Document updated!', 'success');
                        this.showDocumentModal = false;
                        this.newDocumentFile = null;
                        this.newDocumentName = '';
                        this.newDocumentPreview = null;
                        this.refreshProfile();
                    } else {
                        this.showNotification(data.message || 'Failed to update document.', 'error');
                    }
                })
                .catch(() => {
                    this.showNotification('Failed to update document.', 'error');
                });
            },
            confirmDeleteDocument() {
                this.showDeleteDocumentModal = true;
            },
            deleteDocument() {
                fetch('employer_profile?action=deleteDocument', { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('Document deleted!', 'success');
                        this.showDeleteDocumentModal = false;
                        this.refreshProfile();
                    } else {
                        this.showNotification(data.message || 'Failed to delete document.', 'error');
                    }
                })
                .catch(() => {
                    this.showNotification('Failed to delete document.', 'error');
                });
            },
            refreshProfile() {
                fetch('employer_profile?action=details')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.profile) {
                            this.profile = data.profile;
                        }
                    });
            },
            isPdf(filename) {
                return filename && filename.toLowerCase().endsWith('.pdf');
            },
            isImage(filename) {
                return filename && [".jpg", ".jpeg", ".png", ".gif", ".bmp", ".webp"].some(ext => filename.toLowerCase().endsWith(ext));
            },
            getFileName(path) {
                return path ? path.split('/').pop() : '';
            },
            fileIconClass(filename) {
                return window.FileHelpers.fileIconClass(filename);
            }
        }
    }).mount('#app');