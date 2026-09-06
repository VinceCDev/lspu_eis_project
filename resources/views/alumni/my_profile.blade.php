@extends('layouts.alumni')
@section('content')
@verbatim
    <div>
        <!-- Loading Spinner Overlay -->
        <div v-if="loading" class="fixed inset-0 flex items-center justify-center bg-white dark:bg-gray-900 z-[9999]">
            <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-blue-500" role="status"
                aria-live="polite"></div>
            <span class="sr-only">Loading...</span>
        </div>
        <!-- Toast Notification Area -->
        <div class="fixed top-4 right-4 z-[9999] space-y-3 w-full max-w-xs" aria-live="polite">
            <transition-group enter-active-class="transform transition duration-300 ease-out"
                enter-from-class="translate-x-20 opacity-0" enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transform transition duration-200 ease-in"
                leave-from-class="translate-x-0 opacity-100" leave-to-class="translate-x-20 opacity-0">
                <div v-for="notification in notifications" :key="notification.id" :class="{
                        'bg-green-100 border-green-500 text-green-700': notification.type === 'success',
                        'bg-blue-100 border-blue-500 text-blue-700': notification.type === 'info',
                        'bg-red-100 border-red-500 text-red-700': notification.type === 'error',
                        'dark:bg-green-900/80 dark:border-green-700 dark:text-green-200': notification.type === 'success' && darkMode,
                        'dark:bg-blue-900/80 dark:border-blue-700 dark:text-blue-200': notification.type === 'info' && darkMode,
                        'dark:bg-red-900/80 dark:border-red-700 dark:text-red-200': notification.type === 'error' && darkMode
                    }" class="border-l-4 p-4 rounded-lg shadow-lg relative pr-8 flex items-start animate-slide-in"
                    role="alert" tabindex="0">
                    <div class="flex-shrink-0 mt-1">
                        <i v-if="notification.type === 'success'" class="fas fa-check-circle text-lg"></i>
                        <i v-if="notification.type === 'info'" class="fas fa-info-circle text-lg"></i>
                        <i v-if="notification.type === 'error'" class="fas fa-exclamation-circle text-lg"></i>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-sm font-bold capitalize">{{ notification.type }}</h3>
                        <p class="text-sm mt-1">{{ notification.message }}</p>
                    </div>
                    <button @click="notifications = notifications.filter(n => n.id !== notification.id)"
                        class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                        aria-label="Close notification">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </transition-group>
        </div>
        <!-- Header -->

        <!-- Profile Main Content -->
        <main class="max-w-5xl mx-auto px-4 py-8">
            <!-- Hero/Profile Card -->
            <section
                class="relative bg-white dark:bg-gray-700 rounded-xl shadow-md p-4 sm:p-6 mb-6 overflow-hidden">
                <!-- Background image with blue opacity overlay -->
                <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('<?= asset('assets/images/lspu_campus.jpg') ?>')">
                    <div class="absolute inset-0 bg-gradient-to-br from-slate-800/60 via-slate-700/50 to-blue-700/60">
                    </div>
                </div>

                <div class="relative z-10 flex flex-col items-center text-center">
                    <div class="relative mb-4 sm:mb-6">
                        <img v-if="profilePicData.file_name"
                            :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile Photo"
                            class="w-24 h-24 sm:w-40 sm:h-40 rounded-full object-cover border-4 border-white dark:border-gray-300 shadow-lg">
                        <div v-else
                            class="w-24 h-24 sm:w-40 sm:h-40 rounded-full border-4 border-white dark:border-gray-300 shadow-lg bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-3xl text-gray-400 dark:text-gray-300">
                            <i class="fas fa-user"></i>
                        </div>
                        <button type="button"
                            class="absolute -bottom-1 -right-1 sm:-bottom-2 sm:-right-2 bg-blue-600 text-white rounded-full p-1.5 sm:p-2 cursor-pointer hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                            @click="openPhotoModal" aria-label="Change profile photo">
                            <i class="fas fa-camera text-xs sm:text-sm" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="mb-4 sm:mb-6 w-full">
                        <h1 class="text-2xl sm:text-4xl font-bold text-white mb-2 drop-shadow-lg">{{ profile.name }}
                        </h1>
                        <p class="text-lg sm:text-xl text-white mb-4 drop-shadow-lg">Alumni</p>
                        <div class="flex flex-row flex-wrap gap-2 sm:gap-4 justify-center mb-4 sm:mb-6 w-full">
                            <div
                                class="flex items-center justify-center bg-white bg-opacity-20 backdrop-blur-sm rounded-lg px-3 py-2 sm:px-4 sm:py-2 min-w-[120px]">
                                <i class="fas fa-envelope text-white mr-2 text-sm sm:text-base"></i>
                                <span class="text-white text-sm sm:text-base">{{ profile.email || 'No email specified'
                                    }}</span>
                            </div>
                            <div
                                class="flex items-center justify-center bg-white bg-opacity-20 backdrop-blur-sm rounded-lg px-3 py-2 sm:px-4 sm:py-2 min-w-[120px]">
                                <i class="fas fa-phone text-white mr-2 text-sm sm:text-base"></i>
                                <span class="text-white text-sm sm:text-base">{{ profile.contact || 'No phone specified'
                                    }}</span>
                            </div>
                            <div
                                class="flex items-center justify-center bg-white bg-opacity-20 backdrop-blur-sm rounded-lg px-3 py-2 sm:px-4 sm:py-2 min-w-[120px]">
                                <i class="fas fa-map-marker-alt text-white mr-2 text-sm sm:text-base"></i>
                                <span class="text-white text-sm sm:text-base">{{ (profile.city && profile.province) ?
                                    (profile.city + ', ' + profile.province) : 'No location specified' }}</span>
                            </div>
                        </div>
                    </div>
                    <button @click="editProfile"
                        class="bg-white text-blue-600 px-6 py-2 sm:px-8 sm:py-3 rounded-lg hover:bg-gray-100 transition-colors shadow-md font-semibold text-sm sm:text-base">
                        <i class="fas fa-edit mr-2"></i>Edit Profile
                    </button>
                </div>
            </section>

            <!-- Personal Information Section -->
            <section class="bg-white dark:bg-gray-700 rounded-xl shadow-md p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 uppercase truncate">Personal
                        Information</h2>
                    <button @click="editProfile"
                        class="bg-blue-600 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap text-sm sm:text-base flex-shrink-0 ml-3 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors w-auto">
                        <i class="fas fa-edit mr-1"></i>Edit
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Full Name</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.first_name }} {{
                            profile.middle_name }} {{ profile.last_name }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Email</div>
                        <div class="text-gray-800 dark:text-gray-200">{{ profile.email || 'Not specified' }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Phone</div>
                        <div class="text-gray-800 dark:text-gray-200">{{ profile.contact || 'Not specified' }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">City</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.city || 'Not specified' }}
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Province</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.province || 'Not specified'
                            }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Birthdate</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.birthdate || 'Not specified'
                            }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Gender</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.gender || 'Not specified' }}
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Civil Status</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.civil_status || 'Not specified' }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Campus</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.campus_name || 'Not specified' }}
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">College</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.college || 'Not specified' }}
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Program</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.course || 'Not specified' }}
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Year Graduated</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.year_graduated || 'Not specified' }}</div>
                    </div>
                </div>
            </section>

            <!-- Education Section -->
            <section class="bg-white dark:bg-gray-700 rounded-xl shadow-md p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 uppercase">Education</h2>
                    <button @click="showEducationModal = true"
                        class="bg-green-600 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap text-sm sm:text-base flex-shrink-0 ml-3 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors w-auto">
                        <i class="fas fa-plus mr-1"></i>Add
                    </button>
                </div>
                <div v-if="profile.education.length > 0" class="space-y-4 sm:space-y-6">
                    <div v-for="(edu, index) in profile.education" :key="index"
                        class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 sm:p-6">
                        <!-- Invisible education_id for internal use -->
                        <input type="hidden" :value="edu.education_id" />
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-4">
                            <div class="flex-1">
                                <h3
                                    class="text-lg sm:text-xl font-semibold text-gray-800 dark:text-gray-100 uppercase mb-1">
                                    {{ edu.degree }}</h3>
                                <p class="text-base sm:text-lg text-blue-600 dark:text-blue-400 uppercase mb-1">{{
                                    edu.school }}</p>
                                <p v-if="edu.start_date || edu.end_date" class="text-sm sm:text-base text-gray-600 dark:text-gray-300">
                                    {{ formatDate(edu.start_date) }} - {{ edu.current ? 'Present' :
                                    formatDate(edu.end_date) }}
                                </p>
                            </div>
                            <div class="flex gap-2 flex-shrink-0">
                                <button @click="editEducation(index)"
                                    class="bg-blue-600 text-white px-3 py-1.5 sm:px-3 sm:py-1 rounded hover:bg-blue-700 transition-colors text-sm">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </button>
                                <button @click="deleteEducation(index)"
                                    class="bg-red-600 text-white px-3 py-1.5 sm:px-3 sm:py-1 rounded hover:bg-red-700 transition-colors text-sm">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8 sm:py-12">
                    <i class="fas fa-graduation-cap text-3xl sm:text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 dark:text-gray-400 text-sm sm:text-base">No education information added yet
                    </p>
                </div>
            </section>

            <!-- Skills Section -->
            <section class="bg-white dark:bg-gray-700 rounded-xl shadow-md p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-gray-100 uppercase">Skills</h2>
                    <button @click="showSkillsModal = true"
                        class="bg-green-600 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap text-sm sm:text-base flex-shrink-0 ml-3 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors w-auto">
                        <i class="fas fa-plus mr-1"></i>Add
                    </button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <div v-for="(skill, index) in profile.skills" :key="skill.skill_id"
                        class="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-3 py-1 rounded-full flex items-center gap-2 max-w-full">
                        <span class="truncate">{{ skill.name }}</span>
                        <button @click="removeSkill(index)"
                            class="ml-1 text-red-500 hover:text-red-700 dark:hover:text-red-300 flex-shrink-0">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div v-if="profile.skills.length === 0" class="text-gray-500 dark:text-gray-400 text-sm">No skills
                        added yet</div>
                </div>
            </section>

            <!-- Certifications Section (independent of Skills: own table, own CRUD) -->
            <section class="bg-white dark:bg-gray-700 rounded-xl shadow-md p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-gray-100 uppercase">Certifications</h2>
                    <button @click="openCertificationModal"
                        class="bg-green-600 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap text-sm sm:text-base flex-shrink-0 ml-3">
                        <i class="fas fa-plus mr-1"></i>Add
                    </button>
                </div>
                <div v-if="profile.certifications.length > 0" class="space-y-3">
                    <div v-for="(cert, index) in profile.certifications" :key="cert.certification_id"
                        class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="fas fa-certificate text-2xl text-yellow-500 flex-shrink-0"></i>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 dark:text-gray-100 truncate">{{ cert.name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                    {{ cert.issuer }}<span v-if="cert.issuer && cert.issue_date"> &middot; </span>{{ formatDate(cert.issue_date) }}
                                </p>
                                <button v-if="cert.certificate_file" @click="viewCertificationFile(cert)"
                                    class="text-xs text-blue-600 dark:text-blue-400 hover:underline mt-1">
                                    <i class="fas fa-external-link-alt mr-1"></i>View certificate
                                </button>
                            </div>
                        </div>
                        <div class="flex gap-2 flex-shrink-0">
                            <button @click="editCertification(index)"
                                class="bg-blue-600 text-white px-3 py-1.5 rounded hover:bg-blue-700 transition-colors text-sm">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </button>
                            <button @click="deleteCertification(index)"
                                class="bg-red-600 text-white px-3 py-1.5 rounded hover:bg-red-700 transition-colors text-sm">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8 sm:py-12">
                    <i class="fas fa-certificate text-3xl sm:text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 dark:text-gray-400 text-sm sm:text-base">No certifications added yet</p>
                </div>
            </section>

            <!-- Add/Edit Certification Modal -->
            <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
                enter-to-class="modal-enter-to" leave-active-class="modal-leave-active"
                leave-from-class="modal-leave-from" leave-to-class="modal-leave-to">
                <div v-if="showCertificationModal"
                    class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative max-h-[90vh] overflow-y-auto">
                        <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                            @click="closeCertificationModal" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">
                            {{ editingCertificationIndex === null ? 'Add' : 'Edit' }} Certification
                        </h3>
                        <form @submit.prevent="saveCertification">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Certification
                                        Name*</label>
                                    <input type="text" v-model="editCertificationData.name" required
                                        placeholder="e.g. AWS Certified Cloud Practitioner"
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Issued
                                        By</label>
                                    <input type="text" v-model="editCertificationData.issuer" placeholder="e.g. Amazon Web Services"
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date
                                        Issued</label>
                                    <input type="date" v-model="editCertificationData.issue_date"
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Certificate
                                        File</label>
                                    <input type="file" @change="handleCertificationFileUpload" accept=".pdf,.jpg,.jpeg,.png,.gif"
                                        class="block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                    <p v-if="certificationFilePreview" class="text-xs text-gray-500 mt-1">{{ certificationFilePreview }}</p>
                                </div>
                            </div>
                            <div class="flex justify-end gap-3 mt-6">
                                <button type="button" @click="closeCertificationModal"
                                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                                <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Save Certification</button>
                            </div>
                        </form>
                    </div>
                </div>
            </transition>

            <!-- Delete Certification Confirmation Modal -->
            <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
                enter-to-class="modal-enter-to" leave-active-class="modal-leave-active"
                leave-from-class="modal-leave-from" leave-to-class="modal-leave-to">
                <div v-if="showDeleteCertificationModal"
                    class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Confirm Delete</h3>
                        <p class="mb-6 text-gray-700 dark:text-gray-300">Are you sure you want to delete this certification?</p>
                        <div class="flex justify-end gap-3">
                            <button @click="cancelDeleteCertification"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                            <button @click="confirmDeleteCertification"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Work Experience Section -->
            <section class="bg-white dark:bg-gray-700 rounded-xl shadow-md p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-gray-100 uppercase">Work Experience
                    </h2>
                    <button @click="showExperienceModal = true"
                        class="bg-green-600 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap text-sm sm:text-base flex-shrink-0 ml-3 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors w-auto">
                        <i class="fas fa-plus mr-1"></i>Add
                    </button>
                </div>
                <div v-if="profile.experiences.length > 0" class="space-y-4 sm:space-y-6">
                    <div v-for="(exp, index) in profile.experiences" :key="index"
                        class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 sm:p-6">
                        <!-- Invisible experience_id for internal use -->
                        <input type="hidden" :value="exp.experience_id" />
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-4">
                            <div class="flex-1">
                                <h3
                                    class="text-lg sm:text-xl font-semibold text-gray-800 dark:text-gray-100 uppercase mb-1">
                                    {{ exp.title }}</h3>
                                <p class="text-base sm:text-lg text-blue-600 dark:text-blue-400 uppercase mb-1">{{
                                    exp.company }}</p>
                                <p class="text-sm sm:text-base text-gray-600 dark:text-gray-300">
                                    {{ formatDate(exp.start_date) }} - {{ exp.current ? 'Present' :
                                    formatDate(exp.end_date) }}
                                </p>
                                <p class="text-sm text-gray-700 dark:text-gray-200 mt-1">
                                    <span class="font-semibold">Location of Work:</span> {{ exp.location_of_work || 'Not specified' }}
                                </p>
                                <p class="text-sm text-gray-700 dark:text-gray-200 mt-1">
                                    <span class="font-semibold">Employment Status:</span> {{ exp.employment_status ||
                                    'Not specified' }}
                                </p>
                                <p class="text-sm text-gray-700 dark:text-gray-200 mt-1">
                                    <span class="font-semibold">Employment Sector:</span> {{ exp.employment_sector ||
                                    'Not specified' }}
                                </p>
                            </div>
                            <div class="flex gap-2 flex-shrink-0">
                                <button @click="editExperience(index)"
                                    class="bg-blue-600 text-white px-3 py-1.5 sm:px-3 sm:py-1 rounded hover:bg-blue-700 transition-colors text-sm">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </button>
                                <button @click="deleteExperience(index)"
                                    class="bg-red-600 text-white px-3 py-1.5 sm:px-3 sm:py-1 rounded hover:bg-red-700 transition-colors text-sm">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </button>
                            </div>
                        </div>
                        <p class="text-sm sm:text-base text-gray-700 dark:text-gray-200 leading-relaxed">{{
                            exp.description }}</p>
                    </div>
                </div>
                <div v-else class="text-center py-8 sm:py-12">
                    <i class="fas fa-briefcase text-3xl sm:text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 dark:text-gray-400 text-sm sm:text-base">No work experience added yet</p>
                </div>
            </section>

            <!-- Success Stories Section -->
            <section class="bg-white dark:bg-gray-700 rounded-xl shadow-md p-6 mb-6">
                <div class="flex items-center justify-between mb-6 overflow-hidden">
                    <div class="flex items-center flex-1 min-w-0">
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-800 dark:text-gray-100 uppercase truncate">
                            Success Stories</h2>
                    </div>
                    <button @click="showSuccessStoryModal = true"
                        class="bg-gradient-to-r from-green-500 to-green-600 text-white px-3 sm:px-4 py-2 sm:py-2.5 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5 flex items-center flex-shrink-0 ml-3 text-sm sm:text-base whitespace-nowrap bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors w-auto">
                        <i class="fas fa-plus mr-2 text-xs sm:text-sm"></i>Share Story
                    </button>
                </div>
                <div v-if="successStories.length > 0" class="space-y-6">
                    <div v-for="(story, index) in successStories" :key="index"
                        class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 sm:p-6">
                        <div class="flex items-start justify-between mb-4">
                            <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100 flex items-center">
                                <i class="fas fa-trophy text-yellow-500 mr-3 text-sm"></i>
                                {{ story.title }}
                            </h3>
                            <div class="flex gap-2">
                                <button @click="editSuccessStory(index)"
                                    class="bg-gradient-to-r from-blue-500 to-blue-600 text-white px-3 py-1.5 rounded-md hover:from-blue-600 hover:to-blue-700 transition-all duration-200 text-sm shadow-sm flex items-center">
                                    <i class="fas fa-edit mr-1.5 text-xs"></i> Edit
                                </button>
                                <button @click="deleteSuccessStory(index)"
                                    class="bg-gradient-to-r from-red-500 to-red-600 text-white px-3 py-1.5 rounded-md hover:from-red-600 hover:to-red-700 transition-all duration-200 text-sm shadow-sm flex items-center">
                                    <i class="fas fa-trash mr-1.5 text-xs"></i> Delete
                                </button>
                            </div>
                        </div>
                        <p class="text-gray-700 dark:text-gray-200 mb-4 leading-relaxed italic">{{ story.content }}</p>
                        <div
                            class="text-sm text-gray-500 dark:text-gray-400 pt-3 border-t border-gray-100 dark:border-gray-500 flex flex-wrap items-center">
                            <span class="flex items-center mr-4">
                                <i class="far fa-calendar mr-1.5"></i> Posted on: {{ formatDate(story.created_at) }}
                            </span>
                            <span class="flex items-center">
                                <i class="far fa-flag mr-1.5"></i> Status:
                                <span class="font-semibold capitalize ml-1.5 px-2 py-0.5 rounded-full text-xs bg-opacity-10"
                                    :class="{
                                    'text-yellow-600 dark:text-yellow-400 bg-yellow-500': story.status === 'draft',
                                    'text-green-600 dark:text-green-400 bg-green-500': story.status === 'published',
                                    'text-gray-600 dark:text-gray-400 bg-gray-500': story.status === 'archived'
                                }">
                                    {{ story.status }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-12 px-4">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 bg-green-100 dark:bg-green-900/30 rounded-full mb-5">
                        <i class="fas fa-trophy text-3xl text-green-500 dark:text-green-400"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2">No success stories yet</h3>
                    <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto mb-6">Be the first to share your
                        achievement and inspire others!</p>
                    <button @click="showSuccessStoryModal = true"
                        class="bg-gradient-to-r from-green-500 to-green-600 text-white px-5 py-2.5 rounded-lg hover:from-green-600 hover:to-green-700 transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                        Share Your First Success Story
                    </button>
                </div>
            </section>

            <!-- Success Story Modal -->
            <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
                enter-to-class="modal-enter-to" leave-active-class="modal-leave-active"
                leave-from-class="modal-leave-from" leave-to-class="modal-leave-to">
                <div v-if="showSuccessStoryModal"
                    class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl mx-2 p-6 relative max-h-[90vh] overflow-y-auto">
                        <button
                            class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                            @click="closeSuccessStoryModal" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">{{
                            editingSuccessStoryIndex === null ? 'Share' : 'Edit' }} Success Story</h3>
                        <form @submit.prevent="saveSuccessStory">
                            <div class="space-y-4">
                                <div>
                                    <label
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title*</label>
                                    <input type="text" v-model="editSuccessStoryData.title"
                                        placeholder="Enter a title for your story" required
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Your
                                        Story*</label>
                                    <textarea v-model="editSuccessStoryData.content" rows="8"
                                        placeholder="Share your career success, achievement, or inspiring journey..."
                                        required
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                                </div>
                            </div>
                            <div class="flex justify-end gap-3 mt-6">
                                <button type="button" @click="closeSuccessStoryModal"
                                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                                <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Save
                                    Story</button>
                            </div>
                        </form>
                    </div>
                </div>
            </transition>

            <!-- Delete Success Story Confirmation Modal -->
            <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
                enter-to-class="modal-enter-to" leave-active-class="modal-leave-active"
                leave-from-class="modal-leave-from" leave-to-class="modal-leave-to">
                <div v-if="showDeleteSuccessStoryModal"
                    class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Confirm Delete</h3>
                        <p class="mb-6 text-gray-700 dark:text-gray-300">Are you sure you want to delete this success
                            story?</p>
                        <div class="flex justify-end gap-3">
                            <button @click="cancelDeleteSuccessStory"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                            <button @click="confirmDeleteSuccessStory"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Resume Section -->
            <section class="bg-white dark:bg-gray-700 rounded-xl shadow-md p-6 mb-6">
                <div class="flex items-center justify-between mb-6 flex-nowrap">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 uppercase whitespace-nowrap mr-4">
                        Resume</h2>
                    <div class="flex gap-2 flex-shrink-0">
                        <button v-if="!resumeData.resume_id" @click="showResumeModal = true"
                            class="bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap text-sm">
                            <i class="fas fa-upload mr-1"></i>Upload
                        </button>
                        <button v-else @click="showResumeModal = true"
                            class="bg-yellow-500 text-white px-3 py-2 rounded-lg hover:bg-yellow-600 transition-colors whitespace-nowrap text-sm bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition-colors">
                            <i class="fas fa-edit mr-1"></i>Change
                        </button>
                        <button v-if="resumeData.resume_id" @click="openDeleteResumeModal"
                            class="bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 transition-colors whitespace-nowrap text-sm bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors">
                            <i class="fas fa-trash mr-1"></i>Delete
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <i :class="fileIconClass(resumeData.file_name)" class="text-3xl"></i>
                    <div v-if="resumeData.resume_id" class="text-gray-800 dark:text-gray-200 min-w-0 flex-1">
                        <!-- Wrap the link in a container with truncation -->
                        <div class="flex items-center gap-2 truncate">
                            <a :href="'uploads/resumes/' + resumeData.file_name" target="_blank"
                                class="underline hover:text-blue-700 dark:hover:text-blue-300 truncate"
                                :title="resumeData.file_name">
                                {{ resumeData.file_name }}
                            </a>
                            <span class="flex-shrink-0 text-xs text-gray-500">(Uploaded: {{
                                formatDate(resumeData.uploaded_at) }})</span>
                        </div>
                    </div>
                    <div v-else class="text-gray-500 dark:text-gray-400">No resume uploaded</div>
                </div>
            </section>

            <!-- Resume Modal -->
            <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
                enter-to-class="modal-enter-to" leave-active-class="modal-leave-active"
                leave-from-class="modal-leave-from" leave-to-class="modal-leave-to">
                <div v-if="showResumeModal"
                    class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                    <div
                        class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative max-h-[90vh] overflow-y-auto">
                        <button
                            class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                            @click="closeResumeModal" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">{{ resumeData.resume_id
                            ? 'Change Resume' : 'Upload Resume' }}</h3>
                        <form @submit.prevent="resumeData.resume_id ? changeResume() : saveResume()">
                            <div class="mb-4">
                                <input type="file" @change="handleResumeUpload" accept="application/pdf"
                                    class="block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                            </div>
                            <div v-if="resumePreview" class="mb-4">
                                <iframe :src="resumePreview" style="width:100%;height:300px;"
                                    class="border rounded"></iframe>
                            </div>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="closeResumeModal"
                                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                                <button type="submit"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">{{
                                    resumeData.resume_id ? 'Change Resume' : 'Save Resume' }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </transition>

            <!-- Delete Resume Confirmation Modal -->
            <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
                enter-to-class="modal-enter-to" leave-active-class="modal-leave-active"
                leave-from-class="modal-leave-from" leave-to-class="modal-leave-to">
                <div v-if="showDeleteResumeModal"
                    class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Confirm Delete</h3>
                        <p class="mb-6 text-gray-700 dark:text-gray-300">Are you sure you want to delete your resume?
                        </p>
                        <div class="flex justify-end gap-3">
                            <button @click="closeDeleteResumeModal"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                            <button @click="deleteResume"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Verification Document Section -->
            <section v-if="profile.verification_document"
                class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 uppercase truncate mr-3">Verification
                        Document</h2>
                    <button @click="openDeleteDocumentModal"
                        class="bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 transition-colors whitespace-nowrap text-sm flex-shrink-0 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors w-auto">
                        <i class="fas fa-trash mr-1"></i>Delete
                    </button>
                </div>
                <div class="flex items-center gap-4">
                    <i :class="fileIconClass(profile.verification_document)" class="text-3xl"></i>
                    <div class="text-gray-800 dark:text-gray-200 min-w-0 flex-1">
                        <a :href="'uploads/documents/' + profile.verification_document" target="_blank"
                            class="underline hover:text-blue-700 dark:hover:text-blue-300 truncate block"
                            :title="profile.verification_document">
                            {{ profile.verification_document }}
                        </a>
                    </div>
                </div>
            </section>

            <!-- Danger Zone -->
            <section class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 mb-6 border border-red-200 dark:border-red-900">
                <h2 class="text-xl font-bold text-red-600 dark:text-red-400 uppercase mb-2">Delete Account</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Permanently deletes your account and all associated data (profile, education, skills, resume, applications, messages). This cannot be undone.</p>
                <button v-if="!showDeleteAccountConfirm" type="button" @click="showDeleteAccountConfirm = true"
                    class="text-red-600 border border-red-300 hover:bg-red-50 dark:hover:bg-red-900/20 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Delete My Account
                </button>
                <form v-else @submit.prevent="showFinalDeleteAccountConfirm = true" class="space-y-3 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-900 rounded-lg p-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Enter your password to confirm</label>
                    <input type="password" v-model="deleteAccountPassword" required
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-gray-600 dark:text-gray-200">
                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="showDeleteAccountConfirm = false; deleteAccountPassword = ''"
                            class="px-4 py-2 rounded-md text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Cancel</button>
                        <button type="submit" :disabled="deletingAccount"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors disabled:opacity-50">
                            {{ deletingAccount ? 'Deleting...' : 'Permanently Delete' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- Final "are you sure" step — the password form above only confirms identity, this confirms intent -->
            <div v-if="showFinalDeleteAccountConfirm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-700 rounded-lg shadow-xl max-w-sm w-full p-6">
                    <h3 class="text-lg font-bold text-red-600 dark:text-red-400 mb-2">Are you sure?</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">This will permanently delete your account and all associated data. This action cannot be undone.</p>
                    <div class="flex gap-3 justify-end">
                        <button type="button" @click="showFinalDeleteAccountConfirm = false"
                            class="px-4 py-2 rounded-md text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600">Cancel</button>
                        <button type="button" :disabled="deletingAccount" @click="deleteAccount"
                            class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors disabled:opacity-50">
                            {{ deletingAccount ? 'Deleting...' : 'Yes, Delete My Account' }}
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <!-- Floating Resume Generator Button -->
        <button @click="openResumeGenerator"
            class="fixed bottom-24 right-6 z-[150] bg-blue-600 hover:bg-blue-700 text-white rounded-full w-14 h-14 shadow-lg flex items-center justify-center transition-colors"
            title="Generate Resume">
            <i class="fas fa-file-invoice text-xl"></i>
        </button>

        <!-- Delete Verification Document Confirmation Modal -->
        <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to">
            <div v-if="showDeleteDocumentModal"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                    <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        @click="closeDeleteDocumentModal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Confirm Delete</h3>
                    <p class="mb-6 text-gray-700 dark:text-gray-300">Are you sure you want to delete your verification
                        document?</p>
                    <div class="flex justify-end gap-3">
                        <button @click="closeDeleteDocumentModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                        <button @click="deleteVerificationDocument"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Profile Edit Modal -->
        <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to">
            <div v-if="showEditModal"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl mx-2 p-6 relative max-h-[90vh] overflow-y-auto">
                    <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        @click="closeEditModal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Edit Profile</h3>
                    <form @submit.prevent="saveProfile">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First
                                    Name*</label>
                                <input type="text" v-model="editProfileData.first_name" required
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Middle
                                    Name</label>
                                <input type="text" v-model="editProfileData.middle_name"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last
                                    Name*</label>
                                <input type="text" v-model="editProfileData.last_name" required
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email*</label>
                                <input type="email" v-model="editProfileData.email" required
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                                <input type="tel" v-model="editProfileData.contact"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City</label>
                                <select v-model="editProfileData.city" :disabled="!cities.length"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select City/Municipality</option>
                                    <option v-for="city in cities" :key="city.code" :value="city.name">{{ city.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Province</label>
                                <select v-model="editProfileData.province" @change="fetchCities"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Province</option>
                                    <option v-for="province in provinces" :key="province.code" :value="province.name">{{
                                        province.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Birthdate</label>
                                <input type="date" v-model="editProfileData.birthdate"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Gender</label>
                                <select v-model="editProfileData.gender"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Civil
                                    Status</label>
                                <select v-model="editProfileData.civil_status"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Civil Status</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Divorced">Divorced</option>
                                    <option value="Widowed">Widowed</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Campus</label>
                                <select v-model="editProfileData.campus_id" @change="updateCollegeOptions"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Campus</option>
                                    <option v-for="c in campuses" :key="c.campus_id" :value="c.campus_id">{{ c.name }} ({{ c.type }})</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">College</label>
                                <select v-model="editProfileData.college" @change="updateCourseOptions" :disabled="!editProfileData.campus_id"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select College</option>
                                    <option v-for="college in colleges" :key="college" :value="college">{{ college }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Program</label>
                                <select v-model="editProfileData.course" :disabled="!editProfileData.college"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Program</option>
                                    <option v-for="course in collegeCourses[editProfileData.college] || []"
                                        :key="course" :value="course">{{ course }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Year
                                    Graduated</label>
                                <input type="number" v-model="editProfileData.year_graduated" min="1950" max="2030"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" @click="closeEditModal"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to">
            <div v-if="showDeleteEducationModal"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Confirm Delete</h3>
                    <p class="mb-6 text-gray-700 dark:text-gray-300">Are you sure you want to delete this education
                        record?</p>
                    <div class="flex justify-end gap-3">
                        <button @click="cancelDeleteEducation"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                        <button @click="confirmDeleteEducation"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Skills Modal -->
        <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to">
            <div v-if="showSkillsModal"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative max-h-[90vh] overflow-y-auto">
                    <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        @click="closeSkillsModal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Add Skill</h3>
                    <form @submit.prevent="addSkill">
                        <div class="mb-3 relative">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Skill
                                Name*</label>
                            <input type="text" v-model="newSkill.name" @input="onSkillInput"
                                @focus="searchSkills(newSkill.name)" @blur="hideSkillSuggestions" required
                                placeholder="Start typing to see suggestions"
                                class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            <!-- Skill Suggestions Dropdown -->
                            <div v-if="showSkillSuggestions && skillSuggestions.length > 0"
                                class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                <ul class="py-1">
                                    <li v-for="(skill, index) in skillSuggestions" :key="index"
                                        @mousedown="selectSkillSuggestion(skill)"
                                        class="px-3 py-2 cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-800 text-gray-800 dark:text-gray-200">
                                        {{ skill.name }}
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button" @click="closeSkillsModal"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" :disabled="!newSkill.name"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors">
                                Add Skill
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>


        <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to">
            <div v-if="showDeleteSkillModal"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Confirm Delete</h3>
                    <p class="mb-6 text-gray-700 dark:text-gray-300">Are you sure you want to delete this skill?</p>
                    <div class="flex justify-end gap-3">
                        <button @click="cancelDeleteSkill"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                        <button @click="confirmDeleteSkill"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Education Modal -->
        <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to">
            <div v-if="showEducationModal"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-lg mx-2 p-6 relative max-h-[90vh] overflow-y-auto">
                    <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        @click="closeEducationModal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">{{ editingEducationIndex ===
                        null ? 'Add' : 'Edit' }} Education</h3>
                    <form @submit.prevent="saveEducation">
                        <div class="space-y-4">
                            <div class="relative autocomplete">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Degree*</label>
                                <input type="text" id="degreeInput" v-model="degreeInput"
                                    placeholder="Enter degree (ex. Bachelor of Science in Information Technology"
                                    required
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white bg-white dark:bg-gray-700">
                                <div id="degreeSuggestions"
                                    class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                </div>
                            </div>
                            <div class="relative autocomplete">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">School/University*</label>
                                <input type="text" id="universityInput" v-model="schoolInput"
                                    placeholder="Enter school/university" required
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white bg-white dark:bg-gray-700">
                                <div id="suggestions"
                                    class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start
                                        Date*</label>
                                    <input type="date" v-model="editEducationData.start_date" required
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white bg-white dark:bg-gray-700">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End
                                        Date</label>
                                    <input type="date" v-model="editEducationData.end_date"
                                        :disabled="editEducationData.current"
                                        class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white bg-white dark:bg-gray-700">
                                    <div class="flex items-center mt-2">
                                        <input type="checkbox" v-model="editEducationData.current" id="eduCurrent"
                                            class="mr-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500">
                                        <label for="eduCurrent" class="text-sm text-gray-700 dark:text-gray-300">I
                                            currently study here</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" @click="closeEducationModal"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <!-- Experience Modal with Job Title Auto-suggest -->
        <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to">
            <div v-if="showExperienceModal"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl mx-2 p-6 relative max-h-[90vh] overflow-y-auto">
                    <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        @click="closeExperienceModal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">{{ editingExperienceIndex
                        === null ? 'Add' : 'Edit' }} Work Experience</h3>
                    <form @submit.prevent="saveExperience">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Job Title with Auto-suggest -->
                            <div class="md:col-span-2 relative">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Job
                                    Title*</label>
                                <input type="text" v-model="editExperienceData.title" @input="onTitleInput"
                                    @focus="searchJobTitles(editExperienceData.title)" @blur="hideTitleSuggestions"
                                    required
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                    placeholder="Start typing to see job title suggestions">
                                <!-- Job Title Suggestions Dropdown -->
                                <div v-if="showTitleSuggestions && titleSuggestions.length > 0"
                                    class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-y-auto">
                                    <ul class="py-1">
                                        <li v-for="(title, index) in titleSuggestions" :key="index"
                                            @click="selectTitleSuggestion(title)"
                                            class="px-3 py-2 cursor-pointer hover:bg-blue-100 dark:hover:bg-blue-800 text-gray-800 dark:text-gray-200">
                                            {{ title.name }}
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company*</label>
                                <input type="text" v-model="editExperienceData.company" required
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Location
                                    of Work*</label>
                                <select v-model="editExperienceData.location_of_work" required
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Location</option>
                                    <option value="Local">Local</option>
                                    <option value="Abroad">Abroad</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Employment
                                    Status*</label>
                                <select v-model="editExperienceData.employment_status" required
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Status</option>
                                    <option value="Probational">Probational</option>
                                    <option value="Contractual">Contractual</option>
                                    <option value="Regular">Regular</option>
                                    <option value="Self-employed">Self-employed</option>
                                    <option value="Unemployed">Unemployed</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start
                                    Date*</label>
                                <input type="date" v-model="editExperienceData.start_date" required
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End
                                    Date</label>
                                <input type="date" v-model="editExperienceData.end_date"
                                    :disabled="editExperienceData.current"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                <div class="flex items-center mt-2">
                                    <input type="checkbox" v-model="editExperienceData.current" id="expCurrent"
                                        class="mr-2 border border-gray-300 dark:border-gray-600 rounded focus:ring-2 focus:ring-blue-500">
                                    <label for="expCurrent" class="text-sm text-gray-700 dark:text-gray-300">I currently
                                        work here</label>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description*</label>
                                <textarea v-model="editExperienceData.description" rows="4" required
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 mt-4">Employment
                                    Sector*</label>
                                <select v-model="editExperienceData.employment_sector" required
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    <option value="">Select Sector</option>
                                    <option value="Government">Government</option>
                                    <option value="Private">Private</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" @click="closeExperienceModal"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <!-- Delete Work Experience Confirmation Modal -->
        <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to">
            <div v-if="showDeleteExperienceModal"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Confirm Delete</h3>
                    <p class="mb-6 text-gray-700 dark:text-gray-300">Are you sure you want to delete this work
                        experience?</p>
                    <div class="flex justify-end gap-3">
                        <button @click="cancelDeleteExperience"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                        <button @click="confirmDeleteExperience"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>
        </transition>


        <!-- Change Photo Modal -->
        <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to">
            <div v-if="showPhotoModal"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                    <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        @click="closePhotoModal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Update Profile Photo</h3>
                    <div class="space-y-4">
                        <div class="flex justify-center">
                            <div class="relative">
                                <img v-if="newPhotoPreview" :src="newPhotoPreview" alt="New Photo Preview"
                                    class="w-32 h-32 rounded-full object-cover border-4 border-blue-500">
                                <img v-else-if="profilePicData.file_name"
                                    :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Current Photo"
                                    class="w-32 h-32 rounded-full object-cover border-4 border-gray-200 dark:border-gray-600">
                                <div v-else
                                    class="w-32 h-32 rounded-full border-4 border-gray-200 dark:border-gray-600 bg-gray-200 dark:bg-gray-600 flex items-center justify-center text-4xl text-gray-400 dark:text-gray-300">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Choose New
                                Photo</label>
                            <div
                                class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-blue-500 transition-colors">
                                <input type="file" ref="photoInput" @change="handlePhotoUpload" accept="image/*"
                                    class="hidden">
                                <div class="cursor-pointer" @click="$refs.photoInput.click()">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                    <p class="text-gray-600 dark:text-gray-300">Click to upload or drag and drop</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">PNG, JPG, GIF up to 5MB</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button v-if="profilePicData.file_name" @click="openDeleteProfilePicModal"
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 whitespace-nowrap">Delete
                            Photo</button>
                        <!-- Save button only enabled if a file is selected; reads
                             "Update Photo" once there's already a photo to replace. -->
                        <button @click="saveProfilePic" :disabled="!newPhotoFile"
                            class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
                            <span v-if="profilePicData.file_name">Update Photo</span>
                            <span v-else>Save Photo</span>
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Change Verification Document Modal -->
        <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to">
            <div v-if="showDocumentModal"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                    <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                        @click="closeDocumentModal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Change Verification Document
                    </h3>
                    <form @submit.prevent="updateVerificationDocument">
                        <div class="mb-4">
                            <input type="file" @change="handleDocumentUpload" accept=".pdf,.jpg,.jpeg,.png,.gif"
                                class="block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                        </div>
                        <div v-if="documentPreview" class="mb-4">
                            <iframe v-if="documentPreviewType === 'pdf'" :src="documentPreview"
                                style="width:100%;height:300px;" class="border rounded"></iframe>
                            <img v-else :src="documentPreview" alt="Document Preview"
                                class="max-h-48 rounded border mx-auto" />
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="closeDocumentModal"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Save
                                Document</button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to">
            <div v-if="showDeleteProfilePicModal"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Confirm Delete</h3>
                    <p class="mb-6 text-gray-700 dark:text-gray-300">Are you sure you want to delete your profile photo?
                    </p>
                    <div class="flex justify-end gap-3">
                        <button @click="closeDeleteProfilePicModal"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                        <button @click="deleteProfilePic"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Resume Generator Modal -->
        <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to">
            <div v-if="showResumeGeneratorModal"
                class="fixed inset-0 z-[300] flex items-center justify-center bg-black bg-opacity-60 p-2 sm:p-4" role="dialog" aria-modal="true">
                <div
                    class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-6xl h-[92vh] flex flex-col overflow-hidden">
                    <!-- Header -->
                    <div
                        class="flex items-center justify-between px-4 sm:px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">
                            <i class="fas fa-file-invoice mr-2 text-blue-600"></i>Generate Resume
                        </h3>
                        <button @click="closeResumeGenerator" aria-label="Close"
                            class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Professional Summary -->
                    <div class="px-4 sm:px-6 py-3 border-b border-gray-200 dark:border-gray-700 flex-shrink-0 bg-gray-50 dark:bg-gray-900/40">
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Professional
                                Summary</label>
                            <button @click="generateSummaryWithAI" :disabled="generatingSummary"
                                class="text-xs bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md disabled:opacity-60 flex items-center gap-1.5">
                                <i :class="generatingSummary ? 'fas fa-spinner fa-spin' : 'fas fa-magic'"></i>
                                {{ generatingSummary ? 'Generating...' : (resumeSummary ? 'Regenerate with AI' : 'Generate with AI') }}
                            </button>
                        </div>
                        <textarea v-model="resumeSummary" rows="2" placeholder="Click &quot;Generate with AI&quot; or write your own professional summary here..."
                            class="w-full text-sm border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 dark:bg-gray-700 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                    </div>

                    <!-- Body -->
                    <div class="flex flex-1 min-h-0 flex-col md:flex-row">
                        <!-- Template picker -->
                        <div
                            class="md:w-72 flex-shrink-0 border-b md:border-b-0 md:border-r border-gray-200 dark:border-gray-700 overflow-y-auto p-3 space-y-2 max-h-40 md:max-h-none">
                            <button v-for="tpl in resumeTemplates" :key="tpl.id" @click="selectResumeTemplate(tpl.id)"
                                :class="['w-full text-left p-3 rounded-lg border transition-colors flex items-center gap-3',
                                    selectedResumeTemplate === tpl.id
                                        ? 'border-blue-600 bg-blue-50 dark:bg-blue-900/30'
                                        : 'border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700']">
                                <span class="w-8 h-8 rounded-md flex-shrink-0" :style="{ backgroundColor: tpl.color }"></span>
                                <span class="min-w-0">
                                    <span
                                        class="block text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{
                                        tpl.name }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400 truncate">{{ tpl.desc
                                        }}</span>
                                </span>
                            </button>
                        </div>

                        <!-- Preview. items-start (not the flex default of stretch) so the resume
                             card grows to fit its own content instead of being squashed down to
                             this pane's visible height — with the default stretch behavior, a
                             resume taller than the pane rendered with a white background that
                             stopped partway down, and the rest of the content appeared to "spill"
                             onto the dark pane background below it. -->
                        <div class="flex-1 min-h-0 overflow-auto bg-gray-100 dark:bg-gray-900 p-3 sm:p-6 flex items-start justify-center">

                            <!-- ATS Classic preview (plain text, generated separately via jsPDF text API) -->
                            <div v-if="selectedResumeTemplate === 'ats'"
                                class="bg-white shadow-lg p-10 w-[794px] max-w-full font-serif text-[13px] text-black leading-relaxed">
                                <p class="text-2xl font-bold text-center">{{ resumeFullName || 'Your Name' }}</p>
                                <p class="mb-4 text-center">{{ [profile.email, profile.contact, resumeLocation].filter(Boolean).join(' | ') }}</p>

                                <template v-if="resumeSummary">
                                    <p class="font-bold border-b border-black mb-2 mt-4">Professional Summary</p>
                                    <p class="text-sm text-justify">{{ resumeSummary }}</p>
                                </template>

                                <template v-if="resumeSkills.length">
                                    <p class="font-bold border-b border-black mb-2 mt-4">Skills</p>
                                    <p class="text-sm">{{ resumeSkills.map(s => s.name).join(', ') }}</p>
                                </template>

                                <template v-if="resumeCertifications.length">
                                    <p class="font-bold border-b border-black mb-2 mt-4">Certifications</p>
                                    <p class="text-sm">{{ resumeCertifications.map(c => c.name + (c.issuer ? ' (' + c.issuer + ')' : '')).join(', ') }}</p>
                                </template>

                                <template v-if="resumeEducation.length">
                                    <p class="font-bold border-b border-black mb-2 mt-4">Education</p>
                                    <div v-for="e in resumeEducation" :key="'ats-edu-'+e.education_id" class="mb-2 flex justify-between">
                                        <span>
                                            <span class="font-semibold">{{ e.school }}</span><span v-if="e.degree"> — {{ e.degree }}</span>
                                        </span>
                                        <span class="text-xs">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</span>
                                    </div>
                                </template>

                                <template v-if="resumeExperience.length">
                                    <p class="font-bold border-b border-black mb-2 mt-4">Professional Experience</p>
                                    <div v-for="e in resumeExperience" :key="'ats-exp-'+e.experience_id" class="mb-3">
                                        <div class="flex justify-between">
                                            <span class="font-semibold">{{ e.company }}</span>
                                            <span class="text-xs">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</span>
                                        </div>
                                        <p v-if="e.title" class="text-sm italic">{{ e.title }}</p>
                                        <ul v-if="e.description" class="list-disc pl-5 text-sm mt-1">
                                            <li v-for="(line, idx) in e.description.split('\n').map(l => l.trim()).filter(Boolean)" :key="'ats-exp-'+e.experience_id+'-'+idx">{{ line }}</li>
                                        </ul>
                                    </div>
                                </template>
                            </div>

                            <!-- Visual templates (captured via html2canvas for download). Fixed at
                                 794px (A4 width @ 96dpi) with no max-w-full: the preview pane scrolls
                                 horizontally on narrow screens instead, so this element always renders
                                 — and gets captured — at the exact same width the design was built for.
                                 Letting it shrink to fit whatever viewport happened to be open at
                                 download time caused text to wrap at different points than the design
                                 intended and made spacing look inconsistent/cramped in the PDF. -->
                            <div v-else ref="resumeCanvasTarget" class="bg-white w-[794px] shadow-lg flex-shrink-0">

                                <!-- Modern Blue -->
                                <div v-if="selectedResumeTemplate === 'modern'" class="p-10 text-gray-800">
                                    <div class="flex items-center border-b-4 border-blue-600 pb-4 mb-6">
                                        <img v-if="profilePicData.file_name" :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile Photo"
                                            class="w-20 h-20 rounded-full object-cover border-2 border-blue-600 flex-shrink-0" style="margin-right:20px;">
                                        <div>
                                            <h1 class="text-3xl font-bold text-blue-700">{{ resumeFullName || 'Your Name' }}</h1>
                                            <p class="text-sm text-gray-600 mt-1">{{ [profile.email, profile.contact, resumeLocation].filter(Boolean).join('  •  ') }}</p>
                                        </div>
                                    </div>
                                    <div v-if="resumeSummary" class="mb-6">
                                        <h2 class="text-sm font-bold text-blue-700 uppercase tracking-wide border-b border-blue-200 pb-1 mb-3">Professional Summary</h2>
                                        <p class="text-sm text-gray-700">{{ resumeSummary }}</p>
                                    </div>
                                    <div v-if="resumeExperience.length" class="mb-6">
                                        <h2 class="text-sm font-bold text-blue-700 uppercase tracking-wide border-b border-blue-200 pb-1 mb-3">Work Experience</h2>
                                        <div v-for="e in resumeExperience" :key="'m-exp-'+e.experience_id" class="mb-4">
                                            <p class="font-semibold">{{ e.title }}<span v-if="e.title"> — </span>{{ e.company }}</p>
                                            <p class="text-xs text-gray-500">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                            <p v-if="e.description" class="text-sm mt-1 text-gray-700">{{ e.description }}</p>
                                        </div>
                                    </div>
                                    <div v-if="resumeEducation.length" class="mb-6">
                                        <h2 class="text-sm font-bold text-blue-700 uppercase tracking-wide border-b border-blue-200 pb-1 mb-3">Education</h2>
                                        <div v-for="e in resumeEducation" :key="'m-edu-'+e.education_id" class="mb-3">
                                            <p class="font-semibold">{{ e.school }}</p>
                                            <p class="text-sm text-gray-600">{{ e.degree }}</p>
                                            <p class="text-xs text-gray-500">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                        </div>
                                    </div>
                                    <div v-if="resumeSkills.length" class="mb-6">
                                        <h2 class="text-sm font-bold text-blue-700 uppercase tracking-wide border-b border-blue-200 pb-1 mb-3">Skills</h2>
                                        <div class="flex flex-wrap">
                                            <span v-for="s in resumeSkills" :key="'m-skl-'+s.skill_id" class="bg-blue-50 text-blue-700 text-xs px-3 py-1 rounded-full mr-2 mb-2">{{ s.name }}</span>
                                        </div>
                                    </div>
                                    <div v-if="resumeCertifications.length">
                                        <h2 class="text-sm font-bold text-blue-700 uppercase tracking-wide border-b border-blue-200 pb-1 mb-3">Certifications</h2>
                                        <div v-for="c in resumeCertifications" :key="'m-cert-'+c.certification_id" class="mb-2">
                                            <p class="font-semibold">{{ c.name }}</p>
                                            <p class="text-xs text-gray-500">{{ c.issuer }}<span v-if="c.issuer && c.issue_date"> &middot; </span>{{ c.issue_date }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Minimalist Mono -->
                                <div v-else-if="selectedResumeTemplate === 'minimalist'" class="p-10 text-gray-900">
                                    <h1 class="text-2xl font-light tracking-wide">{{ resumeFullName || 'Your Name' }}</h1>
                                    <p class="text-xs text-gray-500 mt-2 mb-8">{{ [profile.email, profile.contact, resumeLocation].filter(Boolean).join('   ') }}</p>
                                    <div v-if="resumeSummary" class="mb-8">
                                        <p class="text-xs font-semibold tracking-[0.2em] mb-3">SUMMARY</p>
                                        <hr class="border-gray-300 mb-4">
                                        <p class="text-sm text-gray-700">{{ resumeSummary }}</p>
                                    </div>
                                    <div v-if="resumeExperience.length" class="mb-8">
                                        <p class="text-xs font-semibold tracking-[0.2em] mb-3">EXPERIENCE</p>
                                        <hr class="border-gray-300 mb-4">
                                        <div v-for="e in resumeExperience" :key="'mn-exp-'+e.experience_id" class="mb-4">
                                            <p class="font-medium">{{ e.title }}<span v-if="e.title"> — </span>{{ e.company }}</p>
                                            <p class="text-xs text-gray-400">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                            <p v-if="e.description" class="text-sm mt-1 text-gray-600">{{ e.description }}</p>
                                        </div>
                                    </div>
                                    <div v-if="resumeEducation.length" class="mb-8">
                                        <p class="text-xs font-semibold tracking-[0.2em] mb-3">EDUCATION</p>
                                        <hr class="border-gray-300 mb-4">
                                        <div v-for="e in resumeEducation" :key="'mn-edu-'+e.education_id" class="mb-3">
                                            <p class="font-medium">{{ e.school }}</p>
                                            <p class="text-sm text-gray-600">{{ e.degree }}</p>
                                        </div>
                                    </div>
                                    <div v-if="resumeSkills.length" class="mb-8">
                                        <p class="text-xs font-semibold tracking-[0.2em] mb-3">SKILLS</p>
                                        <hr class="border-gray-300 mb-4">
                                        <p class="text-sm text-gray-700">{{ resumeSkills.map(s => s.name).join('   /   ') }}</p>
                                    </div>
                                    <div v-if="resumeCertifications.length">
                                        <p class="text-xs font-semibold tracking-[0.2em] mb-3">CERTIFICATIONS</p>
                                        <hr class="border-gray-300 mb-4">
                                        <p class="text-sm text-gray-700">{{ resumeCertifications.map(c => c.name).join('   /   ') }}</p>
                                    </div>
                                </div>

                                <!-- Professional Navy -->
                                <div v-else-if="selectedResumeTemplate === 'navy'" class="p-10 font-serif text-gray-800">
                                    <div class="text-center border-b-2 border-yellow-600 pb-4 mb-6">
                                        <img v-if="profilePicData.file_name" :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile Photo"
                                            class="w-20 h-20 rounded-full object-cover border-2 border-blue-900 mx-auto mb-3">
                                        <h1 class="text-3xl font-bold text-blue-900">{{ resumeFullName || 'Your Name' }}</h1>
                                        <p class="text-sm text-gray-600 mt-1">{{ [profile.email, profile.contact, resumeLocation].filter(Boolean).join('  |  ') }}</p>
                                    </div>
                                    <div v-if="resumeSummary" class="mb-6">
                                        <h2 class="text-base font-bold text-blue-900 mb-3">Summary</h2>
                                        <p class="text-sm">{{ resumeSummary }}</p>
                                    </div>
                                    <div v-if="resumeEducation.length" class="mb-6">
                                        <h2 class="text-base font-bold text-blue-900 mb-3">Education</h2>
                                        <div v-for="e in resumeEducation" :key="'nv-edu-'+e.education_id" class="mb-3">
                                            <p class="font-semibold">{{ e.school }} <span class="font-normal text-gray-600">— {{ e.degree }}</span></p>
                                            <p class="text-xs text-gray-500">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                        </div>
                                    </div>
                                    <div v-if="resumeExperience.length" class="mb-6">
                                        <h2 class="text-base font-bold text-blue-900 mb-3">Experience</h2>
                                        <div v-for="e in resumeExperience" :key="'nv-exp-'+e.experience_id" class="mb-4">
                                            <p class="font-semibold">{{ e.title }}<span v-if="e.title"> — </span>{{ e.company }}</p>
                                            <p class="text-xs text-gray-500">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                            <p v-if="e.description" class="text-sm mt-1">{{ e.description }}</p>
                                        </div>
                                    </div>
                                    <div v-if="resumeSkills.length" class="mb-6">
                                        <h2 class="text-base font-bold text-blue-900 mb-3">Skills</h2>
                                        <p class="text-sm">{{ resumeSkills.map(s => s.name).join(', ') }}</p>
                                    </div>
                                    <div v-if="resumeCertifications.length">
                                        <h2 class="text-base font-bold text-blue-900 mb-3">Certifications</h2>
                                        <p class="text-sm">{{ resumeCertifications.map(c => c.name).join(', ') }}</p>
                                    </div>
                                </div>

                                <!-- Executive Gray -->
                                <div v-else-if="selectedResumeTemplate === 'executive'" class="text-gray-800">
                                    <div class="bg-gray-700 text-white px-10 py-8 flex items-center">
                                        <img v-if="profilePicData.file_name" :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile Photo"
                                            class="w-20 h-20 rounded-full object-cover border-2 border-white flex-shrink-0" style="margin-right:20px;">
                                        <div>
                                            <h1 class="text-3xl font-bold">{{ resumeFullName || 'Your Name' }}</h1>
                                            <p class="text-sm text-gray-300 mt-1">{{ [profile.email, profile.contact, resumeLocation].filter(Boolean).join('  •  ') }}</p>
                                        </div>
                                    </div>
                                    <div class="p-10">
                                        <div v-if="resumeSummary" class="mb-6">
                                            <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Summary</h2>
                                            <p class="text-sm text-gray-700">{{ resumeSummary }}</p>
                                        </div>
                                        <div v-if="resumeExperience.length" class="mb-6">
                                            <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Experience</h2>
                                            <div v-for="e in resumeExperience" :key="'ex-exp-'+e.experience_id" class="mb-4">
                                                <p class="font-semibold">{{ e.title }}<span v-if="e.title"> — </span>{{ e.company }}</p>
                                                <p class="text-xs text-gray-500">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                                <p v-if="e.description" class="text-sm mt-1 text-gray-700">{{ e.description }}</p>
                                            </div>
                                        </div>
                                        <div v-if="resumeEducation.length" class="mb-6">
                                            <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Education</h2>
                                            <div v-for="e in resumeEducation" :key="'ex-edu-'+e.education_id" class="mb-3">
                                                <p class="font-semibold">{{ e.school }}</p>
                                                <p class="text-sm text-gray-600">{{ e.degree }}</p>
                                            </div>
                                        </div>
                                        <div v-if="resumeSkills.length" class="mb-6">
                                            <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Skills</h2>
                                            <div class="flex flex-wrap">
                                                <span v-for="s in resumeSkills" :key="'ex-skl-'+s.skill_id" class="bg-gray-100 text-gray-700 text-xs px-3 py-1 rounded mr-2 mb-2">{{ s.name }}</span>
                                            </div>
                                        </div>
                                        <div v-if="resumeCertifications.length">
                                            <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Certifications</h2>
                                            <div v-for="c in resumeCertifications" :key="'ex-cert-'+c.certification_id" class="mb-2">
                                                <p class="font-semibold">{{ c.name }}</p>
                                                <p class="text-xs text-gray-500">{{ c.issuer }}<span v-if="c.issuer && c.issue_date"> &middot; </span>{{ c.issue_date }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sidebar Split -->
                                <div v-else-if="selectedResumeTemplate === 'sidebar'" class="flex text-gray-800">
                                    <div class="w-1/3 bg-slate-900 text-white p-6">
                                        <img v-if="profilePicData.file_name" :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile Photo"
                                            class="w-20 h-20 rounded-full object-cover border-2 border-slate-600 mb-4">
                                        <div v-else class="w-20 h-20 rounded-full bg-slate-700 flex items-center justify-center text-2xl font-bold mb-4">
                                            {{ resumeInitials || '?' }}
                                        </div>
                                        <h1 class="text-xl font-bold leading-tight mb-4">{{ resumeFullName || 'Your Name' }}</h1>
                                        <p class="text-xs text-slate-300 mb-1" v-if="profile.email"><i class="fas fa-envelope mr-2"></i>{{ profile.email }}</p>
                                        <p class="text-xs text-slate-300 mb-1" v-if="profile.contact"><i class="fas fa-phone mr-2"></i>{{ profile.contact }}</p>
                                        <p class="text-xs text-slate-300 mb-4" v-if="resumeLocation"><i class="fas fa-map-marker-alt mr-2"></i>{{ resumeLocation }}</p>
                                        <div v-if="resumeSkills.length" class="mb-4">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-2">Skills</p>
                                            <p v-for="s in resumeSkills" :key="'sb-skl-'+s.skill_id" class="text-xs text-slate-200 mb-1">{{ s.name }}</p>
                                        </div>
                                        <div v-if="resumeCertifications.length">
                                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-2">Certifications</p>
                                            <p v-for="c in resumeCertifications" :key="'sb-cert-'+c.certification_id" class="text-xs text-slate-200 mb-1">{{ c.name }}</p>
                                        </div>
                                    </div>
                                    <div class="w-2/3 p-6">
                                        <div v-if="resumeSummary" class="mb-6">
                                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide border-b border-slate-300 pb-1 mb-3">Summary</h2>
                                            <p class="text-sm text-gray-700">{{ resumeSummary }}</p>
                                        </div>
                                        <div v-if="resumeExperience.length" class="mb-6">
                                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide border-b border-slate-300 pb-1 mb-3">Experience</h2>
                                            <div v-for="e in resumeExperience" :key="'sb-exp-'+e.experience_id" class="mb-4">
                                                <p class="font-semibold">{{ e.title }}<span v-if="e.title"> — </span>{{ e.company }}</p>
                                                <p class="text-xs text-gray-500">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                                <p v-if="e.description" class="text-sm mt-1 text-gray-700">{{ e.description }}</p>
                                            </div>
                                        </div>
                                        <div v-if="resumeEducation.length">
                                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide border-b border-slate-300 pb-1 mb-3">Education</h2>
                                            <div v-for="e in resumeEducation" :key="'sb-edu-'+e.education_id" class="mb-3">
                                                <p class="font-semibold">{{ e.school }}</p>
                                                <p class="text-sm text-gray-600">{{ e.degree }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Creative Gradient -->
                                <div v-else-if="selectedResumeTemplate === 'gradient'" class="text-gray-800">
                                    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-10 py-10 flex items-center">
                                        <img v-if="profilePicData.file_name" :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile Photo"
                                            class="w-20 h-20 rounded-full object-cover border-2 border-white flex-shrink-0" style="margin-right:20px;">
                                        <div>
                                            <h1 class="text-3xl font-bold">{{ resumeFullName || 'Your Name' }}</h1>
                                            <p class="text-sm text-blue-100 mt-1">{{ [profile.email, profile.contact, resumeLocation].filter(Boolean).join('  •  ') }}</p>
                                        </div>
                                    </div>
                                    <div class="p-10">
                                        <div v-if="resumeSummary" class="mb-6">
                                            <h2 class="text-sm font-bold text-purple-700 uppercase tracking-wide mb-3">Summary</h2>
                                            <p class="text-sm text-gray-700">{{ resumeSummary }}</p>
                                        </div>
                                        <div v-if="resumeExperience.length" class="mb-6">
                                            <h2 class="text-sm font-bold text-purple-700 uppercase tracking-wide mb-3">Experience</h2>
                                            <div v-for="e in resumeExperience" :key="'gr-exp-'+e.experience_id" class="mb-4">
                                                <p class="font-semibold">{{ e.title }}<span v-if="e.title"> — </span>{{ e.company }}</p>
                                                <p class="text-xs text-gray-500">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                                <p v-if="e.description" class="text-sm mt-1 text-gray-700">{{ e.description }}</p>
                                            </div>
                                        </div>
                                        <div v-if="resumeEducation.length" class="mb-6">
                                            <h2 class="text-sm font-bold text-purple-700 uppercase tracking-wide mb-3">Education</h2>
                                            <div v-for="e in resumeEducation" :key="'gr-edu-'+e.education_id" class="mb-3">
                                                <p class="font-semibold">{{ e.school }}</p>
                                                <p class="text-sm text-gray-600">{{ e.degree }}</p>
                                            </div>
                                        </div>
                                        <div v-if="resumeSkills.length" class="mb-6">
                                            <h2 class="text-sm font-bold text-purple-700 uppercase tracking-wide mb-3">Skills</h2>
                                            <div class="flex flex-wrap">
                                                <span v-for="s in resumeSkills" :key="'gr-skl-'+s.skill_id" class="bg-gradient-to-r from-blue-100 to-purple-100 text-purple-700 text-xs px-3 py-1 rounded-full mr-2 mb-2">{{ s.name }}</span>
                                            </div>
                                        </div>
                                        <div v-if="resumeCertifications.length">
                                            <h2 class="text-sm font-bold text-purple-700 uppercase tracking-wide mb-3">Certifications</h2>
                                            <div v-for="c in resumeCertifications" :key="'gr-cert-'+c.certification_id" class="mb-2">
                                                <p class="font-semibold">{{ c.name }}</p>
                                                <p class="text-xs text-gray-500">{{ c.issuer }}<span v-if="c.issuer && c.issue_date"> &middot; </span>{{ c.issue_date }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Timeline -->
                                <div v-else-if="selectedResumeTemplate === 'timeline'" class="p-10 text-gray-800">
                                    <div class="flex items-center mb-6">
                                        <img v-if="profilePicData.file_name" :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile Photo"
                                            class="w-20 h-20 rounded-full object-cover border-2 border-emerald-600 flex-shrink-0" style="margin-right:20px;">
                                        <div>
                                            <h1 class="text-3xl font-bold text-emerald-700">{{ resumeFullName || 'Your Name' }}</h1>
                                            <p class="text-sm text-gray-600 mt-1">{{ [profile.email, profile.contact, resumeLocation].filter(Boolean).join('  •  ') }}</p>
                                        </div>
                                    </div>
                                    <div v-if="resumeSummary" class="mb-6">
                                        <h2 class="text-sm font-bold text-emerald-700 uppercase tracking-wide mb-3">Summary</h2>
                                        <p class="text-sm text-gray-700">{{ resumeSummary }}</p>
                                    </div>
                                    <div v-if="resumeExperience.length" class="mb-6">
                                        <h2 class="text-sm font-bold text-emerald-700 uppercase tracking-wide mb-4">Experience</h2>
                                        <div class="border-l-2 border-emerald-300 pl-4">
                                            <div v-for="e in resumeExperience" :key="'tl-exp-'+e.experience_id" class="mb-5 relative">
                                                <span class="absolute -left-[21px] top-1 w-3 h-3 rounded-full bg-emerald-600"></span>
                                                <p class="font-semibold">{{ e.title }}<span v-if="e.title"> — </span>{{ e.company }}</p>
                                                <p class="text-xs text-gray-500">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                                <p v-if="e.description" class="text-sm mt-1 text-gray-700">{{ e.description }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="resumeEducation.length" class="mb-6">
                                        <h2 class="text-sm font-bold text-emerald-700 uppercase tracking-wide mb-4">Education</h2>
                                        <div class="border-l-2 border-emerald-300 pl-4">
                                            <div v-for="e in resumeEducation" :key="'tl-edu-'+e.education_id" class="mb-5 relative">
                                                <span class="absolute -left-[21px] top-1 w-3 h-3 rounded-full bg-emerald-600"></span>
                                                <p class="font-semibold">{{ e.school }}</p>
                                                <p class="text-sm text-gray-600">{{ e.degree }}</p>
                                                <p class="text-xs text-gray-500">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="resumeSkills.length" class="mb-6">
                                        <h2 class="text-sm font-bold text-emerald-700 uppercase tracking-wide mb-3">Skills</h2>
                                        <div class="flex flex-wrap">
                                            <span v-for="s in resumeSkills" :key="'tl-skl-'+s.skill_id" class="bg-emerald-50 text-emerald-700 text-xs px-3 py-1 rounded-full mr-2 mb-2">{{ s.name }}</span>
                                        </div>
                                    </div>
                                    <div v-if="resumeCertifications.length">
                                        <h2 class="text-sm font-bold text-emerald-700 uppercase tracking-wide mb-3">Certifications</h2>
                                        <div v-for="c in resumeCertifications" :key="'tl-cert-'+c.certification_id" class="mb-2">
                                            <p class="font-semibold">{{ c.name }}</p>
                                            <p class="text-xs text-gray-500">{{ c.issuer }}<span v-if="c.issuer && c.issue_date"> &middot; </span>{{ c.issue_date }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Compact Dense -->
                                <div v-else-if="selectedResumeTemplate === 'compact'" class="p-6 text-gray-800 text-sm">
                                    <h1 class="text-lg font-bold">{{ resumeFullName || 'Your Name' }}</h1>
                                    <p class="text-xs text-gray-500 mb-3">{{ [profile.email, profile.contact, resumeLocation].filter(Boolean).join(' | ') }}</p>
                                    <div v-if="resumeSummary" class="mb-3">
                                        <p class="text-xs font-bold uppercase border-b border-gray-300 mb-1">Summary</p>
                                        <p class="text-xs text-gray-700">{{ resumeSummary }}</p>
                                    </div>
                                    <div v-if="resumeExperience.length" class="mb-3">
                                        <p class="text-xs font-bold uppercase border-b border-gray-300 mb-1">Experience</p>
                                        <div v-for="e in resumeExperience" :key="'cp-exp-'+e.experience_id" class="mb-2">
                                            <p class="font-semibold text-xs">{{ e.title }}<span v-if="e.title"> — </span>{{ e.company }} <span class="font-normal text-gray-500">({{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }})</span></p>
                                            <p v-if="e.description" class="text-xs text-gray-600">{{ e.description }}</p>
                                        </div>
                                    </div>
                                    <div v-if="resumeEducation.length" class="mb-3">
                                        <p class="text-xs font-bold uppercase border-b border-gray-300 mb-1">Education</p>
                                        <div v-for="e in resumeEducation" :key="'cp-edu-'+e.education_id" class="mb-1">
                                            <p class="text-xs font-semibold">{{ e.school }} <span class="font-normal text-gray-500">— {{ e.degree }}</span></p>
                                        </div>
                                    </div>
                                    <div v-if="resumeSkills.length" class="mb-3">
                                        <p class="text-xs font-bold uppercase border-b border-gray-300 mb-1">Skills</p>
                                        <p class="text-xs">{{ resumeSkills.map(s => s.name).join(', ') }}</p>
                                    </div>
                                    <div v-if="resumeCertifications.length">
                                        <p class="text-xs font-bold uppercase border-b border-gray-300 mb-1">Certifications</p>
                                        <p class="text-xs">{{ resumeCertifications.map(c => c.name).join(', ') }}</p>
                                    </div>
                                </div>

                                <!-- Bold Header -->
                                <div v-else-if="selectedResumeTemplate === 'bold'" class="text-gray-800">
                                    <div class="bg-red-600 text-white px-10 py-10 text-center">
                                        <img v-if="profilePicData.file_name" :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile Photo"
                                            class="w-24 h-24 rounded-full object-cover border-4 border-white mx-auto mb-4">
                                        <h1 class="text-4xl font-black">{{ resumeFullName || 'Your Name' }}</h1>
                                        <p class="text-sm text-red-100 mt-2">{{ [profile.email, profile.contact, resumeLocation].filter(Boolean).join('  •  ') }}</p>
                                    </div>
                                    <div class="p-10">
                                        <div v-if="resumeSummary" class="mb-6">
                                            <h2 class="text-sm font-bold text-red-600 uppercase tracking-wide mb-3">Summary</h2>
                                            <p class="text-sm text-gray-700">{{ resumeSummary }}</p>
                                        </div>
                                        <div v-if="resumeExperience.length" class="mb-6">
                                            <h2 class="text-sm font-bold text-red-600 uppercase tracking-wide mb-3">Experience</h2>
                                            <div v-for="e in resumeExperience" :key="'bd-exp-'+e.experience_id" class="mb-4">
                                                <p class="font-semibold">{{ e.title }}<span v-if="e.title"> — </span>{{ e.company }}</p>
                                                <p class="text-xs text-gray-500">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                                <p v-if="e.description" class="text-sm mt-1 text-gray-700">{{ e.description }}</p>
                                            </div>
                                        </div>
                                        <div v-if="resumeEducation.length" class="mb-6">
                                            <h2 class="text-sm font-bold text-red-600 uppercase tracking-wide mb-3">Education</h2>
                                            <div v-for="e in resumeEducation" :key="'bd-edu-'+e.education_id" class="mb-3">
                                                <p class="font-semibold">{{ e.school }}</p>
                                                <p class="text-sm text-gray-600">{{ e.degree }}</p>
                                            </div>
                                        </div>
                                        <div v-if="resumeSkills.length" class="mb-6">
                                            <h2 class="text-sm font-bold text-red-600 uppercase tracking-wide mb-3">Skills</h2>
                                            <div class="flex flex-wrap">
                                                <span v-for="s in resumeSkills" :key="'bd-skl-'+s.skill_id" class="bg-red-50 text-red-700 text-xs px-3 py-1 rounded-full mr-2 mb-2">{{ s.name }}</span>
                                            </div>
                                        </div>
                                        <div v-if="resumeCertifications.length">
                                            <h2 class="text-sm font-bold text-red-600 uppercase tracking-wide mb-3">Certifications</h2>
                                            <div v-for="c in resumeCertifications" :key="'bd-cert-'+c.certification_id" class="mb-2">
                                                <p class="font-semibold">{{ c.name }}</p>
                                                <p class="text-xs text-gray-500">{{ c.issuer }}<span v-if="c.issuer && c.issue_date"> &middot; </span>{{ c.issue_date }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Academic Formal -->
                                <div v-else-if="selectedResumeTemplate === 'academic'" class="p-10 font-serif text-gray-800 text-center">
                                    <h1 class="text-3xl font-bold text-yellow-900">{{ resumeFullName || 'Your Name' }}</h1>
                                    <p class="text-sm text-gray-600 mt-1 mb-6">{{ [profile.email, profile.contact, resumeLocation].filter(Boolean).join('  |  ') }}</p>
                                    <hr class="border-yellow-900 mb-6">
                                    <div v-if="resumeSummary" class="mb-6 text-left">
                                        <h2 class="text-base font-bold text-yellow-900 mb-3 text-center">Summary</h2>
                                        <p class="text-sm text-justify">{{ resumeSummary }}</p>
                                    </div>
                                    <div v-if="resumeEducation.length" class="mb-6 text-left">
                                        <h2 class="text-base font-bold text-yellow-900 mb-3 text-center">Education</h2>
                                        <div v-for="e in resumeEducation" :key="'ac-edu-'+e.education_id" class="mb-3">
                                            <p class="font-semibold">{{ e.school }}</p>
                                            <p class="text-sm text-gray-600">{{ e.degree }}</p>
                                            <p class="text-xs text-gray-500">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                        </div>
                                    </div>
                                    <div v-if="resumeExperience.length" class="mb-6 text-left">
                                        <h2 class="text-base font-bold text-yellow-900 mb-3 text-center">Experience</h2>
                                        <div v-for="e in resumeExperience" :key="'ac-exp-'+e.experience_id" class="mb-4">
                                            <p class="font-semibold">{{ e.title }}<span v-if="e.title"> — </span>{{ e.company }}</p>
                                            <p class="text-xs text-gray-500">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                            <p v-if="e.description" class="text-sm mt-1">{{ e.description }}</p>
                                        </div>
                                    </div>
                                    <div v-if="resumeSkills.length" class="text-left mb-6">
                                        <h2 class="text-base font-bold text-yellow-900 mb-3 text-center">Skills</h2>
                                        <p class="text-sm text-center">{{ resumeSkills.map(s => s.name).join(', ') }}</p>
                                    </div>
                                    <div v-if="resumeCertifications.length" class="text-left">
                                        <h2 class="text-base font-bold text-yellow-900 mb-3 text-center">Certifications</h2>
                                        <p class="text-sm text-center">{{ resumeCertifications.map(c => c.name).join(', ') }}</p>
                                    </div>
                                </div>

                                <!-- Tech Developer -->
                                <div v-else-if="selectedResumeTemplate === 'tech'" class="flex font-mono text-gray-800">
                                    <div class="w-1/3 bg-slate-900 text-teal-300 p-6 text-xs">
                                        <p class="text-teal-400 mb-1">// profile</p>
                                        <img v-if="profilePicData.file_name" :src="'uploads/profile_picture/' + profilePicData.file_name" alt="Profile Photo"
                                            class="w-16 h-16 rounded object-cover border-2 border-teal-500 mb-3">
                                        <h1 class="text-lg font-bold text-white mb-4">{{ resumeFullName || 'Your Name' }}</h1>
                                        <p class="mb-1" v-if="profile.email">email: {{ profile.email }}</p>
                                        <p class="mb-1" v-if="profile.contact">phone: {{ profile.contact }}</p>
                                        <p class="mb-4" v-if="resumeLocation">loc: {{ resumeLocation }}</p>
                                        <div v-if="resumeSkills.length" class="mb-4">
                                            <p class="text-teal-400 mb-2">// skills</p>
                                            <div class="flex flex-wrap">
                                                <span v-for="s in resumeSkills" :key="'tc-skl-'+s.skill_id" class="bg-slate-800 text-teal-300 px-2 py-0.5 rounded text-[10px] mr-1 mb-1">{{ s.name }}</span>
                                            </div>
                                        </div>
                                        <div v-if="resumeCertifications.length">
                                            <p class="text-teal-400 mb-2">// certifications</p>
                                            <div class="flex flex-wrap">
                                                <span v-for="c in resumeCertifications" :key="'tc-cert-'+c.certification_id" class="bg-slate-800 text-teal-300 px-2 py-0.5 rounded text-[10px] mr-1 mb-1">{{ c.name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="w-2/3 p-6 text-sm">
                                        <div v-if="resumeSummary" class="mb-6">
                                            <h2 class="text-teal-700 font-bold mb-3">## Summary</h2>
                                            <p class="text-sm text-gray-700">{{ resumeSummary }}</p>
                                        </div>
                                        <div v-if="resumeExperience.length" class="mb-6">
                                            <h2 class="text-teal-700 font-bold mb-3">## Experience</h2>
                                            <div v-for="e in resumeExperience" :key="'tc-exp-'+e.experience_id" class="mb-4">
                                                <p class="font-semibold">{{ e.title }}<span v-if="e.title"> — </span>{{ e.company }}</p>
                                                <p class="text-xs text-gray-500">{{ e.start_date }} - {{ e.current ? 'Present' : e.end_date }}</p>
                                                <p v-if="e.description" class="text-xs mt-1 text-gray-700">{{ e.description }}</p>
                                            </div>
                                        </div>
                                        <div v-if="resumeEducation.length">
                                            <h2 class="text-teal-700 font-bold mb-3">## Education</h2>
                                            <div v-for="e in resumeEducation" :key="'tc-edu-'+e.education_id" class="mb-3">
                                                <p class="font-semibold">{{ e.school }}</p>
                                                <p class="text-xs text-gray-600">{{ e.degree }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div
                        class="flex items-center justify-end gap-3 px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex-shrink-0">
                        <button @click="closeResumeGenerator" aria-label="Close"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                        <button @click="generateResume" :disabled="generatingResume"
                            class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-60 flex items-center gap-2">
                            <i v-if="generatingResume" class="fas fa-spinner fa-spin"></i>
                            <i v-else class="fas fa-download"></i>
                            {{ generatingResume ? 'Generating...' : 'Download PDF' }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>


@endverbatim
@endsection
