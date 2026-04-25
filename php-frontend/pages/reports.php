<?php
/**
 * Reports Page - PHP Version
 * Analytics and reporting
 * 
 * @var array $tickets List of tickets for analysis
 */

// Calculate statistics
$total = count($tickets);
$byStatus = [];
$byPriority = [];
$byCategory = [];

foreach ($tickets as $ticket) {
    $status = $ticket['status'] ?? 'New';
    $priority = $ticket['priority'] ?? '4 - Low';
    $category = $ticket['category'] ?? 'Uncategorized';
    
    $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
    $byPriority[$priority] = ($byPriority[$priority] ?? 0) + 1;
    $byCategory[$category] = ($byCategory[$category] ?? 0) + 1;
}
?>
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <h1 class="text-2xl font-bold text-sn-dark">Reports & Analytics</h1>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-5">
            <div class="text-[11px] font-bold text-muted-foreground uppercase">Total Tickets</div>
            <div class="text-3xl font-light mt-2"><?= $total ?></div>
        </div>
        <div class="card p-5">
            <div class="text-[11px] font-bold text-muted-foreground uppercase">Open Tickets</div>
            <div class="text-3xl font-light mt-2">
                <?= array_sum(array_filter($byStatus, fn($k) => !in_array($k, ['Resolved', 'Closed']), ARRAY_FILTER_USE_KEY)) ?>
            </div>
        </div>
        <div class="card p-5">
            <div class="text-[11px] font-bold text-muted-foreground uppercase">Resolved</div>
            <div class="text-3xl font-light mt-2">
                <?= ($byStatus['Resolved'] ?? 0) + ($byStatus['Closed'] ?? 0) ?>
            </div>
        </div>
        <div class="card p-5">
            <div class="text-[11px] font-bold text-muted-foreground uppercase">Categories</div>
            <div class="text-3xl font-light mt-2"><?= count($byCategory) ?></div>
        </div>
    </div>

    <!-- Distribution Tables -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="card p-6">
            <h2 class="font-semibold text-lg mb-4">By Status</h2>
            <table class="w-full">
                <tbody>
                    <?php foreach ($byStatus as $status => $count): ?>
                    <tr class="border-b border-border">
                        <td class="py-2"><?= htmlspecialchars($status) ?></td>
                        <td class="py-2 text-right font-bold"><?= $count ?></td>
                        <td class="py-2 text-right text-muted-foreground">
                            <?= round(($count / $total) * 100, 1) ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card p-6">
            <h2 class="font-semibold text-lg mb-4">By Priority</h2>
            <table class="w-full">
                <tbody>
                    <?php foreach ($byPriority as $priority => $count): ?>
                    <tr class="border-b border-border">
                        <td class="py-2"><?= htmlspecialchars($priority) ?></td>
                        <td class="py-2 text-right font-bold"><?= $count ?></td>
                        <td class="py-2 text-right text-muted-foreground">
                            <?= round(($count / $total) * 100, 1) ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Category Breakdown -->
    <div class="card p-6">
        <h2 class="font-semibold text-lg mb-4">By Category</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php foreach ($byCategory as $category => $count): ?>
            <div class="p-4 bg-muted/30 rounded-lg">
                <div class="text-sm font-medium"><?= htmlspecialchars($category) ?></div>
                <div class="text-2xl font-light mt-1"><?= $count ?></div>
                <div class="text-xs text-muted-foreground">
                    <?= round(($count / $total) * 100, 1) ?>% of total
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>lucide.createIcons();</script>
