@verbatim
<?php
/** @var string $active current page slug, e.g. 'home', 'my_application', 'notification' */
$active = $active ?? '';
$navClass = function (string $page) use ($active) {
    return $active === $page
        ? 'relative inline-flex items-center text-blue-700 dark:text-blue-300 font-bold border-b-4 border-blue-700 dark:border-blue-300 pb-1 bg-blue-50 dark:bg-blue-900 rounded-t transition-all duration-200 px-2'
        : 'relative text-gray-600 dark:text-gray-300 hover:text-blue-700 dark:hover:text-blue-300 hover:border-b-4 hover:border-blue-400 dark:hover:border-blue-300 pb-1 transition-all duration-200 px-2';
};
$mobileNavClass = function (string $page) use ($active) {
    return $active === $page
        ? 'relative block py-2 text-blue-700 dark:text-blue-300 hover:text-lspu-blue dark:hover:text-blue-300 font-blue'
        : 'relative block py-2 text-gray-600 dark:text-gray-300 hover:text-lspu-blue dark:hover:text-blue-300';
};
?>
<header class="bg-gradient-to-r from-blue-200 to-white dark:from-blue-900 dark:to-gray-800 shadow-sm fixed top-0 left-0 right-0 bottom-0 z-50 h-[70px] transition-colors duration-200">
    <div class="container mx-auto h-full px-4">
        <nav class="flex items-center justify-between h-full">
            <a href="home" class="flex items-center">
                <img src="<?= asset('assets/images/alumni.png') ?>" alt="LSPU Logo" class="h-[60px] w-auto">
                <span class="text-2xl font-bold text-gray-800 dark:text-white ml-2">LSPU</span>
                <span class="text-2xl font-light text-blue-700 dark:text-blue-300">EIS</span>
            </a>
            <div class="flex items-center space-x-4">
                <!-- Mobile Menu Button -->
                <button class="md:hidden text-gray-600 dark:text-gray-300 focus:outline-none" @click="mobileMenuOpen = !mobileMenuOpen">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-6">
                    <a href="home" class="<?= $navClass('home') ?>">Home</a>
                    <a href="my_application" class="<?= $navClass('my_application') ?>">My Applications</a>
                    <a href="notification" class="<?= $navClass('notification') ?>">
                        Notifications
                        <span
                            v-if="unreadNotifications > 0"
                            class="absolute -top-2 -right-2 inline-flex items-center justify-center h-5 w-5 text-xs font-bold text-white bg-red-500 rounded-full transform transition-transform hover:scale-110"
                        >
                            {{ unreadNotifications > 99 ? '99+' : unreadNotifications }}
                        </span>
                    </a>
                    <!-- Profile Dropdown -->
                    <div class="relative profile-dropdown-wrapper">
                        <button class="flex items-center text-gray-600 dark:text-gray-300 hover:text-blue-700 dark:hover:text-blue-300 focus:outline-none" @click="profileDropdownOpen = !profileDropdownOpen">
                            Profile <i class="fas fa-chevron-down ml-1 text-xs"></i>
                        </button>

                        <div v-show="profileDropdownOpen" class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-700 rounded-md shadow-lg overflow-hidden z-50">
                            <!-- Profile section -->
                            <div class="px-4 py-3 flex items-center">
                                <img v-if="profilePicData && profilePicData.file_name" :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile" class="w-8 h-8 rounded-full mr-2">
                                <img v-else src="<?= asset('assets/images/alumni.png') ?>" alt="Profile" class="w-8 h-8 rounded-full mr-2">
                                <span class="dark:text-white">{{ profile.name || 'Alumni' }}</span>
                            </div>

                            <div class="px-4"><div class="border-t border-gray-200 dark:border-gray-600"></div></div>

                            <!-- Menu items -->
                            <a href="my_profile" class="block px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-500 transition-colors duration-200">
                                <i class="fas fa-user mr-2"></i> View Profile
                            </a>
                            <a href="message" class="block px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-500 transition-colors duration-200">
                                <i class="fas fa-envelope mr-2"></i> Messages
                            </a>
                            <a href="forgot_password" class="block px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-500 transition-colors duration-200">
                                <i class="fas fa-key mr-2"></i> Forgot Password
                            </a>
                            <a href="#" role="button" @click.prevent="openTutorial" class="block px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-500 transition-colors duration-200">
                                <i class="fas fa-graduation-cap mr-2"></i> Show Tutorial
                            </a>

                            <div class="px-4"><div class="border-t border-gray-200 dark:border-gray-600"></div></div>

                            <!-- Dark Mode Toggle -->
                            <div class="px-4 py-2 flex items-center justify-between">
                                <div class="flex items-center text-gray-700 dark:text-gray-200">
                                    <i class="fas fa-moon mr-2"></i>
                                    <span>Dark Mode</span>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" class="sr-only peer" v-model="darkMode">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                </label>
                            </div>

                            <div class="px-4"><div class="border-t border-gray-200 dark:border-gray-600"></div></div>

                            <!-- Logout -->
                            <a href="#" role="button" @click.prevent="showLogoutModal = true" class="block px-4 py-2 text-gray-700 dark:text-gray-200 hover:bg-red-100 hover:text-red-700 dark:hover:bg-blue-500 transition-colors duration-200">
                                <i class="fas fa-sign-out-alt mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </div>
    <!-- Mobile Menu -->
    <div v-show="mobileMenuOpen" class="md:hidden bg-white dark:bg-gray-800 shadow-lg absolute top-[70px] left-0 right-0 transition-colors duration-200 z-40">
        <div class="container mx-auto px-4 py-3">
            <a href="home" class="<?= $mobileNavClass('home') ?>">Home</a>
            <a href="my_application" class="<?= $mobileNavClass('my_application') ?>">My Applications</a>
            <a href="notification" class="<?= $mobileNavClass('notification') ?>">
                Notifications
                <span v-if="unreadNotifications > 0" class="absolute top-0 right-0 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white transform translate-x-1/2 -translate-y-1/2 bg-red-500 rounded-full">
                    {{ unreadNotifications }}
                </span>
            </a>

            <!-- Mobile Profile Dropdown -->
            <div class="pt-2 border-t border-gray-200 dark:border-gray-700 mt-2">
                <button @click="mobileProfileDropdownOpen = !mobileProfileDropdownOpen" class="flex items-center justify-between w-full py-2 text-gray-600 dark:text-gray-300 hover:text-lspu-blue dark:hover:text-blue-300">
                    <div class="flex items-center">
                        <img v-if="profilePicData.file_name" :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile" class="w-8 h-8 rounded-full mr-2">
                        <img v-else src="<?= asset('assets/images/alumni.png') ?>" alt="Profile" class="w-8 h-8 rounded-full mr-2">
                        <span class="dark:text-white">{{ profile.name || 'Alumni' }}</span>
                    </div>
                    <i :class="['fas', mobileProfileDropdownOpen ? 'fa-chevron-up' : 'fa-chevron-down', 'text-xs']"></i>
                </button>

                <div v-show="mobileProfileDropdownOpen" class="pl-6 mt-2 space-y-2">
                    <a href="my_profile" class="block py-2 text-gray-600 dark:text-gray-300 hover:text-black dark:hover:text-blue-300">
                        <i class="fas fa-user mr-2"></i> View Profile
                    </a>
                    <a href="message" class="block py-2 text-gray-600 dark:text-gray-300 hover:text-black dark:hover:text-blue-300">
                        <i class="fas fa-envelope mr-2"></i> Messages
                    </a>
                    <a href="forgot_password" class="block py-2 text-gray-600 dark:text-gray-300 hover:text-black dark:hover:text-blue-300">
                        <i class="fas fa-key mr-2"></i> Forgot Password
                    </a>
                    <a href="#" role="button" @click.prevent="openTutorial" class="block py-2 text-gray-600 dark:text-gray-300 hover:text-black dark:hover:text-blue-300">
                        <i class="fas fa-graduation-cap mr-2"></i> Show Tutorial
                    </a>
                    <div class="py-2 flex items-center justify-between">
                        <div class="flex items-center text-gray-600 dark:text-gray-300 mr-3">
                            <i class="fas fa-moon mr-2"></i>
                            <span>Dark Mode</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" class="sr-only peer" v-model="darkMode">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <a href="#" role="button" @click.prevent="showLogoutModal = true" class="block py-2 text-gray-600 dark:text-gray-300 hover:text-black dark:hover:text-blue-300">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
@endverbatim
