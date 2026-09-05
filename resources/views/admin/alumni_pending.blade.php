@extends('layouts.admin')
@section('content')
@verbatim
<div class="bg-white dark:bg-gray-700 rounded-xl shadow-lg p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <h2 class="text-2xl font-bold mb-2 md:mb-0 text-gray-800 dark:text-gray-100">Pending Alumni</h2>
    </div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-start gap-4 mb-4">
        <div class="flex items-center gap-2 w-full md:w-auto mb-2 md:mb-0">
            <div class="relative w-full md:w-80">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </span>
                <input type="text" class="form-input w-full pl-10 px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search alumni..." v-model="searchQuery" @input="filterAlumni">
            </div>
        </div>
        <div class="flex flex-col sm:flex-row gap-2 w-full md:ml-auto md:w-auto">
            <select v-if="isSuperadmin" class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-[150px] md:w-[180px]" v-model="filters.campus_id">
                <option value="">All Campuses</option>
                <option v-for="c in campuses" :key="c.campus_id" :value="c.campus_id">{{ c.name }}</option>
            </select>
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-[150px] md:w-[180px]" v-model="filters.college">
                <option value="">All Colleges</option>
                <option v-for="college in filterColleges" :key="college" :value="college">{{ college }}</option>
            </select>
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-[150px] md:w-[180px] lg:w-[250px]" v-model="filters.course" :disabled="!filters.college">
                <option value="">All Programs</option>
                <option v-for="course in filterCourseOptions" :key="course" :value="course">{{ course }}</option>
            </select>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left data-table">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                    <th scope="col" class="px-4 py-2 text-center">Name</th>
                    <th scope="col" class="px-4 py-2 text-center">Email</th>
                    <th scope="col" class="px-4 py-2 text-center">Gender</th>
                    <th scope="col" class="px-4 py-2 text-center">Year Graduated</th>
                    <th scope="col" class="px-4 py-2 text-center">Program</th>
                    <th scope="col" class="px-4 py-2 text-center">College</th>
                    <th scope="col" class="px-4 py-2 text-center">Province</th>
                    <th scope="col" class="px-4 py-2 text-center">City/Municipality</th>
                    <th scope="col" class="px-4 py-2 text-center">Status</th>
                    <th scope="col" class="px-4 py-2 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="alumni in paginatedAlumni" :key="alumni.alumni_id" class="border-b border-gray-200 dark:border-gray-600 text-gray-800 dark:text-gray-200">
                    <td class="px-4 py-2 font-semibold text-center">{{ alumni.first_name }} {{ alumni.middle_name }} {{ alumni.last_name }}</td>
                    <td class="px-4 py-2 text-center">{{ alumni.email }}</td>
                    <td class="px-4 py-2 text-center">{{ alumni.gender }}</td>
                    <td class="px-4 py-2 text-center">{{ alumni.year_graduated }}</td>
                    <td class="px-4 py-2 text-center">{{ alumni.course }}</td>
                    <td class="px-4 py-2 text-center">{{ alumni.college }}</td>
                    <td class="px-4 py-2 text-center">{{ alumni.province }}</td>
                    <td class="px-4 py-2 text-center">{{ alumni.city }}</td>
                    <td class="px-4 py-2 text-center">
                        <span :class="['inline-block px-3 py-1.5 rounded-full text-xs font-semibold shadow-sm', alumni.status === 'Active' ? 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200' : alumni.status === 'Pending' ? 'bg-amber-100 text-amber-700 dark:bg-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-700' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200']">
                            {{ alumni.status }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-center">
                        <div class="relative inline-block text-left">
                            <button @click="toggleActionDropdown(alumni.alumni_id)" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none text-gray-600 dark:text-gray-300">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div v-if="actionDropdown === alumni.alumni_id" class="origin-top-right absolute right-0 mt-2 w-32 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5 z-10">
                                <div class="py-1" @click="actionDropdown = null">
                                    <a href="#" role="button" class="block px-4 py-2 text-sm text-green-600 hover:bg-green-100 dark:hover:bg-green-800" @click.prevent="approveAlumni(alumni)"><i class="fas fa-check-circle mr-2"></i>Approve</a>
                                    <a href="#" role="button" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" @click.prevent="viewAlumniDetails(alumni)"><i class="fas fa-eye mr-2"></i>View Profile</a>
                                    <a href="#" role="button" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 dark:hover:bg-red-800" @click.prevent="confirmDelete(alumni)"><i class="fas fa-trash mr-2"></i>Delete</a>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr v-if="filteredAlumni.length === 0">
                    <td colspan="10" class="text-center py-12">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fas fa-user-graduate text-6xl text-gray-300 dark:text-gray-600 mb-4"></i>
                            <p class="text-gray-500 dark:text-gray-300 text-lg font-medium">No pending alumni found</p>
                            <p class="text-gray-400 dark:text-gray-500 text-sm">Try adjusting your search or filters</p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mt-4 gap-2">
        <div class="text-gray-600 dark:text-gray-300 text-sm text-center md:text-left">
            Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredAlumni.length) }} of {{ filteredAlumni.length }} entries
        </div>
        <div class="flex gap-1 justify-center items-center">
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
<!-- View Alumni Details Modal -->
<div v-if="showViewModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true" data-modal="view-modal">
    <div v-if="isLoading" class="text-center py-4 text-white">Loading alumni details...</div>
    <div v-else class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-xl mx-2 p-0 relative max-h-[95vh] overflow-y-auto">
        <button class="absolute top-2 right-2 flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full shadow hover:bg-gray-200 dark:hover:bg-gray-600 transition text-base font-semibold z-20" @click="closeViewModal" aria-label="Close">
            <i class="fas fa-times"></i> <span>Close</span>
        </button>
        <div class="rounded-t-2xl bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400 dark:from-blue-900 dark:via-blue-800 dark:to-blue-700 px-0 pt-6 pb-8 flex flex-col items-center relative">
            <div class="absolute top-4 left-4 bg-white dark:bg-gray-700 rounded-full p-2 shadow-lg">
                <i class="fas fa-user-graduate text-blue-600 dark:text-blue-300 text-2xl"></i>
            </div>
            <img v-if="viewAlumniData.profile_picture" :src="viewAlumniData.profile_picture" alt="Alumni Photo" class="w-28 h-28 rounded-full object-cover border-4 border-white dark:border-gray-700 shadow-xl mb-2 mt-2">
            <div v-else class="w-28 h-28 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center border-4 border-white dark:border-gray-700 shadow-xl mb-2 mt-2">
                <i class="fas fa-user-graduate text-4xl text-gray-400"></i>
            </div>
            <h3 class="text-3xl font-extrabold text-white drop-shadow-lg mb-1 text-center">{{ viewAlumniData.first_name }} {{ viewAlumniData.middle_name }} {{ viewAlumniData.last_name }}</h3>
            <span :class="['inline-block mt-1 px-3 py-1 rounded-full text-xs font-semibold shadow', viewAlumniData.status === 'Active' ? 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200' : viewAlumniData.status === 'Pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-800 dark:text-yellow-200' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200']">{{ viewAlumniData.status }}</span>
        </div>
        <div class="px-6 py-6">
            <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <i class="fas fa-info-circle text-blue-500 dark:text-blue-300"></i> <span>Personal Details</span>
            </h4>
            <div class="grid grid-cols-1 gap-3 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <i class="fas fa-envelope text-blue-500 dark:text-blue-300"></i>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Email:</span>
                    <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewAlumniData.email || 'N/A' }}</span>
                </div>
                <div v-if="viewAlumniData.secondary_email" class="flex items-center gap-3">
                    <i class="fas fa-envelope-open text-blue-500 dark:text-blue-300"></i>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Secondary Email:</span>
                    <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewAlumniData.secondary_email }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <i class="fas fa-venus-mars text-blue-500 dark:text-blue-300"></i>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Gender:</span>
                    <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewAlumniData.gender || 'N/A' }}</span>
                </div>
                <div v-if="viewAlumniData.birthdate" class="flex items-center gap-3">
                    <i class="fas fa-birthday-cake text-blue-500 dark:text-blue-300"></i>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Birthdate:</span>
                    <span class="ml-1 text-gray-700 dark:text-gray-200">{{ formatDate(viewAlumniData.birthdate) }}</span>
                </div>
                <div v-if="viewAlumniData.contact" class="flex items-center gap-3">
                    <i class="fas fa-phone text-blue-500 dark:text-blue-300"></i>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Contact:</span>
                    <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewAlumniData.contact }}</span>
                </div>
                <div v-if="viewAlumniData.civil_status" class="flex items-center gap-3">
                    <i class="fas fa-ring text-blue-500 dark:text-blue-300"></i>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Civil Status:</span>
                    <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewAlumniData.civil_status }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <i class="fas fa-calendar-alt text-blue-500 dark:text-blue-300"></i>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Year Graduated:</span>
                    <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewAlumniData.year_graduated || 'N/A' }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <i class="fas fa-university text-blue-500 dark:text-blue-300"></i>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">College:</span>
                    <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewAlumniData.college || 'N/A' }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <i class="fas fa-book text-blue-500 dark:text-blue-300"></i>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Program:</span>
                    <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewAlumniData.course || 'N/A' }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <i class="fas fa-map-marker-alt text-blue-500 dark:text-blue-300"></i>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Province:</span>
                    <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewAlumniData.province || 'N/A' }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <i class="fas fa-city text-blue-500 dark:text-blue-300"></i>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">City/Municipality:</span>
                    <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewAlumniData.city || 'N/A' }}</span>
                </div>
            </div>

            <div class="my-6 border-t border-gray-200 dark:border-gray-700"></div>
            <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <i class="fas fa-file-alt text-blue-500 dark:text-blue-300"></i> <span>Documents Submitted</span>
            </h4>
            <div v-if="viewAlumniData.verification_document" class="space-y-3 mb-4">
                <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                    <div class="flex items-center gap-2">
                        <i :class="getFileIcon(viewAlumniData.verification_document)"></i>
                        <span class="font-medium text-gray-700 dark:text-gray-200">Verification Document</span>
                    </div>
                    <div class="flex gap-2">
                        <a :href="'/uploads/documents/' + viewAlumniData.verification_document" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-300 dark:hover:text-blue-200 flex items-center gap-1">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <a :href="'/uploads/documents/' + viewAlumniData.verification_document" download class="text-green-600 hover:text-green-800 dark:text-green-300 dark:hover:text-green-200 flex items-center gap-1">
                            <i class="fas fa-download"></i> Download
                        </a>
                    </div>
                </div>
            </div>
            <div v-else class="text-gray-500 dark:text-gray-400 mb-4">No documents submitted.</div>

            <div class="flex flex-col md:flex-row gap-3 mt-6 w-full">
                <button @click="contactAlumni(viewAlumniData)" class="flex-1 px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center justify-center gap-2 text-sm sm:text-base">
                    <i class="fas fa-envelope"></i> Contact
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Delete Confirmation Modal -->
<div v-if="showDeleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" role="dialog" aria-modal="true">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Confirm Delete</h3>
                <button @click="showDeleteModal = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p class="text-gray-600 dark:text-gray-300 mb-6">Are you sure you want to delete this alumni? This action cannot be undone.</p>
            <div class="flex justify-end space-x-3">
                <button @click="showDeleteModal = false" class="px-4 py-2 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                    Cancel
                </button>
                <button @click="confirmDeleteAlumni" class="px-4 py-2 rounded-md bg-red-600 text-white hover:bg-red-700 transition-colors">
                    Delete
                </button>
            </div>
        </div>
    </div>
</div>

@endverbatim
@endsection
