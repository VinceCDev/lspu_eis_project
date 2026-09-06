@extends('layouts.admin')
@section('content')
@verbatim
<div class="bg-white dark:bg-gray-700 rounded-xl shadow-lg p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <h2 class="text-2xl font-bold mb-2 md:mb-0 text-gray-800 dark:text-gray-100">Alumni Profiles</h2>
        <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full md:w-auto">
            <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition w-full sm:w-auto justify-center" @click="openAddModal">
                <i class="fas fa-plus"></i> Add Alumni
            </button>
            <button class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition w-full sm:w-auto justify-center" @click="exportToExcel">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <button class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition w-full sm:w-auto justify-center" @click="exportToPDF">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
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
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-[150px] md:w-[180px]" v-model="filters.college" @change="updateFilterCourseOptions">
                <option value="">All Colleges</option>
                <option v-for="college in filterColleges" :key="college" :value="college">{{ college }}</option>
            </select>
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-[150px] md:w-[180px] lg:w-[250px]" v-model="filters.course" :disabled="!filters.college">
                <option value="">All Programs</option>
                <option v-for="course in filterCourseOptions" :key="course" :value="course">{{ course }}</option>
            </select>
            <select v-if="!isSuperadmin" class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-[150px] md:w-[180px] lg:w-[210px]" v-model="filters.status">
                <option value="">All Status</option>
                <option value="Active">Active</option>
                <option value="Pending">Pending</option>
                <option value="Inactive">Inactive</option>
            </select>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-center data-table">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                    <th scope="col" class="px-4 py-2">Name</th>
                    <th scope="col" class="px-4 py-2">Email</th>
                    <th scope="col" class="px-4 py-2">Gender</th>
                    <th scope="col" class="px-4 py-2">Year Graduated</th>
                    <th scope="col" class="px-4 py-2">Program</th>
                    <th scope="col" class="px-4 py-2">College</th>
                    <th scope="col" class="px-4 py-2">Province</th>
                    <th scope="col" class="px-4 py-2">City/Municipality</th>
                    <th scope="col" class="px-4 py-2">Status</th>
                    <th scope="col" class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="alumni in paginatedAlumni" :key="alumni.id" class="border-b border-gray-200 dark:border-gray-600 text-center text-gray-800 dark:text-gray-200">
                    <td class="px-4 py-2 font-semibold">{{ alumni.first_name }} {{ alumni.middle_name }} {{ alumni.last_name }}</td>
                    <td class="px-4 py-2">{{ alumni.email }}</td>
                    <td class="px-4 py-2">{{ alumni.gender }}</td>
                    <td class="px-4 py-2">{{ alumni.year_graduated }}</td>
                    <td class="px-4 py-2">{{ alumni.course }}</td>
                    <td class="px-4 py-2">{{ alumni.college }}</td>
                    <td class="px-4 py-2">{{ alumni.province }}</td>
                    <td class="px-4 py-2">{{ alumni.city }}</td>
                    <td class="px-4 py-2">
                        <span :class="['inline-block px-2 py-1 rounded text-xs font-semibold', alumni.status === 'Active' ? 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200' : alumni.status === 'Pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-800 dark:text-yellow-200' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200']">
                            {{ alumni.status }}
                        </span>
                    </td>
                    <td class="px-4 py-2">
                        <div class="relative inline-block text-left">
                            <button @click="toggleActionDropdown(alumni.id, $event)" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none text-gray-600 dark:text-gray-300">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <teleport to="body">
                                <div v-if="actionDropdown === alumni.id" class="teleported-action-dropdown fixed w-32 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5 z-[300]" :style="{ top: dropdownPosition.top + 'px', left: dropdownPosition.left + 'px' }">
                                    <div class="py-1" @click="actionDropdown = null">
                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" @click.prevent="viewAlumniDetails(alumni)"><i class="fas fa-eye mr-2"></i>View</a>
                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-yellow-600 hover:bg-yellow-100 dark:hover:bg-yellow-800" @click.prevent="editAlumni(alumni)"><i class="fas fa-edit mr-2"></i>Edit</a>
                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 dark:hover:bg-red-800" @click.prevent="confirmDelete(alumni)"><i class="fas fa-trash mr-2"></i>Delete</a>
                                    </div>
                                </div>
                            </teleport>
                        </div>
                    </td>
                </tr>
                <tr v-if="filteredAlumni.length === 0">
                    <td colspan="10" class="py-12 text-center">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fas fa-user-graduate text-4xl text-gray-300 mb-2"></i>
                            <span class="text-lg text-gray-400">No alumni found</span>
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
            <button class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors" :disabled="currentPage === 1" @click="prevPage" title="Previous page">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button v-for="page in paginationGroup.pages" :key="page" class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-blue-100 dark:hover:bg-blue-900 min-w-[40px] transition-colors" :class="{'bg-blue-600 text-white dark:bg-blue-500 dark:text-white': page === currentPage}" @click="goToPage(page)">
                {{ page }}
            </button>
            <button class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors" :disabled="currentPage === totalPages" @click="nextPage" title="Next page">
                <i class="fas fa-chevron-right"></i>
            </button>
            <button class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors" :disabled="!paginationGroup.hasNextGroup" @click="goToNextGroup" title="Next 5 pages">
                <i class="fas fa-angle-double-right"></i>
            </button>
        </div>
    </div>
</div>
<!-- Add/Edit Alumni Modal -->
<div v-if="showAlumniModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true" data-modal="alumni-modal">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl mx-2 p-6 relative max-h-[90vh] overflow-y-auto">
        <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="closeAlumniModal" aria-label="Close"><i class="fas fa-times"></i></button>
        <div class="mt-3 text-left w-full">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                {{ selectedAlumni ? 'Edit Alumni' : 'Add New Alumni' }}
            </h3>
            <form @submit.prevent="selectedAlumni ? updateAlumni() : addAlumni()">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name*</label>
                        <input type="text" v-model="alumniForm.first_name" required autocomplete="off" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white" id="first-name-input">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Middle Name</label>
                        <input type="text" v-model="alumniForm.middle_name" autocomplete="off" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name*</label>
                        <input type="text" v-model="alumniForm.last_name" required autocomplete="off" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email*</label>
                        <input type="email" v-model="alumniForm.email" required autocomplete="off" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Secondary Email</label>
                        <input type="email" v-model="alumniForm.secondary_email" autocomplete="off" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Gender*</label>
                        <select v-model="alumniForm.gender" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Year Graduated*</label>
                        <input type="text" v-model="alumniForm.year_graduated" required autocomplete="off" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                    </div>
                    <div v-if="isSuperadmin">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Campus*</label>
                        <select v-model="alumniForm.campus_id" required @change="onFormCampusChange" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Campus</option>
                            <option v-for="c in campuses" :key="c.campus_id" :value="c.campus_id">{{ c.name }} ({{ c.type }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">College*</label>
                        <select v-model="alumniForm.college" required @change="updateCourseOptions" :disabled="isSuperadmin && !alumniForm.campus_id" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select College</option>
                            <option v-for="college in formColleges" :key="college" :value="college">{{ college }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Program*</label>
                        <select v-model="alumniForm.course" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Course</option>
                            <option v-for="course in courseOptions" :key="course">{{ course }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Province*</label>
                        <select v-model="alumniForm.province" required @change="fetchCities" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select Province</option>
                            <option v-for="province in provinces" :key="province.code" :value="province.name">{{ province.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">City/Municipality*</label>
                        <select v-model="alumniForm.city" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="">Select City/Municipality</option>
                            <option v-for="city in cities" :key="city.code" :value="city.name">{{ city.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status*</label>
                        <select v-model="alumniForm.status" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <option value="Active">Active</option>
                            <option value="Pending">Pending</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm">
                        {{ selectedAlumni ? 'Update Alumni' : 'Add Alumni' }}
                    </button>
                    <button type="button" @click="closeAlumniModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
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
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Programs:</span>
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
                <i class="fas fa-graduation-cap text-blue-500 dark:text-blue-300"></i> <span>Education</span>
            </h4>
            <div v-if="viewAlumniData.education && viewAlumniData.education.length" class="space-y-4 mb-4">
                <div v-for="edu in viewAlumniData.education" :key="edu.education_id" class="grid grid-cols-1 gap-3 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-certificate text-blue-500 dark:text-blue-300"></i>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">Degree:</span>
                        <span class="ml-1 text-gray-700 dark:text-gray-200">{{ edu.degree || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i class="fas fa-school text-blue-500 dark:text-blue-300"></i>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">School:</span>
                        <span class="ml-1 text-gray-700 dark:text-gray-200">{{ edu.school || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i class="fas fa-calendar-alt text-blue-500 dark:text-blue-300"></i>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">Period:</span>
                        <span class="ml-1 text-gray-700 dark:text-gray-200">
                            {{ formatDate(edu.start_date) }} - {{ edu.current ? 'Present' : formatDate(edu.end_date) }}
                        </span>
                    </div>
                </div>
            </div>
            <div v-else class="text-gray-500 dark:text-gray-400 mb-4">No education information available.</div>

            <div class="my-6 border-t border-gray-200 dark:border-gray-700"></div>
            <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <i class="fas fa-briefcase text-blue-500 dark:text-blue-300"></i> <span>Employment History</span>
            </h4>
            <div v-if="viewAlumniData.experiences && viewAlumniData.experiences.length" class="space-y-4 mb-4">
                <div v-for="exp in viewAlumniData.experiences" :key="exp.experience_id" class="grid grid-cols-1 gap-3 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-building text-blue-500 dark:text-blue-300"></i>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">Company Name:</span>
                        <span class="ml-1 text-gray-700 dark:text-gray-200">{{ exp.company || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i class="fas fa-user-tie text-blue-500 dark:text-blue-300"></i>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">Position:</span>
                        <span class="ml-1 text-gray-700 dark:text-gray-200">{{ exp.title || 'N/A' }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i class="fas fa-calendar-alt text-blue-500 dark:text-blue-300"></i>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">Period:</span>
                        <span class="ml-1 text-gray-700 dark:text-gray-200">
                            {{ formatDate(exp.start_date) }} - {{ exp.current ? 'Present' : formatDate(exp.end_date) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-3">
                        <i class="fas fa-clipboard-check text-blue-500 dark:text-blue-300"></i>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">Employment Status:</span>
                        <span class="ml-1 text-gray-700 dark:text-gray-200">{{ exp.employment_status || 'N/A' }}</span>
                    </div>
                    <div v-if="exp.description" class="flex items-start gap-3">
                        <i class="fas fa-align-left text-blue-500 dark:text-blue-300"></i>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">Description:</span>
                        <span class="ml-1 text-gray-600 dark:text-gray-300">{{ exp.description }}</span>
                    </div>
                    <div v-if="exp.location_of_work" class="flex items-center gap-3">
                        <i class="fas fa-map-pin text-blue-500 dark:text-blue-300"></i>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">Location:</span>
                        <span class="ml-1 text-gray-700 dark:text-gray-200">{{ exp.location_of_work }}</span>
                    </div>
                    <div v-if="exp.employment_sector" class="flex items-center gap-3">
                        <i class="fas fa-industry text-blue-500 dark:text-blue-300"></i>
                        <span class="font-semibold text-gray-700 dark:text-gray-200">Sector:</span>
                        <span class="ml-1 text-gray-700 dark:text-gray-200">{{ exp.employment_sector }}</span>
                    </div>
                </div>
            </div>
            <div v-else class="text-gray-500 dark:text-gray-400 mb-4">No employment history available.</div>

            <div class="my-6 border-t border-gray-200 dark:border-gray-700"></div>
            <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <i class="fas fa-lightbulb text-blue-500 dark:text-blue-300"></i> <span>Skills</span>
            </h4>
            <div v-if="viewAlumniData.skills && viewAlumniData.skills.length" class="space-y-3 mb-4">
                <div v-for="(skill, index) in viewAlumniData.skills" :key="index" class="bg-gray-50 dark:bg-gray-900 p-3 rounded-lg">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-gray-700 dark:text-gray-200">{{ skill.name }}</span>
                        <span v-if="skill.certificate || skill.certificate_file" class="inline-flex items-center bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 px-2 py-1 rounded-full text-xs">
                            <i class="fas fa-certificate mr-1"></i> Certified
                        </span>
                    </div>
                    <div v-if="skill.certificate" class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        <span class="font-medium">Certificate:</span> {{ skill.certificate }}
                    </div>
                    <div v-if="skill.certificate_file" class="mt-3 flex justify-between items-center bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                        <div class="flex items-center gap-2">
                            <i :class="getFileIcon(skill.certificate_file)"></i>
                            <span class="text-sm text-gray-600 dark:text-gray-300">Certificate File</span>
                        </div>
                        <div class="flex gap-2">
                            <a :href="getCertificateUrl(skill.certificate_file)" target="_blank" class="text-blue-600 hover:text-blue-800 dark:text-blue-300 dark:hover:text-blue-200 text-sm flex items-center gap-1">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a :href="getCertificateUrl(skill.certificate_file)" download class="text-green-600 hover:text-green-800 dark:text-green-300 dark:hover:text-green-200 text-sm flex items-center gap-1">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-gray-500 dark:text-gray-400 mb-4">No skills listed.</div>

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
<!-- Delete Confirmation Modal for Alumni -->
<div v-if="showDeleteModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
        <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100">Confirm Delete</h3>
        <p class="mb-6 text-gray-700 dark:text-gray-200">Are you sure you want to delete this alumni?</p>
        <div class="flex justify-end gap-2">
            <button class="px-4 py-2 rounded bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 hover:bg-gray-400 dark:hover:bg-gray-700" @click="showDeleteModal = false">Cancel</button>
            <button class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700 transition" @click="confirmDeleteAlumni">Delete</button>
        </div>
    </div>
</div>

@endverbatim
@endsection
