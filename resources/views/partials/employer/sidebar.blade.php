<?php
/** @var string $active current page slug, e.g. 'employer_dashboard' */
$active = $active ?? '';
$linkClass = function (string $page) use ($active) {
    return $active === $page
        ? 'flex items-center px-6 py-3 mx-2 rounded-lg bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 hover:bg-blue-500/20 dark:hover:bg-blue-500/30 transition-colors duration-200 border-l-4 border-blue-500 dark:border-blue-400'
        : 'flex items-center px-6 py-3 mx-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors duration-200';
};
?>
<div v-if="sidebarActive" class="fixed top-0 left-0 bottom-0 w-[280px] bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-slate-200 shadow-xl z-50 transition-all duration-300 ease-in-out transform md:translate-x-0" :class="{'-translate-x-full': !sidebarActive && isMobile}">
    <div class="bg-white dark:bg-slate-700 shadow-sm h-[70px] border-b border-slate-200 dark:border-gray-700">
        <div class="flex items-center h-full px-6 mx-auto max-w-7xl">
            <a href="employer_dashboard" class="flex items-center">
                <img src="<?= asset('assets/images/logo.png') ?>" alt="Logo" class="w-12 h-12 mr-4 rounded-lg bg-white p-1 shadow-md ring-1 ring-slate-200/50 dark:bg-slate-700 dark:ring-slate-600/50">
                <span class="text-2xl font-bold text-slate-800 dark:text-slate-100 tracking-tight">LSPU EIS</span>
            </a>
            <button class="md:hidden ml-auto p-2 rounded-full hover:bg-slate-100/50 dark:hover:bg-slate-700/50 transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-blue-500/30" @click="toggleSidebar">
                <i class="fas fa-times text-xl text-slate-600 dark:text-slate-300"></i>
            </button>
        </div>
    </div>

    <div class="overflow-y-auto pt-4 pb-20 h-[calc(100%-64px)] scrollbar-thin scrollbar-thumb-slate-300 scrollbar-track-slate-100 dark:scrollbar-thumb-slate-600 dark:scrollbar-track-slate-800/50">
        <div class="px-6 py-2 mb-2">
            <span class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400 tracking-wider">Main</span>
        </div>

        <a href="employer_dashboard" class="<?= $linkClass('employer_dashboard') ?>" @click="handleNavClick">
            <i class="fas fa-tachometer-alt w-5 mr-3 text-center text-blue-500 dark:text-blue-400"></i>
            <span class="font-medium">Dashboard</span>
        </a>

        <a href="employer_matchboard" class="<?= $linkClass('employer_matchboard') ?>" @click="handleNavClick">
            <i class="fas fa-handshake w-5 mr-3 text-center text-amber-500 dark:text-amber-400"></i>
            <span class="font-medium">Matchboard</span>
        </a>

        <a href="employer_jobposting" class="<?= $linkClass('employer_jobposting') ?>" @click="handleNavClick">
            <i class="fas fa-briefcase w-5 mr-3 text-center text-emerald-500 dark:text-emerald-400"></i>
            <span class="font-medium">Jobs</span>
        </a>

        <a href="employer_applicants" class="<?= $linkClass('employer_applicants') ?>" @click="handleNavClick">
            <i class="fas fa-users w-5 mr-3 text-center text-amber-500 dark:text-amber-400"></i>
            <span class="font-medium">Applicants</span>
        </a>

        <a href="employer_interview" class="<?= $linkClass('employer_interview') ?>" @click="handleNavClick">
            <i class="fas fa-calendar-alt w-5 mr-3 text-center text-violet-500 dark:text-violet-400"></i>
            <span class="font-medium">Interviews</span>
        </a>

        <a href="employer_onboarding" class="<?= $linkClass('employer_onboarding') ?>" @click="handleNavClick">
            <i class="fas fa-user-check w-5 mr-3 text-center text-blue-500 dark:text-blue-400"></i>
            <span class="font-medium">Onboarding</span>
        </a>

        <a href="employer_messages" class="<?= $linkClass('employer_messages') ?>" @click="handleNavClick">
            <i class="fas fa-envelope w-5 mr-3 text-center text-pink-500 dark:text-pink-400"></i>
            <span class="font-medium">Messages</span>
        </a>

        <a href="employer_settings" class="<?= $linkClass('employer_settings') ?>" @click="handleNavClick">
            <i class="fas fa-cog w-5 mr-3 text-center text-slate-500 dark:text-slate-400"></i>
            <span class="font-medium">Settings</span>
        </a>
    </div>
</div>
<div v-if="sidebarActive && isMobile" class="fixed inset-0 bg-black bg-opacity-40 z-40 md:hidden" @click="toggleSidebar"></div>
