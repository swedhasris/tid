<?php
/**
 * Task Detail View — shows task info + all linked tickets
 *
 * @var array  $taskDetail   The task document
 * @var array  $taskTickets  Tickets linked to this task
 * @var array  $allUsers     All users for name lookup
 */

// Build user map
$tvUserMap = [];
foreach ($allUsers as $u) {
    $tvUserMap[$u['uid'] ?? $u['id']] = $u;
}

// Priority helpers
function tvPriorityClass(string $p): string {
    if (str_contains($p, 'Critical')) return 'tv-critical';
    if (str_contains($p, 'High'))     return 'tv-high';
    if (str_contains($p, 'Moderate')) return 'tv-moderate';
    return 'tv-low';
}
function tvStatusClass(string $s): string {
    return match($s) {
        'New'         => 'tv-s-new',
        'In Progress' => 'tv-s-inprogress',
        'On Hold'     => 'tv-s-hold',
        'Resolved'    => 'tv-s-resolved',
        'Closed'      => 'tv-s-closed',
        default       => 'tv-s-new',
    };
}
function tvFormatDate($v): string {
    if (!$v) return '—';
    if (is_array($v) && isset($v['seconds'])) return date('M d, Y H:i', $v['seconds']);
    $ts = strtotime($v);
    return $ts ? date('M d, Y H:i', $ts) : '—';
}

// Active filter
$tvFilter = $_GET['status'] ?? 'all';
$tvFiltered = array_values(array_filter($taskTickets, function($t) use ($tvFilter) {
    if ($tvFilter === 'all')      return true;
    if ($tvFilter === 'open')     return in_array($t['status'] ?? '', ['New', 'In Progress']);
    if ($tvFilter === 'resolved') return in_array($t['status'] ?? '', ['Resolved', 'Closed']);
    return ($t['status'] ?? '') === $tvFilter;
}));

// Counts
$tvCounts = [
    'all'         => count($taskTickets),
    'open'        => count(array_filter($taskTickets, fn($t) => in_array($t['status'] ?? '', ['New', 'In Progress']))),
    'In Progress' => count(array_filter($taskTickets, fn($t) => ($t['status'] ?? '') === 'In Progress')),
    'On Hold'     => count(array_filter($taskTickets, fn($t) => ($t['status'] ?? '') === 'On Hold')),
    'resolved'    => count(array_filter($taskTickets, fn($t) => in_array($t['status'] ?? '', ['Resolved', 'Closed']))),
];
?>

<div class="space-y-6">

    <!-- ── Breadcrumb ── -->
    <div class="flex items-center gap-2 text-sm text-muted-foreground">
        <a href="<?= BASE_URL ?>/?page=tasks" class="hover:text-foreground">Tasks</a>
        <i data-lucide="chevron-right" class="w-3 h-3"></i>
        <span class="text-foreground font-medium"><?= htmlspecialchars($taskDetail['name'] ?? '') ?></span>
    </div>

    <!-- ── Task Header Card ── -->
    <div class="tv-task-header">
        <div class="tv-task-header-icon">
            <i data-lucide="clipboard-list" class="w-7 h-7"></i>
        </div>
        <div class="tv-task-header-info">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-sn-dark"><?= htmlspecialchars($taskDetail['name'] ?? '') ?></h1>
                <span class="tv-cat-badge"><?= htmlspecialchars($taskDetail['category'] ?? 'General') ?></span>
                <?php if (!empty($taskDetail['isActive']) && $taskDetail['isActive']): ?>
                <span class="tv-active-badge">Active</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($taskDetail['description'])): ?>
            <p class="text-muted-foreground mt-1"><?= htmlspecialchars($taskDetail['description']) ?></p>
            <?php endif; ?>
        </div>
        <div class="tv-task-header-actions">
            <a href="<?= BASE_URL ?>/?page=tickets&action=new" class="btn btn-primary">
                <i data-lucide="plus" class="w-4 h-4 mr-1"></i> New Ticket for this Task
            </a>
        </div>
    </div>

    <!-- ── Stats ── -->
    <div class="tv-stats-row">
        <?php
        $tvStats = [
            ['label' => 'Total Tickets',  'value' => $tvCounts['all'],         'icon' => 'layers',       'cls' => 'tv-stat-total'],
            ['label' => 'Open',           'value' => $tvCounts['open'],        'icon' => 'folder-open',  'cls' => 'tv-stat-open'],
            ['label' => 'In Progress',    'value' => $tvCounts['In Progress'], 'icon' => 'loader',       'cls' => 'tv-stat-inprogress'],
            ['label' => 'On Hold',        'value' => $tvCounts['On Hold'],     'icon' => 'pause-circle', 'cls' => 'tv-stat-hold'],
            ['label' => 'Resolved',       'value' => $tvCounts['resolved'],    'icon' => 'check-circle', 'cls' => 'tv-stat-resolved'],
        ];
        foreach ($tvStats as $s): ?>
        <div class="tv-stat-card <?= $s['cls'] ?>">
            <i data-lucide="<?= $s['icon'] ?>" class="tv-stat-icon"></i>
            <div class="tv-stat-value"><?= $s['value'] ?></div>
            <div class="tv-stat-label"><?= $s['label'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Tickets Section ── -->
    <div class="tv-tickets-section">

        <!-- Section header with collapse toggle -->
        <div class="tv-section-header" onclick="tvToggleSection()">
            <div class="flex items-center gap-3">
                <i data-lucide="ticket" class="w-5 h-5 text-sn-green"></i>
                <h2 class="font-bold text-sn-dark">Tickets under this Task</h2>
                <span class="tv-count-pill"><?= count($taskTickets) ?></span>
            </div>
            <div class="flex items-center gap-3">
                <!-- Filter tabs inline -->
                <div class="tv-inline-tabs" onclick="event.stopPropagation()">
                    <?php
                    $tvTabs = [
                        ['key' => 'all',         'label' => 'All'],
                        ['key' => 'open',        'label' => 'Open'],
                        ['key' => 'In Progress', 'label' => 'In Progress'],
                        ['key' => 'On Hold',     'label' => 'On Hold'],
                        ['key' => 'resolved',    'label' => 'Resolved'],
                    ];
                    foreach ($tvTabs as $tab): ?>
                    <a href="<?= BASE_URL ?>/?page=task_view&id=<?= urlencode($taskDetail['id']) ?>&status=<?= urlencode($tab['key']) ?>"
                       class="tv-inline-tab <?= $tvFilter === $tab['key'] ? 'tv-inline-tab-active' : '' ?>">
                        <?= $tab['label'] ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <!-- Search -->
                <div class="relative" onclick="event.stopPropagation()">
                    <i data-lucide="search" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground"></i>
                    <input type="text" id="tvSearch" placeholder="Search…" oninput="tvFilterCards(this.value)"
                           class="pl-8 pr-3 py-1.5 border border-border rounded-lg text-xs w-44 outline-none focus:ring-2 focus:ring-sn-green">
                </div>
                <i data-lucide="chevron-down" class="w-4 h-4 text-muted-foreground" id="tvChevron"></i>
            </div>
        </div>

        <!-- Collapsible ticket cards -->
        <div id="tvTicketBody">
            <?php if (empty($tvFiltered)): ?>
            <div class="tv-empty">
                <i data-lucide="inbox" class="w-10 h-10 text-muted-foreground mx-auto mb-2"></i>
                <p class="text-muted-foreground">No tickets match this filter.</p>
                <a href="<?= BASE_URL ?>/?page=tickets&action=new" class="btn btn-primary mt-3">
                    <i data-lucide="plus" class="w-4 h-4 mr-1"></i> Create First Ticket
                </a>
            </div>
            <?php else: ?>
            <div class="tv-cards-grid" id="tvGrid">
                <?php foreach ($tvFiltered as $ticket):
                    $priority    = $ticket['priority'] ?? '4 - Low';
                    $status      = $ticket['status']   ?? 'New';
                    $pClass      = tvPriorityClass($priority);
                    $sClass      = tvStatusClass($status);
                    $assignedUid = $ticket['assignedTo'] ?? '';
                    $assignedUser = $tvUserMap[$assignedUid] ?? null;
                    $createdUid  = $ticket['createdBy'] ?? '';
                    $createdUser = $tvUserMap[$createdUid] ?? null;
                ?>
                <div class="tv-ticket-card <?= $pClass ?>"
                     data-search="<?= strtolower(htmlspecialchars(($ticket['title'] ?? '') . ' ' . ($ticket['number'] ?? '') . ' ' . ($ticket['caller'] ?? ''))) ?>">

                    <!-- Stripe + header -->
                    <div class="tv-card-top">
                        <div class="tv-card-stripe"></div>
                        <div class="tv-card-header">
                            <a href="<?= BASE_URL ?>/?page=ticket&id=<?= $ticket['id'] ?>" class="tv-ticket-num">
                                <?= htmlspecialchars($ticket['number'] ?? substr($ticket['id'], 0, 8)) ?>
                            </a>
                            <span class="tv-status-badge <?= $sClass ?>"><?= $status ?></span>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="tv-card-body">
                        <a href="<?= BASE_URL ?>/?page=ticket&id=<?= $ticket['id'] ?>" class="tv-title">
                            <?= htmlspecialchars($ticket['title'] ?? 'Untitled') ?>
                        </a>

                        <?php if (!empty($ticket['description'])): ?>
                        <p class="tv-desc"><?= htmlspecialchars(mb_strimwidth($ticket['description'], 0, 90, '…')) ?></p>
                        <?php endif; ?>

                        <!-- Meta -->
                        <div class="tv-meta">
                            <span class="tv-priority-pill <?= $pClass ?>-pill">
                                <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                <?= $priority ?>
                            </span>
                            <span class="tv-meta-item">
                                <i data-lucide="tag" class="w-3 h-3"></i>
                                <?= htmlspecialchars($ticket['category'] ?? 'General') ?>
                            </span>
                            <span class="tv-meta-item">
                                <i data-lucide="calendar" class="w-3 h-3"></i>
                                <?= tvFormatDate($ticket['createdAt'] ?? '') ?>
                            </span>
                        </div>

                        <!-- Assignment -->
                        <div class="tv-assign-row">
                            <div class="tv-avatar" title="<?= htmlspecialchars($assignedUser['name'] ?? 'Unassigned') ?>">
                                <?= strtoupper(substr($assignedUser['name'] ?? '?', 0, 1)) ?>
                            </div>
                            <div>
                                <div class="tv-assign-label">Assigned to</div>
                                <div class="tv-assign-name"><?= htmlspecialchars($assignedUser['name'] ?? 'Unassigned') ?></div>
                            </div>
                            <?php if ($createdUser): ?>
                            <div class="ml-4">
                                <div class="tv-assign-label">Raised by</div>
                                <div class="tv-assign-name"><?= htmlspecialchars($createdUser['name'] ?? '—') ?></div>
                            </div>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>/?page=ticket&id=<?= $ticket['id'] ?>" class="tv-view-link ml-auto">
                                <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
lucide.createIcons();

// Collapse/expand
let tvOpen = true;
function tvToggleSection() {
    tvOpen = !tvOpen;
    document.getElementById('tvTicketBody').style.display = tvOpen ? '' : 'none';
    document.getElementById('tvChevron').style.transform  = tvOpen ? '' : 'rotate(-90deg)';
}

// Search filter
function tvFilterCards(q) {
    const lq = q.toLowerCase();
    document.querySelectorAll('.tv-ticket-card').forEach(c => {
        c.style.display = (c.dataset.search || '').includes(lq) ? '' : 'none';
    });
}
</script>
