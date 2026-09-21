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
            showImportModal: false,
            importFile: null,
            importCampusId: '',
            importYear: null,
            importing: false,       // true only while the file is being uploaded (the import itself runs on the server queue)
            importResult: null,     // final summary once the queued import has completed
            importPhase: null,      // 'uploading' while the file transfers
            importUploadPct: 0,     // real 0-100 during upload (XHR upload.onprogress)
            importJob: null,        // the queued/processing/finished import being watched: {id,status,percent,processed_rows,total_rows,...}
            importHistory: [],      // the 10 most recent imports (survives closing the browser: read from the server)
            importActive: 0,        // queued + processing imports (badge on the Import button)
            totalAlumni: 0,         // rows matching the search/filters on the SERVER (this.alumni holds only the current page)
            totalCapped: false,     // the server stopped counting at 10,000 ("10,000+")
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
        this.loadImportHistory().then(() => this._watchActiveImports());   // badge if an import is still running from an earlier visit
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
            this.currentPage = 1;
            this.queueFetch();
        },
        'filters.campus_id'() {
            this.filters.college = '';
            this.filters.course = '';
            this.updateFilterCourseOptions();
            this.currentPage = 1;
            this.queueFetch();
        },
        'filters.course'() {
            this.currentPage = 1;
            this.queueFetch();
        },
        'filters.status'() {
            this.currentPage = 1;
            this.queueFetch();
        },
        currentPage() {
            this.queueFetch(0);
        }
    },
    computed: {
        // Search, filters and paging run on the server (a campus can hold 100k+ alumni): `alumni` is already the current page.
        filteredAlumni() {
            return this.alumni;
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
            return this.alumni;
        },
        totalPages() {
            return Math.ceil(this.totalAlumni / this.itemsPerPage) || 1;
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
            // typing in the search box: wait for a pause, then ask the server (page 1)
            this.currentPage = 1;
            this.queueFetch(350);
        },
        // Coalesces bursts of triggers (page click + filter reset, keystrokes) into one request.
        queueFetch(delay = 60) {
            if (this._fetchTimer) clearTimeout(this._fetchTimer);
            this._fetchTimer = setTimeout(() => { this._fetchTimer = null; this.fetchAlumni(); }, delay);
        },
        _alumniQuery(page, perPage) {
            const q = new URLSearchParams({ action: 'paginatedList', page: String(page), per_page: String(perPage) });
            const term = (this.searchQuery || '').trim();
            if (term) q.set('search', term);
            if (this.isSuperadmin && this.filters.campus_id) q.set('campus_id', this.filters.campus_id);
            if (this.filters.college) q.set('college', this.filters.college);
            if (this.filters.course) q.set('course', this.filters.course);
            if (this.filters.status) q.set('status', this.filters.status);
            return '/admin_alumni?' + q.toString();
        },
        _mapAlumni(alumni) {
            const experience = alumni.experience || [];
            let resume = null;
            if (alumni.resume && alumni.resume.file_name) {
                resume = { ...alumni.resume, url: 'uploads/resumes/' + alumni.resume.file_name };
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
        },
        // Every alumnus matching the current search/filters, up to a cap (the export buttons; the table itself is paged).
        async fetchAllForExport(cap = 5000) {
            const rows = [];
            for (let page = 1; rows.length < cap; page++) {
                const res = await fetch(this._alumniQuery(page, 100), { credentials: 'include' });
                const data = await res.json();
                if (!data.success) { this.showNotification(data.message || 'Could not load alumni for export', 'error'); break; }
                rows.push(...data.alumni.map(a => this._mapAlumni(a)));
                if (data.alumni.length < 100 || rows.length >= data.total) break;
            }
            if (rows.length >= cap) this.showNotification('Export limited to the first ' + cap.toLocaleString() + ' matching alumni — narrow the filters for the rest.', 'error');
            return rows.slice(0, cap);
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
        // Import from Employment Report.
        // The upload only transfers the file; the server QUEUES the import and returns at once. Progress is then read from
        // import_jobs with a light poll (one primary-key read per call), and the import keeps running if this window closes.
        openImportModal() {
            this.importFile = null;
            this.importResult = null;
            this.importing = false;
            this.importJob = null;
            this._resetImportProgress();
            this.importCampusId = this.isSuperadmin ? '' : (this.currentCampusId || '');
            this.importYear = null;
            if (!this.campuses.length) this.fetchCampuses();
            this.showImportModal = true;
            this.loadImportHistory();
        },
        closeImportModal() {
            this.showImportModal = false;
            this._stopImportPoll();
            if (this.importResult && this.importResult.imported > 0) {
                this.fetchAlumni();
            }
            this.importResult = null;
            this.importFile = null;
            this.importJob = null;
            this._resetImportProgress();
            this._watchActiveImports();
        },
        onImportFileChange(e) {
            this.importFile = e.target.files[0] || null;
        },
        _resetImportProgress() {
            this.importPhase = null;
            this.importUploadPct = 0;
        },
        _stopImportPoll() {
            if (this._importPoll) { clearTimeout(this._importPoll); this._importPoll = null; }
        },
        importStatusLabel(job) {
            if (!job) return '';
            switch (job.status) {
                case 'queued': return job.queue_position > 0 ? ('Queued — #' + job.queue_position + ' in line') : 'Queued — starting shortly';
                case 'processing': return 'Importing…';
                case 'completed': return 'Completed';
                case 'failed': return 'Failed';
                case 'cancelled': return 'Cancelled';
                default: return job.status;
            }
        },
        importIsActive(job) {
            return !!job && (job.status === 'queued' || job.status === 'processing');
        },
        async loadImportHistory() {
            try {
                const res = await fetch('/admin_alumni?action=importList', { headers: { Accept: 'application/json' } });
                const data = await res.json();
                if (data.success) {
                    this.importHistory = data.imports;
                    this.importActive = data.imports.filter(j => this.importIsActive(j)).length;
                }
            } catch (e) { /* history is a convenience; ignore */ }
        },
        // After a page load / closing the modal: if something is still running, keep the badge current (slow poll, only while visible).
        _watchActiveImports() {
            this._stopImportPoll();
            if (this.importActive <= 0 || this.showImportModal) return;
            this._importPoll = setTimeout(async () => {
                if (!document.hidden) await this.loadImportHistory();
                this._watchActiveImports();
            }, 10000);
        },
        watchImport(job) {
            this.importJob = job;
            this.importResult = null;
            this._pollImport(job.id, 0);
        },
        _pollImport(id, n) {
            this._stopImportPoll();
            // 2 s while it is young, then back off to 5 s; paused while the tab is hidden (resumes on the next tick).
            const delay = n < 30 ? 2000 : 5000;
            this._importPoll = setTimeout(async () => {
                if (document.hidden) { this._pollImport(id, n); return; }
                try {
                    const res = await fetch('/admin_alumni?action=importStatus&id=' + encodeURIComponent(id), { headers: { Accept: 'application/json' } });
                    const data = await res.json();
                    if (data.success) {
                        this.importJob = data.import;
                        if (!this.importIsActive(data.import)) {
                            this._importFinished(data.import);
                            return;
                        }
                    }
                } catch (e) { /* transient network error: keep polling */ }
                this._pollImport(id, n + 1);
            }, delay);
        },
        _importFinished(job) {
            this.loadImportHistory();
            if (job.status === 'completed') {
                this.importResult = job.summary || { imported: job.successful_rows, skipped: job.failed_rows, experience_rows: 0, placeholder_emails: 0, emailed: 0 };
                this.showNotification('Import finished: ' + job.successful_rows + ' alumni imported.', 'success');
            } else if (job.status === 'failed') {
                this.showNotification(job.error_message || 'Import failed.', 'error');
            }
        },
        async cancelImport() {
            if (!this.importJob || !this.importIsActive(this.importJob)) return;
            if (!confirm('Cancel this import? Rows that were already imported are kept.')) return;
            try {
                const res = await fetch('/admin_alumni?action=importCancel', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ id: this.importJob.id }),
                });
                const data = await res.json();
                this.showNotification(data.message, data.success ? 'success' : 'error');
                this._pollImport(this.importJob.id, 0);
            } catch (e) {
                this.showNotification('Could not cancel the import.', 'error');
            }
        },
        runImport() {
            if (!this.importFile || this.importing) return;
            this.importing = true;
            this._resetImportProgress();
            this.importPhase = 'uploading';

            const fd = new FormData();
            fd.append('file', this.importFile);
            if (this.importCampusId) fd.append('campus_id', this.importCampusId);
            if (this.importYear) fd.append('year', this.importYear);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/admin_alumni?action=importEmploymentReport');
            xhr.setRequestHeader('Accept', 'application/json');

            // Real % for the file transfer (the only part the browser has to stay for).
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    this.importUploadPct = Math.round((e.loaded / e.total) * 100);
                }
            };
            xhr.upload.onload = () => { this.importUploadPct = 100; };

            const finish = () => { this.importing = false; this.importPhase = null; };
            xhr.onload = () => {
                finish();
                let msg = {};
                try { msg = JSON.parse(xhr.responseText); } catch (e) { /* non-JSON error page */ }
                if (msg.success && msg.import) {
                    this.importFile = null;
                    this.showNotification(msg.message, 'success');
                    this.importActive = Math.max(1, this.importActive);
                    if (msg.import.status === 'completed') {
                        this.importJob = msg.import;
                        this._importFinished(msg.import);   // sync queue (development): already done
                    } else {
                        this.watchImport(msg.import);
                    }
                    this.loadImportHistory();
                } else {
                    this.showNotification(msg.message || 'Import failed (HTTP ' + xhr.status + ').', 'error');
                }
            };
            xhr.onerror = () => { finish(); this.showNotification('Upload failed — the connection was lost. Nothing was imported.', 'error'); };
            xhr.ontimeout = () => { finish(); this.showNotification('Upload timed out. Check your connection and try again.', 'error'); };

            xhr.send(fd);
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
            const seq = (this._fetchSeq = (this._fetchSeq || 0) + 1);
            this.isLoading = true;
            try {
                const response = await fetch(this._alumniQuery(this.currentPage, this.itemsPerPage), { credentials: 'include' });
                const data = await response.json();
                if (seq !== this._fetchSeq) return;   // a newer request is in flight: ignore this stale answer

                if (!data.success) {
                    this.showNotification(data.message || 'Failed to load alumni data', 'error');
                    return;
                }

                // One page of alumni (with skills/education/experience/resume batched server-side); `total` is the number
                // of rows matching the search/filters across the whole campus.
                this.alumni = data.alumni.map(a => this._mapAlumni(a));
                this.totalAlumni = data.total_capped ? 1000 : data.total;
                this.totalCapped = !!data.total_capped;
                const last = Math.ceil(data.total / this.itemsPerPage) || 1;
                if (this.currentPage > last) this.currentPage = last;
            } catch (error) {
                console.error('Error fetching alumni:', error);
                this.showNotification('Error loading alumni data', 'error');
            } finally {
                if (seq === this._fetchSeq) this.isLoading = false;
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
            const rows = (await this.fetchAllForExport()).map(a => [
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
                ...(await this.fetchAllForExport()).map(a => [
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