const { createApp } = Vue;
        createApp({
            data() {
                return {
                    loading: true,
                    showTutorialButton: true, // Start as false, will be updated after check
                    showWelcomeModal: false, // Start as false
                    currentWelcomeSlide: 0,
                    welcomeSlides: [
                        { title: "Welcome", content: "intro" },
                        { title: "Navigation", content: "navigation" },
                        { title: "Job Search", content: "job_search" },
                        { title: "Profile", content: "profile" }
                    ],
                    notificationIcons: {
                        application: 'fas fa-briefcase',
                        interview: 'fas fa-calendar-check',
                        message: 'fas fa-envelope',
                        profile: 'fas fa-user-tie',
                        system: 'fas fa-cog',
                        job_match: 'fas fa-bolt'
                    },
                    darkMode: false,
                    mobileMenuOpen: false,
                    profileDropdownOpen: false,
                    profilePicData: { file_name: '' },
                    profile: { name: '' },
                    showLogoutModal: false,
                    unreadNotifications: 0,
                    notifications: [],
                    mobileProfileDropdownOpen: false
                }
            },
            computed: {
                allRead() {
                    return this.notifications.every(n => n.read);
                }
            },
            methods: {
                handleClickOutsideProfile(event) {
                    if (this.profileDropdownOpen && !event.target.closest('.profile-dropdown-wrapper')) {
                        this.profileDropdownOpen = false;
                    }
                },
                async fetchNotifications() {
                    try {
                        this.loading = true;
                        // Fetch notifications for the logged-in user
                        const res = await fetch('notification?action=list');
                        const data = await res.json();
                        if (data.success) {
                            this.notifications = data.notifications;
                        } else {
                            this.notifications = [];
                        }
                    } catch (error) {
                        this.notifications = [];
                    } finally {
                        this.loading = false;
                    }
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
                formatTime(date) {
                    const now = new Date();
                    const d = new Date(date);
                    const diffInSeconds = Math.floor((now - d) / 1000);
                    if (diffInSeconds < 60) return 'Just now';
                    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
                    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
                    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} days ago`;
                    return d.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric'
                    });
                },
                markAsRead(id) {
                    const notification = this.notifications.find(n => n.id === id);
                    if (notification) {
                        notification.read = true;
                    }
                },
                async markNotificationAsRead(notification) {
                    // Remove the early return for read notifications
                    // if (notification.read) return;
                    
                    // Only mark as read if it's not already read
                    if (!notification.read) {
                        // Optimistically mark as read
                        notification.read = true;
                        
                        try {
                            await fetch('notification?action=update', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `action=mark_one_read&id=${encodeURIComponent(notification.id)}`
                            });
                        } catch (e) {
                            console.error('Error marking notification as read:', e);
                            // Revert the read status if there was an error
                            notification.read = false;
                        }
                    }
                    
                    // Always handle redirects, regardless of read status
                    if (notification.type === 'job_match' && notification.job_id) {
                        window.location.href = `home?job_id=${notification.job_id}&from_notification=job_match`;
                    } else if (notification.type === 'application' && notification.job_id) {
                        window.location.href = `my_application?job_id=${notification.job_id}&from_notification=application`;
                    } else if (notification.type.startsWith('interview_')) {
                        window.location.href = notification.job_id
                            ? `my_application?job_id=${notification.job_id}&from_notification=interview`
                            : 'my_application';
                    } else if (notification.type === 'password') {
                        window.location.href = 'my_profile';
                    } else if (notification.type === 'message' && notification.message_id) {
                        window.location.href = `message?message_id=${notification.message_id}`;
                    }
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
                markAllAsRead() {
                    this.notifications.forEach(n => n.read = true);
                    // Update on backend using session (no user_id sent)
                    fetch('notification?action=update', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=mark_all_read`
                    });
                },
                clearAllNotifications() {
                    this.notifications = [];
                },
                applyDarkMode() {
                    const html = document.documentElement;
                    if (this.darkMode) {
                        html.classList.add('dark');
                    } else {
                        html.classList.remove('dark');
                    }
                },
                logout() {
                    window.location.href = 'logout';
                }
            },
            watch: {
                darkMode(val) {
                    localStorage.setItem('darkMode', val.toString());
                    this.applyDarkMode();
                }
            },
            mounted() {
                document.addEventListener('click', this.handleClickOutsideProfile);
                // Set dark mode on initial load
                const storedMode = localStorage.getItem('darkMode');
                this.fetchUnreadNotifications();
  
                // Optional: Poll for new notifications every 30 seconds
                this.notificationInterval = setInterval(this.fetchUnreadNotifications, 30000);
                if (storedMode !== null) {
                    this.darkMode = storedMode === 'true';
                } else {
                    this.darkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
                }
                this.applyDarkMode();
                this.fetchNotifications();
                fetch('alumni_profile_data?action=fetchProfilePic')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.file_name) {
                            this.profilePicData.file_name = data.file_name;
                        } else {
                            this.profilePicData.file_name = '';
                        }
                    });
                fetch('alumni_profile_data?action=details')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.profile) {
                            this.profile.name = `${data.profile.first_name} ${data.profile.last_name}`;
                        }
                    });
            },
            beforeUnmount() {
                // Clean up the interval
                if (this.notificationInterval) {
                  clearInterval(this.notificationInterval);
                }
              }
        }).mount('#app');