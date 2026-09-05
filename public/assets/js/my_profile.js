const { createApp } = Vue;
        createApp({
            data() {
                return {
                    loading: true, // Add loading state
                    showDeleteAccountConfirm: false,
                    showFinalDeleteAccountConfirm: false,
                    deleteAccountPassword: '',
                    deletingAccount: false,
                    darkMode: localStorage.getItem('darkMode') === 'true' || 
                     (localStorage.getItem('darkMode') === null && 
                      window.matchMedia('(prefers-color-scheme: dark)').matches),
                    mobileMenuOpen: false,
                    profileDropdownOpen: false,
                    mobileProfileDropdownOpen: false,
                    showTutorialButton: true, // Start as false, will be updated after check
                    showWelcomeModal: false, // Start as false
                    currentWelcomeSlide: 0,
                    welcomeSlides: [
                        { title: "Welcome", content: "intro" },
                        { title: "Navigation", content: "navigation" },
                        { title: "Job Search", content: "job_search" },
                        { title: "Profile", content: "profile" }
                    ],
                    unreadNotifications: 0,
                    showSuccessStoryModal: false,
                    showDeleteSuccessStoryModal: false,
                    editingSuccessStoryIndex: null,
                    editSuccessStoryData: {
                        title: '',
                        content: ''
                    },
                    successStories: [],
                    deletingSuccessStoryIndex: null,
                    notifications: [],
                    profile: {
                        first_name: '',
                        middle_name: '',
                        last_name: '',
                        email: '',
                        secondary_email: '',
                        contact: '',
                        city: '',
                        province: '',
                        birthdate: '',
                        gender: '',
                        civil_status: '',
                        college: '',
                        course: '',
                        year_graduated: '',
                        education: [],
                        skills: [],
                        certifications: [],
                        experiences: [],
                        resume: null,
                        title: '',
                        image: '',
                        verification_document: '',
                        status_prior_graduation: '',
                        status_employement_graduation: ''
                    },
                    editProfileData: {
                        first_name: '',
                        middle_name: '',
                        last_name: '',
                        email: '',
                        secondary_email: '',
                        contact: '',
                        city: '',
                        province: '',
                        birthdate: '',
                        gender: '',
                        civil_status: '',
                        year_graduated: '',
                        campus_id: '',
                        college: '',
                        course: '',
                        degree: '',
                        status_prior_graduation: '',
                        status_employement_graduation: ''
                    },
                    editSkillsData: [],
                    newSkill: {
                        name: ''
                    },
                    skillSuggestions: [],
                    showSkillSuggestions: false,
                    skillInputTimeout: null,
                    showDeleteDocumentModal: false,
                    documentToDelete: null,
                    
                    // API credentials for EMSI skills service
                    skillsApi: {
                        clientId: "b6efq6u8v44x705u",
                        clientSecret: "ogCw9Yv4",
                        scope: "emsi_open",
                        token: null,
                        tokenExpiry: 0
                    },
                    editEducationData: {
                        degree: '',
                        school: '',
                        start_date: '',
                        end_date: '',
                        current: false
                    },
                    editExperienceData: {
                        title: '',
                        company: '',
                        company_address: '',
                        sector: '',
                        location: '',
                        salary: '',
                        employment_type: '',
                        industry: '',
                        start_date: '',
                        end_date: '',
                        current: false,
                        description: '',
                        location_of_work: '',
                        employment_status: '',
                        employment_sector: '' // NEW FIELD
                    },
                    editingEducationIndex: null,
                    editingExperienceIndex: null,
                    showEditModal: false,
                    showEducationModal: false,
                    showSkillsModal: false,
                    showExperienceModal: false,
                    showResumeModal: false,
                    showPhotoModal: false,
                    newPhotoPreview: null,
                    resumePreview: null,
                    editResumeFile: null,
                    showDocumentModal: false, // Added for verification document modal
                    documentPreview: null,
                    documentPreviewType: '',
                    documentFile: null,
                    provinces: [],
                    cities: [],
                    campuses: [],
                    // Campus -> College -> Course/Program data for all LSPU campuses.
                    // `colleges`/`collegeCourses` below are derived from this per the selected
                    // campus (see updateCollegeOptions()) rather than a single fixed list.
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
                    colleges: [],
                    collegeCourses: {},
                    notifications: [],
                    notificationId: 0,
                    schools: [],
                    degrees: [],
                    degreeInput: '',
                    schoolInput: '',
                    filteredDegreeSuggestions: [],
                    filteredSchoolSuggestions: [],
                    showDegreeSuggestions: false,
                    showSchoolSuggestions: false,
                    showDeleteEducationModal: false,
                    educationToDeleteIndex: null,
                    educationToDeleteId: null,
                    showDeleteExperienceModal: false,
                    experienceToDeleteIndex: null,
                    experienceToDeleteId: null,
                    showDeleteSkillModal: false,
                    skillToDeleteIndex: null,
                    skillToDeleteId: null,

                    // Certifications (own section, independent of Skills)
                    showCertificationModal: false,
                    showDeleteCertificationModal: false,
                    editingCertificationIndex: null,
                    editCertificationData: { id: null, name: '', issuer: '', issue_date: '', certificate_file: null },
                    certificationToDeleteIndex: null,
                    certificationToDeleteId: null,
                    certificationFilePreview: null,

                    resumeData: {
                        resume_id: null,
                        file_name: '',
                        uploaded_at: ''
                    },
                    showDeleteResumeModal: false,
                    profilePicData: {
                        file_name: '',
                    },
                    showDeleteProfilePicModal: false,
                    newPhotoFile: null,
                    showLogoutModal: false,
                    titleSuggestions: [],
                    showTitleSuggestions: false,
                    titleInputTimeout: null,

                    // Resume generator
                    showResumeGeneratorModal: false,
                    selectedResumeTemplate: 'modern',
                    generatingResume: false,
                    resumeSummary: '',
                    generatingSummary: false,
                    resumeTemplates: [
                        { id: 'ats', name: 'ATS Classic', desc: 'Plain text, parser-friendly', color: '#374151' },
                        { id: 'modern', name: 'Modern Blue', desc: 'Clean layout with blue accents', color: '#2563eb' },
                        { id: 'minimalist', name: 'Minimalist Mono', desc: 'Black & white, minimal lines', color: '#111827' },
                        { id: 'navy', name: 'Professional Navy', desc: 'Traditional serif, navy accents', color: '#1e3a8a' },
                        { id: 'executive', name: 'Executive Gray', desc: 'Corporate dark header banner', color: '#4b5563' },
                        { id: 'sidebar', name: 'Sidebar Split', desc: 'Dark sidebar with contact & skills', color: '#0f172a' },
                        { id: 'gradient', name: 'Creative Gradient', desc: 'Colorful gradient header', color: '#7c3aed' },
                        { id: 'timeline', name: 'Timeline', desc: 'Vertical timeline of history', color: '#059669' },
                        { id: 'compact', name: 'Compact Dense', desc: 'Tight spacing, fits more content', color: '#6b7280' },
                        { id: 'bold', name: 'Bold Header', desc: 'Large full-width name banner', color: '#dc2626' },
                        { id: 'academic', name: 'Academic Formal', desc: 'Education-first, formal serif', color: '#78350f' },
                        { id: 'tech', name: 'Tech Developer', desc: 'Dark sidebar, code-style skill tags', color: '#0d9488' },
                    ],

                    // API credentials for EMSI services
                    emsiApi: {
                        clientId: "b6efq6u8v44x705u",
                        clientSecret: "ogCw9Yv4",
                        scope: "emsi_open",
                        token: null,
                        tokenExpiry: 0
                    }
                };
            },
            watch: {
                darkMode(val) {
                    localStorage.setItem('darkMode', val.toString());
                    this.applyDarkMode();
                },
                degreeInput(newVal) {
                    this.editEducationData.degree = newVal;
                },
                schoolInput(newVal) {
                    this.editEducationData.school = newVal;
                },
                showEducationModal(val) {
                    if (val) {
                        // Wait for the modal to be rendered in DOM
                        this.$nextTick(() => {
                            this.initUniversityAutocomplete();
                        });
                    }
                },
                showEducationModal(val) {
                    if (val) {
                        // Wait for the modal to be rendered in DOM
                        this.$nextTick(() => {
                            this.initAutocompletes();
                        });
                    }
                }
            },
            computed: {
                resumeFullName() {
                    return [this.profile.first_name, this.profile.middle_name, this.profile.last_name]
                        .filter(Boolean).join(' ');
                },
                resumeInitials() {
                    return [this.profile.first_name, this.profile.last_name]
                        .filter(Boolean).map(n => n.charAt(0).toUpperCase()).join('');
                },
                resumeEducation() {
                    return (this.profile.education || []).filter(e => e.school);
                },
                resumeExperience() {
                    return (this.profile.experiences || []).filter(e => e.company);
                },
                resumeSkills() {
                    return (this.profile.skills || []).filter(s => s.name);
                },
                resumeCertifications() {
                    return (this.profile.certifications || []).filter(c => c.name);
                },
                resumeLocation() {
                    return [this.profile.city, this.profile.province].filter(Boolean).join(', ');
                },
                selectedResumeTemplateMeta() {
                    return this.resumeTemplates.find(t => t.id === this.selectedResumeTemplate) || this.resumeTemplates[0];
                },
            },
            methods: {
                handleClickOutsideProfile(event) {
                    if (this.profileDropdownOpen && !event.target.closest('.profile-dropdown-wrapper')) {
                        this.profileDropdownOpen = false;
                    }
                },
                async deleteAccount() {
                    this.deletingAccount = true;
                    try {
                        const response = await fetch('/my_profile?action=deleteAccount', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ password: this.deleteAccountPassword }),
                        });
                        const data = await response.json();
                        if (data.success) {
                            window.location.href = 'login';
                        } else {
                            this.showNotification(data.message || 'Failed to delete account.', 'error');
                            this.deletingAccount = false;
                            this.showFinalDeleteAccountConfirm = false;
                        }
                    } catch (error) {
                        this.showNotification('Failed to delete account.', 'error');
                        this.deletingAccount = false;
                        this.showFinalDeleteAccountConfirm = false;
                    }
                },
                handleModalEscapeKey(e) {
                    if (e.key !== 'Escape') return;
                    const openModalFlags = [
                        'showWelcomeModal', 'showSuccessStoryModal', 'showDeleteSuccessStoryModal',
                        'showDeleteDocumentModal', 'showEditModal', 'showEducationModal', 'showSkillsModal',
                        'showExperienceModal', 'showResumeModal', 'showPhotoModal', 'showDocumentModal',
                        'showDeleteEducationModal', 'showDeleteExperienceModal', 'showDeleteSkillModal',
                        'showCertificationModal', 'showDeleteCertificationModal', 'showDeleteResumeModal',
                        'showDeleteProfilePicModal', 'showLogoutModal', 'showResumeGeneratorModal',
                        'showFinalDeleteAccountConfirm',
                    ];
                    for (const flag of openModalFlags) {
                        if (this[flag]) {
                            this[flag] = false;
                            return;
                        }
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
                fileIconClass(filename) {
                    return window.FileHelpers.fileIconClass(filename);
                },

                // ---- Resume Generator ----
                openResumeGenerator() {
                    this.showResumeGeneratorModal = true;
                },
                closeResumeGenerator() {
                    this.showResumeGeneratorModal = false;
                },
                selectResumeTemplate(id) {
                    this.selectedResumeTemplate = id;
                },
                async generateSummaryWithAI() {
                    if (this.generatingSummary) return;
                    this.generatingSummary = true;
                    try {
                        const response = await fetch('my_profile?action=generateSummary', { method: 'POST' });
                        const data = await response.json();
                        if (data.success) {
                            this.resumeSummary = data.summary;
                        } else {
                            this.showNotification(data.message || 'Could not generate a summary.', 'error');
                        }
                    } catch (error) {
                        console.error('Summary generation failed:', error);
                        this.showNotification('Could not generate a summary. Please try again.', 'error');
                    } finally {
                        this.generatingSummary = false;
                    }
                },
                resumeFileBaseName() {
                    const name = this.resumeFullName.trim().replace(/\s+/g, '_') || 'Resume';
                    const templateName = this.selectedResumeTemplateMeta.name.replace(/\s+/g, '_');
                    return `${name}_Resume_${templateName}`;
                },
                async generateResume() {
                    if (this.generatingResume) return;
                    this.generatingResume = true;
                    try {
                        if (this.selectedResumeTemplate === 'ats') {
                            await this.generateAtsResume();
                        } else {
                            await this.generateVisualResume();
                        }
                        this.showNotification('Your resume PDF has been downloaded.', 'success');
                    } catch (error) {
                        console.error('Resume generation failed:', error);
                        this.showNotification('Could not generate the resume. Please try again.', 'error');
                    } finally {
                        this.generatingResume = false;
                    }
                },
                async generateAtsResume() {
                    await LibLoader.ensureJsPDF();
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF({ unit: 'pt', format: 'a4' });
                    const marginX = 56;
                    const pageWidth = doc.internal.pageSize.getWidth();
                    const pageHeight = doc.internal.pageSize.getHeight();
                    const maxWidth = pageWidth - marginX * 2;
                    let y = 56;

                    const ensureSpace = (needed) => {
                        if (y + needed > pageHeight - 48) {
                            doc.addPage();
                            y = 56;
                        }
                    };
                    const writeLine = (text, size, style, gap, align) => {
                        doc.setFont('times', style || 'normal');
                        doc.setFontSize(size || 10);
                        const lines = doc.splitTextToSize(text, maxWidth);
                        lines.forEach(line => {
                            ensureSpace(size ? size * 1.4 : 14);
                            if (align === 'center') {
                                doc.text(line, pageWidth / 2, y, { align: 'center' });
                            } else if (align === 'justify') {
                                doc.text(line, marginX, y, { maxWidth, align: 'justify' });
                            } else {
                                doc.text(line, marginX, y);
                            }
                            y += (size || 10) * 1.35;
                        });
                        y += gap || 0;
                    };
                    const writeBullet = (text, size) => {
                        doc.setFont('times', 'normal');
                        doc.setFontSize(size || 10);
                        const lines = doc.splitTextToSize(text, maxWidth - 14);
                        lines.forEach((line, idx) => {
                            ensureSpace((size || 10) * 1.4);
                            doc.text(idx === 0 ? '•' : '', marginX, y);
                            doc.text(line, marginX + 14, y);
                            y += (size || 10) * 1.35;
                        });
                    };
                    const writeHeading = (text) => {
                        ensureSpace(30);
                        y += 10;
                        writeLine(text, 12, 'bold', 2);
                        doc.setDrawColor(0, 0, 0);
                        doc.line(marginX, y - 8, pageWidth - marginX, y - 8);
                    };

                    writeLine(this.resumeFullName || 'Your Name', 22, 'bold', 4, 'center');
                    const contactParts = [this.profile.email, this.profile.contact, this.resumeLocation].filter(Boolean);
                    if (contactParts.length) writeLine(contactParts.join('  |  '), 10, 'normal', 10, 'center');

                    if (this.resumeSummary) {
                        writeHeading('Professional Summary');
                        writeLine(this.resumeSummary, 10, 'normal', 6, 'justify');
                    }

                    if (this.resumeSkills.length) {
                        writeHeading('Skills');
                        writeLine(this.resumeSkills.map(s => s.name).join(', '), 10, 'normal', 6);
                    }

                    if (this.resumeCertifications.length) {
                        writeHeading('Certifications');
                        writeLine(this.resumeCertifications.map(c => c.name + (c.issuer ? ' (' + c.issuer + ')' : '')).join(', '), 10, 'normal', 6);
                    }

                    if (this.resumeEducation.length) {
                        writeHeading('Education');
                        this.resumeEducation.forEach(e => {
                            const range = `${e.start_date || ''} - ${e.current ? 'Present' : (e.end_date || '')}`;
                            writeLine(`${e.school}${e.degree ? ' - ' + e.degree : ''}`, 11, 'bold', 0);
                            writeLine(range, 9, 'normal', 8);
                        });
                    }

                    if (this.resumeExperience.length) {
                        writeHeading('Professional Experience');
                        this.resumeExperience.forEach(e => {
                            const range = `${e.start_date || ''} - ${e.current ? 'Present' : (e.end_date || '')}`;
                            writeLine(`${e.company}`, 11, 'bold', 0);
                            writeLine(`${e.title || ''}    ${range}`, 9, 'italic', 4);
                            if (e.description) {
                                e.description.split('\n').map(l => l.trim()).filter(Boolean).forEach(line => writeBullet(line, 10));
                                y += 8;
                            } else {
                                y += 6;
                            }
                        });
                    }

                    doc.save(this.resumeFileBaseName() + '.pdf');
                },
                async generateVisualResume() {
                    const el = this.$refs.resumeCanvasTarget;
                    if (!el) throw new Error('Resume preview not found');

                    // Capturing before web fonts/images finish loading is a classic
                    // html2canvas trap: it silently rasterizes with fallback fonts and/or
                    // blank image boxes, which is what made downloaded PDFs look different
                    // (cramped spacing, missing photo) from the on-screen preview.
                    await this.waitForResumeAssets(el);

                    const canvas = await html2canvas(el, {
                        scale: 2,
                        useCORS: true,
                        backgroundColor: '#ffffff',
                        // el sits inside a scrollable modal pane; without these, html2canvas can
                        // clip the capture to whatever was scrolled into view instead of the
                        // element's full content height.
                        width: el.scrollWidth,
                        height: el.scrollHeight,
                        windowWidth: el.scrollWidth,
                        windowHeight: el.scrollHeight,
                        scrollX: 0,
                        scrollY: 0,
                    });
                    await LibLoader.ensureJsPDF();
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF({ unit: 'pt', format: 'a4' });
                    const pageWidth = doc.internal.pageSize.getWidth();
                    const pageHeight = doc.internal.pageSize.getHeight();

                    // A page-edge-to-page-edge image looks unfinished, and on a page break
                    // it cuts straight across whatever content happened to land there with
                    // no breathing room at all. A modest margin on every page makes each
                    // page read like an actual printed page even where a section does get
                    // split across two pages.
                    const marginX = 32;
                    const marginY = 28;
                    const imgWidth = pageWidth - marginX * 2;
                    const imgHeight = (canvas.height * imgWidth) / canvas.width;
                    const contentHeightPerPage = pageHeight - marginY * 2;

                    const imgData = canvas.toDataURL('image/png');
                    let heightLeft = imgHeight;
                    let position = marginY;

                    doc.addImage(imgData, 'PNG', marginX, position, imgWidth, imgHeight);
                    heightLeft -= contentHeightPerPage;

                    while (heightLeft > 0) {
                        position = marginY - (imgHeight - heightLeft);
                        doc.addPage();
                        doc.addImage(imgData, 'PNG', marginX, position, imgWidth, imgHeight);
                        heightLeft -= contentHeightPerPage;
                    }

                    doc.save(this.resumeFileBaseName() + '.pdf');
                },
                /** Resolves once web fonts and every <img> inside the resume preview have finished loading (or a 3s timeout, so a slow/broken image never blocks the download entirely). */
                waitForResumeAssets(el) {
                    const fontsReady = document.fonts && document.fonts.ready
                        ? document.fonts.ready
                        : Promise.resolve();

                    const images = Array.from(el.querySelectorAll('img'));
                    const imagesReady = Promise.all(images.map(img => {
                        if (img.complete) return Promise.resolve();
                        return new Promise(resolve => {
                            img.addEventListener('load', resolve, { once: true });
                            img.addEventListener('error', resolve, { once: true });
                        });
                    }));

                    const timeout = new Promise(resolve => setTimeout(resolve, 3000));

                    return Promise.race([Promise.all([fontsReady, imagesReady]), timeout]);
                },

                openDeleteDocumentModal() {
                    if (this.profile.verification_document) {
                        this.showDeleteDocumentModal = true;
                    }
                },

                closeDeleteDocumentModal() {
                    this.showDeleteDocumentModal = false;
                    this.documentToDelete = null;
                },

                deleteVerificationDocument() {
                    fetch('alumni_profile_data?action=deleteVerificationDocument', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.showNotification('Verification document deleted successfully!', 'success');
                            this.profile.verification_document = ''; // This will make the section disappear
                            this.closeDeleteDocumentModal();
                        } else {
                            this.showNotification(data.message || 'Failed to delete document.', 'error');
                        }
                    })
                    .catch(() => {
                        this.showNotification('Failed to delete document.', 'error');
                    });
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
                logout() {
                    window.location.href = 'logout';
                },
                formatDate(dateString) {
                    if (!dateString) return '';
                    const date = new Date(dateString);
                    if (isNaN(date)) return dateString;
                    const options = { year: 'numeric', month: 'short', day: 'numeric' };
                    return date.toLocaleDateString(undefined, options);
                },
                editProfile() {
                    // Copy all fields from profile to editProfileData
                    this.editProfileData = {
                        first_name: this.profile.first_name || '',
                        middle_name: this.profile.middle_name || '',
                        last_name: this.profile.last_name || '',
                        email: this.profile.email || '',
                        secondary_email: this.profile.secondary_email || '',
                        contact: this.profile.contact || '',
                        address: this.profile.address || '',
                        province: this.profile.province || '',
                        city: this.profile.city || '',
                        birthdate: this.profile.birthdate || '',
                        gender: this.profile.gender || '',
                        civil_status: this.profile.civil_status || '',
                        campus_id: this.profile.campus_id || '',
                        college: this.profile.college || '',
                        course: this.profile.course || '',
                        degree: this.profile.degree || '',
                        year_graduated: this.profile.year_graduated || '',
                        status_prior_graduation: this.profile.status_prior_graduation || '',
                        status_employement_graduation: this.profile.status_employement_graduation || '',
                        image: this.profile.image || '',
                        verification_document: this.profile.verification_document || '',
                    };
                    this.updateCollegeOptions(true);
                    if (this.editProfileData.province) {
                        this.fetchCities();
                    }
                    this.showEditModal = true;
                },
                openEditModal() { this.editProfile(); },
                closeEditModal() { this.showEditModal = false; },
                saveProfile() {
                    const formData = new FormData();
                    for (const key in this.editProfileData) {
                        formData.append(key, this.editProfileData[key]);
                    }
                    fetch('alumni_profile_data?action=updateProfile', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.profile = {
                                ...this.profile,
                                ...this.editProfileData,
                                campus_name: this.campusNameById(this.editProfileData.campus_id)
                            };
                            this.closeEditModal();
                            this.showNotification('Profile updated successfully!', 'success');
                        } else {
                            this.showNotification(data.message || 'Failed to update profile.', 'error');
                        }
                    })
                    .catch(() => {
                        this.showNotification('Failed to update profile.', 'error');
                    });
                },
                showNotification(message, type = 'success') {
                    const id = this.notificationId++;
                    this.notifications.push({ id, type, message });
                    setTimeout(() => {
                        this.notifications = this.notifications.filter(n => n.id !== id);
                    }, 3000);
                },
                openEducationModal() {
                    this.showEducationModal = true;
                    this.degreeInput = this.editEducationData.degree || '';
                    this.schoolInput = this.editEducationData.school || '';
                },
                closeEducationModal() { this.showEducationModal = false; this.editingEducationIndex = null; },
                saveEducation() {
                    if (this.editingEducationIndex === null) {
                        // For new education, make sure to set degree and school from inputs
                        this.editEducationData.degree = this.degreeInput;
                        this.editEducationData.school = this.schoolInput;
                        this.addEducationToBackend();
                    } else {
                        // Ensure degree and school are set from inputs
                        this.editEducationData.degree = this.degreeInput;
                        this.editEducationData.school = this.schoolInput;
                        
                        // Update existing education
                        const formData = new FormData();
                        formData.append('id', this.editEducationData.id);
                        formData.append('degree', this.editEducationData.degree);
                        formData.append('school', this.editEducationData.school);
                        formData.append('start_date', this.editEducationData.start_date);
                        formData.append('end_date', this.editEducationData.end_date);
                        formData.append('current', this.editEducationData.current ? 'true' : '');
                        
                        fetch('alumni_profile_data?action=updateEducation', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.showNotification('Education updated!', 'success');
                                this.fetchEducation();
                                this.closeEducationModal();
                            } else {
                                this.showNotification(data.message || 'Failed to update education.', 'error');
                            }
                        })
                        .catch(() => {
                            this.showNotification('Failed to update education.', 'error');
                        });
                    }
                },
                editEducation(index) {
                    this.editingEducationIndex = index;
                    const edu = this.profile.education[index];
                    this.editEducationData = { ...edu, id: edu.education_id };
                    this.degreeInput = edu.degree;
                    this.schoolInput = edu.school;
                    this.openEducationModal();
                },
                deleteEducation(index) {
                    this.educationToDeleteIndex = index;
                    this.educationToDeleteId = this.profile.education[index].education_id;
                    this.showDeleteEducationModal = true;
                },
                confirmDeleteEducation() {
                    const education_id = this.educationToDeleteId;
                    if (!education_id) return;
                    const formData = new FormData();
                    formData.append('id', education_id);
                    fetch('alumni_profile_data?action=deleteEducation', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.showNotification('Education deleted!', 'success');
                            this.fetchEducation();
                        } else {
                            this.showNotification(data.message || 'Failed to delete education.', 'error');
                        }
                        this.showDeleteEducationModal = false;
                        this.educationToDeleteIndex = null;
                        this.educationToDeleteId = null;
                    })
                    .catch(() => {
                        this.showNotification('Failed to delete education.', 'error');
                        this.showDeleteEducationModal = false;
                        this.educationToDeleteIndex = null;
                        this.educationToDeleteId = null;
                    });
                },
                cancelDeleteEducation() {
                    this.showDeleteEducationModal = false;
                    this.educationToDeleteIndex = null;
                },

                // ---- Certifications ----
                fetchCertifications() {
                    fetch('alumni_profile_data?action=certifications')
                        .then(res => res.json())
                        .then(data => {
                            this.profile.certifications = data.success ? (data.certifications || []) : [];
                        })
                        .catch(() => {
                            this.profile.certifications = [];
                        });
                },
                openCertificationModal() {
                    this.showCertificationModal = true;
                },
                closeCertificationModal() {
                    this.showCertificationModal = false;
                    this.editingCertificationIndex = null;
                    this.editCertificationData = { id: null, name: '', issuer: '', issue_date: '', certificate_file: null };
                    this.certificationFilePreview = null;
                },
                editCertification(index) {
                    this.editingCertificationIndex = index;
                    const cert = this.profile.certifications[index];
                    this.editCertificationData = { id: cert.certification_id, name: cert.name, issuer: cert.issuer, issue_date: cert.issue_date, certificate_file: null };
                    this.certificationFilePreview = null;
                    this.openCertificationModal();
                },
                handleCertificationFileUpload(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.editCertificationData.certificate_file = file;
                        this.certificationFilePreview = file.name;
                    }
                },
                saveCertification() {
                    if (!this.editCertificationData.name) return;

                    const formData = new FormData();
                    formData.append('name', this.editCertificationData.name);
                    formData.append('issuer', this.editCertificationData.issuer || '');
                    formData.append('issue_date', this.editCertificationData.issue_date || '');
                    if (this.editCertificationData.certificate_file) {
                        formData.append('certificate_file', this.editCertificationData.certificate_file);
                    }

                    if (this.editingCertificationIndex === null) {
                        fetch('alumni_profile_data?action=addCertification', { method: 'POST', body: formData })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    this.showNotification('Certification added!', 'success');
                                    this.fetchCertifications();
                                    this.closeCertificationModal();
                                } else {
                                    this.showNotification(data.message || 'Failed to add certification.', 'error');
                                }
                            })
                            .catch(() => this.showNotification('Failed to add certification.', 'error'));
                    } else {
                        formData.append('id', this.editCertificationData.id);
                        fetch('alumni_profile_data?action=updateCertification', { method: 'POST', body: formData })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    this.showNotification('Certification updated!', 'success');
                                    this.fetchCertifications();
                                    this.closeCertificationModal();
                                } else {
                                    this.showNotification(data.message || 'Failed to update certification.', 'error');
                                }
                            })
                            .catch(() => this.showNotification('Failed to update certification.', 'error'));
                    }
                },
                deleteCertification(index) {
                    this.certificationToDeleteIndex = index;
                    this.certificationToDeleteId = this.profile.certifications[index].certification_id;
                    this.showDeleteCertificationModal = true;
                },
                confirmDeleteCertification() {
                    const certification_id = this.certificationToDeleteId;
                    if (!certification_id) return;
                    const formData = new FormData();
                    formData.append('id', certification_id);
                    fetch('alumni_profile_data?action=deleteCertification', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.showNotification('Certification deleted!', 'success');
                                this.fetchCertifications();
                            } else {
                                this.showNotification(data.message || 'Failed to delete certification.', 'error');
                            }
                            this.showDeleteCertificationModal = false;
                            this.certificationToDeleteIndex = null;
                            this.certificationToDeleteId = null;
                        })
                        .catch(() => {
                            this.showNotification('Failed to delete certification.', 'error');
                            this.showDeleteCertificationModal = false;
                            this.certificationToDeleteIndex = null;
                            this.certificationToDeleteId = null;
                        });
                },
                cancelDeleteCertification() {
                    this.showDeleteCertificationModal = false;
                    this.certificationToDeleteIndex = null;
                },
                viewCertificationFile(cert) {
                    if (cert.certificate_file) {
                        window.open('uploads/certificates/' + cert.certificate_file, '_blank');
                    }
                },

                async fetchDegrees(query) {
                    const url = `https://www.wikidata.org/w/api.php?action=wbsearchentities&search=${encodeURIComponent(query)}&language=en&type=item&format=json&origin=*`;
                    
                    try {
                        const response = await fetch(url);
                        const data = await response.json();
                        
                        // Filter results: only keep those with descriptions mentioning "degree", "program", or "course"
                        return (data.search || []).filter(item => {
                            const desc = item.description ? item.description.toLowerCase() : "";
                            return desc.includes("degree") || desc.includes("program") || desc.includes("course") || desc.includes("academic");
                        });
                    } catch (err) {
                        console.error("Fetch failed", err);
                        return []; // Always return an array, even on error
                    }
                },
                
                initAutocompletes() {
                    this.initUniversityAutocomplete();
                    this.initDegreeAutocomplete();
                },
                
                // Also update the autocomplete event handlers to handle undefined results
                initDegreeAutocomplete() {
                    const input = document.getElementById("degreeInput");
                    const suggestions = document.getElementById("degreeSuggestions");
                    
                    if (!input || !suggestions) return;
                    
                    // Clear any existing event listeners
                    const newInput = input.cloneNode(true);
                    input.parentNode.replaceChild(newInput, input);
                    
                    const degreeInput = document.getElementById("degreeInput");
                    const suggestionsContainer = document.getElementById("degreeSuggestions");
                    
                    degreeInput.addEventListener("input", this.debounce(async () => {
                        const query = degreeInput.value.trim();
                        suggestionsContainer.innerHTML = "";
                        
                        if (!query) return;
                        
                        const results = await this.fetchDegrees(query);
                        
                        // Add null/undefined check before calling slice
                        if (results && Array.isArray(results)) {
                            results.slice(0, 8).forEach(item => {
                                const div = document.createElement("div");
                                div.className = "px-3 py-2 cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-800 text-gray-800 dark:text-gray-200";
                                div.textContent = item.label + (item.description ? " – " + item.description : "");
                                div.addEventListener("click", () => {
                                    degreeInput.value = item.label;
                                    this.degreeInput = item.label; // Update Vue data
                                    suggestionsContainer.innerHTML = "";
                                    console.log("Selected Degree:", item.id, item.label);
                                });
                                suggestionsContainer.appendChild(div);
                            });
                        }
                    }, 300));
                    
                    document.addEventListener("click", (e) => {
                        if (!e.target.closest(".autocomplete")) {
                            suggestionsContainer.innerHTML = "";
                        }
                    });
                },
               
                openSkillsModal() { this.showSkillsModal = true; this.editSkillsData = []; this.newSkill = { name: '' }; },
                closeSkillsModal() { this.showSkillsModal = false; },
                addSkill() {
                    if (this.newSkill.name) {
                        const formData = new FormData();
                        formData.append('name', this.newSkill.name);

                        fetch('alumni_profile_data?action=addSkill', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.showNotification('Skill added!', 'success');
                                this.fetchSkills();
                                this.newSkill = { name: '' };
                            } else {
                                this.showNotification(data.message || 'Failed to add skill.', 'error');
                            }
                        })
                        .catch(() => {
                            this.showNotification('Failed to add skill.', 'error');
                        });
                    }
                },
                removeSkill(index) {
                    this.skillToDeleteIndex = index;
                    this.skillToDeleteId = this.profile.skills[index].skill_id;
                    this.showDeleteSkillModal = true;
                },
                confirmDeleteSkill() {
                    const skill_id = this.skillToDeleteId;
                    if (!skill_id) return;
                    const formData = new FormData();
                    formData.append('id', skill_id);
                    fetch('alumni_profile_data?action=deleteSkill', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.showNotification('Skill deleted!', 'success');
                            this.fetchSkills();
                        } else {
                            this.showNotification(data.message || 'Failed to delete skill.', 'error');
                        }
                        this.showDeleteSkillModal = false;
                        this.skillToDeleteIndex = null;
                        this.skillToDeleteId = null;
                    })
                    .catch(() => {
                        this.showNotification('Failed to delete skill.', 'error');
                        this.showDeleteSkillModal = false;
                        this.skillToDeleteIndex = null;
                        this.skillToDeleteId = null;
                    });
                },
                cancelDeleteSkill() {
                    this.showDeleteSkillModal = false;
                    this.skillToDeleteIndex = null;
                    this.skillToDeleteId = null;
                },
                saveSkills() {
                    this.profile.skills = [...this.editSkillsData];
                    this.closeSkillsModal();
                },
                removeEditSkill(index) {
                    this.editSkillsData.splice(index, 1);
                },
                async getSkillsToken() {
                    if (this.skillsApi.token && Date.now() < this.skillsApi.tokenExpiry) {
                        return this.skillsApi.token;
                    }
        
                    try {
                        const response = await fetch("https://auth.emsicloud.com/connect/token", {
                            method: "POST",
                            headers: { "Content-Type": "application/x-www-form-urlencoded" },
                            body: new URLSearchParams({
                                client_id: this.skillsApi.clientId,
                                client_secret: this.skillsApi.clientSecret,
                                grant_type: "client_credentials",
                                scope: this.skillsApi.scope
                            })
                        });
        
                        const data = await response.json();
                        this.skillsApi.token = data.access_token;
                        this.skillsApi.tokenExpiry = Date.now() + data.expires_in * 1000;
                        return this.skillsApi.token;
                    } catch (error) {
                        console.error("Error fetching skills token:", error);
                        return null;
                    }
                },
        
                async searchSkills(query) {
                    if (!query || query.length < 2) {
                        this.skillSuggestions = [];
                        this.showSkillSuggestions = false;
                        return;
                    }
        
                    const token = await this.getSkillsToken();
                    if (!token) return;
        
                    try {
                        const response = await fetch(
                            `https://emsiservices.com/skills/versions/latest/skills?q=${encodeURIComponent(query)}&limit=5`,
                            {
                                headers: {
                                    "Authorization": "Bearer " + token,
                                    "accept": "application/json"
                                }
                            }
                        );
        
                        const data = await response.json();
                        this.skillSuggestions = data.data || [];
                        this.showSkillSuggestions = this.skillSuggestions.length > 0;
                    } catch (error) {
                        console.error("Error searching skills:", error);
                        this.skillSuggestions = [];
                        this.showSkillSuggestions = false;
                    }
                },
        
                onSkillInput() {
                    // Clear previous timeout
                    if (this.skillInputTimeout) {
                        clearTimeout(this.skillInputTimeout);
                    }
                    
                    // Set new timeout for debouncing
                    this.skillInputTimeout = setTimeout(() => {
                        this.searchSkills(this.newSkill.name);
                    }, 300);
                },
        
                selectSkillSuggestion(skill) {
                    this.newSkill.name = skill.name;
                    this.skillSuggestions = [];
                    this.showSkillSuggestions = false;
                },
        
                hideSkillSuggestions() {
                    setTimeout(() => {
                        this.showSkillSuggestions = false;
                    }, 200);
                },
                openExperienceModal() { this.showExperienceModal = true; },
                closeExperienceModal() { this.showExperienceModal = false; this.editingExperienceIndex = null; },
                saveExperience() {
                    if (this.editingExperienceIndex === null) {
                        // Add new experience
                        const formData = new FormData();
                        formData.append('title', this.editExperienceData.title);
                        formData.append('company', this.editExperienceData.company);
                        formData.append('start_date', this.editExperienceData.start_date);
                        formData.append('end_date', this.editExperienceData.end_date);
                        formData.append('current', this.editExperienceData.current ? '1' : '0');
                        formData.append('description', this.editExperienceData.description);
                        formData.append('location_of_work', this.editExperienceData.location_of_work);
                        formData.append('employment_status', this.editExperienceData.employment_status);
                        formData.append('employment_sector', this.editExperienceData.employment_sector); // NEW
                        fetch('alumni_profile_data?action=addExperience', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.showNotification('Experience added!', 'success');
                                this.fetchExperience();
                                this.closeExperienceModal();
                            } else {
                                this.showNotification(data.message || 'Failed to add experience.', 'error');
                            }
                        })
                        .catch(() => {
                            this.showNotification('Failed to add experience.', 'error');
                        });
                    } else {
                        // ... existing update logic ...
                        const formData = new FormData();
                        formData.append('id', this.editExperienceData.experience_id);
                        formData.append('title', this.editExperienceData.title);
                        formData.append('company', this.editExperienceData.company);
                        formData.append('start_date', this.editExperienceData.start_date);
                        formData.append('end_date', this.editExperienceData.end_date);
                        formData.append('current', this.editExperienceData.current ? '1' : '0');
                        formData.append('description', this.editExperienceData.description);
                        formData.append('location_of_work', this.editExperienceData.location_of_work);
                        formData.append('employment_status', this.editExperienceData.employment_status);
                        formData.append('employment_sector', this.editExperienceData.employment_sector); // NEW
                        fetch('alumni_profile_data?action=updateExperience', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.showNotification('Experience updated!', 'success');
                                this.fetchExperience();
                                this.closeExperienceModal();
                            } else {
                                this.showNotification(data.message || 'Failed to update experience.', 'error');
                            }
                        })
                        .catch(() => {
                            this.showNotification('Failed to update experience.', 'error');
                        });
                    }
                },
                editExperience(index) {
                    this.editingExperienceIndex = index;
                    const exp = this.profile.experiences[index];
                    this.editExperienceData = {
                        ...exp,
                        experience_id: exp.experience_id,
                        location_of_work: exp.location_of_work || '',
                        employment_status: exp.employment_status || '',
                        employment_sector: exp.employment_sector || '' // NEW
                    };
                    this.openExperienceModal();
                },
                deleteExperience(index) {
                    this.experienceToDeleteIndex = index;
                    this.experienceToDeleteId = this.profile.experiences[index].experience_id;
                    this.showDeleteExperienceModal = true;
                },
                confirmDeleteExperience() {
                    const experience_id = this.experienceToDeleteId;
                    if (!experience_id) return;
                    const formData = new FormData();
                    formData.append('id', experience_id);
                    fetch('alumni_profile_data?action=deleteExperience', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.showNotification('Experience deleted!', 'success');
                            this.fetchExperience();
                        } else {
                            this.showNotification(data.message || 'Failed to delete experience.', 'error');
                        }
                        this.showDeleteExperienceModal = false;
                        this.experienceToDeleteIndex = null;
                        this.experienceToDeleteId = null;
                    })
                    .catch(() => {
                        this.showNotification('Failed to delete experience.', 'error');
                        this.showDeleteExperienceModal = false;
                        this.experienceToDeleteIndex = null;
                        this.experienceToDeleteId = null;
                    });
                },
                cancelDeleteExperience() {
                    this.showDeleteExperienceModal = false;
                    this.experienceToDeleteIndex = null;
                    this.experienceToDeleteId = null;
                },
                async getEmsiToken() {
                    if (this.emsiApi.token && Date.now() < this.emsiApi.tokenExpiry) {
                        return this.emsiApi.token;
                    }
        
                    try {
                        const response = await fetch("https://auth.emsicloud.com/connect/token", {
                            method: "POST",
                            headers: { "Content-Type": "application/x-www-form-urlencoded" },
                            body: new URLSearchParams({
                                client_id: this.emsiApi.clientId,
                                client_secret: this.emsiApi.clientSecret,
                                grant_type: "client_credentials",
                                scope: this.emsiApi.scope
                            })
                        });
        
                        const data = await response.json();
                        this.emsiApi.token = data.access_token;
                        this.emsiApi.tokenExpiry = Date.now() + data.expires_in * 1000;
                        return this.emsiApi.token;
                    } catch (error) {
                        console.error("Error fetching EMSI token:", error);
                        return null;
                    }
                },
                // Job title auto-suggestion methods
                async searchJobTitles(query) {
                    if (!query || query.length < 2) {
                        this.titleSuggestions = [];
                        this.showTitleSuggestions = false;
                        return;
                    }

                    const token = await this.getEmsiToken();
                    if (!token) return;

                    try {
                        const response = await fetch(
                            `https://emsiservices.com/titles/versions/latest/titles?q=${encodeURIComponent(query)}&limit=5`,
                            {
                                headers: {
                                    "Authorization": "Bearer " + token,
                                    "accept": "application/json"
                                }
                            }
                        );

                        const data = await response.json();
                        this.titleSuggestions = data.data || [];
                        this.showTitleSuggestions = this.titleSuggestions.length > 0;
                    } catch (error) {
                        console.error("Error searching job titles:", error);
                        this.titleSuggestions = [];
                        this.showTitleSuggestions = false;
                    }
                },

                onTitleInput() {
                    if (this.titleInputTimeout) {
                        clearTimeout(this.titleInputTimeout);
                    }
                    
                    this.titleInputTimeout = setTimeout(() => {
                        this.searchJobTitles(this.editExperienceData.title);
                    }, 300);
                },

                selectTitleSuggestion(title) {
                    this.editExperienceData.title = title.name;
                    this.titleSuggestions = [];
                    this.showTitleSuggestions = false;
                },

                hideTitleSuggestions() {
                    setTimeout(() => {
                        this.showTitleSuggestions = false;
                    }, 200);
                },
                openResumeModal() { this.showResumeModal = true; },
                closeResumeModal() { this.showResumeModal = false; this.resumePreview = null; },
                handleResumeUpload(e) {
                    const file = e.target.files[0];
                    if (file) {
                        this.resumePreview = URL.createObjectURL(file);
                        this.editResumeFile = file;
                    }
                },
                saveResume() {
                    if (this.editResumeFile) {
                        this.profile.resume = { name: this.editResumeFile.name, url: this.resumePreview };
                    }
                    this.closeResumeModal();
                },
                openPhotoModal() { this.showPhotoModal = true; this.newPhotoPreview = null; },
                closePhotoModal() { this.showPhotoModal = false; this.newPhotoPreview = null; },
                handlePhotoUpload(event) {
                    const file = event.target.files[0];
                    if (file) {
                        this.newPhotoFile = file;
                        console.log('Selected file:', file);
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.newPhotoPreview = e.target.result;
                        };
                        reader.readAsDataURL(file);
                    } else {
                        this.newPhotoFile = null;
                        console.log('No file selected');
                    }
                },
                savePhoto() {
                    if (this.newPhotoPreview) {
                        this.profile.image = this.newPhotoPreview;
                        this.closePhotoModal();
                    }
                },
                initUniversityAutocomplete() {
                    const input = document.getElementById("universityInput");
                    const suggestions = document.getElementById("suggestions");
                    
                    if (!input || !suggestions) return;
                    
                    // Clear any existing event listeners
                    const newInput = input.cloneNode(true);
                    input.parentNode.replaceChild(newInput, input);
                    
                    const universityInput = document.getElementById("universityInput");
                    const suggestionsContainer = document.getElementById("suggestions");
                    
                    universityInput.addEventListener("input", this.debounce(async () => {
                        const query = universityInput.value.trim();
                        suggestionsContainer.innerHTML = "";
                        
                        if (!query) return;
                        
                        const results = await this.fetchUniversities(query);
                        
                        results.slice(0, 8).forEach(item => {
                            const div = document.createElement("div");
                            div.className = "px-3 py-2 cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-800 text-gray-800 dark:text-gray-200";
                            div.textContent = item.label + (item.description ? " – " + item.description : "");
                            div.addEventListener("click", () => {
                                universityInput.value = item.label;
                                this.schoolInput = item.label; // Update Vue data
                                suggestionsContainer.innerHTML = "";
                                console.log("Selected:", item.id, item.label);
                            });
                            suggestionsContainer.appendChild(div);
                        });
                    }, 300));
                    
                    document.addEventListener("click", (e) => {
                        if (!e.target.closest(".autocomplete")) {
                            suggestionsContainer.innerHTML = "";
                        }
                    });
                },
                
                debounce(func, wait) {
                    let timeout;
                    return function executedFunction(...args) {
                        const later = () => {
                            clearTimeout(timeout);
                            func(...args);
                        };
                        clearTimeout(timeout);
                        timeout = setTimeout(later, wait);
                    };
                },
                
                async fetchUniversities(query) {
                    const url = `https://www.wikidata.org/w/api.php?action=wbsearchentities&search=${encodeURIComponent(query)}&language=en&type=item&format=json&origin=*`;
                    
                    try {
                        const response = await fetch(url);
                        const data = await response.json();
                        
                        // Filter results: only items with "university", "school", or "college" in description
                        return (data.search || []).filter(item => {
                            const desc = item.description ? item.description.toLowerCase() : "";
                            return desc.includes("university") || desc.includes("school") || desc.includes("college");
                        });
                    } catch (err) {
                        console.error("Fetch failed", err);
                        return [];
                    }
                },
                openDocumentModal() { this.showDocumentModal = true; this.documentPreview = null; this.documentFile = null; },
                closeDocumentModal() { this.showDocumentModal = false; this.documentPreview = null; this.documentFile = null; },
                handleDocumentUpload(e) {
                    const file = e.target.files[0];
                    if (file) {
                        this.documentFile = file;
                        const ext = file.name.split('.').pop().toLowerCase();
                        if (ext === 'pdf') {
                            this.documentPreviewType = 'pdf';
                            this.documentPreview = URL.createObjectURL(file);
                        } else if (['jpg','jpeg','png','gif'].includes(ext)) {
                            this.documentPreviewType = 'img';
                            const reader = new FileReader();
                            reader.onload = (ev) => { this.documentPreview = ev.target.result; };
                            reader.readAsDataURL(file);
                        } else {
                            this.documentPreview = null;
                        }
                    }
                },
                updateVerificationDocument() {
                    if (!this.documentFile) {
                        this.showNotification('No file selected.', 'error');
                        return;
                    }
                    const formData = new FormData();
                    formData.append('verification_document', this.documentFile);
                    fetch('alumni_profile_data?action=updateVerificationDocument', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.showNotification('Verification document updated!', 'success');
                            // Update the UI
                            this.profile.verification_document = data.file_name;
                            this.closeDocumentModal();
                        } else {
                            this.showNotification(data.message || 'Failed to update document.', 'error');
                        }
                    })
                    .catch(() => {
                        this.showNotification('Failed to update document.', 'error');
                    });
                },
                // Success Story Methods
                openSuccessStoryModal() {
                    this.showSuccessStoryModal = true;
                },
                closeSuccessStoryModal() {
                    this.showSuccessStoryModal = false;
                    this.editingSuccessStoryIndex = null;
                    this.editSuccessStoryData = { title: '', content: '' };
                },
                editSuccessStory(index) {
                    this.editingSuccessStoryIndex = index;
                    this.editSuccessStoryData = { ...this.successStories[index] };
                    this.showSuccessStoryModal = true;
                },
                async saveSuccessStory() {
                    try {
                        const formData = new FormData();
                        formData.append('title', this.editSuccessStoryData.title);
                        formData.append('content', this.editSuccessStoryData.content);
                        
                        if (this.editingSuccessStoryIndex !== null) {
                            formData.append('story_id', this.successStories[this.editingSuccessStoryIndex].story_id);
                            const response = await fetch('alumni_success_stories?action=update', {
                                method: 'POST',
                                body: formData
                            });
                            const result = await response.json();
                            
                            if (result.success) {
                                this.successStories[this.editingSuccessStoryIndex] = result.story;
                                this.showNotification('Success story updated successfully!', 'success');
                            } else {
                                this.showNotification(result.message || 'Error updating story', 'error');
                            }
                        } else {
                            const response = await fetch('alumni_success_stories?action=store', {
                                method: 'POST',
                                body: formData
                            });
                            const result = await response.json();
                            
                            if (result.success) {
                                this.successStories.push(result.story);
                                this.showNotification('Success story submitted for review!', 'success');
                            } else {
                                this.showNotification(result.message || 'Error adding story', 'error');
                            }
                        }
                        
                        this.closeSuccessStoryModal();
                    } catch (error) {
                        this.showNotification('Error saving success story', 'error');
                        console.error('Error:', error);
                    }
                },
                deleteSuccessStory(index) {
                    this.deletingSuccessStoryIndex = index;
                    this.showDeleteSuccessStoryModal = true;
                },
                cancelDeleteSuccessStory() {
                    this.showDeleteSuccessStoryModal = false;
                    this.deletingSuccessStoryIndex = null;
                },
                async confirmDeleteSuccessStory() {
                    if (this.deletingSuccessStoryIndex === null) return;
                    
                    try {
                        const storyId = this.successStories[this.deletingSuccessStoryIndex].story_id;
                        const response = await fetch('alumni_success_stories?action=destroy', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ story_id: storyId })
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            this.successStories.splice(this.deletingSuccessStoryIndex, 1);
                            this.showNotification('Success story deleted successfully!', 'success');
                        } else {
                            this.showNotification(result.message || 'Error deleting story', 'error');
                        }
                    } catch (error) {
                        this.showNotification('Error deleting success story', 'error');
                        console.error('Error:', error);
                    }
                    
                    this.showDeleteSuccessStoryModal = false;
                    this.deletingSuccessStoryIndex = null;
                },
                async fetchSuccessStories() {
                    try {
                        const response = await fetch('alumni_success_stories');
                        const result = await response.json();
                        
                        if (result.success) {
                            this.successStories = result.stories;
                        } else {
                            this.showNotification('Error loading success stories', 'error');
                        }
                    } catch (error) {
                        console.error('Error fetching success stories:', error);
                    }
                },
                fetchCities() {
                    this.cities = [];
                    
                    // Add array check
                    if (!Array.isArray(this.provinces) || !this.editProfileData.province) return;
                    
                    const province = this.provinces.find(p => p.name === this.editProfileData.province);
                    if (!province) return;
                    
                    fetch(`https://psgc.gitlab.io/api/provinces/${province.code}/cities-municipalities/`)
                        .then(res => res.json())
                        .then(data => {
                            this.cities = Array.isArray(data) 
                                ? data.map(c => ({ name: c.name, code: c.code }))
                                : [];
                        })
                        .catch(() => {
                            if (this.editProfileData.province === 'Laguna') {
                                this.cities = [
                                    { name: 'San Pablo City', code: '043404' },
                                    { name: 'Calamba City', code: '043405' },
                                    { name: 'Santa Cruz', code: '043406' }
                                ];
                            } else {
                                this.cities = [];
                            }
                        });
                },
                campusNameById(campusId) {
                    const campus = this.campuses.find(c => String(c.campus_id) === String(campusId));
                    return campus ? campus.name : null;
                },
                updateCollegeOptions(preserveSelection) {
                    // Rebuild the college list for the selected campus. When called from a plain
                    // campus change, clear the now-invalid college/course; when priming the modal
                    // from an existing profile (preserveSelection), keep them if still valid.
                    const campusName = this.campusNameById(this.editProfileData.campus_id);
                    const collegeCourses = campusName ? (this.campusCollegeCourses[campusName] || {}) : {};
                    this.collegeCourses = collegeCourses;
                    this.colleges = Object.keys(collegeCourses);

                    if (!preserveSelection || !this.colleges.includes(this.editProfileData.college)) {
                        this.editProfileData.college = '';
                        this.editProfileData.course = '';
                    } else {
                        this.updateCourseOptions(preserveSelection);
                    }
                },
                updateCourseOptions(preserveSelection) {
                    // Clear course if college changes
                    const courses = this.collegeCourses[this.editProfileData.college] || [];
                    if (!this.editProfileData.college || !courses.includes(this.editProfileData.course)) {
                        if (!preserveSelection) {
                            this.editProfileData.course = '';
                        }
                    }
                },
                fetchEducation() {
                    fetch('alumni_profile_data?action=education')
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // Ensure we're properly assigning to the reactive property
                                this.profile.education = data.education || [];
                            } else {
                                this.profile.education = [];
                            }
                        })
                        .catch(() => {
                            this.profile.education = [];
                        });
                },
                addEducationToBackend() {
                    // Make sure degree and school are set from inputs
                    this.editEducationData.degree = this.degreeInput;
                    this.editEducationData.school = this.schoolInput;
                    
                    const formData = new FormData();
                    for (const key in this.editEducationData) {
                        if (this.editEducationData[key] !== null && this.editEducationData[key] !== undefined) {
                            formData.append(key, this.editEducationData[key]);
                        }
                    }
                    
                    fetch('alumni_profile_data?action=addEducation', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.showNotification('Education added!', 'success');
                            this.fetchEducation();
                            this.closeEducationModal();
                        } else {
                            this.showNotification(data.message || 'Failed to add education.', 'error');
                        }
                    })
                    .catch(() => {
                        this.showNotification('Failed to add education.', 'error');
                    });
                },
                fetchSchools() {
                    this.schools = [
                        'Laguna State Polytechnic University',
                        'UP Diliman',
                        'Ateneo de Manila University',
                        'De La Salle University',
                        'Far Eastern University',
                        'University of Santo Tomas',
                        'Mapua University',
                        'Adamson University',
                        'Polytechnic University of the Philippines'
                    ];
                },
                filterSchoolSuggestions() {
                    const val = this.schoolInput.trim();
                    if (!val) {
                        this.filteredSchoolSuggestions = [];
                        this.showSchoolSuggestions = false;
                        return;
                    }
                    fetch(`https://universities.hipolabs.com/search?name=${encodeURIComponent(val)}`)
                        .then(res => res.json())
                        .then(data => {
                            this.filteredSchoolSuggestions = data.map(u => u.name).slice(0, 8);
                            this.showSchoolSuggestions = this.filteredSchoolSuggestions.length > 0;
                        })
                        .catch(() => {
                            this.filteredSchoolSuggestions = [];
                            this.showSchoolSuggestions = false;
                        });
                    this.editEducationData.school = this.schoolInput;
                },
                selectDegreeSuggestion(suggestion) {
                    this.degreeInput = suggestion;
                    this.editEducationData.degree = suggestion;
                    this.showDegreeSuggestions = false;
                },
                selectSchoolSuggestion(suggestion) {
                    this.schoolInput = suggestion;
                    this.editEducationData.school = suggestion;
                    this.showSchoolSuggestions = false;
                },
                hideDegreeSuggestions() {
                    setTimeout(() => { this.showDegreeSuggestions = false; }, 150);
                },
                hideSchoolSuggestions() {
                    setTimeout(() => { this.showSchoolSuggestions = false; }, 150);
                },
                fetchExperience() {
                    fetch('alumni_profile_data?action=experience')
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                // Ensure we're properly assigning to the reactive property
                                this.profile.experiences = data.experience || [];
                            } else {
                                this.profile.experiences = [];
                            }
                        })
                        .catch(() => {
                            this.profile.experiences = [];
                        });
                },        
                addExperienceToBackend() {
                    const formData = new FormData();
                    for (const key in this.editExperienceData) {
                        formData.append(key, this.editExperienceData[key]);
                    }
                    fetch('alumni_profile_data?action=addExperience', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.showNotification('Experience added!', 'success');
                            this.fetchExperience();
                            this.closeExperienceModal();
                        } else {
                            this.showNotification(data.message || 'Failed to add experience.', 'error');
                        }
                    })
                    .catch(() => {
                        this.showNotification('Failed to add experience.', 'error');
                    });
                },
                fetchSkills() {
                    fetch('alumni_profile_data?action=skills')
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.profile.skills = data.skills || [];
                            } else {
                                this.profile.skills = [];
                            }
                        })
                        .catch(() => {
                            this.profile.skills = [];
                        });
                },
                fetchResume() {
                    fetch('alumni_profile_data?action=resume')
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.resume) {
                                this.resumeData = data.resume;
                                this.profile.resume = { name: data.resume.file_name, url: 'uploads/resumes/' + data.resume.file_name };
                            } else {
                                this.resumeData = { resume_id: null, file_name: '', uploaded_at: '' };
                                this.profile.resume = null;
                            }
                        });
                },
                saveResume() {
                    if (this.editResumeFile) {
                        const formData = new FormData();
                        formData.append('resume', this.editResumeFile);
                        fetch('alumni_profile_data?action=insertResume', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.showNotification('Resume uploaded!', 'success');
                                this.fetchResume();
                                this.closeResumeModal();
                            } else {
                                this.showNotification(data.message || 'Failed to upload resume.', 'error');
                            }
                        })
                        .catch(() => {
                            this.showNotification('Failed to upload resume.', 'error');
                        });
                    }
                },
                changeResume() {
                    if (this.editResumeFile && this.resumeData.resume_id) {
                        const formData = new FormData();
                        formData.append('resume', this.editResumeFile);
                        fetch('alumni_profile_data?action=changeResume', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.showNotification('Resume updated!', 'success');
                                this.fetchResume();
                                this.closeResumeModal();
                            } else {
                                this.showNotification(data.message || 'Failed to update resume.', 'error');
                            }
                        })
                        .catch(() => {
                            this.showNotification('Failed to update resume.', 'error');
                        });
                    }
                },
                deleteResume() {
                    if (this.resumeData.resume_id) {
                        fetch('alumni_profile_data?action=deleteResume', {
                            method: 'POST'
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                this.showNotification('Resume deleted!', 'success');
                                this.fetchResume();
                            } else {
                                this.showNotification(data.message || 'Failed to delete resume.', 'error');
                            }
                            this.showDeleteResumeModal = false;
                        })
                        .catch(() => {
                            this.showNotification('Failed to delete resume.', 'error');
                            this.showDeleteResumeModal = false;
                        });
                    }
                },
                openDeleteResumeModal() {
                    this.showDeleteResumeModal = true;
                },
                closeDeleteResumeModal() {
                    this.showDeleteResumeModal = false;
                },
                fetchProfilePic() {
                    fetch('alumni_profile_data?action=fetchProfilePic')
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.file_name) {
                                this.profilePicData.file_name = data.file_name;
                                this.profile.image = 'uploads/profile_picture/' + data.file_name;
                            } else {
                                this.profilePicData.file_name = '';
                                this.profile.image = '';
                            }
                        });
                },
                saveProfilePic() {
                    if (!this.newPhotoFile) {
                        this.showNotification('No file selected.', 'error');
                        console.log('No file selected for upload');
                        return;
                    }
                    const formData = new FormData();
                    formData.append('profile_pic', this.newPhotoFile);
                    // Use update_profile_pic.php if a profile picture exists, otherwise use insert_profile_pic.php
                    const endpoint = this.profilePicData.file_name ? 'alumni_profile_data?action=updateProfilePic' : 'alumni_profile_data?action=insertProfilePic';
                    console.log('Uploading to:', endpoint);
                    fetch(endpoint, {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        console.log('Server response:', data);
                        if (data.success) {
                            this.showNotification('Profile photo updated!', 'success');
                            this.fetchProfilePic();
                            this.closePhotoModal();
                        } else {
                            this.showNotification(data.message || 'Failed to update photo.', 'error');
                        }
                    })
                    .catch((err) => {
                        this.showNotification('Failed to update photo.', 'error');
                        console.log('Fetch error:', err);
                    });
                },
                deleteProfilePic() {
                    fetch('alumni_profile_data?action=deleteProfilePic', {
                        method: 'POST'
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.showNotification('Profile photo deleted!', 'success');
                            this.fetchProfilePic();
                        } else {
                            this.showNotification(data.message || 'Failed to delete photo.', 'error');
                        }
                        this.showDeleteProfilePicModal = false;
                    })
                    .catch(() => {
                        this.showNotification('Failed to delete photo.', 'error');
                        this.showDeleteProfilePicModal = false;
                    });
                },
                openDeleteProfilePicModal() {
                    this.showDeleteProfilePicModal = true;
                },
                closeDeleteProfilePicModal() {
                    this.showDeleteProfilePicModal = false;
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
                async generateResumePDF() {
                    await LibLoader.ensureJsPDF();
                    const doc = new window.jspdf.jsPDF({
                        unit: 'pt',
                        format: 'a4',
                        compress: true
                    });

                    // Constants
                    const margin = 40;
                    const pageWidth = doc.internal.pageSize.getWidth();
                    const pageHeight = doc.internal.pageSize.getHeight();
                    const contentWidth = pageWidth - (margin * 2);
                    const lineHeight = 16;
                    const sectionGap = 24;
                    const smallSectionGap = 12;
                    
                    // Color Scheme
                    const blueColor = '#0078D7';
                    const darkText = '#333333';
                    const lightText = '#666666';
                    
                    // Font settings
                    const fontBold = 'helvetica', fontNormal = 'helvetica';
                    const fontItalic = 'helvetica';
                    
                    // Current position
                    let y = margin;
                    
                    // Profile picture handling
                    const p = this.profile;
                    let profilePicPromise = Promise.resolve(null);
                    
                    if (this.profilePicData.file_name) {
                        profilePicPromise = fetch('uploads/profile_picture/' + this.profilePicData.file_name)
                            .then(r => {
                                if (!r.ok) throw new Error('Image not found');
                                return r.blob();
                            })
                            .then(blob => new Promise(res => {
                                const reader = new FileReader();
                                reader.onload = () => res(reader.result);
                                reader.readAsDataURL(blob);
                            }))
                            .catch(() => null);
                    }
                    
                    profilePicPromise.then(imgData => {
                        // Date formatting function
                        const formatDate = (dateString) => {
                            if (!dateString) return '';
                            const date = new Date(dateString);
                            if (isNaN(date)) return dateString;
                            
                            const options = { year: 'numeric', month: 'long', day: 'numeric' };
                            return date.toLocaleDateString('en-US', options);
                        };

                    // ===== HEADER SECTION =====

                    // Picture in box on right side (top-aligned)
                    if (imgData) {
                        try {
                            // Draw picture box
                            doc.setDrawColor(200, 200, 200);
                            doc.setFillColor(255, 255, 255);
                            doc.rect(pageWidth - margin - 100, margin, 100, 100, 'FD');
                            
                            // Add image to box
                            doc.addImage(imgData, 'JPEG', 
                                pageWidth - margin - 95,
                                margin + 5,
                                90,
                                90,
                                undefined, 'MEDIUM');
                        } catch (e) {
                            console.error('Error adding profile image:', e);
                        }
                    }

                        // Name in ALL CAPS on left side (aligned with top of picture)
                        doc.setFontSize(25);
                        doc.setTextColor(darkText);
                        doc.setFont(fontBold, 'bold');
                        const fullName = `${p.first_name || ''} ${p.middle_name || ''} ${p.last_name || ''}`.trim().toUpperCase();
                        doc.text(fullName, margin, y + 30); // Adjusted to align with picture top
                        y += lineHeight + 30; // Adjusted spacing

                        // Add margin above personal details
                        y += 10; // Extra space between name and details

                        // Personal details below name (without icons)
                        doc.setFontSize(13);
                        doc.setFont(fontNormal, 'normal');
                        doc.setTextColor(darkText);

                        const personalDetails = [];
                        if (p.birthdate) personalDetails.push(formatDate(p.birthdate));
                        if (p.city || p.province) personalDetails.push(`${p.city || ''}${p.city && p.province ? ', ' : ''}${p.province || ''}`);
                        if (p.email) personalDetails.push(p.email);
                        if (p.contact) personalDetails.push(p.contact);

                        personalDetails.forEach(detail => {
                            doc.text(detail, margin, y);
                            y += lineHeight;
                        });

                        // Adjust final spacing after header
                        y += sectionGap - 10; // Reduced space to compensate for earlier addition
                        
                        // Blue separator line
                        doc.setDrawColor(blueColor);
                        doc.setLineWidth(2);
                        doc.line(margin, y, pageWidth - margin, y);
                        y += sectionGap;
                        
                        // ===== PERSONAL DETAILS SECTION =====
                        doc.setFontSize(14);
                        doc.setTextColor(blueColor);
                        doc.setFont(fontBold, 'bold');
                        doc.text('PERSONAL DETAILS', margin, y);
                        y += lineHeight + 10;
                        
                        doc.setFontSize(10);
                        doc.setFont(fontNormal, 'normal');
                        doc.setTextColor(darkText);
                        
                        const details = [
                            { label: 'Birthdate', value: formatDate(p.birthdate), bold: true },
                            { label: 'Gender', value: p.gender, bold: true },
                            { label: 'Civil Status', value: p.civil_status, bold: true },
                            { label: 'Phone', value: p.contact, bold: true },
                            { label: 'Email', value: p.email, bold: true },
                            { label: 'Address', value: `${p.city || ''}${p.city && p.province ? ', ' : ''}${p.province || ''}`, bold: true }
                        ].filter(item => item.value);
                        
                        details.forEach(detail => {
                            if (detail.bold) {
                                doc.setFont(fontBold, 'bold');
                                doc.text(`${detail.label}:`, margin, y);
                                const labelWidth = doc.getStringUnitWidth(`${detail.label}: `) * doc.internal.getFontSize() / doc.internal.scaleFactor;
                                doc.setFont(fontNormal, 'normal');
                                doc.text(detail.value, margin + labelWidth, y);
                            } else {
                                doc.text(`${detail.label}: ${detail.value}`, margin, y);
                            }
                            y += lineHeight;
                        });
                        
                        y += sectionGap;
                        
                        // Blue separator line
                        doc.setDrawColor(blueColor);
                        doc.setLineWidth(2);
                        doc.line(margin, y, pageWidth - margin, y);
                        y += sectionGap;
                        
                        // ===== EDUCATION SECTION =====
                        doc.setFontSize(14);
                        doc.setTextColor(blueColor);
                        doc.setFont(fontBold, 'bold');
                        doc.text('EDUCATION', margin, y);
                        y += lineHeight + 10;
                        
                        doc.setFontSize(12); // Increased from 10
                        doc.setFont(fontNormal, 'normal');
                        doc.setTextColor(darkText);
                        
                        if (Array.isArray(p.education) && p.education.length > 0) {
                            p.education.forEach(edu => {
                                // Degree and School
                                doc.setFont(fontBold, 'bold');
                                doc.text(`${edu.degree || 'Unspecified Degree'}`, margin, y);
                                y += lineHeight;
                                
                                doc.setFont(fontNormal, 'normal');
                                doc.text(`${edu.school || 'Unspecified School'}`, margin, y);
                                y += lineHeight;
                                
                                // Duration
                                doc.setFont(fontItalic, 'italic');
                                const startDate = formatDate(edu.start_date);
                                const endDate = edu.current ? 'Present' : formatDate(edu.end_date);
                                const eduDuration = `${startDate || '?'} - ${endDate || '?'}`;
                                doc.text(eduDuration, margin, y);
                                y += lineHeight + smallSectionGap;
                                
                                doc.setFont(fontNormal, 'normal');
                            });
                        } else {
                            doc.text('No education information', margin, y);
                            y += lineHeight;
                        }
                        
                        y += sectionGap;
                        
                        // Blue separator line
                        doc.setDrawColor(blueColor);
                        doc.setLineWidth(2);
                        doc.line(margin, y, pageWidth - margin, y);
                        y += sectionGap;
                        
                        // ===== WORK EXPERIENCE SECTION =====
                        doc.setFontSize(16); // Increased from 14
                        doc.setTextColor(blueColor);
                        doc.setFont(fontBold, 'bold');
                        doc.text('WORK EXPERIENCE', margin, y);
                        y += lineHeight + 10;
                        
                        doc.setFontSize(12); // Increased from 10
                        doc.setFont(fontNormal, 'normal');
                        doc.setTextColor(darkText);
                        
                        if (Array.isArray(p.experiences) && p.experiences.length > 0) {
                            p.experiences.forEach(exp => {
                                // Company and Position
                                doc.setFont(fontBold, 'bold');
                                doc.text(`${exp.title || 'Unspecified Position'}`, margin, y);
                                y += lineHeight;
                                
                                doc.setFont(fontNormal, 'normal');
                                doc.text(`${exp.company || 'Unspecified Company'}`, margin, y);
                                y += lineHeight;
                                
                                // Duration
                                doc.setFont(fontItalic, 'italic');
                                const startDate = formatDate(exp.start_date);
                                const endDate = exp.current ? 'Present' : formatDate(exp.end_date);
                                const expDuration = `${startDate || '?'} - ${endDate || '?'}`;
                                doc.text(expDuration, margin, y);
                                y += lineHeight;
                                
                                // Description
                                if (exp.description) {
                                    doc.setFont(fontNormal, 'normal');
                                    const splitDesc = doc.splitTextToSize(exp.description, contentWidth);
                                    splitDesc.forEach(text => {
                                        doc.text(text, margin, y);
                                        y += lineHeight;
                                    });
                                }
                                
                                y += smallSectionGap;
                            });
                        } else {
                            doc.text('No work experience listed', margin, y);
                            y += lineHeight;
                        }
                        
                        y += sectionGap;
                        
                        // Blue separator line
                        doc.setDrawColor(blueColor);
                        doc.setLineWidth(2);
                        doc.line(margin, y, pageWidth - margin, y);
                        y += sectionGap;
                        
                        // ===== SKILLS SECTION =====
                        doc.setFontSize(16); // Increased from 14
                        doc.setTextColor(blueColor);
                        doc.setFont(fontBold, 'bold');
                        doc.text('SKILLS', margin, y);
                        y += lineHeight + 10;
                        
                        doc.setFontSize(12); // Increased from 10
                        doc.setFont(fontNormal, 'normal');
                        doc.setTextColor(darkText);
                        
                        if (Array.isArray(p.skills) && p.skills.length > 0) {
                            p.skills.forEach(skill => {
                                doc.text(`• ${skill.name}`, margin, y);
                                y += lineHeight;
                                
                                if (skill.certificate) {
                                    doc.setFontSize(10);
                                    doc.setTextColor(lightText);
                                    doc.text(`  (Certified: ${skill.certificate})`, margin + 10, y);
                                    doc.setFontSize(12);
                                    doc.setTextColor(darkText);
                                    y += lineHeight - 2;
                                }
                            });
                        } else {
                            doc.text('No skills listed', margin, y);
                            y += lineHeight;
                        }
                        
                        // Save PDF (no footer)
                        const timestamp = new Date().toISOString().slice(0, 19).replace(/[-:T]/g, '');
                        doc.save(`Resume_${fullName.replace(/\s+/g, '_')}_${timestamp}.pdf`);
                    }).catch(err => {
                        console.error('Error generating PDF:', err);
                    });
                },
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
                document.addEventListener('keydown', this.handleModalEscapeKey);
                this.fetchSuccessStories();
                fetch('/signup?action=campuses')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.campuses = data.campuses || [];
                        }
                    });

                this.fetchUnreadNotifications();
  
                 // Optional: Poll for new notifications every 30 seconds
                this.notificationInterval = setInterval(this.fetchUnreadNotifications, 30000);

                // First fetch the alumni details separately to ensure it completes
        
                
                // Fetch alumni profile via AJAX
                Promise.all([
                    fetch('alumni_profile_data?action=details').then(res => res.json()),
                    fetch('https://psgc.gitlab.io/api/provinces/')
                        .then(res => res.json())
                        .catch(() => [{ name: 'Laguna', code: '0434' }]),
                    fetch('alumni_profile_data?action=skills').then(res => res.json()),
                    fetch('alumni_profile_data?action=education').then(res => res.json()),
                    fetch('alumni_profile_data?action=experience').then(res => res.json()),
                ]).then(([profileData, provincesData, skillsData, educationData, experienceData]) => {
                    // Handle profile data
                    if (profileData.success && profileData.profile) {
                        Object.assign(this.profile, profileData.profile);
                    }
                    
                    // Ensure provinces is always an array
                    this.provinces = Array.isArray(provincesData) 
                        ? provincesData.map(p => ({ name: p.name, code: p.code }))
                        : [{ name: 'Laguna', code: '0434' }];
                    
                    // Set province and fetch cities if available
                    if (this.profile.province) {
                        this.editProfileData.province = this.profile.province;
                        this.fetchCities();
                    }
                    
                    // Handle other data...
                    if (skillsData.success) this.profile.skills = skillsData.skills || [];
                    if (educationData.success) this.profile.education = educationData.education || [];
                    if (experienceData.success) this.profile.experiences = experienceData.experience || [];
                    
                }).catch(error => {
                    console.error('Error fetching data:', error);
                    // Initialize with default values
                    this.provinces = [{ name: 'Laguna', code: '0434' }];
                }).finally(() => {
                    this.loading = false;
                    
                    // Fetch additional data
                    this.fetchSchools();
                    this.fetchDegrees();
                    this.fetchResume();
                    this.fetchProfilePic();
                });


                fetch('alumni_profile_data?action=details')
                .then(res => res.json())
                .then(profileData => {
                    if (profileData.success && profileData.profile) {
                        // Assign profile data directly to this.profile
                        Object.assign(this.profile, profileData.profile);
                        
                        // Now fetch the other data in parallel
                        return Promise.all([
                            fetch('https://psgc.gitlab.io/api/provinces/')
                                .then(res => res.json())
                                .catch(() => [{ name: 'Laguna', code: '0434' }]),
                            this.fetchEducation(),
                            this.fetchExperience(),
                            this.fetchSkills(),
                            this.fetchCertifications(),
                            this.fetchResume(),
                            this.fetchProfilePic()
                        ]);
                    }
                })
                .then(([provincesData]) => {
                    this.provinces = provincesData.map ? provincesData.map(p => ({ name: p.name, code: p.code })) : provincesData;
                    if (this.profile.province) {
                        this.editProfileData.province = this.profile.province;
                        this.fetchCities();
                    }
                })
                .catch(error => {
                    console.error('Error fetching data:', error);
                    // Initialize with empty data if fetch fails
                    this.provinces = [{ name: 'Laguna', code: '0434' }];
                })
                .finally(() => {
                    this.loading = false;
                });
            },
            beforeUnmount() {
                // Clean up the interval
                if (this.notificationInterval) {
                  clearInterval(this.notificationInterval);
                }
                document.removeEventListener('keydown', this.handleModalEscapeKey);
              }
        }).mount('#app');