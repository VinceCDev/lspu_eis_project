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
                experience: ''
            },
            uniquePositions: [],
            uniqueExperiences: [],
            notifications: [],
            notificationId: 0,
            employerProfile: {
                company_name: '',
                company_logo: ''
            },
            activePage: 'applicants',
        };
    },
    mounted() {
        this.fetchApplications();
        // Use localStorage and system preference for dark mode
        this.applyDarkMode();
        window.addEventListener('resize', this.handleResize);
        this.handleResize();
        document.addEventListener('click', this.handleClickOutsideDropdown);
        document.addEventListener('click', this.handleClickOutsideProfile);
        // Listen for system dark mode changes
        fetch('employer_profile?action=details')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.profile) {
                    this.employerProfile = data.profile;
                }
            });
        const path = document.location.pathname;
        if (path.endsWith('employer_dashboard.php')) this.activePage = 'dashboard';
        else if (path.endsWith('employer_jobposting.php')) this.activePage = 'jobs';
        else if (path.endsWith('employer_applicants.php')) this.activePage = 'applicants';
        else if (path.endsWith('employer_messages.php')) this.activePage = 'messages';
        else if (path.endsWith('employer_profile.php')) this.activePage = 'profile';
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
        confirmLogout() {
            this.showLogoutModal = true;
        },
        logout() {
            window.location.href = 'logout';
        },
        toggleSidebar() {
            if (this.isMobile) {
                this.sidebarActive = !this.sidebarActive;
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
            // The menu is teleported to <body> (see the template) so the
            // table's own overflow-x-auto — which, per the CSS spec, also
            // clips the Y axis the moment X isn't "visible" — can't cut it
            // off or force a scroll to see the rest of it. Since it's no
            // longer a descendant of the button, position it manually from
            // the button's own on-screen location instead of relying on
            // absolute positioning within a relative ancestor.
            this.$nextTick(() => {
                const btn = event.currentTarget;
                const rect = btn.getBoundingClientRect();
                this.dropdownPosition = { top: rect.bottom + 4, left: rect.right - 160 };
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
                // Replace with actual endpoint for employer's applicants
                const res = await fetch('/employer_applicants?action=list');
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
                        company_name: app.company_name,
                        alumni: app, // full alumni details
                        job: app // full job details
                    }));
                    this.initializeUniqueLists();
                }
            } catch (e) {
                this.showNotification('Failed to fetch applications', 'error');
            }
        },
        // In the viewApplicant method, update the skills processing:
        viewApplicant(applicant) {
            this.selectedApplicant = applicant;
            this.showApplicantModal = true;
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
        closeApplicantModal() {
            this.showApplicantModal = false;
            this.selectedApplicant = {};
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
        deleteApplicant(applicant) {
            this.applicants = this.applicants.filter(a => a.id !== applicant.id);
            this.showNotification('Applicant deleted successfully!', 'success');
        },
        formatDate(date) {
            return new Date(date).toLocaleDateString();
        },
        initializeUniqueLists() {
            this.uniquePositions = [...new Set(this.applicants.map(a => a.appliedFor))];
            this.uniqueExperiences = [...new Set(this.applicants.map(a => a.experience))].sort((a, b) => a - b);
        },
        exportToExcel() {
            let csv = 'Name,Email,Applied For,Applied Date,Experience,Status,Company\n';
            this.filteredApplicants.forEach(a => {
                csv += `${a.name},${a.email},${a.appliedFor},${a.appliedDate},${a.experience},${a.status},${a.company_name}\n`;
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
                const tableColumn = ["Name", "Email", "Applied For", "Applied Date", "Experience", "Status", "Company"];
                const tableRows = [];
                this.filteredApplicants.forEach(a => {
                    tableRows.push([
                        a.name,
                        a.email,
                        a.appliedFor,
                        a.appliedDate,
                        a.experience,
                        a.status,
                        a.company_name
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
        },
        async updateApplicantStatus(applicant, status) {
            try {
                const res = await fetch('/employer_applicants?action=updateStatus', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ application_id: applicant.id, status })
                });
                const data = await res.json();
                if (data.success) {
                    this.showNotification(`Applicant status updated to ${status}.`, 'success');
                    this.fetchApplications();
                } else {
                    this.showNotification(data.message || 'Failed to update status.', 'error');
                }
            } catch (e) {
                this.showNotification('Failed to update status.', 'error');
            }
        },
        async contactAlumni(alumni) {
            try {
                // Redirect to messages page with alumni email as parameter
                const messagesUrl = `employer_messages.php?compose=true&to=${encodeURIComponent(alumni.email)}`;
                window.location.href = messagesUrl;
            } catch (error) {
                console.error('Error redirecting to messages:', error);
                this.showNotification('Error opening message composer', 'error');
            }
        }
    }
}).mount('#app');