const { createApp } = Vue;
createApp({
    data() {
        return {
            sidebarActive: window.innerWidth >= 768,
            alumniDropdownOpen: true,
            companiesDropdownOpen: false,
            systemDropdownOpen: false,
            profileDropdownOpen: false,
            darkMode: localStorage.getItem('darkMode') === 'true' || 
                     (localStorage.getItem('darkMode') === null && 
                      window.matchMedia('(prefers-color-scheme: dark)').matches),
            showLogoutModal: false,
            isMobile: window.innerWidth < 768,
            notifications: [],
            notificationId: 0,
            actionDropdown: null,
            alumni: [],
            searchQuery: '',
            itemsPerPage: 5,
            currentPage: 1,
            paginationGroupSize: 5,
            showViewModal: false,
            viewAlumniData: {},
            showDeleteModal: false,
            alumniToDelete: null,
            filters: {
                campus_id: '',
                college: '',
                course: ''
            },
            campuses: [],
            isSuperadmin: false,
            currentCampusId: null,
            // Campus -> College -> Course/Program data for all LSPU campuses (mirrors admin_alumni.js).
            campusCollegeCourses: {
                "Santa Cruz": {
                    "College of Arts and Sciences": ["BA Broadcasting", "BS Biology", "BS Chemistry", "BS Mathematics", "BS Psychology"],
                    "College of Business Administration and Accountancy": ["BS Office Administration", "BS Entrepreneurship", "BS Accountancy"],
                    "College of Computer Studies": ["BS Information Technology", "BS Computer Science", "MS Information Technology"],
                    "College of Criminal Justice Education": ["BS Criminology"],
                    "College of Engineering": ["BS Electronics Engineering", "BS Mechanical Engineering", "BS Electrical Engineering", "BS Civil Engineering", "BS Computer Engineering"],
                    "College of Industrial Technology": ["BS Industrial Technology"],
                    "College of International Hospitality and Tourism Management": ["BS Hospitality Management", "BS Tourism Management"],
                    "College of Nursing and Allied Health": ["BS Nursing"],
                    "College of Teacher Education": ["Bachelor of Secondary Education", "Bachelor of Elementary Education", "Bachelor of Technical-Vocational Teacher Education", "Bachelor of Physical Education", "Bachelor of Technology and Livelihood Education"]
                },
                "Siniloan": {
                    "College of Agriculture": ["BS Agriculture (Major in Animal Science)", "BS Agriculture (Major in Crop Science)", "BS Agricultural Business", "MS Agriculture (Animal Science)", "MS Agriculture (Crop Science)", "MS Agriculture Education", "PhD Agriculture (Animal Science)", "PhD Agriculture (Crop Science)"],
                    "College of Arts and Sciences": ["BS Psychology"],
                    "College of Business Administration and Accountancy": ["BS Office Administration (Legal Office Procedure)", "BS Office Administration (Medical Office Procedure)", "BS Business Administration (Financial Management)", "BS Business Administration (Marketing Management)", "BS Accountancy"],
                    "College of Computer Studies": ["BS Computer Science", "BS Information Technology", "BS Information System", "Master in Information Technology"],
                    "College of Criminal Justice Education": ["BS Criminology"],
                    "College of Engineering": ["BS Agricultural and Biosystems Engineering", "BS Computer Engineering", "BS Mechanical Engineering", "BS Industrial Technology"],
                    "College of International Hospitality and Tourism Management": ["BS Hospitality Management", "BS Tourism Management"],
                    "College of Teacher Education": [
                        "Bachelor of Secondary Education (English)", "Bachelor of Secondary Education (Filipino)", "Bachelor of Secondary Education (Science)", "Bachelor of Secondary Education (Mathematics)", "Bachelor of Secondary Education (Social Studies)", "Bachelor of Secondary Education (Values Education)",
                        "Bachelor of Elementary Education", "Bachelor of Early Childhood Education", "Bachelor of Physical Education",
                        "Bachelor of Technical-Vocational Teacher Education (Agricultural Crops Production)", "Bachelor of Technology and Livelihood Education (Home Economics)",
                        "Master of Arts in Education (English)", "Master of Arts in Education (Filipino)", "Master of Arts in Education (Science and Technology)", "Master of Arts in Education (Mathematics)", "Master of Arts in Education (Social Science)", "Master of Arts in Education (Physical Education)", "Master of Arts in Education (Guidance and Counseling)", "Master of Arts in Education (Technology and Home Economics)", "Master of Arts in Education (Educational Management)",
                        "Doctor of Philosophy in Education (Educational Leadership and Management)"
                    ]
                },
                "Los Baños": {
                    "College of Arts and Sciences": ["BS Psychology"],
                    "College of Business Administration and Accountancy": ["BS Accountancy", "BS Business Administration"],
                    "College of Computer Studies": ["BS Information Technology", "BS Computer Science"],
                    "College of Criminal Justice Education": ["BS Criminology"],
                    "College of Fisheries": ["BS Fisheries", "BS Agri-Fisheries Business Management", "BS Fishery Education"],
                    "College of Food Nutrition and Dietetics": ["BS Food Technology", "BS Nutrition and Dietetics"],
                    "College of International Hospitality and Tourism Management": ["BS Hotel and Restaurant Management", "BS Tourism Management"],
                    "College of Teacher Education": ["Bachelor of Secondary Education (Mathematics)", "Bachelor of Secondary Education (MAPEH)", "Bachelor of Secondary Education (Technology & Livelihood Education)", "Bachelor of Secondary Education (English)", "Bachelor of Secondary Education (Filipino)", "Bachelor of Secondary Education (General Science)", "Bachelor of Elementary Education"]
                },
                "San Pablo": {
                    "College of Arts and Sciences": ["BS Biology", "BS Psychology"],
                    "College of Business Administration and Accountancy": ["BS Office Administration", "BS Business Administration (Financial Management)", "BS Business Administration (Marketing Management)", "BS Accountancy"],
                    "College of Computer Studies": ["BS Information Technology (Animation and Motion Graphics)", "BS Information Technology (Service Management Program)", "BS Information Technology (Web and Mobile Application Development)", "BS Information Technology (Network Administration)", "BS Computer Science (Graphics and Visualization)", "BS Computer Science (Intelligent Systems)", "Master in Information Technology"],
                    "College of Criminal Justice Education": ["BS Criminology"],
                    "College of Engineering": ["BS Electronics Engineering", "BS Electrical Engineering", "BS Computer Engineering"],
                    "College of Industrial Technology": ["BS Industrial Technology (Automotive Technology)", "BS Industrial Technology (Architectural Drafting)", "BS Industrial Technology (Electrical Technology)", "BS Industrial Technology (Electronics Technology)", "BS Industrial Technology (Food and Beverage Preparation and Service Management Technology)", "BS Industrial Technology (Heating, Ventilating, Air-Conditioning and Refrigeration Technology)"],
                    "College of International Hospitality and Tourism Management": ["BS Hospitality Management", "BS Tourism Management"],
                    "College of Teacher Education": [
                        "Bachelor of Secondary Education (English)", "Bachelor of Secondary Education (Filipino)", "Bachelor of Secondary Education (Mathematics)", "Bachelor of Secondary Education (Science)", "Bachelor of Secondary Education (Social Science)",
                        "Bachelor of Elementary Education", "Bachelor of Physical Education",
                        "Bachelor of Technology and Livelihood Education (Home Economics)", "Bachelor of Technology and Livelihood Education (Industrial Arts)",
                        "Bachelor of Technical-Vocational Teacher Education (Electrical Technology)", "Bachelor of Technical-Vocational Teacher Education (Electronics Technology)", "Bachelor of Technical-Vocational Teacher Education (Food and Service Management)", "Bachelor of Technical-Vocational Teacher Education (Garments, Fashion and Design)",
                        "Doctor of Education",
                        "Master of Arts in Education (Educational Management)", "Master of Arts in Education (English)", "Master of Arts in Education (Filipino)", "Master of Arts in Education (Guidance and Counseling)", "Master of Arts in Education (Mathematics)", "Master of Arts in Education (Physical Education)", "Master of Arts in Education (Science and Technology)", "Master of Arts in Education (Social Studies)", "Master of Arts in Education (Technology and Livelihood Education)",
                        "Doctor of Philosophy in Education (English Language Education)", "Doctor of Philosophy in Education (Edukasyong Pangwika sa Filipino)", "Doctor of Philosophy in Education (Mathematics Education)", "Doctor of Philosophy in Education (Science Education)", "Doctor of Philosophy in Education (Educational Management)"
                    ]
                },
                "Nagcarlan": {
                    "College of Agriculture": ["BS Agriculture (Major in Agricultural Extension)"]
                },
                "Lopez Quezon": {
                    "College of Agriculture": ["BS Agriculture (Major in Crop Science)"]
                }
            },
            profile: {
                profile_pic: '',
                name: '',
            }
            };
    },
    computed: {
        filteredAlumni() {
            // Ensure we're always working with an array
            if (!Array.isArray(this.alumni)) {
                console.warn('Alumni data is not an array:', this.alumni);
                return [];
            }
            
            let filtered = this.alumni;
                
            // Search
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                filtered = filtered.filter(a =>
                    (`${a.first_name} ${a.middle_name} ${a.last_name}`.toLowerCase().includes(q) ||
                    a.email.toLowerCase().includes(q) ||
                    a.gender.toLowerCase().includes(q) ||
                        a.year_graduated.toString().includes(q) ||
                    a.course.toLowerCase().includes(q) ||
                    a.college.toLowerCase().includes(q) ||
                    a.province.toLowerCase().includes(q) ||
                    a.city.toLowerCase().includes(q) ||
                    a.status.toLowerCase().includes(q))
                );
            }
                
            // Filters
            if (this.isSuperadmin && this.filters.campus_id) {
                filtered = filtered.filter(a => String(a.campus_id) === String(this.filters.campus_id));
            }
            if (this.filters.college) {
                filtered = filtered.filter(a => a.college === this.filters.college);
            }
            if (this.filters.course) {
                filtered = filtered.filter(a => a.course === this.filters.course);
            }
            return filtered;
        },
        paginatedAlumni() {
            // Add a safety check
            if (!Array.isArray(this.filteredAlumni)) {
                console.warn('Filtered alumni is not an array:', this.filteredAlumni);
                return [];
            }
            
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.filteredAlumni.slice(start, start + this.itemsPerPage);
        },
        totalPages() {
            return Math.ceil(this.filteredAlumni.length / this.itemsPerPage) || 1;
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
            // Colleges available in the filter bar's College select, scoped to the chosen
            // Campus filter (superadmin) or the admin's own campus. Falls back to every
            // college across all campuses when superadmin hasn't picked a campus yet.
            filterColleges() {
                const campusName = this.isSuperadmin
                    ? (this.filters.campus_id ? this.campusNameById(this.filters.campus_id) : null)
                    : this.campusNameById(this.currentCampusId);
                if (campusName && this.campusCollegeCourses[campusName]) {
                    return Object.keys(this.campusCollegeCourses[campusName]);
                }
                const all = Object.values(this.campusCollegeCourses).flatMap(colleges => Object.keys(colleges));
                return [...new Set(all)];
            },
            filterCourseOptions() {
                const campusName = this.isSuperadmin
                    ? (this.filters.campus_id ? this.campusNameById(this.filters.campus_id) : null)
                    : this.campusNameById(this.currentCampusId);
                const collegeCourses = campusName ? (this.campusCollegeCourses[campusName] || {}) : null;

                if (this.filters.college) {
                    return collegeCourses ? (collegeCourses[this.filters.college] || []) : [];
                }
                if (collegeCourses) {
                    return [...new Set(Object.values(collegeCourses).flat())];
                }
                const all = Object.values(this.campusCollegeCourses).flatMap(colleges => Object.values(colleges).flat());
                return [...new Set(all)];
            }
        },
        mounted() {
            this.applyDarkMode();
            window.addEventListener('resize', this.handleResize);
            document.addEventListener('click', this.handleClickOutsideDropdown);
            document.addEventListener('click', this.handleClickOutsideProfile);
            this.fetchCampuses();
            this.fetchAlumni();
            this.fetchAlumniWithDetails();
            fetch('admin_profile?action=details')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.profile) {
                        this.profile = data.profile;
                    }
                });
        },
        beforeUnmount() {
            window.removeEventListener('resize', this.handleResize);
        },
        watch: {

            'filters.college'(val) {
                this.filters.course = '';
        },
        'filters.campus_id'() {
            this.filters.college = '';
            this.filters.course = '';
        },
        darkMode(val) {
            this.applyDarkMode();
        }
    },
    methods: {
            async fetchCampuses() {
                try {
                    const response = await fetch('/admin_user?action=campuses');
                    const data = await response.json();
                    if (data.success) {
                        this.campuses = data.campuses || [];
                        this.isSuperadmin = !!data.is_superadmin;
                        this.currentCampusId = data.current_campus_id ?? null;
                    }
                } catch (error) {
                    this.campuses = [];
                }
            },
            campusNameById(campusId) {
                const campus = this.campuses.find(c => String(c.campus_id) === String(campusId));
                return campus ? campus.name : null;
            },
            fetchAlumni() {
                fetch('/admin_alumni_pending?action=pendingList')
                    .then(res => res.json())
                    .then(data => {
                        this.alumni = Array.isArray(data.alumni) ? data.alumni : [];
                    });
            },
            // Add this method to fetch alumni with detailed information
            async fetchAlumniWithDetails() {
                this.isLoading = true;
                try {
                    const response = await fetch('/admin_alumni_pending?action=pendingList');
                    const data = await response.json();

                    if (!data || !data.alumni || !Array.isArray(data.alumni)) {
                        console.error('Invalid data structure received:', data);
                        this.showNotification('Invalid data received from server', 'error');
                        this.alumni = []; // Reset to empty array
                        return;
                    }
                    
                    // The pendingList endpoint already includes each alumni's
                    // skills/education/experience/resume (batched server-side) —
                    // no more per-alumni follow-up requests needed here.
                    this.alumni = data.alumni.map(alumni => {
                        const experience = alumni.experience || [];
                        let resume = null;
                        if (alumni.resume && alumni.resume.file_name) {
                            resume = {
                                ...alumni.resume,
                                url: 'uploads/resume/' + alumni.resume.file_name
                            };
                        }

                        return {
                            alumni_id: alumni.alumni_id,
                            first_name: alumni.first_name,
                            middle_name: alumni.middle_name,
                            last_name: alumni.last_name,
                            email: alumni.email,
                            secondary_email: alumni.secondary_email,
                            gender: alumni.gender,
                            year_graduated: alumni.year_graduated,
                            course: alumni.course,
                            college: alumni.college,
                            province: alumni.province,
                            city: alumni.city,
                            status: alumni.status,
                            birthdate: alumni.birthdate,
                            contact: alumni.contact,
                            civil_status: alumni.civil_status,
                            verification_document: alumni.verification_document,
                            profile_picture: alumni.profile_picture,
                            skills: alumni.skills || [],
                            education: alumni.education || [],
                            experiences: experience,
                            resume: resume,
                            employment: experience.length > 0 ? {
                                company_name: experience[0].company,
                                position: experience[0].title,
                                status: experience[0].employment_status,
                                years: this.calculateYears(experience[0].start_date, experience[0].end_date)
                            } : null
                        };
                    });
                } catch (error) {
                    console.error('Error fetching alumni:', error);
                    this.showNotification('Error loading alumni data', 'error');
                    this.alumni = [];
                } finally {
                    this.isLoading = false;
                }
            },

            // Add this method to view alumni details
            viewAlumniDetails(alumni) {
                this.viewAlumniData = {
                    ...alumni,
                    skills: alumni.skills || [],
                    experiences: alumni.experiences || [],
                    documents: alumni.documents || [],
                    employment: alumni.employment,
                    profile_picture: alumni.profile_picture // Correct path: /uploads/profile_picture/
                };
                this.showViewModal = true;
                this.$nextTick(() => this.focusFirstInput('view-modal'));
            },

            // Add this method to close the view modal
            closeViewModal() {
                this.showViewModal = false;
                this.viewAlumniData = null;
            },

            // Add this helper method for file icons
            getFileIcon(filename) {
                if (!filename) return 'fas fa-file text-gray-400';
                
                const extension = filename.split('.').pop().toLowerCase();
                switch (extension) {
                    case 'pdf':
                        return 'fas fa-file-pdf text-red-500';
                    case 'doc':
                    case 'docx':
                        return 'fas fa-file-word text-blue-500';
                    case 'xls':
                    case 'xlsx':
                        return 'fas fa-file-excel text-green-500';
                    case 'jpg':
                    case 'jpeg':
                    case 'png':
                    case 'gif':
                        return 'fas fa-file-image text-purple-500';
                    default:
                        return 'fas fa-file text-gray-400';
                }
            },

            // Add this method to calculate years of experience
            calculateYears(startDate, endDate) {
                if (!startDate) return 0;
                
                const start = new Date(startDate);
                const end = endDate ? new Date(endDate) : new Date();
                
                const years = end.getFullYear() - start.getFullYear();
                const months = end.getMonth() - start.getMonth();
                
                return years + (months >= 0 ? 0 : -1);
            },

            // Add this method to format dates
            formatDate(dateString) {
                if (!dateString) return 'N/A';
                
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
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

            confirmLogout() {
                this.showLogoutModal = true;
            },
            approveAlumni(alumni) {
                fetch('/admin_alumni_pending?action=approve', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ alumni_id: alumni.alumni_id })
                })
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        this.fetchAlumni();
                        this.showNotification('Alumni approved!', 'success');
                    } else {
                        this.showNotification('Failed to approve alumni.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error approving alumni:', error);
                    this.showNotification('Error approving alumni.', 'error');
                });
            },
            deleteAlumni(alumni) {
                fetch('/admin_alumni_pending?action=destroy', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ alumni_id: alumni.alumni_id })
                })
                .then(res => res.json())
                .then(response => {
                    if (response.success) {
                        this.fetchAlumni();
                        this.showNotification('Alumni deleted!', 'success');
                    } else {
                        this.showNotification('Failed to delete alumni.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error deleting alumni:', error);
                    this.showNotification('Error deleting alumni.', 'error');
                });
            },
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
        handleResize() {
            this.isMobile = window.innerWidth < 768;
            this.sidebarActive = window.innerWidth >= 768;
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
        toggleActionDropdown(id) {
            this.actionDropdown = this.actionDropdown === id ? null : id;
        },
        handleClickOutsideDropdown(event) {
            if (this.actionDropdown !== null && !event.target.closest('.relative.inline-block.text-left')) {
                this.actionDropdown = null;
            }
        },
        viewAlumniDetails(alumni) {
            this.viewAlumniData = alumni;
            this.showViewModal = true;
        },
        confirmDelete(alumni) {
            this.showDeleteModal = true;
            this.alumniToDelete = alumni;
        },
        confirmDeleteAlumni() {
            if (this.alumniToDelete) {
                this.deleteAlumni(this.alumniToDelete);
                this.alumniToDelete = null;
            }
            this.showDeleteModal = false;
        },
            updateFilterCourseOptions() {
                // This method is now redundant as filterCourseOptions is computed
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
        showNotification(message, type = 'success') {
            const id = this.notificationId++;
            this.notifications.push({ id, type, message });
            setTimeout(() => this.removeNotification(id), 3000);
        },
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        },
        
    }
}).mount('#app');