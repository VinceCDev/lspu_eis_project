@extends('layouts.employer')
@section('content')
@verbatim
                <div class="bg-white dark:bg-gray-700 rounded-xl shadow-xl p-6">
                    <!-- Header with Buttons -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                        <h2 class="text-2xl font-bold mb-2 md:mb-0 text-gray-800 dark:text-gray-100">Interview Scheduler</h2>
                        
                        <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full md:w-auto">
                            <!-- View Buttons -->
                            <button @click="viewMode = 'list'" :class="{'bg-blue-600 text-white border-blue-600': viewMode === 'list', 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-500': viewMode !== 'list'}" class="flex items-center gap-2 px-4 py-2 rounded hover:bg-blue-700 hover:text-white transition w-full sm:w-auto justify-center border">
                                <i class="fas fa-list"></i> List View
                            </button>
                            
                            <button @click="viewMode = 'calendar'" :class="{'bg-blue-600 text-white border-blue-600': viewMode === 'calendar', 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-300 dark:border-gray-500': viewMode !== 'calendar'}" class="flex items-center gap-2 px-4 py-2 rounded hover:bg-blue-700 hover:text-white transition w-full sm:w-auto justify-center border">
                                <i class="fas fa-calendar"></i> Calendar View
                            </button>
                            
                            <!-- Action Buttons -->
                            <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition w-full sm:w-auto justify-center border border-blue-700" @click="openScheduleModal">
                                <i class="fas fa-calendar-plus"></i> Schedule Interview
                            </button>
                            
                            <button class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition w-full sm:w-auto justify-center border border-green-700" @click="exportToExcel">
                                <i class="fas fa-file-excel"></i> Export Excel
                            </button>
                        </div>
                    </div>

                    <!-- Filters and Search -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
                        <div class="flex items-center gap-2 w-full md:w-auto mb-2 md:mb-0">
                            <div class="relative w-full md:w-80">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </span>
                                <input type="text" v-model="searchQuery" @input="filterInterviews" class="form-input w-full pl-10 px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search candidate or job...">
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full md:w-auto">
                            <select v-model="filters.job" class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto lg:w-[200px]">
                                <option value="">All Jobs</option>
                                <option v-for="job in jobOptions" :value="job.job_id">{{ job.title }}</option>
                            </select>
                            <select v-model="filters.status" class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto lg:w-[180px]">
                                <option value="">All Statuses</option>
                                <option value="Scheduled">Scheduled</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                                <option value="No Show">No Show</option>
                            </select>
                        </div>
                    </div>

                    <!-- List View -->
                    <div v-if="viewMode === 'list'" class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                                    <th scope="col" class="px-4 py-2 text-left">Candidate</th>
                                    <th scope="col" class="px-4 py-2 text-left">Job Title</th>
                                    <th scope="col" class="px-4 py-2 text-left">Date & Time</th>
                                    <th scope="col" class="px-4 py-2 text-left">Type</th>
                                    <th scope="col" class="px-4 py-2 text-center">Status</th>
                                    <th scope="col" class="px-4 py-2 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="interview in paginatedInterviews" :key="interview.interview_id" class="border-b border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <img :src="interview.profile_image || '<?= asset('assets/images/default-avatar.png') ?>'" alt="Profile" class="w-8 h-8 rounded-full mr-3">
                                            <div>
                                                <div class="font-medium text-gray-800 dark:text-gray-200">{{ interview.alumni_name }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ interview.email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ getJobTitle(interview.job_id) }}</td>
                                    <td class="px-4 py-3 text-gray-800 dark:text-gray-200">
                                        <div>{{ formatDateTime(interview.interview_date) }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ interview.duration }} mins</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ interview.interview_type }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="['inline-block px-2 py-1 rounded text-xs font-semibold', 
                                            interview.status === 'Scheduled' ? 'bg-blue-100 text-blue-700 dark:bg-blue-800 dark:text-blue-200' : 
                                            interview.status === 'Completed' ? 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200' : 
                                            interview.status === 'Cancelled' ? 'bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-200' : 
                                            'bg-yellow-100 text-yellow-700 dark:bg-yellow-800 dark:text-yellow-200']">
                                            {{ interview.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="relative inline-block text-left">
                                            <button @click="toggleActionDropdown(interview.interview_id, $event)" class="p-2 rounded focus:outline-none transition-colors"
                                                :class="darkMode ? 'text-gray-200 hover:bg-gray-700' : 'text-gray-600 hover:bg-gray-200'">
                                                <i class="fas fa-ellipsis-h"></i>
                                            </button>
                                            <teleport to="body">
                                                <div v-if="actionDropdown === interview.interview_id" class="teleported-action-dropdown fixed w-48 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5 z-[300]" :style="{ top: dropdownPosition.top + 'px', left: dropdownPosition.left + 'px' }">
                                                    <div class="py-1" @click="actionDropdown = null">
                                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200" @click.prevent="viewInterview(interview)">
                                                            <i class="fas fa-eye mr-2"></i> View Details
                                                        </a>
                                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors duration-200" @click.prevent="editInterview(interview)" v-if="interview.status === 'Scheduled'">
                                                            <i class="fas fa-edit mr-2"></i> Reschedule
                                                        </a>
                                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/30 transition-colors duration-200" @click.prevent="markAsComplete(interview)" v-if="interview.status === 'Scheduled'">
                                                            <i class="fas fa-check-circle mr-2"></i> Mark Complete
                                                        </a>
                                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors duration-200" @click.prevent="confirmCancel(interview)" v-if="interview.status === 'Scheduled'">
                                                            <i class="fas fa-times-circle mr-2"></i> Cancel
                                                        </a>
                                                        <a v-if="interview.alumni && interview.alumni.resume_file" :href="interview.alumni.resume_file" download class="block px-4 py-2 text-sm text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30 transition-colors duration-200">
                                                            <i class="fas fa-download mr-2"></i> Download Resume
                                                        </a>
                                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors duration-200" @click.prevent="sendReminder(interview)" v-if="interview.status === 'Scheduled'">
                                                            <i class="fas fa-bell mr-2"></i> Send Reminder
                                                        </a>
                                                    </div>
                                                </div>
                                            </teleport>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredInterviews.length === 0">
                                    <td colspan="6" class="py-12 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fas fa-calendar-times text-4xl text-gray-300 mb-2"></i>
                                            <span class="text-lg text-gray-400">No interviews scheduled</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Calendar View (Fixed for Dark Mode) -->
                    <div v-if="viewMode === 'calendar'" class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">{{ calendarTitle }}</h3>
                            <div class="flex space-x-2">
                                <button @click="prevMonth" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button @click="nextMonth" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-800 dark:text-gray-200">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-7 gap-2 mb-2">
                            <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day" 
                                class="text-center font-medium text-gray-600 dark:text-gray-300 py-2">
                                {{ day }}
                            </div>
                        </div>
                        <div class="grid grid-cols-7 gap-2">
                            <div v-for="day in calendarDays" :key="day.date" 
                                :class="['min-h-20 p-2 border rounded', 
                                        day.isCurrentMonth 
                                        ? 'bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-200 border-gray-200 dark:border-gray-600' 
                                        : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 border-gray-200 dark:border-gray-700']">
                                <div class="font-medium mb-1">{{ day.day }}</div>
                                <div v-for="interview in getInterviewsForDay(day.date)" :key="interview.interview_id" 
                                    @click="viewInterview(interview)"
                                    class="text-xs p-1 mb-1 rounded cursor-pointer truncate"
                                    :class="getInterviewStatusClass(interview.status)">
                                    {{ formatTime(interview.interview_date) }}: {{ interview.alumni_name }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination (for list view) -->
                    <div v-if="viewMode === 'list'" class="flex flex-col md:flex-row md:items-center justify-center md:justify-between mt-4 gap-2">
                        <div class="text-gray-600 dark:text-gray-300 text-sm text-center md:text-left w-full md:w-auto flex justify-center md:justify-start">
                            Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredInterviews.length) }} of {{ filteredInterviews.length }} entries
                        </div>
                        <div class="flex gap-1 justify-center md:justify-start">
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
            </div>

            <!-- Schedule/Edit Interview Modal -->
            <div v-if="showInterviewModal" class="fixed inset-0 z-[210] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl mx-4 p-6 relative max-h-[90vh] overflow-y-auto">
                    <button class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300" @click="closeInterviewModal">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                    
                    <h2 class="text-2xl font-bold mb-6 text-gray-800 dark:text-gray-100">
                        {{ modalMode === 'edit' ? 'Edit' : 'Schedule' }} Interview
                    </h2>
                    
                    <form @submit.prevent="modalMode === 'edit' ? updateInterview() : scheduleInterview()" class="space-y-4">
                        <!-- Candidate Selection -->
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Select Candidate*:</label>
                            <select v-model="interviewForm.application_id" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="">-- Select a Candidate --</option>
                                <option v-for="candidate in interviewCandidates" :value="candidate.application_id">
                                    {{ candidate.alumni_name }} - {{ getJobTitle(candidate.job_id) }}
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Date and Time -->
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date & Time*:</label>
                                <input type="datetime-local" v-model="interviewForm.interview_date" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <!-- Duration -->
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Duration (minutes):</label>
                                <input type="number" v-model="interviewForm.duration" min="15" step="15" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Interview Type -->
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Interview Type:</label>
                                <select v-model="interviewForm.interview_type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="Video Call">Video Call</option>
                                    <option value="Phone">Phone</option>
                                    <option value="In-person">In-person</option>
                                </select>
                            </div>
                            
                            <!-- Status -->
                            <div class="form-group" v-if="modalMode === 'edit'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status:</label>
                                <select v-model="interviewForm.status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="Scheduled">Scheduled</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                    <option value="No Show">No Show</option>
                                </select>
                            </div>
                        </div>

                        <!-- Location/Link -->
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ interviewForm.interview_type === 'In-person' ? 'Location' : 'Meeting Link' }}:
                            </label>
                            <input type="text" v-model="interviewForm.location" :placeholder="interviewForm.interview_type === 'In-person' ? 'Office address...' : 'https://meet.google.com/...'" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        
                        <!-- Notes -->
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notes:</label>
                            <textarea v-model="interviewForm.notes" rows="3" placeholder="Agenda, special instructions, etc." class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                        </div>
                        
                        <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                            <button type="button" @click="closeInterviewModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                                Cancel
                            </button>
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm">
                                {{ modalMode === 'edit' ? 'Update' : 'Schedule' }} Interview
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- View Interview Modal - Enhanced Version -->
            <div v-if="showViewModal" class="fixed inset-0 z-[210] flex items-center justify-center bg-black bg-opacity-50 pointer-events-auto" role="dialog" aria-modal="true" data-modal="view-interview-modal">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-4xl mx-2 p-0 relative max-h-[95vh] overflow-y-auto pointer-events-auto">
                    <button class="absolute top-3 right-3 flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full shadow hover:bg-gray-200 dark:hover:bg-gray-600 transition text-base font-semibold z-20" @click="closeViewModal" id="close-view-interview-modal-btn">
                        <i class="fas fa-times"></i> <span>Close</span>
                    </button>
                    
                    <!-- Header Section -->
                    <div class="rounded-t-2xl bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400 dark:from-blue-900 dark:via-blue-800 dark:to-blue-700 px-0 pt-6 pb-8 flex flex-col items-center relative">
                        <div class="absolute top-4 left-4 bg-white dark:bg-gray-700 rounded-full p-2 shadow-lg">
                            <i class="fas fa-calendar-alt text-blue-600 dark:text-blue-300 text-2xl"></i>
                        </div>
                        <div class="w-28 h-28 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center border-4 border-white dark:border-gray-700 shadow-xl mb-2 mt-2">
                            <img v-if="selectedInterview.profile_image" :src="selectedInterview.profile_image" alt="Candidate" class="w-full h-full rounded-full object-cover">
                            <i v-else class="fas fa-user text-4xl text-gray-400"></i>
                        </div>
                        <h3 class="text-3xl font-extrabold text-white drop-shadow-lg mb-1 text-center">{{ selectedInterview.alumni_name }}</h3>
                        <span :class="['inline-block mt-1 px-3 py-1 rounded-full text-xs font-semibold shadow', 
                            selectedInterview.status === 'Scheduled' ? 'bg-blue-100 text-blue-700 dark:bg-blue-800 dark:text-blue-200' : 
                            selectedInterview.status === 'Completed' ? 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200' : 
                            selectedInterview.status === 'Cancelled' ? 'bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-200' : 
                            'bg-yellow-100 text-yellow-700 dark:bg-yellow-800 dark:text-yellow-200']">
                            {{ selectedInterview.status }}
                        </span>
                    </div>
                    
                    <!-- Content Section -->
                    <div class="px-6 py-6">
                        <!-- Basic Information -->
                        <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                            <i class="fas fa-info-circle text-blue-500 dark:text-blue-300"></i> 
                            <span>Interview Details</span>
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-6">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-user-graduate text-blue-500 dark:text-blue-300 w-5"></i>
                                <span class="font-semibold text-gray-700 dark:text-gray-200">Candidate:</span> 
                                <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedInterview.alumni_name }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-envelope text-blue-500 dark:text-blue-300 w-5"></i>
                                <span class="font-semibold text-gray-700 dark:text-gray-200">Email:</span> 
                                <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedInterview.email }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-briefcase text-blue-500 dark:text-blue-300 w-5"></i>
                                <span class="font-semibold text-gray-700 dark:text-gray-200">Job Position:</span> 
                                <span class="ml-1 text-gray-700 dark:text-gray-200">{{ getJobTitle(selectedInterview.job_id) }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-calendar-day text-blue-500 dark:text-blue-300 w-5"></i>
                                <span class="font-semibold text-gray-700 dark:text-gray-200">Date & Time:</span> 
                                <span class="ml-1 text-gray-700 dark:text-gray-200">{{ formatDateTime(selectedInterview.interview_date) }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-clock text-blue-500 dark:text-blue-300 w-5"></i>
                                <span class="font-semibold text-gray-700 dark:text-gray-200">Duration:</span> 
                                <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedInterview.duration }} minutes</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <i class="fas fa-video text-blue-500 dark:text-blue-300 w-5"></i>
                                <span class="font-semibold text-gray-700 dark:text-gray-200">Type:</span> 
                                <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedInterview.interview_type }}</span>
                            </div>
                        </div>

                        <!-- Location/Link Details -->
                        <div v-if="selectedInterview.location">
                            <h4 class="text-lg font-bold mb-3 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-blue-500 dark:text-blue-300"></i> 
                                <span>{{ selectedInterview.interview_type === 'In-person' ? 'Location' : 'Meeting Details' }}</span>
                            </h4>
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-6">
                                <p class="text-gray-700 dark:text-gray-200">
                                    <a v-if="selectedInterview.interview_type !== 'In-person'" :href="selectedInterview.location" target="_blank" class="text-blue-600 hover:underline dark:text-blue-400 break-all">
                                        <i class="fas fa-external-link-alt mr-1"></i> {{ selectedInterview.location }}
                                    </a>
                                    <span v-else>{{ selectedInterview.location }}</span>
                                </p>
                                <button v-if="selectedInterview.interview_type !== 'In-person'" @click="copyToClipboard(selectedInterview.location)" class="mt-2 flex items-center gap-1 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                    <i class="fas fa-copy"></i> Copy link
                                </button>
                            </div>
                        </div>

                        <!-- Notes Section -->
                        <div v-if="selectedInterview.notes">
                            <h4 class="text-lg font-bold mb-3 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                                <i class="fas fa-sticky-note text-blue-500 dark:text-blue-300"></i> 
                                <span>Interview Notes</span>
                            </h4>
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-6">
                                <p class="text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ selectedInterview.notes }}</p>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button @click="editInterview(selectedInterview)" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center justify-center gap-2" v-if="selectedInterview.status === 'Scheduled'">
                                <i class="fas fa-edit"></i> Reschedule
                            </button>
                            <button @click="confirmCancel(selectedInterview)" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors flex items-center justify-center gap-2" v-if="selectedInterview.status === 'Scheduled'">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button @click="markAsComplete(selectedInterview)" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors flex items-center justify-center gap-2" v-if="selectedInterview.status === 'Scheduled'">
                                <i class="fas fa-check"></i> Complete
                            </button>
                            <button @click="closeViewModal" class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cancel Confirmation Modal -->
            <div v-if="showCancelModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                    <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100">Confirm Cancellation</h3>
                    <p class="mb-6 text-gray-700 dark:text-gray-200">Are you sure you want to cancel the interview with {{ selectedInterview.alumni_name }}?</p>
                    <div class="flex justify-end gap-2">
                        <button class="px-4 py-2 rounded bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 hover:bg-gray-400 dark:hover:bg-gray-700" @click="showCancelModal = false">No, Keep It</button>
                        <button class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700 transition" @click="cancelInterview">Yes, Cancel</button>
                    </div>
                </div>

@endverbatim
@endsection
