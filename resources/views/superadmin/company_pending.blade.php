@extends('layouts.admin')
@section('content')
@verbatim
<div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm main-shadow p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <h2 class="text-2xl font-bold mb-2 md:mb-0 text-gray-800 dark:text-gray-100">Company Profiles</h2>
    </div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
        <div class="flex items-center gap-2 w-full md:w-auto mb-2 md:mb-0">
            <div class="relative w-full md:w-80">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </span>
                <input type="text" class="form-input w-full pl-10 px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search company..." v-model="searchQuery" @input="filterCompanies">
            </div>
        </div>
        <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full md:w-auto">
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto" v-model="filters.industry_type" @change="filterCompanies">
                <option value="">All Types</option>
                <option v-for="industry_type in uniqueIndustryTypes" :key="industry_type">{{ industry_type }}</option>
            </select>
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto" v-model="filters.nature_of_business" @change="filterCompanies">
                <option value="">All Nature</option>
                <option v-for="nature_of_business in uniqueNatureOfBusiness" :key="nature_of_business">{{ nature_of_business }}</option>
            </select>
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto" v-model="filters.accreditation_status" @change="filterCompanies">
                <option value="">Status</option>
                <option v-for="accreditation_status in uniqueAccreditationStatus" :key="accreditation_status">{{ accreditation_status }}</option>
            </select>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-center data-table">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                    <th scope="col" class="px-4 py-2">Name of Company</th>
                    <th scope="col" class="px-4 py-2">Location</th>
                    <th scope="col" class="px-4 py-2">Contact Email</th>
                    <th scope="col" class="px-4 py-2">Industry Type</th>
                    <th scope="col" class="px-4 py-2">Nature of Business</th>
                    <th scope="col" class="px-4 py-2">Status</th>
                    <th scope="col" class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="employer in paginatedEmployers" :key="employer.user_id" class="border-b border-gray-200 dark:border-gray-600">
                    <td class="px-4 py-2 font-semibold text-gray-700 dark:text-gray-200">{{ employer.company_name }}</td>
                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ employer.company_location }}</td>
                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ employer.contact_email }}</td>
                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ employer.industry_type }}</td>
                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ employer.nature_of_business }}</td>
                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">
                        <span :class="[
                            'inline-block px-2 py-1 rounded text-xs font-semibold',
                            employer.status === 'Approved'
                                ? 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200'
                                : 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200'
                        ]">
                            {{ employer.status }}
                        </span>
                    </td>
                    <td class="px-4 py-2">
                        <div class="relative inline-block text-left">
                            <button @click="toggleActionDropdown(employer.user_id)" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none text-gray-500 dark:text-gray-200">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div v-if="actionDropdown === employer.user_id" class="origin-top-right absolute right-0 mt-2 w-32 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5 z-50 drop-shadow-lg">
                                <div class="py-1" @click="actionDropdown = null">
                                    <a href="#" role="button" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" @click.prevent="viewEmployer(employer)"><i class="fas fa-eye mr-2"></i>View</a>
                                    <a href="#" role="button" class="block px-4 py-2 text-sm text-green-600 hover:bg-green-100 dark:hover:bg-green-800" @click.prevent="approveEmployer(employer)"><i class="fas fa-check-circle mr-2"></i>Approve</a>
                                    <a href="#" role="button" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 dark:hover:bg-red-800" @click.prevent="confirmDeleteModal(employer)"><i class="fas fa-trash-alt mr-2"></i>Delete</a>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr v-if="filteredEmployers.length === 0">
                    <td colspan="7" class="py-12 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fas fa-building text-4xl text-gray-300 mb-2"></i>
                            <span class="text-lg text-gray-400">No company register found</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mt-4 gap-2">
        <div class="text-gray-600 dark:text-gray-300 text-sm">
            Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredEmployers.length) }} of {{ filteredEmployers.length }} entries
        </div>
        <div class="flex gap-1 items-center">
            <button class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors" :disabled="!paginationGroup.hasPrevGroup" @click="goToPrevGroup" title="Previous 5 pages">
                <i class="fas fa-angle-double-left"></i>
            </button>
            <button class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" :disabled="currentPage === 1" @click="prevPage">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button v-for="page in paginationGroup.pages" :key="page" class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-blue-100 dark:hover:bg-blue-900 min-w-[40px] transition-colors" :class="{'bg-blue-600 text-white dark:bg-blue-500 dark:text-white': page === currentPage}" @click="goToPage(page)">{{ page }}</button>
            <button class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" :disabled="currentPage === totalPages" @click="nextPage">
                <i class="fas fa-chevron-right"></i>
            </button>
            <button class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors" :disabled="!paginationGroup.hasNextGroup" @click="goToNextGroup" title="Next 5 pages">
                <i class="fas fa-angle-double-right"></i>
            </button>
        </div>
    </div>
</div>
<!-- Delete Confirmation Modal for Company -->
<div v-if="showDeleteModal" class="fixed inset-0 z-[220] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
        <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100">Confirm Delete</h3>
        <p class="mb-6 text-gray-700 dark:text-gray-200">Are you sure you want to delete <strong>{{ employerToDelete && employerToDelete.company_name }}</strong>? This cannot be undone.</p>
        <div class="flex justify-end gap-2">
            <button class="px-4 py-2 rounded bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 hover:bg-gray-400 dark:hover:bg-gray-700" :disabled="deletingEmployer" @click="showDeleteModal = false">Cancel</button>
            <button class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700 transition disabled:opacity-50" :disabled="deletingEmployer" @click="confirmDeleteEmployer">{{ deletingEmployer ? 'Deleting...' : 'Delete' }}</button>
        </div>
    </div>
</div>
<!-- View Company Details Modal -->
<div v-if="showViewModal" class="fixed inset-0 z-[210] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-xl mx-2 p-0 relative max-h-[95vh] overflow-y-auto">
        <button class="absolute top-3 right-3 flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full shadow hover:bg-gray-200 dark:hover:bg-gray-600 transition text-base font-semibold z-20" @click="showViewModal = false">
            <i class="fas fa-times"></i> <span>Close</span>
        </button>
        <div class="rounded-t-2xl bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400 dark:from-blue-900 dark:via-blue-800 dark:to-blue-700 px-0 pt-6 pb-8 flex flex-col items-center relative">
            <div class="absolute top-4 left-4 bg-white dark:bg-gray-700 rounded-full p-2 shadow-lg">
                <i class="fas fa-building text-blue-600 dark:text-blue-300 text-2xl"></i>
            </div>
            <img :src="viewedEmployer.logo" alt="Company Logo" class="w-28 h-28 rounded-full object-cover border-4 border-white dark:border-gray-700 shadow-xl mb-2 mt-2">
            <h3 class="text-3xl font-extrabold text-white drop-shadow-lg mb-1 text-center">{{ viewedEmployer.company_name }}</h3>
            <span
                :class="[
                    'inline-block mt-1 px-3 py-1 rounded-full text-xs font-semibold shadow',
                    viewedEmployer.status === 'Active'
                        ? 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200'
                        : 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200'
                ]"
            >{{ viewedEmployer.status }}</span>
        </div>
        <div class="px-6 py-6">
            <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-info-circle text-blue-500 dark:text-blue-300"></i> <span>Company Details</span></h4>
            <div class="grid grid-cols-1 gap-3 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm">
                <div class="flex items-center gap-3"><i class="fas fa-map-marker-alt text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Location:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewedEmployer.company_location }}</span></div>
                <div class="flex items-center gap-3"><i class="fas fa-envelope text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Contact Email:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewedEmployer.contact_email }}</span></div>
                <div class="flex items-center gap-3"><i class="fas fa-industry text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Industry Type:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewedEmployer.industry_type }}</span></div>
                <div class="flex items-center gap-3"><i class="fas fa-briefcase text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Nature of Business:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewedEmployer.nature_of_business }}</span></div>
                <div class="flex items-center gap-3"><i class="fas fa-certificate text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Accreditation Status:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewedEmployer.accreditation_status || 'N/A' }}</span></div>
            </div>
            <div class="my-6 border-t border-gray-200 dark:border-gray-700"></div>
            <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-file-alt text-blue-500 dark:text-blue-300"></i> <span>Documents</span></h4>
            <div v-if="viewedEmployer.documents && viewedEmployer.documents.length" class="space-y-3">
                <div v-for="doc in viewedEmployer.documents" :key="doc.name" class="flex items-center gap-3 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/40 shadow hover:bg-blue-100 dark:hover:bg-blue-800/60 transition">
                    <i class="fas fa-file-pdf text-red-500 text-xl"></i>
                    <a :href="doc.url" target="_blank" class="text-blue-700 dark:text-blue-300 underline font-medium hover:text-blue-900 dark:hover:text-white transition">{{ doc.name }}</a>
                </div>
            </div>
            <div v-else class="text-gray-500 dark:text-gray-400">No documents submitted.</div>
        </div>
    </div>
</div>

@endverbatim
@endsection
