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
                        notifications: [],
                        notificationId: 0,
                        profile: {
                            profile_pic: '',
                            name: '',
                        },
                        programStats: [],
                        sectorStats: [],
                        locationStats: [],
                        statusStats: [],
                        loading: true,
                        companiesDropdownOpen: false,
            systemDropdownOpen: false,
                        alumniDropdownOpen: false,
                        colleges: [],
                        campuses: [],
                        years: [],
                        isSuperadmin: false,
                        selectedCampusId: '',
                        selectedYear: '',
                        showEmailReportModal: false,
                        emailReportCollege: '',
                        emailReportRecipient: '',
                        emailReportSending: false,
                        // Value -> { bg, font, bold } cell styling for the Detailed Report Excel export's
                        // tracer-report-style conditional coloring (Status/Location/Industry/Relevance columns).
                        reportColorMaps: {
                            statusAfter: {
                                'Regular': { bg: 'FCE4D6' },
                                'Probationary': { font: '1F4E78', bold: true },
                                'Contractual': { bg: 'F8CBAD' },
                                'Unemployed': { bg: '1F2937', font: 'FFFFFF', bold: true }
                            },
                            location: {
                                'Local': { bg: 'BDD7EE' },
                                'Abroad': { bg: 'FFE699' }
                            },
                            relevance: {
                                'Matched': { bg: 'C6E0B4', font: '375623', bold: true },
                                'Mismatched': { bg: 'F8CBAD', font: 'C00000', bold: true }
                            },
                            industry: {
                                'Manufacturing': { bg: '375623', font: 'FFFFFF', bold: true },
                                'IT Industry': { bg: 'C6E0B4', font: '375623', bold: true },
                                'Public Administration and Defense; Compulsory Social Security': { font: '1F4E78', bold: true },
                                'Health and Social Work': { font: 'C00000', bold: true },
                                'Wholesale and Retail Trade, Repair of Motorcycles and Personal Households goods': { font: '375623', bold: true }
                            }
                        }
                    }
                },
                computed: {
                    selectedCampusName() {
                        if (!this.selectedCampusId) return 'All Campuses';
                        const campus = this.campuses.find(c => String(c.campus_id) === String(this.selectedCampusId));
                        return campus ? campus.name : 'All Campuses';
                    },
                    summaryStats() {
                        const totalGraduates = this.programStats.reduce((sum, program) => sum + parseInt(program.total_graduates), 0);
                        const employedCount = this.programStats.reduce((sum, program) => sum + parseInt(program.employed_count), 0);
                        const relatedJobCount = this.programStats.reduce((sum, program) => sum + parseInt(program.related_job_count), 0);
                        
                        return {
                            totalGraduates,
                            employedCount,
                            employmentRate: totalGraduates > 0 ? Math.round((employedCount / totalGraduates) * 100) : 0,
                            jobMatchRate: employedCount > 0 ? Math.round((relatedJobCount / employedCount) * 100) : 0
                        };
                    }
                },
                mounted() {
                    this.applyDarkMode();
                    document.addEventListener('click', this.handleClickOutsideProfile);
                    this.fetchCampuses();
                    this.fetchSummaryData();
                    this.fetchColleges();
                    this.fetchYears();
                    fetch('admin_profile?action=details')
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.profile) {
                                this.profile = data.profile;
                            }
                        });
                    window.addEventListener('resize', this.handleResize);
                },
                beforeUnmount() {
                    window.removeEventListener('resize', this.handleResize);
                },
                watch: {
                    darkMode(val) {
                        this.applyDarkMode();
                    }
                },
                methods: {
                    campusQuery() {
                        let query = this.selectedCampusId ? ('&campus_id=' + encodeURIComponent(this.selectedCampusId)) : '';
                        if (this.selectedYear) {
                            query += '&year_graduated=' + encodeURIComponent(this.selectedYear);
                        }
                        return query;
                    },
                    async fetchCampuses() {
                        try {
                            const response = await fetch('/admin_user?action=campuses');
                            const data = await response.json();
                            if (data.success) {
                                this.campuses = data.campuses || [];
                                this.isSuperadmin = !!data.is_superadmin;
                            }
                        } catch (error) {
                            this.campuses = [];
                        }
                    },
                    onCampusChange() {
                        this.fetchColleges();
                        this.fetchYears();
                        this.fetchSummaryData();
                    },
                    onYearChange() {
                        this.fetchColleges();
                        this.fetchSummaryData();
                    },
                    async fetchColleges() {
                        try {
                            const response = await fetch('/admin_reports?action=colleges' + this.campusQuery());
                            const data = await response.json();
                            if (data.success) {
                                this.colleges = data.colleges;
                            }
                        } catch (error) {
                            this.colleges = [];
                        }
                    },
                    async fetchYears() {
                        try {
                            const response = await fetch('/admin_reports?action=years' + this.campusQuery());
                            const data = await response.json();
                            if (data.success) {
                                this.years = data.years;
                            }
                        } catch (error) {
                            this.years = [];
                        }
                    },
                    openEmailReportModal() {
                        this.emailReportCollege = '';
                        this.emailReportRecipient = '';
                        this.showEmailReportModal = true;
                    },
                    async sendReportEmail() {
                        if (!this.emailReportRecipient) {
                            this.showNotification('Please enter a recipient email.', 'error');
                            return;
                        }
                        this.emailReportSending = true;
                        try {
                            const response = await fetch('/admin_reports?action=emailReport', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    recipient_email: this.emailReportRecipient,
                                    college: this.emailReportCollege || null,
                                    campus_id: this.selectedCampusId || null,
                                    year_graduated: this.selectedYear || null
                                })
                            });
                            const data = await response.json();
                            if (data.success) {
                                this.showNotification('Report sent successfully!', 'success');
                                this.showEmailReportModal = false;
                            } else {
                                this.showNotification(data.message || 'Failed to send report.', 'error');
                            }
                        } catch (error) {
                            this.showNotification('Failed to send report.', 'error');
                        } finally {
                            this.emailReportSending = false;
                        }
                    },
                    async fetchSummaryData() {
                        try {
                            const response = await fetch('/admin_reports?action=summary' + this.campusQuery());
                            const data = await response.json();

                            if (data.success) {
                                this.programStats = data.program_stats;
                                this.sectorStats = data.sector_stats;
                                this.locationStats = data.location_stats;
                                this.statusStats = data.status_stats;
                            } else {
                                this.showNotification('Failed to load summary data', 'error');
                            }
                        } catch (error) {
                            console.error('Error fetching summary data:', error);
                            this.showNotification('Failed to load summary data', 'error');
                        } finally {
                            this.loading = false;
                        }
                    },
                    toggleSidebar() {
                        this.sidebarActive = !this.sidebarActive;
                    },
                    handleResize() {
                        this.isMobile = window.innerWidth < 768;
                        if (window.innerWidth >= 768) {
                            this.sidebarActive = true;
                        } else {
                            this.sidebarActive = false;
                        }
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
                    toggleProfileDropdown() {
                        this.profileDropdownOpen = !this.profileDropdownOpen;
                    },
                    handleClickOutsideProfile(event) {
                        if (this.profileDropdownOpen && !event.target.closest('.profile-dropdown-wrapper')) {
                            this.profileDropdownOpen = false;
                        }
                    },
                    handleNavClick() {
                        // Close dropdowns when navigating
                        this.companiesDropdownOpen = false;
                        this.alumniDropdownOpen = false;
                    },
                    confirmLogout() {
                        this.showLogoutModal = true;
                    },
                    logout() {
                        window.location.href = 'logout';
                    },
                    showNotification(message, type = 'success') {
                        const id = this.notificationId++;
                        this.notifications.push({ id, type, message });
                        setTimeout(() => this.removeNotification(id), 3000);
                    },
                    removeNotification(id) {
                        this.notifications = this.notifications.filter(n => n.id !== id);
                    },
                    calculatePercentage(part, total) {
                        return window.ReportHelpers.calculatePercentage(part, total);
                    },
                    // Enhanced applyEmploymentSummaryStyling function with proper header colors and styling
                    applyEmploymentSummaryStyling(worksheet) {
                        const headerRange = XLSX.utils.decode_range(worksheet['!ref']);
                        
                        // Apply enhanced styling to headers (first row) - dark blue background with borders
                        for (let col = headerRange.s.c; col <= headerRange.e.c; col++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: 0, c: col });
                            if (!worksheet[cellAddress]) {
                                worksheet[cellAddress] = { v: '', t: 's' };
                            }
                            worksheet[cellAddress].s = {
                                fill: { 
                                    patternType: "solid",
                                    fgColor: { rgb: "4472C4" } // Dark blue background
                                },
                                font: { 
                                    bold: true, 
                                    color: { rgb: "FFFFFF" }, // White text
                                    sz: 12 // Font size
                                },
                                alignment: { 
                                    horizontal: "center", 
                                    vertical: "center",
                                    wrapText: true
                                },
                                border: {
                                    top: { style: "thin", color: { rgb: "000000" } },
                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                    left: { style: "thin", color: { rgb: "000000" } },
                                    right: { style: "thin", color: { rgb: "000000" } }
                                }
                            };
                        }
                    
                        // Rest of your styling logic...
                        return worksheet;
                    },
                    
                    applyDetailedReportStyling(worksheet) {
                        const headerRange = XLSX.utils.decode_range(worksheet['!ref']);
                        
                        // Apply enhanced styling to first header row (employment summary) with borders
                        for (let col = 0; col < 10; col++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: 0, c: col });
                            if (!worksheet[cellAddress]) {
                                worksheet[cellAddress] = { v: '', t: 's' };
                            }
                            
                            let headerColor = "4472C4"; // Default blue
                            if (col === 0) headerColor = "006400"; // Dark green
                            else if (col >= 1 && col <= 3) headerColor = "000080"; // Dark blue
                            else if (col >= 4 && col <= 6) headerColor = "800020"; // Maroon
                            else if (col >= 7 && col <= 9) headerColor = "FF0000"; // Red
                            
                            worksheet[cellAddress].s = {
                                fill: { 
                                    patternType: "solid",
                                    fgColor: { rgb: headerColor }
                                },
                                font: { 
                                    bold: true, 
                                    color: { rgb: "FFFFFF" },
                                    sz: 12 // Font size
                                },
                                alignment: { 
                                    horizontal: "center", 
                                    vertical: "center",
                                    wrapText: true
                                },
                                border: {
                                    top: { style: "thin", color: { rgb: "000000" } },
                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                    left: { style: "thin", color: { rgb: "000000" } },
                                    right: { style: "thin", color: { rgb: "000000" } }
                                }
                            };
                        }
                        
                        // Apply styling to second header row (employment summary)
                        for (let col = 0; col < 10; col++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: 1, c: col });
                            if (!worksheet[cellAddress]) {
                                worksheet[cellAddress] = { v: '', t: 's' };
                            }
                            
                            if (col === 0) {
                                // Program header - dark green background
                                worksheet[cellAddress].s = {
                                    fill: { 
                                        patternType: "solid",
                                        fgColor: { rgb: "006400" } 
                                    },
                                    font: { 
                                        color: { rgb: "FFFFFF" }, 
                                        bold: true 
                                    },
                                    alignment: { 
                                        horizontal: "center", 
                                        vertical: "center" 
                                    }
                                };
                            } else if (col === 1 || col === 4 || col === 7) {
                                // Category columns - same colors as their sections
                                const sectionColors = {
                                    1: "000080", // Dark blue for Status
                                    4: "800020", // Dark purple/maroon for Sector
                                    7: "FF0000"  // Red for Location
                                };
                                worksheet[cellAddress].s = {
                                    fill: { 
                                        patternType: "solid",
                                        fgColor: { rgb: sectionColors[col] } 
                                    },
                                    font: { 
                                        color: { rgb: "FFFFFF" }, 
                                        bold: true 
                                    },
                                    alignment: { 
                                        horizontal: "center", 
                                        vertical: "center" 
                                    }
                                };
                            } else if (col === 2 || col === 5 || col === 8) {
                                // TOTAL columns - same colors as their sections
                                const sectionColors = {
                                    2: "000080", // Dark blue for Status
                                    5: "800020", // Dark purple/maroon for Sector
                                    8: "FF0000"  // Red for Location
                                };
                                worksheet[cellAddress].s = {
                                    fill: { 
                                        patternType: "solid",
                                        fgColor: { rgb: sectionColors[col] } 
                                    },
                                    font: { 
                                        color: { rgb: "FFFFFF" }, 
                                        bold: true 
                                    },
                                    alignment: { 
                                        horizontal: "center", 
                                        vertical: "center" 
                                    }
                                };
                            } else {
                                // Empty columns - same colors as their sections
                                const sectionColors = {
                                    3: "000080", // Dark blue for Status
                                    6: "800020", // Dark purple/maroon for Sector
                                    9: "FF0000"  // Red for Location
                                };
                                worksheet[cellAddress].s = {
                                    fill: { 
                                        patternType: "solid",
                                        fgColor: { rgb: sectionColors[col] } 
                                    },
                                    font: { 
                                        color: { rgb: "FFFFFF" }, 
                                        bold: true 
                                    },
                                    alignment: { 
                                        horizontal: "center", 
                                        vertical: "center" 
                                    }
                                };
                            }
                        }
                        
                        // Apply styling to employment summary data rows
                        for (let row = 2; row <= headerRange.e.r; row++) {
                            for (let col = 0; col < 10; col++) {
                                const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                                if (worksheet[cellAddress]) {
                                    if (col === 0) {
                                        // Program column - white background, black text
                                        worksheet[cellAddress].s = {
                                            font: { bold: false },
                                            alignment: { 
                                                horizontal: "left", 
                                                vertical: "center" 
                                            }
                                        };
                                    } else {
                                        // Data columns - white background, black text
                                        worksheet[cellAddress].s = {
                                            font: { bold: false },
                                            alignment: { 
                                                horizontal: "center", 
                                                vertical: "center" 
                                            }
                                        };
                                    }
                                }
                            }
                        }
                    
                        // Find and style the graduates table section
                        let graduatesStartRow = -1;
                        for (let row = 2; row <= headerRange.e.r; row++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: row, c: 0 });
                            if (worksheet[cellAddress] && worksheet[cellAddress].v === 'Campus') {
                                graduatesStartRow = row;
                                break;
                            }
                        }
                    
                        if (graduatesStartRow !== -1) {
                            // Apply styling to graduates table header row
                            for (let col = 0; col < headerRange.e.c; col++) {
                                const cellAddress = XLSX.utils.encode_cell({ r: graduatesStartRow, c: col });
                                if (!worksheet[cellAddress]) {
                                    worksheet[cellAddress] = { v: '', t: 's' };
                                }
                                
                                if (col >= 13 && col <= 15) {
                                    // Relevance of Employment section - green background
                                    worksheet[cellAddress].s = {
                                        fill: { 
                                            patternType: "solid",
                                            fgColor: { rgb: "008000" } 
                                        },
                                        font: { 
                                            bold: true, 
                                            color: { rgb: "FFFFFF" } 
                                        },
                                        alignment: { 
                                            horizontal: "center", 
                                            vertical: "center" 
                                        }
                                    };
                                } else if (col >= 16 && col <= 20) {
                                    // Personal Details section - red background
                                    worksheet[cellAddress].s = {
                                        fill: { 
                                            patternType: "solid",
                                            fgColor: { rgb: "FF0000" } 
                                        },
                                        font: { 
                                            bold: true, 
                                            color: { rgb: "FFFFFF" } 
                                        },
                                        alignment: { 
                                            horizontal: "center", 
                                            vertical: "center" 
                                        }
                                    };
                                } else {
                                    // Regular header styling
                                    worksheet[cellAddress].s = {
                                        fill: { 
                                            patternType: "solid",
                                            fgColor: { rgb: "4472C4" } 
                                        },
                                        font: { 
                                            bold: true,
                                            color: { rgb: "FFFFFF" } 
                                        },
                                        alignment: { 
                                            horizontal: "center", 
                                            vertical: "center" 
                                        }
                                    };
                                }
                            }
                    
                            // Apply styling to sub-headers row
                            for (let col = 0; col < headerRange.e.c; col++) {
                                const cellAddress = XLSX.utils.encode_cell({ r: graduatesStartRow + 1, c: col });
                                if (!worksheet[cellAddress]) {
                                    worksheet[cellAddress] = { v: '', t: 's' };
                                }
                                
                                if (col >= 13 && col <= 15) {
                                    // Relevance of Employment sub-headers - green background
                                    worksheet[cellAddress].s = {
                                        fill: { 
                                            patternType: "solid",
                                            fgColor: { rgb: "008000" } 
                                        },
                                        font: { 
                                            bold: true, 
                                            color: { rgb: "FFFFFF" } 
                                        },
                                        alignment: { 
                                            horizontal: "center", 
                                            vertical: "center" 
                                        }
                                    };
                                } else if (col >= 16 && col <= 20) {
                                    // Personal Details sub-headers - red background
                                    worksheet[cellAddress].s = {
                                        fill: { 
                                            patternType: "solid",
                                            fgColor: { rgb: "FF0000" } 
                                        },
                                        font: { 
                                            bold: true, 
                                            color: { rgb: "FFFFFF" } 
                                        },
                                        alignment: { 
                                            horizontal: "center", 
                                            vertical: "center" 
                                        }
                                    };
                                } else {
                                    // Regular sub-header styling
                                    worksheet[cellAddress].s = {
                                        fill: { 
                                            patternType: "solid",
                                            fgColor: { rgb: "4472C4" } 
                                        },
                                        font: { 
                                            bold: true,
                                            color: { rgb: "FFFFFF" } 
                                        },
                                        alignment: { 
                                            horizontal: "center", 
                                            vertical: "center" 
                                        }
                                    };
                                }
                            }
                    
                            // Apply styling to graduates data rows
                            for (let row = graduatesStartRow + 2; row <= headerRange.e.r; row++) {
                                for (let col = 0; col < headerRange.e.c; col++) {
                                    const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                                    if (worksheet[cellAddress]) {
                                        if (col === 0) {
                                            // Course header row - yellow background
                                            if (worksheet[cellAddress].v && (worksheet[cellAddress].v.includes('BS ') || worksheet[cellAddress].v.includes('Bachelor'))) {
                                                worksheet[cellAddress].s = {
                                                    fill: { 
                                                        patternType: "solid",
                                                        fgColor: { rgb: "FFFF00" } 
                                                    },
                                                    font: { bold: true },
                                                    alignment: { 
                                                        horizontal: "center", 
                                                        vertical: "center" 
                                                    }
                                                };
                                            } else if (col >= 13 && col <= 15) {
                                                // Relevance of Employment data - light green background
                                                worksheet[cellAddress].s = {
                                                    fill: { 
                                                        patternType: "solid",
                                                        fgColor: { rgb: "E6FFE6" } 
                                                    },
                                                    font: { bold: false },
                                                    alignment: { 
                                                        horizontal: "center", 
                                                        vertical: "center" 
                                                    }
                                                };
                                            } else if (col >= 16 && col <= 20) {
                                                // Personal Details data - light red background
                                                worksheet[cellAddress].s = {
                                                    fill: { 
                                                        patternType: "solid",
                                                        fgColor: { rgb: "FFE6E6" } 
                                                    },
                                                    font: { bold: false },
                                                    alignment: { 
                                                        horizontal: "left", 
                                                        vertical: "center" 
                                                    }
                                                };
                                            } else if (col === 1) {
                                                // Program Name column - light blue background
                                                worksheet[cellAddress].s = {
                                                    fill: { 
                                                        patternType: "solid",
                                                        fgColor: { rgb: "E6F3FF" } 
                                                    },
                                                    font: { bold: false },
                                                    alignment: { 
                                                        horizontal: "left", 
                                                        vertical: "center" 
                                                    }
                                                };
                                            } else {
                                                // Regular data styling
                                                worksheet[cellAddress].s = {
                                                    font: { bold: false },
                                                    alignment: { 
                                                        horizontal: "center", 
                                                        vertical: "center" 
                                                    }
                                                };
                                            }
                                        } else {
                                            if (col >= 13 && col <= 15) {
                                                // Relevance of Employment data - light green background
                                                worksheet[cellAddress].s = {
                                                    fill: { 
                                                        patternType: "solid",
                                                        fgColor: { rgb: "E6FFE6" } 
                                                    },
                                                    font: { bold: false },
                                                    alignment: { 
                                                        horizontal: "center", 
                                                        vertical: "center" 
                                                    }
                                                };
                                            } else if (col >= 16 && col <= 20) {
                                                // Personal Details data - light red background
                                                worksheet[cellAddress].s = {
                                                    fill: { 
                                                        patternType: "solid",
                                                        fgColor: { rgb: "FFE6E6" } 
                                                    },
                                                    font: { bold: false },
                                                    alignment: { 
                                                        horizontal: "left", 
                                                        vertical: "center" 
                                                    }
                                                };
                                            } else if (col === 1) {
                                                // Program Name column - light blue background
                                                worksheet[cellAddress].s = {
                                                    fill: { 
                                                        patternType: "solid",
                                                        fgColor: { rgb: "E6F3FF" } 
                                                    },
                                                    font: { bold: false },
                                                    alignment: { 
                                                        horizontal: "left", 
                                                        vertical: "center" 
                                                    }
                                                };
                                            } else {
                                                // Regular data styling
                                                worksheet[cellAddress].s = {
                                                    font: { bold: false },
                                                    alignment: { 
                                                        horizontal: "center", 
                                                        vertical: "center" 
                                                    }
                                                };
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        return worksheet;
                    },

                    async exportEmploymentSummary(format) {
                        try {
                            if (format === 'excel') {
                                // Fetch data from fetch_all_report_data.php
                                const response = await fetch('/admin_reports?action=fullData' + this.campusQuery());
                                const data = await response.json();
                                
                                if (!data.success) {
                                    throw new Error(data.error || 'Failed to fetch data');
                                }
                    
                                // Create new ExcelJS workbook
                                const workbook = new ExcelJS.Workbook();
                                const worksheet = workbook.addWorksheet("Employment Summary");
                    
                                // Define colors
                                const colors = {
                                    headerBg: '4F46E5', // Indigo
                                    headerText: 'FFFFFF', // White
                                    collegeBg: 'E0E7FF', // Light indigo
                                    collegeText: '1E3A8A', // Dark blue
                                    courseBg: 'FFFFFF', // White
                                    courseText: '000000', // Black
                                    highlightGreen: 'D1FAE5', // Light green
                                    highlightRed: 'FEE2E2'  // Light red
                                };
                    
                                // Add header row
                                const headerRow = worksheet.addRow([
                                    '', 'No. of Graduates', 'No. of Employed', 
                                    'Percentage', 'Work Related/in-line to course', '% Matched'
                                ]);
                    
                                // Style header row
                                headerRow.eachCell((cell) => {
                                    cell.fill = {
                                        type: 'pattern',
                                        pattern: 'solid',
                                        fgColor: { argb: colors.headerBg }
                                    };
                                    cell.font = {
                                        bold: true,
                                        color: { argb: colors.headerText }
                                    };
                                    cell.alignment = { 
                                        horizontal: 'center', 
                                        vertical: 'middle',
                                        wrapText: true
                                    };
                                    cell.border = {
                                        top: { style: 'thin' },
                                        left: { style: 'thin' },
                                        bottom: { style: 'thin' },
                                        right: { style: 'thin' }
                                    };
                                });
                    
                                // Group data by college
                                const collegeGroups = {};
                                data.employment_summary.forEach(row => {
                                    if (row.is_header) {
                                        if (!collegeGroups[row.college]) {
                                            collegeGroups[row.college] = [];
                                        }
                                        collegeGroups[row.college].push({
                                            type: 'college_header',
                                            data: row
                                        });
                                    } else if (row.course && row.course.trim() !== '') {
                                        const collegeName = this.findCollegeForCourse(data.employment_summary, row.course);
                                        if (!collegeGroups[collegeName]) {
                                            collegeGroups[collegeName] = [];
                                        }
                                        collegeGroups[collegeName].push({
                                            type: 'course_data',
                                            data: row
                                        });
                                    }
                                });
                    
                                // Add data rows with styling
                                Object.keys(collegeGroups).forEach(collegeName => {
                                    const collegeData = collegeGroups[collegeName];
                                    
                                    // Add college header row
                                    const collegeRow = worksheet.addRow([
                                        collegeName, '', '', '', '', ''
                                    ]);
                                    
                                    // Style college header row
                                    collegeRow.eachCell((cell) => {
                                        cell.fill = {
                                            type: 'pattern',
                                            pattern: 'solid',
                                            fgColor: { argb: colors.collegeBg }
                                        };
                                        cell.font = {
                                            bold: true,
                                            color: { argb: colors.collegeText }
                                        };
                                        cell.border = {
                                            top: { style: 'thin' },
                                            left: { style: 'thin' },
                                            bottom: { style: 'thin' },
                                            right: { style: 'thin' }
                                        };
                                    });
                    
                                    // Add course data rows
                                    collegeData.forEach(item => {
                                        if (item.type === 'course_data') {
                                            const courseRow = worksheet.addRow([
                                                item.data.course,
                                                item.data.total_graduates,
                                                item.data.employed_count,
                                                item.data.employment_rate,
                                                item.data.related_job_count,
                                                item.data.match_rate
                                            ]);
                    
                                            // Style course data row
                                            courseRow.eachCell((cell, colNumber) => {
                                                cell.fill = {
                                                    type: 'pattern',
                                                    pattern: 'solid',
                                                    fgColor: { argb: colors.courseBg }
                                                };
                                                cell.font = {
                                                    color: { argb: colors.courseText }
                                                };
                                                cell.border = {
                                                    top: { style: 'thin' },
                                                    left: { style: 'thin' },
                                                    bottom: { style: 'thin' },
                                                    right: { style: 'thin' }
                                                };
                    
                                                // Right-align numeric columns
                                                if (colNumber > 1) {
                                                    cell.alignment = { horizontal: 'right' };
                                                }
                    
                                                // Highlight low/high percentages
                                                if (colNumber === 4) { // Percentage column
                                                    const rate = parseFloat(item.data.employment_rate) || 0;
                                                    if (rate < 50) {
                                                        cell.fill.fgColor = { argb: colors.highlightRed };
                                                    } else if (rate > 80) {
                                                        cell.fill.fgColor = { argb: colors.highlightGreen };
                                                    }
                                                }
                                            });
                                        }
                                    });
                                });
                    
                                // Set column widths
                                worksheet.columns = [
                                    { width: 30 }, // College/Course names
                                    { width: 18 }, // No. of Graduates
                                    { width: 18 }, // No. of Employed
                                    { width: 15 }, // Percentage
                                    { width: 25 }, // Work Related/in-line to course
                                    { width: 15 }  // % Matched
                                ];
                    
                                // Generate filename with current date
                                const filename = `employment_summary_${new Date().toISOString().split('T')[0]}.xlsx`;
                                
                                // Export to Excel file
                                const buffer = await workbook.xlsx.writeBuffer();
                                saveAs(new Blob([buffer]), filename);
                                
                                this.showNotification('Excel report exported successfully!', 'success');
                            }
                        } catch (error) {
                            console.error('Export error:', error);
                            this.showNotification('Failed to export report', 'error');
                        }
                    },

                    async exportDetailedEmployment(format) {
                        try {
                            if (format === 'excel') {
                                const response = await fetch('/admin_reports?action=fullData' + this.campusQuery());
                                const data = await response.json();
                                
                                if (!data.success) {
                                    throw new Error(data.error || 'Failed to fetch data');
                                }

                                // Overall industry distribution across every graduate in this report
                                // (not per-college), rendered once as a pie-chart image and placed to
                                // the right of the first worksheet's table.
                                const industryCounts = {};
                                if (data.detailed_employment && Array.isArray(data.detailed_employment)) {
                                    data.detailed_employment.forEach(row => {
                                        if (row['Section'] === 'Complete Details' && row['Industry']) {
                                            industryCounts[row['Industry']] = (industryCounts[row['Industry']] || 0) + 1;
                                        }
                                    });
                                }
                                const industryChart = this.buildIndustryPieChartImage(industryCounts);

                                const workbook = new ExcelJS.Workbook();

                                const styles = {
                                    header: {
                                        fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: '4F46E5' } },
                                        font: { bold: true, color: { argb: 'FFFFFF' }, size: 12 },
                                        alignment: { horizontal: 'center', vertical: 'middle', wrapText: true }
                                    },
                                    subHeader: {
                                        fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: '93C5FD' } },
                                        font: { bold: true, size: 11 },
                                        alignment: { horizontal: 'center', vertical: 'middle' }
                                    },
                                    collegeHeader: {
                                        fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'E0E7FF' } },
                                        font: { bold: true, color: { argb: '1E3A8A' } },
                                        alignment: { horizontal: 'left', vertical: 'middle' }
                                    },
                                    courseHeader: {
                                        fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'F3F4F6' } },
                                        font: { bold: true, italic: true },
                                        alignment: { horizontal: 'left', vertical: 'middle' }
                                    },
                                    dataRow: {
                                        font: { color: { argb: '000000' } },
                                        alignment: { vertical: 'middle' }
                                    },
                                    border: {
                                        top: { style: 'thin' },
                                        left: { style: 'thin' },
                                        bottom: { style: 'thin' },
                                        right: { style: 'thin' }
                                    }
                                };
                    
                                // Colleges/courses are derived from the actual report data (not a fixed
                                // San Pablo-only list) so the exported workbook matches whichever campus's
                                // data was returned -- a campus admin only ever gets their own campus's rows,
                                // and a superadmin gets all of them.
                                const collegeCoursesMap = {};
                                if (data.detailed_employment && Array.isArray(data.detailed_employment)) {
                                    data.detailed_employment.forEach(row => {
                                        if (row['Section'] === 'Complete Details' && row['College'] && row['Course']) {
                                            if (!collegeCoursesMap[row['College']]) {
                                                collegeCoursesMap[row['College']] = new Set();
                                            }
                                            collegeCoursesMap[row['College']].add(row['Course']);
                                        }
                                    });
                                }

                                const colleges = Object.keys(collegeCoursesMap).sort().map(collegeName => ({
                                    name: collegeName,
                                    courses: Array.from(collegeCoursesMap[collegeName]).sort()
                                }));
                    
                                for (const college of colleges) {
                                    const worksheet = workbook.addWorksheet(this.getCollegeAbbreviation(college.name));
                                    
                                    const headerRow1 = worksheet.addRow([]);
                                    const headerRow2 = worksheet.addRow([]);
                    
                                    headerRow1.getCell(7).value = 'Status of Employment';
                                    worksheet.mergeCells(`G${headerRow1.number}:I${headerRow1.number}`);
                                    headerRow2.getCell(7).value = 'Category';
                                    headerRow2.getCell(8).value = 'TOTAL';
                                    headerRow2.getCell(9).value = '';

                                    headerRow1.getCell(10).value = 'Employment Sector';
                                    worksheet.mergeCells(`J${headerRow1.number}:L${headerRow1.number}`);
                                    headerRow2.getCell(10).value = 'Category';
                                    headerRow2.getCell(11).value = 'TOTAL';
                                    headerRow2.getCell(12).value = '';

                                    headerRow1.getCell(13).value = 'Location of Employment';
                                    worksheet.mergeCells(`M${headerRow1.number}:O${headerRow1.number}`);
                                    headerRow2.getCell(13).value = 'Category';
                                    headerRow2.getCell(14).value = 'TOTAL';
                                    headerRow2.getCell(15).value = '';

                                    // Each section gets its own header color (blue/purple/red) instead
                                    // of one flat color, so the groups are visually distinct at a glance.
                                    const sectionHeaderColors = { 7: '2E5B8A', 10: '6B3FA0', 13: 'C0392B' };
                                    [7, 10, 13].forEach(startCol => {
                                        const bg = sectionHeaderColors[startCol];
                                        [0, 1, 2].forEach(offset => {
                                            const cell = headerRow1.getCell(startCol + offset);
                                            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: bg } };
                                            cell.font = { bold: true, color: { argb: 'FFFFFF' }, size: 12 };
                                            cell.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
                                        });
                                        const subBg = { '2E5B8A': 'BDD7EE', '6B3FA0': 'D9C2EC', 'C0392B': 'F5B7B1' }[bg];
                                        [0, 1, 2].forEach(offset => {
                                            const cell = headerRow2.getCell(startCol + offset);
                                            if (cell.value === '') return;
                                            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: subBg } };
                                            cell.font = { bold: true, size: 11 };
                                            cell.alignment = { horizontal: 'center', vertical: 'middle' };
                                        });
                                    });
                    
                                    for (const course of college.courses) {
                                        const row = worksheet.addRow([]);
                                        row.getCell(7).value = course;
                                        
                                        const statusData = data.employment_status_summary.find(row => row.Course === course && row.College === '');
                                        if (statusData) {
                                            const statuses = ['Regular', 'Probational', 'Contractual'];
                                            let mostCommonStatus = 'Contractual';
                                            let maxCount = 0;
                                            
                                            statuses.forEach(status => {
                                                const count = parseInt(statusData[status]) || 0;
                                                if (count > maxCount) {
                                                    maxCount = count;
                                                    mostCommonStatus = status;
                                                }
                                            });
                                            
                                            const totalStatus = statuses.reduce((sum, status) => sum + (parseInt(statusData[status]) || 0), 0);
                                            
                                            row.getCell(8).value = mostCommonStatus;
                                            row.getCell(9).value = totalStatus;
                                        } else {
                                            row.getCell(8).value = 'Contractual';
                                            row.getCell(9).value = 0;
                                        }
                                        
                                        const sectorData = data.employment_sector_summary.find(row => row.Course === course && row.College === '');
                                        if (sectorData) {
                                            const sectors = ['Government', 'Private', 'Self-employed'];
                                            let mostCommonSector = 'Private';
                                            let maxCount = 0;
                                            
                                            sectors.forEach(sector => {
                                                const count = parseInt(sectorData[sector]) || 0;
                                                if (count > maxCount) {
                                                    maxCount = count;
                                                    mostCommonSector = sector;
                                                }
                                            });
                                            
                                            const totalSector = sectors.reduce((sum, sector) => sum + (parseInt(sectorData[sector]) || 0), 0);
                                            
                                            row.getCell(11).value = mostCommonSector;
                                            row.getCell(12).value = totalSector;
                                        } else {
                                            row.getCell(11).value = 'Private';
                                            row.getCell(12).value = 0;
                                        }
                                        
                                        const locationData = data.location_summary.find(row => row.Course === course && row.College === '');
                                        if (locationData) {
                                            const locations = ['Local', 'Abroad'];
                                            let mostCommonLocation = 'Local';
                                            let maxCount = 0;
                                            
                                            locations.forEach(location => {
                                                const count = parseInt(locationData[location]) || 0;
                                                if (count > maxCount) {
                                                    maxCount = count;
                                                    mostCommonLocation = location;
                                                }
                                            });
                                            
                                            const totalLocation = locations.reduce((sum, location) => sum + (parseInt(locationData[location]) || 0), 0);
                                            
                                            row.getCell(14).value = mostCommonLocation;
                                            row.getCell(15).value = totalLocation;
                                        } else {
                                            row.getCell(14).value = 'Local';
                                            row.getCell(15).value = 0;
                                        }
                    
                                        for (let i = 7; i <= 15; i++) {
                                            const cell = row.getCell(i);
                                            cell.style = {
                                                ...styles.dataRow,
                                                border: styles.border
                                            };
                                        }
                                    }
                    
                                    worksheet.addRow([]);
                                    worksheet.addRow([]);
                    
                                    const graduatesHeader = worksheet.addRow([
                                        'Campus', 'Program Name', 'Name of Graduates', 'Gender',
                                        'Date of Graduation', 'Date Hired for Current Job', 'Green Jobs', 'CTR',
                                        'Status of Employment prior to graduation',
                                        'Status of Employment after graduation',
                                        'Sector (Private/Government)',
                                        'Location of Employment (Local/Abroad)',
                                        'Average Monthly Income',
                                        'Company/Organization',
                                        'Type of Industry (Nature of Work)',
                                        '', 'Relevance of Employment', '',
                                        '', '', 'Personal Details', '', ''
                                    ]);

                                    const subHeaders = worksheet.addRow([
                                        '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                                        'Employed-Aligned to their Program',
                                        'Self-Employed Graduates (1)',
                                        'Enrolled in Further Studies (1)',
                                        'Contact Number',
                                        'Email Address',
                                        'Civil Status',
                                        'Birthday',
                                        'Home Address'
                                    ]);
                    
                                    graduatesHeader.eachCell(cell => {
                                        cell.style = {
                                            ...styles.header,
                                            alignment: { ...styles.header.alignment, horizontal: 'center' }
                                        };
                                    });
                    
                                    subHeaders.eachCell(cell => {
                                        cell.style = {
                                            ...styles.subHeader,
                                            alignment: { ...styles.subHeader.alignment, horizontal: 'center' }
                                        };
                                    });
                    
                                    const graduatesByCourse = {};
                                    if (data.detailed_employment && Array.isArray(data.detailed_employment)) {
                                        data.detailed_employment.forEach(row => {
                                            if (row['Section'] === 'Complete Details' && 
                                                row['Full Name'] && row['Full Name'].trim() !== '' &&
                                                row['Course'] && row['Course'].trim() !== '' &&
                                                row['College'] === college.name) {
                                                
                                                const course = row['Course'];
                                                if (!graduatesByCourse[course]) {
                                                    graduatesByCourse[course] = [];
                                                }
                                                graduatesByCourse[course].push(row);
                                            }
                                        });
                                    }
                    
                                    for (const course of Object.keys(graduatesByCourse)) {
                                        const courseHeader = worksheet.addRow(Array(23).fill(''));
                                        courseHeader.getCell(1).value = course;
                                        courseHeader.eachCell(cell => {
                                            cell.style = styles.courseHeader;
                                        });
                    
                                        for (const row of graduatesByCourse[course]) {
                                            const campus = data.campus_name || 'All Campuses';
                                            const graduationDate = row['Year Graduated'] ? `July 5, ${row['Year Graduated']}` : '';
                                            const hiringDate = row['Date Hired'] ?
                                                new Date(row['Date Hired']).toLocaleDateString('en-US', {
                                                    month: '2-digit',
                                                    day: '2-digit',
                                                    year: 'numeric'
                                                }) : '';
                                            const greenJobs = row['Green Jobs'] || 'No';
                                            const ctr = (row['Employment Status'] && row['Employment Status'] !== 'Unemployed') ? '1' : '';
                                            const statusPrior = 'Student';
                                            let statusAfter = '';
                                            
                                            if (row['Employment Status']) {
                                                switch(row['Employment Status']) {
                                                    case 'Regular': statusAfter = 'Regular'; break;
                                                    case 'Probational': statusAfter = 'Probationary'; break;
                                                    case 'Contractual': statusAfter = 'Contractual'; break;
                                                    default: statusAfter = 'Unemployed';
                                                }
                                            }
                                            
                                            const sector = row['Employment Sector'] || '';
                                            const location = row['Location of Work'] || '';
                                            const income = '';
                                            const industryType = row['Industry'] || '';
                                            const employedAligned = row['Job Related'] === 'Yes' ? 'Matched' : 'Mismatched';
                                            const selfEmployed = '';
                                            const furtherStudies = '';
                                            const contactNumber = row['Contact'] || '';
                                            const emailAddress = row['Email'] || '';
                                            const civilStatus = row['Civil Status'] || '';
                                            
                                            const birthday = row['Birthdate'] ? 
                                                new Date(row['Birthdate']).toLocaleDateString('en-US', {
                                                    month: '2-digit',
                                                    day: '2-digit',
                                                    year: 'numeric'
                                                }) : '';
                                            
                                            const homeAddress = row['Address'] || '';
                                            
                                            const gradRow = worksheet.addRow([
                                                campus,
                                                row['Course'] || '',
                                                row['Full Name'] || '',
                                                row['Gender'] || '',
                                                graduationDate,
                                                hiringDate,
                                                greenJobs,
                                                ctr,
                                                statusPrior,
                                                statusAfter,
                                                sector,
                                                location,
                                                income,
                                                row['Company'] || '',
                                                industryType,
                                                employedAligned,
                                                selfEmployed,
                                                furtherStudies,
                                                contactNumber,
                                                emailAddress,
                                                civilStatus,
                                                birthday,
                                                homeAddress
                                            ]);

                                            gradRow.eachCell(cell => {
                                                cell.style = {
                                                    ...styles.dataRow,
                                                    border: styles.border
                                                };
                                            });

                                            // Green Jobs pill-style highlight, matching the app's existing
                                            // light-green/light-red conditional-formatting convention.
                                            const greenJobsCell = gradRow.getCell(7);
                                            greenJobsCell.fill = {
                                                type: 'pattern',
                                                pattern: 'solid',
                                                fgColor: { argb: greenJobs === 'Yes' ? 'D1FAE5' : 'FEE2E2' }
                                            };
                                            greenJobsCell.font = { ...greenJobsCell.font, bold: true, color: { argb: greenJobs === 'Yes' ? '065F46' : '991B1B' } };
                                            greenJobsCell.alignment = { horizontal: 'center', vertical: 'middle' };

                                            // Value-based conditional coloring for the remaining tracer-report columns.
                                            this.applyConditionalFill(gradRow.getCell(10), statusAfter, this.reportColorMaps.statusAfter);
                                            this.applyConditionalFill(gradRow.getCell(12), location, this.reportColorMaps.location);
                                            this.applyConditionalFill(gradRow.getCell(15), industryType, this.reportColorMaps.industry);
                                            this.applyConditionalFill(gradRow.getCell(16), employedAligned, this.reportColorMaps.relevance);
                                        }

                                        worksheet.addRow(Array(23).fill(''));
                                    }

                                    worksheet.columns = [
                                        { width: 25 }, { width: 25 }, { width: 30 }, { width: 10 },
                                        { width: 20 }, { width: 20 }, { width: 15 }, { width: 25 },
                                        { width: 25 }, { width: 25 }, { width: 20 }, { width: 25 },
                                        { width: 20 }, { width: 30 }, { width: 40 }, { width: 25 },
                                        { width: 25 }, { width: 25 }, { width: 15 }, { width: 30 },
                                        { width: 15 }, { width: 15 }, { width: 50 }
                                    ];

                                    worksheet.views = [
                                        { state: 'frozen', xSplit: 0, ySplit: 2 }
                                    ];

                                    // Overall industry-distribution pie chart, placed once to the right
                                    // of the first worksheet's table.
                                    if (industryChart && college === colleges[0]) {
                                        const imageId = workbook.addImage({ base64: industryChart.dataUrl, extension: 'png' });
                                        const displayWidth = 560;
                                        const displayHeight = Math.round(displayWidth * (industryChart.height / industryChart.width));
                                        worksheet.addImage(imageId, { tl: { col: 26, row: 1 }, ext: { width: displayWidth, height: displayHeight } });
                                    }
                                }

                                const buffer = await workbook.xlsx.writeBuffer();
                                saveAs(new Blob([buffer]), `detailed_employment_${new Date().toISOString().split('T')[0]}.xlsx`);
                                this.showNotification('Excel report exported successfully!', 'success');
                            }
                        } catch (error) {
                            console.error('Export error:', error);
                            this.showNotification('Failed to export detailed employment report', 'error');
                        }
                    },

                    /** Colors one Excel cell based on its value, using a { value: {bg, font, bold} } map from reportColorMaps. No-ops when the value isn't in the map. */
                    applyConditionalFill(cell, value, colorMap) {
                        const style = colorMap[value];
                        if (!style) {
                            return;
                        }
                        if (style.bg) {
                            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: style.bg } };
                        }
                        cell.font = { ...cell.font, color: { argb: style.font || '000000' }, bold: !!style.bold };
                        cell.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
                    },

                    /** Renders an industry-distribution pie chart (with a legend) to a PNG data URL for embedding into an exported Excel sheet. Returns null when there's no data to chart. */
                    buildIndustryPieChartImage(industryCounts) {
                        const entries = Object.entries(industryCounts)
                            .filter(([, count]) => count > 0)
                            .sort((a, b) => b[1] - a[1]);

                        if (entries.length === 0) {
                            return null;
                        }

                        const palette = ['#4F46E5', '#059669', '#D97706', '#DC2626', '#7C3AED', '#0EA5E9', '#DB2777', '#65A30D', '#EA580C', '#0891B2', '#9333EA', '#64748B'];
                        const total = entries.reduce((sum, [, count]) => sum + count, 0);

                        const canvas = document.createElement('canvas');
                        canvas.width = 980;
                        canvas.height = Math.max(420, 66 + entries.length * 24 + 20);
                        const ctx = canvas.getContext('2d');

                        ctx.fillStyle = '#FFFFFF';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);

                        ctx.fillStyle = '#1F2937';
                        ctx.font = 'bold 18px Arial';
                        ctx.fillText('Industry Distribution (All Programs)', 16, 30);

                        const cx = 180;
                        const cy = 230;
                        const r = 150;
                        let startAngle = -Math.PI / 2;

                        entries.forEach(([, count], i) => {
                            const sliceAngle = (count / total) * Math.PI * 2;
                            ctx.beginPath();
                            ctx.moveTo(cx, cy);
                            ctx.arc(cx, cy, r, startAngle, startAngle + sliceAngle);
                            ctx.closePath();
                            ctx.fillStyle = palette[i % palette.length];
                            ctx.fill();
                            ctx.strokeStyle = '#FFFFFF';
                            ctx.lineWidth = 2;
                            ctx.stroke();
                            startAngle += sliceAngle;
                        });

                        let legendY = 66;
                        ctx.font = '13px Arial';
                        entries.forEach(([label, count], i) => {
                            const pct = ((count / total) * 100).toFixed(1);
                            ctx.fillStyle = palette[i % palette.length];
                            ctx.fillRect(390, legendY, 14, 14);
                            ctx.fillStyle = '#1F2937';
                            ctx.fillText(`${label}: ${count} (${pct}%)`, 412, legendY + 12);
                            legendY += 24;
                        });

                        return { dataUrl: canvas.toDataURL('image/png'), width: canvas.width, height: canvas.height };
                    },

                    async exportIndustryAnalysis(format) {
                        try {
                            if (format === 'excel') {
                                const response = await fetch('/admin_reports?action=fullData' + this.campusQuery());
                                const data = await response.json();
                                
                                if (!data.success) {
                                    throw new Error(data.error || 'Failed to fetch data');
                                }
                                
                                const workbook = new ExcelJS.Workbook();
                                
                                const styles = {
                                    header1: {
                                        fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: '641E16' } },
                                        font: { bold: true, color: { argb: 'FFFFFF' } },
                                        alignment: { horizontal: 'center', vertical: 'middle', wrapText: true }
                                    },
                                    header2: {
                                        fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: '641E16' } },
                                        font: { bold: true, color: { argb: 'FFFFFF' } },
                                        alignment: { horizontal: 'center', vertical: 'middle' }
                                    },
                                    dataRow: {
                                        font: { color: { argb: '000000' } },
                                        alignment: { vertical: 'middle' },
                                        border: {
                                            top: { style: 'thin' },
                                            left: { style: 'thin' },
                                            bottom: { style: 'thin' },
                                            right: { style: 'thin' }
                                        }
                                    },
                                    industryLabel: {
                                        font: { bold: true, color: { argb: '117864' } },
                                        alignment: { vertical: 'middle' },
                                        border: {
                                            top: { style: 'thin' },
                                            left: { style: 'thin' },
                                            bottom: { style: 'thin' },
                                            right: { style: 'thin' }
                                        }
                                    },
                                    maleCell: {
                                        fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: '2E5B8A' } },
                                        font: { bold: true, color: { argb: 'FFFFFF' } },
                                        alignment: { horizontal: 'center', vertical: 'middle' },
                                        border: {
                                            top: { style: 'thin' },
                                            left: { style: 'thin' },
                                            bottom: { style: 'thin' },
                                            right: { style: 'thin' }
                                        }
                                    },
                                    femaleCell: {
                                        font: { color: { argb: '000000' } },
                                        alignment: { horizontal: 'center', vertical: 'middle' },
                                        border: {
                                            top: { style: 'thin' },
                                            left: { style: 'thin' },
                                            bottom: { style: 'thin' },
                                            right: { style: 'thin' }
                                        }
                                    },
                                    totalCell: {
                                        fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'F5B7B1' } },
                                        font: { bold: true, color: { argb: '922B21' } },
                                        alignment: { horizontal: 'center', vertical: 'middle' },
                                        border: {
                                            top: { style: 'thin' },
                                            left: { style: 'thin' },
                                            bottom: { style: 'thin' },
                                            right: { style: 'thin' }
                                        }
                                    }
                                };
                    
                                // Colleges/courses are derived from the actual report data (not a fixed
                                // San Pablo-only list) so the exported workbook matches whichever campus's
                                // data was returned -- a campus admin only ever gets their own campus's rows,
                                // and a superadmin gets all of them.
                                const collegeCoursesMap = {};
                                if (data.detailed_employment && Array.isArray(data.detailed_employment)) {
                                    data.detailed_employment.forEach(row => {
                                        if (row['Section'] === 'Complete Details' && row['College'] && row['Course']) {
                                            if (!collegeCoursesMap[row['College']]) {
                                                collegeCoursesMap[row['College']] = new Set();
                                            }
                                            collegeCoursesMap[row['College']].add(row['Course']);
                                        }
                                    });
                                }

                                const colleges = Object.keys(collegeCoursesMap).sort().map(collegeName => ({
                                    name: collegeName,
                                    courses: Array.from(collegeCoursesMap[collegeName]).sort()
                                }));
                    
                                const industryCategories = [...new Set(data.industry_analysis.map(row => row.Industry))];
                    
                                for (const college of colleges) {
                                    const worksheet = workbook.addWorksheet(this.getCollegeAbbreviation(college.name));
                    
                                    const headerRow1 = worksheet.addRow(['Nature of Work/Industry']);
                                    const headerRow2 = worksheet.addRow(['Nature of Work/Industry']);
                    
                                    college.courses.forEach(course => {
                                        const startCol = headerRow1.actualCellCount + 1;
                                        
                                        headerRow1.getCell(startCol).value = course;
                                        worksheet.mergeCells(headerRow1.number, startCol, headerRow1.number, startCol + 2);
                                        
                                        headerRow2.getCell(startCol).value = 'MALE';
                                        headerRow2.getCell(startCol + 1).value = 'FEMALE';
                                        headerRow2.getCell(startCol + 2).value = 'TOTAL';
                                    });
                    
                                    headerRow1.eachCell(cell => {
                                        cell.style = styles.header1;
                                    });
                                    headerRow2.eachCell(cell => {
                                        cell.style = styles.header2;
                                    });
                    
                                    // Running per-course Male/Female/Total sums for the bottom TOTAL row.
                                    const courseSums = college.courses.map(() => ({ male: 0, female: 0, total: 0 }));

                                    industryCategories.forEach(industry => {
                                        const rowValues = [industry];

                                        college.courses.forEach((course, i) => {
                                            const courseData = data.industry_analysis.find(row => row.Industry === industry);

                                            const male = courseData ? (courseData[course + ' - Male'] || 0) : 0;
                                            const female = courseData ? (courseData[course + ' - Female'] || 0) : 0;
                                            const total = courseData ? (courseData[course + ' - Total'] || 0) : 0;

                                            courseSums[i].male += male;
                                            courseSums[i].female += female;
                                            courseSums[i].total += total;

                                            rowValues.push(male, female, total);
                                        });

                                        const row = worksheet.addRow(rowValues);
                                        row.getCell(1).style = styles.industryLabel;
                                        college.courses.forEach((course, i) => {
                                            const base = 2 + i * 3;
                                            row.getCell(base).style = styles.maleCell;
                                            row.getCell(base + 1).style = styles.femaleCell;
                                            row.getCell(base + 2).style = styles.totalCell;
                                        });
                                    });

                                    const totalRowValues = ['TOTAL'];
                                    courseSums.forEach(sum => totalRowValues.push(sum.male, sum.female, sum.total));
                                    const totalRow = worksheet.addRow(totalRowValues);
                                    totalRow.getCell(1).style = { ...styles.industryLabel, font: { ...styles.industryLabel.font, size: 12 } };
                                    college.courses.forEach((course, i) => {
                                        const base = 2 + i * 3;
                                        totalRow.getCell(base).style = styles.maleCell;
                                        totalRow.getCell(base + 1).style = { ...styles.femaleCell, font: { bold: true, color: { argb: '000000' } } };
                                        totalRow.getCell(base + 2).style = styles.totalCell;
                                    });

                                    worksheet.columns = [
                                        { width: 50 },
                                        ...Array(college.courses.length * 3).fill().map(() => ({ width: 12 }))
                                    ];
                                }
                    
                                const buffer = await workbook.xlsx.writeBuffer();
                                saveAs(new Blob([buffer]), `industry_analysis_${new Date().toISOString().split('T')[0]}.xlsx`);
                                this.showNotification('Excel report exported successfully!', 'success');
                            }
                        } catch (error) {
                            console.error('Export error:', error);
                            this.showNotification('Failed to export report', 'error');
                        }
                    },

                    getCollegeAbbreviation(collegeName) {
                        return window.ReportHelpers.getCollegeAbbreviation(collegeName);
                    },
                    // Helper function to find college for a course
                    findCollegeForCourse(employmentSummary, courseName) {
                        // Find the college that contains this course
                        for (let i = 0; i < employmentSummary.length; i++) {
                            if (employmentSummary[i].is_header) {
                                // This is a college header, check if the next course belongs to it
                                const collegeName = employmentSummary[i].college;
                                // Look for the course in the next rows until we hit another header
                                for (let j = i + 1; j < employmentSummary.length; j++) {
                                    if (employmentSummary[j].is_header) {
                                        break; // Found another college header, stop searching
                                    }
                                    if (employmentSummary[j].course === courseName) {
                                        return collegeName;
                                    }
                                }
                            }
                        }
                        return 'Unknown College';
                    },
                    // Helper function to apply styling to Excel worksheets - match image format
                    applyExcelStyling(worksheet) {
                        const headerRange = XLSX.utils.decode_range(worksheet['!ref']);
                        
                        // Apply styling to headers (first row) - colored headers
                        for (let col = headerRange.s.c; col <= headerRange.e.c; col++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: 0, c: col });
                            if (!worksheet[cellAddress]) {
                                worksheet[cellAddress] = { v: '', t: 's' };
                            }
                            
                            // Set different colors for different columns
                            let headerColor = "4472C4"; // Default blue
                            let textColor = "FFFFFF";   // White text
                            
                            // Column-specific colors
                            if (col === 0) { // Logo column
                                headerColor = "5B9BD5"; // Lighter blue
                            } else if (col === 1) { // Company Name column
                                headerColor = "4472C4"; // Blue
                            } else if (col === 2) { // Location column
                                headerColor = "70AD47"; // Green
                            } else if (col === 3) { // Contact Email column
                                headerColor = "FFC000"; // Orange
                            } else if (col === 4) { // Industry Type column
                                headerColor = "5B9BD5"; // Light Blue
                            } else if (col === 5) { // Nature of Business column
                                headerColor = "A5A5A5"; // Gray
                            } else if (col === 6) { // Accreditation Status column
                                headerColor = "ED7D31"; // Dark Orange
                            } else if (col === 7) { // Status column
                                headerColor = "7030A0"; // Purple
                            } else if (col === 8) { // Actions column
                                headerColor = "FF0000"; // Red
                            }
                            
                            worksheet[cellAddress].s = {
                                fill: { fgColor: { rgb: headerColor } },
                                font: { bold: true, color: { rgb: textColor } },
                                alignment: { horizontal: "center", vertical: "center" }
                            };
                        }

                        // Apply styling to data rows
                        for (let row = 1; row <= headerRange.e.r; row++) {
                            for (let col = 0; col <= headerRange.e.c; col++) {
                                const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                                if (worksheet[cellAddress]) {
                                    // Status column formatting
                                    if (col === 7 && worksheet[cellAddress].v) {
                                        const status = worksheet[cellAddress].v.toString().toLowerCase();
                                        let bgColor = "FFFFFF"; // Default white
                                        let textColor = "000000"; // Default black text

                                        if (status === 'active') {
                                            bgColor = "C6EFCE"; // Light green
                                            textColor = "006100"; // Dark green text
                                        } else if (status === 'pending') {
                                            bgColor = "FFEB9C"; // Light yellow
                                            textColor = "9C6500"; // Dark yellow text
                                        } else if (status === 'rejected') {
                                            bgColor = "FFC7CE"; // Light red
                                            textColor = "9C0006"; // Dark red text
                                        }
                                        
                                        worksheet[cellAddress].s = {
                                            fill: { fgColor: { rgb: bgColor } },
                                            font: { bold: true, color: { rgb: textColor } },
                                            alignment: { horizontal: "center", vertical: "center" }
                                        };
                                    } else {
                                        // Alternate row coloring
                                        const bgColor = row % 2 === 0 ? "FFFFFF" : "F2F2F2"; // White or light gray
                                        worksheet[cellAddress].s = {
                                            fill: { fgColor: { rgb: bgColor } },
                                            font: { bold: false },
                                            alignment: { horizontal: "center", vertical: "center" }
                                        };
                                    }
                                }
                            }
                        }
                        
                        return worksheet;
                    },
                    // Enhanced helper function to apply styling for industry analysis format with borders
                    applyIndustryAnalysisStyling(worksheet, courseCount) {
                        const headerRange = XLSX.utils.decode_range(worksheet['!ref']);
                        
                        // Apply enhanced styling to first header row (course names spanning columns) with borders
                        for (let col = 0; col < headerRange.e.c; col++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: 0, c: col });
                            if (!worksheet[cellAddress]) {
                                worksheet[cellAddress] = { v: '', t: 's' };
                            }
                            
                            if (col === 0) {
                                // First column header (Nature of Work/Industry)
                                worksheet[cellAddress].s = {
                                    fill: { fgColor: { rgb: "8B4513" } }, // Dark reddish-brown background
                                    font: { 
                                        color: { rgb: "FFFFFF" }, 
                                        bold: true,
                                        sz: 12 // Font size
                                    }, // White bold text
                                    alignment: { horizontal: "center", vertical: "center" },
                                    border: {
                                        top: { style: "thin", color: { rgb: "000000" } },
                                        bottom: { style: "thin", color: { rgb: "000000" } },
                                        left: { style: "thin", color: { rgb: "000000" } },
                                        right: { style: "thin", color: { rgb: "000000" } }
                                    }
                                };
                            } else {
                                // Course headers spanning 3 columns each
                                const courseIndex = Math.floor((col - 1) / 3);
                                if ((col - 1) % 3 === 0) {
                                    // First column of each course group
                                    worksheet[cellAddress].s = {
                                        fill: { fgColor: { rgb: "8B4513" } }, // Dark reddish-brown background
                                        font: { 
                                            color: { rgb: "FFFFFF" }, 
                                            bold: true,
                                            sz: 12 // Font size
                                        }, // White bold text
                                        alignment: { horizontal: "center", vertical: "center" },
                                        border: {
                                            top: { style: "thin", color: { rgb: "000000" } },
                                            bottom: { style: "thin", color: { rgb: "000000" } },
                                            left: { style: "thin", color: { rgb: "000000" } },
                                            right: { style: "thin", color: { rgb: "000000" } }
                                        }
                                    };
                                } else {
                                    // Empty cells in course header span
                                    worksheet[cellAddress].s = {
                                        fill: { fgColor: { rgb: "8B4513" } }, // Dark reddish-brown background
                                        font: { 
                                            color: { rgb: "FFFFFF" }, 
                                            bold: true,
                                            sz: 12 // Font size
                                        }, // White bold text
                                        alignment: { horizontal: "center", vertical: "center" },
                                        border: {
                                            top: { style: "thin", color: { rgb: "000000" } },
                                            bottom: { style: "thin", color: { rgb: "000000" } },
                                            left: { style: "thin", color: { rgb: "000000" } },
                                            right: { style: "thin", color: { rgb: "000000" } }
                                        }
                                    };
                                }
                            }
                        }

                        // Apply enhanced styling to second header row (MALE, FEMALE, TOTAL) with borders
                        for (let col = 0; col < headerRange.e.c; col++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: 1, c: col });
                            if (!worksheet[cellAddress]) {
                                worksheet[cellAddress] = { v: '', t: 's' };
                            }
                            
                            if (col === 0) {
                                // First column header (Nature of Work/Industry)
                                worksheet[cellAddress].s = {
                                    fill: { fgColor: { rgb: "8B4513" } }, // Dark reddish-brown background
                                    font: { 
                                        color: { rgb: "FFFFFF" }, 
                                        bold: true,
                                        sz: 11 // Font size
                                    }, // White bold text
                                    alignment: { horizontal: "center", vertical: "center" },
                                    border: {
                                        top: { style: "thin", color: { rgb: "000000" } },
                                        bottom: { style: "thin", color: { rgb: "000000" } },
                                        left: { style: "thin", color: { rgb: "000000" } },
                                        right: { style: "thin", color: { rgb: "000000" } }
                                    }
                                };
                            } else {
                                // MALE, FEMALE, TOTAL headers
                                worksheet[cellAddress].s = {
                                    font: { 
                                        bold: true,
                                        sz: 11 // Font size
                                    },
                                    alignment: { horizontal: "center", vertical: "center" },
                                    border: {
                                        top: { style: "thin", color: { rgb: "000000" } },
                                        bottom: { style: "thin", color: { rgb: "000000" } },
                                        left: { style: "thin", color: { rgb: "000000" } },
                                        right: { style: "thin", color: { rgb: "000000" } }
                                    }
                                };
                            }
                        }

                        // Apply enhanced styling to data rows with borders
                        for (let row = 2; row <= headerRange.e.r; row++) {
                            for (let col = 0; col < headerRange.e.c; col++) {
                                const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                                if (worksheet[cellAddress]) {
                                    if (col === 0) {
                                        // First column (Industry categories)
                                        worksheet[cellAddress].s = {
                                            font: { 
                                                bold: false,
                                                sz: 10 // Font size
                                            },
                                            alignment: { horizontal: "left", vertical: "center" },
                                            border: {
                                                top: { style: "thin", color: { rgb: "000000" } },
                                                bottom: { style: "thin", color: { rgb: "000000" } },
                                                left: { style: "thin", color: { rgb: "000000" } },
                                                right: { style: "thin", color: { rgb: "000000" } }
                                            }
                                        };
                                    } else {
                                        // Data cells - alternate between light blue and light pink
                                        const courseIndex = Math.floor((col - 1) / 3);
                                        const subColIndex = (col - 1) % 3; // 0=MALE, 1=FEMALE, 2=TOTAL
                                        
                                        if (subColIndex === 0 || subColIndex === 1) {
                                            // MALE and FEMALE columns - light blue background
                                            worksheet[cellAddress].s = {
                                                fill: { fgColor: { rgb: "E6F3FF" } }, // Light blue background
                                                font: { 
                                                    bold: false,
                                                    sz: 10 // Font size
                                                },
                                                alignment: { horizontal: "center", vertical: "center" },
                                                border: {
                                                    top: { style: "thin", color: { rgb: "000000" } },
                                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                                    left: { style: "thin", color: { rgb: "000000" } },
                                                    right: { style: "thin", color: { rgb: "000000" } }
                                                }
                                            };
                                        } else {
                                            // TOTAL columns - light pink background
                                            worksheet[cellAddress].s = {
                                                fill: { fgColor: { rgb: "FFE6E6" } }, // Light pink background
                                                font: { 
                                                    bold: false,
                                                    sz: 10 // Font size
                                                },
                                                alignment: { horizontal: "center", vertical: "center" },
                                                border: {
                                                    top: { style: "thin", color: { rgb: "000000" } },
                                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                                    left: { style: "thin", color: { rgb: "000000" } },
                                                    right: { style: "thin", color: { rgb: "000000" } }
                                                }
                                            };
                                        }
                                    }
                                }
                            }
                        }
                        
                        return worksheet;
                    },
                    // Enhanced helper function to apply styling for graduates per program with borders
                    applyGraduatesPerProgramStyling(worksheet) {
                        const headerRange = XLSX.utils.decode_range(worksheet['!ref']);
                        
                        // Apply enhanced styling to header row (first row) with borders
                        for (let col = 0; col < headerRange.e.c; col++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: 0, c: col });
                            if (!worksheet[cellAddress]) {
                                worksheet[cellAddress] = { v: '', t: 's' };
                            }
                            worksheet[cellAddress].s = {
                                fill: { 
                                    patternType: "solid",
                                    fgColor: { rgb: "4472C4" } // Blue background
                                },
                                font: { 
                                    bold: true,
                                    color: { rgb: "FFFFFF" }, // White text
                                    sz: 11 // Font size
                                },
                                alignment: { horizontal: "center", vertical: "center" },
                                border: {
                                    top: { style: "thin", color: { rgb: "000000" } },
                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                    left: { style: "thin", color: { rgb: "000000" } },
                                    right: { style: "thin", color: { rgb: "000000" } }
                                }
                            };
                        }

                        // Apply enhanced styling to yellow filter row (second row) with borders
                        for (let col = 0; col < headerRange.e.c; col++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: 1, c: col });
                            if (!worksheet[cellAddress]) {
                                worksheet[cellAddress] = { v: '', t: 's' };
                            }
                            worksheet[cellAddress].s = {
                                fill: { fgColor: { rgb: "FFFF00" } }, // Yellow background
                                font: { 
                                    bold: true,
                                    sz: 11 // Font size
                                },
                                alignment: { horizontal: "center", vertical: "center" },
                                border: {
                                    top: { style: "thin", color: { rgb: "000000" } },
                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                    left: { style: "thin", color: { rgb: "000000" } },
                                    right: { style: "thin", color: { rgb: "000000" } }
                                }
                            };
                        }

                        // Apply enhanced styling to data rows with borders
                        for (let row = 2; row <= headerRange.e.r; row++) {
                            for (let col = 0; col < headerRange.e.c; col++) {
                                const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                                if (worksheet[cellAddress]) {
                                    if (col === 1) { // Program Name column - light blue background
                                        worksheet[cellAddress].s = {
                                            fill: { fgColor: { rgb: "E6F3FF" } }, // Light blue background
                                            font: { 
                                                bold: false,
                                                sz: 10 // Font size
                                            },
                                            alignment: { horizontal: "left", vertical: "center" },
                                            border: {
                                                top: { style: "thin", color: { rgb: "000000" } },
                                                bottom: { style: "thin", color: { rgb: "000000" } },
                                                left: { style: "thin", color: { rgb: "000000" } },
                                                right: { style: "thin", color: { rgb: "000000" } }
                                            }
                                        };
                                    } else {
                                        // Apply alternating row colors for other columns
                                        if (row % 2 === 0) { // Even rows (yellow filter row is 1)
                                            worksheet[cellAddress].s = {
                                                fill: { fgColor: { rgb: "F0F0F0" } }, // Light gray background
                                                font: { 
                                                    bold: false,
                                                    sz: 10 // Font size
                                                },
                                                alignment: { horizontal: "center", vertical: "center" },
                                                border: {
                                                    top: { style: "thin", color: { rgb: "000000" } },
                                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                                    left: { style: "thin", color: { rgb: "000000" } },
                                                    right: { style: "thin", color: { rgb: "000000" } }
                                                }
                                            };
                                        } else { // Odd rows (data rows)
                                            worksheet[cellAddress].s = {
                                                font: { 
                                                    bold: false,
                                                    sz: 10 // Font size
                                                },
                                                alignment: { horizontal: "center", vertical: "center" },
                                                border: {
                                                    top: { style: "thin", color: { rgb: "000000" } },
                                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                                    left: { style: "thin", color: { rgb: "000000" } },
                                                    right: { style: "thin", color: { rgb: "000000" } }
                                                }
                                            };
                                        }
                                    }
                                }
                            }
                        }
                        
                        return worksheet;
                    },
                    // Enhanced helper function to apply styling for company details with borders
                    applyCompanyDetailsStyling(worksheet) {
                        const headerRange = XLSX.utils.decode_range(worksheet['!ref']);
                        
                        // Apply enhanced styling to header row (first row) with borders
                        for (let col = 0; col < headerRange.e.c; col++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: 0, c: col });
                            if (!worksheet[cellAddress]) {
                                worksheet[cellAddress] = { v: '', t: 's' };
                            }
                            worksheet[cellAddress].s = {
                                fill: { 
                                    patternType: "solid",
                                    fgColor: { rgb: "4472C4" } // Blue background
                                },
                                font: { 
                                    bold: true,
                                    color: { rgb: "FFFFFF" }, // White text
                                    sz: 11 // Font size
                                },
                                alignment: { horizontal: "center", vertical: "center" },
                                border: {
                                    top: { style: "thin", color: { rgb: "000000" } },
                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                    left: { style: "thin", color: { rgb: "000000" } },
                                    right: { style: "thin", color: { rgb: "000000" } }
                                }
                            };
                        }

                        // Apply enhanced styling to yellow highlight row (second row) with borders
                        for (let col = 0; col < headerRange.e.c; col++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: 1, c: col });
                            if (!worksheet[cellAddress]) {
                                worksheet[cellAddress] = { v: '', t: 's' };
                            }
                            worksheet[cellAddress].s = {
                                fill: { fgColor: { rgb: "FFFF00" } }, // Yellow background
                                font: { 
                                    bold: true,
                                    sz: 11 // Font size
                                },
                                alignment: { horizontal: "center", vertical: "center" },
                                border: {
                                    top: { style: "thin", color: { rgb: "000000" } },
                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                    left: { style: "thin", color: { rgb: "000000" } },
                                    right: { style: "thin", color: { rgb: "000000" } }
                                }
                            };
                        }

                        // Apply enhanced styling to data rows with borders
                        for (let row = 2; row <= headerRange.e.r; row++) {
                            for (let col = 0; col < headerRange.e.c; col++) {
                                const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                                if (worksheet[cellAddress]) {
                                    if (col === 3) { // Employed-Aligned column
                                        const cellValue = worksheet[cellAddress].v;
                                        if (cellValue === 'Matched') {
                                            worksheet[cellAddress].s = {
                                                fill: { fgColor: { rgb: "FF0000" } }, // Red background for Matched
                                                font: { 
                                                    bold: true, 
                                                    color: { rgb: "FFFFFF" },
                                                    sz: 10 // Font size
                                                },
                                                alignment: { horizontal: "center", vertical: "center" },
                                                border: {
                                                    top: { style: "thin", color: { rgb: "000000" } },
                                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                                    left: { style: "thin", color: { rgb: "000000" } },
                                                    right: { style: "thin", color: { rgb: "000000" } }
                                                }
                                            };
                                        } else if (cellValue === 'Mismatched') {
                                            worksheet[cellAddress].s = {
                                                fill: { fgColor: { rgb: "00FF00" } }, // Green background for Mismatched
                                                font: { 
                                                    bold: true, 
                                                    color: { rgb: "000000" },
                                                    sz: 10 // Font size
                                                },
                                                alignment: { horizontal: "center", vertical: "center" },
                                                border: {
                                                    top: { style: "thin", color: { rgb: "000000" } },
                                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                                    left: { style: "thin", color: { rgb: "000000" } },
                                                    right: { style: "thin", color: { rgb: "000000" } }
                                                }
                                            };
                                        } else {
                                            worksheet[cellAddress].s = {
                                                font: { 
                                                    bold: false,
                                                    sz: 10 // Font size
                                                },
                                                alignment: { horizontal: "center", vertical: "center" },
                                                border: {
                                                    top: { style: "thin", color: { rgb: "000000" } },
                                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                                    left: { style: "thin", color: { rgb: "000000" } },
                                                    right: { style: "thin", color: { rgb: "000000" } }
                                                }
                                            };
                                        }
                                    } else {
                                        // Light blue-green background for data rows
                                        worksheet[cellAddress].s = {
                                            fill: { fgColor: { rgb: "E6F3FF" } }, // Light blue-green background
                                            font: { 
                                                bold: false,
                                                sz: 10 // Font size
                                            },
                                            alignment: { horizontal: "left", vertical: "center" },
                                            border: {
                                                top: { style: "thin", color: { rgb: "000000" } },
                                                bottom: { style: "thin", color: { rgb: "000000" } },
                                                left: { style: "thin", color: { rgb: "000000" } },
                                                right: { style: "thin", color: { rgb: "000000" } }
                                            }
                                        };
                                    }
                                }
                            }
                        }
                        
                        return worksheet;
                    },
                    // Enhanced helper function to apply styling for personal details with borders
                    applyPersonalDetailsStyling(worksheet) {
                        const headerRange = XLSX.utils.decode_range(worksheet['!ref']);
                        
                        // Apply enhanced styling to header row (first row) with borders
                        for (let col = 0; col < headerRange.e.c; col++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: 0, c: col });
                            if (!worksheet[cellAddress]) {
                                worksheet[cellAddress] = { v: '', t: 's' };
                            }
                            worksheet[cellAddress].s = {
                                fill: { fgColor: { rgb: "8B0000" } }, // Dark red background
                                font: { 
                                    bold: true, 
                                    color: { rgb: "FFFFFF" },
                                    sz: 11 // Font size
                                }, // White bold text
                                alignment: { horizontal: "center", vertical: "center" },
                                border: {
                                    top: { style: "thin", color: { rgb: "000000" } },
                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                    left: { style: "thin", color: { rgb: "000000" } },
                                    right: { style: "thin", color: { rgb: "000000" } }
                                }
                            };
                        }

                        // Apply enhanced styling to yellow filter row (second row) with borders
                        for (let col = 0; col < headerRange.e.c; col++) {
                            const cellAddress = XLSX.utils.encode_cell({ r: 1, c: col });
                            if (!worksheet[cellAddress]) {
                                worksheet[cellAddress] = { v: '', t: 's' };
                            }
                            worksheet[cellAddress].s = {
                                fill: { fgColor: { rgb: "FFFF00" } }, // Yellow background
                                font: { 
                                    bold: true,
                                    sz: 11 // Font size
                                },
                                alignment: { horizontal: "center", vertical: "center" },
                                border: {
                                    top: { style: "thin", color: { rgb: "000000" } },
                                    bottom: { style: "thin", color: { rgb: "000000" } },
                                    left: { style: "thin", color: { rgb: "000000" } },
                                    right: { style: "thin", color: { rgb: "000000" } }
                                }
                            };
                        }

                        // Apply enhanced styling to data rows with borders
                        for (let row = 2; row <= headerRange.e.r; row++) {
                            for (let col = 0; col < headerRange.e.c; col++) {
                                const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                                if (worksheet[cellAddress]) {
                                    if (col === 0) { // First column - light green background
                                        worksheet[cellAddress].s = {
                                            fill: { fgColor: { rgb: "E6FFE6" } }, // Light green background
                                            font: { 
                                                bold: false,
                                                sz: 10 // Font size
                                            },
                                            alignment: { horizontal: "center", vertical: "center" },
                                            border: {
                                                top: { style: "thin", color: { rgb: "000000" } },
                                                bottom: { style: "thin", color: { rgb: "000000" } },
                                                left: { style: "thin", color: { rgb: "000000" } },
                                                right: { style: "thin", color: { rgb: "000000" } }
                                            }
                                        };
                                    } else { // Other columns - light red/salmon background
                                        worksheet[cellAddress].s = {
                                            fill: { fgColor: { rgb: "FFE6E6" } }, // Light red/salmon background
                                            font: { 
                                                bold: false,
                                                sz: 10 // Font size
                                            },
                                            alignment: { horizontal: "left", vertical: "center" },
                                            border: {
                                                top: { style: "thin", color: { rgb: "000000" } },
                                                bottom: { style: "thin", color: { rgb: "000000" } },
                                                left: { style: "thin", color: { rgb: "000000" } },
                                                right: { style: "thin", color: { rgb: "000000" } }
                                            }
                                        };
                                    }
                                }
                            }
                        }
                        
                        return worksheet;
                    }
                }
            }).mount('#app');