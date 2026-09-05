<div v-if="showLogoutModal" class="fixed inset-0 flex items-start justify-center z-[100]" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-black bg-opacity-50" @click="showLogoutModal = false"></div>
    <div class="absolute top-8 left-1/2 -translate-x-1/2 bg-white dark:bg-gray-700 rounded-lg shadow-xl p-6 w-full max-w-md mx-1">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Confirm Logout</h3>
            <button @click="showLogoutModal = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <p class="text-gray-600 dark:text-gray-300 mb-6">Are you sure you want to logout?</p>
        <div class="flex justify-end space-x-3">
            <button @click="showLogoutModal = false" class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                Cancel
            </button>
            <button @click="logout" class="px-4 py-2 rounded-md bg-red-600 text-white hover:bg-red-700 transition-colors">
                Logout
            </button>
        </div>
    </div>
</div>
