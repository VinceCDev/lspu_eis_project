@extends('layouts.admin')
@section('content')
@verbatim
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200 mb-2">System Info</h1>
    <p class="text-gray-600 dark:text-gray-400">System-wide stats and recent activity</p>
</div>

<div v-if="loading" class="flex justify-center items-center py-12">
    <i class="fas fa-spinner fa-spin text-2xl text-gray-500"></i>
    <span class="ml-2 text-gray-500">Loading...</span>
</div>

<template v-else>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 flex items-center">
            <div class="p-3 rounded-full bg-cyan-100 dark:bg-cyan-900">
                <i class="fas fa-user-graduate text-cyan-600 dark:text-cyan-400 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Alumni</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ userCounts.alumni || 0 }}</p>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 flex items-center">
            <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900">
                <i class="fas fa-building text-purple-600 dark:text-purple-400 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Employers</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ userCounts.employer || 0 }}</p>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 flex items-center">
            <div class="p-3 rounded-full bg-red-100 dark:bg-red-900">
                <i class="fas fa-user-shield text-red-600 dark:text-red-400 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Admins</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ userCounts.admin || 0 }}</p>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 flex items-center">
            <div class="p-3 rounded-full bg-amber-100 dark:bg-amber-900">
                <i class="fas fa-crown text-amber-600 dark:text-amber-400 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Superadmins</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ userCounts.superadmin || 0 }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Pending Alumni</h3>
            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ pendingCounts.alumni || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Pending Employers</h3>
            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ pendingCounts.employer || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Database Size</h3>
            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ formatBytes(databaseSizeBytes) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Storage Usage</h2>
            <div class="space-y-2">
                <div v-for="(bytes, folder) in uploadsSizeBytes" :key="folder" class="flex justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-400 capitalize">{{ folder.replace(/_/g, ' ') }}</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ formatBytes(bytes) }}</span>
                </div>
                <div v-if="!Object.keys(uploadsSizeBytes).length" class="text-sm text-gray-400">No uploads yet</div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Server Info</h2>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">PHP Version</span><span class="font-medium text-gray-800 dark:text-gray-200">{{ serverInfo.php_version }}</span></div>
                <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">MySQL Version</span><span class="font-medium text-gray-800 dark:text-gray-200">{{ serverInfo.mysql_version }}</span></div>
                <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Server Software</span><span class="font-medium text-gray-800 dark:text-gray-200 text-right">{{ serverInfo.server_software }}</span></div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Recent Registrations</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-left">
                        <th scope="col" class="px-4 py-2">Name</th>
                        <th scope="col" class="px-4 py-2">Email</th>
                        <th scope="col" class="px-4 py-2">Role</th>
                        <th scope="col" class="px-4 py-2">Status</th>
                        <th scope="col" class="px-4 py-2">Registered</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-600">
                    <tr v-for="row in recentRegistrations" :key="row.user_id">
                        <td class="px-4 py-2">{{ row.display_name || '—' }}</td>
                        <td class="px-4 py-2">{{ row.email }}</td>
                        <td class="px-4 py-2 capitalize">{{ row.user_role }}</td>
                        <td class="px-4 py-2">{{ row.status }}</td>
                        <td class="px-4 py-2">{{ formatDate(row.created_at) }}</td>
                    </tr>
                    <tr v-if="!recentRegistrations.length">
                        <td colspan="5" class="px-4 py-6 text-center text-gray-400">No recent registrations</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

@endverbatim
@endsection
