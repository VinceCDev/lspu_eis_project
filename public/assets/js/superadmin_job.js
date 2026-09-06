const { createApp } = Vue;
createApp({
    data() {
        return {
            sidebarActive: window.innerWidth >= 768,
            companiesDropdownOpen: false,
            systemDropdownOpen: false,
            alumniDropdownOpen: false,
            isSubmitting: false,
            profileDropdownOpen: false,
            darkMode: localStorage.getItem('darkMode') === 'true' || 
                 (localStorage.getItem('darkMode') === null && 
                  window.matchMedia('(prefers-color-scheme: dark)').matches),
            showLogoutModal: false,
            isMobile: window.innerWidth < 768,
            // Job Posting Management Data
            jobs: [
                { id: 1, title: 'Software Engineer', company: 'Tech Corp', type: 'Full-time', location: 'Remote', status: 'Active', description: 'Looking for a skilled software engineer with 3+ years of experience.', requirements: 'Bachelor\'s degree in Computer Science, 3+ years of experience in software development.', qualifications: 'Strong knowledge of Java/Python, experience with frameworks like Spring/Django.' },
                { id: 2, title: 'Data Analyst', company: 'Data Solutions Inc.', type: 'Part-time', location: 'San Pablo City', status: 'Active', description: 'Looking for a data analyst to help with data processing and reporting.', requirements: 'Bachelor\'s degree in Statistics or related field, proficiency in SQL and data visualization tools.', qualifications: 'Experience with data cleaning, analysis, and reporting using tools like Excel, Power BI, Tableau.' },
                { id: 3, title: 'UI/UX Designer', company: 'Design Studio', type: 'Contract', location: 'Santa Cruz', status: 'Closed', description: 'Looking for a UI/UX designer to create engaging user experiences.', requirements: 'Bachelor\'s degree in Design or related field, proficiency in Figma, Adobe XD, or similar tools.', qualifications: 'Experience with user research, wireframing, prototyping, and testing user flows.' },
                { id: 4, title: 'Marketing Manager', company: 'Marketing Masters', type: 'Full-time', location: 'Los Baños', status: 'Active', description: 'Looking for a marketing manager to lead digital marketing efforts.', requirements: 'Bachelor\'s degree in Marketing or related field, 5+ years of experience in digital marketing.', qualifications: 'Strong knowledge of SEO, SEM, social media marketing, and content marketing.' },
                { id: 5, title: 'Project Manager', company: 'Project Pros', type: 'Contract', location: 'Siniloan', status: 'Closed', description: 'Looking for a project manager for a new software development project.', requirements: 'Bachelor\'s degree in Business Administration or related field, 3+ years of experience in project management.', qualifications: 'Experience with Agile methodologies, JIRA, Confluence, and MS Project.' }
            ],
            searchQuery: '',
            filters: {
                company: '',
                type: '',
                status: ''
            },
            sortBy: 'id', // Changed default sort to 'id' since 'created_at' is removed
            sortOrder: 'desc',
            itemsPerPage: 5,
            currentPage: 1,
            paginationGroupSize: 5,
            showJobModal: false,
            modalMode: 'add', // 'add', 'edit', 'view'
            selectedJob: null,
            showDeleteModal: false,
            notifications: [],
            notificationId: 0,
            actionDropdown: null,
            dropdownPosition: { top: 0, left: 0 },
            jobForm: {
                title: '',
                company: '',
                type: '',
                work_setup: '',
                classification: '',
                location: '',
                description: '',
                requirements: '',
                qualifications: '',
                salary: '',
                status: 'Active',
                questions: []
            },
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
            uniqueCompanies: [],
            uniqueTypes: [],
            companiesList: [],
            jobLocationSuggestions: [],
            showJobLocationSuggestions: false,
            profile: {
                profile_pic: '',
                name: ''
            }
        }
    },
    mounted() {
        this.applyDarkMode();
        window.addEventListener('resize', this.handleResize);
        document.addEventListener('click', this.handleClickOutsideDropdown);
        document.addEventListener('click', this.handleClickOutsideProfile);
        this.initializeUniqueLists();
        this.fetchCompaniesList().then(() => {
            this.fetchJobs();
        });
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
        filteredJobs() {
            let filtered = this.jobs;
            if (this.searchQuery) {
                filtered = filtered.filter(job =>
                    job.title.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    job.company.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    job.type.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    job.location.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                    job.status.toLowerCase().includes(this.searchQuery.toLowerCase())
                );
            }
            if (this.filters.company) {
                filtered = filtered.filter(job => job.company === this.filters.company);
            }
            if (this.filters.type) {
                filtered = filtered.filter(job => job.type === this.filters.type);
            }
            if (this.filters.status) {
                filtered = filtered.filter(job => job.status === this.filters.status);
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
        // Page-number buttons in groups of 5 (same pattern as Manage Alumni)
        // instead of listing every page in a row as the job count grows.
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
            this.companiesDropdownOpen = false;
            this.alumniDropdownOpen = false;
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
        initializeUniqueLists() {
            this.uniqueCompanies = [...new Set(this.jobs.map(job => job.company))];
            this.uniqueTypes = [...new Set(this.jobs.map(job => job.type))];
        },
        filterJobs() {
            this.currentPage = 1;
        },
        sortJobs(field) {
            if (this.sortBy === field) {
                this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortBy = field;
                this.sortOrder = 'asc';
            }
        },
        openAddModal() {
            this.modalMode = 'add';
            this.selectedJob = null;
            this.jobForm = {
                title: '',
                company: '',
                type: '',
                work_setup: '',
                classification: '',
                location: '',
                description: '',
                requirements: '',
                qualifications: '',
                salary: '',
                status: 'Active',
                questions: []
            };
            this.showJobModal = true;
        },
        openEditModal(job) {
            this.modalMode = 'edit';
            this.selectedJob = job;
            const questions = Array.isArray(job.questions) && job.questions.length
                ? job.questions.map((q) => ({ question_text: q.question_text, is_required: !!q.is_required }))
                : (job.employer_question ? [{ question_text: job.employer_question, is_required: !!Number(job.employer_question_required) }] : []);
            this.jobForm = { ...job, questions };
            this.showJobModal = true;
        },
        addQuestion() {
            this.jobForm.questions.push({ question_text: '', is_required: false });
        },
        removeQuestion(index) {
            this.jobForm.questions.splice(index, 1);
        },
        viewJob(job) {
            this.modalMode = 'view';
            this.selectedJob = job;
            this.showJobModal = true;
        },
        async addJob() {
            // Prevent multiple submissions
            if (this.isSubmitting) return;
            
            this.isSubmitting = true;
            
            try {
                const formData = new FormData();
                for (const key in this.jobForm) {
                    if (key === 'questions') continue; // sent separately as JSON below
                    if (this.jobForm[key] !== null && this.jobForm[key] !== undefined && this.jobForm[key] !== '') {
                        formData.append(key, this.jobForm[key]);
                    }
                }
                formData.append('questions', JSON.stringify(this.jobForm.questions || []));

                const res = await fetch('/superadmin_job?action=store', {
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
                console.error('Error adding job:', error);
                this.showNotification('Failed to post job. Please try again.', 'error');
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
                    if (key === 'questions') continue; // sent separately as JSON below
                    if (this.jobForm[key] !== null && this.jobForm[key] !== undefined && this.jobForm[key] !== '') {
                        formData.append(key, this.jobForm[key]);
                    }
                }
                formData.append('questions', JSON.stringify(this.jobForm.questions || []));
                formData.append('job_id', this.selectedJob.id || this.selectedJob.job_id);
                
                const res = await fetch('/superadmin_job?action=store', {
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
                console.error('Error updating job:', error);
                this.showNotification('Failed to update job. Please try again.', 'error');
            } finally {
                this.isSubmitting = false;
            }
        },
        confirmDelete(job) {
            this.selectedJob = job;
            this.showDeleteModal = true;
        },
        async deleteJob() {
            try {
                const res = await fetch('/superadmin_job?action=destroy', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ job_id: this.selectedJob.id || this.selectedJob.job_id })
                });
                const data = await res.json();
                this.showNotification(data.message, data.success ? 'success' : 'error');
                if (data.success) {
                    this.closeJobModal();
                    this.fetchJobs && this.fetchJobs();
                }
                this.showDeleteModal = false;
            } catch (error) {
                this.showNotification('Failed to delete job.', 'error');
            }
        },
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        },
        showNotification(message, type = 'success', title = 'Success') {
            const id = this.notificationId++;
            this.notifications.push({ id, type, title, message });
            setTimeout(() => this.removeNotification(id), 3000);
        },
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        },
        toggleActionDropdown(jobId, event) {
            if (this.actionDropdown === jobId) {
                this.actionDropdown = null;
                return;
            }
            this.actionDropdown = jobId;
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
        closeJobModal() {
            this.showJobModal = false;
            this.actionDropdown = null;
            this.requirementsSuggestions = [];
            this.qualificationsSuggestions = [];
            this.showRequirementsSuggestions = false;
            this.showQualificationsSuggestions = false;
            this.isGeneratingSuggestions = false;
            this.isSubmitting = false;
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
                const tableColumn = ["ID", "Title", "Company", "Type", "Location", "Status", "Description", "Requirements", "Qualifications", "Salary"];
                const tableRows = [];

                this.jobs.forEach(job => {
                    const jobData = [
                        job.id,
                        job.title,
                        job.company,
                        job.type,
                        job.location,
                        job.status,
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
        toggleProfileDropdown() {
            this.profileDropdownOpen = !this.profileDropdownOpen;
        },
        handleClickOutsideProfile(event) {
            if (this.profileDropdownOpen && !event.target.closest('.profile-dropdown-wrapper')) {
                this.profileDropdownOpen = false;
            }
        },
        async fetchCompaniesList() {
            try {
                const res = await fetch('/superadmin_job?action=companies');
                const data = await res.json();
                
                if (Array.isArray(data)) {
                    this.companiesList = data;
                    // Update uniqueCompanies with actual company names
                    this.uniqueCompanies = [...new Set(data.map(company => company.company_name))];
                } else {
                    console.error('Invalid companies data:', data);
                    this.companiesList = [];
                    this.uniqueCompanies = [];
                }
            } catch (e) {
                console.error('Error fetching companies:', e);
                this.showNotification('Failed to fetch companies.', 'error');
                this.companiesList = [];
                this.uniqueCompanies = [];
            }
        },
        async fetchJobs() {
            try {
                const res = await fetch('/superadmin_job?action=list');
                const data = await res.json();
                
                // Fetch companies first to ensure we have the latest list
                await this.fetchCompaniesList();
                
                this.jobs = data.map(job => {
                    // Find company by employer_id
                    const company = this.companiesList.find(c => c.user_id == job.employer_id);
                    
                    return {
                        ...job,
                        id: job.job_id,
                        company: company ? company.company_name : (job.company_name || 'Unknown'),
                        employer_id: job.employer_id,
                        logo: company ? company.company_logo : null
                    };
                });
                this.initializeUniqueLists();
            } catch (e) {
                console.error('Error fetching jobs:', e);
                this.showNotification('Failed to fetch jobs.', 'error');
            }
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