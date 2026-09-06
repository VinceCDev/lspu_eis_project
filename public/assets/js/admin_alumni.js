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
            dropdownPosition: { top: 0, left: 0 },
            paginationGroupSize: 5,
            alumni: [],
            searchQuery: '',
            itemsPerPage: 5,
            currentPage: 1,
            showAlumniModal: false,
            selectedAlumni: null,
            alumniForm: {
                first_name: '',
                middle_name: '',
                last_name: '',
                email: '',
                secondary_email: '',
                gender: '',
                year_graduated: '',
                college: '',
                course: '',
                province: '',
                city: '',
                status: 'Active',
                campus_id: '',
            },
            campuses: [],
            isSuperadmin: false,
            showDeleteModal: false,
            alumniToDelete: null,
            showViewModal: false,
            viewAlumniData: {
                skills: [],
                experiences: [],
                documents: [],
                employment: null
            },
            isLoading: false, // Added for loading state
            // Campus -> College -> Course/Program data for all LSPU campuses (mirrors signup.php).
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
            courseOptions: [],
            provinces: [],
            cities: [],
            currentCampusId: null,
            filters: {
                campus_id: '',
                college: '',
                course: '',
                status: ''
            },
            filterCourseOptions: [],
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
        this.fetchProvinces();
        this.fetchCampuses();
        this.updateFilterCourseOptions();
        this.fetchAlumni();
        this.fetchProfile();
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
    },
    watch: {
        darkMode(val) {
            this.applyDarkMode();
        },
        'filters.college'(val) {
            this.filters.course = '';
            this.updateFilterCourseOptions();
        },
        'filters.campus_id'() {
            this.filters.college = '';
            this.filters.course = '';
            this.updateFilterCourseOptions();
        }
    },
    computed: {
        filteredAlumni() {
            let filtered = this.alumni;
            if (this.isSuperadmin && this.filters.campus_id) {
                filtered = filtered.filter(a => String(a.campus_id) === String(this.filters.campus_id));
            }
            if (this.filters.college) {
                filtered = filtered.filter(a => a.college === this.filters.college);
            }
            if (this.filters.course) {
                filtered = filtered.filter(a => a.course === this.filters.course);
            }
            if (this.filters.status) {
                filtered = filtered.filter(a => a.status === this.filters.status);
            }
            if (this.searchQuery) {
                const q = this.searchQuery.toLowerCase();
                filtered = filtered.filter(a =>
                    (`${a.first_name} ${a.middle_name} ${a.last_name}`.toLowerCase().includes(q) ||
                    a.email.toLowerCase().includes(q) ||
                    a.gender.toLowerCase().includes(q) ||
                    a.year_graduated.toLowerCase().includes(q) ||
                    a.course.toLowerCase().includes(q) ||
                    a.college.toLowerCase().includes(q) ||
                    a.province.toLowerCase().includes(q) ||
                    a.city.toLowerCase().includes(q) ||
                    a.status.toLowerCase().includes(q))
                );
            }
            return filtered;
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
        // Colleges available in the Add/Edit Alumni modal, scoped to the form's selected
        // Campus (superadmin) or the admin's own campus.
        formColleges() {
            const campusName = this.isSuperadmin
                ? this.campusNameById(this.alumniForm.campus_id)
                : this.campusNameById(this.currentCampusId);
            return campusName && this.campusCollegeCourses[campusName]
                ? Object.keys(this.campusCollegeCourses[campusName])
                : [];
        },
        paginatedAlumni() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.filteredAlumni.slice(start, start + this.itemsPerPage);
        },
        totalPages() {
            return Math.ceil(this.filteredAlumni.length / this.itemsPerPage) || 1;
        },
        // ADD THIS NEW COMPUTED PROPERTY:
        paginationGroup() {
            const groupSize = this.paginationGroupSize; // Use the property from data
            const currentGroup = Math.ceil(this.currentPage / groupSize);
            const startPage = (currentGroup - 1) * groupSize + 1;
            const endPage = Math.min(startPage + groupSize - 1, this.totalPages);
            
            // Create array of page numbers for current group
            const pages = [];
            for (let i = startPage; i <= endPage; i++) {
                pages.push(i);
            }
            
            return {
                pages,                     // Array of page numbers to show (e.g., [1,2,3,4,5])
                hasPrevGroup: startPage > 1,  // Whether previous group exists
                hasNextGroup: endPage < this.totalPages,  // Whether next group exists
                prevGroupStart: startPage - groupSize,    // Starting page of previous group
                nextGroupStart: startPage + groupSize     // Starting page of next group
            };
        }
    },
    methods: {
        // Sidebar and dropdowns
        toggleSidebar() {
            this.sidebarActive = !this.sidebarActive;
            this.alumniDropdownOpen = true;
            this.companiesDropdownOpen = false;
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
        handleResize() {
            this.isMobile = window.innerWidth < 768;
            this.sidebarActive = window.innerWidth >= 768;
        },
        confirmLogout() {
            this.showLogoutModal = true;
        },
        logout() {
            window.location.href = 'logout';
        },
        // Dark mode
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
        // Profile dropdown
        toggleProfileDropdown() {
            this.profileDropdownOpen = !this.profileDropdownOpen;
        },
        handleClickOutsideProfile(event) {
            if (this.profileDropdownOpen && !event.target.closest('.profile-dropdown-wrapper')) {
                this.profileDropdownOpen = false;
            }
        },
        // Table and pagination
        filterAlumni() {
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
        toggleActionDropdown(id, event) {
            if (this.actionDropdown === id) {
                this.actionDropdown = null;
                return;
            }
            this.actionDropdown = id;
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
        async fetchCampuses() {
            try {
                const response = await fetch('/admin_user?action=campuses');
                const data = await response.json();
                if (data.success) {
                    this.campuses = data.campuses || [];
                    this.isSuperadmin = !!data.is_superadmin;
                    this.currentCampusId = data.current_campus_id ?? null;
                    this.updateFilterCourseOptions();
                }
            } catch (error) {
                this.campuses = [];
            }
        },
        campusNameById(campusId) {
            const campus = this.campuses.find(c => String(c.campus_id) === String(campusId));
            return campus ? campus.name : null;
        },
        // Modals (Add/Edit/View/Delete)
        openAddModal() {
            this.showAlumniModal = true;
            this.selectedAlumni = null;
            this.alumniForm = {
                first_name: '',
                middle_name: '',
                last_name: '',
                email: '',
                secondary_email: '',
                gender: '',
                year_graduated: '',
                college: '',
                course: '',
                province: '',
                city: '',
                status: 'Active',
                campus_id: ''
            };
            this.courseOptions = [];
            this.cities = [];
            this.$nextTick(() => this.focusFirstInput('alumni-modal'));
        },
        editAlumni(alumni) {
            this.selectedAlumni = alumni;
            this.alumniForm = { ...alumni };
            this.updateCourseOptions();
            this.fetchCities();
            this.showAlumniModal = true;
            this.$nextTick(() => this.focusFirstInput('alumni-modal'));
        },
        closeAlumniModal() {
            this.showAlumniModal = false;
            this.selectedAlumni = null;
        },
        closeViewModal() {
            this.showViewModal = false;
            this.viewAlumniData = { skills: [], experiences: [], documents: [], employment: null };
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
        deleteAlumni(alumni) {
            fetch('/admin_alumni?action=destroy', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ alumni_id: alumni.id }),
                credentials: 'include' // Ensure session cookie is sent
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('Alumni deleted!', 'success');
                        this.fetchAlumni();
                    } else {
                        this.showNotification(data.message || 'Failed to delete alumni', 'error');
                    }
                })
                .catch(() => this.showNotification('Error deleting alumni', 'error'));
        },
        // Form logic
        onFormCampusChange() {
            this.alumniForm.college = '';
            this.alumniForm.course = '';
            this.courseOptions = [];
        },
        updateCourseOptions() {
            const campusName = this.isSuperadmin
                ? this.campusNameById(this.alumniForm.campus_id)
                : this.campusNameById(this.currentCampusId);
            const collegeCourses = campusName ? (this.campusCollegeCourses[campusName] || {}) : {};
            this.courseOptions = collegeCourses[this.alumniForm.college] || [];
            if (!this.courseOptions.includes(this.alumniForm.course)) {
                this.alumniForm.course = '';
            }
        },
        updateFilterCourseOptions() {
            const campusName = this.isSuperadmin
                ? (this.filters.campus_id ? this.campusNameById(this.filters.campus_id) : null)
                : this.campusNameById(this.currentCampusId);
            const collegeCourses = campusName ? (this.campusCollegeCourses[campusName] || {}) : null;

            if (this.filters.college) {
                this.filterCourseOptions = collegeCourses ? (collegeCourses[this.filters.college] || []) : [];
                return;
            }

            if (collegeCourses) {
                this.filterCourseOptions = [...new Set(Object.values(collegeCourses).flat())];
            } else {
                const all = Object.values(this.campusCollegeCourses).flatMap(colleges => Object.values(colleges).flat());
                this.filterCourseOptions = [...new Set(all)];
            }
        },
        // Province/City API logic
        async fetchProvinces() {
            try {
                const res = await fetch('https://psgc.gitlab.io/api/provinces/');
                const data = await res.json();
                this.provinces = data.map(p => ({ code: p.code, name: p.name }));
            } catch (e) {
                this.provinces = [{ code: '0434', name: 'Laguna' }];
            }
        },
        async fetchCities() {
            this.cities = [];
            const province = this.provinces.find(p => p.name === this.alumniForm.province);
            if (!province) return;
            try {
                const res = await fetch(`https://psgc.gitlab.io/api/provinces/${province.code}/cities-municipalities/`);
                const data = await res.json();
                this.cities = data.map(c => ({ code: c.code, name: c.name }));
            } catch (e) {
                this.cities = [];
            }
        },
        // Notifications
        showNotification(message, type = 'success') {
            const id = this.notificationId++;
            this.notifications.push({ id, type, message });
            setTimeout(() => this.removeNotification(id), 3000);
        },
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        },
        async fetchAlumni() {
            this.isLoading = true;
            try {
                const response = await fetch('/admin_alumni?action=list', { credentials: 'include' });
                const data = await response.json();

                if (!data.success) {
                    this.showNotification(data.message || 'Failed to load alumni data', 'error');
                    return;
                }

                // The list endpoint already includes each alumni's
                // skills/education/experience/resume (batched server-side) —
                // no more per-alumni follow-up requests needed here.
                this.alumni = data.alumni.map(alumni => {
                    const experience = alumni.experience || [];
                    let resume = null;
                    if (alumni.resume && alumni.resume.file_name) {
                        resume = {
                            ...alumni.resume,
                            url: 'uploads/resumes/' + alumni.resume.file_name
                        };
                    }

                    return {
                        id: alumni.alumni_id,
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
                        campus_id: alumni.campus_id,
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
            } finally {
                this.isLoading = false;
            }
        },
        // In the viewAlumniDetails method, update the skills display logic:
        viewAlumniDetails(alumni) {
            this.viewAlumniData = {
                ...alumni,
                skills: alumni.skills || [],
                experiences: alumni.experiences || [],
                documents: alumni.documents || [],
                employment: alumni.employment,
                profile_picture: alumni.profile_picture
            };
            this.showViewModal = true;
            this.$nextTick(() => this.focusFirstInput('view-modal'));
        },

        // Update the getFileIcon method to handle certificate files:
        getFileIcon(filename) {
            if (!filename) return 'fas fa-file text-gray-400';
            const ext = filename.split('.').pop().toLowerCase();
            const icons = {
                pdf: 'fas fa-file-pdf text-red-500',
                jpg: 'fas fa-file-image text-green-500',
                jpeg: 'fas fa-file-image text-green-500',
                png: 'fas fa-file-image text-green-500',
                doc: 'fas fa-file-word text-blue-500',
                docx: 'fas fa-file-word text-blue-500',
                default: 'fas fa-file text-gray-400'
            };
            return icons[ext] || icons.default;
        },

        // Add a method to get certificate URL
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
        
        calculateYears(startDate, endDate) {
            const start = new Date(startDate);
            const end = endDate ? new Date(endDate) : new Date();
            const diffTime = Math.abs(end - start);
            return Math.floor(diffTime / (1000 * 60 * 60 * 24 * 365.25));
        },
        formatDate(date) {
            if (!date) return 'N/A';
            return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        },
        getDocumentUrl(filename) {
            if (!filename) return null;
            const allowedExtensions = ['.pdf', '.jpg', '.png', '.doc', '.docx'];
            const ext = filename.slice(filename.lastIndexOf('.')).toLowerCase();
            if (!allowedExtensions.includes(ext)) {
                console.warn('Invalid document extension:', filename);
                return null;
            }
            return `/uploads/documents/${encodeURIComponent(filename)}`;
        },
        getFileIcon(url) {
            if (!url) return 'fas fa-file text-gray-400';
            const ext = url.split('.').pop().toLowerCase();
            const icons = {
                pdf: 'fas fa-file-pdf text-red-500',
                jpg: 'fas fa-file-image text-green-500',
                jpeg: 'fas fa-file-image text-green-500',
                png: 'fas fa-file-image text-green-500',
                doc: 'fas fa-file-word text-blue-500',
                docx: 'fas fa-file-word text-blue-500',
                default: 'fas fa-file text-gray-400'
            };
            return icons[ext] || icons.default;
        },
        // Add/Update Alumni
        addAlumni() {
            fetch('/admin_alumni?action=store', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(this.alumniForm),
                credentials: 'include'
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('Alumni added!', 'success');
                        this.showAlumniModal = false;
                        this.fetchAlumni();
                    } else {
                        this.showNotification(data.message || 'Failed to add alumni', 'error');
                    }
                })
                .catch(() => this.showNotification('Error adding alumni', 'error'));
        },
        updateAlumni() {
            fetch('/admin_alumni?action=store', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ alumni_id: this.selectedAlumni.id, ...this.alumniForm }),
                credentials: 'include'
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showNotification('Alumni updated!', 'success');
                        this.showAlumniModal = false;
                        this.fetchAlumni();
                    } else {
                        this.showNotification(data.message || 'Failed to update alumni', 'error');
                    }
                })
                .catch(() => this.showNotification('Error updating alumni', 'error'));
        },
        // Export
        async exportToPDF() {
            await LibLoader.ensureJsPDFAutoTable();
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const columns = [
                'Name', 'Email', 'Gender', 'Year Graduated', 'Course', 'College', 'Province', 'City/Municipality', 'Status'
            ];
            const rows = this.filteredAlumni.map(a => [
                `${a.first_name} ${a.middle_name} ${a.last_name}`,
                a.email, a.gender, a.year_graduated, a.course, a.college, a.province, a.city, a.status
            ]);
            doc.autoTable({ head: [columns], body: rows });
            doc.save('alumni.pdf');
            this.showNotification('PDF generated successfully!', 'success');
        },
        async exportToExcel() {
            await LibLoader.ensureXLSX();
            const wb = XLSX.utils.book_new();
            const wsData = [
                ['Name', 'Email', 'Gender', 'Year Graduated', 'Course', 'College', 'Province', 'City/Municipality', 'Status'],
                ...this.filteredAlumni.map(a => [
                    `${a.first_name} ${a.middle_name} ${a.last_name}`,
                    a.email, a.gender, a.year_graduated, a.course, a.college, a.province, a.city, a.status
                ])
            ];
            const ws = XLSX.utils.aoa_to_sheet(wsData);
            XLSX.utils.book_append_sheet(wb, ws, 'Alumni');
            XLSX.writeFile(wb, 'alumni.xlsx');
            this.showNotification('Excel file generated successfully!', 'success');
        },
        // Accessibility helpers
        focusFirstInput(modalId) {
            this.$nextTick(() => {
                const modal = document.querySelector(`[aria-modal="true"][data-modal="${modalId}"]`) || document.querySelector('.fixed[role="dialog"]');
                if (modal) {
                    const input = modal.querySelector('input, select, textarea, button');
                    if (input) input.focus();
                }
            });
        },
        // Fetch profile data
        async fetchProfile() {
            try {
                const response = await fetch('admin_profile?action=details', { credentials: 'include' });
                const data = await response.json();
                if (data.success) {
                    this.profile = data.profile;
                } else {
                    console.error('Error fetching profile:', data.message);
                    this.showNotification(data.message || 'Failed to fetch admin details', 'error');
                    this.profile = { profile_pic: null, name: 'Admin' };
                }
            } catch (error) {
                console.error('Error fetching profile:', error);
                this.showNotification('Error fetching admin details', 'error');
                this.profile = { profile_pic: null, name: 'Admin' };
            }
        },
        
    }
}).mount('#app');