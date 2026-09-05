@extends('layouts.admin')
@section('content')
@verbatim
<div class="bg-white dark:bg-gray-700 rounded-xl shadow-xl main-shadow p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <h2 class="text-2xl font-bold mb-2 md:mb-0 text-gray-800 dark:text-gray-100">All Accounts</h2>
        <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full md:w-auto">
            <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition w-full sm:w-auto justify-center" @click="addAdmin">
                <i class="fas fa-plus"></i> Add Admin
            </button>
        </div>
    </div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
        <div class="flex items-center gap-2 w-full md:w-auto mb-2 md:mb-0">
            <div class="relative w-full md:w-80">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </span>
                <input type="text" class="form-input w-full pl-10 px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Search accounts..." v-model="search">
            </div>
        </div>
        <div class="flex flex-col sm:flex-row flex-wrap gap-2 w-full md:w-auto">
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto" v-model="filterRole">
                <option value="">All Roles</option>
                <option value="admin">Admin</option>
                <option v-if="isSuperadmin" value="superadmin">Superadmin</option>
                <option v-if="isSuperadmin" value="employer">Employer</option>
                <option value="alumni">Alumni</option>
            </select>
            <select class="form-select px-3 py-2 rounded border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-100 w-full sm:w-auto" v-model="filterStatus">
                <option value="">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-center">
            <thead>
                <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                    <th scope="col" class="px-4 py-2 text-center">Profile</th>
                    <th scope="col" class="px-4 py-2 text-center">Name</th>
                    <th scope="col" class="px-4 py-2 text-center">Email</th>
                    <th scope="col" class="px-4 py-2 text-center">Role</th>
                    <th scope="col" class="px-4 py-2 text-center">Status</th>
                    <th scope="col" class="px-4 py-2 text-center">Last Login</th>
                    <th scope="col" class="px-4 py-2 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="account in paginatedAccounts" :key="account.user_id" class="border-b border-gray-200 dark:border-gray-600">
                    <td class="px-4 py-2 text-center">
                        <img :src="getProfilePic(account)" :alt="account.name || 'Profile photo'" class="w-12 h-12 rounded-full object-cover border-2 border-blue-500 mx-auto">
                    </td>
                    <td class="px-4 py-2 font-semibold text-gray-800 dark:text-gray-200 text-center">{{ account.name }}</td>
                    <td class="px-4 py-2 text-gray-800 dark:text-gray-200 text-center">{{ account.email }}</td>
                    <td class="px-4 py-2 text-center">
                        <span :class="{
                            'bg-blue-100 text-blue-800': account.user_role === 'admin',
                            'bg-purple-100 text-purple-800': account.user_role === 'superadmin',
                            'bg-yellow-100 text-yellow-800': account.user_role === 'employer',
                            'bg-green-100 text-green-800': account.user_role === 'alumni'
                        }" class="inline-block px-2 py-1 rounded text-xs font-semibold capitalize">{{ account.user_role }}</span>
                    </td>
                    <td class="px-4 py-2 text-center">
                        <span :class="['inline-block px-2 py-1 rounded text-xs font-semibold', account.status === 'Active' ? 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200' : 'bg-red-100 text-red-700 dark:bg-red-800 dark:text-red-200']">
                            {{ account.status }}
                        </span>
                    </td>
                    <td class="px-4 py-2 font-semibold text-gray-800 dark:text-gray-200 text-center">{{ account.last_login }}</td>
                    <td class="px-4 py-2 text-center">
                        <div class="relative inline-block text-left">
                            <button @click="toggleActionDropdown(account.user_id)" class="p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none text-gray-500 dark:text-gray-200">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div v-if="actionDropdown === account.user_id" class="origin-top-right absolute right-0 mt-2 w-44 rounded-md shadow-lg bg-white dark:bg-gray-700 ring-1 ring-black ring-opacity-5 z-10">
                                <div class="py-1" @click="actionDropdown = null">
                                    <a href="#" role="button" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600" @click.prevent="viewAccount(account)"><i class="fas fa-eye mr-2"></i>View</a>
                                    <template v-if="account.user_role !== 'superadmin'">
                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-yellow-600 hover:bg-yellow-100 dark:hover:bg-yellow-800" @click.prevent="openEditModal(account)"><i class="fas fa-edit mr-2"></i>Edit</a>
                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-blue-600 hover:bg-blue-100 dark:hover:bg-blue-800" @click.prevent="resetAccountPassword(account)"><i class="fas fa-key mr-2"></i>Reset Password</a>
                                        <a v-if="account.status !== 'Inactive'" href="#" role="button" class="block px-4 py-2 text-sm text-orange-600 hover:bg-orange-100 dark:hover:bg-orange-800" @click.prevent="confirmDeactivateModal(account)"><i class="fas fa-ban mr-2"></i>Deactivate</a>
                                        <a href="#" role="button" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 dark:hover:bg-red-800" @click.prevent="confirmDelete(account)"><i class="fas fa-trash mr-2"></i>Delete</a>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr v-if="filteredAccounts.length === 0">
                    <td colspan="6" class="py-12 text-center text-gray-500 dark:text-gray-400">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fas fa-user-shield text-4xl text-gray-300 mb-2"></i>
                            <span class="text-lg text-gray-400">No accounts found</span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mt-4 gap-2">
        <div class="text-gray-600 dark:text-gray-300 text-sm text-center md:text-left">
            Showing {{ (currentPage - 1) * pageSize + 1 }} to {{ Math.min(currentPage * pageSize, filteredAccounts.length) }} of {{ filteredAccounts.length }} entries
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
    <!-- Add/Edit Admin Modal -->
    <div v-if="showAdminModal" class="fixed inset-0 z-[210] flex items-center justify-center bg-black bg-opacity-50 pointer-events-auto" role="dialog" aria-modal="true">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-2xl mx-2 p-6 relative max-h-[90vh] overflow-y-auto pointer-events-auto">
            <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200" @click="closeAdminModal"><i class="fas fa-times"></i></button>
            <div class="mt-3 text-left w-full">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                    {{ adminModalMode === 'edit' ? 'Edit Admin' : 'Add Admin' }}
                </h3>
                <div v-if="roleSelectionStep" class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Select Role</label>
                    <select v-model="roleToAdd" @change="() => { adminForm.user_role = roleToAdd; roleSelectionStep = false; }" class="block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        <option value="" disabled>Select role...</option>
                        <option value="admin">Admin</option>
                        <option v-if="isSuperadmin" value="employer">Employer</option>
                        <option value="alumni">Alumni</option>
                    </select>
                </div>
                <form v-if="!roleSelectionStep" @submit.prevent="adminModalMode === 'edit' ? updateAdmin() : addAdminSubmit()">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <template v-if="adminForm.user_role === 'employer'">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Company Name*</label>
                                <input type="text" v-model="adminForm.company_name" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Industry Name*</label>
                                <input type="text" v-model="adminForm.industry_type" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </template>
                        <template v-else-if="adminForm.user_role === 'admin' || adminForm.user_role === 'alumni'">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">First Name*</label>
                                <input type="text" v-model="adminForm.first_name" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Middle Name</label>
                                <input type="text" v-model="adminForm.middle_name" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Name*</label>
                                <input type="text" v-model="adminForm.last_name" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            </div>
                        </template>
                        <div v-if="adminForm.user_role === 'admin'">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Campus*</label>
                            <select v-model="adminForm.campus_id" required :disabled="!isSuperadmin" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white disabled:opacity-60 disabled:cursor-not-allowed">
                                <option value="" disabled>Select campus...</option>
                                <option v-for="c in campuses" :key="c.campus_id" :value="c.campus_id">{{ c.name }} ({{ c.type }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email*</label>
                            <input type="email" v-model="adminForm.email" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                            <select v-model="adminForm.status" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Profile Picture</label>
                            <input type="file" @change="handleAdminPhotoUpload" accept="image/*" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white">
                            <p v-if="currentProfilePicName" class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">Current: {{ currentProfilePicName }}</p>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm">
                            {{ adminModalMode === 'edit' ? 'Update Admin' : 'Add Admin' }}
                        </button>
                        <button type="button" @click="closeAdminModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-[220] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
            <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100">Confirm Delete</h3>
            <p class="mb-6 text-gray-700 dark:text-gray-200">Are you sure you want to delete this admin account?</p>
            <div class="flex justify-end gap-2">
                <button class="px-4 py-2 rounded bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 hover:bg-gray-400 dark:hover:bg-gray-700" @click="showDeleteModal = false">Cancel</button>
                <button class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700 transition" @click="deleteAdmin">Delete</button>
            </div>
        </div>
    </div>
    <!-- Deactivate Confirmation Modal -->
    <div v-if="showDeactivateModal" class="fixed inset-0 z-[220] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
            <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100">Deactivate Account</h3>
            <p class="mb-4 text-gray-700 dark:text-gray-200">This account will no longer be able to log in until reactivated. Please provide a reason (visible in Audit Logs).</p>
            <textarea v-model="deactivateNotes" rows="3" placeholder="Reason for deactivation..." class="w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 mb-4 focus:outline-none focus:ring-orange-500 focus:border-orange-500 dark:bg-gray-700 dark:text-white"></textarea>
            <div class="flex justify-end gap-2">
                <button class="px-4 py-2 rounded bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 hover:bg-gray-400 dark:hover:bg-gray-700" :disabled="deactivating" @click="showDeactivateModal = false">Cancel</button>
                <button class="px-4 py-2 rounded bg-orange-600 text-white hover:bg-orange-700 transition disabled:opacity-50" :disabled="deactivating || !deactivateNotes.trim()" @click="deactivateAccount">{{ deactivating ? 'Deactivating...' : 'Deactivate' }}</button>
            </div>
        </div>
    </div>
    <!-- View Modal -->
    <div v-if="showViewModal" class="fixed inset-0 z-[210] flex items-center justify-center bg-black bg-opacity-50 pointer-events-auto" role="dialog" aria-modal="true">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-xl mx-2 p-0 relative max-h-[95vh] overflow-y-auto pointer-events-auto">
            <button class="absolute top-3 right-3 flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full shadow hover:bg-gray-200 dark:hover:bg-gray-600 transition text-base font-semibold z-20" @click="showViewModal = false">
                <i class="fas fa-times"></i> <span>Close</span>
            </button>
            <div class="rounded-t-2xl bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400 dark:from-blue-900 dark:via-blue-800 dark:to-blue-700 px-0 pt-6 pb-8 flex flex-col items-center relative">
                <div class="absolute top-4 left-4 bg-white dark:bg-gray-700 rounded-full p-2 shadow-lg">
                    <i class="fas fa-user-shield text-blue-600 dark:text-blue-300 text-2xl"></i>
                </div>
                <div class="w-28 h-28 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center border-4 border-white dark:border-gray-700 shadow-xl mb-2 mt-2">
                    <img :src="getProfilePic(viewedAccount)" :alt="viewedAccount.name || 'Profile photo'" class="w-24 h-24 rounded-full object-cover">
                </div>
                <h3 class="text-3xl font-extrabold text-white drop-shadow-lg mb-1 text-center">{{ viewedAccount.name }}</h3>
                <span :class="['inline-block mt-1 px-3 py-1 rounded-full text-xs font-semibold shadow', viewedAccount.status === 'Active' ? 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200' : 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200']">{{ viewedAccount.status }}</span>
            </div>
            <div class="px-6 py-6">
                <h4 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100 flex items-center gap-2"><i class="fas fa-info-circle text-blue-500 dark:text-blue-300"></i> <span>Account Details</span></h4>
                <div class="grid grid-cols-1 gap-3 bg-gray-50 dark:bg-gray-900 rounded-xl p-4 shadow-sm">
                    <div class="flex items-center gap-3"><i class="fas fa-envelope text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Email:</span> <span class="ml-1 text-gray-700 dark:text-gray-200">{{ viewedAccount.email }}</span></div>
                    <div class="flex items-center gap-3"><i class="fas fa-user-tag text-blue-500 dark:text-blue-300"></i><span class="font-semibold text-gray-700 dark:text-gray-200">Role:</span> <span class="ml-1 text-gray-700 dark:text-gray-200 capitalize">{{ viewedAccount.user_role }}</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

@endverbatim
@endsection
