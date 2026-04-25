<?php
/**
 * Users Page - PHP Version
 * User management for administrators
 * 
 * @var array $users List of users
 */

if (!isAdmin()) {
    setFlash('error', 'Access denied');
    redirect('/dashboard');
}
?>
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <div class="flex items-center gap-2">
            <h1 class="text-2xl font-bold text-sn-dark">User Management</h1>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card overflow-hidden">
        <div class="p-4 border-b border-border flex items-center justify-between bg-muted/30">
            <span class="text-sm font-bold">Users <span class="ml-1 px-2 py-0.5 bg-muted rounded text-xs"><?= count($users) ?></span></span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-muted/50 border-b border-border">
                        <th class="data-table-header p-3">Name</th>
                        <th class="data-table-header p-3">Email</th>
                        <th class="data-table-header p-3">Role</th>
                        <th class="data-table-header p-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr class="border-b border-border hover:bg-muted/10">
                        <td class="p-3 font-medium"><?= htmlspecialchars($user['name'] ?? 'Unknown') ?></td>
                        <td class="p-3 text-sm"><?= htmlspecialchars($user['email'] ?? '') ?></td>
                        <td class="p-3">
                            <span class="badge <?= ($user['role'] ?? '') === 'admin' ? 'badge-high' : (($user['role'] ?? '') === 'agent' ? 'badge-moderate' : 'badge-low') ?>">
                                <?= ucfirst($user['role'] ?? 'user') ?>
                            </span>
                        </td>
                        <td class="p-3 text-sm text-muted-foreground">
                            <?= isset($user['createdAt']) ? date('Y-m-d', strtotime(is_string($user['createdAt']) ? $user['createdAt'] : '')) : 'N/A' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>lucide.createIcons();</script>
