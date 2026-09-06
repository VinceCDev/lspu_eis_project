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
            applicants: [],
            searchQuery: '',
            itemsPerPage: 5,
            currentPage: 1,
            paginationGroupSize: 5,
            showApplicantModal: false,
            selectedApplicant: {},
            showDeleteModal: false,
            applicantToDelete: null,
            actionDropdown: null,
            dropdownPosition: { top: 0, left: 0 },
            filters: {
                status: '',
                appliedFor: '',
                experience: '',
                campus: ''
            },
            uniquePositions: [],
            uniqueExperiences: [],
            uniqueCampuses: [],
            isSuperadmin: false,
            notifications: [],
            notificationId: 0,
            profile: {
                profile_pic: '',
                name: '',
            }
        };
    },
    mounted() {
        this.fetchApplications();
        this.applyDarkMode();
        window.addEventListener('resize', this.handleResize);
        document.addEventListener('click', this.handleClickOutsideDropdown);
        document.addEventListener('click', this.handleClickOutsideProfile);
        this.handleResize(); // Ensure correct state on mount
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
                    this.isSuperadmin = !!data.is_superadmin;
                }
            });
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
    },
    watch: {
        darkMode(val) {
            this.applyDarkMode();
        }
    },
    computed: {
        filteredApplicants() {
            let filtered = this.applicants;
            if (this.searchQuery) {
                filtered = filtered.filter(applicant =>
                    applicant.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    applicant.email.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    applicant.appliedFor.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    applicant.status.toLowerCase().includes(this.searchQuery.toLowerCase())
                );
            }
            if (this.filters.status) {
                filtered = filtered.filter(applicant => applicant.status === this.filters.status);
            }
            if (this.filters.appliedFor) {
                filtered = filtered.filter(applicant => applicant.appliedFor === this.filters.appliedFor);
            }
            if (this.filters.experience) {
                filtered = filtered.filter(applicant => applicant.experience == this.filters.experience);
            }
            if (this.filters.campus) {
                filtered = filtered.filter(applicant => applicant.campus === this.filters.campus);
            }
            return filtered;
        },
        paginatedApplicants() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.filteredApplicants.slice(start, start + this.itemsPerPage);
        },
        totalPages() {
            return Math.ceil(this.filteredApplicants.length / this.itemsPerPage) || 1;
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
        handleResize() {
            this.isMobile = window.innerWidth < 768;
            this.sidebarActive = !this.isMobile;
        },
        toggleSidebar() {
            if (this.isMobile) {
                this.sidebarActive = !this.sidebarActive;
                this.companiesDropdownOpen = false;
                this.alumniDropdownOpen = false;
            }
        },
        handleNavClick() {
            if (this.isMobile) {
                this.sidebarActive = false;
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
        toggleActionDropdown(id, event) {
            if (this.actionDropdown === id) {
                this.actionDropdown = null;
                return;
            }
            this.actionDropdown = id;
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
        filterApplicants() {
            this.currentPage = 1;
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
        async fetchApplications() {
            try {
                const res = await fetch('/admin_applicant?action=list');
                const data = await res.json();
                if (Array.isArray(data.applications)) {
                    this.applicants = data.applications.map(app => ({
                        id: app.application_id,
                        name: app.alumni_name,
                        email: app.email,
                        appliedFor: app.title,
                        appliedDate: app.applied_at,
                        status: app.application_status || 'Pending', // Use application_status instead of job_status
                        experience: app.year_graduated ? (new Date().getFullYear() - parseInt(app.year_graduated)) : '',
                        campus: app.campus_name || '',
                        alumni: app, // full alumni details
                        job: app // full job details
                    }));
                    this.initializeUniqueLists();
                }
            } catch (e) {
                this.showNotification('Failed to fetch applications', 'error');
            }
        },
        viewApplicant(applicant) {
            this.selectedApplicant = applicant;
            this.showApplicantModal = true;
        },
        closeApplicantModal() {
            this.showApplicantModal = false;
            this.selectedApplicant = {};
        },
        async contactAlumni(alumni) {
            try {
                // Redirect to messages page with alumni email as parameter
                const messagesUrl = `admin_message?compose=true&to=${encodeURIComponent(alumni.email)}`;
                window.location.href = messagesUrl;
            } catch (error) {
                console.error('Error redirecting to messages:', error);
                this.showNotification('Error opening message composer', 'error');
            }
        },
        confirmDelete(applicant) {
            this.showDeleteModal = true;
            this.applicantToDelete = applicant;
        },
        confirmDeleteApplicant() {
            if (this.applicantToDelete) {
                this.deleteApplicant(this.applicantToDelete);
                this.applicantToDelete = null;
            }
            this.showDeleteModal = false;
        },
        async deleteApplicant(applicant) {
            try {
                const res = await fetch('/admin_applicant?action=destroy', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ application_id: applicant.id })
                });
                const data = await res.json();
                if (data.success) {
                    this.applicants = this.applicants.filter(a => a.id !== applicant.id);
                    this.showNotification('Applicant deleted successfully!', 'success');
                } else {
                    this.showNotification(data.message || 'Failed to delete applicant.', 'error');
                }
            } catch (e) {
                this.showNotification('Failed to delete applicant.', 'error');
            }
        },
        formatDate(date) {
            return new Date(date).toLocaleDateString();
        },
        getCertificateUrl(filename) {
            if (!filename) return null;
            const allowedExtensions = ['.pdf', '.jpg', '.jpeg', '.png', '.gif'];
            const ext = filename.slice(filename.lastIndexOf('.')).toLowerCase();
            if (!allowedExtensions.includes(ext)) {
                console.warn('Invalid certificate extension:', filename);
                return null;
            }
            return `uploads/certificates/${encodeURIComponent(filename)}`;
        },
        confirmLogout() {
            this.showLogoutModal = true;
        },
        logout() {
            window.location.href = 'logout';
        },
        initializeUniqueLists() {
            this.uniquePositions = [...new Set(this.applicants.map(a => a.appliedFor))];
            this.uniqueExperiences = [...new Set(this.applicants.map(a => a.experience))].sort((a, b) => a - b);
            this.uniqueCampuses = [...new Set(this.applicants.map(a => a.campus).filter(Boolean))].sort();
        },
        exportToExcel() {
            let csv = 'Name,Email,Applied For,Applied Date,Experience,Status\n';
            this.filteredApplicants.forEach(a => {
                csv += `${a.name},${a.email},${a.appliedFor},${a.appliedDate},${a.experience},${a.status}\n`;
            });
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'applicants.csv';
            a.click();
            window.URL.revokeObjectURL(url);
            this.showNotification('Applicants exported to Excel successfully!', 'success');
        },
        async exportToPDF() {
            try {
                await LibLoader.ensureJsPDFAutoTable();
                const doc = new window.jspdf.jsPDF();
                const tableColumn = ["Name", "Email", "Applied For", "Applied Date", "Experience", "Status"];
                const tableRows = [];
                this.filteredApplicants.forEach(a => {
                    tableRows.push([
                        a.name,
                        a.email,
                        a.appliedFor,
                        a.appliedDate,
                        a.experience,
                        a.status
                    ]);
                });
                doc.autoTable({
                    head: [tableColumn],
                    body: tableRows
                });
                doc.save("applicants.pdf");
                this.showNotification('Applicants exported to PDF successfully!', 'success');
            } catch (error) {
                this.showNotification('Failed to export applicants to PDF.', 'error');
            }
        },
        showNotification(message, type = 'success') {
            const id = this.notificationId++;
            this.notifications.push({ id, type, message });
            setTimeout(() => this.removeNotification(id), 3000);
        },
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        }
    }
}).mount('#app');