@extends('layouts.employer')
@section('content')
@verbatim
<script>
    window.__notificationTypes = <?= json_encode($notificationTypes) ?>;
    window.__notificationPreferences = <?= json_encode($preferences) ?>;
</script>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200 mb-2">Settings</h1>
    <p class="text-gray-600 dark:text-gray-400">Manage your account and notification preferences</p>
</div>

<div class="flex gap-2 mb-6 border-b border-gray-200 dark:border-gray-600">
    <button @click="activeTab = 'account'" :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors', activeTab === 'account' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200']">
        <i class="fas fa-user-lock mr-2"></i>Account
    </button>
    <button @click="activeTab = 'notifications'" :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors', activeTab === 'notifications' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200']">
        <i class="fas fa-bell mr-2"></i>Notifications
    </button>
</div>

<!-- Account tab -->
<div v-if="activeTab === 'account'" class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 max-w-lg">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6">Change Password</h2>
    <form @submit.prevent="changePassword" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Current Password</label>
            <input type="password" v-model="passwordForm.current_password" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password</label>
            <input type="password" v-model="passwordForm.new_password" required minlength="8" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm New Password</label>
            <input type="password" v-model="passwordForm.confirm_password" required minlength="8" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
        </div>
        <div class="flex justify-end">
            <button type="submit" :disabled="passwordSaving" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                {{ passwordSaving ? 'Saving...' : 'Update Password' }}
            </button>
        </div>
    </form>

    <div class="mt-10 pt-6 border-t border-red-200 dark:border-red-900">
        <h2 class="text-xl font-semibold text-red-600 dark:text-red-400 mb-2">Delete Account</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Permanently deletes your account and all associated data (company profile, job postings, applications received, messages). This cannot be undone.</p>
        <button v-if="!showDeleteConfirm" type="button" @click="showDeleteConfirm = true" class="text-red-600 border border-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 px-4 py-2 rounded-md text-sm font-medium transition-colors">
            Delete My Account
        </button>
        <form v-else @submit.prevent="showFinalDeleteConfirm = true" class="space-y-3 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-900 rounded-md p-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Enter your password to confirm</label>
            <input type="password" v-model="deletePassword" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-gray-600 dark:text-gray-200">
            <div class="flex gap-3 justify-end">
                <button type="button" @click="showDeleteConfirm = false; deletePassword = ''" class="px-4 py-2 rounded-md text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Cancel</button>
                <button type="submit" :disabled="deleteSaving" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors disabled:opacity-50">
                    {{ deleteSaving ? 'Deleting...' : 'Permanently Delete' }}
                </button>
            </div>
        </form>
    </div>

    <!-- Final "are you sure" step — the password form above only confirms identity, this confirms intent -->
    <div v-if="showFinalDeleteConfirm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" role="dialog" aria-modal="true">
        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-xl max-w-sm w-full p-6">
            <h3 class="text-lg font-bold text-red-600 dark:text-red-400 mb-2">Are you sure?</h3>
            <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">This will permanently delete your account and all associated data. This action cannot be undone.</p>
            <div class="flex gap-3 justify-end">
                <button type="button" @click="showFinalDeleteConfirm = false" class="px-4 py-2 rounded-md text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Cancel</button>
                <button type="button" :disabled="deleteSaving" @click="deleteAccount" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors disabled:opacity-50">
                    {{ deleteSaving ? 'Deleting...' : 'Yes, Delete My Account' }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Notifications tab -->
<div v-if="activeTab === 'notifications'" class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 max-w-lg">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Notification Preferences</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Choose which in-app notifications you want to receive.</p>
    <div class="space-y-4">
        <label v-for="(label, type) in notificationTypes" :key="type" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-600 rounded">
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ label }}</span>
            <input type="checkbox" v-model="notificationPrefs[type]" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 checked:bg-blue-600 checked:border-blue-600">
        </label>
    </div>
    <div class="flex justify-end mt-6">
        <button @click="saveNotificationPreferences" :disabled="notifSaving" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            {{ notifSaving ? 'Saving...' : 'Save Preferences' }}
        </button>
    </div>
</div>

@endverbatim
@endsection
