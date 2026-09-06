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
                showEditModal: false,
                modalMode: 'profile',
                editingExperienceIndex: null,
                profile: {
                    profile_pic: '',
                    name: '',
                    email: '',
                    phone: '',
                    address: '',
                    position: '',
                    campus_name: '',
                    is_superadmin: false
                },
                editForm: {
                    first_name: '',
                    middle_name: '',
                    last_name: '',
                    contact: '',
                    address: ''
                },
                addressSuggestions: [],
                showAddressSuggestions: false,
                showPhotoModal: false,
                newPhotoPreview: null,
                newPhotoFile: null,
                showDeletePhotoModal: false,
                showDeleteModal: false,
                experienceToDelete: null
            }
        },
        mounted() {
            this.applyDarkMode();
            window.addEventListener('resize', this.handleResize);
            document.addEventListener('click', this.handleClickOutsideProfile);
            // Fetch admin details
            fetch('admin_profile?action=details')
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
        methods: {
            toggleSidebar() {
                this.sidebarActive = !this.sidebarActive;
                this.companiesDropdownOpen = false;
                this.alumniDropdownOpen = false;
            },
            toggleProfileDropdown() {
                this.profileDropdownOpen = !this.profileDropdownOpen;
            },
            handleClickOutsideProfile(event) {
                if (this.profileDropdownOpen && !event.target.closest('.profile-dropdown-wrapper')) {
                    this.profileDropdownOpen = false;
                }
            },
            handleNavClick() {
                if (this.isMobile) {
                    this.sidebarActive = false;
                }
            },
            toggleDarkMode() {
                this.darkMode = !this.darkMode;
                localStorage.setItem('darkMode', this.darkMode.toString());
                this.applyDarkMode();
                // Force a small delay to ensure the DOM updates
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
            confirmLogout() {
                this.showLogoutModal = true;
            },
            logout() {
                window.location.href = 'logout';
            },
            handleResize() {
                this.isMobile = window.innerWidth < 768;
                if (window.innerWidth >= 768) {
                    this.sidebarActive = true;
                } else {
                    this.sidebarActive = false;
                }
            },
            formatDate(dateString) {
                if (!dateString) return 'Present';
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
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
                this.modalMode = 'profile';
                this.editForm = {
                    first_name: this.profile.first_name || '',
                    middle_name: this.profile.middle_name || '',
                    last_name: this.profile.last_name || '',
                    contact: this.profile.phone || '',
                    address: this.profile.address || ''
                };
                this.showEditModal = true;
            },
            async fetchAddressSuggestions() {
                const val = this.editForm.address;
                if (!val || val.length < 3) {
                    this.addressSuggestions = [];
                    this.showAddressSuggestions = false;
                    return;
                }
                const url = `/geocode_proxy?action=autocomplete&text=${encodeURIComponent(val)}&limit=5`;
                try {
                    const res = await fetch(url);
                    const data = await res.json();
                    this.addressSuggestions = data.features.map(f => f.properties.formatted);
                    this.showAddressSuggestions = true;
                } catch (e) {
                    this.addressSuggestions = [];
                    this.showAddressSuggestions = false;
                }
            },
            selectAddressSuggestion(suggestion) {
                this.editForm.address = suggestion;
                this.addressSuggestions = [];
                this.showAddressSuggestions = false;
            },
            hideAddressSuggestions() {
                setTimeout(() => { this.showAddressSuggestions = false; }, 150);
            },
            addExperience() {
                this.modalMode = 'experience';
                this.editingExperienceIndex = null;
                this.editForm = {
                    title: '',
                    company: '',
                    start_date: '',
                    end_date: '',
                    current: false,
                    description: ''
                };
                this.showEditModal = true;
            },
            editExperience(index) {
                this.modalMode = 'experience';
                this.editingExperienceIndex = index;
                this.editForm = { ...this.profile.experiences[index] };
                this.showEditModal = true;
            },
            deleteExperience(index) {
                this.experienceToDelete = this.profile.experiences[index];
                this.showDeleteModal = true;
            },
            cancelDelete() {
                this.showDeleteModal = false;
                this.experienceToDelete = null;
            },
            confirmDeleteExperience() {
                if (this.experienceToDelete) {
                    this.profile.experiences.splice(this.profile.experiences.indexOf(this.experienceToDelete), 1);
                    this.showNotification('Experience deleted successfully!', 'success');
                    this.showDeleteModal = false;
                    this.experienceToDelete = null;
                }
            },
            saveProfile() {
                const formData = new FormData();
                formData.append('first_name', this.editForm.first_name);
                formData.append('middle_name', this.editForm.middle_name);
                formData.append('last_name', this.editForm.last_name);
                formData.append('contact', this.editForm.contact);
                formData.append('address', this.editForm.address);
                fetch('/admin_profile?action=update', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('Profile updated successfully!', 'success');
                        this.closeEditModal();
                        fetch('admin_profile?action=details')
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
            saveExperience() {
                if (this.editingExperienceIndex !== null) {
                    // Update existing experience
                    this.profile.experiences[this.editingExperienceIndex] = { ...this.editForm };
                    this.showNotification('Experience updated successfully!', 'success');
                } else {
                    // Add new experience
                    this.profile.experiences.push({ ...this.editForm });
                    this.showNotification('Experience added successfully!', 'success');
                }
                this.closeEditModal();
            },
            closeEditModal() {
                this.showEditModal = false;
                this.editingExperienceIndex = null;
                this.editForm = {};
            },
            openPhotoUpload() {
                this.showPhotoModal = true;
                this.newPhotoPreview = null;
                this.newPhotoFile = null;
            },
            handlePhotoUpload(event) {
                const file = event.target.files[0];
                if (file) {
                    this.newPhotoPreview = URL.createObjectURL(file);
                    this.newPhotoFile = file;
                }
            },
            savePhoto() {
                if (!this.newPhotoFile) return;
                const formData = new FormData();
                formData.append('profile_pic', this.newPhotoFile);
                fetch('/admin_profile?action=updatePhoto', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('Profile photo updated successfully!', 'success');
                        this.closePhotoModal();
                        fetch('admin_profile?action=details')
                            .then(res => res.json())
                            .then(data => {
                                if (data.success && data.profile) {
                                    this.profile = data.profile;
                                }
                            });
                    } else {
                        this.showNotification(data.message || 'Failed to update photo.', 'error');
                    }
                })
                .catch(() => {
                    this.showNotification('Failed to update photo.', 'error');
                });
            },
            closePhotoModal() {
                this.showPhotoModal = false;
                this.newPhotoPreview = null;
                this.newPhotoFile = null;
            },
            confirmDeletePhoto() {
                this.showDeletePhotoModal = true;
            },
            deletePhoto() {
                fetch('/admin_profile?action=deletePhoto', { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('Profile photo deleted!', 'success');
                        this.showDeletePhotoModal = false;
                        this.closePhotoModal();
                        this.profile.profile_pic = null;
                    } else {
                        this.showNotification(data.message || 'Failed to delete photo.', 'error');
                    }
                })
                .catch(() => {
                    this.showNotification('Failed to delete photo.', 'error');
                });
            }
        }
    }).mount('#app');