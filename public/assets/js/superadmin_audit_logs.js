const { createApp } = Vue;

createApp({
    data() {
        return {
            sidebarActive: window.innerWidth >= 768,
            companiesDropdownOpen: false,
            systemDropdownOpen: false,
            alumniDropdownOpen: false,
            profileDropdownOpen: false,
            darkMode: localStorage.getItem('darkMode') === 'true' ||
                (localStorage.getItem('darkMode') === null &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches),
            showLogoutModal: false,
            isMobile: window.innerWidth < 768,
            notifications: [],
            notificationId: 0,
            profile: {
                profile_pic: '',
                name: ''
            },

            loading: true,
            logs: [],
            total: 0,
            perPage: 10,
            currentPage: 1,
            totalPages: 1,
            availableActions: [],
            filters: {
                search: '',
                action_filter: '',
                date_from: '',
                date_to: ''
            },
            searchDebounce: null,
            paginationGroupSize: 5
        };
    },
    mounted() {
        this.applyDarkMode();
        window.addEventListener('resize', this.handleResize);
        document.addEventListener('click', this.handleClickOutsideProfile);
        this.fetchProfile();
        this.fetchActions();
        this.fetchLogs();
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
    },
    watch: {
        darkMode() {
            this.applyDarkMode();
        }
    },
    computed: {
        // Page-number buttons in groups of 5, same pattern used across the
        // admin/employer tables. this.totalPages here is a plain data
        // property (set from the server's paginated response), not derived
        // client-side, but a computed can depend on it the same way.
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
        handleResize() {
            this.isMobile = window.innerWidth < 768;
            this.sidebarActive = window.innerWidth >= 768;
        },
        handleNavClick() {
            if (this.isMobile) this.sidebarActive = false;
        },
        logout() {
            window.location.href = 'logout';
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
        toggleProfileDropdown() {
            this.profileDropdownOpen = !this.profileDropdownOpen;
        },
        handleClickOutsideProfile(event) {
            if (this.profileDropdownOpen && !event.target.closest('.profile-dropdown-wrapper')) {
                this.profileDropdownOpen = false;
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
        async fetchProfile() {
            try {
                const response = await fetch('admin_profile?action=details', { credentials: 'include' });
                const data = await response.json();
                this.profile = data.success ? data.profile : { profile_pic: null, name: 'Admin' };
            } catch (error) {
                this.profile = { profile_pic: null, name: 'Admin' };
            }
        },

        formatTime(date) {
            if (!date) return '';
            const d = new Date(date.replace(' ', 'T'));
            return d.toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        async fetchActions() {
            try {
                const res = await fetch('superadmin_audit_logs?action=actions');
                const data = await res.json();
                this.availableActions = data.success ? data.actions : [];
            } catch (error) {
                this.availableActions = [];
            }
        },
        async fetchLogs() {
            this.loading = true;
            try {
                const params = new URLSearchParams({
                    page_num: this.currentPage,
                    search: this.filters.search,
                    action_filter: this.filters.action_filter,
                    date_from: this.filters.date_from,
                    date_to: this.filters.date_to
                });
                const res = await fetch(`superadmin_audit_logs?action=list&${params.toString()}`);
                const data = await res.json();
                if (data.success) {
                    this.logs = data.logs;
                    this.total = data.total;
                    this.perPage = data.per_page || this.perPage;
                    this.totalPages = data.total_pages || 1;
                } else {
                    this.logs = [];
                }
            } catch (error) {
                this.logs = [];
            } finally {
                this.loading = false;
            }
        },
        onFilterChange() {
            clearTimeout(this.searchDebounce);
            this.searchDebounce = setTimeout(() => {
                this.currentPage = 1;
                this.fetchLogs();
            }, 300);
        },
        goToPage(page) {
            if (page < 1 || page > this.totalPages) return;
            this.currentPage = page;
            this.fetchLogs();
        },
        goToPrevGroup() {
            if (this.paginationGroup.hasPrevGroup) {
                this.goToPage(this.paginationGroup.prevGroupStart);
            }
        },
        goToNextGroup() {
            if (this.paginationGroup.hasNextGroup) {
                this.goToPage(this.paginationGroup.nextGroupStart);
            }
        }
    }
}).mount('#app');
