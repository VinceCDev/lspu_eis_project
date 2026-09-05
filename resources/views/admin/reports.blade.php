@extends('layouts.admin')
@section('content')
@verbatim
<div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <h1 class="text-4xl font-bold text-gray-800 dark:text-gray-200">General Reports</h1>
    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
        <select v-if="isSuperadmin" v-model="selectedCampusId" @change="onCampusChange" class="w-full sm:w-72 border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All Campuses (combined)</option>
            <option v-for="c in campuses" :key="c.campus_id" :value="c.campus_id">{{ c.name }} ({{ c.type }})</option>
        </select>
        <select v-model="selectedYear" @change="onYearChange" class="w-full sm:w-48 border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All Years Graduated</option>
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
    </div>
</div>

<!-- Email Report Modal -->
<div v-if="showEmailReportModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
        <h3 class="text-lg font-bold mb-1 text-gray-800 dark:text-gray-100">Email Report</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Choose the college scope and where to send the summary.</p>
        <p v-if="isSuperadmin" class="text-xs text-gray-400 dark:text-gray-500 -mt-3 mb-4">Campus scope: <strong>{{ selectedCampusName }}</strong> (set via the Campus filter above)</p>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">College</label>
        <select v-model="emailReportCollege" class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 mb-4 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">All Colleges</option>
            <option v-for="college in colleges" :key="college" :value="college">{{ college }}</option>
        </select>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Recipient Email</label>
        <input type="email" v-model="emailReportRecipient" placeholder="dean@lspu.edu.ph"
            class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 mb-4 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
        <div class="flex justify-end gap-2">
            <button @click="showEmailReportModal = false" class="px-4 py-2 rounded bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 hover:bg-gray-400 dark:hover:bg-gray-700">Cancel</button>
            <button @click="sendReportEmail" :disabled="emailReportSending" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                {{ emailReportSending ? 'Sending...' : 'Send' }}
            </button>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Employment Summary</h3>
            <i class="fas fa-chart-pie text-blue-500 text-xl"></i>
        </div>
        <p class="text-gray-600 dark:text-gray-400 mb-4">Comprehensive employment statistics by program</p>
        <div class="flex space-x-2 mb-2">
            <button @click="exportEmploymentSummary('excel')" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                <i class="fas fa-file-excel mr-2"></i>Export Excel
            </button>
        </div>
        <div class="flex space-x-2">
            <button @click="openEmailReportModal" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                <i class="fas fa-paper-plane mr-2"></i>Email Report
            </button>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Detailed Employment</h3>
            <i class="fas fa-table text-green-500 text-xl"></i>
        </div>
        <p class="text-gray-600 dark:text-gray-400 mb-4">Employment status, sector, and location by college</p>
        <div class="flex space-x-2 mb-2">
            <button @click="exportDetailedEmployment('excel')" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                <i class="fas fa-file-excel mr-2"></i>Export Excel
            </button>
        </div>
        <div class="flex space-x-2">
            <button @click="openEmailReportModal" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                <i class="fas fa-paper-plane mr-2"></i>Email Report
            </button>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Industry Analysis</h3>
            <i class="fas fa-industry text-purple-500 text-xl"></i>
        </div>
        <p class="text-gray-600 dark:text-gray-400 mb-4">Comprehensive analysis of graduate employment distribution</p>
        <div class="flex space-x-2 mb-2">
            <button @click="exportIndustryAnalysis('excel')" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                <i class="fas fa-file-excel mr-2"></i>Export Excel
            </button>
        </div>
        <div class="flex space-x-2">
            <button @click="openEmailReportModal" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm transition-colors">
                <i class="fas fa-paper-plane mr-2"></i>Email Report
            </button>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                <i class="fas fa-graduation-cap text-blue-600 dark:text-blue-400 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Graduates</p>
                <p v-if="loading" class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    <i class="fas fa-spinner fa-spin"></i>
                </p>
                <p v-else class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ summaryStats.totalGraduates }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                <i class="fas fa-briefcase text-green-600 dark:text-green-400 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Employed</p>
                <p v-if="loading" class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    <i class="fas fa-spinner fa-spin"></i>
                </p>
                <p v-else class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ summaryStats.employedCount }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900">
                <i class="fas fa-percentage text-yellow-600 dark:text-yellow-400 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Employment Rate</p>
                <p v-if="loading" class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    <i class="fas fa-spinner fa-spin"></i>
                </p>
                <p v-else class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ summaryStats.employmentRate }}%</p>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900">
                <i class="fas fa-check-circle text-purple-600 dark:text-purple-400 text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Job Match Rate</p>
                <p v-if="loading" class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    <i class="fas fa-spinner fa-spin"></i>
                </p>
                <p v-else class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ summaryStats.jobMatchRate }}%</p>
            </div>
        </div>
    </div>
</div>

<div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6">Employment by Program</h2>
    <div v-if="loading" class="flex justify-center items-center py-8">
        <i class="fas fa-spinner fa-spin text-2xl text-gray-500"></i>
        <span class="ml-2 text-gray-500">Loading program statistics...</span>
    </div>
    <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Program</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Graduates</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employed</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Employment Rate</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Job Match Rate</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-700 divide-y divide-gray-200 dark:divide-gray-600">
                <tr v-for="program in programStats" :key="program.course" class="hover:bg-gray-50 dark:hover:bg-gray-600">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ program.course }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ program.total_graduates }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ program.employed_count }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ calculatePercentage(program.employed_count, program.total_graduates) }}%
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ calculatePercentage(program.related_job_count, program.employed_count) }}%
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

@endverbatim
@endsection
