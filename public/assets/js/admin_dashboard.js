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
            isSuperadmin: !!window.IS_SUPERADMIN,
            markers: [
                { lat: 14.1667, lng: 121.2167, title: 'Main Campus', alumni: 120 },
                { lat: 14.2775, lng: 121.4158, title: 'San Pablo City', alumni: 85 },
                { lat: 14.1833, lng: 121.3000, title: 'Santa Cruz', alumni: 65 },
                { lat: 13.9319, lng: 121.4233, title: 'Siniloan', alumni: 90 },
                { lat: 14.0333, lng: 121.3167, title: 'Los Baños', alumni: 45 }
            ],
            charts: {},
            chartInitialized: false,
            isMobile: window.innerWidth < 768,
            notifications: [],
            notificationId: 0,
            dashboardStats: null,
            // KNOWN INCOMPLETE FEATURE (confirmed during ISO 25010
            // remediation, item 12): showDrilldown()/closeDrilldown()/
            // renderDrilldownChart() below are fully implemented and wired
            // to the college/location/sector chart onClick handlers, but
            // admin/dashboard.blade.php has no modal/panel bound to
            // `drilldown.active` and no `#drilldownChart` canvas — clicking
            // a chart segment computes drilldown.data and silently does
            // nothing visible (renderDrilldownChart() guards on the canvas
            // existing, so this doesn't throw, it just has no UI yet).
            // Needs a template addition (modal with a canvas#drilldownChart
            // and a close button calling closeDrilldown()) — left as-is
            // rather than removed since it's clearly intended, not
            // abandoned, but building that UI is out of scope for this
            // remediation pass (see the separate frontend/UI testing phase).
            drilldown: {
              active: false,
              type: '', // 'college', 'location', 'sector'
              label: '',
              data: null
            },
            profile: {
                profile_pic: '',
                name: '',
            },
            showEmploymentStatusModal: false,
            employmentStatusLoading: true,
            employmentStatusByCampus: [],
            selectedCampusForChart: null,
            campusCharts: {}
        }
    },
    mounted() {
        this.applyDarkMode();
        document.addEventListener('click', this.handleClickOutsideProfile);
        window.addEventListener('resize', this.handleResize);

        // The dev server (php artisan serve) is single-process and the fpm
        // pool is small, so firing every dashboard AJAX at once monopolises
        // the server and a navigation click ends up queued behind all of
        // them. Instead: run them ONE AT A TIME, and abort whatever is
        // in-flight (and skip the rest) the moment the user leaves the page,
        // so the next page's request hits a free server immediately.
        this._dashAbort = new AbortController();
        this._onLeave = () => { try { this._dashAbort.abort(); } catch (e) {} };
        window.addEventListener('pagehide', this._onLeave);

        if (window.LibLoader) { LibLoader.ensureChart(); LibLoader.ensureLeaflet(); }

        this.loadDashboardData();
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
        window.removeEventListener('pagehide', this._onLeave);
        document.removeEventListener('click', this.handleClickOutsideProfile);
        try { this._dashAbort && this._dashAbort.abort(); } catch (e) {}
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
            this.companiesDropdownOpen = false;
            this.alumniDropdownOpen = false;
        },
        handleNavClick() {
            if (this.isMobile) {
                this.sidebarActive = false;
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
        reinitializeCharts() {
            if (this.chartInitialized) {
                this.destroyCharts();
            }
            this.initCharts();
        },
        destroyCharts() {
            Object.values(this.charts).forEach(chart => {
                if (chart && !chart._destroyed) {
                    chart.destroy();
                }
            });
            this.charts = {};
            this.chartInitialized = false;
        },
        // Runs the dashboard's data requests sequentially (never more than one
        // occupying the server at a time) so a navigation request can slot in
        // between them, and bails out entirely if the user has navigated away.
        async loadDashboardData() {
            const signal = this._dashAbort.signal;
            const getJson = async (url) => (await fetch(url, { signal })).json();
            const gone = (e) => e && e.name === 'AbortError';

            try {
                // 1. profile — tiny; fills the header immediately
                try {
                    const p = await getJson('admin_profile?action=details');
                    if (p && p.success && p.profile) this.profile = p.profile;
                } catch (e) { if (gone(e)) return; }

                // 2. stats — charts + map data
                try {
                    this.dashboardStats = await getJson('/admin_dashboard?action=stats');
                    await this.$nextTick();
                    this.initCharts();   // async, self-guarded; renders when Chart.js is ready
                    this.showLogoutModal = false;
                } catch (e) { if (gone(e)) return; }

                // 3. per-campus employment status (campus buttons)
                try { await this.fetchEmploymentStatusByCampus(signal); }
                catch (e) { if (gone(e)) return; }

                // 4. course-work alignment (one chart; can be the slowest)
                try {
                    const a = await getJson('/admin_dashboard?action=courseWorkAlignment');
                    if (a && a.success) {
                        this.dashboardStats = this.dashboardStats || {};
                        this.dashboardStats.course_work_alignment = a.course_work_alignment;
                        this.renderAlignmentChart();
                    }
                } catch (e) { if (gone(e)) return; }

                // 5. map last — its geocode call is the slowest tail; if the
                //    user already navigated we never even fire it.
                if (!signal.aborted) this.initMap();
            } catch (e) {
                if (!gone(e)) console.error('Dashboard data load failed:', e);
            }
        },
        async fetchEmploymentStatusByCampus(signal) {
            this.employmentStatusLoading = true;
            try {
                const res = await fetch('/admin_dashboard?action=employmentStatusByCampus', signal ? { signal } : undefined);
                const data = await res.json();
                this.employmentStatusByCampus = data.success ? data.campuses : [];
            } catch (error) {
                if (error && error.name === 'AbortError') throw error;
                console.error('Error fetching employment status by campus:', error);
                this.employmentStatusByCampus = [];
            }
            this.employmentStatusLoading = false;
        },
        openEmploymentStatusModal(campus) {
            this.selectedCampusForChart = campus;
            this.showEmploymentStatusModal = true;
            this.$nextTick(() => this.renderCampusEmploymentChart(campus));
        },
        async openCollegeStatusModal(campus, college) {
            try {
                const res = await fetch(`/admin_dashboard?action=collegeEmploymentStatus&campus_id=${campus.campus_id}&college=${encodeURIComponent(college)}`);
                const data = await res.json();
                if (!data.success) {
                    this.addNotification('error', 'Error', data.message || 'Failed to load college data.');
                    return;
                }
                // Reuses the same modal/chart as openEmploymentStatusModal() — a
                // college-scoped "campus" is the same shape (id/name/program stats),
                // just further filtered on the backend.
                const scoped = {
                    campus_id: `${data.campus_id}_${college}`,
                    campus_name: `${data.campus_name} — ${college}`,
                    employment_status_per_program: data.employment_status_per_program
                };
                this.selectedCampusForChart = scoped;
                this.showEmploymentStatusModal = true;
                this.$nextTick(() => this.renderCampusEmploymentChart(scoped));
            } catch (error) {
                this.addNotification('error', 'Error', 'Failed to load college data.');
            }
        },
        closeEmploymentStatusModal() {
            Object.values(this.campusCharts).forEach(chart => {
                if (chart && !chart._destroyed) {
                    chart.destroy();
                }
            });
            this.campusCharts = {};
            this.showEmploymentStatusModal = false;
            this.selectedCampusForChart = null;
        },
        async renderAlignmentChart() {
            if (window.LibLoader) { await LibLoader.ensureChart(); }
            const alignmentCtx = document.getElementById('alignmentChart');
            if (!alignmentCtx || !this.dashboardStats || !this.dashboardStats.course_work_alignment) return;

            if (this.charts.alignment && !this.charts.alignment._destroyed) {
                this.charts.alignment.destroy();
            }

            const textColor = this.darkMode ? '#e5e7eb' : '#374151';
            const alignmentLabels = ['Highly Aligned', 'Moderately Aligned', 'Slightly Aligned', 'Not Aligned'];
            // Aggregate total counts for each label across all courses
            const totalCounts = alignmentLabels.map(label => {
                return Object.values(this.dashboardStats.course_work_alignment).reduce((sum, course) => {
                    if (course.counts && typeof course.counts[label] === 'number') {
                        return sum + course.counts[label];
                    }
                    return sum;
                }, 0);
            });
            this.charts.alignment = new Chart(alignmentCtx, {
                type: 'doughnut',
                data: {
                    labels: alignmentLabels,
                    datasets: [{
                        data: totalCounts,
                        backgroundColor: [
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(255, 99, 132, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    // Without this, this chart's default grow-in animation
                    // can still have an in-flight requestAnimationFrame when
                    // it gets destroyed and immediately recreated — it's
                    // rendered from two independent, uncoordinated fetches
                    // (see mounted()'s dedicated courseWorkAlignment call and
                    // initCharts() below), which race on real page loads.
                    // The stale frame then fires against a canvas Chart.js
                    // already nulled out during destroy(), throwing
                    // "Cannot read properties of null (reading 'getContext')"
                    // from Chart.js's global animator. Every other chart on
                    // this page already sets duration: 0 for the same reason.
                    animation: { duration: 0 },
                    plugins: { legend: { position: 'bottom', labels: { color: textColor } } }
                }
            });
        },
        async renderCampusEmploymentChart(campus) {
            if (window.LibLoader) { await LibLoader.ensureChart(); }
            const canvas = document.getElementById('employmentStatusCampusChart_' + campus.campus_id);
            if (!canvas) return;

            const textColor = this.darkMode ? '#e5e7eb' : '#374151';
            const gridColor = this.darkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
            const programs = Object.keys(campus.employment_status_per_program || {});
            const statusLabels = ['Probational', 'Contractual', 'Regular', 'Self-employed', 'Unemployed'];
            const colors = [
                ['rgba(153, 102, 255, 0.7)', 'rgba(153, 102, 255, 1)'],
                ['rgba(255, 159, 64, 0.7)', 'rgba(255, 159, 64, 1)'],
                ['rgba(54, 162, 235, 0.7)', 'rgba(54, 162, 235, 1)'],
                ['rgba(75, 192, 192, 0.7)', 'rgba(75, 192, 192, 1)'],
                ['rgba(255, 99, 132, 0.7)', 'rgba(255, 99, 132, 1)']
            ];
            const datasets = statusLabels.map((status, i) => ({
                label: status,
                data: programs.map(p => (campus.employment_status_per_program[p] && typeof campus.employment_status_per_program[p][status] === 'number') ? campus.employment_status_per_program[p][status] : 0),
                backgroundColor: colors[i][0],
                borderColor: colors[i][1],
                borderWidth: 1
            }));

            this.campusCharts[campus.campus_id] = new Chart(canvas, {
                type: 'bar',
                data: { labels: programs, datasets: datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 0 },
                    scales: {
                        x: { grid: { color: gridColor }, ticks: { color: textColor, autoSkip: false, maxRotation: 60, minRotation: 60, font: { size: 9 } } },
                        y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor } }
                    },
                    plugins: { legend: { position: 'bottom', labels: { color: textColor, boxWidth: 12, font: { size: 10 } } } }
                }
            });
        },
        async initCharts() {
            if (this.chartInitialized) return;
            if (window.LibLoader) { await LibLoader.ensureChart(); }
            const textColor = this.darkMode ? '#e5e7eb' : '#374151';
            const gridColor = this.darkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
            try {
                // Chart 1: Graduates and Employment per College
                const graduatesCtx = document.getElementById('graduatesChart');
                if (graduatesCtx && this.dashboardStats) {
                    const colleges = Object.keys(this.dashboardStats.graduates_per_college || {});
                    const graduates = colleges.map(c => this.dashboardStats.graduates_per_college[c].graduates);
                    const employed = colleges.map(c => this.dashboardStats.graduates_per_college[c].employed);
                    this.charts.graduates = new Chart(graduatesCtx, {
                        type: 'bar',
                        data: {
                            labels: colleges,
                            datasets: [
                                {
                                    label: 'Graduates',
                                    data: graduates,
                                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                                    borderColor: 'rgba(54, 162, 235, 1)',
                                    borderWidth: 1
                                },
                                {
                                    label: 'Employed',
                                    data: employed,
                                    backgroundColor: 'rgba(75, 192, 192, 0.7)',
                                    borderColor: 'rgba(75, 192, 192, 1)',
                                    borderWidth: 1
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { duration: 0 },
                            scales: {
                                x: { grid: { color: gridColor }, ticks: { color: textColor } },
                                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: textColor } }
                            },
                            plugins: { legend: { labels: { color: textColor } } }
                        }
                    });
                    graduatesCtx.onclick = (evt) => {
                        const points = this.charts.graduates.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                        if (points.length) {
                            const idx = points[0].index;
                            const college = colleges[idx];
                            this.showDrilldown('college', college);
                        }
                    };
                }
                // Chart 2: Course-Work Alignment — fetched separately (see mounted()) since
                // it's the one Gemini-backed piece of this page; render it now only if that
                // fetch already resolved by the time initCharts() runs.
                this.renderAlignmentChart();
                // Chart 3 (Employment Status per Program) moved into the per-campus modal —
                // see openEmploymentStatusModal()/renderCampusEmploymentChart() — a single
                // chart merging every program across every campus became unreadable.
                // Chart 4: Work Location Distribution
                const locationCtx = document.getElementById('locationChart');
                if (locationCtx && this.dashboardStats) {
                    const locLabels = ['Local', 'Abroad'];
                    const locData = locLabels.map(l => (this.dashboardStats.work_location_distribution && typeof this.dashboardStats.work_location_distribution[l] === 'number') ? this.dashboardStats.work_location_distribution[l] : 0);
                    this.charts.location = new Chart(locationCtx, {
                        type: 'pie',
                        data: {
                            labels: locLabels,
                            datasets: [{
                                data: locData,
                                backgroundColor: [
                                    'rgba(255, 159, 64, 0.7)',
                                    'rgba(255, 99, 132, 0.7)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { duration: 0 },
                            plugins: { legend: { position: 'bottom', labels: { color: textColor } } }
                        }
                    });
                    locationCtx.onclick = (evt) => {
                        const points = this.charts.location.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                        if (points.length) {
                            const idx = points[0].index;
                            const location = locLabels[idx];
                            this.showDrilldown('location', location);
                        }
                    };
                }
                // Chart 5: Employment Sector
                const sectorCtx = document.getElementById('sectorChart');
                if (sectorCtx && this.dashboardStats) {
                    const sectorLabels = ['Government', 'Private'];
                    const sectorData = sectorLabels.map(s => (this.dashboardStats.employment_sector_distribution && typeof this.dashboardStats.employment_sector_distribution[s] === 'number') ? this.dashboardStats.employment_sector_distribution[s] : 0);
                    this.charts.sector = new Chart(sectorCtx, {
                        type: 'polarArea',
                        data: {
                            labels: sectorLabels,
                            datasets: [{
                                data: sectorData,
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.7)',
                                    'rgba(54, 162, 235, 0.7)'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { duration: 0 },
                            plugins: { legend: { position: 'bottom', labels: { color: textColor } } }
                        }
                    });
                    sectorCtx.onclick = (evt) => {
                        const points = this.charts.sector.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
                        if (points.length) {
                            const idx = points[0].index;
                            const sector = sectorLabels[idx];
                            this.showDrilldown('sector', sector);
                        }
                    };
                }
                this.chartInitialized = true;
            } catch (e) {
                console.error('Chart initialization error:', e);
                this.chartInitialized = false;
            }
        },
        async initMap() {
            try {
                if (window.LibLoader) { await LibLoader.ensureLeaflet(); }
                const map = L.map('alumniMap').setView([14.1667, 121.2167], 10);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                if (this.dashboardStats && this.dashboardStats.alumni_map) {
                    // C1: the payload is now one summary per location. The
                    // per-alumnus list is fetched lazily when a popup opens.
                    const entries = Object.entries(this.dashboardStats.alumni_map);

                    const summaryHtml = (location, cluster) => {
                        const courses = (cluster.top_courses || [])
                            .map(c => `<span style='font-size:12px;'>${c.course} <b>(${c.count})</b></span>`)
                            .join('<br>');
                        return `<div class='alumni-cluster' data-city="${encodeURIComponent(cluster.city)}" data-province="${encodeURIComponent(cluster.province)}">
                            <b>${location}</b><br><br>
                            <span style='font-size:12px;'>Alumni: <b>${cluster.count}</b> &nbsp;|&nbsp; Employed: <b>${cluster.employed}</b></span>
                            ${courses ? `<div style='margin-top:6px;'>${courses}</div>` : ''}
                            <button type='button' class='alumni-cluster-view'
                                style='margin-top:8px;padding:4px 10px;font-size:12px;background:#3b82f6;color:#fff;border:none;border-radius:4px;cursor:pointer;'>
                                View alumni (${cluster.count})
                            </button>
                        </div>`;
                    };

                    const alumniCardHtml = (a) => `
                        <div style='margin-bottom:10px;'>
                            ${a.profile_pic ? `<div style='text-align:center;margin-bottom:4px;'><img src='${a.profile_pic}' style='width:48px;height:48px;object-fit:cover;border-radius:50%;border:2px solid #3b82f6;'></div>` : ''}
                            <b>${a.name}</b><br>
                            <span style='font-size:12px;'>${a.course} (${a.year_graduated})</span><br>
                            <span style='font-size:12px;'>Status: <b>${a.status}</b></span><br>
                            ${a.status === 'Employed' && a.work_details ? `
                                <div style='margin-top:4px; padding-left:8px; border-left:2px solid #3b82f6;'>
                                    <span style='font-size:12px;'><b>Position:</b> ${a.work_details.title}</span><br>
                                    <span style='font-size:12px;'><b>Company:</b> ${a.work_details.company}</span><br>
                                    <span style='font-size:12px;'><b>From:</b> ${a.work_details.start_date || '-'} <b>To:</b> ${a.work_details.end_date || 'Present'}</span><br>
                                    <span style='font-size:12px;'><b>Description:</b> ${a.work_details.description || '-'}</span>
                                </div>
                            ` : ''}
                        </div>`;

                    const listHtml = (location, city, province, data, page) => {
                        const pages = Math.max(1, Math.ceil(data.total / data.per_page));
                        const cards = data.alumni.map(alumniCardHtml).join('<hr style="margin:6px 0;">');
                        return `<div class='alumni-cluster-list' data-city="${encodeURIComponent(city)}" data-province="${encodeURIComponent(province)}" data-page="${page}" data-pages="${pages}">
                            <b>${location}</b> <span style='font-size:12px;color:#6b7280;'>(${data.total} alumni)</span><br><br>
                            ${cards || `<span style='font-size:12px;'>No alumni found.</span>`}
                            <div style='display:flex;justify-content:space-between;align-items:center;margin-top:8px;font-size:12px;'>
                                <button type='button' class='alumni-page-prev' ${page <= 1 ? 'disabled' : ''} style='padding:3px 8px;border:1px solid #d1d5db;border-radius:4px;background:#fff;cursor:pointer;'>‹ Prev</button>
                                <span>Page ${page} / ${pages}</span>
                                <button type='button' class='alumni-page-next' ${page >= pages ? 'disabled' : ''} style='padding:3px 8px;border:1px solid #d1d5db;border-radius:4px;background:#fff;cursor:pointer;'>Next ›</button>
                            </div>
                        </div>`;
                    };

                    const loadPage = async (marker, city, province, location, page) => {
                        const popup = marker.getPopup();
                        popup.setContent(`<b>${location}</b><br><br><span style='font-size:12px;'>Loading…</span>`);
                        try {
                            const res = await fetch(`/admin_dashboard?action=alumniAtLocation&city=${encodeURIComponent(city)}&province=${encodeURIComponent(province)}&page=${page}`);
                            const data = await res.json();
                            if (!data.success) { popup.setContent(`<b>${location}</b><br><br><span style='font-size:12px;'>Could not load alumni.</span>`); return; }
                            popup.setContent(listHtml(location, city, province, data, page));
                        } catch (e) {
                            popup.setContent(`<b>${location}</b><br><br><span style='font-size:12px;'>Could not load alumni.</span>`);
                        }
                    };

                    const addMarker = (location, cluster, lat, lng) => {
                        const marker = L.marker([lat, lng]).addTo(map).bindPopup(summaryHtml(location, cluster));
                        marker.on('popupopen', (e) => {
                            const el = e.popup.getElement();
                            if (!el) return;
                            const viewBtn = el.querySelector('.alumni-cluster-view');
                            if (viewBtn) {
                                viewBtn.onclick = () => loadPage(marker, cluster.city, cluster.province, location, 1);
                            }
                            const prev = el.querySelector('.alumni-page-prev');
                            const next = el.querySelector('.alumni-page-next');
                            const wrap = el.querySelector('.alumni-cluster-list');
                            if (wrap && prev) prev.onclick = () => loadPage(marker, cluster.city, cluster.province, location, Math.max(1, parseInt(wrap.dataset.page, 10) - 1));
                            if (wrap && next) next.onclick = () => loadPage(marker, cluster.city, cluster.province, location, Math.min(parseInt(wrap.dataset.pages, 10), parseInt(wrap.dataset.page, 10) + 1));
                        });
                    };

                    // Geocoded server-side (and cached there permanently) instead of through
                    // a browser-side third-party CORS proxy — that proxy (api.allorigins.win)
                    // is no longer reachable, which was silently collapsing every location
                    // onto the same fallback point and making the whole map look like one marker.
                    const coordinates = await this.geocodeLocations(entries.map(([location]) => location));
                    entries.forEach(([location, cluster]) => {
                        const coords = coordinates[location] || { lat: 14.1667, lng: 121.2167 };
                        addMarker(location, cluster, coords.lat, coords.lng);
                    });
                }
            } catch (e) {
                console.error('Map initialization error:', e);
            }
        },
        async geocodeLocations(locations) {
            try {
                const response = await fetch('/admin_dashboard?action=geocode', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ locations }),
                    signal: this._dashAbort ? this._dashAbort.signal : undefined
                });
                const data = await response.json();
                return data.success ? data.coordinates : {};
            } catch (error) {
                return {};
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
        // Mirrors DashboardStats::COLLEGE_ABBREVIATIONS / admin_reports.js's copy of the
        // same helper, so the college-drilldown buttons here match the abbreviations
        // used everywhere else in the app (worksheet tab names, etc.).
        getCollegeAbbreviation(collegeName) {
            const abbreviations = {
                "College of Computer Studies": "CCS",
                "College of Business Administration and Accountancy": "CBAA",
                "College of Arts and Sciences": "CAS",
                "College of Teacher Education": "CTE",
                "College of Engineering": "COE",
                "College of Agriculture": "CA",
                "College of Criminal Justice Education": "CCJE",
                "College of Industrial Technology": "CIT",
                "College of International Hospitality and Tourism Management": "CIHTM",
                "College of Nursing and Allied Health": "CNAH",
                "College of Fisheries": "CF",
                "College of Food Nutrition and Dietetics": "CFND"
            };
            if (abbreviations[collegeName]) return abbreviations[collegeName];

            const skipWords = new Set(['of', 'and', 'the', 'for']);
            const initials = collegeName.split(/\s+/)
                .filter(word => word && !skipWords.has(word.toLowerCase()))
                .map(word => word[0].toUpperCase())
                .join('');
            return initials || collegeName.substring(0, 3).toUpperCase();
        },
        addNotification(type, title, message) {
            const id = this.notificationId++;
            this.notifications.push({ id, type, title, message });
            setTimeout(() => this.removeNotification(id), 5000); // Auto-dismiss after 5 seconds
        },
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        },
        buildExportSections() {
            const stats = this.dashboardStats || {};

            const statusTotals = { Probational: 0, Contractual: 0, Regular: 0, 'Self-employed': 0, Unemployed: 0 };
            Object.values(stats.employment_status_per_program || {}).forEach(counts => {
                Object.keys(statusTotals).forEach(label => {
                    statusTotals[label] += counts[label] || 0;
                });
            });

            const alignmentLabels = ['Highly Aligned', 'Moderately Aligned', 'Slightly Aligned', 'Not Aligned'];
            const alignmentTotals = {};
            alignmentLabels.forEach(label => {
                alignmentTotals[label] = Object.values(stats.course_work_alignment || {}).reduce((sum, course) => {
                    return sum + ((course.counts && typeof course.counts[label] === 'number') ? course.counts[label] : 0);
                }, 0);
            });

            const graduatesPerCollege = {};
            Object.entries(stats.graduates_per_college || {}).forEach(([college, counts]) => {
                graduatesPerCollege[college] = counts.graduates || 0;
            });

            return {
                'General Insights': {
                    'Total Jobs': stats.total_jobs || 0,
                    'Total Employers': stats.total_companies || 0,
                    'Total Applications': stats.total_applications || 0,
                    'Total Alumni': stats.total_alumni || 0,
                },
                'Employment Status': statusTotals,
                'Work Location': stats.work_location_distribution || {},
                'Employment Sector': stats.employment_sector_distribution || {},
                'Course-Work Alignment': alignmentTotals,
                'Graduates per College': graduatesPerCollege,
            };
        },
        exportSectionsToRows(sections) {
            const rows = [];
            Object.entries(sections).forEach(([category, values]) => {
                Object.entries(values).forEach(([label, value]) => {
                    rows.push({ Category: `${category} - ${label}`, Value: value });
                });
            });
            return rows;
        },
        async exportToExcel() {
            await LibLoader.ensureXLSX();
            const rows = this.exportSectionsToRows(this.buildExportSections());
            const workbook = XLSX.utils.book_new();
            const worksheet = XLSX.utils.json_to_sheet(rows);
            XLSX.utils.book_append_sheet(workbook, worksheet, "Sheet1");
            XLSX.writeFile(workbook, "LSPU_Employment_Insights.xlsx");
        },
        async exportToPDF() {
            await LibLoader.ensureJsPDFAutoTable();
            if (typeof jspdf === 'undefined' || typeof jspdf.jsPDF === 'undefined') {
                alert('PDF export is unavailable right now — please try again in a moment.');
                return;
            }
            const { jsPDF } = jspdf;
            const doc = new jsPDF();
            const rows = this.exportSectionsToRows(this.buildExportSections())
                .map(row => [row.Category, row.Value]);

            doc.autoTable({
                head: [['Category', 'Value']],
                body: rows
            });
            doc.save("LSPU_Employment_Insights.pdf");
        },
        showDrilldown(type, label) {
          this.drilldown.active = true;
          this.drilldown.type = type;
          this.drilldown.label = label;
          let courseData = null;
          if (type === 'college') {
            courseData = this.dashboardStats.courses_per_college && this.dashboardStats.courses_per_college[label];
          } else if (type === 'location') {
            courseData = this.dashboardStats.courses_per_location && this.dashboardStats.courses_per_location[label];
          } else if (type === 'sector') {
            courseData = this.dashboardStats.courses_per_sector && this.dashboardStats.courses_per_sector[label];
          }
          if (courseData && Object.keys(courseData).length > 0) {
            this.drilldown.data = {
              labels: Object.keys(courseData),
              graduates: Object.values(courseData).map(c => c.graduates),
              employed: Object.values(courseData).map(c => c.employed)
            };
          } else {
            this.drilldown.data = {
              labels: [],
              graduates: [],
              employed: []
            };
          }
          this.$nextTick(() => this.renderDrilldownChart());
        },
        closeDrilldown() {
          this.drilldown.active = false;
          if (this.charts.drilldown) {
            this.charts.drilldown.destroy();
            this.charts.drilldown = null;
          }
        },
        async renderDrilldownChart() {
          if (!this.drilldown.active) return;
          if (window.LibLoader) { await LibLoader.ensureChart(); }
          const ctx = document.getElementById('drilldownChart');
          if (ctx) {
            if (this.charts.drilldown) this.charts.drilldown.destroy();
            if (this.drilldown.data) {
              this.charts.drilldown = new Chart(ctx, {
                type: 'bar',
                data: {
                  labels: this.drilldown.data.labels,
                  datasets: [
                    {
                      label: 'Graduates',
                      data: this.drilldown.data.graduates,
                      backgroundColor: 'rgba(54, 162, 235, 0.7)'
                    },
                    {
                      label: 'Employed',
                      data: this.drilldown.data.employed,
                      backgroundColor: 'rgba(75, 192, 192, 0.7)'
                    }
                  ]
                },
                options: {
                  responsive: true,
                  maintainAspectRatio: false,
                  plugins: { legend: { labels: { color: '#374151' } } }
                }
              });
            }
          }
        }
    }
}).mount('#app');