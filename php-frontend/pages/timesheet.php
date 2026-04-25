<?php
/**
 * Timesheet Dashboard - Weekly View (ServiceNow-style)
 */

require_once __DIR__ . '/../includes/TimesheetModel.php';

$user = getCurrentUser();
$userId = $user['uid'] ?? '';
$model = new TimesheetModel();

// Get week parameter or default to current week
$weekOffset = intval($_GET['week'] ?? 0);
$currentDate = new DateTime();
$currentDate->modify("{$weekOffset} weeks");

// Get Monday of current week
$monday = clone $currentDate;
$monday->modify('monday this week');
$weekStart = $monday->format('Y-m-d');

// Get or create timesheet for this week
$timesheet = $model->getOrCreateTimesheet($userId, $weekStart);
$timesheetId = $timesheet['id'] ?? '';

// Get time cards for this timesheet
$timeCards = $model->getTimeCardsByTimesheet($timesheetId);

// Get all tasks for dropdown
$tasks = $model->getTasks();

// Organize time cards by day
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $day = clone $monday;
    $day->modify("+{$i} days");
    $dateKey = $day->format('Y-m-d');
    $weekDays[$dateKey] = [
        'date' => $dateKey,
        'dayName' => $day->format('D'),
        'fullDate' => $day->format('M d'),
        'entries' => [],
        'total' => 0,
    ];
}

// Group time cards by date
foreach ($timeCards as $card) {
    $date = is_string($card['entryDate']) ? substr($card['entryDate'], 0, 10) : '';
    if (isset($weekDays[$date])) {
        $task = $model->getTask($card['taskId'] ?? '');
        $card['taskName'] = $task['name'] ?? 'Unknown';
        $card['taskPriority'] = $task['priority'] ?? ($card['priority'] ?? 'medium');
        $card['taskStatus']   = $task['status']   ?? ($card['cardStatus'] ?? 'pending');
        $weekDays[$date]['entries'][] = $card;
        $weekDays[$date]['total'] += (float) ($card['hoursWorked'] ?? 0);
    }
}

// Calculate week total
$weekTotal = array_sum(array_column($weekDays, 'total'));

// Timesheet status badge colors
$statusColors = [
    'Draft'     => 'ts-badge-draft',
    'Submitted' => 'ts-badge-submitted',
    'Approved'  => 'ts-badge-approved',
    'Rejected'  => 'ts-badge-rejected',
];

// Ticket card priority → CSS class
function ticketClass(string $priority, string $status): string {
    if ($status === 'completed') return 'tc-completed';
    if ($status === 'pending')   return 'tc-pending';
    return match(strtolower($priority)) {
        'high'   => 'tc-high',
        'low'    => 'tc-low',
        default  => 'tc-medium',
    };
}

$currentStatus = $timesheet['status'] ?? 'Draft';
$canEdit = in_array($currentStatus, ['Draft', 'Rejected']);
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold text-sn-dark">Timesheet</h1>
            <p class="text-muted-foreground">Log and manage your weekly working hours</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="ts-status-badge <?= $statusColors[$currentStatus] ?? 'ts-badge-draft' ?>">
                <?= $currentStatus ?>
            </span>
            <?php if ($canEdit): ?>
            <button onclick="submitTimesheet()" class="btn btn-primary">
                <i data-lucide="check-circle" class="w-4 h-4 mr-2"></i>
                Submit Timesheet
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rejection Reason Banner -->
    <?php if ($currentStatus === 'Rejected' && !empty($timesheet['rejectionReason'])): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg flex items-start gap-3">
        <i data-lucide="alert-circle" class="w-5 h-5 mt-0.5 shrink-0"></i>
        <div>
            <div class="font-semibold">Timesheet Rejected</div>
            <div class="text-sm mt-1"><?= htmlspecialchars($timesheet['rejectionReason']) ?></div>
            <div class="text-xs mt-1 text-red-500">Please make corrections and resubmit.</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Week Navigation -->
    <div class="flex items-center justify-between">
        <a href="<?= BASE_URL ?>/?page=timesheet&week=<?= $weekOffset - 1 ?>" class="btn btn-ghost">
            <i data-lucide="chevron-left" class="w-4 h-4 mr-1"></i>
            Previous Week
        </a>
        <div class="text-center">
            <h2 class="text-lg font-semibold">
                Week of <?= $monday->format('M d, Y') ?> - <?= (clone $monday)->modify('+6 days')->format('M d, Y') ?>
            </h2>
            <p class="text-sm text-muted-foreground">Total: <strong><?= number_format($weekTotal, 2) ?></strong> hours</p>
        </div>
        <a href="<?= BASE_URL ?>/?page=timesheet&week=<?= $weekOffset + 1 ?>" class="btn btn-ghost">
            Next Week
            <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
        </a>
    </div>

    <!-- Weekly Grid -->
    <div class="ts-grid">
        <?php foreach ($weekDays as $date => $day):
            $isWeekend = in_array($day['dayName'], ['Sat', 'Sun']);
            $isToday   = $date === date('Y-m-d');
        ?>
        <div class="ts-day-col <?= $isWeekend ? 'ts-weekend' : '' ?> <?= $isToday ? 'ts-today' : '' ?>"
             id="day-<?= $date ?>"
             data-date="<?= $date ?>"
             <?php if ($canEdit): ?>
             ondragover="event.preventDefault(); this.classList.add('ts-drag-over')"
             ondragleave="this.classList.remove('ts-drag-over')"
             ondrop="handleDrop(event, '<?= $date ?>')"
             <?php endif; ?>>

            <!-- Day Header -->
            <div class="ts-day-header <?= $isToday ? 'ts-today-header' : '' ?>">
                <div class="ts-day-name"><?= $day['dayName'] ?></div>
                <div class="ts-day-date"><?= $day['fullDate'] ?></div>
                <div class="ts-day-total"><?= number_format($day['total'], 1) ?> hrs</div>
                <?php if ($isToday): ?><div class="ts-today-dot"></div><?php endif; ?>
            </div>

            <!-- Ticket Cards -->
            <div class="ts-entries" id="entries-<?= $date ?>">
                <?php if (empty($day['entries'])): ?>
                <div class="ts-empty">No entries</div>
                <?php endif; ?>

                <?php foreach ($day['entries'] as $entry):
                    $priority   = strtolower($entry['taskPriority'] ?? 'medium');
                    $cardStatus = strtolower($entry['taskStatus']   ?? 'pending');
                    $cssClass   = ticketClass($priority, $cardStatus);
                    $entryJson  = htmlspecialchars(json_encode([
                        'id'          => $entry['id'],
                        'taskId'      => $entry['taskId'] ?? '',
                        'hoursWorked' => $entry['hoursWorked'] ?? 0,
                        'description' => $entry['description'] ?? '',
                        'priority'    => $priority,
                        'cardStatus'  => $cardStatus,
                    ]), ENT_QUOTES);
                ?>
                <div class="ticket-card <?= $cssClass ?>"
                     id="card-<?= $entry['id'] ?>"
                     data-id="<?= $entry['id'] ?>"
                     data-date="<?= $date ?>"
                     data-entry='<?= $entryJson ?>'
                     <?php if ($canEdit): ?>draggable="true" ondragstart="handleDragStart(event)"<?php endif; ?>
                     title="<?= htmlspecialchars($entry['description'] ?? '') ?>">

                    <!-- Priority stripe -->
                    <div class="tc-stripe"></div>

                    <div class="tc-body">
                        <!-- Title row -->
                        <div class="tc-title"><?= htmlspecialchars($entry['taskName']) ?></div>

                        <!-- Meta row -->
                        <div class="tc-meta">
                            <span class="tc-hours">
                                <i data-lucide="clock" class="tc-icon"></i>
                                <?= number_format($entry['hoursWorked'], 1) ?> hrs
                            </span>
                            <span class="tc-badge tc-badge-<?= $cardStatus ?>">
                                <?= ucfirst($cardStatus) ?>
                            </span>
                        </div>

                        <!-- Priority pill -->
                        <div class="tc-priority-row">
                            <span class="tc-priority-pill tc-priority-<?= $priority ?>">
                                <?= ucfirst($priority) ?>
                            </span>
                            <?php if (!empty($entry['description'])): ?>
                            <span class="tc-desc"><?= htmlspecialchars(mb_strimwidth($entry['description'], 0, 30, '…')) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions (visible on hover) -->
                    <?php if ($canEdit): ?>
                    <div class="tc-actions">
                        <button onclick="editEntry('<?= $entry['id'] ?>', '<?= $date ?>')" title="Edit">
                            <i data-lucide="pencil" class="w-3 h-3"></i>
                        </button>
                        <button onclick="deleteEntry('<?= $entry['id'] ?>', '<?= $timesheetId ?>')" title="Delete" class="tc-delete">
                            <i data-lucide="trash-2" class="w-3 h-3"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Add Button -->
            <?php if ($canEdit): ?>
            <div class="ts-add-btn">
                <button onclick="openEntryModal('<?= $date ?>')">
                    <i data-lucide="plus" class="w-3 h-3"></i> Add Entry
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Stats -->
    <div class="ts-stats-row">
        <div class="ts-stat-card">
            <div class="ts-stat-label">Week Total</div>
            <div class="ts-stat-value"><?= number_format($weekTotal, 2) ?> hrs</div>
        </div>
        <div class="ts-stat-card">
            <div class="ts-stat-label">Daily Average</div>
            <div class="ts-stat-value"><?= number_format($weekTotal / 7, 2) ?> hrs</div>
        </div>
        <div class="ts-stat-card">
            <div class="ts-stat-label">Entries</div>
            <div class="ts-stat-value"><?= count($timeCards) ?></div>
        </div>
        <div class="ts-stat-card">
            <div class="ts-stat-label">Status</div>
            <div class="ts-stat-value">
                <span class="ts-status-badge <?= $statusColors[$currentStatus] ?? 'ts-badge-draft' ?>"><?= $currentStatus ?></span>
            </div>
        </div>
    </div>

    <!-- Legend -->
    <div class="ts-legend">
        <span class="ts-legend-title">Priority:</span>
        <span class="ts-legend-item"><span class="ts-legend-dot tc-high"></span> High</span>
        <span class="ts-legend-item"><span class="ts-legend-dot tc-medium"></span> Medium</span>
        <span class="ts-legend-item"><span class="ts-legend-dot tc-low"></span> Low</span>
        <span class="ts-legend-item"><span class="ts-legend-dot tc-completed"></span> Completed</span>
        <span class="ts-legend-item"><span class="ts-legend-dot tc-pending"></span> Pending</span>
    </div>
</div>

<!-- Add/Edit Entry Modal -->
<div id="entryModal" class="modal-backdrop hidden">
    <div class="ate-modal">

        <!-- Header -->
        <div class="ate-header">
            <h3 id="modalTitle">Add Time Entry</h3>
            <button onclick="closeEntryModal()" class="ate-close">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form id="entryForm" class="ate-body">
            <input type="hidden" id="entryId" name="entryId">
            <input type="hidden" id="entryDate" name="entryDate">
            <input type="hidden" name="timesheetId" value="<?= $timesheetId ?>">

            <!-- Row 1: Date + Hours -->
            <div class="ate-row-2col">
                <div class="ate-field">
                    <label class="ate-label">Date</label>
                    <input type="date" id="displayDate" name="displayDateVal"
                           class="ate-input" readonly>
                </div>
                <div class="ate-field">
                    <label class="ate-label">Hours</label>
                    <input type="number" name="hoursWorked" id="hoursWorked"
                           step="0.5" min="0.5" max="24" required
                           class="ate-input" placeholder="">
                </div>
            </div>

            <!-- Related Ticket / Task -->
            <div class="ate-field">
                <div class="ate-label-row">
                    <label class="ate-label">Related Ticket</label>
                    <div class="ate-ticket-actions">
                        <span class="ate-ticket-action-link" onclick="showTicketIdInput()">Select</span>
                        <span class="ate-sep">|</span>
                        <span class="ate-ticket-action-plain" onclick="showTicketIdInput()">Enter ID</span>
                        <span class="ate-sep">|</span>
                        <a href="<?= BASE_URL ?>/?page=tickets&action=new" target="_blank" class="ate-ticket-action-plain">Create New</a>
                    </div>
                </div>
                <div class="ate-select-wrapper">
                    <select name="taskId" id="taskId" required class="ate-select">
                        <option value="">No ticket selected</option>
                        <?php foreach ($tasks as $task): ?>
                        <option value="<?= $task['id'] ?>"
                                data-priority="<?= htmlspecialchars($task['priority'] ?? 'medium') ?>"
                                data-status="<?= htmlspecialchars($task['status'] ?? 'pending') ?>">
                            <?= htmlspecialchars($task['name']) ?>
                            <?php if (!empty($task['category'])): ?> · <?= htmlspecialchars($task['category']) ?><?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <i data-lucide="chevron-down" class="ate-select-icon"></i>
                </div>
            </div>

            <!-- Task Description -->
            <div class="ate-field">
                <label class="ate-label">Task Description</label>
                <textarea name="description" id="description" rows="3"
                          class="ate-textarea" placeholder=""></textarea>
            </div>

            <!-- Billable toggle -->
            <div class="ate-billable-row">
                <label class="ate-toggle-wrap">
                    <input type="checkbox" name="billable" id="billable" class="ate-toggle-input" checked>
                    <span class="ate-toggle-track">
                        <span class="ate-toggle-thumb"></span>
                    </span>
                </label>
                <span class="ate-billable-label">Billable</span>
            </div>

            <!-- Notes -->
            <div class="ate-field">
                <label class="ate-label">Notes</label>
                <textarea name="notes" id="notes" rows="3"
                          class="ate-textarea" placeholder=""></textarea>
            </div>

            <!-- Hidden priority/status (auto-filled) -->
            <input type="hidden" name="priority"   id="priority"   value="medium">
            <input type="hidden" name="cardStatus" id="cardStatus" value="pending">

            <!-- Validation errors -->
            <div id="validationErrors" class="ate-errors hidden"></div>

            <!-- Footer -->
            <div class="ate-footer">
                <button type="button" onclick="closeEntryModal()" class="ate-btn-cancel">Cancel</button>
                <button type="submit" class="ate-btn-add">Add</button>
            </div>
        </form>
    </div>
</div>

<script>
lucide.createIcons();

// ── Modal ──────────────────────────────────────────────
function openEntryModal(date) {
    document.getElementById('entryId').value = '';
    document.getElementById('entryDate').value = date;
    document.getElementById('displayDate').value = date;
    document.getElementById('modalTitle').textContent = 'Add Time Entry';
    document.getElementById('entryForm').reset();
    document.getElementById('entryDate').value = date;
    document.getElementById('displayDate').value = date;
    document.getElementById('billable').checked = true;
    document.getElementById('validationErrors').classList.add('hidden');
    document.getElementById('entryModal').classList.remove('hidden');
}

function editEntry(entryId, date) {
    fetch('<?= BASE_URL ?>/?page=timesheet_ajax&action=get_entry&id=' + entryId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const e = data.entry;
            document.getElementById('entryId').value      = e.id;
            document.getElementById('entryDate').value    = date;
            document.getElementById('displayDate').value  = date;
            document.getElementById('taskId').value       = e.taskId || '';
            document.getElementById('hoursWorked').value  = e.hoursWorked || '';
            document.getElementById('description').value  = e.description || '';
            document.getElementById('notes').value        = e.notes || '';
            document.getElementById('priority').value     = e.priority || 'medium';
            document.getElementById('cardStatus').value   = e.cardStatus || 'pending';
            document.getElementById('billable').checked   = e.billable !== false;
            document.getElementById('modalTitle').textContent = 'Edit Time Entry';
            document.getElementById('validationErrors').classList.add('hidden');
            document.getElementById('entryModal').classList.remove('hidden');
        });
}

function closeEntryModal() {
    document.getElementById('entryModal').classList.add('hidden');
}

document.getElementById('entryModal').addEventListener('click', function(e) {
    if (e.target === this) closeEntryModal();
});

// Auto-fill priority/status from task selection
document.getElementById('taskId').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (opt.dataset.priority) document.getElementById('priority').value    = opt.dataset.priority;
    if (opt.dataset.status)   document.getElementById('cardStatus').value  = opt.dataset.status;
});

// ── Form submit ────────────────────────────────────────
document.getElementById('entryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(this));
    fetch('<?= BASE_URL ?>/?page=timesheet_ajax&action=save_entry', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) { closeEntryModal(); location.reload(); }
        else {
            const div = document.getElementById('validationErrors');
            div.innerHTML = (res.errors || [res.message]).map(e => '• ' + e).join('<br>');
            div.classList.remove('hidden');
        }
    });
});

// ── Submit timesheet ───────────────────────────────────
function submitTimesheet() {
    if (!confirm("Submit this timesheet for approval? You won't be able to edit it after submission.")) return;
    fetch('<?= BASE_URL ?>/?page=timesheet_ajax&action=submit_timesheet&timesheetId=<?= $timesheetId ?>', { method:'POST' })
        .then(r => r.json())
        .then(d => { if (d.success) { alert('Submitted!'); location.reload(); } else alert('Error: ' + d.message); });
}

// ── Delete entry ───────────────────────────────────────
function deleteEntry(entryId, timesheetId) {
    if (!confirm('Delete this time entry?')) return;
    fetch('<?= BASE_URL ?>/?page=timesheet_ajax&action=delete_entry', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ entryId, timesheetId })
    })
    .then(r => r.json())
    .then(d => { if (d.success) location.reload(); else alert('Error: ' + (d.message || 'Could not delete')); });
}

// ── Drag & Drop ────────────────────────────────────────
let draggedId = null;

function handleDragStart(e) {
    draggedId = e.currentTarget.dataset.id;
    e.currentTarget.classList.add('tc-dragging');
    e.dataTransfer.effectAllowed = 'move';
}

function handleDrop(e, newDate) {
    e.preventDefault();
    document.querySelectorAll('.ts-day-col').forEach(c => c.classList.remove('ts-drag-over'));
    if (!draggedId) return;

    const card = document.getElementById('card-' + draggedId);
    if (!card || card.dataset.date === newDate) return;

    fetch('<?= BASE_URL ?>/?page=timesheet_ajax&action=move_entry', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ entryId: draggedId, newDate, timesheetId: '<?= $timesheetId ?>' })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) location.reload();
        else alert('Could not move entry: ' + (d.message || ''));
    });
    draggedId = null;
}

document.addEventListener('dragend', () => {
    document.querySelectorAll('.ticket-card').forEach(c => c.classList.remove('tc-dragging'));
    document.querySelectorAll('.ts-day-col').forEach(c => c.classList.remove('ts-drag-over'));
});
</script>
