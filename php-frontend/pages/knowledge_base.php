<?php
/**
 * Knowledge Base Page - PHP Version
 */
?>
<div class="space-y-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <h1 class="text-2xl font-bold text-sn-dark">Knowledge Base</h1>
        <div class="relative">
            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"></i>
            <input type="text" placeholder="Search articles..." class="pl-9 pr-4 py-2 border border-border rounded-md text-sm w-64" />
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="card p-6">
            <h3 class="font-semibold text-lg flex items-center gap-2">
                <i data-lucide="monitor" class="w-5 h-5 text-blue-600"></i>
                Getting Started
            </h3>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="#" class="text-blue-600 hover:underline">How to create a ticket</a></li>
                <li><a href="#" class="text-blue-600 hover:underline">Understanding ticket status</a></li>
                <li><a href="#" class="text-blue-600 hover:underline">SLA guidelines</a></li>
            </ul>
        </div>

        <div class="card p-6">
            <h3 class="font-semibold text-lg flex items-center gap-2">
                <i data-lucide="settings" class="w-5 h-5 text-green-600"></i>
                IT Support
            </h3>
            <ul class="mt-4 space-y-2 text-sm">
                <li><a href="#" class="text-blue-600 hover:underline">Password reset guide</a></li>
                <li><a href="#" class="text-blue-600 hover:underline">VPN connection setup</a></li>
                <li><a href="#" class="text-blue-600 hover:underline">Printer troubleshooting</a></li>
            </ul>
        </div>
    </div>
</div>

<script>lucide.createIcons();</script>
