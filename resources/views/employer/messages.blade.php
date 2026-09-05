@extends('layouts.employer')
@section('content')
@verbatim
    <div class="container-fluid max-w-7xl mx-auto">
                <div class="flex flex-col md:flex-row gap-6">
                    <!-- Message Sidebar (Folders) -->
                    <div class="w-full md:w-1/4">
                        <div class="bg-white dark:bg-slate-700 rounded-lg shadow-lg p-4 text-slate-800 dark:text-slate-200 border border-gray-200 dark:border-gray-600">
                            <div class="border-b-2 border-blue-600 dark:border-blue-400 pb-2 mb-4">
                                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Mailbox Folders</h2>
                            </div>
        
                        <!-- Compose Button -->
                        <button 
                            class="w-full flex items-center justify-center gap-2 bg-blue-700 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg mb-4 transition-colors duration-200 shadow-sm"
                            @click="showCompose = true">
                            <i class="fas fa-pen-to-square"></i> 
                            <span>Compose</span>
                        </button>
        
                        <!-- Folder Navigation -->
                        <nav class="flex flex-col gap-1">
                            <!-- Inbox -->
                            <a href="#" 
                            @click.prevent="activeFolder = 'inbox'; showCompose = false" 
                            :class="[
                                'flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200',
                                activeFolder === 'inbox' 
                                    ? 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 border-l-4 border-blue-600 dark:border-blue-400' 
                                    : 'hover:bg-slate-200 dark:hover:bg-slate-600/50 text-slate-700 dark:text-slate-300'
                            ]">
                                <span class="flex items-center">
                                    <i class="fas fa-inbox mr-3 text-blue-500 dark:text-blue-400"></i>
                                    <span class="font-medium">Inbox</span>
                                </span>
                                <span v-if="inboxCount > 0" class="bg-blue-600 text-white text-xs font-bold px-2 py-1 rounded-full">
                                    {{ inboxCount }}
                                </span>
                            </a>
                            
                            <!-- Sent -->
                            <a href="#" 
                            @click.prevent="activeFolder = 'sent'; showCompose = false" 
                            :class="[
                                'flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200',
                                activeFolder === 'sent' 
                                    ? 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 border-l-4 border-blue-600 dark:border-blue-400' 
                                    : 'hover:bg-slate-200 dark:hover:bg-slate-600/50 text-slate-700 dark:text-slate-300'
                            ]">
                                <span class="flex items-center">
                                    <i class="fas fa-paper-plane mr-3 text-blue-500 dark:text-blue-400"></i>
                                    <span class="font-medium">Sent</span>
                                </span>
                                <span v-if="sentCount > 0" class="bg-blue-600 text-white text-xs font-bold px-2 py-1 rounded-full">
                                    {{ sentCount }}
                                </span>
                            </a>

                            <!-- Important -->
                            <a href="#"
                            @click.prevent="activeFolder = 'important'; showCompose = false"
                            :class="[
                                'flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200',
                                activeFolder === 'important'
                                    ? 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 border-l-4 border-blue-600 dark:border-blue-400'
                                    : 'hover:bg-slate-200 dark:hover:bg-slate-600/50 text-slate-700 dark:text-slate-300'
                            ]">
                                <span class="flex items-center">
                                    <i class="fas fa-star mr-3 text-yellow-500 dark:text-yellow-400"></i>
                                    <span class="font-medium">Important</span>
                                </span>
                                <span v-if="importantCount > 0" class="bg-blue-600 text-white text-xs font-bold px-2 py-1 rounded-full">
                                    {{ importantCount }}
                                </span>
                            </a>

                            <!-- Trash -->
                            <a href="#"
                            @click.prevent="activeFolder = 'trash'; showCompose = false"
                            :class="[
                                'flex items-center justify-between px-3 py-2.5 rounded-lg transition-colors duration-200',
                                activeFolder === 'trash'
                                    ? 'bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 border-l-4 border-blue-600 dark:border-blue-400'
                                    : 'hover:bg-slate-200 dark:hover:bg-slate-600/50 text-slate-700 dark:text-slate-300'
                            ]">
                                <span class="flex items-center">
                                    <i class="fas fa-trash mr-3 text-red-500 dark:text-red-400"></i>
                                    <span class="font-medium">Trash</span>
                                </span>
                                <span v-if="trashCount > 0" class="bg-blue-600 text-white text-xs font-bold px-2 py-1 rounded-full">
                                    {{ trashCount }}
                                </span>
                            </a>
                        </nav>
                    </div>
            </div>
            <!-- Main Panel -->
            <div class="w-full md:w-3/4 bg-white dark:bg-gray-700 rounded-lg shadow p-4">
                <!-- Compose Message Panel (not modal) -->
                <!-- Compose Message Panel (not modal) -->
                <div v-if="showCompose" class="bg-white dark:bg-gray-700 rounded-lg shadow-lg p-6 mb-6 border border-gray-200 dark:border-gray-600">
                    <div class="border-b-2 border-blue-600 dark:border-blue-400 pb-3 mb-6 flex items-center justify-between">
                        <h2 class="text-xl font-bold flex items-center gap-2 text-blue-700 dark:text-blue-300">
                            <i class="fas fa-pen text-blue-500 dark:text-blue-400"></i> 
                            Compose New Message
                        </h2>
                        <button @click="showCompose = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 p-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    
                    <form @submit.prevent="sendMessage" class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role <span class="text-red-500">*</span></label>
                            <input v-model="compose.role"
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-400 dark:focus:border-blue-400 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm" readonly disabled>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Receiver <span class="text-red-500">*</span></label>
                            <select v-model="compose.receiver" required @change="onReceiverChange" 
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-400 dark:focus:border-blue-400 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm">
                                <option value="">Select Receiver</option>
                                <option v-for="user in allUsers" :key="user.email" :value="user.email">
                                    {{ user.name }} ({{ user.email }})
                                </option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subject <span class="text-red-500">*</span></label>
                            <input v-model="compose.subject" required type="text" 
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:focus:ring-blue-400 dark:focus:border-blue-400 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Message <span class="text-red-500">*</span>
                            </label>
                            <div id="editor" class="min-h-[220px] border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800"></div>
                        </div>
                        
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" 
                                    class="px-5 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors duration-200 font-medium shadow-sm">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-5 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium shadow-sm transition-colors duration-200 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 flex items-center gap-2">
                                <i class="fas fa-paper-plane"></i>
                                Send Message
                            </button>
                        </div>
                    </form>
                </div>
                <!-- Folder Panels -->
                <div v-else>
                    <div class="border-b-2 border-blue-700 dark:border-blue-300 pb-2 mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold flex items-center gap-2 text-blue-700 dark:text-blue-300">
                            <i :class="folderIcon"></i> {{ folderTitle }}
                        </h2>                        <!-- Search bar styled like superadmin_job.php -->
                        <div class="relative w-64 sm:w-72 md:w-80 lg:w-96 ml-8 sm:ml-20 mr-4 sm:mr-0">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </span>
                            <input 
                                type="text" 
                                v-model="searchQuery" 
                                placeholder="Search alumni..." 
                                class="w-full form-input pl-10 px-3 py-1.5 sm:py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 text-sm sm:text-base text-black dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-200"
                                @input="handleSearchInput"
                            >
                            <button 
                                v-if="searchQuery" 
                                @click="clearSearch"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors duration-200"
                            >
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <!-- Bulk Action Toolbar -->
                    <div class="flex items-center gap-2 px-2 py-3 mb-2">
                        <button class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300" title="Star" @click="toggleImportantSelected"><i class="fas fa-star"></i></button>
                        <button class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300" title="Trash" @click="moveToTrashSelected"><i class="fas fa-trash"></i></button>
                        <button v-if="activeFolder === 'trash'" class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300" title="Restore" @click="restoreFromTrashSelected"><i class="fas fa-undo"></i></button>
                        <button class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300" title="Copy" @click="copyTable"><i class="fas fa-copy"></i></button>
                        <button class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300" title="Export Excel" @click="exportToExcel"><i class="fas fa-file-excel"></i></button>
                        <button class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300" title="Export PDF" @click="exportToPDF"><i class="fas fa-file-pdf"></i></button>
                        <button class="bg-blue-50 dark:bg-blue-900 hover:bg-blue-100 dark:hover:bg-blue-800 p-2 rounded text-blue-700 dark:text-blue-300" title="Print" @click="printTable"><i class="fas fa-print"></i></button>
                    </div>
                    <div class="overflow-x-auto">
                        <!-- Inbox Table -->
                                                <!-- Inbox Table -->
                        <table v-if="activeFolder === 'inbox'" class="min-w-full text-base select-none">
                            <thead>
                                <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                                    <th v-if="filteredPaginatedMessages.length > 0" scope="col" class="pl-2"><input type="checkbox" v-model="selectAll" @change="toggleSelectAll"></th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Sender</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Subject</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Message</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr 
                                    v-for="(msg, idx) in filteredPaginatedMessages" 
                                    :key="msg.id" 
                                    class="hover:bg-gray-50 dark:hover:bg-gray-600 transition border-b border-gray-100 dark:border-gray-800"
                                >
                                    <td class="pl-2">
                                        <input 
                                            type="checkbox" 
                                            :value="msg.id" 
                                            v-model="selectedMessages"
                                            class="cursor-pointer"
                                        >
                                    </td>
                                    <td class="pl-8 pr-4 py-3 text-black dark:text-white">{{ msg.sender }}</td>
                                    <td class="pl-8 pr-4 py-3 text-black dark:text-white cursor-pointer hover:text-blue-600 dark:hover:text-blue-400" @click="selectMessage(msg)">
                                        {{ msg.subject }}
                                    </td>
                                    <td class="pl-8 pr-4 py-3 truncate max-w-xs text-black dark:text-white cursor-pointer hover:text-blue-600 dark:hover:text-blue-400" @click="selectMessage(msg)">
                                        {{ stripHtml(msg.message) }}
                                    </td>
                                    <td class="pl-8 pr-4 py-3 whitespace-nowrap text-black dark:text-white">{{ msg.time }}</td>
                                </tr>
                                <tr v-if="filteredPaginatedMessages.length === 0">
                                    <td colspan="6" class="text-center py-16">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fas fa-inbox text-5xl text-gray-300 mb-4"></i>
                                            <span class="text-gray-400 text-lg">No messages found</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <!-- Sent Table -->
                        <!-- Sent Table -->
                        <table v-if="activeFolder === 'sent'" class="min-w-full text-base select-none">
                            <thead>
                                <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                                    <th v-if="filteredPaginatedMessages.length > 0" scope="col" class="pl-2"><input type="checkbox" v-model="selectAll" @change="toggleSelectAll"></th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Receiver</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Subject</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Message</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Time</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(msg, idx) in filteredPaginatedMessages" :key="msg.id" class="hover:bg-gray-50 dark:hover:bg-gray-600 transition border-b border-gray-100 dark:border-gray-800">
                                    <td class="pl-2">
                                        <input 
                                            type="checkbox" 
                                            :value="msg.id" 
                                            v-model="selectedMessages"
                                            class="cursor-pointer"
                                        >
                                    </td>
                                    <td class="pl-8 pr-4 py-3 text-black dark:text-white">{{ msg.sender }}</td>
                                    <td class="pl-8 pr-4 py-3 text-black dark:text-white cursor-pointer hover:text-blue-600 dark:hover:text-blue-400" @click="selectMessage(msg)">
                                        {{ msg.subject }}
                                    </td>
                                    <td class="pl-8 pr-4 py-3 truncate max-w-xs text-black dark:text-white cursor-pointer hover:text-blue-600 dark:hover:text-blue-400" @click="selectMessage(msg)">
                                        {{ stripHtml(msg.message) }}
                                    </td>
                                    <td class="pl-8 pr-4 py-3 whitespace-nowrap text-black dark:text-white">{{ msg.time }}</td>
                                    <td class="pl-8 pr-4 py-3">
                                        <button @click="selectMessage(msg)" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" title="View Message">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredPaginatedMessages.length === 0">
                                    <td colspan="6" class="text-center py-16">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fas fa-inbox text-5xl text-gray-300 mb-4"></i>
                                            <span class="text-gray-400 text-lg">No messages found</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <!-- Important Table -->
                        <!-- Important Table -->
                        <table v-if="activeFolder === 'important'" class="min-w-full text-base select-none">
                            <thead>
                               <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                                    <th v-if="filteredPaginatedMessages.length > 0" scope="col" class="pl-2"><input type="checkbox" v-model="selectAll" @change="toggleSelectAll"></th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">#</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Type</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Sender / Receiver</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Subject</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Message</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Time</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(msg, idx) in filteredPaginatedMessages" :key="msg.id" class="hover:bg-gray-50 dark:hover:bg-gray-600 transition border-b border-gray-100 dark:border-gray-800">
                                    <td class="pl-2">
                                        <input 
                                            type="checkbox" 
                                            :value="msg.id" 
                                            v-model="selectedMessages"
                                            class="cursor-pointer"
                                        >
                                    </td>
                                    <td class="pl-8 pr-4 py-3 text-black dark:text-white">{{ idx + 1 }}</td>
                                    <td class="pl-8 pr-4 py-3 text-black dark:text-white"><i class="fas fa-share"></i></td>
                                    <td class="pl-8 pr-4 py-3 text-black dark:text-white">{{ msg.sender }}</td>
                                    <td class="pl-8 pr-4 py-3 text-black dark:text-white cursor-pointer hover:text-blue-600 dark:hover:text-blue-400" @click="selectMessage(msg)">
                                        {{ msg.subject }}
                                    </td>
                                    <td class="pl-8 pr-4 py-3 truncate max-w-xs text-black dark:text-white cursor-pointer hover:text-blue-600 dark:hover:text-blue-400" @click="selectMessage(msg)">
                                        {{ stripHtml(msg.message) }}
                                    </td>
                                    <td class="pl-8 pr-4 py-3 whitespace-nowrap text-black dark:text-white">{{ msg.time }}</td>
                                    <td class="pl-8 pr-4 py-3">
                                        <button @click="selectMessage(msg)" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" title="View Message">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredPaginatedMessages.length === 0">
                                    <td colspan="8" class="text-center py-16">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fas fa-inbox text-5xl text-gray-300 mb-4"></i>
                                            <span class="text-gray-400 text-lg">No messages found</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <!-- Trash Table -->
                        <!-- Trash Table -->
                        <table v-if="activeFolder === 'trash'" class="min-w-full text-base select-none">
                            <thead>
                                <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                                    <th v-if="filteredPaginatedMessages.length > 0" scope="col" class="pl-2"><input type="checkbox" v-model="selectAll" @change="toggleSelectAll"></th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Receiver</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Subject</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Message</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Time</th>
                                    <th scope="col" class="pl-8 pr-4 py-3 text-left">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(msg, idx) in filteredPaginatedMessages" :key="msg.id" class="hover:bg-gray-50 dark:hover:bg-gray-600 transition border-b border-gray-100 dark:border-gray-800">
                                    <td class="pl-2">
                                        <input 
                                            type="checkbox" 
                                            :value="msg.id" 
                                            v-model="selectedMessages"
                                            class="cursor-pointer"
                                        >
                                    </td>
                                    <td class="pl-8 pr-4 py-3 text-black dark:text-white">{{ msg.sender }}</td>
                                    <td class="pl-8 pr-4 py-3 text-black dark:text-white cursor-pointer hover:text-blue-600 dark:hover:text-blue-400" @click="selectMessage(msg)">
                                        {{ msg.subject }}
                                    </td>
                                    <td class="pl-8 pr-4 py-3 truncate max-w-xs text-black dark:text-white cursor-pointer hover:text-blue-600 dark:hover:text-blue-400" @click="selectMessage(msg)">
                                        {{ stripHtml(msg.message) }}
                                    </td>
                                    <td class="pl-8 pr-4 py-3 whitespace-nowrap text-black dark:text-white">{{ msg.time }}</td>
                                    <td class="pl-8 pr-4 py-3">
                                        <button @click="selectMessage(msg)" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300" title="View Message">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredPaginatedMessages.length === 0">
                                    <td colspan="6" class="text-center py-16">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fas fa-inbox text-5xl text-gray-300 mb-4"></i>
                                            <span class="text-gray-400 text-lg">No messages found</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div v-if="activeFolderMessages.length > 0" class="flex justify-end items-center space-x-1 mt-3 mb-1">
                        <button
                            @click="goToPage(1)"
                            :disabled="currentPage === 1"
                            :class="['w-8 h-8 text-sm flex items-center justify-center rounded-md transition-colors',
                                    currentPage === 1 ? 'text-gray-400 cursor-not-allowed' :
                                    'text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-700 dark:hover:text-blue-300']">
                            <i class="fas fa-angles-left"></i>
                        </button>
                        <button
                            @click="prevPage"
                            :disabled="currentPage === 1"
                            :class="['w-8 h-8 text-sm flex items-center justify-center rounded-md transition-colors',
                                    currentPage === 1 ? 'text-gray-400 cursor-not-allowed' :
                                    'text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-700 dark:hover:text-blue-300']">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="flex items-center space-x-1">
                            <button
                                v-for="page in totalPages"
                                :key="page"
                                @click="goToPage(page)"
                                :class="['w-8 h-8 text-sm flex items-center justify-center rounded-md transition-colors font-medium',
                                        currentPage === page ?
                                        'bg-blue-600 text-white' :
                                        'text-gray-600 dark:text-gray-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-700 dark:hover:text-blue-300']">
                                {{ page }}
                            </button>
                        </div>
                        <button
                            @click="nextPage"
                            :disabled="currentPage === totalPages"
                            :class="['w-8 h-8 text-sm flex items-center justify-center rounded-md transition-colors',
                                    currentPage === totalPages ? 'text-gray-400 cursor-not-allowed' :
                                    'text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-700 dark:hover:text-blue-300']">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        <button
                            @click="goToPage(totalPages)"
                            :disabled="currentPage === totalPages"
                            :class="['w-8 h-8 text-sm flex items-center justify-center rounded-md transition-colors',
                                    currentPage === totalPages ? 'text-gray-400 cursor-not-allowed' :
                                    'text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-700 dark:hover:text-blue-300']">
                            <i class="fas fa-angles-right"></i>
                        </button>
                    </div>
                </div>
            </div>
            <!-- Gmail-style Message View Modal -->
            <div v-if="viewingMessage" class="fixed inset-0 bg-black/50 dark:bg-black/70 flex items-center justify-center z-[1000] p-4" role="dialog" aria-modal="true">
                <div class="bg-white dark:bg-gray-900 rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-gray-200 dark:border-gray-700">
                    
                    <!-- Header with Gmail-like actions -->
                    <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <div class="flex items-center space-x-3">
                        <button @click="viewingMessage = null" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300">
                        <i class="fas fa-arrow-left"></i>
                        </button>
                        <button @click="moveToTrash(viewingMessage)" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300" title="Delete">
                        <i class="fas fa-trash"></i>
                        </button>
                        <button 
                        @click="toggleImportant(viewingMessage)"
                        class="p-2 rounded-full dark:text-white hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                        :class="{'text-yellow-500': viewingMessage.folder === 'important'}"
                        :title="viewingMessage.folder === 'important' ? 'Unmark Important' : 'Mark Important'"
                        >
                        <i class="fas fa-star"></i>
                        </button>
                    </div>
                    <button @click="viewingMessage = null" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300">
                        <i class="fas fa-times"></i>
                    </button>
                    </div>
                    
                    <!-- Message Header -->
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-3">{{ viewingMessage.subject }}</h2>
                    
                    <div class="flex items-start justify-between">
                        <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-300 mr-3">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                            {{ activeFolder === 'inbox' ? viewingMessage.sender_email : viewingMessage.receiver_email }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                            to {{ activeFolder === 'inbox' ? 'me' : viewingMessage.receiver_email }}
                            </div>
                        </div>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ formatDateTime(viewingMessage.created_at) }}
                        </div>
                    </div>
                    </div>
                    
                    <!-- Message Body -->
                    <div class="flex-1 p-6 overflow-y-auto">
                    <div class="prose max-w-none dark:prose-invert prose-p:text-gray-800 dark:prose-p:text-gray-200 prose-li:text-gray-800 dark:prose-li:text-gray-200 text-black dark:text-white">
                        <div class="whitespace-pre-wrap" v-html="sanitizeHtml(viewingMessage.message)"></div>
                    </div>
                    
                    <!-- Attachments (if any) -->
                    <div v-if="viewingMessage.attachments && viewingMessage.attachments.length" class="mt-6">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Attachments</h4>
                        <div class="flex flex-wrap gap-3">
                        <div v-for="attachment in viewingMessage.attachments" :key="attachment.id" class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <div class="flex items-center">
                            <i class="fas fa-paperclip text-gray-500 dark:text-gray-400 mr-2"></i>
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ attachment.name }}</span>
                            </div>
                        </div>
                        </div>
                    </div>
                    </div>
                    
                    <!-- Footer with Reply Options -->
                    <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <div class="flex justify-between items-center">
                        <button 
                        @click="startReply(viewingMessage)"
                        class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors flex items-center"
                        >
                        <i class="fas fa-reply mr-2"></i>
                        Reply
                        </button>
                        
                        <div class="flex items-center space-x-2">
                        <button 
                            @click="startReply(viewingMessage, true)"
                            class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        >
                            <i class="fas fa-reply-all mr-1"></i> Reply All
                        </button>
                        <button 
                            @click="forwardMessage(viewingMessage)"
                            class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                        >
                            <i class="fas fa-share mr-1"></i> Forward
                        </button>
                        </div>
                    </div>
                    </div>
                </div>
                </div>
            </div>
        </div>

@endverbatim
@endsection
