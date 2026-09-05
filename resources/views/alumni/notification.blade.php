@extends('layouts.alumni')
@section('content')
@verbatim
            <!-- Title and Actions -->
            <div class="flex flex-col sm:flex-row justify-between items-center mb-2 gap-4 pb-4 pt-4">
                <h1 class="text-2xl font-bold text-blue-700 dark:text-blue-300 tracking-wide">
                    Notifications
                </h1>
                <div class="flex justify-between gap-2">
                    <button @click="markAllAsRead" :disabled="notifications.length === 0 || allRead" class="px-5 py-2 rounded-md text-sm font-semibold transition-colors duration-200 focus:outline-none flex items-center gap-2 disabled:opacity-50 bg-blue-700 dark:bg-blue-600 text-white hover:bg-blue-800 dark:hover:bg-blue-700">
                        <i class="fas fa-check"></i> Mark all as read
                    </button>
                    <button @click="fetchNotifications" :disabled="loading" class="px-5 py-2 rounded-md text-sm font-semibold transition-colors duration-200 focus:outline-none flex items-center gap-2 disabled:opacity-50 bg-gray-500 dark:bg-gray-700 text-white hover:bg-gray-600 dark:hover:bg-gray-600">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <!-- Loading state -->
            <div v-if="loading" class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-700 border-t-transparent"></div>
                <p class="mt-2 text-gray-600 dark:text-gray-400">Loading your notifications...</p>
            </div>
            <!-- Empty state -->
            <div v-else-if="notifications.length === 0" class="flex justify-center items-center py-12">
                <div class="w-full max-w-md mx-auto p-8 flex flex-col items-center">
                    <i class="far fa-bell-slash text-4xl text-gray-300 dark:text-gray-500 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-700 dark:text-gray-200">No notifications yet</h3>
                    <p class="text-gray-500 dark:text-gray-400 mt-1 mb-4 text-center">When you have new notifications, they'll appear here.</p>
                    <a href="home" class="inline-block px-4 py-2 bg-blue-700 text-white rounded-md hover:bg-blue-800 transition-colors duration-200 font-semibold shadow">Go to Home</a>
                </div>
            </div>
            <!-- Notifications list -->
            <div v-else class="flex flex-col gap-4 pb-8">
                <div v-for="notification in notifications" :key="notification.id"
                    class="rounded-2xl shadow-md border border-blue-100 dark:border-gray-700 bg-white dark:bg-gray-800/80 overflow-hidden transition-all duration-200 hover:shadow-xl hover:border-blue-400 hover:-translate-y-1 hover:scale-[1.02] cursor-pointer flex flex-col gap-2 p-6"
                    :class="{ 'opacity-70': notification.read }"
                    @click="markNotificationAsRead(notification)">
                    
                    <!-- Add icon here -->
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 mt-1">
                            <i :class="[notificationIcons[notification.type] || 'fas fa-bell', 'text-blue-500 dark:text-blue-400 text-lg']"></i>
                        </div>
                        <div class="flex-1">
                            <div class="font-semibold text-gray-800 dark:text-gray-100 text-base">{{ notification.message }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ notification.details }}</div>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between mt-1">
                        <div class="text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1">
                            <i class="far fa-clock"></i> {{ formatTime(notification.time) }}
                        </div>
                        <span v-if="!notification.read" class="bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 px-2 py-0.5 rounded-full text-xs font-semibold">Unread</span>
                    </div>
                </div>
            </div>

@endverbatim
@endsection
