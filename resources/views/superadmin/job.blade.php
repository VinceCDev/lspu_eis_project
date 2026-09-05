@extends('layouts.admin')
@section('content')
@verbatim
<div class="bg-white dark:bg-gray-700 rounded-xl shadow-xl main-shadow p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <h2 class="text-2xl font-bold mb-2 md:mb-0 text-gray-800 dark:text-gray-100">Job Postings</h2>
        <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full md:w-auto">
            <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition w-full sm:w-auto justify-center" @click="openAddModal">
                <i class="fas fa-plus"></i> Post New Job
            </button>
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
                <input type="text" class="form-input w-full pl-10 px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search jobs..." v-model="searchQuery" @input="filterJobs">
            </div>
        </div>
        <div class="flex flex-col sm:flex-row gap-2 w-full lg:ml-20">
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto lg:w-[250px]">
                <option value="">All Company</option>
                <option v-for="company in uniqueCompanies" :key="company">{{ company }}</option>
            </select>
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto lg:w-[250px]">
                <option value="">All Types</option>
                <option v-for="type in uniqueTypes" :key="type">{{ type }}</option>
            </select>
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto lg:w-[210px]">
                <option value="">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Closed">Closed</option>
            </select>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-center">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                    <th scope="col" class="px-4 py-2 text-center">Job Title</th>
                    <th scope="col" class="px-4 py-2 text-center">Company</th>
                    <th scope="col" class="px-4 py-2 text-center">Type</th>
                    <th scope="col" class="px-4 py-2 text-center">Location</th>
                    <th scope="col" class="px-4 py-2 text-center">Status</th>
                    <th scope="col" class="px-4 py-2 text-center">Posted Date</th>
                    <th scope="col" class="px-4 py-2 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="job in paginatedJobs" :key="job.id" class="border-b border-gray-200 dark:border-gray-600">
                    <td class="px-4 py-2 font-semibold text-gray-800 dark:text-gray-200 text-center">{{ job.title }}</td>
                    <td class="px-4 py-2 text-gray-800 dark:text-gray-200 text-center">{{ job.company }}</td>
                    <td class="px-4 py-2 text-gray-800 dark:text-gray-200 text-center">{{ job.type }}</td>
                    <td class="px-4 py-2 text-gray-800 dark:text-gray-200 text-center">{{ job.location }}</td>
                    <td class="px-4 py-2 text-center">
                        <span :class="['inline-block px-2 py-1 rounded text-xs font-semibold', job.status === 'Active' ? 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200' : 'bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-200']">
                            {{ job.status }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-gray-800 dark:text-gray-200 text-center">{{ formatDate(job.created_at) }}</td>
                    <td class="px-4 py-2 text-center">
                        <div class="relative inline-block text-left">
                            <button @click="toggleActionDropdown(job.id)" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none text-gray-500 dark:text-gray-200">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div v-if="actionDropdown === job.id" class="origin-top-right absolute right-0 mt-2 w-32 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5 z-10">
                                <div class="py-1" @click="actionDropdown = null">
                                    <a href="#" role="button" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" @click.prevent="viewJob(job)"><i class="fas fa-eye mr-2"></i>View</a>
                                    <a href="#" role="button" class="block px-4 py-2 text-sm text-yellow-600 hover:bg-yellow-100 dark:hover:bg-yellow-800" @click.prevent="openEditModal(job)"><i class="fas fa-edit mr-2"></i>Edit</a>
                                    <a href="#" role="button" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 dark:hover:bg-red-800" @click.prevent="confirmDelete(job)"><i class="fas fa-trash mr-2"></i>Delete</a>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr v-if="filteredJobs.length === 0">
                    <td colspan="7" class="py-12 text-center text-gray-500 dark:text-gray-400">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fas fa-briefcase text-4xl text-gray-300 mb-2"></i>
                            <span class="text-lg text-gray-400">No job postings found</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="flex flex-col md:flex-row md:items-center justify-center md:justify-between mt-4 gap-2">
        <div class="text-gray-600 dark:text-gray-300 text-sm text-center md:text-left w-full md:w-auto flex justify-center md:justify-start">
            Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredJobs.length) }} of {{ filteredJobs.length }} entries
        </div>
        <div class="flex gap-1 justify-center md:justify-start items-center">
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
<!-- Add/Edit Job Modal -->
<div v-if="showJobModal && (modalMode === 'add' || modalMode === 'edit')" class="fixed inset-0 z-[210] flex items-center justify-center bg-black bg-opacity-50 pointer-events-auto" role="dialog" aria-modal="true" data-modal="add-edit-job-modal">
  <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl mx-2 p-6 relative max-h-[90vh] overflow-y-auto pointer-events-auto">
    <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="closeJobModal"><i class="fas fa-times"></i></button>
    <div class="mt-3 text-left w-full">
      <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
        {{ modalMode === 'edit' ? 'Edit Job Posting' : 'Post New Job' }}
      </h3>
      <form @submit.prevent="modalMode === 'edit' ? updateJob() : addJob()">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
          <div>
            <label for="jobTitle" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Job Title*</label>
            <input id="jobTitle" type="text" v-model="jobForm.title" required autocomplete="off"
                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                @input="onJobTitleChange">
          </div>
          <div>
            <label for="jobCompany" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Company*</label>
            <select id="jobCompany" v-model="jobForm.employer_id" required
              class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
              <option value="">Select Company</option>
              <option v-for="company in companiesList" :key="company.user_id" :value="company.user_id">{{ company.company_name }}</option>
            </select>
          </div>
          <div>
            <label for="jobType" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type*</label>
            <select id="jobType" v-model="jobForm.type" required
              class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
              <option value="">Select Type</option>
              <option value="Full-time">Full-time</option>
              <option value="Part-time">Part-time</option>
              <option value="Contract">Contract</option>
              <option value="Freelance">Freelance</option>
            </select>
          </div>
          <div>
            <label for="jobWorkSetup" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Work Setup*</label>
            <select id="jobWorkSetup" v-model="jobForm.work_setup" required
              class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
              <option value="">Select Work Setup</option>
              <option v-for="setup in workSetups" :key="setup" :value="setup">{{ setup }}</option>
            </select>
          </div>
          <div>
            <label for="jobClassification" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Classification*</label>
            <select id="jobClassification" v-model="jobForm.classification" required
              class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
              <option value="">Select Classification</option>
              <option v-for="classification in jobClassifications" :key="classification" :value="classification">{{ classification }}</option>
            </select>
          </div>
          <div>
            <label for="jobLocation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Location*</label>
            <div class="relative">
                <input id="jobLocation" type="text" v-model="jobForm.location" required autocomplete="off"
                  class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                  @input="fetchJobLocationSuggestions" @focus="showJobLocationSuggestions = true" @blur="hideJobLocationSuggestions" placeholder="Start typing address...">
                <ul v-if="showJobLocationSuggestions && jobLocationSuggestions.length" class="absolute z-10 left-0 right-0 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded shadow mt-1 max-h-48 overflow-y-auto">
                  <li v-for="suggestion in jobLocationSuggestions" :key="suggestion" @mousedown.prevent="selectJobLocationSuggestion(suggestion)" class="px-4 py-2 cursor-pointer text-gray-800 dark:text-gray-100 hover:bg-blue-100 dark:hover:bg-blue-900 hover:text-blue-900 dark:hover:text-white transition">
                    {{ suggestion }}
                  </li>
                </ul>
            </div>
          </div>
          <div>
            <label for="jobSalary" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Salary</label>
            <input id="jobSalary" type="text" v-model="jobForm.salary" autocomplete="off"
                class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="Optional">
        </div>
          <div>
            <label for="jobStatus" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status*</label>
            <select id="jobStatus" v-model="jobForm.status" required
              class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
              <option value="Active">Active</option>
              <option value="Closed">Closed</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-1 gap-4 mt-4">
            <div>
                <label for="jobDescription" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description*</label>
                <textarea id="jobDescription" v-model="jobForm.description" rows="2" required
              class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
            </div>
            <div>
            <label for="jobRequirements" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Requirements*
            </label>
            <div class="relative mt-1">
                <textarea id="jobRequirements" v-model="jobForm.requirements" rows="3" required
                        class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                        @input="generateSuggestions('requirements')"
                        @focus="generateSuggestions('requirements')"
                        @blur="hideSuggestionsWithDelay('requirements')"></textarea>
                <div v-if="showRequirementsSuggestions && requirementsSuggestions.length"
                    class="absolute z-20 left-0 right-0 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded shadow-lg max-h-60 overflow-y-auto">
                <div class="p-2 bg-gray-100 dark:bg-gray-700 flex justify-between items-center text-gray-800 dark:text-white">
                    <span class="text-sm font-medium">Suggestions for "{{ jobForm.title }}"</span>
                    <button @click="closeSuggestions('requirements')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                    </button>
                </div>
                <button @click="applyAllSuggestions('requirements')"
                        class="w-full text-left px-4 py-2 text-sm bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50">
                    <i class="fas fa-check-double mr-2"></i> Apply All Suggestions
                </button>
                <div v-for="(suggestion, index) in requirementsSuggestions" :key="index"
                    @mousedown.prevent="selectSuggestion('requirements', suggestion)"
                    class="px-4 py-2 cursor-pointer text-gray-800 dark:text-gray-100 hover:bg-blue-100 dark:hover:bg-blue-900 hover:text-blue-900 dark:hover:text-blue-100 transition border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                     {{ suggestion }}
                </div>
                </div>
            </div>
            </div>

            <div>
            <label for="jobQualifications" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                Qualifications*
            </label>
            <div class="relative mt-1">
                <textarea id="jobQualifications" v-model="jobForm.qualifications" rows="3" required
                        class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                        @input="generateSuggestions('qualifications')"
                        @focus="generateSuggestions('qualifications')"
                        @blur="hideSuggestionsWithDelay('qualifications')"></textarea>
                <div v-if="showQualificationsSuggestions && qualificationsSuggestions.length"
                    class="absolute z-20 left-0 right-0 top-full mt-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded shadow-lg max-h-60 overflow-y-auto">
                <div class="p-2 bg-gray-100 dark:bg-gray-700 flex justify-between items-center text-gray-800 dark:text-white">
                    <span class="text-sm font-medium">Suggestions for "{{ jobForm.title }}"</span>
                    <button @click="closeSuggestions('qualifications')" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                    </button>
                </div>
                <button @click="applyAllSuggestions('qualifications')"
                        class="w-full text-left px-4 py-2 text-sm bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50">
                    <i class="fas fa-check-double mr-2"></i> Apply All Suggestions
                </button>
                <div v-for="(suggestion, index) in qualificationsSuggestions" :key="index"
                    @mousedown.prevent="selectSuggestion('qualifications', suggestion)"
                    class="px-4 py-2 cursor-pointer text-gray-800 dark:text-gray-100 hover:bg-blue-100 dark:hover:bg-blue-900 hover:text-blue-900 dark:hover:text-blue-100 transition border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                     {{ suggestion }}
                </div>
                </div>
            </div>
            </div>
            <div>
                <label for="jobEmployerQuestion" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Applicant Question <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <textarea id="jobEmployerQuestion" v-model="jobForm.employer_question" rows="2"
                    placeholder="e.g. Why are you interested in this role?"
                    class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                <label v-if="jobForm.employer_question" class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" v-model="jobForm.employer_question_required" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                    Require applicants to answer this question
                </label>
            </div>
        </div>
        <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
            <button type="submit"
                :disabled="isSubmitting"
                :class="[
                    'w-full inline-flex justify-center items-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm transition-colors duration-200 min-h-[42px]',
                    isSubmitting
                        ? 'bg-blue-500 cursor-not-allowed'
                        : 'bg-blue-600 hover:bg-blue-700 text-white'
                ]">
                <span class="inline-flex items-center gap-2">
                    <i v-if="isSubmitting" class="fas fa-spinner fa-spin"></i>
                    <span>{{ isSubmitting ? 'Posting...' : (modalMode === 'edit' ? 'Update Job' : 'Post Job') }}</span>
                </span>
            </button>
            <button type="button"
                :disabled="isSubmitting"
                @click="closeJobModal"
                :class="[
                    'mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm',
                    isSubmitting ? 'opacity-50 cursor-not-allowed' : ''
                ]">
                Cancel
            </button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- View Job Modal -->
<div v-if="showJobModal && modalMode === 'view'" class="fixed inset-0 z-[210] flex items-center justify-center bg-black bg-opacity-50 pointer-events-auto" role="dialog" aria-modal="true" data-modal="view-job-modal">
  <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-xl mx-2 p-0 relative max-h-[95vh] overflow-y-auto pointer-events-auto">
    <button class="absolute top-3 right-3 flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full shadow hover:bg-gray-200 dark:hover:bg-gray-600 transition text-base font-semibold z-20" @click="closeJobModal" id="close-view-job-modal-btn">
      <i class="fas fa-times"></i> <span>Close</span>
    </button>
    <div class="rounded-t-2xl bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400 dark:from-blue-900 dark:via-blue-800 dark:to-blue-700 px-0 pt-6 pb-8 flex flex-col items-center relative">
      <div class="absolute top-4 left-4 bg-white dark:bg-gray-700 rounded-full p-2 shadow-lg">
        <i class="fas fa-briefcase text-blue-600 dark:text-blue-300 text-2xl"></i>
      </div>
      <img v-if="selectedJob.logo" :src="'/uploads/logos/' + selectedJob.logo" alt="Company Logo" class="w-28 h-28 rounded-full object-cover border-4 border-white dark:border-gray-700 shadow-xl mb-2 mt-2">
      <div v-else class="w-28 h-28 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center border-4 border-white dark:border-gray-700 shadow-xl mb-2 mt-2">
        <i class="fas fa-briefcase text-4xl text-gray-400"></i>
      </div>
      <h3 class="text-3xl font-extrabold text-white drop-shadow-lg mb-1 text-center">{{ selectedJob.title }}</h3>
      <span :class="['inline-block mt-1 px-3 py-1 rounded-full text-xs font-semibold shadow', selectedJob.status === 'Active' ? 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200' : 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200']">{{ selectedJob.status }}</span>
    </div>
    <div class="px-6 py-6">
      <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-info-circle text-blue-500 dark:text-blue-300"></i> <span>Job Details</span></h4>
      <div class="grid grid-cols-1 gap-3 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm">
        <div class="flex items-center gap-3"><i class="fas fa-building text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Company:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedJob.company }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-map-marker-alt text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Location:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedJob.location }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-calendar-alt text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Posted Date:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ formatDate(selectedJob.created_at) }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-money-bill-wave text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Salary:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedJob.salary || 'N/A' }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-clipboard-list text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Type:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedJob.type }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-house-laptop text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Work Setup:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedJob.work_setup }}</span></div>
        <div class="flex items-center gap-3"><i class="fas fa-layer-group text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Classification:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ selectedJob.classification }}</span></div>
      </div>
      <div class="my-6 border-t border-gray-200 dark:border-gray-700"></div>
      <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-align-left text-blue-500 dark:text-blue-300"></i> <span>Description</span></h4>
      <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-4 whitespace-pre-line text-gray-700 dark:text-gray-200">{{ selectedJob.description }}</div>
      <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-tasks text-blue-500 dark:text-blue-300"></i> <span>Requirements</span></h4>
      <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-4 whitespace-pre-line text-gray-700 dark:text-gray-200">• {{ selectedJob.requirements }}</div>
      <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-graduation-cap text-blue-500 dark:text-blue-300"></i> <span>Qualifications</span></h4>
      <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-4 whitespace-pre-line text-gray-700 dark:text-gray-200">• {{ selectedJob.qualifications }}</div>
      <div v-if="selectedJob.employer_question">
        <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-question-circle text-blue-500 dark:text-blue-300"></i> <span>Applicant Question</span></h4>
        <div class="bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm mb-4 text-gray-700 dark:text-gray-200">
          {{ selectedJob.employer_question }}
          <span v-if="Number(selectedJob.employer_question_required)" class="ml-2 inline-block px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200">Required</span>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Delete Confirmation Modal -->
<div v-if="showDeleteModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50 lg:items-start lg:pt-8" role="dialog" aria-modal="true">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
        <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100">Confirm Delete</h3>
        <p class="mb-6 text-gray-700 dark:text-gray-200">Are you sure you want to delete this job posting?</p>
        <div class="flex justify-end gap-2">
            <button class="px-4 py-2 rounded bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 hover:bg-gray-400 dark:hover:bg-gray-700" @click="showDeleteModal = false">Cancel</button>
            <button class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700 transition" @click="deleteJob">Delete</button>
        </div>
    </div>
</div>

@endverbatim
@endsection
