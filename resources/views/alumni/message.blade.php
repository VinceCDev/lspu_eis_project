@verbatim
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    @endverbatim
    @include('partials.session_guard')
    @verbatim
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | LSPU - EIS</title>
    <link rel="icon" type="image/png" href="<?= asset('assets/images/logo.png') ?>">
    <link rel="stylesheet" href="<?= asset('assets/vendor/fontawesome/css/all.min.css') ?>">
    <link href="<?= asset('assets/vendor/quill/quill.snow.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/messages.css') ?>">
    <link rel="stylesheet" href="<?= asset('assets/vendor/tailwind/tailwind.css') ?>">
    <style>[v-cloak] { display: none !important; }</style>
</head>
<body id="app" v-cloak :class="[darkMode ? 'dark bg-gmailgray' : 'bg-gray-100']" class="h-full">
    <!-- Add this modal code right after the opening <body> tag -->
    <div v-if="showWelcomeModal" class="fixed inset-0 z-[1000] flex items-center justify-center bg-black bg-opacity-70" role="dialog" aria-modal="true">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="bg-blue-600 text-white p-5 flex justify-between items-center">
                <h2 class="text-2xl font-bold flex items-center">
                    <i class="fas fa-graduation-cap mr-3"></i> Welcome to LSPU Alumni Portal!
                </h2>
                <button @click="closeWelcomeModal" class="text-white hover:text-blue-200 text-xl">
                    <i class="fas fa-times"></i>
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

    <!-- Toast Notification Area -->
    <div class="fixed top-4 right-4 z-[9999] space-y-3 w-full max-w-xs" aria-live="polite">
            <transition-group 
                enter-active-class="transform transition duration-300 ease-out"
                enter-from-class="translate-x-20 opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transform transition duration-200 ease-in"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="translate-x-20 opacity-0"
            >
                <div v-for="notification in notifications" :key="notification.id" 
                     :class="{
                         'bg-green-100 border-green-500 text-green-700': notification.type === 'success',
                         'bg-blue-100 border-blue-500 text-blue-700': notification.type === 'info',
                         'bg-red-100 border-red-500 text-red-700': notification.type === 'error',
                         'dark:bg-green-900/80 dark:border-green-700 dark:text-green-200': notification.type === 'success' && darkMode,
                         'dark:bg-blue-900/80 dark:border-blue-700 dark:text-blue-200': notification.type === 'info' && darkMode,
                         'dark:bg-red-900/80 dark:border-red-700 dark:text-red-200': notification.type === 'error' && darkMode
                     }" 
                     class="border-l-4 p-4 rounded-lg shadow-lg relative pr-8 flex items-start animate-slide-in" role="alert" tabindex="0">
                    <div class="flex-shrink-0 mt-1">
                            <i v-if="notification.type === 'success'" class="fas fa-check-circle text-lg"></i>
                            <i v-if="notification.type === 'info'" class="fas fa-info-circle text-lg"></i>
                            <i v-if="notification.type === 'error'" class="fas fa-exclamation-circle text-lg"></i>
                        </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-bold capitalize">{{ notification.type }}</h3>
                            <p class="text-sm mt-1">{{ notification.message }}</p>
                    </div>
                    <button @click="notifications = notifications.filter(n => n.id !== notification.id)" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" aria-label="Close notification">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </transition-group>
    </div>

        <div v-if="showLogoutModal" class="fixed inset-0 z-[100] flex items-center justify-center md:items-start md:justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-black bg-opacity-50" @click="showLogoutModal = false"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md mx-4 md:mt-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Confirm Logout</h3>
                    <button @click="showLogoutModal = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="mb-6 text-gray-700 dark:text-gray-300">Are you sure you want to logout?</p>
                <div class="flex justify-end gap-3">
                    <button @click="showLogoutModal = false" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Cancel</button>
                    <button @click="logout" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">Logout</button>
                </div>
            </div>
        </div>
        <!-- Floating Dark Mode Button -->
        <button class="fixed bottom-6 right-6 z-50 p-3 rounded-full bg-white dark:bg-gray-800 shadow-lg hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-600 transition-all duration-200" @click="toggleDarkMode">
            <i :class="darkMode ? 'fas fa-sun text-yellow-500' : 'fas fa-moon text-blue-500'" class="text-lg"></i>
        </button>
        <!-- Header -->
        <header class="sticky top-0 z-40 w-full bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 flex items-center px-2 md:px-6 h-16">
            <button class="mr-2 p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-800" @click="goBack" title="Back">
                <i class="fas fa-arrow-left text-xl text-gray-700 dark:text-white"></i>
            </button>
            <img src="<?= asset('assets/images/alumni.png') ?>" class="w-8 h-8" alt="Logo">
            <span class="ml-3 text-2xl font-bold text-gray-800 dark:text-white">Messages</span>
            <div class="flex-1"></div>
            <!-- Hamburger for mobile sidebar -->
            <button class="md:hidden p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-800 ml-2" @click="sidebarOpen = !sidebarOpen">
                <i class="fas fa-bars text-xl text-gray-700 dark:text-white"></i>
            </button>
            <div class="flex items-center gap-2 ml-2 hidden md:flex">
                <!-- Profile Dropdown -->
                <!-- Profile Dropdown for Desktop -->
                <div class="relative hidden md:block profile-dropdown-wrapper">
                    <button class="p-0 rounded-full focus:outline-none border-2 border-blue-700 dark:border-blue-300"
                            @click="profileDropdownOpen = !profileDropdownOpen">
                        <img v-if="profilePicData.file_name" :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile" class="w-10 h-10 rounded-full object-cover">
                        <img v-else src="<?= asset('assets/images/alumni.png') ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover">
                    </button>
                    <div v-show="profileDropdownOpen" class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-700 rounded-md shadow-lg overflow-hidden z-50 py-2 border border-gray-100 dark:border-gray-700 animate-slide-in">
                        <div class="px-4 py-3 flex items-center">
                            <img v-if="profilePicData.file_name" :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile" class="w-8 h-8 rounded-full mr-2">
                            <img v-else src="<?= asset('assets/images/alumni.png') ?>" alt="Profile" class="w-8 h-8 rounded-full mr-2">
                            <span class="dark:text-white">{{ profile.name || 'Alumni' }}</span>
                        </div>
                        <div class="px-4"><div class="border-t border-gray-200 dark:border-gray-600"></div></div>
                        <a href="my_profile" class="block px-4 py-2 text-gray-700 dark:text-gray-200  hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-500 transition-colors duration-200">
                            <i class="fas fa-user mr-2"></i> View Profile
                        </a>
                        <a href="message" class="block px-4 py-2 text-gray-700 dark:text-gray-200  hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-500 transition-colors duration-200">
                            <i class="fas fa-envelope mr-2"></i> Messages
                        </a>
                        <a href="forgot_password" class="block px-4 py-2 text-gray-700 dark:text-gray-200  hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-500 transition-colors duration-200">
                            <i class="fas fa-key mr-2"></i> Forgot Password
                        </a>
                        <a href="#" role="button" @click.prevent="openTutorial" class="block px-4 py-2 text-gray-700 dark:text-gray-200  hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-500 transition-colors duration-200">
                            <i class="fas fa-graduation-cap mr-2"></i> Show Tutorial
                        </a>
                        <div class="px-4"><div class="border-t border-gray-200 dark:border-gray-600"></div></div>
                        <a href="#" role="button" @click.prevent="showLogoutModal = true" class="block px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-red-100 hover:text-red-700 dark:hover:bg-blue-500 transition-colors duration-200">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>
        <!-- Responsive Main Layout -->
        <div class="flex w-full h-[calc(100vh-4rem)] relative">
            <!-- Sidebar -->
            <aside class="fixed md:static z-50 md:z-auto top-0 left-0 w-64 h-full bg-gray-50 dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col pt-8 px-0 overflow-y-auto scrollbar-thin transition-transform duration-200" :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen, 'md:translate-x-0': true}">
                <div class="flex flex-col gap-6 w-full px-4">
                    <button class="flex items-center gap-3 w-full bg-blue-700 text-white font-semibold text-sm py-3 px-6 rounded-lg shadow hover:bg-blue-800 dark:bg-blue-900 dark:text-blue-300 dark:hover:bg-blue-800 transition" @click="showCompose = true">
                        <i class="fas fa-pen-to-square text-lg"></i> Compose
                    </button>
                    <nav class="flex flex-col gap-2">
                        <a href="#" role="button" @click.prevent="activeFolder = 'inbox'" :class="['flex items-center gap-3 px-4 py-3 rounded-lg font-semibold text-sm transition', activeFolder === 'inbox' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 border-l-4 border-blue-700 dark:border-blue-300' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800']">
                            <span class="flex items-center"><i class="fas fa-envelope mr-2"></i>Inbox</span>
                            <span v-if="inboxCount > 0" class="ml-auto bg-blue-700 text-white text-xs font-bold px-2 py-0.5 rounded">{{ inboxCount }}</span>
                        </a>
                        <a href="#" role="button" @click.prevent="activeFolder = 'sent'" :class="['flex items-center gap-3 px-4 py-3 rounded-lg font-semibold text-sm transition', activeFolder === 'sent' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 border-l-4 border-blue-700 dark:border-blue-300' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800']">
                            <span class="flex items-center"><i class="fas fa-paper-plane mr-2"></i>Sent</span>
                            <span v-if="sentCount > 0" class="ml-auto bg-blue-700 text-white text-xs font-bold px-2 py-0.5 rounded">{{ sentCount }}</span>
                        </a>
                        <a href="#" role="button" @click.prevent="activeFolder = 'important'" :class="['flex items-center gap-3 px-4 py-3 rounded-lg font-semibold text-sm transition', activeFolder === 'important' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 border-l-4 border-blue-700 dark:border-blue-300' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800']">
                            <span class="flex items-center"><i class="fas fa-bell mr-2"></i> Important</span>
                            <span v-if="importantCount > 0" class="ml-auto bg-blue-700 text-white text-xs font-bold px-2 py-0.5 rounded">{{ importantCount }}</span>
                        </a>
                        <a href="#" role="button" @click.prevent="activeFolder = 'trash'" :class="['flex items-center gap-3 px-4 py-3 rounded-lg font-semibold text-sm transition', activeFolder === 'trash' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 border-l-4 border-blue-700 dark:border-blue-300' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800']">
                            <span class="flex items-center"><i class="fas fa-trash mr-2"></i> Trash</span>
                            <span v-if="trashCount > 0" class="ml-auto bg-blue-700 text-white text-xs font-bold px-2 py-0.5 rounded">{{ trashCount }}</span>
                        </a>
                    </nav>
                </div>
                <div class="mt-auto pb-8 px-4 md:hidden">
                    <div class="relative profile-dropdown-wrapper" @mouseleave="profileDropdownOpen = false">
                        <button class="flex items-center gap-3 w-full p-3 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800" @click="profileDropdownOpen = !profileDropdownOpen">
                            <img v-if="profilePicData.file_name" :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile" class="w-8 h-8 rounded-full object-cover">
                            <img v-else src="<?= asset('assets/images/alumni.png') ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover">
                            <span class="text-gray-700 dark:text-gray-200">{{ profile.name || 'Alumni' }}</span>
                            <i class="fas fa-chevron-down ml-auto text-gray-500 text-xs transition-transform duration-200" :class="{'rotate-180': profileDropdownOpen}"></i>
                        </button>
                        <div v-show="profileDropdownOpen" class="mt-1 w-full bg-white dark:bg-gray-700 rounded-md shadow-lg overflow-hidden z-50 py-2 border border-gray-100 dark:border-gray-700 animate-slide-in">
                            <a href="my_profile" class="block px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-500 dark:hover:text-white transition-colors duration-200">
                                <i class="fas fa-user mr-2"></i> View Profile
                            </a>
                            <a href="message" class="block px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-500 dark:hover:text-white transition-colors duration-200">
                                <i class="fas fa-envelope mr-2"></i> Messages
                            </a>
                            <a href="forgot_password" class="block px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-500 dark:hover:text-white transition-colors duration-200">
                                <i class="fas fa-key mr-2"></i> Forgot Password
                            </a>
                            <a href="#" role="button" @click.prevent="openTutorial" class="block px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-500 transition-colors duration-200">
                                <i class="fas fa-graduation-cap mr-2"></i> Show Tutorial
                            </a>
                            <div class="border-t border-gray-200 dark:border-gray-600 my-1"></div>
                            <a href="#" role="button" @click.prevent="showLogoutModal = true" class="block px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-red-100 hover:text-red-700 dark:hover:bg-red-500 dark:hover:text-white transition-colors duration-200">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </aside>
            <!-- Close button for mobile sidebar -->
            <button v-show="sidebarOpen" class="fixed md:hidden top-4 right-4 z-60 p-2 rounded-full bg-white dark:bg-gray-800 shadow-lg hover:bg-gray-100 dark:hover:bg-gray-700" @click="sidebarOpen = false">
                <i class="fas fa-times text-xl text-gray-700 dark:text-white"></i>
            </button>
            <!-- Main Panel -->
            <main class="flex-1 flex flex-col h-full min-h-0 bg-white dark:bg-gray-900 overflow-hidden">
                <!-- Section Title and Action Buttons -->
                <div class="flex flex-col gap-0 w-full">
                    <div class="flex items-center border-b-2 border-blue-700 dark:border-blue-300 bg-white dark:bg-gray-900 px-4 md:px-8 pt-4 md:pt-8 pb-4">
                        <h2 class="text-lg md:text-2xl font-bold flex items-center gap-2 text-blue-700 dark:text-blue-300">
                            <i :class="folderIcon"></i> {{ folderTitle }}
                        </h2>
                        <div class="flex-1"></div>
                        <div class="relative w-48 md:w-64 lg:w-80 ml-2 md:ml-4">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><i class="fas fa-search text-sm"></i></span>
                            <input v-model="search" type="text" placeholder="Search mail" class="w-full pl-8 md:pl-10 pr-3 md:pr-4 py-1.5 md:py-2 rounded-full bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 dark:focus:ring-blue-500 transition text-xs md:text-sm"/>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 px-6 md:px-8 py-4">
                        <button class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300 text-sm" title="Star" @click="toggleImportantSelected"><i class="fas fa-star"></i></button>
                        <button class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300 text-sm" title="Trash" @click="moveToTrashSelected"><i class="fas fa-trash"></i></button>
                        <button v-if="activeFolder === 'trash'" class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300 text-sm" title="Restore" @click="restoreFromTrashSelected"><i class="fas fa-undo"></i></button>
                        <!-- Export buttons -->
                        <button class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300 text-sm" title="Copy" @click="copyTable"><i class="fas fa-copy"></i></button>
                        <button class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300 text-sm" title="Export Excel" @click="exportToExcel"><i class="fas fa-file-excel"></i></button>
                        <button class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300 text-sm" title="Export PDF" @click="exportToPDF"><i class="fas fa-file-pdf"></i></button>
                        <button class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300 text-sm" title="Print" @click="printTable"><i class="fas fa-print"></i></button>
                    </div>
                </div>
                <!-- Message Table with Horizontal Scroll -->
                <!-- Replace your current table with this -->
                <div class="flex-1 min-h-0 overflow-auto bg-white dark:bg-gray-900">
                    <div class="inline-block min-w-full align-middle">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-blue-100 dark:bg-blue-900">
                                <tr>
                                    <th v-if="paginatedMessages.length > 0" scope="col" class="px-3 py-2 text-center w-8 pl-8">
                                        <input type="checkbox" v-model="selectAll" @change="toggleSelectAll" class="h-4 w-4">
                                    </th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs sm:text-sm md:text-md font-medium text-blue-700 dark:text-blue-300 tracking-wider pl-8">
                                        Sender/Receiver
                                    </th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs sm:text-sm md:text-md font-medium text-blue-700 dark:text-blue-300 tracking-wider pl-8">
                                        Subject
                                    </th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs sm:text-sm md:text-md font-medium text-blue-700 dark:text-blue-300 tracking-wider pl-8">
                                        Message
                                    </th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs sm:text-sm md:text-md font-medium text-blue-700 dark:text-blue-300 tracking-wider pl-8">
                                        Time
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="(msg, idx) in paginatedMessages" :key="msg.id"
                                    @click="openMessage(msg)"
                                    class="hover:bg-blue-50 dark:hover:bg-blue-800 cursor-pointer transition-colors duration-150">
                                    <td class="px-3 py-2 whitespace-nowrap pl-8">
                                        <input type="checkbox" :value="msg.id" v-model="selectedMessages" @click.stop class="h-4 w-4">
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-xs sm:text-sm md:text-md text-gray-700 dark:text-gray-300 pl-8">
                                        {{ msg.sender }}
                                    </td>
                                    <td class="px-3 py-2 text-xs sm:text-sm md:text-md text-gray-700 dark:text-gray-300 max-w-[120px] sm:max-w-[180px] md:max-w-[240px] truncate pl-8">
                                        {{ msg.subject }}
                                    </td>
                                    <td class="px-3 py-2 text-xs sm:text-sm md:text-md text-gray-700 dark:text-gray-300 max-w-[180px] sm:max-w-[280px] md:max-w-[360px] truncate pl-8">
                                        {{ stripHtml(msg.message) }}
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-xs sm:text-sm md:text-md text-gray-700 dark:text-gray-300 pl-8">
                                        {{ msg.time }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div v-if="filteredMessages.length > 0" class="flex justify-end items-center space-x-1 py-3 px-4 bg-white dark:bg-gray-900">
                    <button
                        @click="goToPage(1)"
                        :disabled="currentPage === 1"
                        :class="['w-8 h-8 text-sm flex items-center justify-center rounded-md transition-colors',
                                currentPage === 1 ? 'text-gray-400 cursor-not-allowed' :
                                'text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-700 dark:hover:text-blue-300']">
                        <i class="fas fa-angles-left"></i>
                    </button>
                    <button
                        @click="prevPage"
                        :disabled="currentPage === 1"
                        :class="['w-8 h-8 text-sm flex items-center justify-center rounded-md transition-colors',
                                currentPage === 1 ? 'text-gray-400 cursor-not-allowed' :
                                'text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-700 dark:hover:text-blue-300']">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="flex items-center space-x-1">
                        <button
                            v-for="page in totalPages"
                            :key="page"
                            @click="goToPage(page)"
                            :class="['w-8 h-8 text-sm flex items-center justify-center rounded-md transition-colors font-medium',
                                    currentPage === page ?
                                    'bg-blue-600 text-white' :
                                    'text-gray-600 dark:text-gray-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-700 dark:hover:text-blue-300']">
                            {{ page }}
                        </button>
                    </div>
                    <button
                        @click="nextPage"
                        :disabled="currentPage === totalPages"
                        :class="['w-8 h-8 text-sm flex items-center justify-center rounded-md transition-colors',
                                currentPage === totalPages ? 'text-gray-400 cursor-not-allowed' :
                                'text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-700 dark:hover:text-blue-300']">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button
                        @click="goToPage(totalPages)"
                        :disabled="currentPage === totalPages"
                        :class="['w-8 h-8 text-sm flex items-center justify-center rounded-md transition-colors',
                                currentPage === totalPages ? 'text-gray-400 cursor-not-allowed' :
                                'text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-700 dark:hover:text-blue-300']">
                        <i class="fas fa-angles-right"></i>
                    </button>
                    </div>
                </div>
            </main>
            
            <!-- Compose Modal (floating) -->
            <div v-if="showCompose" class="fixed inset-0 z-[100] flex items-end justify-end p-4 bg-black bg-opacity-50 dark:bg-opacity-70" role="dialog" aria-modal="true">
                <div class="compose-modal bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 p-0 relative overflow-hidden pointer-events-auto w-full max-w-md">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-900/30">
                        <span class="font-semibold text-gray-800 dark:text-gray-100">New Message</span>
                        <button class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-1 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors" @click="showCompose = false">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                    <form @submit.prevent="sendMessage" class="px-5 py-4 space-y-4">
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Receiver <span class="text-red-500">*</span></label>
                            <select v-model="compose.receiver" required @change="onReceiverChange" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                <option value="">Select recipient</option>
                                <option v-for="user in allUsers" :key="user.email" :value="user.email">
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Role</label>
                            <input type="text" v-model="compose.role" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 cursor-not-allowed" readonly disabled>
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Subject <span class="text-red-500">*</span></label>
                            <input v-model="compose.subject" required type="text" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100" placeholder="Enter subject">
                        </div>
                        <div>
                            <label class="block text-gray-700 dark:text-gray-300 font-medium mb-2">Message <span class="text-red-500">*</span></label>
                            <div id="editor" class="bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg min-h-[120px] p-3 text-gray-900 dark:text-gray-100"></div>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="px-5 py-2.5 rounded-lg bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 font-medium hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors" @click="showCompose = false">Cancel</button>
                            <button type="submit" class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition-colors flex items-center gap-2">
                                <i class="fas fa-paper-plane text-sm"></i>
                                Send
                            </button>
                        </div>
                    </form>
                </div>
            </div>
    </div>

    <!-- Message Detail Modal -->
    <transition enter-active-class="ease-out duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
        <div v-if="selectedMessage" class="fixed inset-0 z-[100] overflow-y-auto" role="dialog" aria-modal="true">
            <!-- Overlay -->
            <transition enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0">
                <div class="fixed inset-0 bg-gray-900 bg-opacity-75 dark:bg-opacity-85 transition-opacity" 
                    @click="selectedMessage = null"></div>
            </transition>

            <!-- Modal Content -->
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <transition enter-active-class="ease-out duration-300"
                        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-active-class="ease-in duration-200"
                        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl w-full">
                        <!-- Header -->
                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-start">
                            <div class="pr-4">
                                <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100">
                                    {{ selectedMessage.subject || '(No Subject)' }}
                                </h3>
                                <div class="mt-2 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 text-sm">
                                    <span class="text-gray-600 dark:text-gray-300 flex items-center">
                                        <span class="font-medium mr-1">{{ activeFolder === 'inbox' ? 'From:' : 'To:' }}</span>
                                        {{ activeFolder === 'inbox' ? selectedMessage.sender_email : selectedMessage.receiver_email }}
                                    </span>
                                    <span class="hidden sm:inline text-gray-400 dark:text-gray-500">•</span>
                                    <span class="text-gray-500 dark:text-gray-400 flex items-center">
                                        <i class="far fa-clock mr-1.5"></i>
                                        {{ formatDate(selectedMessage.created_at) }}
                                    </span>
                                </div>
                            </div>
                            <button @click="selectedMessage = null" 
                                    class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 p-1.5 rounded-full hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>

                        <!-- Message Body -->
                        <div class="px-6 py-5 bg-white dark:bg-gray-800 max-h-[70vh] overflow-y-auto">
                            <div class="prose prose-gray dark:prose-invert max-w-none text-gray-700 dark:text-gray-200 whitespace-pre-wrap" v-html="sanitizeHtml(selectedMessage.message)"></div>
                        </div>

                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                <!-- Reply Button -->
                                <button @click="replyToMessage(selectedMessage)"
                                        class="inline-flex items-center justify-center px-3.5 py-2 bg-blue-100 dark:bg-blue-600 text-blue-700 dark:text-blue-100 hover:bg-blue-200 dark:hover:bg-blue-500 rounded-md text-sm font-medium transition-colors w-full">
                                    <i class="fas fa-reply mr-2 text-xs"></i> Reply
                                </button>
                                
                                <!-- Forward Button -->
                                <button @click="forwardMessage(selectedMessage)"
                                        class="inline-flex items-center justify-center px-3.5 py-2 bg-violet-100 dark:bg-violet-600 text-violet-700 dark:text-violet-100 hover:bg-violet-200 dark:hover:bg-violet-500 rounded-md text-sm font-medium transition-colors w-full">
                                    <i class="fas fa-share mr-2 text-xs"></i> Forward
                                </button>
                                
                                <!-- Important Button -->
                                <button @click="toggleImportant(selectedMessage)"
                                        :class="selectedMessage.is_important ? 
                                            'bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 hover:bg-amber-200 dark:hover:bg-amber-500/30' : 
                                            'bg-yellow-100 dark:bg-yellow-600 text-yellow-700 dark:text-yellow-300 hover:bg-yellow-200 dark:hover:bg-yellow-500'"
                                            
                                        class="inline-flex items-center justify-center px-3.5 py-2 bg-blue-100 dark:bg-blue-600 text-blue-700 dark:text-blue-100 hover:bg-blue-200 dark:hover:bg-blue-500 rounded-md text-sm font-medium transition-colors w-full">
                                    <i class="fas fa-star mr-2 text-xs"></i>
                                    {{ selectedMessage.is_important ? 'Unmark' : 'Mark' }}
                                </button>
                                
                                <!-- Trash Button -->
                                <button @click="moveToTrash(selectedMessage)"
                                        class="inline-flex items-center justify-center px-3.5 py-2 bg-red-100 dark:bg-red-600 text-red-700 dark:text-red-100 hover:bg-red-200 dark:hover:bg-red-500 rounded-md text-sm font-medium transition-colors w-full">
                                    <i class="fas fa-trash mr-2 text-xs"></i> Trash
                                </button>
                            </div>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </transition>
    
    <script>
        (function() {
            try {
                var dark = localStorage.getItem('darkMode');
                if (dark === 'true' || (dark === null && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch(e){}
        })();
    </script>
    <script src="<?= asset('assets/vendor/vue/vue.global.prod.js') ?>"></script>
    <script src="<?= asset('assets/vendor/jspdf/jspdf.umd.min.js') ?>"></script>
    <script src="<?= asset('assets/vendor/xlsx/xlsx.full.min.js') ?>"></script>
    <script src="<?= asset('assets/vendor/file-saver/FileSaver.min.js') ?>"></script>
    <script src="<?= asset('assets/vendor/jspdf-autotable/jspdf.plugin.autotable.min.js') ?>"></script>
    <script src="<?= asset('assets/vendor/quill/quill.min.js') ?>"></script>
    <script src="<?= asset('assets/js/user_messages.js') ?>"></script>
</body>
</html>
@endverbatim
