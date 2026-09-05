@extends('layouts.admin')
@section('content')
@verbatim
<div class="bg-white dark:bg-gray-700 rounded-xl shadow-xl main-shadow p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <h2 class="text-2xl font-bold mb-2 md:mb-0 text-gray-800 dark:text-gray-100">Audit Logs</h2>
        <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full md:w-auto">
            <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition w-full sm:w-auto justify-center" @click="fetchLogs" :disabled="loading">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
        <div class="flex items-center gap-2 w-full md:w-auto mb-2 md:mb-0">
            <div class="relative w-full md:w-80">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </span>
                <input type="text" class="form-input w-full pl-10 px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search description, email, action..." v-model="filters.search" @input="onFilterChange">
            </div>
        </div>
        <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto md:ml-auto">
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto lg:w-[220px]" v-model="filters.action_filter" @change="onFilterChange">
                <option value="">All Actions</option>
                <option v-for="a in availableActions" :key="a" :value="a">{{ a }}</option>
            </select>
            <input type="date" class="form-input px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto" v-model="filters.date_from" @change="onFilterChange">
            <input type="date" class="form-input px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto" v-model="filters.date_to" @change="onFilterChange">
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-center">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                    <th scope="col" class="px-4 py-2 text-center">Date/Time</th>
                    <th scope="col" class="px-4 py-2 text-center">Admin</th>
                    <th scope="col" class="px-4 py-2 text-center">Action</th>
                    <th scope="col" class="px-4 py-2 text-center">Entity</th>
                    <th scope="col" class="px-4 py-2 text-center">Description</th>
                    <th scope="col" class="px-4 py-2 text-center">IP Address</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="log in logs" :key="log.id" class="border-b border-gray-200 dark:border-gray-600">
                    <td class="px-4 py-2 whitespace-nowrap text-gray-800 dark:text-gray-200 text-center">{{ formatTime(log.created_at) }}</td>
                    <td class="px-4 py-2 text-gray-800 dark:text-gray-200 text-center">{{ log.user_email || 'System' }}</td>
                    <td class="px-4 py-2 text-center">
                        <span class="inline-block px-2 py-1 rounded text-xs font-semibold bg-blue-100 text-blue-700 dark:bg-blue-800 dark:text-blue-200">{{ log.action }}</span>
                    </td>
                    <td class="px-4 py-2 text-gray-800 dark:text-gray-200 text-center">
                        <span v-if="log.entity_type">{{ log.entity_type }}<span v-if="log.entity_id"> #{{ log.entity_id }}</span></span>
                        <span v-else>&mdash;</span>
                    </td>
                    <td class="px-4 py-2 text-gray-800 dark:text-gray-200 text-center max-w-xs truncate mx-auto" :title="log.description">{{ log.description }}</td>
                    <td class="px-4 py-2 text-gray-500 dark:text-gray-400 whitespace-nowrap text-center">{{ log.ip_address }}</td>
                </tr>
                <tr v-if="!loading && logs.length === 0">
                    <td colspan="6" class="py-12 text-center text-gray-500 dark:text-gray-400">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fas fa-clipboard-list text-4xl text-gray-300 mb-2"></i>
                            <span class="text-lg text-gray-400">No audit log entries found</span>
                        </div>
                    </td>
                </tr>
                <tr v-if="loading">
                    <td colspan="6" class="py-12 text-center text-gray-500 dark:text-gray-400">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-blue-600 border-t-transparent"></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="flex flex-col md:flex-row md:items-center justify-center md:justify-between mt-4 gap-2">
        <div class="text-gray-600 dark:text-gray-300 text-sm text-center md:text-left w-full md:w-auto flex justify-center md:justify-start">
            Showing {{ logs.length === 0 ? 0 : (currentPage - 1) * perPage + 1 }} to {{ Math.min(currentPage * perPage, total) }} of {{ total }} entries
        </div>
        <div class="flex gap-1 justify-center md:justify-start items-center">
            <button class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 disabled:opacity-40" :disabled="!paginationGroup.hasPrevGroup" @click="goToPrevGroup" title="Previous 5 pages">
                <i class="fas fa-angle-double-left"></i>
            </button>
            <button class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 disabled:opacity-40" :disabled="currentPage === 1" @click="goToPage(currentPage - 1)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button v-for="page in paginationGroup.pages" :key="page" class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-blue-100 dark:hover:bg-blue-900 min-w-[40px] transition-colors" :class="{'bg-blue-600 text-white dark:bg-blue-500 dark:text-white': page === currentPage}" @click="goToPage(page)">{{ page }}</button>
            <button class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 disabled:opacity-40" :disabled="currentPage === totalPages" @click="goToPage(currentPage + 1)">
                <i class="fas fa-chevron-right"></i>
            </button>
            <button class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 disabled:opacity-40" :disabled="!paginationGroup.hasNextGroup" @click="goToNextGroup" title="Next 5 pages">
                <i class="fas fa-angle-double-right"></i>
            </button>
        </div>
    </div>
</div>

@endverbatim
@endsection
