<?php
/**
 * Ticket Detail Page - PHP Version
 * Shows detailed view of a single ticket
 * 
 * @var array $ticket Ticket data
 * @var array $agents List of agents
 */

$user = getCurrentUser();
$canEdit = isAgent() || ($ticket['createdBy'] ?? '') === ($user['uid'] ?? '');

// Get history
$history = $ticket['history'] ?? [];

// Format dates
function formatDate($date) {
    if (!$date) return 'N/A';
    if (is_string($date)) return date('Y-m-d H:i', strtotime($date));
    if (is_array($date) && isset($date['seconds'])) return date('Y-m-d H:i', $date['seconds']);
    return 'N/A';
}

$priority = $ticket['priority'] ?? '4 - Low';
$badgeClass = str_contains($priority, 'Critical') ? 'badge-critical' : 
             (str_contains($priority, 'High') ? 'badge-high' : 
             (str_contains($priority, 'Moderate') ? 'badge-moderate' : 'badge-low'));

$assignedAgent = null;
foreach ($agents as $agent) {
    if (($agent['uid'] ?? $agent['id']) === ($ticket['assignedTo'] ?? '')) {
        $assignedAgent = $agent;
        break;
    }
}
?>
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="<?= BASE_URL ?>/?page=tickets" class="p-2 hover:bg-muted rounded">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold"><?= htmlspecialchars($ticket['number'] ?? 'Ticket') ?></h1>
                <p class="text-muted-foreground"><?= htmlspecialchars($ticket['title'] ?? '') ?></p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="badge <?= $badgeClass ?>"><?= $priority ?></span>
            <span class="px-2 py-1 bg-muted rounded text-sm"><?= $ticket['status'] ?? 'New' ?></span>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Ticket Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Info Card -->
            <div class="card p-6">
                <h2 class="font-semibold text-lg mb-4">Ticket Information</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs text-muted-foreground uppercase">Caller</label>
                        <p class="font-medium"><?= htmlspecialchars($ticket['caller'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <label class="text-xs text-muted-foreground uppercase">Category</label>
                        <p class="font-medium"><?= htmlspecialchars($ticket['category'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <label class="text-xs text-muted-foreground uppercase">Created</label>
                        <p class="font-medium"><?= formatDate($ticket['createdAt']) ?></p>
                    </div>
                    <div>
                        <label class="text-xs text-muted-foreground uppercase">Updated</label>
                        <p class="font-medium"><?= formatDate($ticket['updatedAt']) ?></p>
                    </div>
                    <div>
                        <label class="text-xs text-muted-foreground uppercase">Assignment Group</label>
                        <p class="font-medium"><?= htmlspecialchars($ticket['assignmentGroup'] ?? 'N/A') ?></p>
                    </div>
                    <div>
                        <label class="text-xs text-muted-foreground uppercase">Assigned To</label>
                        <p class="font-medium"><?= htmlspecialchars($assignedAgent['name'] ?? 'Unassigned') ?></p>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="card p-6">
                <h2 class="font-semibold text-lg mb-4">Description</h2>
                <p class="text-sm whitespace-pre-wrap"><?= nl2br(htmlspecialchars($ticket['description'] ?? 'No description provided.')) ?></p>
            </div>

            <!-- SLA Status -->
            <div class="card p-6">
                <h2 class="font-semibold text-lg mb-4">SLA Status</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 bg-muted/30 rounded-lg">
                        <label class="text-xs text-muted-foreground uppercase block mb-2">Response SLA</label>
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-bold <?= ($ticket['responseSlaStatus'] ?? '') === 'Breached' ? 'text-red-600' : (($ticket['responseSlaStatus'] ?? '') === 'Completed' ? 'text-green-600' : 'text-blue-600') ?>">
                                <?= $ticket['responseSlaStatus'] ?? 'In Progress' ?>
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Deadline: <?= formatDate($ticket['responseDeadline'] ?? '') ?>
                        </p>
                    </div>
                    <div class="p-4 bg-muted/30 rounded-lg">
                        <label class="text-xs text-muted-foreground uppercase block mb-2">Resolution SLA</label>
                        <div class="flex items-center gap-2">
                            <span class="text-lg font-bold <?= ($ticket['resolutionSlaStatus'] ?? '') === 'Breached' ? 'text-red-600' : (($ticket['resolutionSlaStatus'] ?? '') === 'Completed' ? 'text-green-600' : 'text-blue-600') ?>">
                                <?= $ticket['resolutionSlaStatus'] ?? 'In Progress' ?>
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Deadline: <?= formatDate($ticket['resolutionDeadline'] ?? '') ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Add Comment -->
            <?php if ($canEdit): ?>
            <div class="card p-6">
                <h2 class="font-semibold text-lg mb-4">Add Comment</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="add_comment" value="1">
                    <textarea name="comment" rows="3" class="w-full p-3 border border-border rounded text-sm resize-none" placeholder="Enter your comment..."></textarea>
                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="message-square" class="w-4 h-4 mr-2"></i>
                            Add Comment
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column - Actions & History -->
        <div class="space-y-6">
            <!-- Actions -->
            <?php if ($canEdit): ?>
            <div class="card p-6">
                <h2 class="font-semibold text-lg mb-4">Actions</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="update_ticket" value="1">
                    <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                    
                    <div>
                        <label class="text-xs text-muted-foreground uppercase block mb-2">Status</label>
                        <select name="status" class="w-full p-2 border border-border rounded text-sm">
                            <option value="New" <?= ($ticket['status'] ?? '') === 'New' ? 'selected' : '' ?>>New</option>
                            <option value="Assigned" <?= ($ticket['status'] ?? '') === 'Assigned' ? 'selected' : '' ?>>Assigned</option>
                            <option value="In Progress" <?= ($ticket['status'] ?? '') === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="On Hold" <?= ($ticket['status'] ?? '') === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                            <option value="Waiting for Customer" <?= ($ticket['status'] ?? '') === 'Waiting for Customer' ? 'selected' : '' ?>>Waiting for Customer</option>
                            <option value="Resolved" <?= ($ticket['status'] ?? '') === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                            <option value="Closed" <?= ($ticket['status'] ?? '') === 'Closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs text-muted-foreground uppercase block mb-2">Assign To</label>
                        <select name="assignedTo" class="w-full p-2 border border-border rounded text-sm">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($agents as $agent): ?>
                            <option value="<?= $agent['uid'] ?? $agent['id'] ?>" <?= ($ticket['assignedTo'] ?? '') === ($agent['uid'] ?? $agent['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($agent['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="w-full btn btn-primary">
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                        Update Ticket
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <!-- History -->
            <div class="card p-6">
                <h2 class="font-semibold text-lg mb-4">Activity History</h2>
                <div class="space-y-4 max-h-96 overflow-y-auto">
                    <?php if (empty($history)): ?>
                    <p class="text-sm text-muted-foreground">No activity recorded.</p>
                    <?php else: 
                        foreach (array_reverse($history) as $entry): 
                    ?>
                    <div class="flex gap-3">
                        <div class="w-8 h-8 rounded-full bg-sn-green/10 flex items-center justify-center shrink-0">
                            <i data-lucide="activity" class="w-4 h-4 text-sn-green"></i>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium"><?= htmlspecialchars($entry['action'] ?? '') ?></p>
                            <p class="text-xs text-muted-foreground">
                                <?= formatDate($entry['timestamp'] ?? '') ?> by <?= htmlspecialchars($entry['user'] ?? 'System') ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
lucide.createIcons();
</script>
