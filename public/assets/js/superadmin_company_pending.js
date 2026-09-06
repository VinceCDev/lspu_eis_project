const { createApp } = Vue;
createApp({
    data() {
        return {
            sidebarActive: window.innerWidth >= 768,
            companiesDropdownOpen: true,
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
            actionDropdown: null,
            dropdownPosition: { top: 0, left: 0 },
            searchQuery: '',
            filters: {
                industry_type: '',
                nature_of_business: '',
                accreditation_status: ''
            },
            itemsPerPage: 5,
            currentPage: 1,
            paginationGroupSize: 5,
            showCompanyModal: false,
            selectedCompany: null,
            companyForm: {},
            uniqueIndustryTypes: [],
            uniqueNatureOfBusiness: [],
            uniqueAccreditationStatus: ['Approved', 'Pending'],
            showDeleteModal: false,
            employerToDelete: null,
            deletingEmployer: false,
            showViewModal: false,
            employers: [],
            profile: {
                profile_pic: '',
                name: '',
                email: ''
            }
        }
    },
    mounted() {
        this.applyDarkMode();
        window.addEventListener('resize', this.handleResize);
        document.addEventListener('click', this.handleClickOutsideDropdown);
        document.addEventListener('click', this.handleClickOutsideProfile);
        this.fetchEmployers();
        // this.fetchProfile(); // Removed: Fetch profile data
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
    computed: {
        filteredEmployers() {
            let filtered = Array.isArray(this.employers) ? this.employers : [];
            if (this.searchQuery) {
                filtered = filtered.filter(company =>
                    (company.company_name || '').toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    (company.company_location || '').toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    (company.contact_email || '').toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    (company.industry_type || '').toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    (company.nature_of_business || '').toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    (company.status || '').toLowerCase().includes(this.searchQuery.toLowerCase())
                );
            }
            if (this.filters.industry_type) {
                filtered = filtered.filter(company => company.industry_type === this.filters.industry_type);
            }
            if (this.filters.nature_of_business) {
                filtered = filtered.filter(company => company.nature_of_business === this.filters.nature_of_business);
            }
            if (this.filters.accreditation_status) {
                filtered = filtered.filter(company => company.status === this.filters.accreditation_status);
            }
            return filtered;
        },
        paginatedEmployers() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.filteredEmployers.slice(start, end);
        },
        totalPages() {
            return Math.ceil((this.filteredEmployers ? this.filteredEmployers.length : 0) / this.itemsPerPage) || 1;
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
    methods: {
        toggleSidebar() {
            this.sidebarActive = !this.sidebarActive;
            this.companiesDropdownOpen = true;
            this.alumniDropdownOpen = false;
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
        filterCompanies() {
            this.currentPage = 1;
        },
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
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
        toggleActionDropdown(companyId, event) {
            if (this.actionDropdown === companyId) {
                this.actionDropdown = null;
                return;
            }
            this.actionDropdown = companyId;
            this.$nextTick(() => {
                const btn = event.currentTarget;
                const rect = btn.getBoundingClientRect();
                this.dropdownPosition = { top: rect.bottom + 4, left: rect.right - 128 };
            });
        },
        handleClickOutsideDropdown(event) {
            if (this.actionDropdown !== null && !event.target.closest('.relative.inline-block.text-left') && !event.target.closest('.teleported-action-dropdown')) {
                this.actionDropdown = null;
            }
        },
        openAddModal() {
            this.showNotification('Add Company functionality is currently disabled.', 'info');
        },
        confirmDeleteModal(employer) {
            // Opening this used to leave any still-open View modal (z-[210])
            // sitting on top of this one (z-[200]) — clicks landed on the
            // wrong modal's backdrop instead of Cancel/Delete.
            this.showViewModal = false;
            this.employerToDelete = employer;
            this.showDeleteModal = true;
        },
        confirmDeleteEmployer() {
            if (!this.employerToDelete || this.deletingEmployer) return;
            this.deletingEmployer = true;
            fetch('/superadmin_company_pending?action=destroy', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ company_id: this.employerToDelete.user_id })
            })
                .then(res => res.json())
                .then(data => {
                    this.showNotification(data.message || (data.success ? 'Employer deleted successfully.' : 'Failed to delete.'), data.success ? 'success' : 'error');
                    if (data.success) {
                        this.showDeleteModal = false;
                        this.employerToDelete = null;
                        this.fetchEmployers();
                    }
                })
                .catch(() => {
                    this.showNotification('Failed to delete employer. Please try again.', 'error');
                })
                .finally(() => {
                    this.deletingEmployer = false;
                });
        },
        showNotification(message, type = 'success') {
            const id = this.notificationId++;
            this.notifications.push({ id, type, message });
            setTimeout(() => this.removeNotification(id), 3000);
        },
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        },
        initializeUniqueLists() {
            this.uniqueIndustryTypes = [...new Set(this.employers.map(c => c.industry_type).filter(Boolean))];
            this.uniqueNatureOfBusiness = [...new Set(this.employers.map(c => c.nature_of_business).filter(Boolean))];
        },
        handleNavClick() {
            if (this.isMobile) {
                this.sidebarActive = false;
            }
        },
        toggleProfileDropdown() {
            this.profileDropdownOpen = !this.profileDropdownOpen;
        },
        handleClickOutsideProfile(e) {
            if (this.profileDropdownOpen && !e.target.closest('.profile-dropdown-wrapper')) {
                this.profileDropdownOpen = false;
            }
        },
        confirmLogout() {
            this.showLogoutModal = true;
        },
        logout() {
            window.location.href = 'logout';
        },
        async exportToPDF() {
            await LibLoader.ensureJsPDFAutoTable();
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const columns = [
                'Name of Company',
                'Location',
                'Contact Email',
                'Industry Type',
                'Nature of Business',
                'Status'
            ];
            const rows = this.filteredCompanies.map(company => [
                company.company_name,
                company.company_location,
                company.contact_email,
                company.industry_type,
                company.nature_of_business,
                company.status
            ]);
            doc.autoTable({ head: [columns], body: rows });
            doc.save('companies.pdf');
            this.showNotification('PDF generated successfully!', 'success');
        },
        async exportToExcel() {
            await LibLoader.ensureXLSX();
            const wb = XLSX.utils.book_new();
            const wsData = [
                [
                    'Name of Company',
                    'Location',
                    'Contact Email',
                    'Industry Type',
                    'Nature of Business',
                    'Status'
                ],
                ...this.filteredCompanies.map(company => [
                    company.company_name,
                    company.company_location,
                    company.contact_email,
                    company.industry_type,
                    company.nature_of_business,
                    company.status
                ])
            ];
            const ws = XLSX.utils.aoa_to_sheet(wsData);
            XLSX.utils.book_append_sheet(wb, ws, 'Companies');
            XLSX.writeFile(wb, 'companies.xlsx');
            this.showNotification('Excel file generated successfully!', 'success');
        },
        fetchEmployers() {
            fetch('/superadmin_company_pending?action=pendingList')
                .then(res => res.text())
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        this.employers = data;
                        this.initializeUniqueLists();
                    } catch (e) {
                        this.showNotification('Error parsing employer data. See console for details.', 'error');
                        console.error('JSON parse error:', e, text);
                    }
                })
                .catch(err => {
                    this.showNotification('Fetch error: ' + err, 'error');
                    console.error('Fetch error:', err);
                });
        },
        approveEmployer(employer) {
            fetch('/superadmin_company_pending?action=approve', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: employer.user_id })
            })
            .then(async res => {
                let text = await res.text();
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        this.showNotification('Employer approved!', 'success');
                        this.fetchEmployers();
                    } else {
                        this.showNotification(data.message || 'Failed to approve.', 'error');
                    }
                } catch (e) {
                    this.showNotification('Unexpected server response. See console.', 'error');
                    console.error('Approve error:', text);
                }
            });
        },
        viewEmployer(employer) {
            // Show modal with employer details
            let docArr = [];
            if (employer.document_file) {
                // Try to extract original filename (after first underscore)
                let original = employer.document_file.split('_').slice(1).join('_') || employer.document_file;
                let docUrl = '/uploads/documents/' + employer.document_file.replace(/^.*[\\\/]/, '');
                docArr = [{
                    name: original,
                    url: docUrl
                }];
            }
            let logoUrl = employer.company_logo
                ? '/uploads/logos/' + employer.company_logo.replace(/^.*[\\\/]/, '')
                : '/assets/images/logo.png';
            this.viewedEmployer = {
                ...employer,
                logo: logoUrl,
                documents: docArr
            };
            this.showViewModal = true;
        }
    }
}).mount('#app');