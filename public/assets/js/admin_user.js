const { createApp } = Vue;
    createApp({
        data() {
            return {
                accounts: [],
                search: '',
                showViewModal: false,
                viewedAccount: {},
                profile: {
                    profile_pic: '',
                    name: '',
                },
                paginationGroupSize: 5,
                profileDropdownOpen: false,
                sidebarActive: window.innerWidth >= 768,
                companiesDropdownOpen: false,
            systemDropdownOpen: false,
                alumniDropdownOpen: false,
                darkMode: localStorage.getItem('darkMode') === 'true' || 
                     (localStorage.getItem('darkMode') === null && 
                      window.matchMedia('(prefers-color-scheme: dark)').matches),
                showLogoutModal: false,
                isMobile: window.innerWidth < 768,
                notifications: [],
                notificationId: 0,
                showFilterDropdown: false,
                filterRole: '',
                filterStatus: '',
                currentPage: 1,
                pageSize: 5,
                actionDropdown: null, // For action dropdown
                dropdownPosition: { top: 0, left: 0 },
                showAdminModal: false,
                adminModalMode: 'add', // 'add' or 'edit'
                adminForm: {
                    user_id: null,
                    user_role: '',
                    first_name: '',
                    middle_name: '',
                    last_name: '',
                    company_name: '',
                    industry_type: '',
                    email: '',
                    status: 'Active',
                    profile_pic: null,
                    campus_id: '',
                },
                currentProfilePicName: '',
                showDeleteModal: false,
                showDeactivateModal: false,
                accountToDeactivate: null,
                deactivateNotes: '',
                deactivating: false,
                roleToAdd: '',
                roleSelectionStep: false,
                campuses: [],
                isSuperadmin: false,
                currentCampusId: null,
            };
        },
        computed: {
            filteredAccounts() {
                let filtered = this.accounts;
                if (this.filterRole) {
                    filtered = filtered.filter(a => a.user_role === this.filterRole);
                }
                if (this.filterStatus) {
                    filtered = filtered.filter(a => a.status === this.filterStatus);
                }
                if (this.search) {
                    const s = this.search.toLowerCase();
                    filtered = filtered.filter(a =>
                        a.name.toLowerCase().includes(s) ||
                        a.email.toLowerCase().includes(s) ||
                        (a.user_role && a.user_role.toLowerCase().includes(s))
                    );
                }
                return filtered;
            },
            paginatedAccounts() {
                const start = (this.currentPage - 1) * this.pageSize;
                return this.filteredAccounts.slice(start, start + this.pageSize);
            },
            totalPages() {
                return Math.ceil(this.filteredAccounts.length / this.pageSize) || 1;
            },
            startItem() {
                return (this.currentPage - 1) * this.pageSize;
            },
            endItem() {
                return Math.min(this.startItem + this.pageSize, this.filteredAccounts.length);
            },
            paginationGroup() {
                const groupSize = this.paginationGroupSize;
                const currentGroup = Math.ceil(this.currentPage / groupSize);
                const startPage = (currentGroup - 1) * groupSize + 1;
                const endPage = Math.min(startPage + groupSize - 1, this.totalPages);
                
                const pages = [];
                for (let i = startPage; i <= endPage; i++) {
                    pages.push(i);
                }
                
                return {
                    pages,
                    hasPrevGroup: startPage > 1,
                    hasNextGroup: endPage < this.totalPages,
                    prevGroupStart: startPage - groupSize,
                    nextGroupStart: startPage + groupSize
                };
            }
        },
        mounted() {
            this.applyDarkMode();
            window.addEventListener('resize', this.handleResize);
            document.addEventListener('click', this.handleClickOutsideDropdown);
            document.addEventListener('click', this.handleClickOutsideProfile);
            fetch('/admin_user?action=list')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.accounts) {
                        this.accounts = data.accounts;
                    }
                });
            fetch('admin_profile?action=details')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.profile) {
                        this.profile = data.profile;
                    }
                });
            fetch('/admin_user?action=campuses')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.campuses = data.campuses || [];
                        this.isSuperadmin = !!data.is_superadmin;
                        this.currentCampusId = data.current_campus_id;
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
            prevPage() {
                if (this.currentPage > 1) this.currentPage--;
            },
            nextPage() {
                if (this.currentPage < this.totalPages) this.currentPage++;
            },
            goToPage(page) {
                this.currentPage = page;
            },
            goToPrevGroup() {
                if (this.paginationGroup.hasPrevGroup) {
                    this.currentPage = this.paginationGroup.prevGroupStart;
                }
            },
            
            goToNextGroup() {
                if (this.paginationGroup.hasNextGroup) {
                    this.currentPage = this.paginationGroup.nextGroupStart;
                }
            },
            addAdmin() {
                this.adminModalMode = 'add';
                this.roleToAdd = '';
                this.roleSelectionStep = true;
                this.adminForm = {
                    user_id: null,
                    user_role: '',
                    first_name: '',
                    middle_name: '',
                    last_name: '',
                    company_name: '',
                    industry_type: '',
                    email: '',
                    status: 'Active',
                    profile_pic: null,
                    campus_id: this.isSuperadmin ? '' : this.currentCampusId,
                };
                this.currentProfilePicName = '';
                this.showAdminModal = true;
            },
            openEditModal(account) {
                this.adminModalMode = 'edit';
                if (account.user_role === 'employer') {
                    this.adminForm = {
                        user_id: account.user_id,
                        user_role: account.user_role,
                        company_name: account.company_name || '',
                        industry_type: account.industry_type || '',
                        email: account.email || '',
                        status: account.status || 'Active',
                        profile_pic: null,
                    };
                } else {
                    this.adminForm = {
                        user_id: account.user_id,
                        user_role: account.user_role,
                        first_name: account.first_name || '',
                        middle_name: account.middle_name || '',
                        last_name: account.last_name || '',
                        email: account.email || '',
                        status: account.status || 'Active',
                        profile_pic: null,
                        campus_id: account.campus_id || (this.isSuperadmin ? '' : this.currentCampusId),
                    };
                }
                // Native file inputs can't be pre-filled with an existing
                // value (browsers block that) — show the saved file's
                // name/path as plain text instead so the field isn't blank
                // for an account that already has a picture.
                this.currentProfilePicName = account.profile_pic || '';
                this.showAdminModal = true;
            },
            closeAdminModal() {
                this.showAdminModal = false;
                this.adminModalMode = 'add';
                this.roleToAdd = '';
                this.roleSelectionStep = false;
                this.adminForm = {
                    user_id: null,
                    user_role: '',
                    first_name: '',
                    middle_name: '',
                    last_name: '',
                    company_name: '',
                    industry_type: '',
                    email: '',
                    status: 'Active',
                    profile_pic: null,
                    campus_id: '',
                };
                this.currentProfilePicName = '';
            },
            addAdminSubmit() {
                this.submitAccountForm('add');
            },
            updateAdmin() {
                this.submitAccountForm('edit');
            },
            submitAccountForm(mode) {
                const formData = new FormData();
                if (mode === 'edit') {
                    formData.append('user_id', this.adminForm.user_id);
                    formData.append('user_role', this.adminForm.user_role);
                } else {
                    formData.append('user_role', this.adminForm.user_role);
                }
                formData.append('first_name', this.adminForm.first_name || '');
                formData.append('middle_name', this.adminForm.middle_name || '');
                formData.append('last_name', this.adminForm.last_name || '');
                formData.append('company_name', this.adminForm.company_name || '');
                formData.append('industry_type', this.adminForm.industry_type || '');
                formData.append('email', this.adminForm.email || '');
                formData.append('status', this.adminForm.status || 'Active');
                if (this.adminForm.user_role === 'admin') {
                    formData.append('campus_id', this.isSuperadmin ? (this.adminForm.campus_id || '') : this.currentCampusId);
                }
                if (this.adminForm.profile_pic) {
                    formData.append('profile_pic', this.adminForm.profile_pic);
                }
                let url = '';
                if (mode === 'add') {
                    url = '/admin_user?action=store';
                } else {
                    url = '/admin_user?action=update';
                }
                fetch(url, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification(mode === 'add' ? 'Account created successfully!' : 'Account updated successfully!', 'success');
                        this.closeAdminModal();
                        this.fetchAccounts();
                    } else {
                        this.showNotification(data.message || (mode === 'add' ? 'Failed to create account.' : 'Failed to update account.'), 'error');
                    }
                })
                .catch(error => {
                    this.showNotification('Error: ' + error.message, 'error');
                });
            },
            confirmDelete(account) {
                this.viewedAccount = account; // Set for view modal
                this.showDeleteModal = true;
            },
            deleteAdmin() {
                if (!this.viewedAccount.user_id) return;
                fetch('/admin_user?action=destroy', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `user_id=${this.viewedAccount.user_id}&user_role=${this.viewedAccount.user_role}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('Account deleted successfully!', 'success');
                        this.showDeleteModal = false;
                        this.fetchAccounts();
                    } else {
                        this.showNotification(data.message || 'Failed to delete account.', 'error');
                    }
                })
                .catch(error => {
                    this.showNotification('Error deleting account: ' + error.message, 'error');
                });
            },
            confirmDeactivateModal(account) {
                this.showViewModal = false;
                this.accountToDeactivate = account;
                this.deactivateNotes = '';
                this.showDeactivateModal = true;
            },
            deactivateAccount() {
                if (!this.accountToDeactivate || !this.deactivateNotes.trim() || this.deactivating) return;
                this.deactivating = true;
                fetch('/admin_user?action=deactivate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        user_id: this.accountToDeactivate.user_id,
                        user_role: this.accountToDeactivate.user_role,
                        notes: this.deactivateNotes.trim(),
                    })
                })
                .then(res => res.json())
                .then(data => {
                    this.showNotification(data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        this.showDeactivateModal = false;
                        this.accountToDeactivate = null;
                        this.fetchAccounts();
                    }
                })
                .catch(() => {
                    this.showNotification('Failed to deactivate account. Please try again.', 'error');
                })
                .finally(() => {
                    this.deactivating = false;
                });
            },
            resetAccountPassword(account) {
                if (account.resettingPassword) return;
                account.resettingPassword = true;
                fetch('/admin_user?action=resetPassword', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: account.user_id, user_role: account.user_role })
                })
                .then(res => res.json())
                .then(data => {
                    this.showNotification(data.message, data.success ? 'success' : 'error');
                })
                .catch(() => {
                    this.showNotification('Failed to send reset link. Please try again.', 'error');
                })
                .finally(() => {
                    account.resettingPassword = false;
                });
            },
            viewAccount(account) {
                this.viewedAccount = account;
                this.showViewModal = true;
            },
            toggleActionDropdown(userId) {
                this.actionDropdown = this.actionDropdown === userId ? null : userId;
            },
            handleClickOutsideDropdown(event) {
                if (this.actionDropdown !== null && !event.target.closest('.relative.inline-block.text-left')) {
                    this.actionDropdown = null;
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
            fetchAccounts() {
                fetch('/admin_user?action=list')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.accounts) {
                            this.accounts = data.accounts;
                        }
                    });
            },
            handleAdminPhotoUpload(event) {
                this.adminForm.profile_pic = event.target.files[0];
                this.currentProfilePicName = this.adminForm.profile_pic ? this.adminForm.profile_pic.name : '';
            },
            getProfilePic(account) {
                if (!account.profile_pic) return '/assets/images/logo.png';
                if (account.user_role === 'alumni') {
                    if (account.profile_pic.startsWith('uploads/')) {
                        return account.profile_pic;
                    } else {
                        return 'uploads/profile_picture/' + account.profile_pic;
                    }
                }
                if (account.user_role === 'employer') {
                    if (account.profile_pic.startsWith('uploads/')) {
                        return account.profile_pic;
                    } else {
                        return 'uploads/logos/' + account.profile_pic;
                    }
                }
                if (account.profile_pic.startsWith('uploads/')) {
                    return account.profile_pic;
                }
                return 'uploads/profile_picture/' + account.profile_pic;
            },
            showNotification(message, type = 'success') {
                const id = this.notificationId++;
                this.notifications.push({ id, type, message });
                setTimeout(() => this.removeNotification(id), 3000);
            },
            removeNotification(id) {
                this.notifications = this.notifications.filter(n => n.id !== id);
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
            }
        }
    }).mount('#app');