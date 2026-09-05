@extends('layouts.admin')
@section('content')
@verbatim
<div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-6 mb-6 border border-gray-100 dark:border-gray-600">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Success Stories</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Manage inspiring stories from alumni</p>
        </div>
        <button class="flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-all duration-200 shadow-sm hover:shadow-md w-full md:w-auto justify-center" @click="openAddStoryModal">
            <i class="fas fa-plus-circle"></i> Add New Story
        </button>
    </div>
</div>

<div class="bg-white dark:bg-gray-700 rounded-xl shadow-sm p-5 mb-6 border border-gray-100 dark:border-gray-600">
    <div class="flex flex-col md:flex-row md:items-center gap-4">
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search Stories</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <i class="fas fa-search text-gray-400"></i>
                </span>
                <input type="text" class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-600 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" placeholder="Search by title or content..." v-model="filters.search">
            </div>
        </div>

        <div class="w-full md:w-48">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
            <select class="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-600 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" v-model="filters.status">
                <option value="">All Status</option>
                <option value="published">Published</option>
                <option value="draft">Draft</option>
                <option value="archived">Archived</option>
            </select>
        </div>

        <div class="w-full md:w-48">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date Range</label>
            <select class="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-600 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" v-model="filters.dateRange">
                <option value="">All Time</option>
                <option value="today">Today</option>
                <option value="week">This Week</option>
                <option value="lastWeek">Last Week</option>
                <option value="month">This Month</option>
                <option value="lastMonth">Last Month</option>
                <option value="year">This Year</option>
                <option value="lastYear">Last Year</option>
            </select>
        </div>

        <div class="w-full md:w-48">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Author</label>
            <select class="w-full px-3 py-2.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-600 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" v-model="filters.author">
                <option value="">All Authors</option>
                <option v-for="alumni in alumniList" :key="alumni.user_id" :value="alumni.user_id">{{ alumni.full_name }} ({{ alumni.email }})</option>
            </select>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-6">
    <div v-for="story in filteredStories" :key="story.story_id" class="bg-white dark:bg-gray-700 rounded-xl shadow-md overflow-hidden hover:shadow-lg transition-all duration-300 border border-gray-200 dark:border-gray-600 group">
        <div class="h-48 bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-gray-600 dark:to-gray-800 relative overflow-hidden">
            <img v-if="story.profile_picture" :src="'/uploads/profile_picture/' + story.profile_picture" :alt="story.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
            <div v-else class="w-full h-full flex items-center justify-center text-blue-400 dark:text-blue-300">
                <i class="fas fa-user-circle text-5xl opacity-50"></i>
            </div>
            <span :class="['absolute top-3 right-3 px-3 py-1 rounded-full text-xs font-semibold shadow-sm',
                story.status === 'published' ? 'bg-green-100 text-green-700 dark:bg-green-800 dark:text-green-200' :
                story.status === 'draft' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-800 dark:text-yellow-200' :
                'bg-gray-100 text-gray-700 dark:bg-gray-600 dark:text-gray-200']">
                {{ story.status }}
            </span>
        </div>

        <div class="p-5">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2 line-clamp-2 leading-tight">{{ story.title }}</h3>
            <p class="text-gray-600 dark:text-gray-300 text-sm mb-4 line-clamp-3">{{ story.content.substring(0, 120) + '...' }}</p>

            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center mr-2 text-white text-sm overflow-hidden">
                        <img v-if="story.profile_picture" :src="'/uploads/profile_picture/' + story.profile_picture" :alt="story.author_full_name" class="w-full h-full object-cover">
                        <span v-else>{{ story.author_full_name ? story.author_full_name.charAt(0).toUpperCase() : 'A' }}</span>
                    </div>
                    <div>
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ story.author_full_name }}</span>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ story.author_email }}</p>
                    </div>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(story.created_at) }}</span>
            </div>

            <div class="flex flex-wrap gap-2 mb-4">
                <button v-if="story.status !== 'published'" @click="updateStoryStatus(story, 'published')" class="flex-1 px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white text-xs rounded-lg transition-colors flex items-center justify-center">
                    <i class="fas fa-check mr-1 text-xs"></i> Publish
                </button>
                <button v-if="story.status !== 'draft'" @click="updateStoryStatus(story, 'draft')" class="flex-1 px-3 py-1.5 bg-yellow-500 hover:bg-yellow-600 text-white text-xs rounded-lg transition-colors flex items-center justify-center">
                    <i class="fas fa-edit mr-1 text-xs"></i> Draft
                </button>
                <button v-if="story.status !== 'archived'" @click="updateStoryStatus(story, 'archived')" class="flex-1 px-3 py-1.5 bg-gray-500 hover:bg-gray-600 text-white text-xs rounded-lg transition-colors flex items-center justify-center">
                    <i class="fas fa-archive mr-1 text-xs"></i> Archive
                </button>
            </div>

            <div class="flex justify-between items-center pt-3 border-t border-gray-100 dark:border-gray-600">
                <button @click="viewStory(story)" class="text-blue-600 hover:text-blue-800 dark:text-blue-300 dark:hover:text-blue-200 text-sm flex items-center">
                    <i class="fas fa-eye mr-1.5"></i> View
                </button>
                <div class="flex space-x-3">
                    <button @click="deleteStory(story)" class="text-red-600 hover:text-red-800 dark:text-red-300 dark:hover:text-red-200 transition-colors" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div v-if="filteredStories.length === 0" class="col-span-full py-16 text-center">
        <div class="flex flex-col items-center justify-center">
            <div class="w-24 h-24 rounded-full bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center mb-6">
                <i class="fas fa-book-open text-4xl text-blue-400 dark:text-blue-300"></i>
            </div>
            <h3 class="text-xl font-medium text-gray-600 dark:text-gray-300 mb-2">No success stories found</h3>
            <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-md">Create your first success story to inspire and motivate others.</p>
            <button @click="openAddStoryModal" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2">
                <i class="fas fa-plus"></i> Add Your First Story
            </button>
        </div>
    </div>
</div>

<!-- Add/Edit Story Modal -->
<div v-if="showStoryModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true" data-modal="story-modal">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-3xl mx-2 p-6 relative max-h-[90vh] overflow-y-auto">
        <button class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors" @click="closeStoryModal" aria-label="Close">
            <i class="fas fa-times text-xl"></i>
        </button>

        <div class="mt-3 text-left w-full">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-6 pb-3 border-b border-gray-200 dark:border-gray-600">
                {{ editingStory ? 'Edit Success Story' : 'Add New Success Story' }}
            </h3>

            <form @submit.prevent="editingStory ? updateStory() : addStory()">
                <div class="grid grid-cols-1 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Author*</label>
                        <select v-model="storyForm.user_id" required class="w-full border border-gray-200 dark:border-gray-600 rounded-lg py-2.5 px-4 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition-all">
                            <option value="">Select Alumni Author</option>
                            <option v-for="alumni in alumniList" :key="alumni.user_id" :value="alumni.user_id">
                                {{ alumni.full_name }} ({{ alumni.email }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title*</label>
                        <input type="text" v-model="storyForm.title" required autocomplete="off"
                            class="w-full border border-gray-200 dark:border-gray-600 rounded-lg py-2.5 px-4 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Content*</label>
                        <textarea v-model="storyForm.content" required rows="6"
                            class="w-full border border-gray-200 dark:border-gray-600 rounded-lg py-2.5 px-4 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition-all"
                            placeholder="Share the inspiring success story..."></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status*</label>
                        <select v-model="storyForm.status" required
                            class="w-full border border-gray-200 dark:border-gray-600 rounded-lg py-2.5 px-4 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white transition-all">
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                </div>

                <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                    <button type="button" @click="closeStoryModal"
                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:col-start-1 sm:text-sm">
                        Cancel
                    </button>
                    <button type="submit"
                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:col-start-2 sm:text-sm">
                        {{ editingStory ? 'Update Story' : 'Add Story' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Story Modal -->
<div v-if="showViewStoryModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true" data-modal="view-story-modal">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-4xl mx-2 p-0 relative max-h-[95vh] overflow-y-auto">
        <button class="absolute top-4 right-4 flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-full shadow hover:bg-gray-200 dark:hover:bg-gray-600 transition text-base font-semibold z-20" @click="closeViewStoryModal" aria-label="Close">
            <i class="fas fa-times"></i> <span>Close</span>
        </button>

        <div class="relative h-64 bg-gradient-to-r from-blue-600 via-blue-500 to-blue-400 dark:from-blue-900 dark:via-blue-800 dark:to-blue-700">
            <div v-if="viewingStory.profile_picture" class="absolute inset-0">
                <img :src="'/uploads/profile_picture/' + viewingStory.profile_picture" :alt="viewingStory.author_full_name" class="w-full h-full object-cover opacity-20">
            </div>
            <div class="relative z-10 flex flex-col items-center justify-center h-full text-center px-6 text-white">
                <h1 class="text-3xl font-bold drop-shadow-lg mb-2">{{ viewingStory.title }}</h1>
                <span :class="['inline-block mt-2 px-3 py-1 rounded-full text-xs font-semibold shadow',
                    viewingStory.status === 'published' ? 'bg-green-100 text-green-700' :
                    viewingStory.status === 'draft' ? 'bg-yellow-100 text-yellow-700' :
                    'bg-gray-200 text-gray-700']">
                    {{ viewingStory.status }}
                </span>
            </div>
        </div>

        <div class="px-6 py-6">
            <div class="flex items-center mb-6">
                <div class="w-12 h-12 rounded-full bg-blue-500 flex items-center justify-center mr-3 overflow-hidden">
                    <img v-if="viewingStory.profile_picture" :src="'/uploads/profile_picture/' + viewingStory.profile_picture" :alt="viewingStory.author_full_name" class="w-full h-full object-cover">
                    <i v-else class="fas fa-user text-white"></i>
                </div>
                <div>
                    <h4 class="font-semibold text-gray-800 dark:text-gray-100">{{ viewingStory.author_full_name }}</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ viewingStory.author_email }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(viewingStory.created_at) }}</p>
                </div>
            </div>

            <div class="prose dark:prose-invert max-w-none mb-6">
                <p class="text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">{{ viewingStory.content }}</p>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button @click="editStory(viewingStory)" class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">
                    <i class="fas fa-edit mr-2"></i> Edit
                </button>
                <button @click="deleteStory(viewingStory)" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-trash mr-2"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Delete Confirmation Modal for Success Story -->
<div v-if="showDeleteModal" class="fixed inset-0 z-[1000] flex items-center justify-center bg-black bg-opacity-50" role="dialog" aria-modal="true">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-md mx-2 p-6 relative">
        <h3 class="text-lg font-bold mb-4 text-gray-800 dark:text-gray-100">Confirm Delete</h3>
        <p class="mb-6 text-gray-700 dark:text-gray-200">Are you sure you want to delete this success story?</p>
        <div class="flex justify-end gap-2">
            <button class="px-4 py-2 rounded bg-gray-300 dark:bg-gray-600 text-gray-800 dark:text-gray-200 hover:bg-gray-400 dark:hover:bg-gray-700" @click="showDeleteModal = false">Cancel</button>
            <button class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700 transition" @click="confirmDeleteStory">Delete</button>
        </div>
    </div>
</div>

@endverbatim
@endsection
