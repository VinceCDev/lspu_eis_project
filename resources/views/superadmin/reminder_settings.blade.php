@extends('layouts.admin')
@section('content')
@verbatim
<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200 mb-2">Reminder System Settings</h1>
    <p class="text-gray-600 dark:text-gray-400">Configure automated reminder notifications for alumni</p>
</div>

<div class="bg-white dark:bg-gray-700 rounded-lg shadow-md p-6 mb-8">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-6">General Settings</h2>
    <form @submit.prevent="saveSettings" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Business Hours Start</label>
                <select v-model="settings.business_hours_start" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
                    <option v-for="i in 24" :key="i-1" :value="(i-1).toString()">
                        {{ String(i-1).padStart(2, '0') }}:00
                    </option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Business Hours End</label>
                <select v-model="settings.business_hours_end" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
                    <option v-for="i in 24" :key="i-1" :value="(i-1).toString()">
                        {{ String(i-1).padStart(2, '0') }}:00
                    </option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Timezone</label>
                <select v-model="settings.timezone" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
                    <option value="Asia/Manila">Asia/Manila (GMT+8)</option>
                    <option value="UTC">UTC (GMT+0)</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Send Every (minutes)</label>
                <select v-model="settings.frequency_minutes" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
                    <option value="1">1 minute</option>
                    <option value="5">5 minutes</option>
                    <option value="15">15 minutes</option>
                    <option value="30">30 minutes</option>
                    <option value="60">1 hour</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Max Reminders Per Day</label>
                <select v-model="settings.max_reminders_per_day" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
                    <option v-for="i in 10" :key="i" :value="i.toString()">
                        {{ i }} reminder{{ i > 1 ? 's' : '' }}
                    </option>
                </select>
            </div>
        </div>

        <div class="space-y-4">
            <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200">Notification Methods</h3>
            <div class="flex items-center space-x-4">
                <label class="flex items-center">
                    <input type="checkbox" v-model="settings.email_enabled" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 checked:bg-blue-600 checked:border-blue-600">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Email Notifications</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" v-model="settings.sms_enabled" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 checked:bg-blue-600 checked:border-blue-600">
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">SMS Notifications</span>
                </label>
            </div>
        </div>

        <div class="space-y-4">
            <h3 class="text-lg font-medium text-gray-800 dark:text-gray-200">Message Templates</h3>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Subject</label>
                <input type="text" v-model="settings.email_subject" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email Message</label>
                <textarea v-model="settings.email_message" rows="4" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">SMS Message</label>
                <textarea v-model="settings.sms_message" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-600 dark:text-gray-200"></textarea>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition-colors">
                Save Settings
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

@endverbatim
@endsection
