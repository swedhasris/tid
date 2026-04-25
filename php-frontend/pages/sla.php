<?php
/**
 * SLA Management Page - PHP Version
 * SLA policy management for administrators
 * 
 * @var array $policies List of SLA policies
 */

if (!isAdmin()) {
    setFlash('error', 'Access denied');
    redirect('/dashboard');
}
?>
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <h1 class="text-2xl font-bold text-sn-dark">SLA Management</h1>
    </div>

    <!-- SLA Policies Table -->
    <div class="card overflow-hidden">
        <div class="p-4 border-b border-border bg-muted/30">
            <span class="text-sm font-bold">SLA Policies</span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-muted/50 border-b border-border">
                        <th class="data-table-header p-3">Name</th>
                        <th class="data-table-header p-3">Priority</th>
                        <th class="data-table-header p-3">Response Time</th>
                        <th class="data-table-header p-3">Resolution Time</th>
                        <th class="data-table-header p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($policies)): ?>
                    <tr>
                        <td colspan="5" class="p-8 text-center text-muted-foreground">
                            No SLA policies configured.
                        </td>
                    </tr>
                    <?php else: 
                        foreach ($policies as $policy): 
                    ?>
                    <tr class="border-b border-border hover:bg-muted/10">
                        <td class="p-3 font-medium"><?= htmlspecialchars($policy['name'] ?? '') ?></td>
                        <td class="p-3"><?= htmlspecialchars($policy['priority'] ?? '') ?></td>
                        <td class="p-3"><?= ($policy['responseTimeHours'] ?? 0) ?> hours</td>
                        <td class="p-3"><?= ($policy['resolutionTimeHours'] ?? 0) ?> hours</td>
                        <td class="p-3">
                            <span class="badge <?= ($policy['isActive'] ?? false) ? 'badge-low' : 'badge-high' ?>">
                                <?= ($policy['isActive'] ?? false) ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>lucide.createIcons();</script>
