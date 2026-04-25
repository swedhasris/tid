<?php
/**
 * Service Catalog Page - PHP Version
 */
?>
<div class="space-y-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <h1 class="text-2xl font-bold text-sn-dark">Service Catalog</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="card p-6 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center mb-4">
                <i data-lucide="server" class="w-6 h-6 text-blue-600"></i>
            </div>
            <h3 class="font-semibold text-lg">Hardware Request</h3>
            <p class="text-sm text-muted-foreground mt-2">Request new hardware equipment</p>
            <a href="<?= BASE_URL ?>/?page=tickets&action=new" class="btn btn-outline mt-4 w-full">Request</a>
        </div>

        <div class="card p-6 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center mb-4">
                <i data-lucide="download" class="w-6 h-6 text-green-600"></i>
            </div>
            <h3 class="font-semibold text-lg">Software Install</h3>
            <p class="text-sm text-muted-foreground mt-2">Request software installation</p>
            <a href="<?= BASE_URL ?>/?page=tickets&action=new" class="btn btn-outline mt-4 w-full">Request</a>
        </div>

        <div class="card p-6 hover:shadow-md transition-shadow">
            <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center mb-4">
                <i data-lucide="key" class="w-6 h-6 text-purple-600"></i>
            </div>
            <h3 class="font-semibold text-lg">Access Request</h3>
            <p class="text-sm text-muted-foreground mt-2">Request system access</p>
            <a href="<?= BASE_URL ?>/?page=tickets&action=new" class="btn btn-outline mt-4 w-full">Request</a>
        </div>
    </div>
</div>

<script>lucide.createIcons();</script>
