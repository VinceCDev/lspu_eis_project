const { createApp, reactive, ref } = Vue;
createApp({
    data() {
        return {
            form: {
                email: '',
                secondary_email: '',
                password: '',
                current_password: '',
                first_name: '',
                middle_name: '',
                last_name: '',
                birthdate: '',
                contact: '',
                gender: '',
                civil_status: '',
                city: '',
                province: '',
                year_graduated: '',
                campus_id: '',
                college: '',
                course: '',
                verification_documents: null
            },
            showPassword: false,
            showConfirmPassword: false,
            campuses: [],
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
            passwordValid: {
                length: false,
                upper: false,
                lower: false,
                number: false,
                special: false
            },
            loading: false,
            agreeToDisclaimer: false, // Add this line
            showDisclaimerModal: false, // Add this line to control modal visibility
            message: '',
            success: false,
            provinces: [],
            cities: [],
            fileError: '',
            allowedTypes: ['image/jpeg', 'image/png', 'application/pdf'],
            maxSize: 5 * 1024 * 1024
        }
    },
    mounted() {
        this.fetchProvinces();
        this.fetchCampuses();
    },
    watch: {
        'form.campus_id'() {
            this.form.college = '';
            this.form.course = '';
        },
        'form.college'() {
            this.form.course = '';
        }
    },
    computed: {
        collegeOptions() {
            const campusName = this.campusNameById(this.form.campus_id);
            return campusName && this.campusCollegeCourses[campusName]
                ? Object.keys(this.campusCollegeCourses[campusName])
                : [];
        },
        courseOptions() {
            const campusName = this.campusNameById(this.form.campus_id);
            return campusName && this.campusCollegeCourses[campusName] && this.campusCollegeCourses[campusName][this.form.college]
                ? this.campusCollegeCourses[campusName][this.form.college]
                : [];
        }
    },
    methods: {
        togglePasswordVisibility() {
            this.showPassword = !this.showPassword;
        },
        toggleConfirmPasswordVisibility() {
            this.showConfirmPassword = !this.showConfirmPassword;
        },
        campusNameById(campusId) {
            const campus = this.campuses.find(c => String(c.campus_id) === String(campusId));
            return campus ? campus.name : null;
        },
        async fetchCampuses() {
            try {
                const res = await fetch('/signup?action=campuses');
                const data = await res.json();
                if (data.success) {
                    this.campuses = data.campuses;
                }
            } catch (e) {
                this.campuses = [];
            }
        },
        async fetchProvinces() {
            // Use a public PSGC API or static JSON fallback
            try {
                const res = await fetch('https://psgc.gitlab.io/api/provinces/');
                const data = await res.json();
                this.provinces = data.map(p => ({ name: p.name, code: p.code }));
            } catch (e) {
                this.provinces = [{ name: 'Laguna', code: '0434' }]; // fallback
            }
        },
        validateFile(event) {
            const fileInput = event.target;
            const files = fileInput.files;
            this.fileError = '';
            
            // Check if any file is selected
            if (!files || files.length === 0) {
                this.form.verification_documents = null;
                return true;
            }
            
            // Check each selected file
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                // Validate file type
                if (!this.allowedTypes.includes(file.type)) {
                    this.fileError = 'Invalid file type. Only JPG, PNG, and PDF files are allowed.';
                    fileInput.value = '';
                    this.form.verification_documents = null;
                    return false;
                }
                
                // Validate file size
                if (file.size > this.maxSize) {
                    this.fileError = `File "${file.name}" is too large. Maximum size is 5MB.`;
                    fileInput.value = '';
                    this.form.verification_documents = null;
                    return false;
                }
                
                // Validate file name (prevent path traversal attacks)
                if (file.name.includes('..') || file.name.includes('/') || file.name.includes('\\')) {
                    this.fileError = 'Invalid file name.';
                    fileInput.value = '';
                    this.form.verification_documents = null;
                    return false;
                }
            }
            
            // If all validations pass
            this.form.verification_documents = files[0]; // Store first file only
            return true;
        },
        async fetchCities() {
            this.cities = [];
            if (!this.form.province) return;
            try {
                // Find province code
                const province = this.provinces.find(p => p.name === this.form.province);
                if (!province) return;
                const res = await fetch(`https://psgc.gitlab.io/api/provinces/${province.code}/cities-municipalities/`);
                const data = await res.json();
                this.cities = data.map(c => ({ name: c.name, code: c.code }));
            } catch (e) {
                if (this.form.province === 'Laguna') {
                    this.cities = [
                        { name: 'San Pablo City', code: '043404' },
                        { name: 'Calamba City', code: '043405' },
                        { name: 'Santa Cruz', code: '043406' },
                        // ... add more as needed
                    ];
                }
            }
        },
        handleFileUpload(e) {
            if (this.validateFile(e)) {
                // File is valid, proceed with your existing logic
                this.form.verification_documents = e.target.files[0];
            }
        },
        validatePassword() {
            const p = this.form.password;
            this.passwordValid.length = p.length >= 8;
            this.passwordValid.upper = /[A-Z]/.test(p);
            this.passwordValid.lower = /[a-z]/.test(p);
            this.passwordValid.number = /[0-9]/.test(p);
            this.passwordValid.special = /[!@#$%^&*(),.?":{}|<>]/.test(p);
        },
        async submitForm() {
            this.message = '';
            this.success = false;
            this.loading = true;
            // Client-side validation
            if (!this.form.email || !this.form.password || !this.form.current_password || !this.form.first_name || !this.form.last_name || !this.form.birthdate || !this.form.contact || !this.form.gender || !this.form.civil_status || !this.form.city || !this.form.province || !this.form.year_graduated || !this.form.campus_id || !this.form.college || !this.form.course || !this.form.verification_documents) {
                this.message = 'Please fill in all required fields.';
                this.success = false;
                this.loading = false;
                return;
            }
            if (this.form.password !== this.form.current_password) {
                this.message = 'Passwords do not match.';
                this.success = false;
                this.loading = false;
                return;
            }
            if (!this.passwordValid.length || !this.passwordValid.upper || !this.passwordValid.lower || !this.passwordValid.number || !this.passwordValid.special) {
                this.message = 'Password does not meet requirements.';
                this.success = false;
                this.loading = false;
                return;
            }
            if (!this.form.verification_documents) {
                this.message = 'Please upload a valid verification document.';
                this.success = false;
                this.loading = false;
                return;
            }
            if (!this.agreeToDisclaimer) {
                this.message = 'You must agree to the Data Privacy Policy and Disclaimer to register.';
                this.success = false;
                this.loading = false;
                return;
            }
            // Prepare form data
            const formData = new FormData();
            for (const key in this.form) {
                if (key === 'verification_documents') {
                    formData.append(key, this.form[key]);
                } else {
                    formData.append(key, this.form[key]);
                }
            }
            formData.append('agree_to_disclaimer', this.agreeToDisclaimer ? '1' : '0');
            try {
                const response = await fetch('/signup?action=register', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                this.message = data.message;
                this.success = data.success;
                if (data.success) {
                    this.form = {
                        email: '', secondary_email: '', password: '', current_password: '', first_name: '', middle_name: '', last_name: '', birthdate: '', contact: '', gender: '', civil_status: '', city: '', province: '', year_graduated: '', campus_id: '', college: '', course: '', verification_documents: null
                    };
                    this.passwordValid = { length: false, upper: false, lower: false, number: false, special: false };
                }
            } catch (e) {
                this.message = 'An error occurred. Please try again.';
                this.success = false;
            }
            this.loading = false;
        },
        openDisclaimerModal() {
            this.showDisclaimerModal = true;
        },
        closeDisclaimerModal() {
            this.showDisclaimerModal = false;
        }
    }
}).mount('#signupApp');