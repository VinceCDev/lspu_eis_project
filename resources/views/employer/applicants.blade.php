@extends('layouts.employer')
@section('content')
@verbatim
<div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <h2 class="text-2xl font-bold mb-2 md:mb-0 text-gray-800 dark:text-gray-100">Applicants</h2>
        <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full md:w-auto">
            <button class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition w-full sm:w-auto justify-center" @click="exportToExcel">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
            <button class="flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition w-full sm:w-auto justify-center" @click="exportToPDF">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
        </div>
    </div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
        <div class="flex items-center gap-2 w-full md:w-auto mb-2 md:mb-0">
            <div class="relative w-full md:w-80">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </span>
                <input type="text" class="form-input w-full pl-10 px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search applicants..." v-model="searchQuery" @input="filterApplicants">
            </div>
        </div>
        <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full md:w-auto">
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto" v-model="filters.status">
                <option value="">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="Interview">Interview</option>
                <option value="Hired">Hired</option>
                <option value="Rejected">Reject</option>
            </select>
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto" v-model="filters.appliedFor">
                <option value="">All Positions</option>
                <option v-for="position in uniquePositions" :key="position">{{ position }}</option>
            </select>
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto" v-model="filters.experience">
                <option value="">All Experience</option>
                <option v-for="exp in uniqueExperiences" :key="exp">{{ exp }} years</option>
            </select>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-center">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                    <th scope="col" class="px-4 py-2">Applicant</th>
                    <th scope="col" class="px-4 py-2">Applied For</th>
                    <th scope="col" class="px-4 py-2">Applied Date</th>
                    <th scope="col" class="px-4 py-2">Experience</th>
                    <th scope="col" class="px-4 py-2">Status</th>
                    <th scope="col" class="px-4 py-2">Company</th>
                    <th scope="col" class="px-4 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="applicant in paginatedApplicants" :key="applicant.id" class="border-b border-gray-200 dark:border-gray-600">
                    <td class="px-4 py-2 font-semibold text-center text-gray-700 dark:text-gray-200">
                        <div>{{ applicant.name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ applicant.email }}</div>
                    </td>
                    <td class="px-4 py-2 text-center text-gray-700 dark:text-gray-200">{{ applicant.appliedFor }}</td>
                    <td class="px-4 py-2 text-center text-gray-700 dark:text-gray-200">{{ formatDate(applicant.appliedDate) }}</td>
                    <td class="px-4 py-2 text-center text-gray-700 dark:text-gray-200">{{ applicant.experience }} years</td>
                    <td class="px-4 py-2 text-center">
                        <span :class="[
                            'inline-block px-2 py-1 rounded text-xs font-semibold',
                            applicant.status === 'Hired' ? 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200' :
                            applicant.status === 'Interview' ? 'bg-blue-100 text-blue-700 dark:bg-blue-800 dark:text-blue-200' :
                            applicant.status === 'Pending' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-800 dark:text-yellow-200' :
                            'bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-200'
                        ]">
                            {{ applicant.status }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-center text-gray-700 dark:text-gray-200">{{ applicant.company_name }}</td>
                    <td class="px-4 py-2 text-center">
                        <div class="relative inline-block text-left">
                            <button @click="toggleActionDropdown(applicant.id, $event)" class="p-2 rounded focus:outline-none transition-colors"
                                :class="darkMode ? 'text-gray-200 hover:bg-gray-700' : 'text-gray-600 hover:bg-gray-200'">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <teleport to="body">
                                <div v-if="actionDropdown === applicant.id" class="teleported-action-dropdown fixed w-40 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5 z-[300]"
                                    :style="{ top: dropdownPosition.top + 'px', left: dropdownPosition.left + 'px' }">
                                    <div class="py-1" @click="actionDropdown = null">
                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" @click.prevent="viewApplicant(applicant)"><i class="fas fa-eye mr-2"></i>View</a>
                                        <a v-if="applicant.alumni && applicant.alumni.resume_file" :href="applicant.alumni.resume_file" download class="block px-4 py-2 text-sm text-green-700 dark:text-green-300 hover:bg-green-50 dark:hover:bg-green-900"><i class="fas fa-download mr-2"></i>Download Resume</a>
                                        <a href="#"
                                        class="block px-4 py-2 text-sm text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors duration-200"
                                        @click.prevent="updateApplicantStatus(applicant, 'Interview')">
                                        <i class="fas fa-calendar-check mr-2"></i>Interview
                                        </a>
                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-green-700 dark:text-green-300 hover:bg-green-50 dark:hover:bg-green-900" @click.prevent="updateApplicantStatus(applicant, 'Hired')"><i class="fas fa-user-check mr-2"></i>Hired</a>
                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-yellow-700 dark:text-yellow-300 hover:bg-yellow-50 dark:hover:bg-yellow-900" @click.prevent="updateApplicantStatus(applicant, 'Rejected')"><i class="fas fa-user-times mr-2"></i>Reject</a>
                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 dark:text-red-400 dark:hover:bg-red-800" @click.prevent="confirmDelete(applicant)"><i class="fas fa-trash mr-2"></i>Delete</a>
                                    </div>
                                </div>
                            </teleport>
                        </div>
                    </td>
                </tr>
                <tr v-if="filteredApplicants.length === 0">
                    <td colspan="6" class="py-12 text-center text-gray-700 dark:text-gray-200">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fas fa-users text-4xl text-gray-300 mb-2"></i>
                            <span class="text-lg text-gray-400">No applicants found</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mt-4 gap-2">
        <div class="text-gray-600 dark:text-gray-300 text-sm w-full md:w-auto flex justify-center md:justify-start">
            Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredApplicants.length) }} of {{ filteredApplicants.length }} entries
        </div>
        <div class="flex gap-1 justify-center w-full md:w-auto">
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
<!-- View Applicant Modal -->
<div v-if="showApplicantModal" class="fixed inset-0 z-[210] flex items-center justify-center bg-black bg-opacity-50 pointer-events-auto" role="dialog" aria-modal="true" data-modal="view-applicant-modal">
  <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-xl mx-2 p-0 relative max-h-[95vh] overflow-y-auto pointer-events-auto">
    <button class="absolute top-3 right-3 flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full shadow hover:bg-gray-200 dark:hover:bg-gray-600 transition text-base font-semibold z-20" @click="showApplicantModal = false">
      <i class="fas fa-times"></i> <span>Close</span>
    </button>
    <div class="rounded-t-2xl bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400 dark:from-blue-900 dark:via-blue-800 dark:to-blue-700 px-0 pt-6 pb-8 flex flex-col items-center relative">
      <div class="absolute top-4 left-4 bg-white dark:bg-gray-700 rounded-full p-2 shadow-lg">
        <i class="fas fa-user-graduate text-blue-600 dark:text-blue-300 text-2xl"></i>
      </div>
      <img v-if="selectedApplicant.alumni && selectedApplicant.alumni.profile_image" :src="selectedApplicant.alumni.profile_image" alt="Alumni Photo" class="w-28 h-28 rounded-full object-cover border-4 border-white dark:border-gray-700 shadow-xl mb-2 mt-2">
      <div v-else class="w-28 h-28 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center border-4 border-white dark:border-gray-700 shadow-xl mb-2 mt-2">
        <i class="fas fa-user-graduate text-4xl text-gray-400"></i>
      </div>
      <h3 class="text-3xl font-extrabold text-white drop-shadow-lg mb-1 text-center">{{ selectedApplicant.alumni ? selectedApplicant.alumni.alumni_name : selectedApplicant.name }}</h3>
      <span class="inline-block mt-1 px-3 py-1 rounded-full text-xs font-semibold shadow bg-blue-100 text-blue-700 dark:bg-blue-800 dark:text-blue-200">Applicant</span>
    </div>
    <div class="px-6 py-6">
      <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-info-circle text-blue-500 dark:text-blue-300"></i> <span>Alumni Details</span></h4>
      <div class="grid grid-cols-1 gap-3 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-6">
        <div class="flex items-center gap-3"><i class="fas fa-envelope text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Email:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedApplicant.alumni ? selectedApplicant.alumni.email : selectedApplicant.email }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-phone text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Contact:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedApplicant.alumni ? selectedApplicant.alumni.contact : '' }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-calendar-alt text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Birthdate:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedApplicant.alumni ? selectedApplicant.alumni.birthdate : '' }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-venus-mars text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Gender:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedApplicant.alumni ? selectedApplicant.alumni.gender : '' }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-user-friends text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Civil Status:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedApplicant.alumni ? selectedApplicant.alumni.civil_status : '' }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-university text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">College:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedApplicant.alumni ? selectedApplicant.alumni.college : '' }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-graduation-cap text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Program:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedApplicant.alumni ? selectedApplicant.alumni.course : '' }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-map-marker-alt text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Campus:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ (selectedApplicant.alumni && selectedApplicant.alumni.campus_name) || 'Not specified' }}</span></div>
      </div>
      <div class="mb-6">
        <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-file-alt text-blue-500 dark:text-blue-300"></i> <span>Resume</span></h4>
        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm">
          <a v-if="selectedApplicant.alumni && selectedApplicant.alumni.resume_file" :href="selectedApplicant.alumni.resume_file" target="_blank" class="inline-flex items-center gap-2 text-blue-700 dark:text-blue-300 underline">
            <i class="fas fa-download"></i> View Resume
          </a>
          <p v-else class="text-gray-500 dark:text-gray-400 italic">No resume uploaded.</p>
        </div>
      </div>
      <div v-if="selectedApplicant.alumni && selectedApplicant.alumni.experiences && selectedApplicant.alumni.experiences.length" class="mb-6">
          <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
              <i class="fas fa-briefcase text-blue-500 dark:text-blue-300"></i>
              <span>Work Experience</span>
          </h4>
          <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm space-y-3">
              <div v-for="exp in selectedApplicant.alumni.experiences" :key="exp.experience_id" class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                  <div class="font-semibold text-gray-800 dark:text-gray-100">{{ exp.title }} <span class="text-xs text-gray-500 dark:text-gray-400 font-normal">@ {{ exp.company }}</span></div>
                  <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ exp.start_date }} - {{ exp.current ? 'Present' : exp.end_date }}</div>
                  <div v-if="exp.description" class="text-gray-700 dark:text-gray-200 text-sm mt-2">{{ exp.description }}</div>
              </div>
          </div>
      </div>
      <div v-if="selectedApplicant.alumni && selectedApplicant.alumni.educations && selectedApplicant.alumni.educations.length" class="mb-6">
          <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
              <i class="fas fa-graduation-cap text-blue-500 dark:text-blue-300"></i>
              <span>Education</span>
          </h4>
          <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm space-y-3">
              <div v-for="edu in selectedApplicant.alumni.educations" :key="edu.education_id" class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                  <div class="font-semibold text-gray-800 dark:text-gray-100">{{ edu.degree }} <span class="text-xs text-gray-500 dark:text-gray-400 font-normal">@ {{ edu.school }}</span></div>
                  <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ edu.start_date }} - {{ edu.current ? 'Present' : edu.end_date }}</div>
              </div>
          </div>
      </div>
      <div v-if="selectedApplicant.alumni && selectedApplicant.alumni.skills && selectedApplicant.alumni.skills.length" class="mb-6">
          <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
              <i class="fas fa-cogs text-blue-500 dark:text-blue-300"></i>
              <span>Skills</span>
          </h4>
          <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm space-y-3">
              <div v-for="skill in selectedApplicant.alumni.skills" :key="skill.skill_id" class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                  <div class="flex items-center justify-between mb-1">
                      <span class="font-semibold text-gray-800 dark:text-gray-100">{{ skill.name }}</span>
                      <span v-if="skill.certificate || skill.certificate_file"
                          class="inline-flex items-center bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 px-2 py-0.5 rounded-full text-xs font-medium">
                          <i class="fas fa-certificate mr-1"></i> Certified
                      </span>
                  </div>
                  <p v-if="skill.certificate" class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ skill.certificate }}</p>
                  <div v-if="skill.certificate_file" class="flex gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
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
      <div v-if="selectedApplicant.alumni && selectedApplicant.alumni.certifications && selectedApplicant.alumni.certifications.length" class="mb-6">
          <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2">
              <i class="fas fa-certificate text-blue-500 dark:text-blue-300"></i>
              <span>Certifications</span>
          </h4>
          <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm space-y-3">
              <div v-for="cert in selectedApplicant.alumni.certifications" :key="cert.certification_id" class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                  <div class="flex items-center justify-between mb-1">
                      <span class="font-semibold text-gray-800 dark:text-gray-100">{{ cert.name }}</span>
                      <span v-if="cert.issue_date" class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(cert.issue_date) }}</span>
                  </div>
                  <p v-if="cert.issuer" class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ cert.issuer }}</p>
                  <div v-if="cert.certificate_file" class="flex gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                      <a :href="getCertificateUrl(cert.certificate_file)" target="_blank"
                      class="flex-1 flex items-center justify-center gap-2 py-2 px-3 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300 rounded-md text-sm font-medium hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors">
                          <i class="fas fa-eye text-xs"></i> Preview
                      </a>
                      <a :href="getCertificateUrl(cert.certificate_file)" download
                      class="flex-1 flex items-center justify-center gap-2 py-2 px-3 bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-300 rounded-md text-sm font-medium hover:bg-green-100 dark:hover:bg-green-900/50 transition-colors">
                          <i class="fas fa-download text-xs"></i> Download
                      </a>
                  </div>
              </div>
          </div>
      </div>
      <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-briefcase text-blue-500 dark:text-blue-300"></i> <span>Job Details</span></h4>
      <div class="grid grid-cols-1 gap-3 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-6">
        <div class="flex items-center gap-3"><i class="fas fa-briefcase text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Title:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedApplicant.job ? selectedApplicant.job.title : '' }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-map-marker-alt text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Location:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedApplicant.job ? selectedApplicant.job.location : '' }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-money-bill-wave text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Salary:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedApplicant.job ? selectedApplicant.job.salary : '' }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-building text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Company:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedApplicant.company_name || (selectedApplicant.job ? selectedApplicant.job.company_name : '') }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-calendar-alt text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Posted:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedApplicant.job ? formatDate(selectedApplicant.job.created_at) : '' }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-info-circle text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Status:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedApplicant.job ? selectedApplicant.job.job_status : '' }}</span></div>
      </div>
      <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-file-alt text-blue-500 dark:text-blue-300"></i> <span>Job Description</span></h4>
      <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-6 text-gray-700 dark:text-gray-200">
        {{ selectedApplicant.job ? selectedApplicant.job.description : '' }}
      </div>
      <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-tasks text-blue-500 dark:text-blue-300"></i> <span>Requirements</span></h4>
      <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-6 text-gray-700 dark:text-gray-200">
        <ul>
          <li v-for="(req, idx) in (selectedApplicant.job && selectedApplicant.job.requirements ? selectedApplicant.job.requirements.split('\n') : [])" :key="'req'+idx">{{ req }}</li>
        </ul>
      </div>
      <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-award text-blue-500 dark:text-blue-300"></i> <span>Qualifications</span></h4>
      <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-6 text-gray-700 dark:text-gray-200">
        <ul>
          <li v-for="(qual, idx) in (selectedApplicant.job && selectedApplicant.job.qualifications ? selectedApplicant.job.qualifications.split('\n') : [])" :key="'qual'+idx">{{ qual }}</li>
        </ul>
      </div>
      <div class="mb-6">
        <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-envelope-open-text text-blue-500 dark:text-blue-300"></i> <span>Cover Letter</span></h4>
        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm">
          <p v-if="selectedApplicant.alumni && selectedApplicant.alumni.cover_letter_text" class="text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ selectedApplicant.alumni.cover_letter_text }}</p>
          <a v-else-if="selectedApplicant.alumni && selectedApplicant.alumni.cover_letter_file" :href="'uploads/cover_letters/' + selectedApplicant.alumni.cover_letter_file" target="_blank" class="inline-flex items-center gap-2 text-blue-700 dark:text-blue-300 underline">
            <i class="fas fa-file"></i> View uploaded cover letter
          </a>
          <p v-else class="text-gray-500 dark:text-gray-400 italic">No cover letter submitted.</p>
        </div>
      </div>
      <div class="mb-6">
        <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-question-circle text-blue-500 dark:text-blue-300"></i> <span>Applicant Question</span></h4>
        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm">
          <template v-if="selectedApplicant.job && selectedApplicant.job.answers && selectedApplicant.job.answers.length">
            <div v-for="a in selectedApplicant.job.answers" :key="a.question_id" class="mb-3">
              <p class="text-gray-700 dark:text-gray-200 mb-2">{{ a.question_text }}</p>
              <p class="text-gray-700 dark:text-gray-200 whitespace-pre-line border-t border-gray-200 dark:border-gray-700 pt-2"><strong>Answer:</strong> {{ a.answer_text || 'No answer provided.' }}</p>
            </div>
          </template>
          <p v-else class="text-gray-500 dark:text-gray-400 italic">No question was set by the employer for this job.</p>
        </div>
      </div>
      <div class="flex flex-col md:flex-row gap-3 mt-6 w-full">
            <button @click="contactAlumni(selectedApplicant.alumni)"
                class="flex-1 px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition flex items-center justify-center gap-2 text-sm sm:text-base">
            <i class="fas fa-envelope"></i> Contact
            </button>
      </div>
    </div>
  </div>
</div>
<!-- Delete Confirmation Modal -->
<div v-if="showDeleteModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
        <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100">Confirm Delete</h3>
        <p class="mb-6 text-gray-700 dark:text-gray-200">Are you sure you want to delete this applicant?</p>
        <div class="flex justify-end gap-2">
            <button class="px-4 py-2 rounded bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 hover:bg-gray-400 dark:hover:bg-gray-700" @click="showDeleteModal = false">Cancel</button>
            <button class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700 transition" @click="confirmDeleteApplicant">Delete</button>
        </div>
    </div>
</div>

@endverbatim
@endsection
