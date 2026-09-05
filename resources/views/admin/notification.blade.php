@extends('layouts.admin')
@section('content')
@verbatim
<div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 mb-6 border border-gray-100 dark:border-gray-600">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Notifications</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">System and account activity relevant to admins</p>
        </div>
        <div class="flex gap-2">
            <button @click="markAllAsRead" :disabled="notificationsList.length === 0 || allRead"
                class="px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2 disabled:opacity-50 bg-blue-600 hover:bg-blue-700 text-white">
                <i class="fas fa-check"></i> Mark all as read
            </button>
            <button @click="fetchNotifications" :disabled="loading"
                class="px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2 disabled:opacity-50 bg-gray-500 hover:bg-gray-600 text-white">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>
</div>

<div v-if="loading" class="text-center py-12">
    <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-600 border-t-transparent"></div>
    <p class="mt-2 text-gray-600 dark:text-gray-400">Loading notifications...</p>
</div>

<div v-else-if="notificationsList.length === 0" class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-12 border border-gray-100 dark:border-gray-600 text-center">
    <i class="far fa-bell-slash text-4xl text-gray-300 dark:text-gray-500 mb-4"></i>
    <h3 class="text-lg font-medium text-gray-700 dark:text-gray-200">No notifications yet</h3>
    <p class="text-gray-500 dark:text-gray-400 mt-1">New employer/alumni registrations and system events will appear here.</p>
</div>

<div v-else class="flex flex-col gap-3 pb-8">
    <div v-for="notification in notificationsList" :key="notification.id"
        class="bg-white dark:bg-gray-700 rounded-xl shadow-sm border border-gray-100 dark:border-gray-600 p-5 flex items-start gap-3 cursor-pointer hover:shadow-md transition-shadow"
        :class="{ 'opacity-70': notification.read }"
        @click="markNotificationAsRead(notification)">
        <div class="flex-shrink-0 mt-1">
            <i :class="[notificationIcons[notification.type] || 'fas fa-bell', 'text-blue-500 dark:text-blue-400 text-lg']"></i>
        </div>
        <div class="flex-1 min-w-0">
            <div class="font-semibold text-gray-800 dark:text-gray-100">{{ notification.message }}</div>
            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ notification.details }}</div>
            <div class="text-xs text-gray-400 dark:text-gray-500 mt-2 flex items-center gap-1">
                <i class="far fa-clock"></i> {{ formatTime(notification.time) }}
            </div>
        </div>
        <span v-if="!notification.read" class="bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 px-2 py-0.5 rounded-full text-xs font-semibold flex-shrink-0">Unread</span>
    </div>
</div>

@endverbatim
@endsection
