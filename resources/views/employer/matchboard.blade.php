@extends('layouts.employer')
@section('content')
@verbatim
            <!-- Leaderboard Header -->
            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Matches -->
                <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900">
                            <i class="fas fa-handshake text-blue-600 dark:text-blue-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Matches</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ statistics.totalMatches }}</p>
                        </div>
                    </div>
                </div>

                <!-- High Matches -->
                <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 dark:bg-green-900">
                            <i class="fas fa-chart-line text-green-600 dark:text-green-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">80%+ Matches</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ statistics.highMatches }}</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Matches -->
                <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900">
                            <i class="fas fa-clock text-purple-600 dark:text-purple-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Recent Matches (7 days)</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ statistics.recentMatches }}</p>
                        </div>
                    </div>
                </div>

                <!-- Average Match -->
                <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 dark:bg-orange-900">
                            <i class="fas fa-percentage text-orange-600 dark:text-orange-400 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Match %</p>
                            <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ statistics.averageMatch }}%</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leaderboard Content -->
            <div class="bg-white dark:bg-gray-700 rounded-xl shadow-xl p-6">
                 <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-2xl font-bold mb-2 text-gray-800 dark:text-gray-100">Job Match Leaderboard</h2>
                    </div>
                    <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full md:w-auto">
                        <button class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition w-full sm:w-auto justify-center" @click="exportLeaderboardExcel">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                    </div>
                </div>
                <!-- Filters -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                    <div class="flex items-center gap-2 w-full md:w-auto mb-2 md:mb-0">
                        <div class="relative w-full md:w-80">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </span>
                            <input type="text" class="form-input w-full pl-10 px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search alumni..." v-model="searchQuery">
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2 w-full md:w-auto">
                        <select v-model="selectedJob" class="px-4 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto">
                            <option value="">All Jobs</option>
                            <option v-for="job in jobs" :key="job.job_id" :value="job.job_id">
                                {{ job.title }}
                            </option>
                        </select>
                        <!-- Replace the percentage filter select options -->
                        <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto" v-model="minPercentage">
                            <option value="60">60%+ Match</option>
                            <option value="70">70%+ Match</option>
                            <option value="80">80%+ Match</option>
                            <option value="90">90%+ Match</option>
                        </select>
                        <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto" v-model="sortBy">
                            <option value="percentage">Sort by Match %</option>
                            <option value="name">Sort by Name</option>
                            <option value="course">Sort by Program</option>
                        </select>
                    </div>
                </div>

                <!-- Leaderboard Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                                <th scope="col" class="px-4 py-3 text-left">Rank</th>
                                <th scope="col" class="px-4 py-3 text-left">Alumni</th>
                                <th scope="col" class="px-4 py-3 text-left">Program</th>
                                <th scope="col" class="px-4 py-3 text-left">Job Title</th>
                                <th scope="col" class="px-4 py-3 text-center">Match %</th>
                                <th scope="col" class="px-4 py-3 text-center">Matched On</th>
                                <th scope="col" class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(match, index) in filteredMatches" :key="match.match_id" class="border-b border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <!-- Rank -->
                                <td class="px-4 py-3 font-bold text-gray-800 dark:text-gray-200">
                                    <div class="flex items-center">
                                        <span class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-300 text-sm font-bold">
                                            {{ index + 1 }}
                                        </span>
                                    </div>
                                </td>
                                
                                <!-- Alumni Info -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center mr-3 bg-gray-300 dark:bg-gray-600">
                                            <img v-if="match.profile_pic" :src="match.profile_pic" :alt="match.first_name + ' ' + match.last_name" class="w-full h-full object-cover">
                                            <i v-else class="fas fa-user text-gray-600 dark:text-gray-300"></i>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-gray-800 dark:text-gray-100">{{ match.first_name }} {{ match.last_name }}</div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">{{ match.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Course -->
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ match.course }}</td>
                                
                                <!-- Job Title -->
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ match.job_title }}</td>
                                
                                <!-- Match Percentage -->
                                <td class="px-4 py-3 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-16 h-16 rounded-full flex items-center justify-center border-4 font-bold text-lg"
                                            :class="getPercentageColor(match.match_percentage)">
                                            {{ match.match_percentage }}%
                                        </div>
                                    </div>
                                </td>
                                
                                
                                <!-- Matched Date -->
                                <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-400 text-sm">
                                    {{ formatDate(match.matched_at) }}
                                </td>
                                
                                <!-- Actions -->
                                <td class="px-4 py-3 text-center">
                                    <div class="relative inline-block text-left">
                                        <button @click="toggleActionDropdown(match.match_id)" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none text-gray-500 dark:text-gray-200">
                                            <i class="fas fa-ellipsis-h"></i>
                                        </button>
                                        <div v-if="actionDropdown === match.match_id" class="origin-top-right absolute right-0 mt-2 w-40 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5 z-10">
                                            <div class="py-1" @click="actionDropdown = null">
                                                <a href="#" role="button" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" @click.prevent="viewAlumniProfile(match)">
                                                    <i class="fas fa-eye mr-2"></i>View Profile
                                                </a>
                                                <a href="#" role="button" class="block px-4 py-2 text-sm text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-800" @click.prevent="contactAlumni(match)">
                                                    <i class="fas fa-envelope mr-2"></i>Contact
                                                </a>
                                                <a href="#" role="button" class="block px-4 py-2 text-sm text-green-600 hover:bg-green-100 dark:hover:bg-green-800" @click.prevent="downloadProfile(match)">
                                                    <i class="fas fa-download mr-2"></i>Download CV
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Empty State -->
                            <tr v-if="filteredMatches.length === 0">
                                <td colspan="8" class="py-12 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fas fa-trophy text-4xl text-gray-300 mb-2"></i>
                                        <span class="text-lg text-gray-400">No matches found</span>
                                        <p class="text-sm text-gray-500 mt-1">Try adjusting your filters or post more jobs</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="flex flex-col md:flex-row md:items-center justify-center md:justify-between mt-6 gap-2">
                    <div class="text-gray-600 dark:text-gray-300 text-sm text-center md:text-left">
                        Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredMatches.length) }} of {{ filteredMatches.length }} matches
                    </div>
                    <div class="flex gap-1 justify-center items-center">
                        <button class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors" :disabled="!paginationGroup.hasPrevGroup" @click="goToPrevGroup" title="Previous 5 pages">
                            <i class="fas fa-angle-double-left"></i>
                        </button>
                        <button class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" :disabled="currentPage === 1" @click="prevPage">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button v-for="page in paginationGroup.pages" :key="page"
                                class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-blue-100 dark:hover:bg-blue-900 min-w-[40px] transition-colors"
                                :class="page === currentPage
                                    ? 'bg-blue-600 dark:bg-blue-500 text-white'
                                    : 'bg-white dark:bg-gray-700'"
                                @click="goToPage(page)">
                            {{ page }}
                        </button>
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

        <!-- View Alumni Profile Modal -->
        <div v-if="showAlumniModal" class="fixed inset-0 z-[210] flex items-center justify-center bg-black bg-opacity-50 pointer-events-auto" role="dialog" aria-modal="true" data-modal="view-alumni-modal">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-xl mx-2 p-0 relative max-h-[95vh] overflow-y-auto pointer-events-auto">
                <button class="absolute top-3 right-3 flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full shadow hover:bg-gray-200 dark:hover:bg-gray-600 transition text-base font-semibold z-20" @click="showAlumniModal = false">
                    <i class="fas fa-times"></i> <span>Close</span>
                </button>
                
                <!-- Header Section -->
                <div class="rounded-t-2xl bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400 dark:from-blue-900 dark:via-blue-800 dark:to-blue-700 px-0 pt-6 pb-8 flex flex-col items-center relative">
                    <div class="absolute top-4 left-4 bg-white dark:bg-gray-700 rounded-full p-2 shadow-lg">
                        <i class="fas fa-user-graduate text-blue-600 dark:text-blue-300 text-2xl"></i>
                    </div>
                    
                    <div v-if="selectedAlumni.profile_picture" class="w-28 h-28 rounded-full border-4 border-white dark:border-gray-700 shadow-xl mb-2 mt-2 overflow-hidden">
                        <img :src="selectedAlumni.profile_picture" alt="Alumni Photo" class="w-full h-full object-cover">
                    </div>
                    <div v-else class="w-28 h-28 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center border-4 border-white dark:border-gray-700 shadow-xl mb-2 mt-2">
                        <i class="fas fa-user-graduate text-4xl text-gray-400"></i>
                    </div>
                    
                    <!-- Name and Match Percentage -->
                    <h3 class="text-3xl font-extrabold text-white drop-shadow-lg mb-1 text-center">{{ selectedAlumni.first_name }} {{ selectedAlumni.last_name }}</h3>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="inline-block px-3 py-1 rounded-full text-sm font-semibold shadow bg-white dark:bg-gray-800 text-blue-600 dark:text-blue-300">
                            {{ selectedAlumni.match_percentage }}% Match
                        </span>
                    </div>
                </div>

                <!-- Content Section -->
                <div class="px-6 py-6">
                    <!-- Personal Information -->
                    <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <i class="fas fa-info-circle text-blue-500 dark:text-blue-300"></i> 
                        <span>Personal Information</span>
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-1 gap-4 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-6">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-envelope text-blue-500 dark:text-blue-300"></i>
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Email:</span> 
                            <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedAlumni.email }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-phone text-blue-500 dark:text-blue-300"></i>
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Contact:</span> 
                            <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedAlumni.contact || 'Not provided' }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-calendar-alt text-blue-500 dark:text-blue-300"></i>
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Birthdate:</span> 
                            <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedAlumni.birthdate || 'Not provided' }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-venus-mars text-blue-500 dark:text-blue-300"></i>
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Gender:</span> 
                            <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedAlumni.gender || 'Not provided' }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-user-friends text-blue-500 dark:text-blue-300"></i>
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Civil Status:</span> 
                            <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedAlumni.civil_status || 'Not provided' }}</span>
                        </div>
                    </div>

                    <!-- Education -->
                    <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <i class="fas fa-graduation-cap text-blue-500 dark:text-blue-300"></i> 
                        <span>Education</span>
                    </h4>
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-6">
                        <div class="flex items-center gap-3 mb-3">
                            <i class="fas fa-university text-blue-500 dark:text-blue-300"></i>
                            <span class="font-semibold text-gray-700 dark:text-gray-200">College:</span> 
                            <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedAlumni.college || 'Not provided' }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <i class="fas fa-book text-blue-500 dark:text-blue-300"></i>
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Program:</span> 
                            <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedAlumni.course || 'Not provided' }}</span>
                        </div>
                        <div class="flex items-center gap-3 mt-3">
                            <i class="fas fa-calendar text-blue-500 dark:text-blue-300"></i>
                            <span class="font-semibold text-gray-700 dark:text-gray-200">Year Graduated:</span> 
                            <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedAlumni.year_graduated || 'Not provided' }}</span>
                        </div>
                    </div>

                    <!-- Skills Section -->
                        <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                            <i class="fas fa-cogs text-blue-500 dark:text-blue-300"></i> 
                            <span>Skills & Certifications</span>
                        </h4>
                        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-6">
                            <div v-if="selectedAlumni.skills && selectedAlumni.skills.length" class="space-y-4">
                                <div v-for="skill in selectedAlumni.skills" :key="skill.skill_id" class="border-b border-gray-200 dark:border-gray-700 pb-4 last:border-b-0 last:pb-0">
                                    <!-- Skill Name -->
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-semibold text-gray-800 dark:text-gray-100 text-lg">{{ skill.name }}</span>
                                        <span v-if="skill.certificate || skill.certificate_file" 
                                            class="inline-flex items-center bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 px-3 py-1 rounded-full text-xs font-medium">
                                            <i class="fas fa-certificate mr-1.5"></i> Certified
                                        </span>
                                    </div>
                                    
                                    <!-- Certificate Details -->
                                    <div class="space-y-3 pl-2">
                                        <!-- Certificate Name -->
                                        <div v-if="skill.certificate" class="flex items-start">
                                            <i class="fas fa-file-alt text-blue-500 dark:text-blue-300 mt-1 mr-3"></i>
                                            <div>
                                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Certificate Name</p>
                                                <p class="text-gray-600 dark:text-gray-400 text-sm">{{ skill.certificate }}</p>
                                            </div>
                                        </div>
                                        
                                        <!-- Certificate File -->
                                        <div v-if="skill.certificate_file" class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="flex items-center gap-2">
                                                    <i :class="getFileIcon(skill.certificate_file) + ' text-lg'"></i>
                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Certificate File</span>
                                                </div>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ skill.certificate_file.split('.').pop().toUpperCase() }} File
                                                </span>
                                            </div>
                                            
                                            <div class="flex gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                                                <a :href="getCertificateUrl(skill.certificate_file)" target="_blank" 
                                                class="flex-1 flex items-center justify-center gap-2 py-2 px-3 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300 rounded-md text-sm font-medium hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors">
                                                    <i class="fas fa-eye text-xs"></i> Preview
                                                </a>
                                                <a :href="getCertificateUrl(skill.certificate_file)" download 
                                                class="flex-1 flex items-center justify-center gap-2 py-2 px-3 bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-300 rounded-md text-sm font-medium hover:bg-green-100 dark:hover:bg-green-900/50 transition-colors">
                                                    <i class="fas fa-download text-xs"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-gray-500 dark:text-gray-400 italic">No skills listed</p>
                        </div>

                    <!-- Work Experience -->
                    <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <i class="fas fa-briefcase text-blue-500 dark:text-blue-300"></i> 
                        <span>Work Experience</span>
                    </h4>
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-6">
                        
                        <div v-if="selectedAlumni.experiences && selectedAlumni.experiences.length" class="space-y-3">
                            <div v-for="exp in selectedAlumni.experiences" :key="exp.experience_id" class="border-l-4 border-blue-500 pl-4 py-2">
                                <div class="font-semibold text-gray-800 dark:text-gray-100">{{ exp.title }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-300">{{ exp.company }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ exp.start_date }} - {{ exp.current ? 'Present' : exp.end_date }}
                                </div>
                                <div class="text-gray-700 dark:text-gray-200 mt-1 text-sm">{{ exp.description }}</div>
                            </div>
                        </div>
                        <p v-else class="text-gray-500 dark:text-gray-400 italic">No work experience listed</p>
                    </div>

                    <!-- Match Details -->
                    <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <i class="fas fa-chart-line text-blue-500 dark:text-blue-300"></i> 
                        <span>Match Analysis</span>
                    </h4>
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-blue-600 dark:text-blue-300">{{ selectedAlumni.match_percentage }}%</div>
                                <div class="text-sm text-gray-600 dark:text-gray-300">Overall Match Score</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600 dark:text-green-300">{{ formatDate(selectedAlumni.matched_at) }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-300">Matched On</div>
                            </div>
                        </div>
                        
                        <!-- Skills Match Breakdown -->
                        <div class="mt-4">
                            <h5 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Skills Match:</h5>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-2">
                                <div class="bg-green-500 h-2 rounded-full" :style="{width: selectedAlumni.skills_match + '%'}"></div>
                            </div>
                            <span class="text-sm text-gray-600 dark:text-gray-300">{{ selectedAlumni.skills_match }}% relevant skills</span>
                        </div>
                    </div>

                    <div v-if="selectedAlumni.resume_file || selectedAlumni.file_name" class="mb-6">
                        <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
                            <i class="fas fa-file-pdf text-red-500"></i> 
                            <span>Resume</span>
                        </h4>
                        
                        <!-- Action buttons placed above the viewer -->
                        <div class="flex flex-col gap-3 mb-4">
                            <div class="flex flex-col sm:flex-row gap-2 w-full">
                                <a :href="`uploads/resumes/${selectedAlumni.resume_file || selectedAlumni.file_name}`" target="_blank" 
                                class="inline-flex items-center justify-center gap-2 px-4 py-3 bg-blue-600 text-white rounded hover:bg-blue-700 transition flex-1 text-center">
                                    <i class="fas fa-eye"></i> Preview Resume
                                </a>
                                <a :href="`uploads/resumes/${selectedAlumni.resume_file || selectedAlumni.file_name}`" download 
                                class="inline-flex items-center justify-center gap-2 px-4 py-3 bg-green-600 text-white rounded hover:bg-green-700 transition flex-1 text-center">
                                    <i class="fas fa-download"></i> Download Resume
                                </a>
                            </div>
                        </div>
                        
                        <!-- File viewer placed below the buttons -->
                        <div class="border rounded-lg overflow-hidden" style="height: 500px;">
                            <iframe 
                                :src="`uploads/resumes/${selectedAlumni.resume_file || selectedAlumni.file_name}#toolbar=0`" 
                                width="100%" 
                                height="100%" 
                                frameborder="0"
                                class="bg-gray-100">
                            </iframe>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col md:flex-row gap-3 mt-6 w-full">
                        <button @click="contactAlumni(selectedAlumni)" 
                                class="flex-1 px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center justify-center gap-2 text-sm sm:text-base">
                            <i class="fas fa-envelope"></i> Contact
                        </button>
                        <button v-if="selectedAlumni.status !== 'applied'" @click="markAsApplied(selectedAlumni)" 
                                class="flex-1 px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition flex items-center justify-center gap-2 text-sm sm:text-base">
                            <i class="fas fa-check-circle"></i> Mark Applied
                        </button>
                        <button @click="downloadProfile(selectedAlumni)" 
                                class="flex-1 px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition flex items-center justify-center gap-2 text-sm sm:text-base">
                            <i class="fas fa-download"></i> Download
                        </button>
                    </div>
                </div>
            </div>
        </div>

@endverbatim
@endsection
