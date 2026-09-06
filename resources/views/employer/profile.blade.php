@extends('layouts.employer')
@section('content')
@verbatim
            <!-- Hero Section -->
            <section class="relative bg-white dark:bg-gray-700 rounded-xl shadow-sm p-4 sm:p-6 mb-6 overflow-hidden">
                <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('<?= asset('assets/images/lspu_campus.jpg') ?>')">
                    <div class="absolute inset-0 bg-gradient-to-br from-slate-800/60 via-slate-700/50 to-blue-700/60"></div>
                </div>
                <div class="relative z-10 flex flex-col items-center text-center">
                    <div class="relative mb-4 sm:mb-6">
                        <img :src="profile.company_logo || '<?= asset('assets/images/logo.png') ?>'" alt="Company Logo" class="w-24 h-24 sm:w-40 sm:h-40 rounded-full object-cover border-4 border-white dark:border-gray-300 shadow-lg">
                        <div class="absolute -bottom-1 -right-1 sm:-bottom-2 sm:-right-2 bg-blue-600 text-white rounded-full p-1.5 sm:p-2 cursor-pointer hover:bg-blue-700 transition-colors" @click="openPhotoUpload">
                            <i class="fas fa-camera text-xs sm:text-sm"></i>
                        </div>
                    </div>
                    <div class="mb-4 sm:mb-6 w-full">
                        <h1 class="text-2xl sm:text-4xl font-bold text-white mb-2 drop-shadow-lg">{{ profile.company_name }}</h1>
                        <p class="text-lg sm:text-xl text-white mb-4 drop-shadow-lg">{{ profile.nature_of_business || 'Nature of Business' }}</p>
                        <div class="flex flex-row flex-wrap gap-2 sm:gap-4 justify-center mb-4 sm:mb-6 w-full">
                            <div class="flex items-center justify-center bg-white bg-opacity-20 backdrop-blur-sm rounded-lg px-3 py-2 sm:px-4 sm:py-2 min-w-[120px]">
                                <i class="fas fa-envelope text-white mr-2 text-sm sm:text-base"></i>
                                <span class="text-white text-sm sm:text-base">{{ profile.contact_email || 'No email specified' }}</span>
                            </div>
                            <div class="flex items-center justify-center bg-white bg-opacity-20 backdrop-blur-sm rounded-lg px-3 py-2 sm:px-4 sm:py-2 min-w-[120px]">
                                <i class="fas fa-phone text-white mr-2 text-sm sm:text-base"></i>
                                <span class="text-white text-sm sm:text-base">{{ profile.contact_number || 'No phone specified' }}</span>
                            </div>
                            <div class="flex items-center justify-center bg-white bg-opacity-20 backdrop-blur-sm rounded-lg px-3 py-2 sm:px-4 sm:py-2 min-w-[120px]">
                                <i class="fas fa-map-marker-alt text-white mr-2 text-sm sm:text-base"></i>
                                <span class="text-white text-sm sm:text-base">{{ profile.company_location || 'No location specified' }}</span>
                            </div>
                        </div>
                    </div>
                    <button @click="editProfile" class="bg-white text-blue-600 px-6 py-2 sm:px-8 sm:py-3 rounded-lg hover:bg-gray-100 transition-colors shadow-md font-semibold text-sm sm:text-base">
                        <i class="fas fa-edit mr-2"></i>Edit Profile
                    </button>
                </div>
            </section>
            <!-- Company Information Section -->
            <section class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 uppercase">Company Information</h2>
                    <button @click="editProfile" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-edit mr-1"></i>Edit
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Company Name</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.company_name || 'Not specified' }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Company Address</div>
                        <div class="text-gray-800 dark:text-gray-200">{{ profile.company_location || 'Not specified' }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Contact Email</div>
                        <div class="text-gray-800 dark:text-gray-200">{{ profile.contact_email || 'Not specified' }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Contact Number</div>
                        <div class="text-gray-800 dark:text-gray-200">{{ profile.contact_number || 'Not specified' }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Industry Type</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.industry_type || 'Not specified' }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Nature of Business</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.nature_of_business || 'Not specified' }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">TIN</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.tin || 'Not specified' }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Date Established</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.date_established || 'Not specified' }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Type of Company</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.company_type || 'Not specified' }}</div>
                    </div>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Accreditation Status</div>
                        <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.accreditation_status || 'Not specified' }}</div>
                    </div>
                </div>
            </section>
            <!-- Verification Document Section -->
            <section class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 mb-6 max-w-7xl mx-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-100 uppercase truncate mr-3">Verification Document</h2>
                    <button v-if="profile.document_file" @click="confirmDeleteDocument"
                        class="bg-red-600 text-white px-3 py-2 rounded-lg hover:bg-red-700 transition-colors whitespace-nowrap text-sm flex-shrink-0">
                        <i class="fas fa-trash mr-1"></i>Delete
                    </button>
                </div>
                <div v-if="profile.document_file" class="flex items-center gap-4">
                    <i :class="fileIconClass(profile.document_file)" class="text-3xl"></i>
                    <div class="text-gray-800 dark:text-gray-200 min-w-0 flex-1">
                        <a :href="profile.document_file" target="_blank"
                            class="underline hover:text-blue-700 dark:hover:text-blue-300 truncate block"
                            :title="getFileName(profile.document_file)">
                            {{ getFileName(profile.document_file) }}
                        </a>
                    </div>
                    <button @click="showDocumentModal = true"
                        class="text-gray-500 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-300 text-sm whitespace-nowrap flex-shrink-0">
                        <i class="fas fa-upload mr-1"></i>Change
                    </button>
                </div>
                <div v-else class="flex items-center justify-between gap-4">
                    <span class="text-gray-500 dark:text-gray-400 flex items-center gap-2">
                        <i class="fas fa-file-alt"></i> No document uploaded.
                    </span>
                    <button @click="showDocumentModal = true" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap text-sm flex-shrink-0">
                        <i class="fas fa-upload mr-1"></i>Upload
                    </button>
                </div>
            </section>
            <!-- Document Modal -->
            <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from" enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from" leave-to-class="modal-leave-to">
                <div v-if="showDocumentModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                        <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="showDocumentModal = false">
                            <i class="fas fa-times"></i>
                        </button>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Change Company Document</h3>
                        <input type="file" @change="handleDocumentUpload" accept="application/pdf,image/*" class="mb-4">
                        <div v-if="newDocumentFile">
                            <div v-if="isPdf(newDocumentName)" class="mb-4">
                                <iframe :src="newDocumentPreview" style="width:100%;height:300px;" class="border rounded"></iframe>
                            </div>
                            <div v-else-if="isImage(newDocumentName)" class="mb-4 flex justify-center">
                                <img :src="newDocumentPreview" alt="Document Preview" class="max-h-48 rounded border mx-auto">
                            </div>
                            <div class="mb-2 text-gray-700 dark:text-gray-200 flex items-center">
                                <i class="fas fa-file-alt mr-2"></i>
                                <span>{{ newDocumentName }}</span>
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button @click="showDocumentModal = false" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                            <button @click="saveDocument" :disabled="!newDocumentFile" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">Save Document</button>
                        </div>
                    </div>
                </div>
            </transition>
            <!-- Delete Document Confirmation Modal -->
            <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from" enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from" leave-to-class="modal-leave-to">
                <div v-if="showDeleteDocumentModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Confirm Delete</h3>
                        <p class="mb-6 text-gray-700 dark:text-gray-300">Are you sure you want to delete your company document?</p>
                        <div class="flex justify-end gap-3">
                            <button @click="showDeleteDocumentModal = false" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                            <button @click="deleteDocument" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
        <!-- Edit Profile Modal -->
        <transition 
            enter-active-class="modal-enter-active"
            enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to"
            leave-active-class="modal-leave-active"
            leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to"
        >
            <div v-if="showEditModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl mx-2 p-6 relative max-h-[90vh] overflow-y-auto">
                    <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="closeEditModal">
                        <i class="fas fa-times"></i>
                    </button>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Edit Company Profile</h3>
                    <form @submit.prevent="saveProfile">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company Name*</label>
                                <input type="text" v-model="editForm.company_name" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company Address*</label>
                                <input type="text" v-model="editForm.company_location" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Email*</label>
                                <input type="email" v-model="editForm.contact_email" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Number*</label>
                                <input type="text" v-model="editForm.contact_number" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Industry Type*</label>
                                <input type="text" v-model="editForm.industry_type" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nature of Business*</label>
                                <input type="text" v-model="editForm.nature_of_business" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">TIN*</label>
                                <input type="text" v-model="editForm.tin" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date Established*</label>
                                <input type="date" v-model="editForm.date_established" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type of Company*</label>
                                <input type="text" v-model="editForm.company_type" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Accreditation Status*</label>
                                <input type="text" v-model="editForm.accreditation_status" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" @click="closeEditModal" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>
        <!-- Photo Upload Modal -->
        <transition 
            enter-active-class="modal-enter-active"
            enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to"
            leave-active-class="modal-leave-active"
            leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to"
        >
            <div v-if="showPhotoModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                    <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="closePhotoModal">
                        <i class="fas fa-times"></i>
                    </button>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Update Company Logo</h3>
                    <div class="space-y-4">
                        <div class="flex justify-center">
                            <div class="relative">
                                <img :src="profile.company_logo || '<?= asset('assets/images/logo.png') ?>'" alt="Current Logo" class="w-32 h-32 rounded-full object-cover border-4 border-gray-200 dark:border-gray-600">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Choose New Logo</label>
                            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-blue-500 transition-colors">
                                <input type="file" ref="logoInput" @change="handleLogoUpload" accept="image/*" class="hidden">
                                <div class="cursor-pointer" @click="$refs.logoInput.click()">
                                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                                    <p class="text-gray-600 dark:text-gray-300">Click to upload or drag and drop</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">PNG, JPG, GIF up to 5MB</p>
                                </div>
                            </div>
                        </div>
                        <div v-if="newLogoPreview" class="flex justify-center">
                            <div class="relative">
                                <img :src="newLogoPreview" alt="New Logo Preview" class="w-32 h-32 rounded-full object-cover border-4 border-blue-500">
                                <div class="absolute -top-2 -right-2 bg-green-500 text-white rounded-full p-1">
                                    <i class="fas fa-check text-xs"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button v-if="profile.company_logo" @click="confirmDeleteLogo" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 whitespace-nowrap">
                            Delete Photo
                        </button>
                        <button @click="saveLogo" :disabled="!newLogoPreview" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
                            <span v-if="profile.company_logo">Update Photo</span>
                            <span v-else>Save Photo</span>
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Delete Logo Confirmation Modal -->
        <transition enter-active-class="modal-enter-active" enter-from-class="modal-enter-from"
            enter-to-class="modal-enter-to" leave-active-class="modal-leave-active" leave-from-class="modal-leave-from"
            leave-to-class="modal-leave-to">
            <div v-if="showDeleteLogoModal" class="fixed inset-0 z-[210] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
                    <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100">Confirm Delete</h3>
                    <p class="mb-6 text-gray-700 dark:text-gray-200">Are you sure you want to delete this photo?</p>
                    <div class="flex gap-3">
                        <button @click="showDeleteLogoModal = false" class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                        <button @click="deleteLogo" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Delete</button>
                    </div>
                </div>
            </div>
        </transition>

@endverbatim
@endsection
