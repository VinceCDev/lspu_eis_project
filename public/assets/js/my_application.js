const { createApp } = Vue;

        createApp({
            data() {
                return {
                    darkMode: localStorage.getItem('darkMode') === 'true' || 
                     (localStorage.getItem('darkMode') === null && 
                      window.matchMedia('(prefers-color-scheme: dark)').matches),
                    mobileMenuOpen: false,
                    profileDropdownOpen: false,
                    showTutorialButton: true, // Start as false, will be updated after check
                    showWelcomeModal: false, // Start as false
                    currentWelcomeSlide: 0,
                    currentPageApplied: 1,
                    currentPageSaved: 1,
                    itemsPerPage: 6,
                    welcomeSlides: [
                        { title: "Welcome", content: "intro" },
                        { title: "Navigation", content: "navigation" },
                        { title: "Job Search", content: "job_search" },
                        { title: "Profile", content: "profile" }
                    ],
                    unreadNotifications: 0,
                    hiredCompanies: [],
                    notifications: [],
                    activeTab: 'applied',
                    loading: true, // Add loading state
                    showJobPanel: false,
                    selectedJob: null,
                    selectedJobDetails: null, // Store fetched job details
                    jobDetailsLoading: false, // Loading state for sidebar
                    showConfirmation: false,
                    confirmationTitle: '',
                    confirmationMessage: '',
                    confirmationAction: null,
                    confirmationIsDestructive: false,
                    mobileProfileDropdownOpen: false,
                    notificationId: 0,
                    appliedJobs: [],
                    savedJobs: [
                        {
                            id: 3,
                            title: 'Data Analyst',
                            company: 'Analytics Inc',
                            location: 'Remote',
                            description: 'We are seeking a Data Analyst to help turn data into information, information into insight and insight into business decisions.',
                            fullDescription: 'We are seeking a Data Analyst to help turn data into information, information into insight and insight into business decisions. You will conduct full lifecycle analysis to include requirements, activities and design. Data analysts will develop analysis and reporting capabilities.',
                            requirements: [
                                'Bachelor\'s degree in Mathematics, Economics, Computer Science or related field',
                                'Strong analytical skills',
                                'Knowledge of SQL and Python',
                                'Experience with data visualization tools'
                            ],
                            qualifications: [
                                'Strong analytical and problem-solving skills',
                                'Proficient in data analysis tools (Excel, Python, R)',
                                'Experience with data modeling and statistical analysis',
                                'Ability to communicate complex findings clearly'
                            ],
                            aboutCompany: 'Analytics Inc provides data solutions to businesses looking to leverage their data for strategic decisions.',
                            savedDate: '2023-05-18',
                            postedDate: '2023-05-05'
                        },
                        {
                            id: 4,
                            title: 'Product Manager',
                            company: 'Product Labs',
                            location: 'Laguna',
                            description: 'We are looking for a Product Manager to join our team and help drive product development from conception to launch.',
                            fullDescription: 'We are looking for a Product Manager to join our team and help drive product development from conception to launch. You will work with cross-functional teams to define product vision, strategy, and roadmap. You will gather and prioritize product and customer requirements.',
                            requirements: [
                                'Bachelor\'s degree in Business, Computer Science or related field',
                                '3+ years of product management experience',
                                'Excellent communication skills',
                                'Strong problem-solving skills'
                            ],
                            qualifications: [
                                'Experience in product management, including roadmap, strategy, and execution',
                                'Strong leadership and communication skills',
                                'Ability to influence cross-functional teams',
                                'Experience with agile methodologies'
                            ],
                            aboutCompany: 'Product Labs is an innovative company focused on building products that solve real-world problems.',
                            savedDate: '2023-05-22',
                            postedDate: '2023-05-15'
                        }
                    ],
                    profile: { name: '' },
                    profilePicData: { file_name: '' },
                    showLogoutModal: false,
                    showApplicationModal: false,
                    applicationStep: 1,
                    applicationPersonal: null,
                    applicationEducation: null,
                    applicationSkills: null,
                    applicationExperience: null,
                    applicationResume: null,
                    applicationCoverLetter: { mode: 'skip', file: null, file_name: '', text: '' },
                    applicationAnswer: '',
                    highlightJobId: null
                };
            },
            watch: {
                darkMode(val) {
                    localStorage.setItem('darkMode', val.toString());
                    this.applyDarkMode();
                },

                activeTab(newTab, oldTab) {
                    // Reset page numbers when switching tabs
                    if (newTab !== oldTab) {
                        this.currentPageApplied = 1;
                        this.currentPageSaved = 1;
                    }
                }
            },
            methods: {
                handleClickOutsideProfile(event) {
                    if (this.profileDropdownOpen && !event.target.closest('.profile-dropdown-wrapper')) {
                        this.profileDropdownOpen = false;
                    }
                },
                applyDarkMode() {
                    const html = document.documentElement;
                    if (this.darkMode) {
                        html.classList.add('dark');
                    } else {
                        html.classList.remove('dark');
                    }
                },
                goToPageApplied(page) {
                    this.currentPageApplied = page;
                },
                
                nextPageApplied() {
                    if (this.currentPageApplied < this.totalPagesApplied) {
                        this.currentPageApplied++;
                    }
                },
                
                prevPageApplied() {
                    if (this.currentPageApplied > 1) {
                        this.currentPageApplied--;
                    }
                },
                
                goToPageSaved(page) {
                    this.currentPageSaved = page;
                },
                
                nextPageSaved() {
                    if (this.currentPageSaved < this.totalPagesSaved) {
                        this.currentPageSaved++;
                    }
                },
                
                prevPageSaved() {
                    if (this.currentPageSaved > 1) {
                        this.currentPageSaved--;
                    }
                },
                showHiredMessage(companyName) {
                    this.addNotification('error', 'Application Restricted', 
                        `You have already been hired by ${companyName} and cannot apply to other positions.`);
                },
                shouldHighlightJob(job) {
                    const jobId = job.job_id || job.id;
                    return this.highlightJobId && jobId == this.highlightJobId;
                },
                async fetchHiredCompanies() {
                    try {
                        const res = await fetch('home?action=hiredCompanies');
                        const data = await res.json();
                        
                        // console.log('Hired companies API response:', data);
                        
                        if (data.success && Array.isArray(data.hired_companies)) {
                            this.hiredCompanies = data.hired_companies.map(id => parseInt(id));
                            // console.log('Hired companies set to:', this.hiredCompanies);
                        } else {
                            this.hiredCompanies = [];
                        }
                    } catch (e) {
                        console.error('Error fetching hired companies:', e);
                        this.hiredCompanies = [];
                    }
                },
                async checkIfCanApply(job) {
                    try {
                        // Check if user is already hired by this company
                        const response = await fetch('my_application?action=checkHiredStatus', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                company_id: job.company_id || job.employer_id,
                                user_id: window.USER_ID
                            })
                        });
                        
                        const data = await response.json();
                        
                        if (data.success && data.is_hired) {
                            this.addNotification('error', 'Application Restricted', 
                                'You have already been hired by this company and cannot apply to other positions.');
                            return false;
                        }
                        
                        return true;
                    } catch (error) {
                        // console.error('Error checking hired status:', error);
                        return true; // Allow application if there's an error checking
                    }
                },
        
                // Also add this method for the highlighting effect
                checkForHighlight() {
                    const urlParams = new URLSearchParams(window.location.search);
                    const jobId = urlParams.get('job_id');
                    const fromNotification = urlParams.get('from_notification');
                    
                    if (jobId && fromNotification) {
                        this.highlightJobId = jobId;
                        
                        // Clean up the URL (remove the parameters without reloading)
                        const cleanUrl = window.location.origin + window.location.pathname;
                        window.history.replaceState({}, document.title, cleanUrl);
                        
                        // Remove highlight after 5 seconds
                        setTimeout(() => {
                            this.highlightJobId = null;
                        }, 5000);
                    }
                },
        
                async fetchUnreadNotifications() {
                    try {
                      const response = await fetch('notification?action=unreadCount');
                      const data = await response.json();
                      if (data.success) {
                        this.unreadNotifications = data.unread_count;
                      }
                    } catch (error) {
                      console.error('Error fetching unread notifications:', error);
                    }
                  },
                formatDate(dateString) {
                    if (!dateString) return '';
                    const options = { year: 'numeric', month: 'short', day: 'numeric' };
                    return new Date(dateString).toLocaleDateString(undefined, options);
                },
                addNotification(type, title, message) {
                    const id = this.notificationId++;
                    this.notifications.push({
                        id,
                        type,
                        title,
                        message
                    });
                    setTimeout(() => {
                        this.removeNotification(id);
                    }, 5000);
                },
                removeNotification(id) {
                    this.notifications = this.notifications.filter(n => n.id !== id);
                },
                async fetchJobDetails(jobId) {
                    this.jobDetailsLoading = true;
                    this.selectedJobDetails = null;
                    try {
                        const res = await fetch(`my_application?action=jobDetails&job_id=${jobId}`);
                        const data = await res.json();
                        console.log('API response:', data); // Debug line to see the response structure
                        
                        if (data.success) {
                            // Structure the data to match your template expectations
                            this.selectedJobDetails = {
                                ...data.jobDetails,          // Job details (title, location, etc.)
                                company_name: data.companyDetails.company_name, // Company name at root level
                                companyDetails: data.companyDetails, // All company details as nested object
                                application_status: this.selectedJob.application_status,
                                cover_letter_text: this.selectedJob.cover_letter_text,
                                cover_letter_file: this.selectedJob.cover_letter_file,
                                application_answer: this.selectedJob.application_answer
                            };
                            
                            console.log('Processed job details:', this.selectedJobDetails); // Debug line
                        } else {
                            this.selectedJobDetails = null;
                            this.addNotification('error', 'Failed to fetch job details.', 'Failed to fetch job details.');
                        }
                    } catch (e) {
                        console.error('Error fetching job details:', e);
                        this.selectedJobDetails = null;
                        this.addNotification('error', 'Failed to fetch job details.', 'Failed to fetch job details.');
                    }
                    this.jobDetailsLoading = false;
                },
                messageEmployer(email) {
                    try {
                        // Redirect to messages page with employer email as parameter
                        const messagesUrl = `message?compose=true&to=${encodeURIComponent(email)}`;
                        window.location.href = messagesUrl;
                    } catch (error) {
                        console.error('Error redirecting to messages:', error);
                        this.showNotification('Error opening message composer', 'error');
                    }
                },
                closeJobPanel() {
                    this.showJobPanel = false;
                    setTimeout(() => {
                        if (window.jobMap) {
                            window.jobMap.remove();
                            window.jobMap = null;
                        }
                    }, 500);
                },
                async viewJob(job) {
                    this.selectedJob = job;
                    this.showJobPanel = true;
                    await this.fetchJobDetails(job.job_id || job.id);
                    this.$nextTick(() => {
                        setTimeout(async () => {
                            // Wait for sidebar to be visible in DOM
                            const mapContainer = document.getElementById('job-map');
                            if (!mapContainer) return;
                        if (window.jobMap) {
                            window.jobMap.remove();
                        }
                        window.jobMap = L.map('job-map').setView([13.9644, 121.1631], 13); // Default: San Pablo
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '© OpenStreetMap'
                        }).addTo(window.jobMap);
                        // Geocode location
                            const loc = job.location || (this.selectedJobDetails && this.selectedJobDetails.location);
                            if (loc) {
                            try {
                                const url = `/geocode_proxy?action=search&text=${encodeURIComponent(loc)}`;
                                const res = await fetch(url);
                                const data = await res.json();
                                if (data.features && data.features.length > 0) {
                                    const coords = data.features[0].geometry.coordinates;
                                    const latlng = [coords[1], coords[0]];
                                    window.jobMap.setView(latlng, 15);
                                    L.marker(latlng).addTo(window.jobMap).bindPopup(loc).openPopup();
                                }
                            } catch (e) {}
                        }
                        }, 100);
                    });
                },
                showConfirm(title, message, action, isDestructive = true) {
                    this.confirmationTitle = title;
                    this.confirmationMessage = message;
                    this.confirmationAction = action;
                    this.confirmationIsDestructive = isDestructive;
                    this.showConfirmation = true;
                },
                executeConfirmation() {
                    if (this.confirmationAction) {
                        this.confirmationAction();
                    }
                    this.showConfirmation = false;
                    this.confirmationAction = null;
                },
                async removeApplication(jobId) {
                    try {
                        const res = await fetch('my_application?action=deleteApplication', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `job_id=${jobId}`
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.appliedJobs = this.appliedJobs.filter(job => job.id !== jobId);
                            this.addNotification('success', 'Success', 'Application removed successfully');
                            this.showJobPanel = false;
                        } else {
                            this.addNotification('error', 'Error', data.message || 'Failed to remove application');
                        }
                    } catch (e) {
                        this.addNotification('error', 'Error', 'Failed to remove application');
                    }
                },
                confirmRemoveApplication(jobId) {
                    this.showConfirm(
                        'Remove Application',
                        'Are you sure you want to remove this application? This action cannot be undone.',
                        () => this.removeApplication(jobId),
                        true
                    );
                },
                withdrawApplication(jobId) {
                    this.removeApplication(jobId);
                },
                async applyJob(jobId) {
                    const job = this.savedJobs.find(j => j.id === jobId) || this.appliedJobs.find(j => j.id === jobId);
                    
                    if (!job) return;
                    
                    // Check if user is already hired by this company - IMMEDIATELY BLOCK
                    const employerId = job.employer_id;
                    if (this.isHiredByCompany(employerId)) {
                        this.addNotification('error', 'Application Restricted', 
                            `You have already been hired by ${job.company} and cannot apply to other positions.`);
                        return; // Stop here - don't open any modals
                    }
                    
                    // If not hired, proceed with the application process
                    this.showConfirm(
                        'Apply for Job',
                        'Are you sure you want to apply for this job? Please review your application before submitting.',
                        () => {
                            this.selectedJob = job;
                            this.openApplicationModal(jobId);
                        },
                        false
                    );
                }, 
                unsaveJob(jobId) {
                    this.showConfirm(
                        'Remove Saved Job',
                        'Are you sure you want to remove this saved job? This action cannot be undone.',
                        async () => {
                            try {
                                const res = await fetch('my_application?action=deleteSavedJob', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: `job_id=${jobId}`
                                });
                                const data = await res.json();
                                if (data.success) {
                            this.savedJobs = this.savedJobs.filter(job => job.id !== jobId);
                            this.addNotification('success', 'Success', 'Job removed from saved list');
                                } else {
                                    this.addNotification('error', 'Error', data.message || 'Failed to remove saved job');
                                }
                            } catch (e) {
                                this.addNotification('error', 'Error', 'Failed to remove saved job');
                            }
                        }
                    );
                },
                logout() {
                    window.location.href = 'logout';
                },
                async fetchApplicationData() {
                    // Fetch all data using the same fetch files as my_profile.php
                    try {
                        // Personal Details
                        const personalRes = await fetch('alumni_profile_data?action=details');
                        const personalData = await personalRes.json();
                        if (personalData.success && personalData.profile) {
                            this.applicationPersonal = personalData.profile;
                            this.profile.name = `${personalData.profile.first_name} ${personalData.profile.last_name}`;
                        } else {
                            this.applicationPersonal = null;
                        }
                        // Education
                        const eduRes = await fetch('alumni_profile_data?action=education');
                        const eduData = await eduRes.json();
                        if (eduData.success && Array.isArray(eduData.education)) {
                            this.applicationEducation = eduData.education;
                        } else {
                            this.applicationEducation = [];
                        }
                        // Skills
                        const skillRes = await fetch('alumni_profile_data?action=skills');
                        const skillData = await skillRes.json();
                        if (skillData.success && Array.isArray(skillData.skills)) {
                            this.applicationSkills = skillData.skills;
                        } else {
                            this.applicationSkills = [];
                        }
                        // Work Experience
                        const expRes = await fetch('alumni_profile_data?action=experience');
                        const expData = await expRes.json();
                        if (expData.success && Array.isArray(expData.experience)) {
                            this.applicationExperience = expData.experience;
                        } else {
                            this.applicationExperience = [];
                        }
                        // Resume
                        const resumeRes = await fetch('alumni_profile_data?action=resume');
                        const resumeData = await resumeRes.json();
                        if (resumeData.success && resumeData.resume) {
                            this.applicationResume = resumeData.resume;
                        } else {
                            this.applicationResume = null;
                        }
                    } catch (e) {
                        this.showNotification('Failed to fetch application data.', 'error');
                    }
                },
                async fetchEducationDetails() {
                    try {
                        const res = await fetch(`alumni_profile_data?action=education`);
                        const data = await res.json();
                        if (data.success) {
                            this.applicationEducation = data.data;
                        } else {
                            this.showNotification('Failed to fetch education details.', 'error');
                        }
                    } catch (e) {
                        this.showNotification('Failed to fetch education details.', 'error');
                    }
                },
                async fetchSkills() {
                    try {
                        const res = await fetch(`alumni_profile_data?action=skills`);
                        const data = await res.json();
                        if (data.success) {
                            this.applicationSkills = data.data;
                        } else {
                            this.showNotification('Failed to fetch skills.', 'error');
                        }
                    } catch (e) {
                        this.showNotification('Failed to fetch skills.', 'error');
                    }
                },
                async fetchExperience() {
                    try {
                        const res = await fetch(`alumni_profile_data?action=experience`);
                        const data = await res.json();
                        if (data.success) {
                            this.applicationExperience = data.data;
                        } else {
                            this.showNotification('Failed to fetch work experience.', 'error');
                        }
                    } catch (e) {
                        this.showNotification('Failed to fetch work experience.', 'error');
                    }
                },
                async fetchResume() {
                    try {
                        const res = await fetch(`alumni_profile_data?action=resume`);
                        const data = await res.json();
                        if (data.success) {
                            this.applicationResume = data.data;
                        } else {
                            this.showNotification('Failed to fetch resume.', 'error');
                        }
                    } catch (e) {
                        this.showNotification('Failed to fetch resume.', 'error');
                    }
                },
                async submitApplication() {
                    // Double-check hired status before submission
                    const canApply = await this.checkIfCanApply(this.selectedJob);
                    
                    if (!canApply) {
                        this.closeApplicationModal();
                        return;
                    }
                    
                    if (!this.applicationResume) {
                        this.addNotification('error', 'Error', 'Please upload a resume before submitting.');
                        return;
                    }

                    if (this.applicationCoverLetter.mode === 'upload' && !this.applicationCoverLetter.file) {
                        this.addNotification('error', 'Error', 'Please upload a cover letter file, or choose to write one or skip it.');
                        return;
                    }

                    if (this.selectedJob.employer_question && Number(this.selectedJob.employer_question_required) && !this.applicationAnswer.trim()) {
                        this.addNotification('error', 'Error', 'Please answer the employer question before submitting.');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('job_id', this.selectedJob.id);

                    if (this.applicationResume.file) {
                        formData.append('resume_file', this.applicationResume.file);
                    } else if (this.applicationResume.file_name) {
                        formData.append('resume_file_name', this.applicationResume.file_name);
                    }

                    formData.append('cover_letter_mode', this.applicationCoverLetter.mode);
                    if (this.applicationCoverLetter.mode === 'upload' && this.applicationCoverLetter.file) {
                        formData.append('cover_letter_file', this.applicationCoverLetter.file);
                    } else if (this.applicationCoverLetter.mode === 'write') {
                        formData.append('cover_letter_text', this.applicationCoverLetter.text);
                    }
                    formData.append('employer_question_answer', this.applicationAnswer);

                    try {
                        const res = await fetch('home?action=apply', {
                            method: 'POST',
                            body: formData
                        });
                        const data = await res.json();
                        
                        if (data.success) {
                            this.addNotification('success', 'Success', 'Application submitted successfully!');
                            this.closeApplicationModal();
                            
                            // Move job from saved to applied
                            const jobIndex = this.savedJobs.findIndex(j => j.id === this.selectedJob.id);
                            if (jobIndex !== -1) {
                                const appliedJob = {...this.savedJobs[jobIndex]};
                                appliedJob.appliedDate = new Date().toISOString().split('T')[0];
                                this.appliedJobs.unshift(appliedJob);
                                this.savedJobs.splice(jobIndex, 1);
                            }
                            
                            // Reset application data
                            this.applicationStep = 1;
                            this.applicationPersonal = null;
                            this.applicationEducation = null;
                            this.applicationSkills = null;
                            this.applicationExperience = null;
                            this.applicationResume = null;
                            this.applicationCoverLetter = { mode: 'skip', file: null, file_name: '', text: '' };
                            this.applicationAnswer = '';
                        } else {
                            this.addNotification('error', 'Error', data.message || 'Failed to submit application');
                        }
                    } catch (e) {
                        this.addNotification('error', 'Error', 'Failed to submit application');
                    }
                },
                isHiredByCompany(employerId) {
                    if (!employerId) return false;
                    
                    // Convert both to numbers for comparison
                    const targetId = parseInt(employerId);
                    const hiredIds = this.hiredCompanies.map(id => parseInt(id));
                    
                    console.log('Checking hired status:', {
                        employerId: targetId,
                        hiredCompanies: hiredIds,
                        isHired: hiredIds.includes(targetId)
                    });
                    
                    return hiredIds.includes(targetId);
                },          
                handleApplicationResumeUpload(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.applicationResume = {
                            ...this.applicationResume,
                            file: file,
                            file_name: file.name,
                            uploaded_at: new Date().toISOString()
                        };
                    }
                },
                handleApplicationCoverLetterUpload(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.applicationCoverLetter.file = file;
                        this.applicationCoverLetter.file_name = file.name;
                    }
                },
                openApplicationModal(jobId) {
                    const job = this.savedJobs.find(j => j.id === jobId) || this.appliedJobs.find(j => j.id === jobId);
                    
                    // Double-check hired status before opening modal
                    if (job && this.isHiredByCompany(job.employer_id)) {
                        this.addNotification('error', 'Application Restricted', 
                            `You have already been hired by ${job.company} and cannot apply to other positions.`);
                        return; // Don't open the modal
                    }
                    
                    this.showApplicationModal = true;
                    this.applicationStep = 1;
                    this.selectedJob = job;
                    this.fetchApplicationData(); // Fetch all data on modal open
                },
                closeApplicationModal() {
                    this.showApplicationModal = false;
                    this.applicationStep = 1;
                    this.applicationPersonal = null;
                    this.applicationEducation = null;
                    this.applicationSkills = null;
                    this.applicationExperience = null;
                    this.applicationResume = null;
                    this.applicationCoverLetter = { mode: 'skip', file: null, file_name: '', text: '' };
                    this.applicationAnswer = '';
                },
                nextStep() {
                    this.applicationStep++;
                    if (this.applicationStep === 2) this.fetchPersonalDetails();
                    else if (this.applicationStep === 3) this.fetchEducationDetails();
                    else if (this.applicationStep === 4) this.fetchSkills();
                    else if (this.applicationStep === 5) this.fetchExperience();
                },
                prevStep() {
                    this.applicationStep--;
                    if (this.applicationStep === 1) this.fetchPersonalDetails();
                    else if (this.applicationStep === 2) this.fetchPersonalDetails();
                    else if (this.applicationStep === 3) this.fetchEducationDetails();
                    else if (this.applicationStep === 4) this.fetchSkills();
                    else if (this.applicationStep === 5) this.fetchExperience();
                },
                formatTime(time) {
                    const [hours, minutes] = time.split(':');
                    const date = new Date();
                    date.setHours(hours, minutes, 0, 0);
                    return date.toLocaleTimeString([], { hour: 'numeric', minute: 'numeric' });
                },
                async fetchAppliedJobs() {
                    try {
                        const res = await fetch('my_application?action=appliedJobs');
                        const data = await res.json();
                        if (Array.isArray(data.appliedJobs)) {
                            this.appliedJobs = data.appliedJobs.map(job => ({
                                id: job.job_id, // for v-for and button
                                job_id: job.job_id, // for fetching details
                                title: job.title,
                                company: job.company_name || job.company || '',
                                location: job.location,
                                description: job.description,
                                fullDescription: job.fullDescription || job.description,
                                requirements: job.requirements ? job.requirements.split('\n') : [],
                                qualifications: job.qualifications ? job.qualifications.split('\n') : [],
                                appliedDate: job.appliedDate || job.applied_at || '',
                                postedDate: job.postedDate || job.created_at || '',
                                application_status: job.application_status || 'Pending',
                                cover_letter_text: job.cover_letter_text || '',
                                cover_letter_file: job.cover_letter_file || '',
                                application_answer: job.application_answer || '',
                                employer_question: job.employer_question || '',
                                employer_question_required: job.employer_question_required,
                                companyDetails: {
                                    company_logo: job.company_logo || '',
                                    company_name: job.company_name || '',
                                    company_location: job.company_location || '',
                                    contact_email: job.contact_email || '',
                                    contact_number: job.contact_number || '',
                                    nature_of_business: job.nature_of_business || '',
                                    industry_type: job.industry_type || '',
                                    accreditation_status: job.accreditation_status || ''
                                }
                            }));
                        }
                    } catch (e) {
                        this.addNotification('error', 'Failed to fetch applied jobs.', 'Failed to fetch applied jobs.');
                    }
                },
                async fetchSavedJobs() {
                    try {
                        const res = await fetch('my_application?action=savedJobs');
                        const data = await res.json();
                        if (Array.isArray(data.savedJobs)) {
                            this.savedJobs = data.savedJobs.map(job => ({
                                id: job.job_id,
                                title: job.title,
                                company: job.company_name || job.company || '',
                                employer_id: job.employer_id, // Make sure this is included
                                location: job.location,
                                description: job.description,
                                fullDescription: job.fullDescription || job.description,
                                requirements: job.requirements ? job.requirements.split('\n') : [],
                                qualifications: job.qualifications ? job.qualifications.split('\n') : [],
                                employer_question: job.employer_question || '',
                                employer_question_required: job.employer_question_required,
                                aboutCompany: job.aboutCompany || '',
                                savedDate: job.savedDate || '',
                                postedDate: job.postedDate || '',
                                companyDetails: job.companyDetails || {},
                            }));
                            
                            // Debug: log the first job to check employer_id
                            if (this.savedJobs.length > 0) {
                                // console.log('First saved job employer_id:', this.savedJobs[0].employer_id);
                            }
                        }
                    } catch (e) {
                        console.error('Error fetching saved jobs:', e);
                        // fallback: keep hardcoded jobs if fetch fails
                    }
                },
                openTutorial() {
                    this.showWelcomeModal = true;
                    this.currentWelcomeSlide = 0;
                    
                    // Mark tutorial as viewed in session storage
                    sessionStorage.setItem('tutorial_viewed', 'true');
                },
                
                closeWelcomeModal() {
                    console.log('Closing welcome modal');
                    this.showWelcomeModal = false;
                    
                    // Always mark as shown when user closes the modal
                    localStorage.setItem('welcomeModalShown', 'true');
                    console.log('Set welcomeModalShown to true in localStorage');
                    
                    // If user completed the tutorial (reached the end), mark it as completed
                    if (this.currentWelcomeSlide === this.welcomeSlides.length - 1) {
                        console.log('User completed tutorial, marking as completed');
                        this.markTutorialCompleted();
                    }
                },
                async markTutorialCompleted() {
                    try {
                        const response = await fetch('notification?action=markTutorialCompleted', {
                            method: 'POST'
                        });
                        
                        const data = await response.json();
                        if (data.success) {
                            this.showTutorialButton = false;
                            sessionStorage.setItem('tutorial_completed', 'true');
                        }
                    } catch (error) {
                        console.error('Error marking tutorial as completed:', error);
                    }
                }
            },
            mounted() {
                document.addEventListener('click', this.handleClickOutsideProfile);
                // Set dark mode on initial load
                const storedMode = localStorage.getItem('darkMode');
                if (storedMode !== null) {
                    this.darkMode = storedMode === 'true';
                } else {
                    this.darkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
                }
                this.applyDarkMode();
                this.fetchUnreadNotifications();
                this.fetchHiredCompanies();
  
                this.checkForHighlight();
                // Optional: Poll for new notifications every 30 seconds
                this.notificationInterval = setInterval(this.fetchUnreadNotifications, 30000);
                // Fetch applied and saved jobs for the current user
                Promise.all([
                    this.fetchAppliedJobs(),
                    this.fetchSavedJobs()
                ]).finally(() => {
                    this.loading = false;
                });
                fetch('alumni_profile_data?action=fetchProfilePic')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.file_name) {
                            this.profilePicData.file_name = data.file_name;
                        } else {
                            this.profilePicData.file_name = '';
                        }
                    });
                fetch('alumni_profile_data?action=details')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.profile) {
                            this.profile.name = `${data.profile.first_name} ${data.profile.last_name}`;
                        }
                    });
            },
            computed: {
                totalApplicationSteps() {
                    return this.selectedJob && this.selectedJob.employer_question ? 7 : 6;
                },
                paginatedAppliedJobs() {
                    const start = (this.currentPageApplied - 1) * this.itemsPerPage;
                    const end = start + this.itemsPerPage;
                    return this.appliedJobs.slice(start, end);
                },
                
                paginatedSavedJobs() {
                    const start = (this.currentPageSaved - 1) * this.itemsPerPage;
                    const end = start + this.itemsPerPage;
                    return this.savedJobs.slice(start, end);
                },
                
                totalPagesApplied() {
                    return Math.ceil(this.appliedJobs.length / this.itemsPerPage);
                },
                
                totalPagesSaved() {
                    return Math.ceil(this.savedJobs.length / this.itemsPerPage);
                }
            },
            beforeUnmount() {
                // Clean up the interval
                if (this.notificationInterval) {
                  clearInterval(this.notificationInterval);
                }
              }
        }).mount('#app');