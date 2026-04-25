<?php
/**
 * My Approvals Page - PHP Version
 */
?>
<div class="space-y-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <h1 class="text-2xl font-bold text-sn-dark">My Approvals & Requests</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="card p-6">
            <h3 class="font-semibold text-lg">Pending Approvals</h3>
            <p class="text-muted-foreground text-sm mt-2">No pending approvals</p>
        </div>

        <div class="card p-6">
            <h3 class="font-semibold text-lg">My Requests</h3>
            <p class="text-muted-foreground text-sm mt-2">View your submitted requests</p>
            <a href="<?= BASE_URL ?>/?page=tickets" class="text-blue-600 hover:underline text-sm mt-4 inline-block">View tickets</a>
        </div>
    </div>
</div>

<script>lucide.createIcons();</script>
