@extends('layouts.admin')
@section('content')
@verbatim
<script>
    window.__notificationTypes = <?= json_encode($notificationTypes) ?>;
    window.__notificationPreferences = <?= json_encode($preferences) ?>;
    window.__isSuperadmin = <?= json_encode($isSuperadmin) ?>;
    window.__twoFactorEnabled = <?= json_encode($twoFactorEnabled) ?>;
    window.__passwordPolicy = <?= json_encode($passwordPolicy) ?>;
    <?php if ($isSuperadmin): ?>
    window.__heroSettings = <?= json_encode($heroSettings) ?>;
    window.__newAccountEmailEnabled = <?= json_encode($newAccountEmailEnabled) ?>;
    <?php endif; ?>
</script>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200 mb-2">Settings</h1>
    <p class="text-gray-600 dark:text-gray-400">Manage your account and notification preferences</p>
</div>

<div class="flex gap-2 mb-6 border-b border-gray-200 dark:border-gray-600 overflow-x-auto">
    <button @click="activeTab = 'account'" :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap', activeTab === 'account' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200']">
        <i class="fas fa-user-lock mr-2"></i>Account
    </button>
    <button @click="activeTab = 'security'" :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap', activeTab === 'security' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200']">
        <i class="fas fa-shield-alt mr-2"></i>Security
    </button>
    <button @click="activeTab = 'notifications'" :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap', activeTab === 'notifications' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200']">
        <i class="fas fa-bell mr-2"></i>Notifications
    </button>
    <?php if ($isSuperadmin): ?>
    <button @click="activeTab = 'reminders'" :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap', activeTab === 'reminders' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200']">
        <i class="fas fa-clock mr-2"></i>Reminder Settings
    </button>
    <button @click="activeTab = 'systeminfo'" :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap', activeTab === 'systeminfo' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200']">
        <i class="fas fa-server mr-2"></i>System Info
    </button>
    <button @click="activeTab = 'landing'" :class="['px-4 py-2 text-sm font-medium border-b-2 transition-colors whitespace-nowrap', activeTab === 'landing' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200']">
        <i class="fas fa-image mr-2"></i>Landing Page
    </button>
    <?php endif; ?>
</div>

<!-- Account tab -->
<div v-if="activeTab === 'account'" class="max-w-lg">
    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Change Password</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">{{ passwordRequirementsText }}</p>
        <form @submit.prevent="changePassword" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Current Password</label>
                <input type="password" v-model="passwordForm.current_password" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password</label>
                <input type="password" v-model="passwordForm.new_password" required :minlength="passwordPolicy.min_length" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm New Password</label>
                <input type="password" v-model="passwordForm.confirm_password" required :minlength="passwordPolicy.min_length" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
            </div>
            <div class="flex justify-end">
                <button type="submit" :disabled="passwordSaving" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ passwordSaving ? 'Saving...' : 'Update Password' }}
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Security tab -->
<div v-if="activeTab === 'security'" class="space-y-6 max-w-lg">
    <?php if ($isSuperadmin): ?>
    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Password Requirements</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Configure the password policy enforced for every admin and superadmin account.</p>
        <form @submit.prevent="savePasswordPolicy" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Minimum Length</label>
                <input type="number" min="8" max="64" v-model.number="passwordPolicy.min_length" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
            </div>
            <div class="space-y-2">
                <label class="flex items-center">
                    <input type="checkbox" v-model="passwordPolicy.require_uppercase" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 checked:bg-blue-600 checked:border-blue-600">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Require an uppercase letter</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" v-model="passwordPolicy.require_number" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 checked:bg-blue-600 checked:border-blue-600">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Require a number</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" v-model="passwordPolicy.require_symbol" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 checked:bg-blue-600 checked:border-blue-600">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Require a symbol</span>
                </label>
            </div>
            <div class="flex justify-end">
                <button type="submit" :disabled="passwordPolicySaving" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ passwordPolicySaving ? 'Saving...' : 'Save Requirements' }}
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($isSuperadmin): ?>
    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">New Account Emails</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">When enabled, a credentials email is automatically sent to every new alumni, employer, or admin account created from the Accounts page. Turn this off if logins are handed out another way.</p>
        <label class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-600 rounded cursor-pointer">
            <span class="text-sm text-gray-700 dark:text-gray-300">
                <i class="fas fa-envelope mr-2 text-blue-500"></i>
                {{ newAccountEmailEnabled ? 'Enabled' : 'Disabled' }}
            </span>
            <input type="checkbox" :checked="newAccountEmailEnabled" @change="toggleNewAccountEmail" :disabled="newAccountEmailSaving" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 checked:bg-blue-600 checked:border-blue-600">
        </label>
    </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Two-Factor Authentication</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">When enabled, every login sends a one-time verification code to your email. Accounts are also prompted for 2FA automatically after 30 days of inactivity or on first login.</p>
        <label class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-600 rounded cursor-pointer">
            <span class="text-sm text-gray-700 dark:text-gray-300">
                <i class="fas fa-shield-alt mr-2 text-blue-500"></i>
                {{ twoFactorEnabled ? 'Enabled' : 'Disabled' }}
            </span>
            <input type="checkbox" :checked="twoFactorEnabled" @change="toggleTwoFactor" :disabled="twoFactorSaving" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 checked:bg-blue-600 checked:border-blue-600">
        </label>
    </div>
</div>

<!-- Notifications tab -->
<div v-if="activeTab === 'notifications'" class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 max-w-lg">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-2">Notification Preferences</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Choose which in-app notifications you want to receive.</p>
    <div class="space-y-4">
        <label v-for="(label, type) in notificationTypes" :key="type" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-600 rounded">
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ label }}</span>
            <input type="checkbox" v-model="notificationPrefs[type]" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 checked:bg-blue-600 checked:border-blue-600">
        </label>
    </div>
    <div class="flex justify-end mt-6">
        <button @click="saveNotificationPreferences" :disabled="notifSaving" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
            {{ notifSaving ? 'Saving...' : 'Save Preferences' }}
        </button>
    </div>
</div>

<?php if ($isSuperadmin): ?>
<!-- Reminder Settings tab -->
<div v-if="activeTab === 'reminders'">
    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6">Reminder System Settings</h2>
        <form @submit.prevent="saveReminderSettings" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Business Hours Start</label>
                    <select v-model="reminderSettings.business_hours_start" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
                        <option v-for="i in 24" :key="i-1" :value="(i-1).toString()">{{ String(i-1).padStart(2, '0') }}:00</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Business Hours End</label>
                    <select v-model="reminderSettings.business_hours_end" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
                        <option v-for="i in 24" :key="i-1" :value="(i-1).toString()">{{ String(i-1).padStart(2, '0') }}:00</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Timezone</label>
                    <select v-model="reminderSettings.timezone" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
                        <option value="Asia/Manila">Asia/Manila (GMT+8)</option>
                        <option value="UTC">UTC (GMT+0)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Send Every (minutes)</label>
                    <select v-model="reminderSettings.frequency_minutes" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
                        <option value="1">1 minute</option>
                        <option value="5">5 minutes</option>
                        <option value="15">15 minutes</option>
                        <option value="30">30 minutes</option>
                        <option value="60">1 hour</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Max Reminders Per Day</label>
                    <select v-model="reminderSettings.max_reminders_per_day" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
                        <option v-for="i in 10" :key="i" :value="i.toString()">{{ i }} reminder{{ i > 1 ? 's' : '' }}</option>
                    </select>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200">Notification Methods</h3>
                <div class="flex items-center space-x-4">
                    <label class="flex items-center">
                        <input type="checkbox" v-model="reminderSettings.email_enabled" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 checked:bg-blue-600 checked:border-blue-600">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Email Notifications</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" v-model="reminderSettings.sms_enabled" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 checked:bg-blue-600 checked:border-blue-600">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">SMS Notifications</span>
                    </label>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200">Message Templates</h3>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Subject</label>
                    <input type="text" v-model="reminderSettings.email_subject" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Message</label>
                    <textarea v-model="reminderSettings.email_message" rows="4" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">SMS Message</label>
                    <textarea v-model="reminderSettings.sms_message" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200"></textarea>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" :disabled="reminderSaving" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ reminderSaving ? 'Saving...' : 'Save Settings' }}
                </button>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4">Recent Statistics</h2>
            <?php if (!empty($recentStats)): ?>
                <div class="space-y-3">
                    <?php foreach ($recentStats as $stat): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-600 rounded">
                            <div>
                                <div class="font-medium text-gray-800 dark:text-gray-200"><?= date('M j, Y', strtotime($stat['date'])) ?></div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <?= (int) $stat['emails_sent'] ?> emails, <?= (int) $stat['sms_sent'] ?> SMS sent
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-green-600"><?= (int) $stat['total_sent'] ?> total</div>
                                <div class="text-xs text-red-600"><?= (int) $stat['total_failed'] ?> failed</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No statistics available</p>
            <?php endif; ?>
        </div>

        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4">Recent Activity</h2>
            <?php if (!empty($recentLogs)): ?>
                <div class="space-y-3 max-h-64 overflow-y-auto">
                    <?php foreach ($recentLogs as $log): ?>
                        <div class="flex items-start space-x-3 p-3 bg-gray-50 dark:bg-gray-600 rounded">
                            <div class="flex-shrink-0">
                                <?php if ($log['type'] === 'email'): ?>
                                    <i class="fas fa-envelope text-blue-500"></i>
                                <?php else: ?>
                                    <i class="fas fa-sms text-green-500"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                    <?= ucfirst($log['type']) ?> to <?= htmlspecialchars(substr($log['recipient'], 0, 20).(strlen($log['recipient']) > 20 ? '...' : '')) ?>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">
                                    <?= date('M j, Y g:i A', strtotime($log['sent_at'])) ?>
                                </div>
                                <div class="text-xs <?= $log['status'] === 'sent' ? 'text-green-600' : 'text-red-600' ?>">
                                    <?= ucfirst($log['status']) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No recent activity</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- System Info tab -->
<div v-if="activeTab === 'systeminfo'">
    <div v-if="systemInfoLoading" class="flex justify-center items-center py-12">
        <i class="fas fa-spinner fa-spin text-2xl text-gray-500"></i>
        <span class="ml-2 text-gray-500">Loading...</span>
    </div>

    <template v-else>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 flex items-center">
                <div class="p-3 rounded-full bg-cyan-100 dark:bg-cyan-900">
                    <i class="fas fa-user-graduate text-cyan-600 dark:text-cyan-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Alumni</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ userCounts.alumni || 0 }}</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 flex items-center">
                <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900">
                    <i class="fas fa-building text-purple-600 dark:text-purple-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Employers</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ userCounts.employer || 0 }}</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 flex items-center">
                <div class="p-3 rounded-full bg-red-100 dark:bg-red-900">
                    <i class="fas fa-user-shield text-red-600 dark:text-red-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Admins</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ userCounts.admin || 0 }}</p>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 flex items-center">
                <div class="p-3 rounded-full bg-amber-100 dark:bg-amber-900">
                    <i class="fas fa-crown text-amber-600 dark:text-amber-400 text-xl"></i>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Superadmins</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ userCounts.superadmin || 0 }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
                <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Pending Alumni</h3>
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ pendingCounts.alumni || 0 }}</p>
            </div>
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
                <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Pending Employers</h3>
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ pendingCounts.employer || 0 }}</p>
            </div>
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
                <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">Database Size</h3>
                <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ formatBytes(databaseSizeBytes) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Storage Usage</h2>
                <div class="space-y-2">
                    <div v-for="(bytes, folder) in uploadsSizeBytes" :key="folder" class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400 capitalize">{{ folder.replace(/_/g, ' ') }}</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ formatBytes(bytes) }}</span>
                    </div>
                    <div v-if="!Object.keys(uploadsSizeBytes).length" class="text-sm text-gray-400">No uploads yet</div>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Server Info</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">PHP Version</span><span class="font-medium text-gray-800 dark:text-gray-200">{{ serverInfo.php_version }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">MySQL Version</span><span class="font-medium text-gray-800 dark:text-gray-200">{{ serverInfo.mysql_version }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Server Software</span><span class="font-medium text-gray-800 dark:text-gray-200 text-right">{{ serverInfo.server_software }}</span></div>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Recent Registrations</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-left">
                            <th scope="col" class="px-4 py-2">Name</th>
                            <th scope="col" class="px-4 py-2">Email</th>
                            <th scope="col" class="px-4 py-2">Role</th>
                            <th scope="col" class="px-4 py-2">Status</th>
                            <th scope="col" class="px-4 py-2">Registered</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-600">
                        <tr v-for="row in recentRegistrations" :key="row.user_id">
                            <td class="px-4 py-2">{{ row.display_name || '—' }}</td>
                            <td class="px-4 py-2">{{ row.email }}</td>
                            <td class="px-4 py-2 capitalize">{{ row.user_role }}</td>
                            <td class="px-4 py-2">{{ row.status }}</td>
                            <td class="px-4 py-2">{{ formatDate(row.created_at) }}</td>
                        </tr>
                        <tr v-if="!recentRegistrations.length">
                            <td colspan="5" class="px-4 py-6 text-center text-gray-400">No recent registrations</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </template>
</div>

<!-- Landing Page tab -->
<div v-if="activeTab === 'landing'" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6">Hero Content</h2>
        <form @submit.prevent="saveLanding" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Badge Text</label>
                <input type="text" v-model="landingForm.landing_hero_badge" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Headline</label>
                <input type="text" v-model="landingForm.landing_hero_headline" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Subtext</label>
                <textarea v-model="landingForm.landing_hero_subtext" required rows="4" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hero Background Image</label>
                <input type="file" accept="image/*" @change="onLandingImageChange" class="w-full text-sm text-gray-700 dark:text-gray-300">
                <p class="text-xs text-gray-400 mt-1">Leave empty to keep the current image.</p>
            </div>
            <div class="flex justify-end">
                <button type="submit" :disabled="landingSaving" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ landingSaving ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4">Preview</h2>
        <div class="relative rounded-lg overflow-hidden bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-900 p-8 min-h-[280px] flex flex-col justify-center"
             :style="landingImagePreview ? { backgroundImage: 'linear-gradient(rgba(30,64,175,0.75),rgba(79,70,229,0.75)), url(' + landingImagePreview + ')', backgroundSize: 'cover', backgroundPosition: 'center' } : {}">
            <div class="inline-flex items-center px-3 py-1.5 bg-white/10 border border-white/20 rounded-full text-xs font-medium text-white/90 w-fit mb-4">
                <i class="fas fa-star text-yellow-400 mr-2"></i>{{ landingForm.landing_hero_badge }}
            </div>
            <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-yellow-200 mb-3">{{ landingForm.landing_hero_headline }}</h1>
            <p class="text-blue-100 text-sm">{{ landingForm.landing_hero_subtext }}</p>
        </div>
    </div>
</div>
<?php endif; ?>

@endverbatim
@endsection
