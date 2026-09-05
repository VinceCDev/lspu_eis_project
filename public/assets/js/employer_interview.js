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
            searchQuery: '',
            showResourceModal: false,
            modalMode: 'add', // 'add', 'edit', 'view'
            selectedResource: null,
            showDeleteModal: false,
            actionDropdown: null,
            resourceForm: {
                id: '',
                job_id: '',
                department: '',
                financial_budget: 0,
                technology_needed: '',
                training_required: '',
                physical_objects: '',
                staffing_requirements: '',
                timeline: '',
                status: 'Planning',
                notes: ''
            },
            notifications: [],
            notificationId: 0,
            showAdvancedFilters: false,
            filters: {
                job: '',
                status: '',
                department: '', // Add this
                // ... other filter properties
            },
            departmentOptions: [],
            employerProfile: {
                company_name: '',
                company_logo: ''
            },
            activePage: 'interview',
            interviews: [],
            interviewCandidates: [], // Applicants with status 'Interviewed'
            viewMode: 'list', // 'list' or 'calendar'
            currentDate: new Date(),
            showInterviewModal: false,
            showViewModal: false,
            showCancelModal: false,
            modalMode: 'add', // 'add' or 'edit'
            selectedInterview: null,
            interviewForm: {
                interview_id: '',
                application_id: '',
                job_id: '',
                alumni_id: '',
                interview_date: '',
                duration: 30,
                interview_type: 'Video Call',
                location: '',
                status: 'Scheduled',
                notes: ''
            },
            filters: {
                job: '',
                status: '',
            },
            searchQuery: '',
            itemsPerPage: 5,
            currentPage: 1,
            paginationGroupSize: 5
        }
    },
    mounted() {
        this.applyDarkMode();
        document.addEventListener('click', this.handleClickOutsideDropdown);
        document.addEventListener('click', this.handleClickOutsideProfile);
        this.fetchEmployerProfile(); // Fetch employer profile
        this.fetchJobOptions();
        this.fetchInterviews();
        this.fetchInterviewCandidates();
        this.fetchJobOptions();
        window.addEventListener('resize', this.handleResize);
        const path = document.location.pathname;
        if (path.endsWith('employer_dashboard.php')) this.activePage = 'dashboard';
        else if (path.endsWith('employer_jobposting.php')) this.activePage = 'jobs';
        else if (path.endsWith('employer_onboarding.php')) this.activePage = 'onboarding';
        else if (path.endsWith('employer_applicants.php')) this.activePage = 'applicants';
        else if (path.endsWith('employer_messages.php')) this.activePage = 'messages';
        else if (path.endsWith('employer_profile.php')) this.activePage = 'profile';
    },
    watch: {
        darkMode(val) {
            this.applyDarkMode();
        }
    },
    computed: {
        
        calendarTitle() {
            return this.currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        },
        
        calendarDays() {
            const year = this.currentDate.getFullYear();
            const month = this.currentDate.getMonth();
            
            // First day of the month
            const firstDay = new Date(year, month, 1);
            // Last day of the month
            const lastDay = new Date(year, month + 1, 0);
            
            // Start from the Sunday of the week that contains the first day
            const startDay = new Date(firstDay);
            startDay.setDate(firstDay.getDate() - firstDay.getDay());
            
            // End on the Saturday of the week that contains the last day
            const endDay = new Date(lastDay);
            endDay.setDate(lastDay.getDate() + (6 - lastDay.getDay()));
            
            const days = [];
            const current = new Date(startDay);
            
            while (current <= endDay) {
                days.push({
                    date: new Date(current),
                    day: current.getDate(),
                    isCurrentMonth: current.getMonth() === month
                });
                current.setDate(current.getDate() + 1);
            }
            
            return days;
        },
        
        filteredInterviews() {
            let filtered = this.interviews;
            
            if (this.searchQuery) {
                const query = this.searchQuery.toLowerCase();
                filtered = filtered.filter(interview =>
                    interview.alumni_name.toLowerCase().includes(query) ||
                    this.getJobTitle(interview.job_id).toLowerCase().includes(query) ||
                    interview.email.toLowerCase().includes(query)
                );
            }
            
            if (this.filters.job) {
                filtered = filtered.filter(interview => interview.job_id == this.filters.job);
            }
            
            if (this.filters.status) {
                filtered = filtered.filter(interview => interview.status === this.filters.status);
            }
            
            return filtered;
        },
        
        paginatedInterviews() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.filteredInterviews.slice(start, end);
        },
        
        totalPages() {
            return Math.ceil(this.filteredInterviews.length / this.itemsPerPage);
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
        openEditResourceModal(resource) {
            this.resourceForm = { ...resource };
            this.modalMode = 'edit';
            this.showResourceModal = true;
        },
        
        // Open add modal
        openAddResourceModal(jobId) {
            this.resourceForm = {
                id: '',
                job_id: jobId,
                department: '',
                financial_budget: 0,
                technology_needed: '',
                training_required: '',
                physical_objects: '',
                staffing_requirements: '',
                timeline: '',
                status: 'Planning',
                notes: ''
            };
            this.modalMode = 'add';
            this.showResourceModal = true;
        },    
        
        // Add these methods to your Vue app:
        openAddModal() {
            this.resourceForm = {
                id: '',
                job_id: '',
                department: '',
                financial_budget: 0,
                technology_needed: '',
                training_required: '',
                physical_objects: '',
                staffing_requirements: '',
                timeline: '',
                status: 'Planning',
                notes: ''
            };
            this.modalMode = 'add';
            this.showResourceModal = true;
        },

        openEditModal(resource) {
            this.resourceForm = { ...resource };
            this.modalMode = 'edit';
            this.showResourceModal = true;
        },

        closeResourceModal() {
            this.showResourceModal = false;
            this.modalMode = 'add';
            this.selectedResource = null;
        },

        viewResource(resource) {
            this.selectedResource = resource;
            this.modalMode = 'view';
            this.showResourceModal = true;
        },

        confirmDelete(resource) {
            this.selectedResource = resource;
            this.showDeleteModal = true;
        },
        
        getJobTitle(jobId) {
            const job = this.jobOptions.find(j => j.job_id == jobId);
            return job ? job.title : 'Unknown Job';
        },
        getResourceIcon(type) {
            switch(type) {
                case 'Document': return 'fa-file-pdf';
                case 'Link': return 'fa-link';
                case 'Video': return 'fa-video';
                default: return 'fa-file-alt';
            }
        },

        
        // Fetch interviews from server
    async fetchInterviews() {
        try {
            const response = await fetch('employer_interview?action=list');
            const data = await response.json();
            if (data.success) {
                this.interviews = data.interviews;
            } else {
                this.showNotification('Failed to fetch interviews', 'error');
            }
        } catch (error) {
            console.error('Error fetching interviews:', error);
            this.showNotification('Error fetching interviews', 'error');
        }
    },
    
    // Fetch candidates eligible for interviewing (status 'Interviewed')
    async fetchInterviewCandidates() {
        try {
            const response = await fetch('employer_interview?action=candidates');
            const data = await response.json();
            if (data.success) {
                this.interviewCandidates = data.candidates;
            } else {
                this.showNotification('Failed to fetch candidates', 'error');
            }
        } catch (error) {
            console.error('Error fetching candidates:', error);
            this.showNotification('Error fetching candidates', 'error');
        }
    },
    // In your Vue methods
    async scheduleInterview() {
        try {
            const response = await fetch('employer_interview?action=store', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(this.interviewForm)
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('Interview scheduled successfully', 'success');
                this.fetchInterviews();
                this.closeInterviewModal();
                
                // Add the returned IDs to the interview data
                const notificationData = {
                    ...this.interviewForm,
                    interview_id: data.interview_id,
                    alumni_id: data.alumni_id,
                    job_id: data.job_id
                };
                
                // Send notification and message to the candidate
                await this.sendInterviewNotification(notificationData, 'scheduled');
            } else {
                this.showNotification(data.message || 'Failed to schedule interview', 'error');
            }
        } catch (error) {
            console.error('Error scheduling interview:', error);
            this.showNotification('Error scheduling interview', 'error');
        }
    },

async sendInterviewNotification(interviewData, action) {
    try {
        // Ensure we have the necessary data
        const notificationData = {
            interview_id: interviewData.interview_id,
            alumni_id: interviewData.alumni_id,
            job_id: interviewData.job_id,
            action: action,
            interview_date: interviewData.interview_date,
            interview_type: interviewData.interview_type,
            location: interviewData.location,
            notes: interviewData.notes || ''
        };
        
        const response = await fetch('employer_interview?action=notify', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(notificationData)
        });
        
        const data = await response.json();
        
        if (!data.success) {
            console.error('Failed to send notification:', data.message);
        }
    } catch (error) {
        console.error('Error sending notification:', error);
    }
},
    
async updateInterview() {
    try {
        const response = await fetch('employer_interview?action=update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(this.interviewForm)
        });
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Server returned non-JSON response');
        }
        
        const data = await response.json();
        
        if (data.success) {
            this.showNotification('Interview updated successfully', 'success');
            this.fetchInterviews();
            this.closeInterviewModal();
            
            // Send notification for rescheduling
            await this.sendInterviewNotification(this.interviewForm, 'rescheduled');
        } else {
            this.showNotification(data.message || 'Failed to update interview', 'error');
        }
    } catch (error) {
        console.error('Error updating interview:', error);
        this.showNotification('Error updating interview. Please check console for details.', 'error');
    }
},

async cancelInterview() {
    try {
        const response = await fetch('employer_interview?action=cancel', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                interview_id: this.selectedInterview.interview_id 
            })
        });
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Server returned non-JSON response');
        }
        
        const data = await response.json();
        
        if (data.success) {
            this.showNotification('Interview cancelled successfully', 'success');
            this.fetchInterviews();
            this.showCancelModal = false;
            
            // Send notification for cancellation
            await this.sendInterviewNotification(this.selectedInterview, 'cancelled');
        } else {
            this.showNotification(data.message || 'Failed to cancel interview', 'error');
        }
    } catch (error) {
        console.error('Error cancelling interview:', error);
        this.showNotification('Error cancelling interview. Please check console for details.', 'error');
    }
},

async markAsComplete(interview) {
    try {
        const response = await fetch('employer_interview?action=update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                interview_id: interview.interview_id,
                status: 'Completed'
            })
        });
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Server returned non-JSON response');
        }
        
        const data = await response.json();
        
        if (data.success) {
            this.showNotification('Interview marked as completed', 'success');
            this.fetchInterviews();
            
            // Send notification for completion
            await this.sendInterviewNotification(interview, 'completed');
        } else {
            this.showNotification(data.message || 'Failed to update interview', 'error');
        }
    } catch (error) {
        console.error('Error updating interview:', error);
        this.showNotification('Error updating interview. Please check console for details.', 'error');
    }
},
    
    // Open modal to schedule new interview
    openScheduleModal() {
        this.interviewForm = {
            interview_id: '',
            application_id: '',
            job_id: '',
            alumni_id: '',
            interview_date: '',
            duration: 30,
            interview_type: 'Video Call',
            location: '',
            status: 'Scheduled',
            notes: ''
        };
        this.modalMode = 'add';
        this.showInterviewModal = true;
    },
    
    // Open modal to edit interview
    editInterview(interview) {
        this.interviewForm = { ...interview };
        this.modalMode = 'edit';
        this.showInterviewModal = true;
    },
    
    // View interview details
    viewInterview(interview) {
        this.selectedInterview = interview;
        this.showViewModal = true;
    },
    
    // Confirm cancellation
    confirmCancel(interview) {
        this.selectedInterview = interview;
        this.showCancelModal = true;
    },
    
    // Close modals
    closeInterviewModal() {
        this.showInterviewModal = false;
    },
    
    closeViewModal() {
        this.showViewModal = false;
    },
    
    // Format date and time for display
    formatDateTime(dateTimeString) {
        const date = new Date(dateTimeString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },
    
    formatTime(dateTimeString) {
        const date = new Date(dateTimeString);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },
    
    // Calendar view methods
    prevMonth() {
        this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() - 1, 1);
    },
    
    nextMonth() {
        this.currentDate = new Date(this.currentDate.getFullYear(), this.currentDate.getMonth() + 1, 1);
    },
    
    getInterviewsForDay(date) {
        return this.interviews.filter(interview => {
            const interviewDate = new Date(interview.interview_date).toDateString();
            return interviewDate === new Date(date).toDateString();
        });
    },
    
    getInterviewStatusClass(status) {
        return {
            'Scheduled': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100',
            'Completed': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100',
            'Cancelled': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100',
            'No Show': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-100'
        }[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
    },

        async fetchEmployerProfile() {
            try {
                const res = await fetch('employer_profile?action=details');
                const data = await res.json();
                if (data.success && data.profile) {
                    this.employerProfile = data.profile;
                    // Add uploads/logos/ path prefix to the logo
                    if (this.employerProfile.company_logo) {
                        this.employerProfile.company_logo = this.employerProfile.company_logo;
                    }
                }
            } catch (error) {
                console.error('Failed to fetch employer profile:', error);
            }
        },

        // In your Vue.js methods
        async fetchJobOptions() {
            try {
                const res = await fetch('employer_jobposting?action=titles');
                const text = await res.text();
                
                // Check if response is HTML instead of JSON
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

        
        toggleActionDropdown(resourceId) {
            this.actionDropdown = this.actionDropdown === resourceId ? null : resourceId;
        },
        handleClickOutsideDropdown(event) {
            if (this.actionDropdown !== null && !event.target.closest('.relative.inline-block.text-left')) {
                this.actionDropdown = null;
            }
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
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        },
        handleFileUpload(event) {
            const file = event.target.files[0];
            if (file) {
                this.resourceForm.file = file;
                this.resourceForm.file_name = file.name;
            }
        },
        
        async exportToExcel() {
            try {
                await LibLoader.ensureXLSX();
                const exportData = this.filteredInterviews.map(interview => ({
                    'Candidate Name': interview.alumni_name,
                    'Email': interview.email,
                    'Job Title': this.getJobTitle(interview.job_id),
                    'Interview Date': this.formatDateTime(interview.interview_date),
                    'Duration (mins)': interview.duration,
                    'Interview Type': interview.interview_type,
                    'Status': interview.status,
                    'Location/Link': interview.location || 'N/A',
                    'Notes': interview.notes || 'N/A'
                }));
        
                if (exportData.length === 0) {
                    this.showNotification('No interview data to export', 'warning');
                    return;
                }
        
                // Create workbook
                const workbook = XLSX.utils.book_new();
                
                // Add main data worksheet
                const worksheet = XLSX.utils.json_to_sheet(exportData);
                
                // Add some basic styling
                const wscols = [
                    {wch: 20}, // Candidate Name
                    {wch: 25}, // Email
                    {wch: 25}, // Job Title
                    {wch: 20}, // Interview Date
                    {wch: 15}, // Duration
                    {wch: 15}, // Interview Type
                    {wch: 15}, // Status
                    {wch: 30}, // Location/Link
                    {wch: 40}  // Notes
                ];
                worksheet['!cols'] = wscols;
                
                XLSX.utils.book_append_sheet(workbook, worksheet, "Interviews");
                
                // Add summary worksheet
                const summaryData = this.generateSummaryData();
                const summaryWorksheet = XLSX.utils.json_to_sheet(summaryData);
                XLSX.utils.book_append_sheet(workbook, summaryWorksheet, "Summary");
                
                // Generate file name
                const currentDate = new Date().toISOString().split('T')[0];
                const fileName = `Interview_Report_${currentDate}.xlsx`;
                
                // Export
                XLSX.writeFile(workbook, fileName);
                
                this.showNotification('Interview report exported successfully!', 'success');
                
            } catch (error) {
                console.error('Error exporting to Excel:', error);
                this.showNotification('Error exporting interview report', 'error');
            }
        },
        
        generateSummaryData() {
            const statusCounts = {
                'Scheduled': 0,
                'Completed': 0,
                'Cancelled': 0,
                'No Show': 0
            };
            
            this.filteredInterviews.forEach(interview => {
                statusCounts[interview.status] = (statusCounts[interview.status] || 0) + 1;
            });
            
            return [
                {'Summary': 'Interview Report Summary'},
                {'': ''},
                {'Total Interviews': this.filteredInterviews.length},
                {'Scheduled': statusCounts.Scheduled},
                {'Completed': statusCounts.Completed},
                {'Cancelled': statusCounts.Cancelled},
                {'No Show': statusCounts['No Show']},
                {'': ''},
                {'Generated on': new Date().toLocaleDateString()},
                {'Generated by': this.employerProfile.company_name || 'Employer'}
            ];
        },

        async exportToPDF() {
            try {
                await LibLoader.ensureJsPDFAutoTable();
                const doc = new window.jspdf.jsPDF();
                const tableColumn = ["Department", "Job Title", "Budget", "Status", "Timeline"];
                const tableRows = [];

                this.jobResources.forEach(resource => {
                    const resourceData = [
                        resource.department,
                        this.getJobTitle(resource.job_id),
                        '₱' + this.formatCurrency(resource.financial_budget),
                        resource.status,
                        resource.timeline || 'N/A'
                    ];
                    tableRows.push(resourceData);
                });

                doc.autoTable({
                    head: [tableColumn],
                    body: tableRows,
                    theme: 'grid',
                    styles: { fontSize: 8 },
                    headStyles: { fillColor: [41, 128, 185] }
                });
                
                // Add additional details on second page
                doc.addPage();
                doc.setFontSize(12);
                doc.text('Detailed Job Resources', 14, 15);
                
                this.jobResources.forEach((resource, index) => {
                    const yPosition = 25 + (index * 60);
                    if (yPosition > 270) {
                        doc.addPage();
                        doc.text('Detailed Job Resources (Continued)', 14, 15);
                    }
                    
                    doc.setFontSize(10);
                    doc.text(`Department: ${resource.department}`, 14, yPosition);
                    doc.text(`Job: ${this.getJobTitle(resource.job_id)}`, 14, yPosition + 5);
                    doc.text(`Budget: ₱${this.formatCurrency(resource.financial_budget)}`, 14, yPosition + 10);
                    doc.text(`Technology: ${resource.technology_needed || 'N/A'}`, 14, yPosition + 15);
                    doc.text(`Training: ${resource.training_required || 'N/A'}`, 14, yPosition + 20);
                    doc.text(`Physical Objects: ${resource.physical_objects || 'N/A'}`, 14, yPosition + 25);
                    doc.text(`Staffing: ${resource.staffing_requirements || 'N/A'}`, 14, yPosition + 30);
                    doc.text(`Timeline: ${resource.timeline || 'N/A'}`, 14, yPosition + 35);
                    doc.text(`Status: ${resource.status}`, 14, yPosition + 40);
                    doc.text(`Notes: ${resource.notes || 'N/A'}`, 14, yPosition + 45);
                });

                doc.save("job_resources.pdf");
                this.showNotification('Job resources exported to PDF successfully!', 'success', 'Export Success');
            } catch (error) {
                console.error('Error exporting to PDF:', error);
                this.showNotification('Failed to export job resources to PDF.', 'error', 'Export Error');
            }
        },

        // Add helper method for currency formatting
        formatCurrency(amount) {
            return parseFloat(amount || 0).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
                showNotification(message, type = 'success', title = 'Success') {
            const id = this.notificationId++;
            this.notifications.push({ id, type, title, message });
            setTimeout(() => this.removeNotification(id), 3000); // Auto-dismiss after 3 seconds
        },
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        }
    }
}).mount('#app');