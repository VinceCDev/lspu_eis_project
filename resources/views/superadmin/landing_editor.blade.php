@extends('layouts.admin')
@section('content')
@verbatim
<script>
    window.__heroSettings = <?= json_encode($settings) ?>;
</script>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200 mb-2">Landing Page</h1>
    <p class="text-gray-600 dark:text-gray-400">Edit the hero section shown on the public landing page</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6">Hero Content</h2>
        <form @submit.prevent="save" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Badge Text</label>
                <input type="text" v-model="form.landing_hero_badge" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Headline</label>
                <input type="text" v-model="form.landing_hero_headline" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subtext</label>
                <textarea v-model="form.landing_hero_subtext" required rows="4" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hero Background Image</label>
                <input type="file" accept="image/*" @change="onImageChange" class="w-full text-sm text-gray-700 dark:text-gray-300">
                <p class="text-xs text-gray-400 mt-1">Leave empty to keep the current image.</p>
            </div>
            <div class="flex justify-end">
                <button type="submit" :disabled="saving" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ saving ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4">Preview</h2>
        <div class="relative rounded-lg overflow-hidden bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900 p-8 min-h-[280px] flex flex-col justify-center"
             :style="imagePreview ? { backgroundImage: 'linear-gradient(rgba(30,64,175,0.75),rgba(79,70,229,0.75)), url(' + imagePreview + ')', backgroundSize: 'cover', backgroundPosition: 'center' } : {}">
            <div class="inline-flex items-center px-3 py-1.5 bg-white/10 border border-white/20 rounded-full text-xs font-medium text-white/90 w-fit mb-4">
                <i class="fas fa-star text-yellow-400 mr-2"></i>{{ form.landing_hero_badge }}
            </div>
            <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-yellow-200 mb-3">{{ form.landing_hero_headline }}</h1>
            <p class="text-blue-100 text-sm">{{ form.landing_hero_subtext }}</p>
        </div>
    </div>
</div>

@endverbatim
@endsection
