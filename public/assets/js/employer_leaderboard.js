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
                actionDropdown: null,
                dropdownPosition: { top: 0, left: 0 },
                // Leaderboard data
                matches: [],
                jobs: [],
                selectedJob: '',
                searchQuery: '',
                minPercentage: 60,
                sortBy: 'percentage',
                itemsPerPage: 10,
                currentPage: 1,
                paginationGroupSize: 5,
                
                // Modals
                showAlumniModal: false,
                selectedAlumni: {},
                
                // Filters and data
                notifications: [],
                notificationId: 0,
                employerProfile: {
                    company_name: '',
                    company_logo: ''
                },
                activePage: 'leaderboard',
                
                // In your data() return object, update the statistics property:
                statistics: {
                    totalMatches: 0,
                    highMatches: 0,
                    recentMatches: 0, // Replace applied with this
                    averageMatch: 0
                }
            };
        },
        mounted() {
            this.fetchLeaderboardData();
            this.fetchJobs();
            this.applyDarkMode();
            window.addEventListener('resize', this.handleResize);
            this.handleResize();
            document.addEventListener('click', this.handleClickOutsideDropdown);
            document.addEventListener('click', this.handleClickOutsideProfile);
            
            fetch('employer_profile?action=details')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.profile) {
                        this.employerProfile = data.profile;
                    }
                });
            
            const path = document.location.pathname;
            if (path.endsWith('employer_dashboard.php')) this.activePage = 'dashboard';
            else if (path.endsWith('employer_leaderboard.php')) this.activePage = 'leaderboard';
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
            },
            selectedJob() {
                this.currentPage = 1;
            },
            minPercentage() {
                this.currentPage = 1;
            },
            sortBy() {
                this.currentPage = 1;
            }
        },
        computed: {
            filteredMatches() {
                let filtered = this.matches;
                
                // Filter by selected job
                if (this.selectedJob) {
                    filtered = filtered.filter(match => match.job_id == this.selectedJob);
                }
                
                // Filter by minimum percentage
                if (this.minPercentage > 0) {
                    filtered = filtered.filter(match => match.match_percentage >= this.minPercentage);
                }
                
                // Search filter
                if (this.searchQuery) {
                    const query = this.searchQuery.toLowerCase();
                    filtered = filtered.filter(match =>
                        match.first_name.toLowerCase().includes(query) ||
                        match.last_name.toLowerCase().includes(query) ||
                        match.email.toLowerCase().includes(query) ||
                        match.course.toLowerCase().includes(query) ||
                        match.job_title.toLowerCase().includes(query)
                    );
                }
                
                // Sort results
                switch (this.sortBy) {
                    case 'name':
                        filtered.sort((a, b) => `${a.first_name} ${a.last_name}`.localeCompare(`${b.first_name} ${b.last_name}`));
                        break;
                    case 'course':
                        filtered.sort((a, b) => a.course.localeCompare(b.course));
                        break;
                    case 'percentage':
                    default:
                        filtered.sort((a, b) => b.match_percentage - a.match_percentage);
                        break;
                }
                
                return filtered;
            },
            
            paginatedMatches() {
                const start = (this.currentPage - 1) * this.itemsPerPage;
                return this.filteredMatches.slice(start, start + this.itemsPerPage);
            },
            
            totalPages() {
                return Math.ceil(this.filteredMatches.length / this.itemsPerPage) || 1;
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
            // Navigation and UI methods
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
            
            // Leaderboard data methods
            async fetchLeaderboardData() {
                try {
                    const res = await fetch('employer_matchboard?action=list');
                    const data = await res.json();
                    
                    if (data.success && Array.isArray(data.data)) {
                        this.matches = data.data;
                        this.calculateStatistics();
                    } else {
                        this.showNotification('Failed to fetch leaderboard data', 'error');
                    }
                } catch (error) {
                    this.showNotification('Error fetching leaderboard data', 'error');
                    console.error('Error:', error);
                }
            },
            
            async fetchJobs() {
                try {
                    console.log('Fetching jobs...');
                    const res = await fetch('employer_jobposting?action=list');
                    const data = await res.json();
                    console.log('Jobs API response:', data);
                    
                    if (Array.isArray(data)) {
                        // If the API returns an array directly
                        this.jobs = data.map(job => ({
                            job_id: job.job_id,
                            title: job.title
                        }));
                        console.log('Jobs loaded:', this.jobs);
                    } else if (data.success && Array.isArray(data.jobs)) {
                        // If the API returns {success: true, jobs: [...]}
                        this.jobs = data.jobs.map(job => ({
                            job_id: job.job_id,
                            title: job.title
                        }));
                        console.log('Jobs loaded:', this.jobs);
                    } else {
                        console.log('Failed to fetch jobs or invalid data structure');
                    }
                } catch (error) {
                    console.error('Error fetching jobs:', error);
                }
            },
            
            calculateStatistics() {
                if (this.matches.length === 0) {
                    this.statistics = { 
                        totalMatches: 0, 
                        highMatches: 0, 
                        recentMatches: 0,
                        averageMatch: 0 
                    };
                    return;
                }
                
                // Only count matches that are 60% and above
                const qualifiedMatches = this.matches.filter(m => m.match_percentage >= 60);
                
                this.statistics.totalMatches = qualifiedMatches.length;
                this.statistics.highMatches = qualifiedMatches.filter(m => m.match_percentage >= 80).length;
                
                // Calculate matches from the last 7 days (60%+ only)
                const sevenDaysAgo = new Date();
                sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
                
                this.statistics.recentMatches = qualifiedMatches.filter(match => {
                    const matchDate = new Date(match.matched_at);
                    return matchDate >= sevenDaysAgo;
                }).length;
                
                // Calculate average from qualified matches only
                const totalPercentage = qualifiedMatches.reduce((sum, match) => sum + match.match_percentage, 0);
                this.statistics.averageMatch = qualifiedMatches.length > 0 ? Math.round(totalPercentage / qualifiedMatches.length) : 0;
            },
            
            toggleActionDropdown(matchId, event) {
                if (this.actionDropdown === matchId) {
                    this.actionDropdown = null;
                    return;
                }
                this.actionDropdown = matchId;
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

            // In the viewAlumniProfile method, update the skills processing:
            async viewAlumniProfile(match) {
                try {
                    console.log('Fetching alumni details for ID:', match.alumni_id);
                    
                    // Fetch detailed alumni information
                    const res = await fetch('employer_matchboard?action=list');
                    const data = await res.json();
                    
                    console.log('Full API response:', data);
                    
                    if (data.success) {
                        // Find the specific alumni in the data array
                        const alumniData = data.data.find(item => item.alumni_id === match.alumni_id);
                        
                        if (!alumniData) {
                            console.log('Alumni not found in response data');
                            this.showNotification('Alumni data not found', 'error');
                            return;
                        }
                        
                        console.log('Found alumni data:', alumniData);
                        console.log('Skills with certificates:', alumniData.skills); // Debug skills with certificates
                        
                        this.selectedAlumni = {
                            ...match,
                            ...alumniData,
                            skills_match: this.calculateSkillsMatch(match),
                            experiences: alumniData.experiences || [],
                            educations: alumniData.educations || [],
                            resume_file: alumniData.resume_file || match.file_name,
                            profile_picture: alumniData.profile_pic,
                            skills: alumniData.skills || [] // This now includes certificate and certificate_file
                        };
                        
                        console.log('Final alumni data with skills:', this.selectedAlumni.skills);
                        this.showAlumniModal = true;
                    } else {
                        console.log('API returned success: false');
                        this.showNotification('Failed to load alumni details', 'error');
                    }
                } catch (error) {
                    console.error('Error in viewAlumniProfile:', error);
                    this.showNotification('Error loading alumni profile', 'error');
                }
            },

            getFileIcon(filename) {
                if (!filename) return 'fas fa-file text-gray-400';
                const ext = filename.split('.').pop().toLowerCase();
                const icons = {
                    pdf: 'fas fa-file-pdf text-red-500',
                    jpg: 'fas fa-file-image text-green-500',
                    jpeg: 'fas fa-file-image text-green-500',
                    png: 'fas fa-file-image text-green-500',
                    gif: 'fas fa-file-image text-green-500',
                    doc: 'fas fa-file-word text-blue-500',
                    docx: 'fas fa-file-word text-blue-500',
                    default: 'fas fa-file text-gray-400'
                };
                return icons[ext] || icons.default;
            },
            
            // Add this method to get certificate URLs
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
            
            calculateSkillsMatch(match) {
                // This would be calculated based on job requirements vs alumni skills
                // For now, return a random value between match_percentage-10 and match_percentage+10
                const min = Math.max(0, match.match_percentage - 10);
                const max = Math.min(100, match.match_percentage + 10);
                return Math.floor(Math.random() * (max - min + 1)) + min;
            },
            
            closeAlumniModal() {
                this.showAlumniModal = false;
                this.selectedAlumni = {};
            },
            
            // Action methods
            async contactAlumni(alumni) {
                try {
                    // Redirect to messages page with alumni email as parameter
                    const messagesUrl = `employer_messages.php?compose=true&to=${encodeURIComponent(alumni.email)}`;
                    window.location.href = messagesUrl;
                } catch (error) {
                    console.error('Error redirecting to messages:', error);
                    this.showNotification('Error opening message composer', 'error');
                }
            },
            
            async markAsApplied(match) {
                try {
                    const res = await fetch('functions/update_match_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            match_id: match.match_id,
                            status: 'applied'
                        })
                    });
                    
                    const data = await res.json();
                    if (data.success) {
                        this.showNotification('Marked as applied successfully', 'success');
                        this.fetchLeaderboardData(); // Refresh data
                    } else {
                        this.showNotification('Failed to update status', 'error');
                    }
                } catch (error) {
                    this.showNotification('Error updating status', 'error');
                }
            },
            
            downloadProfile(alumni) {
                this.showNotification('Downloading profile...', 'info');
                // Implement download functionality
            },
            
            // Utility methods
            formatDate(date) {
                return new Date(date).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            },
            
            getPercentageColor(percentage) {
                if (percentage >= 80) return 'border-green-500 text-green-600 bg-green-50 dark:bg-green-900 dark:text-green-300';
                if (percentage >= 60) return 'border-yellow-500 text-yellow-600 bg-yellow-50 dark:bg-yellow-900 dark:text-yellow-300';
                return 'border-red-500 text-red-600 bg-red-50 dark:bg-red-900 dark:text-red-300';
            },
            
            // Pagination methods
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

            // Export methods
            exportLeaderboardExcel() {
                let csv = 'Rank,Name,Email,Course,Job Title,Match %,Status,Matched On\n';
                this.filteredMatches.forEach((match, index) => {
                    csv += `${index + 1},${match.first_name} ${match.last_name},${match.email},${match.course},${match.job_title},${match.match_percentage}%,${match.status || 'pending'},${this.formatDate(match.matched_at)}\n`;
                });
                
                const blob = new Blob([csv], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'leaderboard_export.csv';
                a.click();
                window.URL.revokeObjectURL(url);
                
                this.showNotification('Leaderboard exported to Excel successfully!', 'success');
            },
            
            // Notification methods
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