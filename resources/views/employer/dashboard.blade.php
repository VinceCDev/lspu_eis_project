@extends('layouts.employer')
@section('content')
@verbatim
<!-- Interview List Modal -->
<div v-if="showInterviewModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[80vh] overflow-hidden">
        <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-600">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                Interviews for {{ new Date(selectedDate).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
            </h3>
            <button @click="closeInterviewModal" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="p-6 overflow-y-auto max-h-[60vh]">
            <div v-if="dateInterviews.length === 0" class="text-center py-8">
                <i class="fas fa-calendar-times text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
                <p class="text-gray-600 dark:text-gray-400">No interviews scheduled for this date</p>
            </div>

            <div v-else class="space-y-4">
                <div v-for="interview in dateInterviews" :key="interview.interview_id"
                    class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between">
                        <div class="flex items-start space-x-3">
                            <img :src="interview.profile_image || '<?= asset('assets/images/logo.png') ?>'"
                                alt="Profile"
                                class="w-12 h-12 rounded-full border border-gray-300 dark:border-gray-500">
                            <div>
                                <h4 class="font-semibold text-gray-800 dark:text-gray-100">
                                    {{ interview.alumni_name }}
                                </h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ interview.job_title }}
                                </p>
                                <div class="flex items-center space-x-4 mt-2">
                                    <span class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                        <i class="fas fa-clock mr-2"></i>
                                        {{ formatInterviewTime(interview.interview_date) }}
                                    </span>
                                    <span class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                        <i class="fas fa-stopwatch mr-2"></i>
                                        {{ interview.duration }} mins
                                    </span>
                                    <span :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusBadgeClass(interview.status)]">
                                        {{ interview.status }}
                                    </span>
                                </div>
                                <p v-if="interview.interview_type" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    <i class="fas fa-video mr-2"></i>{{ interview.interview_type }}
                                </p>
                                <p v-if="interview.location && interview.location.startsWith('http')" class="text-sm mt-1">
                                    <a :href="interview.location" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        <i class="fas fa-link mr-2"></i>Join Meeting
                                    </a>
                                </p>
                                <p v-else-if="interview.location" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    <i class="fas fa-map-marker-alt mr-2"></i>{{ interview.location }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div v-if="interview.notes" class="mt-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            <strong>Notes:</strong> {{ interview.notes }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end p-6 border-t border-gray-200 dark:border-gray-600">
            <button @click="closeInterviewModal" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<div class="pb-5">
    <h2 class="text-2xl font-bold text-blue-600 dark:text-blue-400 mb-2">
        Welcome, {{ employerProfile.company_name ? employerProfile.company_name.split(' ')[0] : 'Employer' }}!
    </h2>
    <p class="text-gray-600 dark:text-gray-300">
    Welcome to the Employer Portal of the Laguna State Polytechnic University Employment Information System.
    </p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 relative overflow-hidden transition-transform hover:-translate-y-1 hover:shadow-md">
        <div class="text-gray-600 dark:text-gray-300 font-semibold mb-4">Active Jobs</div>
        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-1">{{ activeJobs }}</div>
        <div class="text-sm text-green-500">
            <i class="fas fa-arrow-up mr-1"></i> {{ jobsChange }} from last week
        </div>
        <i class="fas fa-briefcase text-blue-600 dark:text-blue-400 opacity-10 text-5xl absolute right-5 top-5"></i>
    </div>
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 relative overflow-hidden transition-transform hover:-translate-y-1 hover:shadow-md">
        <div class="text-gray-600 dark:text-gray-300 font-semibold mb-4">Total Applicants</div>
        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-1">{{ totalApplicants }}</div>
        <div class="text-sm text-green-500">
            <i class="fas fa-arrow-up mr-1"></i> {{ applicantsChange }} from last week
        </div>
        <i class="fas fa-users text-blue-600 dark:text-blue-400 opacity-10 text-5xl absolute right-5 top-5"></i>
    </div>
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 relative overflow-hidden transition-transform hover:-translate-y-1 hover:shadow-md">
        <div class="text-gray-600 dark:text-gray-300 font-semibold mb-4">Total Interviews</div>
        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-1">{{ totalInterviews }}</div>
        <div class="text-sm text-green-500">
            <i class="fas fa-arrow-up mr-1"></i> {{ interviewsChange }} from last week
        </div>
        <i class="fas fa-calendar-alt text-blue-600 dark:text-blue-400 opacity-10 text-5xl absolute right-5 top-5"></i>
    </div>
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 relative overflow-hidden transition-transform hover:-translate-y-1 hover:shadow-md">
        <div class="text-gray-600 dark:text-gray-300 font-semibold mb-4">Hired</div>
        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-1">{{ hiredCount }}</div>
        <div class="text-sm text-green-500">
            <i class="fas fa-arrow-up mr-1"></i> 0 from last week
        </div>
        <i class="fas fa-user-check text-blue-600 dark:text-blue-400 opacity-10 text-5xl absolute right-5 top-5"></i>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6">
        <h5 class="font-semibold text-lg dark:text-gray-200 mb-4">Applicants by Program</h5>
        <div class="relative h-80 w-full min-h-[350px]">
            <canvas id="applicantsByCourseChart"></canvas>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6">
        <h5 class="font-semibold text-lg dark:text-gray-200 mb-4">Applicants by Status</h5>
        <div class="relative h-80 w-full">
            <canvas id="applicantsByStatusChart"></canvas>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6">
        <h5 class="font-semibold text-lg dark:text-gray-200 mb-4">Job Listings by Type</h5>
        <div class="relative h-80 w-full">
            <canvas id="jobsByTypeChart"></canvas>
        </div>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6">
        <h5 class="font-semibold text-lg dark:text-gray-200 mb-4">Applicants by Year Graduated</h5>
        <div class="relative h-80 w-full">
            <canvas id="applicantsByYearChart"></canvas>
        </div>
    </div>
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-4 sm:p-6 flex flex-col">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-4 gap-3">
            <h5 class="font-semibold text-lg dark:text-gray-200 text-center sm:text-left">Calendar</h5>
            <div class="flex items-center justify-center space-x-2">
                <button @click="prevMonth" class="px-2 py-1 rounded bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors">
                    <i class="fas fa-chevron-left text-xs sm:text-sm"></i>
                </button>
                <span class="font-semibold text-gray-700 dark:text-gray-200 text-sm sm:text-base min-w-[120px] text-center">{{ calendarMonthYear }}</span>
                <button @click="nextMonth" class="px-2 py-1 rounded bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-500 transition-colors">
                    <i class="fas fa-chevron-right text-xs sm:text-sm"></i>
                </button>
            </div>
        </div>
        <div id="calendar" class="flex-1 flex items-center justify-center overflow-x-auto">
            <table class="w-full text-center border-collapse min-w-[300px]">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th scope="col" class="py-2 px-1 sm:px-2 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-white font-medium text-xs sm:text-sm">Sun</th>
                        <th scope="col" class="py-2 px-1 sm:px-2 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-white font-medium text-xs sm:text-sm">Mon</th>
                        <th scope="col" class="py-2 px-1 sm:px-2 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-white font-medium text-xs sm:text-sm">Tue</th>
                        <th scope="col" class="py-2 px-1 sm:px-2 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-white font-medium text-xs sm:text-sm">Wed</th>
                        <th scope="col" class="py-2 px-1 sm:px-2 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-white font-medium text-xs sm:text-sm">Thu</th>
                        <th scope="col" class="py-2 px-1 sm:px-2 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-white font-medium text-xs sm:text-sm">Fri</th>
                        <th scope="col" class="py-2 px-1 sm:px-2 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-white font-medium text-xs sm:text-sm">Sat</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(week, widx) in calendarWeeks" :key="widx">
                        <td v-for="(day, didx) in week" :key="didx" class="p-1 sm:p-2 border border-gray-300 dark:border-gray-600">
                            <span
                                v-if="day.day > 0"
                                :class="[
                                    isToday(day.day, day.monthOffset) ? 'bg-blue-500 text-white' : (day.monthOffset === 0 ? 'text-gray-800 dark:text-gray-200' : 'text-gray-400 dark:text-gray-400'),
                                    'rounded-full px-1 sm:px-2 inline-block w-6 h-6 sm:w-8 sm:h-8 leading-6 sm:leading-8 text-center select-none transition-colors duration-150 text-xs sm:text-sm cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-900 hover:text-blue-800 dark:hover:text-blue-200'
                                ]"
                                @click="onDateClick(day, day.monthOffset)"
                            >
                                {{ day.day }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@endverbatim
@endsection
