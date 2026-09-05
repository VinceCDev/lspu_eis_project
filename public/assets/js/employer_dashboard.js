const { createApp } = Vue;
    createApp({
        data() {
            return {
                sidebarActive: window.innerWidth >= 768,
                darkMode: localStorage.getItem('darkMode') === 'true' || 
                     (localStorage.getItem('darkMode') === null && 
                      window.matchMedia('(prefers-color-scheme: dark)').matches),
                isMobile: window.innerWidth < 768,
                employerProfile: { company_name: '', company_logo: '' },
                activeJobs: 0,
                jobsChange: 0,
                showLogoutModal: false,
                totalApplicants: 0,
                applicantsChange: 0,
                applicationsCount: 0,
                totalInterviews: 0, // Add this
                interviewsChange: 0, // Add this
                profileViews: 0,
                jobs: [],
                applicants: [],
                charts: {},
                chartInitialized: false,
                hiredCount: 0,
                calendarWeeks: [],
                profileDropdownOpen: false,
                calendarMonth: (new Date()).getMonth(),
                calendarYear: (new Date()).getFullYear(),
                activePage: 'dashboard', // new property
                selectedDate: null,
                showInterviewModal: false,
                dateInterviews: [],
                applicationStatusCounts: {
                    pending: 0,
                    interview: 0,
                    hired: 0,
                    rejected: 0
                },
            }
        },
        mounted() {
            this.applyDarkMode();
            this.fetchEmployerProfile();
            this.fetchJobsAndApplicants();
            this.generateCalendar();
            // Set activePage based on file name
            const path = document.location.pathname;
            if (path.endsWith('employer_dashboard.php')) this.activePage = 'dashboard';
            else if (path.endsWith('employer_jobposting.php')) this.activePage = 'jobs';
            else if (path.endsWith('employer_applicants.php')) this.activePage = 'applicants';
            else if (path.endsWith('employer_messages.php')) this.activePage = 'messages';
            window.addEventListener('resize', this.handleResize);
            document.addEventListener('click', this.handleClickOutside);
        },
        beforeUnmount() {
            document.removeEventListener('click', this.handleClickOutside);
        },
        watch: {
            darkMode(val) {
                this.applyDarkMode();
                this.reinitializeCharts();
            }
        },
        methods: {
            toggleSidebar() {
                this.sidebarActive = !this.sidebarActive;
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
            applyDarkMode() {
                if (this.darkMode) {
                    document.documentElement.classList.add('dark');
                    document.body.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.body.classList.remove('dark');
                }
            },
            fetchEmployerProfile() {
                fetch('employer_profile?action=details')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.profile) {
                            this.employerProfile = data.profile;
                        }
                    });
            },
            async fetchJobsAndApplicants() {
                // Fetch jobs
                const jobsRes = await fetch('employer_jobposting?action=list');
                const jobsData = await jobsRes.json();
                this.jobs = jobsData || [];
                
                // Fetch applicants
                const appsRes = await fetch('/employer_applicants?action=list');
                const appsData = await appsRes.json();
                this.applicants = (appsData && appsData.applications) ? appsData.applications : [];
                
                // Fetch interview count
                const interviewsRes = await fetch('/employer_dashboard?action=interviewCount');
                const interviewsData = await interviewsRes.json();
                
                // Fetch application status counts
                const statusRes = await fetch('/employer_dashboard?action=statusCounts');
                const statusData = await statusRes.json();
                
                // Update cards
                this.activeJobs = this.jobs.filter(j => j.status === 'Active').length;
                this.totalApplicants = this.applicants.length;
                this.totalInterviews = interviewsData.success ? interviewsData.total_interviews : 0;
                
                // Update status counts
                if (statusData.success) {
                    this.applicationStatusCounts = statusData.status_counts;
                }
                
                // Fetch hired count
                fetch('/employer_dashboard?action=hiredCount')
                    .then(res => res.json())
                    .then(data => {
                        this.hiredCount = data.hired_count || 0;
                    });
                
                // For demo, set static values for change
                this.jobsChange = 0;
                this.applicantsChange = 0;
                this.interviewsChange = 0;
                
                // Draw charts after DOM update
                this.$nextTick(() => this.initCharts());
            },
            initCharts() {
                if (this.chartInitialized) {
                    Object.values(this.charts).forEach(chart => chart && chart.destroy());
                }
                // Applicants by Course (horizontal bar, course names on y-axis, fill space)
                const courseCounts = {};
                this.applicants.forEach(a => {
                    const course = a.course || a.job?.course || 'Unknown';
                    courseCounts[course] = (courseCounts[course] || 0) + 1;
                });
                const courseLabels = Object.keys(courseCounts);
                const courseData = Object.values(courseCounts);
                this.charts.course = new Chart(document.getElementById('applicantsByCourseChart'), {
                    type: 'bar',
                    data: {
                        labels: courseLabels,
                        datasets: [{
                            label: 'Applicants',
                            data: courseData,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)'
                        }]
                    },
                    options: {
                        indexAxis: 'x', // vertical bars
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: true,
                                callbacks: {
                                    title: function(context) {
                                        return courseLabels[context[0].dataIndex];
                                    },
                                    label: function(context) {
                                        return 'Applicants: ' + context.parsed.y;
                                    }
                                }
                            }
                        },
                        layout: {
                            padding: { left: 20, right: 20, top: 10, bottom: 30 }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    color: this.darkMode ? '#e5e7eb' : '#374151',
                                    font: { size: 13 },
                                    callback: function(value, index) {
                                        const label = courseLabels[index] || '';
                                        return label.length > 20 ? label.slice(0, 18) + '…' : label;
                                    },
                                    maxRotation: 45,
                                    minRotation: 0,
                                    autoSkip: false
                                },
                                grid: { color: this.darkMode ? '#374151' : '#e5e7eb' }
                            },
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Applicants', color: this.darkMode ? '#e5e7eb' : '#374151' },
                                ticks: { color: this.darkMode ? '#e5e7eb' : '#374151', font: { size: 14 } },
                                grid: { color: this.darkMode ? '#374151' : '#e5e7eb' }
                            }
                        }
                    }
                });
                // Applicants by Status (doughnut)
                const statusCounts = {};
                this.applicants.forEach(a => {
                    const status = a.status || a.job_status || 'Unknown';
                    statusCounts[status] = (statusCounts[status] || 0) + 1;
                });
                const statusLabels = Object.keys(statusCounts);
                const statusData = Object.values(statusCounts);
                // Replace the existing status chart with this:
                // Application Status (doughnut) - Pending, Interview, Hired, Rejected
                this.charts.status = new Chart(document.getElementById('applicantsByStatusChart'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Pending', 'Interview', 'Hired', 'Rejected'],
                        datasets: [{
                            data: [
                                this.applicationStatusCounts.pending,
                                this.applicationStatusCounts.interview,
                                this.applicationStatusCounts.hired,
                                this.applicationStatusCounts.rejected
                            ],
                            backgroundColor: [
                                'rgba(255, 206, 86, 0.7)',  // Yellow for Pending
                                'rgba(54, 162, 235, 0.7)',  // Blue for Interview
                                'rgba(75, 192, 192, 0.7)',  // Green for Hired
                                'rgba(255, 99, 132, 0.7)'   // Red for Rejected
                            ],
                            borderColor: [
                                'rgba(255, 206, 86, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(255, 99, 132, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: this.darkMode ? '#e5e7eb' : '#374151',
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
                // Job Listings by Type (pie)
                const typeCounts = {};
                this.jobs.forEach(j => {
                    const type = j.type || 'Unknown';
                    typeCounts[type] = (typeCounts[type] || 0) + 1;
                });
                const typeLabels = Object.keys(typeCounts);
                const typeData = Object.values(typeCounts);
                this.charts.type = new Chart(document.getElementById('jobsByTypeChart'), {
                    type: 'pie',
                    data: {
                        labels: typeLabels,
                        datasets: [{
                            data: typeData,
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(255, 206, 86, 0.7)',
                                'rgba(255, 99, 132, 0.7)',
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(153, 102, 255, 0.7)'
                            ]
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
                // Applicants by Year Graduated (line)
                const yearCounts = {};
                this.applicants.forEach(a => {
                    const year = a.year_graduated || 'Unknown';
                    yearCounts[year] = (yearCounts[year] || 0) + 1;
                });
                const yearLabels = Object.keys(yearCounts);
                const yearData = Object.values(yearCounts);
                this.charts.year = new Chart(document.getElementById('applicantsByYearChart'), {
                    type: 'line',
                    data: {
                        labels: yearLabels,
                        datasets: [{
                            label: 'Applicants',
                            data: yearData,
                            fill: false,
                            borderColor: 'rgba(255, 159, 64, 0.9)',
                            backgroundColor: 'rgba(255, 159, 64, 0.7)',
                            tension: 0.3
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
                this.chartInitialized = true;
            },
            generateCalendar() {
                // Google Calendar-like: show days from prev/next month, grid, highlight today
                const year = this.calendarYear;
                const month = this.calendarMonth;
                const firstDay = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const prevMonthDays = new Date(year, month, 0).getDate();
                let weeks = [];
                let week = [];
                let day = 1;
                let nextMonthDay = 1;
                // Fill first week
                for (let j = 0; j < 7; j++) {
                    if (j < firstDay) {
                        week.push({ day: prevMonthDays - firstDay + j + 1, monthOffset: -1 });
                    } else {
                        week.push({ day: day++, monthOffset: 0 });
                    }
                }
                weeks.push(week);
                // Fill rest
                while (day <= daysInMonth) {
                    week = [];
                    for (let j = 0; j < 7; j++) {
                        if (day > daysInMonth) {
                            week.push({ day: nextMonthDay++, monthOffset: 1 });
                        } else {
                            week.push({ day: day++, monthOffset: 0 });
                        }
                    }
                    weeks.push(week);
                }
                this.calendarWeeks = weeks;
            },
            isToday(day, monthOffset = 0) {
                const today = new Date();
                const todayDay = today.getDate();
                const todayMonth = today.getMonth();
                const todayYear = today.getFullYear();
                
                let checkYear = this.calendarYear;
                let checkMonth = this.calendarMonth + monthOffset;
                
                // Adjust year if month goes out of bounds
                if (checkMonth < 0) {
                    checkMonth = 11;
                    checkYear--;
                } else if (checkMonth > 11) {
                    checkMonth = 0;
                    checkYear++;
                }
                
                return day === todayDay && 
                       checkMonth === todayMonth && 
                       checkYear === todayYear;
            },
            async onDateClick(day, monthOffset) {
                if (day.day <= 0) return;
                
                // Calculate actual date considering month offset
                let year = this.calendarYear;
                let month = this.calendarMonth + monthOffset;
                
                if (month < 0) {
                    month = 11;
                    year--;
                } else if (month > 11) {
                    month = 0;
                    year++;
                }
                
                // Create date in local timezone (not UTC)
                const date = new Date(year, month, day.day);
                
                // Format as YYYY-MM-DD in local timezone
                const formattedDate = 
                    date.getFullYear() + '-' + 
                    String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(date.getDate()).padStart(2, '0');
                
                console.log('Clicked calendar date:', {
                    day: day.day,
                    month: month,
                    year: year,
                    formattedDate: formattedDate,
                    utcDate: date.toISOString()
                });
                
                this.selectedDate = formattedDate;
                
                try {
                    const response = await fetch(`/employer_dashboard?action=interviewsByDate&date=${formattedDate}`);
                    const data = await response.json();
                    console.log('API Response:', data);
                    
                    if (data.success) {
                        this.dateInterviews = data.interviews;
                        this.showInterviewModal = true;
                    } else {
                        this.dateInterviews = [];
                        this.showInterviewModal = true;
                    }
                } catch (error) {
                    console.error('Error fetching interviews:', error);
                    this.dateInterviews = [];
                    this.showInterviewModal = true;
                }
            },
            formatInterviewTime(interviewDate) {
                const date = new Date(interviewDate);
                // Add 8 hours to convert from UTC to Philippines time
                const phDate = new Date(date.getTime() + (8 * 60 * 60 * 1000));
                return phDate.toLocaleTimeString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit',
                    hour12: true 
                });
            },
            
            getStatusBadgeClass(status) {
                const statusClasses = {
                    'Scheduled': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                    'Completed': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                    'Cancelled': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                    'Rescheduled': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                };
                return statusClasses[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
            },
            
            closeInterviewModal() {
                this.showInterviewModal = false;
                this.dateInterviews = [];
                this.selectedDate = null;
            },
            toggleProfileDropdown(event) {
                event.stopPropagation();
                this.profileDropdownOpen = !this.profileDropdownOpen;
            },
            toggleDarkMode() {
                this.darkMode = !this.darkMode;
                localStorage.setItem('darkMode', this.darkMode);
                this.applyDarkMode();
            },
            handleClickOutside(event) {
                if (this.profileDropdownOpen && !event.target.closest('.profile-dropdown-wrapper')) {
                    this.profileDropdownOpen = false;
                }
            },
            prevMonth() {
                if (this.calendarMonth === 0) {
                    this.calendarMonth = 11;
                    this.calendarYear--;
                } else {
                    this.calendarMonth--;
                }
                this.generateCalendar();
            },
            nextMonth() {
                if (this.calendarMonth === 11) {
                    this.calendarMonth = 0;
                    this.calendarYear++;
                } else {
                    this.calendarMonth++;
                }
                this.generateCalendar();
            },
        },
        computed: {
            calendarMonthYear() {
                const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                return months[this.calendarMonth] + ' ' + this.calendarYear;
            }
        }
    }).mount('#app');