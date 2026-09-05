@extends('layouts.admin')
@section('content')
@verbatim
<section class="relative bg-white dark:bg-gray-700 rounded-xl shadow-sm p-4 sm:p-6 mb-6 overflow-hidden">
    <div class="absolute inset-0 bg-cover bg-center" style="background-image:url('<?= asset('assets/images/lspu_campus.jpg') ?>')">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-800/60 via-slate-700/50 to-blue-700/60"></div>
    </div>
    <div class="relative z-10 flex flex-col items-center text-center">
        <div class="relative mb-4 sm:mb-6">
            <img :src="profile.profile_pic || '<?= asset('assets/images/logo.png') ?>'" alt="Profile Photo" class="w-24 h-24 sm:w-40 sm:h-40 rounded-full object-cover border-4 border-white dark:border-gray-300 shadow-lg">
            <div class="absolute -bottom-1 -right-1 sm:-bottom-2 sm:-right-2 bg-blue-600 text-white rounded-full p-1.5 sm:p-2 cursor-pointer hover:bg-blue-700 transition-colors" @click="openPhotoUpload">
                <i class="fas fa-camera text-xs sm:text-sm"></i>
            </div>
        </div>
        <div class="mb-4 sm:mb-6 w-full">
            <h1 class="text-2xl sm:text-4xl font-bold text-white mb-2 drop-shadow-lg">{{ profile.name }}</h1>
            <p class="text-lg sm:text-xl text-white mb-4 drop-shadow-lg">{{ profile.position || 'Administrator' }}</p>
            <div class="flex flex-row flex-wrap gap-2 sm:gap-4 justify-center mb-4 sm:mb-6 w-full">
                <div class="flex items-center justify-center bg-white bg-opacity-20 backdrop-blur-sm rounded-lg px-3 py-2 sm:px-4 sm:py-2 min-w-[120px]">
                    <i class="fas fa-envelope text-white mr-2 text-sm sm:text-base"></i>
                    <span class="text-white text-sm sm:text-base">{{ profile.email || 'No email specified' }}</span>
                </div>
                <div class="flex items-center justify-center bg-white bg-opacity-20 backdrop-blur-sm rounded-lg px-3 py-2 sm:px-4 sm:py-2 min-w-[120px]">
                    <i class="fas fa-phone text-white mr-2 text-sm sm:text-base"></i>
                    <span class="text-white text-sm sm:text-base">{{ profile.phone || 'No phone specified' }}</span>
                </div>
                <div class="flex items-center justify-center bg-white bg-opacity-20 backdrop-blur-sm rounded-lg px-3 py-2 sm:px-4 sm:py-2 min-w-[120px]">
                    <i class="fas fa-map-marker-alt text-white mr-2 text-sm sm:text-base"></i>
                    <span class="text-white text-sm sm:text-base">{{ profile.address || 'No address specified' }}</span>
                </div>
            </div>
        </div>
        <button @click="editProfile" class="bg-white text-blue-600 px-6 py-2 sm:px-8 sm:py-3 rounded-lg hover:bg-gray-100 transition-colors shadow-md font-semibold text-sm sm:text-base">
            <i class="fas fa-edit mr-2"></i>Edit Profile
        </button>
    </div>
</section>
<section class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 mb-6">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 uppercase">Personal Information</h2>
        <button @click="editProfile" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-edit mr-1"></i>Edit
        </button>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="space-y-2">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Full Name</div>
            <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.name || 'Not specified' }}</div>
        </div>
        <div class="space-y-2">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Email</div>
            <div class="text-gray-800 dark:text-gray-200">{{ profile.email || 'Not specified' }}</div>
        </div>
        <div class="space-y-2">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Phone</div>
            <div class="text-gray-800 dark:text-gray-200">{{ profile.phone || 'Not specified' }}</div>
        </div>
        <div class="space-y-2">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Address</div>
            <div class="text-gray-800 dark:text-gray-200">{{ profile.address || 'Not specified' }}</div>
        </div>
        <div class="space-y-2">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">Position</div>
            <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.position || 'Not specified' }}</div>
        </div>
        <div class="space-y-2">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase">{{ profile.is_superadmin ? 'University' : 'Campus' }}</div>
            <div class="text-gray-800 dark:text-gray-200 uppercase">{{ profile.campus_name || 'Not specified' }}</div>
        </div>
    </div>
</section>
<!-- Edit Profile Modal -->
<div v-if="showEditModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl mx-2 p-6 relative max-h-[90vh] overflow-y-auto">
        <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="closeEditModal">
            <i class="fas fa-times"></i>
        </button>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Edit Profile</h3>
        <form @submit.prevent="saveProfile">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name*</label>
                    <input type="text" v-model="editForm.first_name" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Middle Name</label>
                    <input type="text" v-model="editForm.middle_name" class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name</label>
                    <input type="text" v-model="editForm.last_name" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email*</label>
                    <input type="email" v-model="profile.email" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone</label>
                    <input type="tel" v-model="editForm.contact" class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                    <div class="relative">
                        <input type="text" v-model="editForm.address" @input="fetchAddressSuggestions" @focus="showAddressSuggestions = true" @blur="hideAddressSuggestions" class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" placeholder="Start typing address...">
                        <ul v-if="showAddressSuggestions && addressSuggestions.length" class="absolute z-10 left-0 right-0 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded shadow mt-1 max-h-48 overflow-y-auto">
                            <li v-for="suggestion in addressSuggestions" :key="suggestion" @mousedown.prevent="selectAddressSuggestion(suggestion)" class="px-4 py-2 cursor-pointer text-gray-800 dark:text-gray-100 hover:bg-blue-100 dark:hover:bg-blue-900 hover:text-blue-900 dark:hover:text-white transition">
                                {{ suggestion }}
                            </li>
                        </ul>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Position</label>
                    <input type="text" :value="profile.position" readonly disabled title="Set automatically based on your account role" class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ profile.is_superadmin ? 'University' : 'Campus' }}</label>
                    <input type="text" :value="profile.campus_name" readonly disabled title="Set automatically based on your account" class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 cursor-not-allowed">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" @click="closeEditModal" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
<!-- Photo Upload Modal -->
<div v-if="showPhotoModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
        <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="closePhotoModal">
            <i class="fas fa-times"></i>
        </button>
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Update Profile Photo</h3>
        <div class="space-y-4">
            <div class="flex justify-center">
                <div class="relative">
                    <img :src="profile.profile_pic || '<?= asset('assets/images/logo.png') ?>'" alt="Current Photo" class="w-32 h-32 rounded-full object-cover border-4 border-gray-200 dark:border-gray-600">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Choose New Photo</label>
                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:border-blue-500 transition-colors">
                    <input type="file" ref="photoInput" @change="handlePhotoUpload" accept="image/*" class="hidden">
                    <div class="cursor-pointer" @click="$refs.photoInput.click()">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                        <p class="text-gray-600 dark:text-gray-300">Click to upload or drag and drop</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">PNG, JPG, GIF up to 5MB</p>
                    </div>
                </div>
            </div>
            <div v-if="newPhotoPreview" class="flex justify-center">
                <div class="relative">
                    <img :src="newPhotoPreview" alt="New Photo Preview" class="w-32 h-32 rounded-full object-cover border-4 border-blue-500">
                    <div class="absolute -top-2 -right-2 bg-green-500 text-white rounded-full p-1">
                        <i class="fas fa-check text-xs"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button @click="closePhotoModal" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                Cancel
            </button>
            <button @click="savePhoto" :disabled="!newPhotoPreview" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Update Photo
            </button>
        </div>
    </div>
</div>

@endverbatim
@endsection
