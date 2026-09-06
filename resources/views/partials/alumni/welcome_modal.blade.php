    <div v-if="showWelcomeModal" class="fixed inset-0 z-[1000] flex items-center justify-center bg-black bg-opacity-70" role="dialog" aria-modal="true">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="bg-blue-600 text-white p-5 flex justify-between items-center">
                <h2 class="text-2xl font-bold flex items-center">
                    <i class="fas fa-graduation-cap mr-3"></i> Welcome to LSPU Alumni Portal!
                </h2>
                <button @click="closeWelcomeModal" aria-label="Close" class="text-white hover:text-blue-200 text-xl">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            
            <!-- Content with carousel -->
            <div class="flex-1 overflow-y-auto p-6">
                <!-- Carousel indicators -->
                <div class="flex justify-center mb-6">
                    <div v-for="(slide, index) in welcomeSlides" :key="index" 
                        :class="['w-3 h-3 rounded-full mx-1 cursor-pointer', 
                                currentWelcomeSlide === index ? 'bg-blue-600' : 'bg-gray-300']"
                        @click="currentWelcomeSlide = index">
                    </div>
                </div>
                
                <!-- Slide 1: Introduction -->
                <div v-if="currentWelcomeSlide === 0" class="text-center">
                    <div class="text-blue-500 text-6xl mb-6">
                        <i class="fas fa-hands-helping"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">Welcome, Alumni!</h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-6">
                        We're excited to have you here. This portal connects you with job opportunities, 
                        fellow alumni, and valuable resources from LSPU.
                    </p>
                    <div class="bg-blue-50 dark:bg-blue-900/30 p-4 rounded-lg text-left">
                        <p class="text-blue-700 dark:text-blue-300 flex items-center">
                            <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>
                            <span>Take a quick tour to learn how to make the most of your alumni portal.</span>
                        </p>
                    </div>
                </div>
                
                <!-- Slide 2: Navigation -->
                <div v-if="currentWelcomeSlide === 1" class="">
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                        <i class="fas fa-compass text-blue-500 mr-2"></i> Navigation Guide
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="text-blue-500 text-2xl mb-2">
                                <i class="fas fa-home"></i>
                            </div>
                            <h4 class="font-semibold text-gray-800 dark:text-white">Home</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Browse and search for job opportunities.</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="text-blue-500 text-2xl mb-2">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h4 class="font-semibold text-gray-800 dark:text-white">My Applications</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Track your job applications status.</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="text-blue-500 text-2xl mb-2">
                                <i class="fas fa-bell"></i>
                            </div>
                            <h4 class="font-semibold text-gray-800 dark:text-white">Notifications</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Get updates on applications and messages.</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                            <div class="text-blue-500 text-2xl mb-2">
                                <i class="fas fa-user"></i>
                            </div>
                            <h4 class="font-semibold text-gray-800 dark:text-white">Profile</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-300">Manage your personal information.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Slide 3: Job Search -->
                <div v-if="currentWelcomeSlide === 2" class="">
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                        <i class="fas fa-search text-blue-500 mr-2"></i> Finding Jobs
                    </h3>
                    <div class="space-y-4 mb-6">
                        <div class="flex items-start">
                            <div class="bg-blue-100 dark:bg-blue-900/40 p-2 rounded-full mr-3">
                                <i class="fas fa-search text-blue-600 dark:text-blue-300"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800 dark:text-white">Search & Filter</h4>
                                <p class="text-gray-600 dark:text-gray-300">Use the search bar and filters to find jobs that match your skills and preferences.</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="bg-blue-100 dark:bg-blue-900/40 p-2 rounded-full mr-3">
                                <i class="fas fa-bookmark text-blue-600 dark:text-blue-300"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800 dark:text-white">Save Jobs</h4>
                                <p class="text-gray-600 dark:text-gray-300">Click the bookmark icon to save interesting jobs for later.</p>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="bg-blue-100 dark:bg-blue-900/40 p-2 rounded-full mr-3">
                                <i class="fas fa-paper-plane text-blue-600 dark:text-blue-300"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800 dark:text-white">Apply Easily</h4>
                                <p class="text-gray-600 dark:text-gray-300">Use your pre-filled profile information to apply quickly to jobs.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Slide 4: Profile -->
                <div v-if="currentWelcomeSlide === 3" class="">
                    <h3 class="text-2xl font-bold text-gray-800 dark:text-white mb-4 flex items-center">
                        <i class="fas fa-user-edit text-blue-500 mr-2"></i> Complete Your Profile
                    </h3>
                    <div class="space-y-4 mb-6">
                        <div class="bg-blue-50 dark:bg-blue-900/30 p-4 rounded-lg">
                            <p class="text-blue-700 dark:text-blue-300">
                                <i class="fas fa-info-circle mr-2"></i>
                                A complete profile increases your chances of getting hired by 70%!
                            </p>
                        </div>
                        <ul class="space-y-3 text-gray-600 dark:text-gray-300">
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                Add your education history
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                List your skills and certifications
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                Include work experience
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check text-green-500 mr-2"></i>
                                Upload your resume
                            </li>
                        </ul>
                        <div class="mt-4">
                            <a href="my_profile" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-user-edit mr-2"></i> Complete My Profile Now
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer with navigation -->
            <div class="border-t border-gray-200 dark:border-gray-700 p-4 flex justify-between">
                <button v-if="currentWelcomeSlide > 0" 
                        @click="currentWelcomeSlide--" 
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i> Previous
                </button>
                <div v-else></div>
                
                <button v-if="currentWelcomeSlide < welcomeSlides.length - 1" 
                        @click="currentWelcomeSlide++" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Next <i class="fas fa-arrow-right ml-2"></i>
                </button>
                
                <button v-else 
                        @click="closeWelcomeModal" 
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Finish <i class="fas fa-check ml-2"></i>
                </button>
            </div>
        </div>
    </div>
