// Vue app
const { createApp } = Vue;

createApp({
    data() {
        return {
            loading: true, // Add loading state
            notifications: [],
            showTutorialButton: true, // Start as false, will be updated after check
            showWelcomeModal: false, // Start as false
            currentWelcomeSlide: 0,
            welcomeSlides: [
                { title: "Welcome", content: "intro" },
                { title: "Navigation", content: "navigation" },
                { title: "Job Search", content: "job_search" },
                { title: "Profile", content: "profile" }
            ],
            hiredCompanies: [], 
            currentPage: 1,
            itemsPerPage: 6,
            paginationGroupSize: 5,
            highlightJobId: null,
            notificationId: 0,
            unreadNotifications: 0,
            notifications: [],
            searchQuery: '',
            selectedLocation: '',
            selectedJobType: '',
            selectedWorkSetup: '',
            selectedClassification: '',
            selectedSalary: '',
            selectedDatePosted: '',
            locationQuery: '', // Add this line
            selectedLocation: '',
            showDetails: false,
            mobileProfileDropdownOpen: false,
            selectedJob: {
                title: 'Software Engineer',
                company: 'Tech Corp',
                location: 'Manila',
                type: 'Full-time',
                salary: '₱50,000 - ₱70,000',
                description: 'We are looking for a skilled software engineer to join our team. The ideal candidate will have experience with modern JavaScript frameworks and a strong understanding of web development principles.',
                requirements: 'Bachelor\'s degree in Computer Science or related field',
                qualifications: [
                    '3+ years of experience with JavaScript',
                    'Experience with Vue.js or React',
                    'Strong problem-solving skills',
                    'Knowledge of RESTful APIs'
                ],
                questions: [
                    {
                        text: 'How many years of experience do you have with Vue.js?',
                        type: 'select',
                        options: ['Less than 1 year', '1-2 years', '3+ years']
                    },
                    {
                        text: 'Describe your experience with REST APIs',
                        type: 'textarea',
                        placeholder: 'Your answer here...'
                    }
                ],
                saved: false,
                companyDetails: {} // Added for company details
            },
            jobs: [
                {
                    id: 1,
                    title: 'Software Engineer',
                    company: 'Tech Corp',
                    location: 'Manila',
                    type: 'Full-time',
                    salary: '₱50,000 - ₱70,000',
                    description: 'We are looking for a skilled software engineer to join our team. The ideal candidate will have experience with modern JavaScript frameworks and a strong understanding of web development principles.',
                    requirements: 'Bachelor\'s degree in Computer Science or related field',
                    qualifications: [
                        '3+ years of experience with JavaScript',
                        'Experience with Vue.js or React',
                        'Strong problem-solving skills',
                        'Knowledge of RESTful APIs'
                    ],
                    questions: [
                        {
                            text: 'How many years of experience do you have with Vue.js?',
                            type: 'select',
                            options: ['Less than 1 year', '1-2 years', '3+ years']
                        },
                        {
                            text: 'Describe your experience with REST APIs',
                            type: 'textarea',
                            placeholder: 'Your answer here...'
                        }
                    ],
                    saved: false,
                    companyDetails: { // Added for company details
                        company_name: 'Tech Corp',
                        contact_email: 'info@techcorp.com',
                        contact_number: '+63 912 345 6789',
                        company_location: '123 Main St, Manila',
                        industry_type: 'Technology',
                        nature_of_business: 'Software Development',
                        accreditation_status: 'Accredited'
                    }
                },
                {
                    id: 2,
                    title: 'UX Designer',
                    company: 'Design Studio',
                    location: 'Cebu',
                    type: 'Full-time',
                    salary: '₱40,000 - ₱60,000',
                    description: 'Join our creative team as a UX Designer. You will be responsible for creating user-centered designs by understanding business requirements, and user feedback.',
                    requirements: 'Bachelor\'s degree in Design or related field',
                    qualifications: [
                        '2+ years of UX design experience',
                        'Portfolio of design projects',
                        'Knowledge of Figma or Adobe XD'
                    ],
                    questions: [],
                    saved: true,
                    companyDetails: { // Added for company details
                        company_name: 'Design Studio',
                        contact_email: 'contact@designstudio.com',
                        contact_number: '+63 923 456 7890',
                        company_location: '456 Oak Ave, Cebu',
                        industry_type: 'Design',
                        nature_of_business: 'Design Agency',
                        accreditation_status: 'Not Accredited'
                    }
                },
                {
                    id: 3,
                    title: 'Data Analyst',
                    company: 'Analytics Inc',
                    location: 'Remote',
                    type: 'Contract',
                    salary: '₱45,000 - ₱65,000',
                    description: 'We are seeking a Data Analyst to help turn data into information, information into insight and insight into business decisions.',
                    requirements: 'Bachelor\'s degree in Mathematics, Economics, Computer Science or related field',
                    qualifications: [
                        'Strong analytical skills',
                        'Knowledge of SQL and Python',
                        'Experience with data visualization tools'
                    ],
                    questions: [],
                    saved: false,
                    companyDetails: { // Added for company details
                        company_name: 'Analytics Inc',
                        contact_email: 'hr@analyticsinc.com',
                        contact_number: '+63 934 567 8901',
                        company_location: '789 Pine Ln, Remote',
                        industry_type: 'Data Science',
                        nature_of_business: 'Consulting',
                        accreditation_status: 'Accredited'
                    }
                }
            ],
            locationSuggestions: [],
            showLocationDropdown: false,
            defaultLocations: ['Manila', 'Cebu', 'Davao', 'Laguna', 'Nueva Ecija', 'Bongabon', 'Quezon City', 'Makati', 'Taguig', 'Remote'],
            jobTypes: ['Full-time', 'Part-time', 'Contract', 'Internship', 'Remote'],
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
            salaryRanges: [
                { label: '₱10,000 - ₱20,000', value: '10-20' },
                { label: '₱20,000 - ₱30,000', value: '20-30' },
                { label: '₱30,000 - ₱50,000', value: '30-50' },
                { label: '₱50,000 - ₱100,000', value: '50-100' },
                { label: '₱100,000+', value: '100+' }
            ],
            mobileMenuOpen: false,
            profileDropdownOpen: false,
            filtersOpen: false,
            darkMode: false,
            showApplicationModal: false, // New state for application modal
            applicationStep: 1, // New state for application step
            applicationPersonal: null, // New state for personal details
            applicationEducation: null, // New state for education details
            applicationSkills: null, // New state for skills
            applicationExperience: null, // New state for work experience
            applicationResume: null, // New state for resume file
            applicationCoverLetter: { mode: 'skip', file: null, file_name: '', text: '' },
            applicationAnswer: '',
            showChatOverlay: false, // New state for chat overlay
            chatSearchQuery: '', // New state for chat search query
            unreadMessagesCount: 0, // New state for unread messages count
            conversations: [ // New state for conversations
                {
                    id: 1,
                    name: 'John Doe',
                    avatar: 'images/alumni.png',
                    messages: [
                        { text: 'Hi! I\'m interested in the Software Engineer position.', sender: 'me', time: '10:00' },
                        { text: 'Hello! I\'m the hiring manager. How can I help you?', sender: 'them', time: '10:01' },
                        { text: 'I\'m interested in the position. What are the requirements?', sender: 'me', time: '10:02' },
                        { text: 'You need a Bachelor\'s degree in Computer Science or related field. Experience with modern JavaScript frameworks and a strong understanding of web development principles.', sender: 'them', time: '10:03' },
                        { text: 'I have that experience. Can I apply?', sender: 'me', time: '10:04' },
                        { text: 'Yes, please send your resume.', sender: 'them', time: '10:05' },
                        { text: 'I will send it now.', sender: 'me', time: '10:06' }
                    ],
                    unread: true
                },
                {
                    id: 2,
                    name: 'Jane Smith',
                    avatar: 'images/alumni.png',
                    messages: [
                        { text: 'Hey! I saw your UX Designer position. I\'m interested.', sender: 'me', time: '11:00' },
                        { text: 'Hello! I\'m the hiring manager. I\'m impressed by your portfolio. What are your UX design skills?', sender: 'them', time: '11:01' },
                        { text: 'I have 2+ years of UX design experience. I\'m proficient in Figma and Adobe XD.', sender: 'me', time: '11:02' },
                        { text: 'That\'s great! I\'d like to see your portfolio.', sender: 'them', time: '11:03' },
                        { text: 'I will send it to you via email.', sender: 'me', time: '11:04' },
                        { text: 'Perfect! Looking forward to your application.', sender: 'them', time: '11:05' }
                    ],
                    unread: false
                }
            ],
            activeConversationIndex: null, // New state for active conversation index
            newMessage: '', // New state for new message input
            profilePicData: { file_name: '' },
            profile: { name: '' },
            showLogoutModal: false,
        };
    },
    computed: {
        totalApplicationSteps() {
            return this.selectedJob && this.selectedJob.employer_question ? 7 : 6;
        },
        paginatedJobs() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            const end = start + this.itemsPerPage;
            return this.filteredJobs.slice(start, end);
        },
        
        totalPages() {
            return Math.ceil(this.filteredJobs.length / this.itemsPerPage);
        },
        // Page-number buttons in groups of 5, same pattern used across the
        // admin/employer tables — the window recenters on currentPage
        // automatically via prevPage()/nextPage(), no separate "jump 5" is
        // needed on this page's existing First/Prev/Next controls.
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
        filteredJobs: function() {
            return this.jobs.filter(function(job) {
                const matchesSearch = job.title.toLowerCase().includes(this.searchQuery.toLowerCase()) || 
                                     job.company.toLowerCase().includes(this.searchQuery.toLowerCase());
                
                // Improved location filter - check if either contains the other
                const matchesLocation = !this.selectedLocation || 
                                      job.location.toLowerCase().includes(this.selectedLocation.toLowerCase()) ||
                                      this.selectedLocation.toLowerCase().includes(job.location.toLowerCase());
                
                const matchesJobType = !this.selectedJobType || job.type === this.selectedJobType;

                const matchesWorkSetup = !this.selectedWorkSetup || job.work_setup === this.selectedWorkSetup;

                const matchesClassification = !this.selectedClassification || job.classification === this.selectedClassification;

                // Fixed salary filter
                const matchesSalary = !this.selectedSalary || this.matchesSalaryRange(job.salary, this.selectedSalary);

                const matchesDatePosted = !this.selectedDatePosted || this.matchesDatePosted(job.created_at, this.selectedDatePosted);

                return matchesSearch && matchesLocation && matchesJobType && matchesWorkSetup && matchesClassification && matchesSalary && matchesDatePosted;
            }.bind(this));
        },
        clearLocation() {
            this.locationQuery = '';
            this.selectedLocation = '';
            this.showLocationDropdown = false;
        },
        filteredConversations() {
            return this.conversations.filter(conversation => 
                conversation.name.toLowerCase().includes(this.chatSearchQuery.toLowerCase())
            );
        },
        activeConversationData() {
            if (this.activeConversationIndex !== null && this.conversations[this.activeConversationIndex]) {
                return this.conversations[this.activeConversationIndex];
            }
            return {
                name: 'Select a conversation',
                avatar: 'images/alumni.png',
                messages: [],
                unread: false
            };
        },
        
    },
    watch: {
        searchQuery() {
            this.currentPage = 1;
        },
        
        selectedLocation() {
            this.currentPage = 1;
        },
        
        selectedJobType() {
            this.currentPage = 1;
        },
        
        selectedSalary() {
            this.currentPage = 1;
        },
        selectedDatePosted() {
            this.currentPage = 1;
        },
        darkMode(val) {
            localStorage.setItem('darkMode', val.toString());
            this.applyDarkMode();
        },
        showDetails(val) {
            const header = document.querySelector('header');
            if (header) {
                if (val) {
                    header.classList.add('header-frosted');
                } else {
                    header.classList.remove('header-frosted');
                }
            }
        },
        locationQuery(newVal) {
            if (!newVal.trim()) {
                this.selectedLocation = '';
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
        
        this.checkForHighlight();
        // Check if user is new based on account creation date
        this.checkIfNewUser().then((userData) => {
            console.log('User status:', userData);
            
            // Check if welcome modal was already shown
            const welcomeModalShown = localStorage.getItem('welcomeModalShown');
            console.log('Welcome modal shown status:', welcomeModalShown);
            
            // Only show welcome modal for new users who haven't seen it yet
            // MODIFIED: Check if welcomeModalShown is null or "false" instead of just null
            if (userData.is_new_user && (welcomeModalShown === null || welcomeModalShown === 'false')) {
                console.log('Showing welcome modal for new user');
                // Small delay to ensure everything is loaded
                setTimeout(() => {
                    this.showWelcomeModal = true;
                }, 1000);
            } else {
                console.log('Not showing welcome modal. is_new_user:', userData.is_new_user, 'welcomeModalShown:', welcomeModalShown);
                
                // DEBUG: Force show for testing if needed
                // this.showWelcomeModal = true;
            }
        }).catch((error) => {
            console.error('Failed to check user status:', error);
        });
        
        // Fetch jobs and set loading=false after
        Promise.all([
            this.fetchJobs(),
            this.fetchHiredCompanies()
        ]).finally(() => {
            this.loading = false;
        });
    
        this.fetchUnreadNotifications();
      
        // Optional: Poll for new notifications every 30 seconds
        this.notificationInterval = setInterval(this.fetchUnreadNotifications, 30000);
    
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
        // Fetch personal details to populate profile.name
    },
    beforeUnmount() {
        // Clean up the interval
        if (this.notificationInterval) {
          clearInterval(this.notificationInterval);
        }
      },
    methods: {
        handleClickOutsideProfile(event) {
            if (this.profileDropdownOpen && !event.target.closest('.profile-dropdown-wrapper')) {
                this.profileDropdownOpen = false;
            }
        },
        goToPage(page) {
            this.currentPage = page;
        },
        
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },
        
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },

        async fetchHiredCompanies() {
            try {
                const res = await fetch('home?action=hiredCompanies');
                const data = await res.json();
                
                console.log('Hired companies API response:', data);
                
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

        showHiredMessage(companyName) {
            this.showNotification(`You have already been hired by ${companyName} and cannot apply to other positions.`, 'error');
        },

        fetchJobs: async function() {
            try {
                const res = await fetch('home?action=jobs');
                const data = await res.json();
                
                // Fetch saved jobs for the current user
                let savedJobIds = [];
                try {
                    const savedRes = await fetch('home?action=savedJobs');
                    const savedData = await savedRes.json();
                    savedJobIds = savedData.savedJobIds || [];
                } catch (e) {}
                
                // Fetch companies for details
                let companies = [];
                try {
                    const cres = await fetch('home?action=employers');
                    companies = await cres.json();
                } catch (e) {}

                this.jobs = data.map(job => {
                    const company = companies.find(c => c.user_id == job.employer_id) || {};
                    
                    // Handle logo URL like in your companies mapping. Leave it null when there's no
                    // real logo so the template's own fallback image (asset('images/logo.png')) is
                    // used, instead of building a malformed URL that 404s.
                    const logoUrl = job.company_logo || company.company_logo;
                    const processedLogoUrl = logoUrl
                        ? (logoUrl.startsWith('http')
                            ? logoUrl
                            : '/uploads/logos/' + logoUrl.replace(/^.*[\\\/]/, ''))
                        : null;
                    
                    return {
                        id: job.job_id,
                        title: job.title,
                        company: company.company_name || job.company_name || job.company || '',
                        location: job.location,
                        type: job.type,
                        work_setup: job.work_setup,
                        classification: job.classification,
                        salary: job.salary,
                        description: job.description,
                        requirements: job.requirements,
                        qualifications: job.qualifications ? job.qualifications.split('\n') : [],
                        questions: job.employer_question ? [{ text: job.employer_question, type: 'textarea' }] : [],
                        employer_question: job.employer_question || '',
                        employer_question_required: job.employer_question_required,
                        saved: savedJobIds.map(String).includes(String(job.job_id)),
                        employer_id: job.employer_id, // Make sure this is included
                        companyDetails: {
                            ...company,
                            logoUrl: processedLogoUrl
                        },
                        logoUrl: processedLogoUrl // Also include at job level if needed
                    };
                });
                
                console.log('Saved jobs:', savedJobIds, this.jobs.map(j => ({id: j.id, saved: j.saved})));
                
               // console.log('All job salaries:');
                this.jobs.forEach(job => {
                    console.log(`Job: ${job.title}, Salary: "${job.salary}"`);
                });
            } catch (e) {
                console.error('Error fetching jobs:', e);
                // fallback: keep hardcoded jobs if fetch fails
            }
        },

        forceShowModal() {
            console.log('Manually forcing modal to show');
            this.showWelcomeModal = true;
            this.currentWelcomeSlide = 0;
        },

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
        
        shouldHighlightJob(job) {
            const jobId = job.job_id || job.id;
            return this.highlightJobId && jobId == this.highlightJobId;
        },

        async checkIfNewUser() {
            try {
                const response = await fetch('home?action=checkNewUser');
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Server returned non-JSON response');
                }
                
                const data = await response.json();
                console.log('User status response:', data);
                
                if (data.success) {
                    return {
                        success: true,
                        is_new_user: data.is_new_user,
                        created_at: data.created_at,
                        account_age_days: data.account_age_days,
                        is_today: data.is_today
                    };
                }
                return {success: false, is_new_user: false};
            } catch (error) {
                console.error('Error checking user status:', error);
                return {success: false, is_new_user: false};
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
        },
        matchesSalaryRange(salaryString, selectedRange) {
            if (!salaryString || !selectedRange) {
                console.log('No salary string or selected range, returning true');
                return true;
            }
            
            console.log(`Checking salary: "${salaryString}" against range: ${selectedRange}`);
            
            try {
                let minSalary;
                
                // Remove currency symbols and spaces
                const cleanSalary = salaryString.replace(/[₱\s]/g, '');
                console.log(`Cleaned salary: "${cleanSalary}"`);
                
                // Handle different salary formats
                if (cleanSalary.includes('-')) {
                    // Range format: "50000-70000" or "50,000-70,000"
                    const [minStr] = cleanSalary.split('-');
                    minSalary = parseInt(minStr.replace(/,/g, ''));
                } else if (cleanSalary.includes(',')) {
                    // Single value with comma: "59,000"
                    minSalary = parseInt(cleanSalary.replace(/,/g, ''));
                } else {
                    // Simple number: "60000"
                    minSalary = parseInt(cleanSalary);
                }
                
                console.log(`Parsed minimum salary: ${minSalary}`);
                
                if (isNaN(minSalary)) {
                    console.log('Could not parse salary values');
                    return true;
                }
                
                console.log(`Using minimum salary: ${minSalary}`);
                
                // DO NOT multiply by 1000 - the values are already the actual amount!
                
                switch(selectedRange) {
                    case '10-20':
                        return minSalary >= 10000 && minSalary <= 20000;
                    case '20-30':
                        return minSalary >= 20000 && minSalary <= 30000;
                    case '30-50':
                        return minSalary >= 30000 && minSalary <= 50000;
                    case '50-100':
                        return minSalary >= 50000 && minSalary <= 100000;
                    case '100+':
                        return minSalary > 100000;
                    default:
                        return true;
                }
            } catch (e) {
                console.error('Error parsing salary:', salaryString, e);
                return true;
            }
        },
        async fetchLocationSuggestions() {
            if (!this.locationQuery || this.locationQuery.length < 3) {
                this.locationSuggestions = [];
                this.showLocationDropdown = false;
                return;
            }
            
            const url = `/geocode_proxy?action=autocomplete&text=${encodeURIComponent(this.locationQuery)}&limit=5`;
            
            try {
                const res = await fetch(url);
                const data = await res.json();
                
                // Extract formatted addresses from the response
                this.locationSuggestions = data.features.map(feature => ({
                    place_id: feature.properties.place_id,
                    display_name: feature.properties.formatted
                }));
                
                this.showLocationDropdown = true;
            } catch (e) {
                console.error('Error fetching location suggestions:', e);
                // Fallback to default locations
                this.locationSuggestions = this.defaultLocations
                    .filter(loc => loc.toLowerCase().includes(this.locationQuery.toLowerCase()))
                    .map(loc => ({ place_id: loc, display_name: loc }));
                this.showLocationDropdown = this.locationSuggestions.length > 0;
            }
        },
        selectLocation(location) {
            console.log('Selected location:', location);
            
            // Handle both object and string inputs
            const locationName = typeof location === 'object' ? location.display_name : location;
            
            // Extract just the city/province name from the full formatted address
            const locationParts = locationName.split(', ');
            let selectedLocation = locationParts[0]; // Default to first part
            
            // Try to find a more specific location (city or province)
            const philippineLocations = ['Manila', 'Cebu', 'Davao', 'Laguna', 'Nueva Ecija', 'Bongabon', 'Quezon', 'Cavite', 'Bulacan', 'Pampanga'];
            
            for (const part of locationParts) {
                if (philippineLocations.includes(part)) {
                    selectedLocation = part;
                    break;
                }
            }
            
            this.selectedLocation = selectedLocation;
            this.locationQuery = selectedLocation;
            this.showLocationDropdown = false;
            
            console.log('Processed selectedLocation:', this.selectedLocation);
        },
        hideLocationDropdown() {
            // Use a longer timeout to ensure click events are processed
            setTimeout(() => {
                this.showLocationDropdown = false;
            }, 300);
        },
        handleLogoError(event) {
            // Fallback to the default logo image; guard against re-triggering
            // @error in a loop if this fallback itself ever fails to load.
            const fallback = '/assets/images/logo.png';
            if (event.target.src !== fallback) {
                event.target.src = fallback;
            }
            event.target.classList.add('bg-gray-100', 'dark:bg-gray-600');
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
        async showJobDetails(job) {
            // Find the job by id in jobs array to ensure correct employer_id
            const jobData = this.jobs.find(j => j.id === job.id) || job;
            this.selectedJob = jobData;
            this.showDetails = true;
            // Fetch full employer details
            if (jobData.employer_id) {
                try {
                    const res = await fetch(`home?action=employerDetails&employer_id=${jobData.employer_id}`);
                    const data = await res.json();
                    if (data.success) {
                        this.selectedJob.companyDetails = data.data;
                    }
                } catch (e) {}
            }
            this.$nextTick(async () => {
                // Initialize or update Leaflet map
                if (window.jobMap) {
                    window.jobMap.remove();
                }
                window.jobMap = L.map('job-map').setView([13.9644, 121.1631], 13); // Default: San Pablo
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(window.jobMap);
                // Geocode location
                if (jobData.location) {
                    try {
                        const url = `/geocode_proxy?action=search&text=${encodeURIComponent(jobData.location)}`;
                        const res = await fetch(url);
                        const data = await res.json();
                        if (data.features && data.features.length > 0) {
                            const coords = data.features[0].geometry.coordinates;
                            const latlng = [coords[1], coords[0]];
                            window.jobMap.setView(latlng, 15);
                            L.marker(latlng).addTo(window.jobMap).bindPopup(jobData.location).openPopup();
                        }
                    } catch (e) {}
                }
            });
        },
        hideJobDetails: function() {
            this.showDetails = false;
            document.getElementById('overlay').classList.add('pointer-events-none', 'opacity-0');
            setTimeout(() => {
                if (window.jobMap) {
                    window.jobMap.remove();
                    window.jobMap = null;
                }
            }, 500);
        },
        toggleSave: async function(job) {
            job.saved = !job.saved;
            try {
                const res = await fetch('home?action=toggleSave', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `job_id=${job.id}&action=${job.saved ? 'save' : 'unsave'}`
                });
                const data = await res.json();
                if (data.success) {
                    this.showNotification(job.saved ? 'Job saved!' : 'Job removed from saved jobs.', 'success');
                } else {
                    job.saved = !job.saved; // revert if failed
                    this.showNotification(data.message || 'Failed to save job', 'error');
                }
            } catch (e) {
                job.saved = !job.saved;
                this.showNotification('Failed to save job', 'error');
            }
        },
        confirmLogout: function() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout';
            }
        },
        postedDays(created_at) {
            return window.JobFilters.postedDays(created_at);
        },
        matchesDatePosted(created_at, maxDays) {
            return window.JobFilters.matchesDatePosted(created_at, maxDays);
        },
        applyDarkMode() {
            const html = document.documentElement;
            if (this.darkMode) {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
        },
        toggleDarkMode() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('darkMode', this.darkMode.toString());
            this.applyDarkMode(); // Make sure this is called
        },
        showNotification(message, type = 'success') {
            const id = this.notificationId++;
            this.notifications.push({ id, type, message });
            setTimeout(() => {
                this.notifications = this.notifications.filter(n => n.id !== id);
            }, 3000);
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
            const job = this.jobs.find(j => j.id === this.selectedJob.id);
            if (job && this.isHiredByCompany(job.employer_id)) {
                this.showNotification(`You have already been hired by ${job.company} and cannot apply to other positions.`, 'error');
                this.closeApplicationModal();
                return;
            }
        
            if (!this.applicationResume) {
                this.showNotification('Please upload a resume before submitting.', 'error');
                return;
            }

            if (this.applicationCoverLetter.mode === 'upload' && !this.applicationCoverLetter.file) {
                this.showNotification('Please upload a cover letter file, or choose to write one or skip it.', 'error');
                return;
            }

            if (this.selectedJob.employer_question && Number(this.selectedJob.employer_question_required) && !this.applicationAnswer.trim()) {
                this.showNotification('Please answer the employer question before submitting.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('job_id', this.selectedJob.id);
            formData.append('user_id', window.USER_ID);
            formData.append('resume_file', this.applicationResume.file); // Assuming file is a File object
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
                    this.showNotification('Application submitted successfully!', 'success');
                    // Applying removes the job from Saved Jobs server-side too — reflect it immediately.
                    const appliedJob = this.jobs.find(j => j.id === this.selectedJob.id);
                    if (appliedJob) appliedJob.saved = false;
                    this.closeApplicationModal();
                    this.applicationStep = 1; // Reset step to personal details
                    this.applicationPersonal = null;
                    this.applicationEducation = null;
                    this.applicationSkills = null;
                    this.applicationExperience = null;
                    this.applicationResume = null;
                    this.applicationCoverLetter = { mode: 'skip', file: null, file_name: '', text: '' };
                    this.applicationAnswer = '';
                    this.fetchApplicationData(); // Refresh application data
                } else {
                    this.showNotification(data.message || 'Failed to submit application.', 'error');
                }
            } catch (e) {
                this.showNotification('Failed to submit application.', 'error');
            }
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
            const job = this.jobs.find(j => j.id === jobId);
            
            // Check if user is already hired by this company - IMMEDIATELY BLOCK
            if (job && this.isHiredByCompany(job.employer_id)) {
                this.showNotification(`You have already been hired by ${job.company} and cannot apply to other positions.`, 'error');
                return; // Don't open the modal
            }
            
            this.showApplicationModal = true;
            this.applicationStep = 1;
            this.fetchApplicationData(); // Fetch all data on modal open
            // Set the job ID for the application form
            this.$nextTick(() => {
                const applicationForm = document.getElementById('applicationForm');
                if (applicationForm) {
                    applicationForm.setAttribute('data-job-id', jobId);
                }
            });
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
        selectConversation(index) {
            this.activeConversationIndex = index;
            this.conversations[index].unread = false; // Mark as read
            this.scrollToBottom();
        },
        scrollToBottom() {
            const messagesContainer = this.$refs.messagesContainer;
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        },
        sendMessage() {
            if (!this.newMessage.trim()) return;

            const message = {
                text: this.newMessage,
                sender: 'me',
                time: new Date().toLocaleTimeString([], { hour: 'numeric', minute: 'numeric' })
            };

            if (this.activeConversationIndex !== null) {
                this.conversations[this.activeConversationIndex].messages.push(message);
                this.newMessage = '';
                this.scrollToBottom();
            }
        },
        logout() {
            window.location.href = 'logout';
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
    }
    }
}).mount('#app');


