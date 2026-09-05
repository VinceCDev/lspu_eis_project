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
            isSubmitting: false, 
            isMobile: window.innerWidth < 768,
            jobs: [],
            searchQuery: '',
            itemsPerPage: 5,
            currentPage: 1,
            paginationGroupSize: 5,
            showJobModal: false,
            modalMode: 'add', // 'add', 'edit', 'view'
            selectedJob: null,
            showDeleteModal: false,
            actionDropdown: null,
            jobForm: {
                title: '',
                type: '',
                work_setup: '',
                classification: '',
                location: '',
                description: '',
                requirements: '',
                qualifications: '',
                salary: '',
                status: 'Active',
                employer_question: '',
                employer_question_required: false
            },
            showQuestionField: false,
            workSetups: ['Onsite', 'Remote', 'Hybrid'],
            jobClassifications: [
                'Accounting / Finance',
                'Admin / Human Resources',
                'Agriculture',
                'Banking / Financial Services',
                'Building / Construction',
                'Customer Service',
                'Education / Training',
                'Engineering',
                'Food & Beverage / Hospitality',
                'Healthcare / Medical',
                'Information & Communication Technology',
                'Insurance',
                'Manufacturing',
                'Marketing / Advertising / PR',
                'Sales / Retail',
                'Sciences',
                'Transportation / Logistics',
                'Others'
            ],
            isGeneratingSuggestions: false,
            requirementsSuggestions: [],
            qualificationsSuggestions: [],
            showRequirementsSuggestions: false,
            showQualificationsSuggestions: false,
            lastJobTitle: '',
            suggestionDebounce: null,
            notifications: [],
            notificationId: 0,
            filters: {
                type: '',
                status: '',
                location: ''
            },
            uniqueTypes: [],
            uniqueStatuses: [],
            uniqueLocations: [],
            jobLocationSuggestions: [],
                showJobLocationSuggestions: false,
            employerProfile: {
                company_name: '',
                company_logo: ''
            },
            activePage: 'jobs',
        }
    },
    mounted() {
        this.applyDarkMode();
        this.fetchJobs(); // Fetch jobs on mount
        this.fetchEmployerProfile(); // Fetch employer profile
        window.addEventListener('resize', this.handleResize);
        document.addEventListener('click', this.handleClickOutsideDropdown);
        document.addEventListener('click', this.handleClickOutsideProfile);
        const path = document.location.pathname;
        if (path.endsWith('employer_dashboard.php')) this.activePage = 'dashboard';
        else if (path.endsWith('employer_jobposting.php')) this.activePage = 'jobs';
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
        filteredJobs() {
            let filtered = this.jobs;
            if (this.searchQuery) {
                filtered = filtered.filter(job =>
                    job.title.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    job.type.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    job.location.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    job.status.toLowerCase().includes(this.searchQuery.toLowerCase())
                );
            }
            if (this.filters.type) {
                filtered = filtered.filter(job => job.type === this.filters.type);
            }
            if (this.filters.status) {
                filtered = filtered.filter(job => job.status === this.filters.status);
            }
            if (this.filters.location) {
                filtered = filtered.filter(job => job.location === this.filters.location);
            }
            return filtered;
        },
        paginatedJobs() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.filteredJobs.slice(start, end);
        },
        totalPages() {
            return Math.ceil(this.filteredJobs.length / this.itemsPerPage);
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
        initializeUniqueLists() {
            this.uniqueTypes = [...new Set(this.jobs.map(job => job.type))];
            this.uniqueStatuses = [...new Set(this.jobs.map(job => job.status))];
            this.uniqueLocations = [...new Set(this.jobs.map(job => job.location))];
        },
        filterJobs() {
            this.currentPage = 1; // Reset to first page when filters change
        },
        openAddModal() {
            this.modalMode = 'add';
            this.selectedJob = null;
            this.jobForm = {
                title: '',
                type: '',
                work_setup: '',
                classification: '',
                location: '',
                description: '',
                requirements: '',
                qualifications: '',
                salary: '',
                status: 'Active',
                employer_question: '',
                employer_question_required: false
            };
            this.showQuestionField = false;
            this.showJobModal = true;
        },
        openEditModal(job) {
            this.modalMode = 'edit';
            this.selectedJob = job;
            this.jobForm = { ...job, employer_question_required: !!Number(job.employer_question_required) };
            this.showQuestionField = !!job.employer_question;
            this.showJobModal = true;
        },
        addQuestionField() {
            this.showQuestionField = true;
        },
        removeQuestionField() {
            this.showQuestionField = false;
            this.jobForm.employer_question = '';
            this.jobForm.employer_question_required = false;
        },
        viewJob(job) {
            this.modalMode = 'view';
            this.selectedJob = job;
            this.showJobModal = true;
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
        async fetchJobs() {
            try {
                const res = await fetch('employer_jobposting?action=list');
                const data = await res.json();
                
                // Process the jobs data to include proper logo paths
                this.jobs = data.map(job => ({
                    ...job,
                    id: job.job_id || job.id,
                    // Add uploads/logos/ path prefix to the logo
                    logo: job.logo ? 'uploads/logos/' + job.logo : null
                }));
                
                this.initializeUniqueLists();
            } catch (e) {
                this.showNotification('Failed to fetch jobs.', 'error');
            }
        },
        async addJob() {
            // Prevent multiple submissions
            if (this.isSubmitting) return;
            
            this.isSubmitting = true;
            
            try {
                const formData = new FormData();
                for (const key in this.jobForm) {
                    // Allow empty salary field
                    if (this.jobForm[key] !== null && this.jobForm[key] !== undefined) {
                        formData.append(key, this.jobForm[key] || ''); // Use empty string if falsy
                    }
                }
                const res = await fetch('employer_jobposting?action=store', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                this.showNotification(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    this.closeJobModal();
                    this.fetchJobs && this.fetchJobs();
                }
            } catch (error) {
                this.showNotification('Failed to post job.', 'error');
            } finally {
                this.isSubmitting = false;
            }
        },
        
        async updateJob() {
            // Prevent multiple submissions
            if (this.isSubmitting) return;
            
            this.isSubmitting = true;
            
            try {
                const formData = new FormData();
                for (const key in this.jobForm) {
                    // Allow empty salary field
                    if (this.jobForm[key] !== null && this.jobForm[key] !== undefined) {
                        formData.append(key, this.jobForm[key] || ''); // Use empty string if falsy
                    }
                }
                formData.append('job_id', this.selectedJob.id || this.selectedJob.job_id);
                const res = await fetch('employer_jobposting?action=store', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                this.showNotification(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    this.closeJobModal();
                    this.fetchJobs && this.fetchJobs();
                }
            } catch (error) {
                this.showNotification('Failed to update job.', 'error');
            }
        },
        confirmDelete(job) {
            this.selectedJob = job;
            this.showDeleteModal = true;
        },
        async deleteJob() {
            try {
                const res = await fetch('employer_jobposting?action=store', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ job_id: this.selectedJob.id || this.selectedJob.job_id })
                });
                const data = await res.json();
                this.showNotification(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    this.closeJobModal();
                    this.fetchJobs();
                }
                this.showDeleteModal = false;
            } catch (error) {
                this.showNotification('Failed to delete job.', 'error');
            }
        },
        closeJobModal() {
            this.showJobModal = false;
            this.actionDropdown = null;
            this.isSubmitting = false;
        },
        toggleActionDropdown(jobId) {
            this.actionDropdown = this.actionDropdown === jobId ? null : jobId;
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
        async exportToExcel() {
            try {
                await LibLoader.ensureXLSX();
                const workbook = XLSX.utils.book_new();
                const worksheet = XLSX.utils.json_to_sheet(this.jobs);
                XLSX.utils.book_append_sheet(workbook, worksheet, "Jobs");
                XLSX.writeFile(workbook, "job_postings.xlsx");
                this.showNotification('Job postings exported to Excel successfully!', 'success', 'Export Success');
            } catch (error) {
                console.error('Error exporting to Excel:', error);
                this.showNotification('Failed to export job postings to Excel.', 'error', 'Export Error');
            }
        },
        async exportToPDF() {
            try {
                await LibLoader.ensureJsPDFAutoTable();
                const doc = new window.jspdf.jsPDF();
                const tableColumn = ["ID", "Title", "Company", "Type", "Location", "Status", "Posted Date", "Description", "Requirements", "Qualifications", "Salary"];
                const tableRows = [];

                this.jobs.forEach(job => {
                    const jobData = [
                        job.id,
                        job.title,
                        job.company,
                        job.type,
                        job.location,
                        job.status,
                        job.created_at,
                        job.description,
                        job.requirements,
                        job.qualifications,
                        job.salary
                    ];
                    tableRows.push(jobData);
                });

                doc.autoTable({
                    head: [tableColumn],
                    body: tableRows
                });
                doc.save("job_postings.pdf");
                this.showNotification('Job postings exported to PDF successfully!', 'success', 'Export Success');
            } catch (error) {
                console.error('Error exporting to PDF:', error);
                this.showNotification('Failed to export job postings to PDF.', 'error', 'Export Error');
            }
        },
        showNotification(message, type = 'success', title = 'Success') {
            const id = this.notificationId++;
            this.notifications.push({ id, type, title, message });
            setTimeout(() => this.removeNotification(id), 3000); // Auto-dismiss after 3 seconds
        },
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        },
        async fetchJobLocationSuggestions() {
            const val = this.jobForm.location;
            if (!val || val.length < 3) {
                this.jobLocationSuggestions = [];
                this.showJobLocationSuggestions = false;
                return;
            }
            const url = `/geocode_proxy?action=autocomplete&text=${encodeURIComponent(val)}&limit=5`;
            try {
                const res = await fetch(url);
                const data = await res.json();
                this.jobLocationSuggestions = data.features.map(f => f.properties.formatted);
                this.showJobLocationSuggestions = true;
            } catch (e) {
                this.jobLocationSuggestions = [];
                this.showJobLocationSuggestions = false;
            }
        },
        selectJobLocationSuggestion(suggestion) {
            this.jobForm.location = suggestion;
            this.jobLocationSuggestions = [];
            this.showJobLocationSuggestions = false;
        },
        hideJobLocationSuggestions() {
            setTimeout(() => { this.showJobLocationSuggestions = false; }, 150);
        },
        async generateSuggestions(field) {
            // Check if job title is empty
            if (!this.jobForm.title.trim()) {
                if (field === 'requirements') {
                    this.requirementsSuggestions = [];
                    this.showRequirementsSuggestions = false;
                } else if (field === 'qualifications') {
                    this.qualificationsSuggestions = [];
                    this.showQualificationsSuggestions = false;
                }
                return;
            }
        
            // Debounce to prevent excessive API calls
            clearTimeout(this.suggestionDebounce);
            this.suggestionDebounce = setTimeout(async () => {
                try {
                    // Make API call to suggestions_gemini.php with job title
                    const response = await fetch('ai_suggestions', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            jobTitle: this.jobForm.title.trim(),
                            field: field
                        })
                    });
                    
                    let suggestions = [];
                    
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success && data.suggestions) {
                            suggestions = data.suggestions;
                        }
                    }
                    
                    // Fallback predefined suggestions if backend fails or returns no suggestions
                    if (suggestions.length === 0) {
                        if (field === 'requirements') {
                            suggestions = this.getDefaultRequirementsSuggestions();
                        } else if (field === 'qualifications') {
                            suggestions = this.getDefaultQualificationsSuggestions();
                        }
                    }
        
                    // Set the suggestions
                    if (field === 'requirements') {
                        this.requirementsSuggestions = suggestions;
                        this.showRequirementsSuggestions = true;
                    } else if (field === 'qualifications') {
                        this.qualificationsSuggestions = suggestions;
                        this.showQualificationsSuggestions = true;
                    }
                    
                } catch (error) {
                    console.error('Error generating suggestions:', error);
                    // Use fallback suggestions without showing error message
                    const fallbackSuggestions = field === 'requirements' 
                        ? this.getDefaultRequirementsSuggestions()
                        : this.getDefaultQualificationsSuggestions();
                        
                    if (field === 'requirements') {
                        this.requirementsSuggestions = fallbackSuggestions;
                        this.showRequirementsSuggestions = true;
                    } else if (field === 'qualifications') {
                        this.qualificationsSuggestions = fallbackSuggestions;
                        this.showQualificationsSuggestions = true;
                    }
                }
            }, 500); // 500ms debounce delay
        },
        
        // Add these helper methods for default suggestions
        getDefaultRequirementsSuggestions() {
            const jobTitle = this.jobForm.title.toLowerCase();
            
            if (jobTitle.includes('software') || jobTitle.includes('developer') || jobTitle.includes('engineer')) {
                return [
                    'Bachelor\'s degree in Computer Science or related field',
                    '2+ years of experience in software development',
                    'Proficiency in programming languages like Java, Python, or JavaScript',
                    'Experience with version control systems (Git)',
                    'Strong problem-solving and analytical skills'
                ];
            } else if (jobTitle.includes('data') || jobTitle.includes('analyst')) {
                return [
                    'Bachelor\'s degree in Statistics, Mathematics, or related field',
                    'Experience with data analysis tools (Excel, SQL, Python/R)',
                    'Knowledge of data visualization tools (Tableau, Power BI)',
                    'Strong analytical and quantitative skills',
                    'Experience with data cleaning and preprocessing'
                ];
            } else if (jobTitle.includes('design') || jobTitle.includes('ui/ux')) {
                return [
                    'Bachelor\'s degree in Design or related field',
                    'Proficiency in design tools (Figma, Adobe XD, Sketch)',
                    'Experience with user research and testing',
                    'Strong portfolio demonstrating design skills',
                    'Knowledge of design principles and best practices'
                ];
            } else if (jobTitle.includes('marketing')) {
                return [
                    'Bachelor\'s degree in Marketing, Business, or related field',
                    'Experience with digital marketing strategies',
                    'Knowledge of SEO, SEM, and social media marketing',
                    'Strong communication and copywriting skills',
                    'Analytical skills to measure campaign performance'
                ];
            } else if (jobTitle.includes('manager') || jobTitle.includes('lead')) {
                return [
                    'Bachelor\'s degree in Business Administration or related field',
                    'Proven experience in team management',
                    'Strong leadership and decision-making skills',
                    'Excellent communication and interpersonal skills',
                    'Experience with project management methodologies'
                ];
            } else if (jobTitle.includes('sales')) {
                return [
                    'Bachelor\'s degree in Business, Marketing or related field',
                    'Proven sales experience and track record',
                    'Excellent communication and negotiation skills',
                    'Ability to build and maintain client relationships',
                    'Self-motivated with strong drive to achieve targets'
                ];
            } else if (jobTitle.includes('customer service') || jobTitle.includes('support')) {
                return [
                    'High school diploma or equivalent (Bachelor\'s degree preferred)',
                    'Previous customer service experience',
                    'Excellent verbal and written communication skills',
                    'Patience and empathy when dealing with customers',
                    'Problem-solving and conflict resolution skills'
                ];
            } else {
                // Generic suggestions for any job title
                return [
                    'Bachelor\'s degree in relevant field',
                    '2+ years of experience in related role',
                    'Strong communication and teamwork skills',
                    'Proficiency in relevant tools and technologies',
                    'Problem-solving and critical thinking abilities'
                ];
            }
        },
        
        getDefaultQualificationsSuggestions() {
            const jobTitle = this.jobForm.title.toLowerCase();
            
            if (jobTitle.includes('software') || jobTitle.includes('developer') || jobTitle.includes('engineer')) {
                return [
                    'Experience with software development methodologies (Agile, Scrum)',
                    'Knowledge of database systems and SQL',
                    'Familiarity with cloud platforms (AWS, Azure, GCP)',
                    'Understanding of software testing principles',
                    'Ability to work in collaborative team environment'
                ];
            } else if (jobTitle.includes('data') || jobTitle.includes('analyst')) {
                return [
                    'Experience with statistical analysis and modeling',
                    'Knowledge of machine learning concepts',
                    'Familiarity with big data technologies',
                    'Ability to present complex data in clear manner',
                    'Experience with data governance and quality assurance'
                ];
            } else if (jobTitle.includes('design') || jobTitle.includes('ui/ux')) {
                return [
                    'Understanding of user-centered design principles',
                    'Experience with prototyping and wireframing',
                    'Knowledge of accessibility standards',
                    'Ability to work with cross-functional teams',
                    'Familiarity with front-end development concepts'
                ];
            } else if (jobTitle.includes('marketing')) {
                return [
                    'Experience with content creation and management',
                    'Knowledge of marketing analytics tools',
                    'Familiarity with CRM systems',
                    'Ability to develop marketing strategies',
                    'Experience with brand management'
                ];
            } else if (jobTitle.includes('manager') || jobTitle.includes('lead')) {
                return [
                    'Experience with budget management',
                    'Knowledge of performance metrics and KPIs',
                    'Ability to mentor and develop team members',
                    'Experience with strategic planning',
                    'Strong organizational and time management skills'
                ];
            } else if (jobTitle.includes('sales')) {
                return [
                    'Experience with CRM software (Salesforce, HubSpot)',
                    'Knowledge of sales techniques and strategies',
                    'Ability to analyze market trends and competitor activity',
                    'Experience with sales reporting and forecasting',
                    'Strong presentation and persuasion skills'
                ];
            } else if (jobTitle.includes('customer service') || jobTitle.includes('support')) {
                return [
                    'Experience with customer service software and systems',
                    'Ability to handle multiple tasks simultaneously',
                    'Knowledge of product/service being supported',
                    'Experience with ticketing systems (Zendesk, Freshdesk)',
                    'Ability to remain calm under pressure'
                ];
            } else {
                // Generic qualifications for any job title
                return [
                    'Relevant professional certifications',
                    'Experience with industry-standard software',
                    'Ability to adapt to changing requirements',
                    'Strong attention to detail',
                    'Commitment to continuous learning and improvement'
                ];
            }
        },
    
        selectSuggestion(field, suggestion) {
            if (field === 'requirements') {
                const currentRequirements = this.jobForm.requirements.trim();
                const separator = currentRequirements ? '\n' : '';
                this.jobForm.requirements += separator + suggestion;
                this.showRequirementsSuggestions = false;
            } else if (field === 'qualifications') {
                const currentQualifications = this.jobForm.qualifications.trim();
                const separator = currentQualifications ? '\n' : '';
                this.jobForm.qualifications += separator + suggestion;
                this.showQualificationsSuggestions = false;
            }
        },
        
        applyAllSuggestions(field) {
            if (field === 'requirements' && this.requirementsSuggestions.length > 0) {
                const currentRequirements = this.jobForm.requirements.trim();
                const separator = currentRequirements ? '\n' : '';
                this.jobForm.requirements += separator + this.requirementsSuggestions.join('\n');
                this.showRequirementsSuggestions = false;
            } else if (field === 'qualifications' && this.qualificationsSuggestions.length > 0) {
                const currentQualifications = this.jobForm.qualifications.trim();
                const separator = currentQualifications ? '\n' : '';
                this.jobForm.qualifications += separator + this.qualificationsSuggestions.join('\n');
                this.showQualificationsSuggestions = false;
            }
        },
    
        closeSuggestions(field) {
            if (field === 'requirements') {
                this.showRequirementsSuggestions = false;
            } else if (field === 'qualifications') {
                this.showQualificationsSuggestions = false;
            }
        },
    
        hideSuggestionsWithDelay(field) {
            setTimeout(() => {
                if (field === 'requirements') {
                    this.showRequirementsSuggestions = false;
                } else if (field === 'qualifications') {
                    this.showQualificationsSuggestions = false;
                }
            }, 200);
        }  
    }
}).mount('#app');