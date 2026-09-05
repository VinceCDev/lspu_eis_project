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
