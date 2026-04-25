<?php
/**
 * Dashboard Page - PHP Version
 * Replicates React Dashboard.tsx
 * 
 * @var array $stats Dashboard statistics
 * @var array $tickets List of tickets
 */
?>
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <div class="flex items-center gap-2">
            <h1 class="text-xl font-bold text-sn-dark">Dashboard</h1>
            <button class="p-1 hover:bg-muted rounded" title="Info">
                <i data-lucide="info" class="w-4 h-4 text-muted-foreground"></i>
            </button>
        </div>
        <a href="<?= BASE_URL ?>/?page=tickets&action=new" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4"></i>
            Create Ticket
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-5 space-y-3">
            <div class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">Total Tickets</div>
            <div class="flex items-center gap-2">
                <span class="text-3xl font-light text-sn-dark"><?= $stats['total'] ?></span>
            </div>
        </div>
        <div class="card p-5 space-y-3">
            <div class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">Open Tickets</div>
            <div class="flex items-center gap-2">
                <span class="text-3xl font-light text-sn-dark"><?= $stats['open'] ?></span>
                <div class="p-1 bg-blue-50 text-blue-600 rounded">
                    <i data-lucide="folder-open" class="w-3 h-3"></i>
                </div>
            </div>
        </div>
        <div class="card p-5 space-y-3">
            <div class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">Resolved</div>
            <div class="flex items-center gap-2">
                <span class="text-3xl font-light text-sn-dark"><?= $stats['resolved'] ?></span>
                <div class="p-1 bg-green-50 text-green-600 rounded">
                    <i data-lucide="check-circle" class="w-3 h-3"></i>
                </div>
            </div>
        </div>
        <div class="card p-5 space-y-3">
            <div class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">Critical Priority</div>
            <div class="flex items-center gap-2">
                <span class="text-3xl font-light text-sn-dark"><?= $stats['critical'] ?></span>
                <div class="p-1 bg-red-50 text-red-600 rounded">
                    <i data-lucide="alert-octagon" class="w-3 h-3"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Tickets -->
    <div class="card overflow-hidden">
        <div class="p-4 border-b border-border flex items-center justify-between bg-muted/30">
            <h2 class="font-semibold text-sn-dark">Recent Tickets</h2>
            <a href="<?= BASE_URL ?>/?page=tickets" class="text-sm text-blue-600 hover:underline">View All</a>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-muted/50 border-b border-border">
                        <th class="p-3 text-[11px] font-bold uppercase tracking-tight">Number</th>
                        <th class="p-3 text-[11px] font-bold uppercase tracking-tight">Title</th>
                        <th class="p-3 text-[11px] font-bold uppercase tracking-tight">Caller</th>
                        <th class="p-3 text-[11px] font-bold uppercase tracking-tight">Priority</th>
                        <th class="p-3 text-[11px] font-bold uppercase tracking-tight">Status</th>
                        <th class="p-3 text-[11px] font-bold uppercase tracking-tight">Category</th>
                        <th class="p-3 text-[11px] font-bold uppercase tracking-tight">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $recentTickets = array_slice($tickets, 0, 10);
                    if (empty($recentTickets)): 
                    ?>
                    <tr>
                        <td colspan="7" class="p-8 text-center text-muted-foreground">
                            No tickets found. <a href="<?= BASE_URL ?>/?page=tickets&action=new" class="text-blue-600 hover:underline">Create your first ticket</a>
                        </td>
                    </tr>
                    <?php else: 
                        foreach ($recentTickets as $ticket): 
                            $priority = $ticket['priority'] ?? '4 - Low';
                            $badgeClass = str_contains($priority, 'Critical') ? 'badge-critical' : 
                                         (str_contains($priority, 'High') ? 'badge-high' : 
                                         (str_contains($priority, 'Moderate') ? 'badge-moderate' : 'badge-low'));
                            
                            $status = $ticket['status'] ?? 'New';
                            $createdAt = '';
                            if (isset($ticket['createdAt'])) {
                                if (is_string($ticket['createdAt'])) {
                                    $createdAt = date('Y-m-d', strtotime($ticket['createdAt']));
                                } elseif (is_array($ticket['createdAt']) && isset($ticket['createdAt']['seconds'])) {
                                    $createdAt = date('Y-m-d', $ticket['createdAt']['seconds']);
                                }
                            }
                    ?>
                    <tr class="border-b border-border hover:bg-muted/10 transition-colors">
                        <td class="p-3">
                            <a href="<?= BASE_URL ?>/?page=ticket&id=<?= $ticket['id'] ?>" class="font-mono text-[11px] font-bold text-blue-600 hover:underline">
                                <?= htmlspecialchars($ticket['number'] ?? $ticket['id']) ?>
                            </a>
                        </td>
                        <td class="p-3 text-[11px] font-medium"><?= htmlspecialchars($ticket['title'] ?? '') ?></td>
                        <td class="p-3 text-[11px]"><?= htmlspecialchars($ticket['caller'] ?? '') ?></td>
                        <td class="p-3">
                            <span class="badge <?= $badgeClass ?>"><?= $priority ?></span>
                        </td>
                        <td class="p-3">
                            <span class="text-[11px] status-<?= strtolower(str_replace(' ', '-', $status)) ?>"><?= $status ?></span>
                        </td>
                        <td class="p-3 text-[11px]"><?= htmlspecialchars($ticket['category'] ?? '') ?></td>
                        <td class="p-3 text-[11px] text-muted-foreground"><?= $createdAt ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="<?= BASE_URL ?>/?page=tickets&action=new" class="card p-4 hover:shadow-md transition-shadow flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-sn-green/10 flex items-center justify-center">
                <i data-lucide="plus-circle" class="w-5 h-5 text-sn-green"></i>
            </div>
            <div>
                <h3 class="font-semibold text-sn-dark">Create Ticket</h3>
                <p class="text-xs text-muted-foreground">Submit a new support request</p>
            </div>
        </a>
        
        <a href="<?= BASE_URL ?>/?page=tickets" class="card p-4 hover:shadow-md transition-shadow flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center">
                <i data-lucide="list" class="w-5 h-5 text-blue-600"></i>
            </div>
            <div>
                <h3 class="font-semibold text-sn-dark">My Tickets</h3>
                <p class="text-xs text-muted-foreground">View all your tickets</p>
            </div>
        </a>
        
        <a href="<?= BASE_URL ?>/?page=kb" class="card p-4 hover:shadow-md transition-shadow flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-purple-50 flex items-center justify-center">
                <i data-lucide="book-open" class="w-5 h-5 text-purple-600"></i>
            </div>
            <div>
                <h3 class="font-semibold text-sn-dark">Knowledge Base</h3>
                <p class="text-xs text-muted-foreground">Find solutions and guides</p>
            </div>
        </a>
    </div>
</div>

<script>
lucide.createIcons();
</script>
