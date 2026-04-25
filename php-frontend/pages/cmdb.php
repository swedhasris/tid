<?php
/**
 * CMDB Assets Page - PHP Version
 */
?>
<div class="space-y-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <h1 class="text-2xl font-bold text-sn-dark">CMDB Assets</h1>
        <button class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
            Add Asset
        </button>
    </div>

    <div class="card p-8 text-center">
        <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="database" class="w-8 h-8 text-blue-600"></i>
        </div>
        <h3 class="text-lg font-medium">Configuration Management Database</h3>
        <p class="text-muted-foreground mt-2">Track IT assets and configurations</p>
    </div>
</div>

<script>lucide.createIcons();</script>
