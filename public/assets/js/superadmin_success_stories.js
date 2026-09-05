const { createApp } = Vue;

createApp({
    data() {
        return {
            sidebarActive: window.innerWidth >= 768,
            companiesDropdownOpen: false,
            systemDropdownOpen: false,
            profileDropdownOpen: false,
            darkMode: localStorage.getItem('darkMode') === 'true' || 
                     (localStorage.getItem('darkMode') === null && 
                      window.matchMedia('(prefers-color-scheme: dark)').matches),
            showLogoutModal: false,
            showDeleteModal: false, // Added delete modal state
            isMobile: window.innerWidth < 768,
            notifications: [],
            notificationId: 0,
            
            // Success stories specific data
            stories: [],
            showStoryModal: false,
            showViewStoryModal: false,
            editingStory: null,
            viewingStory: {},
            storyToDelete: null, // Added to store story to be deleted
            storyForm: {
                user_id: '',
                title: '',
                content: '',
                status: 'published'
            },
            filters: {
                search: '',
                status: '',
                dateRange: '',
                author: ''
            },
            isLoading: false,
            profile: {
                profile_pic: '',
                name: ''
            },
            alumniList: []
        }
    },
    mounted() {
        this.applyDarkMode();
        window.addEventListener('resize', this.handleResize);
        document.addEventListener('click', this.handleClickOutsideProfile);
        this.fetchProfile();
        this.fetchAlumniList().then(() => {
            this.fetchStories();
        });
    },
    beforeUnmount() {
        window.removeEventListener('resize', this.handleResize);
    },
    watch: {
        darkMode(val) {
            this.applyDarkMode();
        }
    },
    computed: {
        // Filter stories based on search and filters
        filteredStories() {
            let filtered = this.stories;
            
            // Filter by search query
            if (this.filters.search) {
                const query = this.filters.search.toLowerCase();
                filtered = filtered.filter(story => 
                    story.title.toLowerCase().includes(query) ||
                    story.content.toLowerCase().includes(query) ||
                    (story.author_full_name && story.author_full_name.toLowerCase().includes(query))
                );
            }
            
            // Filter by status
            if (this.filters.status) {
                filtered = filtered.filter(story => story.status === this.filters.status);
            }
            
            // Filter by author
            if (this.filters.author) {
                filtered = filtered.filter(story => story.user_id == this.filters.author);
            }
            
            // Filter by date range
            if (this.filters.dateRange) {
                const now = new Date();
                filtered = filtered.filter(story => {
                    const storyDate = new Date(story.created_at);
                    
                    switch(this.filters.dateRange) {
                        case 'today':
                            return storyDate.toDateString() === now.toDateString();
                        case 'week':
                            const weekStart = new Date(now);
                            weekStart.setDate(now.getDate() - now.getDay());
                            weekStart.setHours(0, 0, 0, 0);
                            return storyDate >= weekStart;
                        case 'lastWeek':
                            const lastWeekStart = new Date(now);
                            lastWeekStart.setDate(now.getDate() - now.getDay() - 7);
                            lastWeekStart.setHours(0, 0, 0, 0);
                            const lastWeekEnd = new Date(lastWeekStart);
                            lastWeekEnd.setDate(lastWeekStart.getDate() + 6);
                            lastWeekEnd.setHours(23, 59, 59, 999);
                            return storyDate >= lastWeekStart && storyDate <= lastWeekEnd;
                        case 'month':
                            return storyDate.getMonth() === now.getMonth() && 
                                   storyDate.getFullYear() === now.getFullYear();
                        case 'lastMonth':
                            const lastMonth = new Date(now);
                            lastMonth.setMonth(now.getMonth() - 1);
                            return storyDate.getMonth() === lastMonth.getMonth() && 
                                   storyDate.getFullYear() === lastMonth.getFullYear();
                        case 'year':
                            return storyDate.getFullYear() === now.getFullYear();
                        case 'lastYear':
                            return storyDate.getFullYear() === now.getFullYear() - 1;
                        default:
                            return true;
                    }
                });
            }
            
            return filtered;
        }
    },
    methods: {
        // Sidebar and dropdowns
        toggleSidebar() {
            this.sidebarActive = !this.sidebarActive;
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

        // Notifications
        showNotification(message, type = 'success') {
            const id = this.notificationId++;
            this.notifications.push({ id, type, message });
            setTimeout(() => this.removeNotification(id), 3000);
        },
        removeNotification(id) {
            this.notifications = this.notifications.filter(n => n.id !== id);
        },
        
        // Fetch profile data
        async fetchProfile() {
            try {
                const response = await fetch('admin_profile?action=details', { credentials: 'include' }); // shared legacy endpoint, still relied on by other pages
                const data = await response.json();
                if (data.success) {
                    this.profile = data.profile;
                } else {
                    console.error('Error fetching profile:', data.message);
                    this.profile = { profile_pic: null, name: 'Admin' };
                }
            } catch (error) {
                console.error('Error fetching profile:', error);
                this.profile = { profile_pic: null, name: 'Admin' };
            }
        },
        
        async fetchAlumniList() {
            try {
                const response = await fetch('/superadmin_success_stories?action=authors', { credentials: 'include' });
                const data = await response.json();
                
                if (data.success) {
                    this.alumniList = data.alumni.map(alumni => ({
                        user_id: alumni.user_id,
                        full_name: `${alumni.first_name} ${alumni.middle_name ? alumni.middle_name + ' ' : ''}${alumni.last_name}`,
                        email: alumni.email,
                        profile_picture: alumni.profile_pic
                    }));
                    return true;
                } else {
                    this.showNotification(data.message || 'Failed to load alumni list', 'error');
                    return false;
                }
            } catch (error) {
                console.error('Error fetching alumni list:', error);
                this.showNotification('Error loading alumni list', 'error');
                return false;
            }
        },
        
        // Fetch success stories
        async fetchStories() {
            this.isLoading = true;
            try {
                const response = await fetch('/superadmin_success_stories?action=list', { credentials: 'include' });
                const data = await response.json();
                
                if (data.success) {
                    this.stories = data.stories.map(story => {
                        // Find the alumni author information
                        const author = this.alumniList.find(a => a.user_id == story.user_id);
                        
                        return {
                            story_id: story.story_id,
                            user_id: story.user_id,
                            title: story.title,
                            content: story.content,
                            status: story.status,
                            created_at: story.created_at,
                            updated_at: story.updated_at,
                            author_full_name: author ? author.full_name : 'Unknown Author',
                            author_email: author ? author.email : '',
                            profile_picture: author ? author.profile_picture : null
                        };
                    });
                } else {
                    this.showNotification(data.message || 'Failed to load success stories', 'error');
                }
            } catch (error) {
                console.error('Error fetching stories:', error);
                this.showNotification('Error loading success stories', 'error');
            } finally {
                this.isLoading = false;
            }
        },
        
        // Clear all filters
        clearFilters() {
            this.filters = {
                search: '',
                status: '',
                dateRange: '',
                author: ''
            };
        },
        
        // Open add story modal
        openAddStoryModal() {
            this.editingStory = null;
            this.storyForm = {
                user_id: '',
                title: '',
                content: '',
                status: 'published'
            };
            this.showStoryModal = true;
        },
        
        // Edit story
        editStory(story) {
            this.editingStory = story;
            this.storyForm = { 
                user_id: story.user_id,
                title: story.title,
                content: story.content,
                status: story.status
            };
            this.showStoryModal = true;
        },
        
        // View story
        viewStory(story) {
            this.viewingStory = story;
            this.showViewStoryModal = true;
        },
        
        // Close modals
        closeStoryModal() {
            this.showStoryModal = false;
            this.editingStory = null;
        },
        
        closeViewStoryModal() {
            this.showViewStoryModal = false;
        },
        
        // Add new story
        async addStory() {
            try {
                const response = await fetch('/superadmin_success_stories?action=store', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.storyForm),
                    credentials: 'include'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('Success story added!', 'success');
                    this.closeStoryModal();
                    this.fetchStories();
                } else {
                    this.showNotification(data.message || 'Failed to add story', 'error');
                }
            } catch (error) {
                this.showNotification('Error adding success story', 'error');
            }
        },
        
        // Update story
        async updateStory() {
            try {
                const response = await fetch('/superadmin_success_stories?action=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        id: this.editingStory.story_id, 
                        ...this.storyForm 
                    }),
                    credentials: 'include'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('Success story updated!', 'success');
                    this.closeStoryModal();
                    this.fetchStories();
                } else {
                    this.showNotification(data.message || 'Failed to update story', 'error');
                }
            } catch (error) {
                this.showNotification('Error updating success story', 'error');
            }
        },
        
        // Update story status
        async updateStoryStatus(story, status) {
            try {
                const response = await fetch('/superadmin_success_stories?action=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        id: story.story_id,
                        user_id: story.user_id,
                        title: story.title,
                        content: story.content,
                        status: status
                    }),
                    credentials: 'include'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification(`Story ${status.toLowerCase()} successfully!`, 'success');
                    this.fetchStories();
                } else {
                    this.showNotification(data.message || 'Failed to update story status', 'error');
                }
            } catch (error) {
                this.showNotification('Error updating story status', 'error');
            }
        },
        
        // Delete story - shows confirmation modal
        deleteStory(story) {
            this.storyToDelete = story;
            this.showDeleteModal = true;
        },
        
        // Confirm and execute story deletion
        async confirmDeleteStory() {
            if (!this.storyToDelete) return;
            
            try {
                const response = await fetch('/superadmin_success_stories?action=destroy', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: this.storyToDelete.story_id }),
                    credentials: 'include'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showNotification('Success story deleted!', 'success');
                    this.fetchStories();
                } else {
                    this.showNotification(data.message || 'Failed to delete story', 'error');
                }
            } catch (error) {
                this.showNotification('Error deleting success story', 'error');
            } finally {
                this.showDeleteModal = false;
                this.storyToDelete = null;
            }
        },
        
        // Format date
        formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        },
        
        // Get profile picture URL
        getProfilePictureUrl(profilePicture) {
            if (!profilePicture) return null;
            return `/uploads/profile_picture/${encodeURIComponent(profilePicture)}`;
        }
    }
}).mount('#app');