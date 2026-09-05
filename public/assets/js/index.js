const { createApp } = Vue;

        createApp({
            data() {
                return {
                    mobileMenuOpen: false,
                    notifications: [],
                    notificationId: 0,
                    darkMode: false,
                    contactForm: {
                        name: '',
                        age: '',
                        email: '',
                        message: ''
                    },
                    storiesLoading: false,
                    publishedStories: [],
                    selectedStory: null,
                    currentStorySlide: 0,
                    storiesPerSlide: 3
                }
            },
            computed: {
                storySlides() {
                    const slides = [];
                    for (let i = 0; i < this.publishedStories.length; i += this.storiesPerSlide) {
                        slides.push(this.publishedStories.slice(i, i + this.storiesPerSlide));
                    }
                    return slides;
                },
                
                totalStorySlides() {
                    return Math.ceil(this.publishedStories.length / this.storiesPerSlide);
                },
                
                maxStorySlides() {
                    return this.totalStorySlides - 1;
                }
            },
            mounted() {
                // Check for saved dark mode preference or default to light mode
                const savedMode = localStorage.getItem('darkMode');
                if (savedMode !== null) {
                    this.darkMode = savedMode === 'true';
                } else {
                    this.darkMode = false; // Default to light mode
                }
                this.applyDarkMode();
                this.fetchSuccessStories();
                this.startCarouselAutoAdvance();
                document.addEventListener('keydown', this.handleEscapeKey);
            },
            beforeUnmount() {
                // ... existing beforeUnmount code ...

                // Clean up carousel interval
                this.stopCarouselAutoAdvance();
                document.removeEventListener('keydown', this.handleEscapeKey);
            },
            methods: {
                handleEscapeKey(e) {
                    if (e.key === 'Escape' && this.selectedStory) {
                        this.selectedStory = null;
                    }
                },
                scrollToSection(sectionId) {
                    const element = document.getElementById(sectionId);
                    if (element) {
                        element.scrollIntoView({ 
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                },
                nextStorySlide() {
                    if (this.currentStorySlide < this.maxStorySlides) {
                        this.currentStorySlide++;
                    } else {
                        this.currentStorySlide = 0; // Loop back to start
                    }
                },
                
                prevStorySlide() {
                    if (this.currentStorySlide > 0) {
                        this.currentStorySlide--;
                    } else {
                        this.currentStorySlide = this.maxStorySlides; // Loop to end
                    }
                },
                
                goToStorySlide(slideIndex) {
                    this.currentStorySlide = slideIndex;
                },
                
                // Auto-advance carousel (optional)
                startCarouselAutoAdvance() {
                    if (this.publishedStories.length > this.storiesPerSlide) {
                        this.carouselInterval = setInterval(() => {
                            this.nextStorySlide();
                        }, 5000); // Change slide every 5 seconds
                    }
                },
                
                stopCarouselAutoAdvance() {
                    if (this.carouselInterval) {
                        clearInterval(this.carouselInterval);
                    }
                },
                showNotification(message, type = 'success') {
                    const id = this.notificationId++;
                    this.notifications.push({ id, type, message });
                    setTimeout(() => {
                        this.removeNotification(id);
                    }, 5000);
                },
                
                removeNotification(id) {
                    this.notifications = this.notifications.filter(n => n.id !== id);
                },
                
                toggleDarkMode() {
                    this.darkMode = !this.darkMode;
                    localStorage.setItem('darkMode', this.darkMode.toString());
                    this.applyDarkMode();
                },
                
                applyDarkMode() {
                    const html = document.documentElement;
                    if (this.darkMode) {
                        html.classList.add('dark');
                    } else {
                        html.classList.remove('dark');
                    }
                },
                
                async submitContactForm() {
                    try {
                        const response = await fetch('landing?action=contact', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify(this.contactForm)
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            this.showNotification(data.message, 'success');
                            this.contactForm = {
                                name: '',
                                age: '',
                                email: '',
                                message: ''
                            };
                        } else {
                            this.showNotification(data.message || 'Failed to send message. Please try again.', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        this.showNotification('Failed to send message. Please try again later.', 'error');
                    }
                },

                async fetchSuccessStories() {
                    this.storiesLoading = true;
                    try {
                        console.log('Fetching success stories...');
                        const response = await fetch('landing?action=publicSuccessStories');
                        console.log('Response status:', response.status);
                        
                        const data = await response.json();
                        console.log('API response:', data);
                        
                        if (data.success) {
                            this.publishedStories = data.stories;
                            console.log('Stories loaded:', this.publishedStories.length);
                        } else {
                            console.error('Failed to load success stories:', data.message);
                            this.showNotification('Failed to load success stories', 'error');
                        }
                    } catch (error) {
                        console.error('Error fetching success stories:', error);
                        this.showNotification('Error loading success stories', 'error');
                    } finally {
                        this.storiesLoading = false;
                    }
                },
                
                // View story in modal
                viewStory(story) {
                    this.selectedStory = story;
                },
                
                // Format date for display
                formatStoryDate(dateString) {
                    if (!dateString) return '';
                    return new Date(dateString).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                }
            }
        }).mount('#app');