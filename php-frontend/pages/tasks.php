<?php
/**
 * Tasks List Page — all tasks with ticket counts
 * @var array $allTasks  Tasks with ticketCount attached
 */
?>
<div class="space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold text-sn-dark">Tasks</h1>
            <p class="text-sm text-muted-foreground">All tasks — click a task to see its linked tickets</p>
        </div>
        <a href="<?= BASE_URL ?>/?page=tickets&action=new" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> New Ticket
        </a>
    </div>

    <?php if (empty($allTasks)): ?>
    <div class="text-center py-16 text-muted-foreground">
        <i data-lucide="inbox" class="w-12 h-12 mx-auto mb-3"></i>
        <p>No tasks found.</p>
    </div>
    <?php else: ?>
    <div class="tv-task-grid">
        <?php foreach ($allTasks as $task):
            $count = $task['ticketCount'] ?? 0;
            $cat   = $task['category'] ?? 'General';
        ?>
        <a href="<?= BASE_URL ?>/?page=task_view&id=<?= urlencode($task['id']) ?>" class="tv-task-card">
            <div class="tv-task-icon">
                <i data-lucide="clipboard-list" class="w-5 h-5"></i>
            </div>
            <div class="tv-task-info">
                <div class="tv-task-name"><?= htmlspecialchars($task['name']) ?></div>
                <div class="tv-task-cat"><?= htmlspecialchars($cat) ?></div>
                <?php if (!empty($task['description'])): ?>
                <div class="tv-task-desc"><?= htmlspecialchars(mb_strimwidth($task['description'], 0, 80, '…')) ?></div>
                <?php endif; ?>
            </div>
            <div class="tv-task-count">
                <span class="tv-count-badge"><?= $count ?></span>
                <span class="tv-count-label">ticket<?= $count !== 1 ? 's' : '' ?></span>
            </div>
            <i data-lucide="chevron-right" class="w-4 h-4 text-muted-foreground ml-2"></i>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<script>lucide.createIcons();</script>
