@extends('layouts.admin')
@section('content')
@verbatim
<div class="pb-5">
    <h2 class="text-2xl font-bold text-blue-600 dark:text-blue-400 mb-2">
        Welcome, {{ profile.name ? profile.name.split(' ')[0] : 'Admin' }}!
    </h2>
    <p class="text-gray-600 dark:text-gray-300">
    Welcome to the <?= \App\Core\Auth::role() === 'superadmin' ? 'Super Administrator' : 'Administrator' ?> Portal of the Laguna State Polytechnic University Employment Information System.
    </p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 relative overflow-hidden transition-transform hover:-translate-y-1 hover:shadow-md">
        <div class="text-gray-600 dark:text-gray-300 font-semibold mb-4">Jobs</div>
        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-1">{{ dashboardStats && dashboardStats.total_jobs ? dashboardStats.total_jobs : 0 }}</div>
        <div class="text-sm text-green-500">
            <i class="fas fa-arrow-up mr-1"></i>
            {{ dashboardStats && dashboardStats.jobs_yesterday ? dashboardStats.jobs_yesterday + ' from yesterday' : '0 from yesterday' }}
        </div>
        <i class="fas fa-briefcase text-blue-600 dark:text-blue-400 opacity-10 text-5xl absolute right-5 top-5"></i>
    </div>
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 relative overflow-hidden transition-transform hover:-translate-y-1 hover:shadow-md">
        <div class="text-gray-600 dark:text-gray-300 font-semibold mb-4">Applications</div>
        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-1">{{ dashboardStats && dashboardStats.total_applications ? dashboardStats.total_applications : 0 }}</div>
        <div class="text-sm text-green-500">
            <i class="fas fa-arrow-up mr-1"></i>
            {{ dashboardStats && dashboardStats.applications_yesterday ? dashboardStats.applications_yesterday + ' from yesterday' : '0 from yesterday' }}
        </div>
        <i class="fas fa-file-alt text-blue-600 dark:text-blue-400 opacity-10 text-5xl absolute right-5 top-5"></i>
    </div>
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 relative overflow-hidden transition-transform hover:-translate-y-1 hover:shadow-md">
        <div class="text-gray-600 dark:text-gray-300 font-semibold mb-4">Companies</div>
        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-1">{{ dashboardStats && dashboardStats.total_companies ? dashboardStats.total_companies : 0 }}</div>
        <div class="text-sm text-green-500">
            <i class="fas fa-arrow-up mr-1"></i>
            {{ dashboardStats && dashboardStats.companies_yesterday ? dashboardStats.companies_yesterday + ' from yesterday' : '0 from yesterday' }}
        </div>
        <i class="fas fa-building text-blue-600 dark:text-blue-400 opacity-10 text-5xl absolute right-5 top-5"></i>
    </div>
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 relative overflow-hidden transition-transform hover:-translate-y-1 hover:shadow-md">
        <div class="text-gray-600 dark:text-gray-300 font-semibold mb-4">Alumni</div>
        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400 mb-1">{{ dashboardStats && dashboardStats.total_alumni ? dashboardStats.total_alumni : 0 }}</div>
        <div class="text-sm text-green-500">
            <i class="fas fa-arrow-up mr-1"></i>
            {{ dashboardStats && dashboardStats.alumni_yesterday ? dashboardStats.alumni_yesterday + ' from yesterday' : '0 from yesterday' }}
        </div>
        <i class="fas fa-user-graduate text-blue-600 dark:text-blue-400 opacity-10 text-5xl absolute right-5 top-5"></i>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-2 bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6">
        <h5 class="font-semibold text-lg dark:text-gray-200 mb-4">Graduates and Employment per College</h5>
        <div class="relative h-80 w-full">
            <canvas id="graduatesChart"></canvas>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6">
        <h5 class="font-semibold text-lg dark:text-gray-200 mb-4">Program-Work Alignment</h5>
        <div class="relative h-80 w-full">
            <canvas id="alignmentChart"></canvas>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 flex flex-col">
        <h5 class="font-semibold text-lg dark:text-gray-200 mb-4">Employment Status per Program</h5>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
            Too many programs to read as one chart — pick a campus to view its own.
        </p>
        <div v-if="employmentStatusLoading" class="flex-1 flex items-center justify-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl text-gray-400"></i>
        </div>
        <template v-else>
            <div class="flex flex-col gap-2">
                <button v-for="campus in employmentStatusByCampus" :key="campus.campus_id" @click="openEmploymentStatusModal(campus)"
                    class="w-full flex items-center justify-between px-4 py-2.5 bg-gray-50 dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 transition">
                    <span><i class="fas fa-map-marker-alt text-blue-500 dark:text-blue-400 mr-2"></i>{{ campus.campus_name }}</span>
                    <i class="fas fa-chevron-right text-xs text-gray-400"></i>
                </button>
            </div>

            <!-- Admin only ever has their own single campus above, which leaves this
                 card mostly empty; break it down further into that campus's colleges
                 (each also opens its own program chart) instead of wasting the space.
                 A superadmin's multi-campus list already fills the space on its own. -->
            <div v-if="!isSuperadmin && employmentStatusByCampus[0] && employmentStatusByCampus[0].colleges && employmentStatusByCampus[0].colleges.length" class="flex-1 mt-4">
                <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-2">By College</p>
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                    <button v-for="college in employmentStatusByCampus[0].colleges" :key="college"
                        @click="openCollegeStatusModal(employmentStatusByCampus[0], college)" :title="college"
                        class="flex flex-col items-center justify-center gap-1 px-2 py-3 bg-gray-50 dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-gray-600 rounded-lg text-xs font-semibold text-gray-700 dark:text-gray-200 transition">
                        <i class="fas fa-building text-blue-500 dark:text-blue-400"></i>
                        {{ getCollegeAbbreviation(college) }}
                    </button>
                </div>
            </div>
        </template>
    </div>

    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6">
        <h5 class="font-semibold text-lg dark:text-gray-200 mb-4">Work Location Distribution</h5>
        <div class="relative h-80 w-full">
            <canvas id="locationChart"></canvas>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6">
        <h5 class="font-semibold text-lg dark:text-gray-200 mb-4">Employment Sector</h5>
        <div class="relative h-80 w-full">
            <canvas id="sectorChart"></canvas>
        </div>
    </div>
</div>

<div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 mb-6">
    <h5 class="font-semibold text-lg dark:text-gray-200 mb-4">Alumni Location Map</h5>
    <div id="alumniMap" class="h-96 rounded-lg z-0"></div>
</div>

<!-- Employment Status per Program, single-campus Modal -->
<div v-if="showEmploymentStatusModal && selectedCampusForChart" class="fixed inset-0 flex items-center justify-center z-[100] overflow-y-auto py-8" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-black bg-opacity-50" @click="closeEmploymentStatusModal"></div>
    <div class="relative bg-white dark:bg-gray-700 rounded-lg shadow-xl p-6 w-full max-w-5xl mx-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold dark:text-gray-100">Employment Status per Program — {{ selectedCampusForChart.campus_name }}</h3>
            <button @click="closeEmploymentStatusModal" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="relative h-[32rem] w-full">
            <canvas :id="'employmentStatusCampusChart_' + selectedCampusForChart.campus_id"></canvas>
        </div>
    </div>
</div>

@endverbatim
@endsection
