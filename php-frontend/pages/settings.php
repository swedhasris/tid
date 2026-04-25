<?php
/**
 * Settings Page - PHP Version
 * System configuration for administrators
 */

if (!isAdmin()) {
    setFlash('error', 'Access denied');
    redirect('/dashboard');
}
?>
<div class="space-y-6 max-w-4xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <h1 class="text-2xl font-bold text-sn-dark">System Settings</h1>
    </div>

    <!-- Settings Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="card p-6">
            <h2 class="font-semibold text-lg mb-4">General Settings</h2>
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium">Application Name</label>
                    <input type="text" value="Connect IT" class="mt-1 w-full p-2 border border-border rounded text-sm" disabled />
                </div>
                <div>
                    <label class="text-sm font-medium">Support Email</label>
                    <input type="email" value="support@connectit.local" class="mt-1 w-full p-2 border border-border rounded text-sm" />
                </div>
            </div>
        </div>

        <div class="card p-6">
            <h2 class="font-semibold text-lg mb-4">Ticket Settings</h2>
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium">Auto-assignment</label>
                    <select class="mt-1 w-full p-2 border border-border rounded text-sm">
                        <option>Enabled</option>
                        <option>Disabled</option>
                    </select>
                </div>
                <div>
                    <label class="text-sm font-medium">Default Priority</label>
                    <select class="mt-1 w-full p-2 border border-border rounded text-sm">
                        <option>3 - Moderate</option>
                        <option>4 - Low</option>
                        <option>2 - High</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button class="btn btn-primary" onclick="alert('Settings saved (demo)')">
            <i data-lucide="save" class="w-4 h-4 mr-2"></i>
            Save Settings
        </button>
    </div>
</div>

<script>lucide.createIcons();</script>
