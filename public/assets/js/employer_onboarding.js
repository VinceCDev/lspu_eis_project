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
            jobOptions: [],
            hiredApplicants: [],
            onboardingData: [],
            checklists: [],
            searchQuery: '',
            showChecklistModal: false,
            showOnboardingModal: false,
            showContactModal: false,
            selectedContactApplicant: null,
            selectedApplicant: null,
            selectedChecklist: null,
            newChecklist: {
                title: '',
                description: '',
                items: [
                    { text: 'Send welcome email', is_required: true, order: 1 },
                    { text: 'Provide login credentials', is_required: true, order: 2 },
                    { text: 'Schedule orientation', is_required: true, order: 3 }
                ]
            },
            itemsPerPage: 5,
            currentPage: 1,
            paginationGroupSize: 5,
            showResourceModal: false,
            modalMode: 'add',
            selectedResource: null,
            showDeleteModal: false,
            actionDropdown: null,
            dropdownPosition: { top: 0, left: 0 },
            showChecklistSelectionModal: false,
            selectedApplicantForChecklist: null,
            selectedChecklistId: null,
            checklistModalMode: 'create',
            editingChecklistId: null,
            checklistLibraryView: 'list',
            showDeleteChecklistModal: false,
            checklistToDelete: null,
            checklistItems: [],
            notifications: [],
            notificationId: 0,
            showAdvancedFilters: false,
            employerProfile: {
                company_name: '',
                company_logo: ''
            },
            onboardingFilters: {
                status: '',
                progress: '',
                checklist: ''
            }
        }
    },
    mounted() {
        this.applyDarkMode();
        this.fetchEmployerProfile();
        this.fetchJobOptions();
        this.fetchHiredApplicants(); // Changed from fetchOnboardingData
        document.addEventListener('click', this.handleClickOutsideExport);
        document.addEventListener('click', this.handleClickOutsideDropdown);
        document.addEventListener('click', this.handleClickOutsideProfile);
        window.addEventListener('resize', this.handleResize);
        const path = document.location.pathname;
        if (path.endsWith('employer_dashboard.php')) this.activePage = 'dashboard';
        else if (path.endsWith('employer_jobposting.php')) this.activePage = 'jobs';
        else if (path.endsWith('employer_resources.php')) this.activePage = 'job_resources';
        else if (path.endsWith('employer_applicants.php')) this.activePage = 'applicants';
        else if (path.endsWith('employer_messages.php')) this.activePage = 'messages';
        else if (path.endsWith('employer_profile.php')) this.activePage = 'profile';
        else if (path.endsWith('employer_onboarding.php')) this.activePage = 'onboarding';
    },
    watch: {
        darkMode(val) {
            this.applyDarkMode();
        }
    },
    computed: {
        filteredApplicants() {
            let filtered = this.hiredApplicants;
            
            // Search filter
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(applicant =>
                    applicant.alumni_name.toLowerCase().includes(query) ||
                    applicant.title.toLowerCase().includes(query) ||
                    applicant.email.toLowerCase().includes(query)
                );
            }
            
            // Status filter
            if (this.onboardingFilters.status) {
                filtered = filtered.filter(applicant => 
                    applicant.status === this.onboardingFilters.status
                );
            }
            
            // Progress filter
            if (this.onboardingFilters.progress) {
                filtered = filtered.filter(applicant => {
                    const progress = applicant.progress;
                    switch (this.onboardingFilters.progress) {
                        case '0-25': return progress >= 0 && progress <= 25;
                        case '26-50': return progress >= 26 && progress <= 50;
                        case '51-75': return progress >= 51 && progress <= 75;
                        case '76-99': return progress >= 76 && progress <= 99;
                        case '100': return progress === 100;
                        default: return true;
                    }
                });
            }
            
            // Checklist filter
            if (this.onboardingFilters.checklist) {
                filtered = filtered.filter(applicant => {
                    if (this.onboardingFilters.checklist === 'unassigned') {
                        return !applicant.checklist_name || 
                               applicant.checklist_name === 'Not assigned' || 
                               applicant.checklist_name === 'Unknown Checklist';
                    } else {
                        // Find the checklist by ID and compare titles
                        const checklist = this.checklists.find(c => c.id == this.onboardingFilters.checklist);
                        return checklist && applicant.checklist_name === checklist.title;
                    }
                });
            }
            
            return filtered;
        },
        paginatedApplicants() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.filteredApplicants.slice(start, end);
        },
        totalPages() {
            return Math.ceil(this.filteredApplicants.length / this.itemsPerPage);
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
        },
        overallProgress() {
            if (this.onboardingData.length === 0) return 0;
            const total = this.onboardingData.reduce((sum, item) => sum + item.completion_percentage, 0);
            return Math.round(total / this.onboardingData.length);
        },
        inProgressCount() {
            return this.onboardingData.filter(item => item.status === 'in_progress').length;
        },
        completedCount() {
            return this.onboardingData.filter(item => item.status === 'completed').length;
        },
        pendingCount() {
            return this.onboardingData.filter(item => item.status === 'pending').length;
        }
    },
    methods: {
        // Navigation and UI methods
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
        
        // Hired Applicants methods
        async fetchHiredApplicants() {
            try {
                const response = await fetch('employer_onboarding?action=hired');
                const data = await response.json();
                
                if (data.success) {
                    this.hiredApplicants = data.hired_applicants;
                    
                    // Set default values for each applicant
                    this.hiredApplicants.forEach(applicant => {
                        applicant.progress = 0;
                        applicant.status = 'pending';
                        applicant.checklist_name = 'Not assigned';
                        applicant.name = applicant.alumni_name;
                        applicant.position = applicant.title;
                    });
                    
                    // Now fetch onboarding data to update status and progress
                    await this.fetchOnboardingData();
                } else {
                    this.showNotification(data.message || 'Failed to fetch hired applicants', 'error');
                }
            } catch (error) {
                console.error('Error fetching hired applicants:', error);
                this.showNotification('Error fetching hired applicants', 'error');
            }
        },

        openContactModal(applicant) {
            this.selectedContactApplicant = applicant;
            this.showContactModal = true;
            // Close any other dropdowns
            this.actionDropdown = null;
        },
        
        contactViaGmail(applicant) {
            if (!applicant) return;
            
            const companyName = this.employerProfile.company_name || 'Our Company';
            const subject = `Regarding your application at ${companyName}`;
            const body = `Hello ${applicant.alumni_name},\n\nI hope this message finds you well. `;
            
            const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(applicant.email)}&su=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
            window.open(gmailUrl, '_blank');
            this.showContactModal = false;
        },
        
        contactViaMessages(applicant) {
            if (!applicant) return;
            
            // Redirect to messages page with the applicant pre-selected
            window.location.href = `employer_messages.php?applicant_id=${applicant.application_id}`;
            this.showContactModal = false;
        },
        
        async fetchOnboardingData() {
            try {
                const response = await fetch('employer_onboarding?action=data');
                const data = await response.json();
                
                if (data.success) {
                    this.checklists = data.checklists;
                    this.onboardingData = data.onboarding_data;
                    
                    // Map onboarding data to applicants
                    this.hiredApplicants.forEach(applicant => {
                        const onboarding = this.onboardingData.find(o => o.application_id === applicant.application_id);
                        if (onboarding) {
                            applicant.progress = onboarding.completion_percentage || 0;
                            applicant.status = onboarding.status || 'pending';
                            applicant.checklist_name = this.getChecklistName(onboarding.checklist_id);
                            applicant.checklist_id = onboarding.checklist_id;
                            applicant.onboarding_id = onboarding.id;
                            applicant.onboarding_notes = onboarding.notes || '';
                        } else {
                            // Set default values if no onboarding record exists
                            applicant.progress = 0;
                            applicant.status = 'pending';
                            applicant.checklist_name = 'Not assigned';
                            applicant.checklist_id = null;
                            applicant.onboarding_id = null;
                            applicant.onboarding_notes = '';
                        }
                    });
                }
            } catch (error) {
                console.error('Error fetching onboarding data:', error);
            }
        },
        
        getChecklistName(checklistId) {
            const checklist = this.checklists.find(c => c.id === checklistId);
            return checklist ? checklist.title : 'Unknown Checklist';
        },
        // Opens the checklist library (list of all saved checklists, with
        // edit/delete per row). This is the only place checklists are
        // created or edited now — the per-applicant flow just picks one.
        async showChecklistManager() {
            this.checklistLibraryView = 'list';
            await this.fetchChecklistItems();
            this.showChecklistModal = true;
        },
        closeChecklistManagerModal() {
            this.showChecklistModal = false;
            this.checklistLibraryView = 'list';
            this.checklistModalMode = 'create';
            this.editingChecklistId = null;
        },
        openCreateChecklistInLibrary() {
            this.checklistModalMode = 'create';
            this.editingChecklistId = null;
            this.selectedApplicantForChecklist = null;
            this.newChecklist = {
                title: '',
                description: '',
                items: [
                    { text: '', is_required: true, order: 1 }
                ]
            };
            this.checklistLibraryView = 'form';
        },
        editChecklistInLibrary(checklist) {
            this.checklistModalMode = 'edit';
            this.editingChecklistId = checklist.id;
            this.selectedApplicantForChecklist = null;
            this.newChecklist = {
                title: checklist.title,
                description: checklist.description || '',
                items: this.getChecklistItems(checklist.id).map(item => ({
                    id: item.id,
                    text: item.item_text,
                    is_required: !!item.is_required,
                    order: item.item_order
                }))
            };
            this.checklistLibraryView = 'form';
        },
        confirmDeleteChecklist(checklist) {
            this.checklistToDelete = checklist;
            this.showDeleteChecklistModal = true;
        },
        async deleteChecklist() {
            if (!this.checklistToDelete) return;
            try {
                const response = await fetch('employer_onboarding?action=deleteChecklist', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ checklist_id: this.checklistToDelete.id })
                });
                const data = await response.json();
                if (data.success) {
                    this.showNotification('Checklist deleted', 'success');
                    await this.fetchOnboardingData();
                } else {
                    this.showNotification(data.message || 'Failed to delete checklist', 'error');
                }
            } catch (error) {
                console.error('Error deleting checklist:', error);
                this.showNotification('Error deleting checklist', 'error');
            } finally {
                this.showDeleteChecklistModal = false;
                this.checklistToDelete = null;
            }
        },
        async viewOnboarding(applicant) {
            this.selectedApplicant = applicant;
            this.showOnboardingModal = true;
            
            // Reset onboarding details
            this.selectedOnboardingDetails = null;
            
            // Load onboarding details if applicant has an onboarding ID
            if (applicant.onboarding_id) {
                await this.loadOnboardingDetails(applicant);
            }
            
            // Close the action dropdown
            this.actionDropdown = null;
        },
        editOnboarding(applicant) {
            this.selectedApplicant = applicant;
            this.showOnboardingModal = true;
            this.loadOnboardingDetails(applicant);
        },
        toggleActionDropdown(applicationId, event) {
            if (this.actionDropdown === applicationId) {
                this.actionDropdown = null;
                return;
            }
            this.actionDropdown = applicationId;
            this.$nextTick(() => {
                const btn = event.currentTarget;
                const rect = btn.getBoundingClientRect();
                this.dropdownPosition = { top: rect.bottom + 4, left: rect.right - 192 };
            });
        },
        handleClickOutsideDropdown(event) {
            if (this.actionDropdown !== null && !event.target.closest('.relative.inline-block.text-left') && !event.target.closest('.teleported-action-dropdown')) {
                this.actionDropdown = null;
            }
        },
        
        async loadOnboardingDetails(applicant) {
            try {
                if (!applicant.onboarding_id) {
                    this.selectedOnboardingDetails = null;
                    return;
                }
                
                const response = await fetch(`employer_onboarding?action=details&onboarding_id=${applicant.onboarding_id}`);
                const data = await response.json();
                
                if (data.success) {
                    this.selectedOnboardingDetails = data.onboarding_details;
                }
            } catch (error) {
                console.error('Error loading onboarding details:', error);
            }
        },
        addChecklistItem() {
            this.newChecklist.items.push({
                text: '',
                is_required: false,
                order: this.newChecklist.items.length + 1
            });
        },
        removeChecklistItem(index) {
            this.newChecklist.items.splice(index, 1);
            // Reorder items
            this.newChecklist.items.forEach((item, i) => {
                item.order = i + 1;
            });
        },
        // Only used by the checklist library now (create/edit a checklist
        // definition) — assigning it to an applicant is a separate step via
        // the picker modal (openAssignChecklistModal / confirmAssignChecklist).
        async saveChecklist() {
            if (this.checklistModalMode === 'edit') {
                await this.updateExistingChecklist();
                return;
            }

            try {
                const response = await fetch('employer_onboarding?action=saveChecklist', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        title: this.newChecklist.title,
                        description: this.newChecklist.description,
                        items: this.newChecklist.items
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    this.showNotification(data.message || 'Failed to save checklist', 'error');
                    return;
                }

                this.showNotification('Checklist created successfully', 'success');
                await this.fetchOnboardingData();
                await this.fetchChecklistItems();
                this.checklistLibraryView = 'list';
            } catch (error) {
                console.error('Error saving checklist:', error);
                this.showNotification('Error saving checklist', 'error');
            }
        },

        async updateExistingChecklist() {
            try {
                const response = await fetch('employer_onboarding?action=updateChecklist', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        checklist_id: this.editingChecklistId,
                        title: this.newChecklist.title,
                        description: this.newChecklist.description,
                        items: this.newChecklist.items
                    })
                });
                const data = await response.json();

                if (data.success) {
                    this.showNotification('Checklist updated successfully', 'success');
                    await this.fetchOnboardingData();
                    await this.fetchChecklistItems();
                    this.checklistLibraryView = 'list';
                    this.checklistModalMode = 'create';
                    this.editingChecklistId = null;
                } else {
                    this.showNotification(data.message || 'Failed to update checklist', 'error');
                }
            } catch (error) {
                console.error('Error updating checklist:', error);
                this.showNotification('Error updating checklist', 'error');
            }
        },
        async updateChecklistItem(onboardingId, itemId, isCompleted) {
            try {
                const response = await fetch('employer_onboarding?action=updateItem', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        onboarding_id: onboardingId,
                        item_id: itemId,
                        is_completed: isCompleted
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('Checklist updated successfully', 'success');
                    // Refresh the data
                    await this.fetchOnboardingData();
                    await this.loadOnboardingDetails(this.selectedApplicant);
                } else {
                    this.showNotification(data.message || 'Failed to update checklist', 'error');
                }
            } catch (error) {
                console.error('Error updating checklist:', error);
                this.showNotification('Error updating checklist', 'error');
            }
        },
        
        // Opens the "pick an existing checklist" modal for this applicant.
        // Checklists themselves are only created/edited via the library
        // (showChecklistManager) — this just assigns one.
        openAssignChecklistModal(applicant) {
            this.selectedApplicantForChecklist = applicant;
            this.selectedChecklistId = this.checklists[0]?.id || null;
            this.showChecklistSelectionModal = true;
            this.actionDropdown = null;
        },
        closeAssignChecklistModal() {
            this.showChecklistSelectionModal = false;
            this.selectedApplicantForChecklist = null;
            this.selectedChecklistId = null;
        },

        async fetchChecklistItems() {
            try {
                const response = await fetch('employer_onboarding?action=items');
                const data = await response.json();
                
                if (data.success) {
                    this.checklistItems = data.checklist_items;
                }
            } catch (error) {
                console.error('Error fetching checklist items:', error);
            }
        },
        
        getChecklistItems(checklistId) {
            return this.checklistItems.filter(item => item.checklist_id == checklistId);
        },
        
        async confirmAssignChecklist() {
            if (!this.selectedChecklistId || !this.selectedApplicantForChecklist) {
                this.showNotification('Please select a checklist first', 'error');
                return;
            }
            
            try {
                const response = await fetch('employer_onboarding?action=assign', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        application_id: this.selectedApplicantForChecklist.application_id,
                        checklist_id: this.selectedChecklistId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('Checklist assigned and emailed to the applicant', 'success');
                    this.closeAssignChecklistModal();
                    await this.fetchOnboardingData();
                } else {
                    this.showNotification(data.message || 'Failed to assign checklist', 'error');
                }
            } catch (error) {
                console.error('Error assigning checklist:', error);
                this.showNotification('Error assigning checklist', 'error');
            }
        },

        async sendWelcomeEmail(applicant) {
            try {
                const response = await fetch('employer_onboarding?action=sendWelcomeEmail', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        application_id: applicant.application_id,
                        email: applicant.email,
                        name: applicant.alumni_name
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('Welcome email sent successfully', 'success');
                } else {
                    this.showNotification(data.message || 'Failed to send welcome email', 'error');
                }
            } catch (error) {
                console.error('Error sending welcome email:', error);
                this.showNotification('Error sending welcome email', 'error');
            }
        },

        exportToExcel() {
            this.exportDropdownOpen = false;
            this.generateExportFile('excel');
        },
        
        exportToPDF() {
            this.exportDropdownOpen = false;
            this.generateExportFile('pdf');
        },
        
        async generateExportFile(format) {
            try {
                // Show loading notification
                this.showNotification('Preparing export...', 'info');
                
                // Get all data for export (not just paginated)
                const exportData = this.filteredApplicants.map(applicant => ({
                    'Candidate Name': applicant.alumni_name,
                    'Email': applicant.email,
                    'Position': applicant.position,
                    'Checklist': applicant.checklist_name || 'Not assigned',
                    'Progress': `${applicant.progress}%`,
                    'Status': this.formatStatus(applicant.status),
                    'Hire Date': this.formatDate(applicant.applied_at)
                }));
                
                if (exportData.length === 0) {
                    this.showNotification('No data to export', 'warning');
                    return;
                }
                
                const fileName = `Onboarding_Report_${new Date().toISOString().split('T')[0]}`;
                
                switch (format) {
                    case 'excel':
                        await this.exportToExcelFile(exportData, fileName);
                        break;
                    case 'pdf':
                        await this.exportToPDFFile(exportData, fileName);
                        break;
                    case 'csv':
                        this.exportToCSVFile(exportData, fileName);
                        break;
                }
                
                this.showNotification(`${format.toUpperCase()} export completed successfully`, 'success');
                
            } catch (error) {
                console.error('Export error:', error);
                this.showNotification('Error exporting data', 'error');
            }
        },
        
        async exportToExcelFile(data, fileName) {
            await LibLoader.ensureXLSX();
            return new Promise((resolve) => {
                // Create workbook
                const wb = XLSX.utils.book_new();
                
                // Convert data to worksheet
                const ws = XLSX.utils.json_to_sheet(data);
                
                // Add worksheet to workbook
                XLSX.utils.book_append_sheet(wb, ws, 'Onboarding Report');
                
                // Generate Excel file and trigger download
                XLSX.writeFile(wb, `${fileName}.xlsx`);
                resolve();
            });
        },
        
        async exportToPDFFile(data, fileName) {
            await LibLoader.ensureJsPDFAutoTable();
            return new Promise((resolve) => {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                // Add company logo (if available)
                const logoUrl = this.employerProfile.company_logo || '/assets/images/logo.png';
                
                // Add header with logo and company name
                doc.setFontSize(16);
                doc.setTextColor(41, 128, 185);
                doc.text(this.employerProfile.company_name || 'LSPU EIS', 14, 15);
                doc.setFontSize(10);
                doc.setTextColor(100, 100, 100);
                doc.text('Onboarding Management Report', 14, 22);
                doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 14, 28);
                
                // Add table
                doc.autoTable({
                    startY: 40,
                    head: [Object.keys(data[0])],
                    body: data.map(item => Object.values(item)),
                    theme: 'grid',
                    styles: { 
                        fontSize: 8,
                        cellPadding: 2
                    },
                    headStyles: { 
                        fillColor: [41, 128, 185],
                        textColor: 255,
                        fontStyle: 'bold'
                    },
                    alternateRowStyles: {
                        fillColor: [240, 240, 240]
                    }
                });
                
                // Add footer with page numbers
                const pageCount = doc.internal.getNumberOfPages();
                for (let i = 1; i <= pageCount; i++) {
                    doc.setPage(i);
                    doc.setFontSize(8);
                    doc.setTextColor(100, 100, 100);
                    doc.text(`Page ${i} of ${pageCount}`, 
                        doc.internal.pageSize.width / 2, 
                        doc.internal.pageSize.height - 10, 
                        { align: 'center' }
                    );
                }
                
                doc.save(`${fileName}.pdf`);
                resolve();
            });
        },
        
        // Close export dropdown when clicking outside
        handleClickOutsideExport(event) {
            if (!event.target.closest('.relative.inline-block')) {
                this.exportDropdownOpen = false;
            }
        },
        
        async markAsComplete(applicant) {
            try {
                const response = await fetch('employer_onboarding?action=markComplete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        onboarding_id: applicant.onboarding_id
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('Onboarding marked as complete and progress set to 100%', 'success');
                    await this.fetchOnboardingData();
                    this.showOnboardingModal = false;
                } else {
                    this.showNotification(data.message || 'Failed to mark as complete', 'error');
                }
            } catch (error) {
                console.error('Error marking as complete:', error);
                this.showNotification('Error marking as complete', 'error');
            }
        },
        
        async saveOnboardingNotes() {
            try {
                const response = await fetch('employer_onboarding?action=saveNotes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        onboarding_id: this.selectedApplicant.onboarding_id,
                        notes: this.selectedApplicant.onboarding_notes
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('Notes saved successfully', 'success');
                } else {
                    this.showNotification(data.message || 'Failed to save notes', 'error');
                }
            } catch (error) {
                console.error('Error saving notes:', error);
                this.showNotification('Error saving notes', 'error');
            }
        },
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString();
        },
        formatStatus(status) {
            const statusMap = {
                'pending': 'Pending',
                'in_progress': 'In Progress',
                'completed': 'Completed'
            };
            return statusMap[status] || status;
        },
        exportOnboardingReport() {
            // Implementation for exporting report
            console.log('Exporting onboarding report');
            this.showNotification('Onboarding report exported successfully', 'success');
        },
        
        // Pagination methods
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

        // Notification methods
        showNotification(message, type = 'success', title = 'Success') {
            const id = this.notificationId++;
            this.notifications.push({ id, type, title, message });
            setTimeout(() => this.removeNotification(id), 3000);
        },
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        },
        
        // Employer profile methods
        async fetchEmployerProfile() {
            try {
                const res = await fetch('employer_profile?action=details');
                const data = await res.json();
                if (data.success && data.profile) {
                    this.employerProfile = data.profile;
                }
            } catch (error) {
                console.error('Failed to fetch employer profile:', error);
            }
        },
        async fetchJobOptions() {
            try {
                const res = await fetch('employer_jobposting?action=titles');
                const text = await res.text();
                
                if (text.trim().startsWith('<')) {
                    console.error('API returned HTML instead of JSON:', text.substring(0, 200));
                    this.showNotification('Server error: Invalid response format', 'error');
                    return;
                }
                
                const data = JSON.parse(text);
                
                if (data.success) {
                    this.jobOptions = data.jobs;
                } else {
                    this.showNotification(data.message || 'Failed to fetch job options.', 'error');
                }
            } catch (error) {
                console.error('Error fetching job options:', error);
                this.showNotification('Error fetching job options', 'error');
            }
        },
        beforeUnmount() {
            document.removeEventListener('click', this.handleClickOutsideExport);
        }
    }
}).mount('#app');