<?php
/**
 * Approved / Resolved Tickets Page
 * @var array $approvedTickets
 * @var array $allUsers
 */
$userMap = [];
foreach ($allUsers as $u) { $userMap[$u['uid'] ?? $u['id']] = $u; }

$priorityColors = [
    '1 - Critical' => 'badge-critical',
    '2 - High'     => 'badge-high',
    '3 - Moderate' => 'badge-moderate',
    '4 - Low'      => 'badge-low',
];

$statusFilter   = $_GET['status']   ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
$search         = $_GET['search']   ?? '';

$filtered = array_values(array_filter($approvedTickets, function($t) use ($statusFilter, $priorityFilter, $search) {
    if ($statusFilter !== 'all' && ($t['status'] ?? '') !== $statusFilter) return false;
    if ($priorityFilter !== 'all' && ($t['priority'] ?? '') !== $priorityFilter) return false;
    if ($search) {
        $hay = strtolower(($t['title'] ?? '') . ' ' . ($t['number'] ?? '') . ' ' . ($t['caller'] ?? ''));
        if (!str_contains($hay, strtolower($search))) return false;
    }
    return true;
}));

$resolvedCount = count(array_filter($approvedTickets, fn($t) => ($t['status'] ?? '') === 'Resolved'));
$closedCount   = count(array_filter($approvedTickets, fn($t) => ($t['status'] ?? '') === 'Closed'));

function atFormatDate($v): string {
    if (!$v) return '—';
    if (is_array($v) && isset($v['seconds'])) return date('M d, Y', $v['seconds']);
    $ts = strtotime($v);
    return $ts ? date('M d, Y', $ts) : '—';
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-green-600 rounded-xl flex items-center justify-center">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-white"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-sn-dark">Approved / Resolved Tickets</h1>
                <p class="text-sm text-muted-foreground">All resolved and closed incidents</p>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4">
        <div class="card p-5 flex items-center gap-4">
            <i data-lucide="check-circle-2" class="w-10 h-10 text-green-500"></i>
            <div>
                <div class="text-3xl font-bold text-green-600"><?= count($approvedTickets) ?></div>
                <div class="text-xs text-muted-foreground uppercase font-bold">Total Resolved</div>
            </div>
        </div>
        <div class="card p-5 flex items-center gap-4" style="border-color:#bbf7d0">
            <i data-lucide="check-circle" class="w-10 h-10 text-green-600"></i>
            <div>
                <div class="text-3xl font-bold text-green-600"><?= $resolvedCount ?></div>
                <div class="text-xs text-muted-foreground uppercase font-bold">Resolved</div>
            </div>
        </div>
        <div class="card p-5 flex items-center gap-4">
            <i data-lucide="archive" class="w-10 h-10 text-gray-500"></i>
            <div>
                <div class="text-3xl font-bold text-gray-600"><?= $closedCount ?></div>
                <div class="text-xs text-muted-foreground uppercase font-bold">Closed</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="<?= BASE_URL ?>/" class="flex items-center gap-3 flex-wrap">
        <input type="hidden" name="page" value="approved_tickets">
        <div class="relative">
            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tickets..."
                class="pl-9 pr-4 py-2 border border-border rounded-lg text-sm w-52 outline-none focus:ring-2 focus:ring-sn-green">
        </div>
        <select name="status" class="p-2 border border-border rounded-lg text-sm outline-none focus:ring-2 focus:ring-sn-green">
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
            <option value="Resolved" <?= $statusFilter === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
            <option value="Closed"   <?= $statusFilter === 'Closed'   ? 'selected' : '' ?>>Closed</option>
        </select>
        <select name="priority" class="p-2 border border-border rounded-lg text-sm outline-none focus:ring-2 focus:ring-sn-green">
            <option value="all" <?= $priorityFilter === 'all' ? 'selected' : '' ?>>All Priorities</option>
            <option value="1 - Critical" <?= $priorityFilter === '1 - Critical' ? 'selected' : '' ?>>Critical</option>
            <option value="2 - High"     <?= $priorityFilter === '2 - High'     ? 'selected' : '' ?>>High</option>
            <option value="3 - Moderate" <?= $priorityFilter === '3 - Moderate' ? 'selected' : '' ?>>Moderate</option>
            <option value="4 - Low"      <?= $priorityFilter === '4 - Low'      ? 'selected' : '' ?>>Low</option>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <span class="text-sm text-muted-foreground ml-auto"><?= count($filtered) ?> tickets</span>
    </form>

    <!-- Table -->
    <div class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-muted/30 border-b border-border text-[10px] font-bold uppercase text-muted-foreground tracking-wide">
                        <th class="p-3">Number</th>
                        <th class="p-3">Short Description</th>
                        <th class="p-3">Reporting User</th>
                        <th class="p-3">Priority</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Category</th>
                        <th class="p-3">Assigned To</th>
                        <th class="p-3">Resolved On</th>
                        <th class="p-3 text-center">View</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filtered)): ?>
                    <tr><td colspan="9" class="p-12 text-center text-muted-foreground">
                        <i data-lucide="check-circle-2" class="w-12 h-12 mx-auto mb-3 opacity-20"></i>
                        <p>No resolved tickets found.</p>
                    </td></tr>
                    <?php else: foreach ($filtered as $t):
                        $priority = $t['priority'] ?? '4 - Low';
                        $status   = $t['status']   ?? 'Resolved';
                        $badgeClass = $priorityColors[$priority] ?? 'badge-low';
                        $assignedUser = $userMap[$t['assignedTo'] ?? ''] ?? null;
                        $statusBg = $status === 'Resolved' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700';
                    ?>
                    <tr class="border-b border-border hover:bg-green-50/20 transition-colors">
                        <td class="p-3">
                            <a href="<?= BASE_URL ?>/?page=ticket&id=<?= $t['id'] ?>"
                               class="font-mono text-[11px] font-bold text-blue-600 hover:underline">
                                <?= htmlspecialchars($t['number'] ?? substr($t['id'], 0, 8)) ?>
                            </a>
                        </td>
                        <td class="p-3 text-sm font-medium max-w-[180px] truncate"><?= htmlspecialchars($t['title'] ?? '—') ?></td>
                        <td class="p-3 text-sm text-muted-foreground"><?= htmlspecialchars($t['caller'] ?? '—') ?></td>
                        <td class="p-3"><span class="badge <?= $badgeClass ?>"><?= $priority ?></span></td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold flex items-center gap-1 w-fit <?= $statusBg ?>">
                                <i data-lucide="check-circle" class="w-3 h-3"></i><?= $status ?>
                            </span>
                        </td>
                        <td class="p-3 text-sm text-muted-foreground"><?= htmlspecialchars($t['category'] ?? '—') ?></td>
                        <td class="p-3 text-sm text-muted-foreground">
                            <?php if ($assignedUser): ?>
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-sn-green flex items-center justify-center text-sn-dark text-[10px] font-bold">
                                    <?= strtoupper(substr($assignedUser['name'] ?? '?', 0, 1)) ?>
                                </div>
                                <span class="text-xs"><?= htmlspecialchars($assignedUser['name'] ?? '') ?></span>
                            </div>
                            <?php else: ?><span class="italic text-xs">Unassigned</span><?php endif; ?>
                        </td>
                        <td class="p-3 text-xs text-muted-foreground"><?= atFormatDate($t['resolvedAt'] ?? $t['updatedAt'] ?? '') ?></td>
                        <td class="p-3 text-center">
                            <a href="<?= BASE_URL ?>/?page=ticket&id=<?= $t['id'] ?>"
                               class="inline-flex items-center justify-center w-8 h-8 border border-border rounded-lg hover:bg-muted transition-colors">
                                <i data-lucide="eye" class="w-3.5 h-3.5 text-muted-foreground"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>lucide.createIcons();</script>
