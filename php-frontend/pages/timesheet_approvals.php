<?php
/**
 * Timesheet Approvals - Admin Page
 * For managers to review and approve/reject submitted timesheets
 */

if (!canApproveTimesheets()) {
    setFlash('error', 'Access denied. Admin, Super Admin, or Ultra Super Admin only.');
    redirect('/dashboard');
}

require_once __DIR__ . '/../includes/TimesheetModel.php';

$model = new TimesheetModel();

// Get filter status
$statusFilter = $_GET['status'] ?? 'Submitted';

// Get timesheets based on filter
if ($statusFilter === 'all') {
    $timesheets = $model->getAllTimesheets(100);
} else {
    $timesheets = $model->getTimesheetsByStatus($statusFilter);
}

// Get user details for each timesheet
$users = $model->api->listDocuments('users');
$userMap = [];
foreach ($users as $u) {
    $userMap[$u['uid'] ?? $u['id']] = $u;
}

// Status colors
$statusColors = [
    'Draft' => 'bg-gray-100 text-gray-700',
    'Submitted' => 'bg-blue-100 text-blue-700',
    'Approved' => 'bg-green-100 text-green-700',
    'Rejected' => 'bg-red-100 text-red-700',
];

// Format date helper
function formatDate($date) {
    if (!$date) return 'N/A';
    if (is_string($date)) return date('M d, Y', strtotime($date));
    if (is_array($date) && isset($date['seconds'])) return date('M d, Y', $date['seconds']);
    return 'N/A';
}
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold text-sn-dark">Timesheet Approvals</h1>
            <p class="text-muted-foreground">Review and manage employee timesheets</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-sm text-muted-foreground">Filter:</span>
            <select onchange="window.location.href='<?= BASE_URL ?>/?page=timesheet_approvals&status='+this.value" 
                    class="p-2 border border-border rounded text-sm">
                <option value="Submitted" <?= $statusFilter === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                <option value="Approved" <?= $statusFilter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                <option value="Rejected" <?= $statusFilter === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="Draft" <?= $statusFilter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
            </select>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <?php
        $counts = [
            'Submitted' => count($model->getTimesheetsByStatus('Submitted')),
            'Approved' => count($model->getTimesheetsByStatus('Approved')),
            'Rejected' => count($model->getTimesheetsByStatus('Rejected')),
            'Draft' => count($model->getTimesheetsByStatus('Draft')),
        ];
        ?>
        <div class="card p-4">
            <div class="text-sm text-muted-foreground">Pending</div>
            <div class="text-2xl font-bold text-blue-600"><?= $counts['Submitted'] ?></div>
        </div>
        <div class="card p-4">
            <div class="text-sm text-muted-foreground">Approved</div>
            <div class="text-2xl font-bold text-green-600"><?= $counts['Approved'] ?></div>
        </div>
        <div class="card p-4">
            <div class="text-sm text-muted-foreground">Rejected</div>
            <div class="text-2xl font-bold text-red-600"><?= $counts['Rejected'] ?></div>
        </div>
        <div class="card p-4">
            <div class="text-sm text-muted-foreground">Draft</div>
            <div class="text-2xl font-bold text-gray-600"><?= $counts['Draft'] ?></div>
        </div>
    </div>

    <!-- Timesheets Table -->
    <div class="card overflow-hidden">
        <div class="p-4 border-b border-border bg-muted/30">
            <span class="text-sm font-bold">Timesheets (<?= count($timesheets) ?>)</span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-muted/50 border-b border-border">
                        <th class="data-table-header p-3">Employee</th>
                        <th class="data-table-header p-3">Week</th>
                        <th class="data-table-header p-3">Total Hours</th>
                        <th class="data-table-header p-3">Status</th>
                        <th class="data-table-header p-3">Submitted</th>
                        <th class="data-table-header p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($timesheets)): ?>
                    <tr>
                        <td colspan="6" class="p-8 text-center text-muted-foreground">
                            No timesheets found for selected filter.
                        </td>
                    </tr>
                    <?php else: 
                        foreach ($timesheets as $ts):
                            $userId = $ts['userId'] ?? '';
                            $user = $userMap[$userId] ?? null;
                            $status = $ts['status'] ?? 'Draft';
                    ?>
                    <tr class="border-b border-border hover:bg-muted/10">
                        <td class="p-3">
                            <div class="font-medium"><?= htmlspecialchars($user['name'] ?? 'Unknown') ?></div>
                            <div class="text-xs text-muted-foreground"><?= htmlspecialchars($user['email'] ?? '') ?></div>
                        </td>
                        <td class="p-3">
                            <div class="font-medium"><?= formatDate($ts['weekStart']) ?></div>
                            <div class="text-xs text-muted-foreground">to <?= formatDate($ts['weekEnd']) ?></div>
                        </td>
                        <td class="p-3 font-bold"><?= number_format($ts['totalHours'] ?? 0, 2) ?> hrs</td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded text-xs font-semibold <?= $statusColors[$status] ?? '' ?>">
                                <?= $status ?>
                            </span>
                        </td>
                        <td class="p-3 text-sm text-muted-foreground">
                            <?= formatDate($ts['submittedAt'] ?? null) ?>
                        </td>
                        <td class="p-3">
                            <button onclick="viewTimesheet('<?= $ts['id'] ?>')" class="btn btn-sm btn-outline mr-1">
                                <i data-lucide="eye" class="w-3 h-3 mr-1"></i>
                                View
                            </button>
                            <?php if ($status === 'Submitted'): ?>
                            <button onclick="approveTimesheet('<?= $ts['id'] ?>', '<?= htmlspecialchars($user['name'] ?? 'Unknown') ?>')" 
                                    class="btn btn-sm btn-primary mr-1">
                                <i data-lucide="check" class="w-3 h-3 mr-1"></i>
                                Approve
                            </button>
                            <button onclick="rejectTimesheet('<?= $ts['id'] ?>', '<?= htmlspecialchars($user['name'] ?? 'Unknown') ?>')" 
                                    class="btn btn-sm btn-outline text-red-600 border-red-200 hover:bg-red-50">
                                <i data-lucide="x" class="w-3 h-3 mr-1"></i>
                                Reject
                            </button>
                            <?php elseif ($status === 'Rejected'): ?>
                            <button onclick="reopenTimesheet('<?= $ts['id'] ?>')" 
                                    class="btn btn-sm btn-outline">
                                <i data-lucide="rotate-ccw" class="w-3 h-3 mr-1"></i>
                                Reopen
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Timesheet Modal -->
<div id="viewModal" class="modal-backdrop hidden">
    <div class="modal" style="max-width: 800px;">
        <div class="p-4 border-b border-border flex items-center justify-between bg-muted/30">
            <h3 class="font-semibold">Timesheet Details</h3>
            <button onclick="closeViewModal()" class="p-1 hover:bg-muted rounded">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div id="viewContent" class="p-6">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal-backdrop hidden">
    <div class="modal" style="max-width: 400px;">
        <div class="p-4 border-b border-border flex items-center justify-between bg-muted/30">
            <h3 class="font-semibold text-red-600">Reject Timesheet</h3>
            <button onclick="closeRejectModal()" class="p-1 hover:bg-muted rounded">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="p-6 space-y-4">
            <p class="text-sm">Please provide a reason for rejection. This will be visible to the employee.</p>
            <textarea id="rejectReason" rows="3" class="w-full p-2 border border-red-200 rounded text-sm resize-none" 
                      placeholder="Enter rejection reason..."></textarea>
            <div class="flex justify-end gap-3">
                <button onclick="closeRejectModal()" class="btn btn-outline">Cancel</button>
                <button onclick="confirmReject()" class="btn bg-red-600 text-white hover:bg-red-700">
                    <i data-lucide="x" class="w-4 h-4 mr-2"></i>
                    Reject
                </button>
            </div>
        </div>
    </div>
</div>

<script>
lucide.createIcons();

let currentTimesheetId = null;

function viewTimesheet(timesheetId) {
    currentTimesheetId = timesheetId;
    document.getElementById('viewContent').innerHTML = '<div class="text-center py-8 text-muted-foreground"><i data-lucide="loader" class="w-6 h-6 animate-spin mx-auto mb-2"></i>Loading...</div>';
    document.getElementById('viewModal').classList.remove('hidden');
    lucide.createIcons();
    
    fetch('<?= BASE_URL ?>/?page=timesheet_ajax&action=get_report_data&type=detail&timesheetId=' + timesheetId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('viewContent').innerHTML = '<p class="text-red-500">Failed to load timesheet.</p>';
                return;
            }
            const ts = data.data;
            const statusClass = {Approved:'bg-green-100 text-green-700', Rejected:'bg-red-100 text-red-700', Submitted:'bg-blue-100 text-blue-700', Draft:'bg-gray-100 text-gray-700'};
            
            let cardsHtml = (ts.timeCards || []).map(c => `
                <tr class="border-b border-border">
                    <td class="p-2 text-sm">${c.entryDate ? c.entryDate.substring(0,10) : ''}</td>
                    <td class="p-2 text-sm font-medium">${c.taskName || 'Unknown'}</td>
                    <td class="p-2 text-sm text-right font-bold">${parseFloat(c.hoursWorked||0).toFixed(1)} hrs</td>
                    <td class="p-2 text-sm text-muted-foreground">${c.description || '-'}</td>
                </tr>
            `).join('');
            
            let historyHtml = (ts.history || []).map(h => `
                <div class="flex items-start gap-2 text-xs py-1 border-b border-border last:border-0">
                    <span class="text-muted-foreground w-32 shrink-0">${h.actionAt ? h.actionAt.substring(0,10) : ''}</span>
                    <span class="font-medium">${h.action}</span>
                    ${h.comments ? `<span class="text-muted-foreground">— ${h.comments}</span>` : ''}
                </div>
            `).join('');
            
            document.getElementById('viewContent').innerHTML = `
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4 text-sm bg-muted/30 p-4 rounded">
                        <div><span class="text-muted-foreground">Status:</span> <span class="px-2 py-0.5 rounded text-xs font-semibold ${statusClass[ts.status]||''}">${ts.status}</span></div>
                        <div><span class="text-muted-foreground">Total Hours:</span> <strong>${parseFloat(ts.totalHours||0).toFixed(2)} hrs</strong></div>
                        <div><span class="text-muted-foreground">Week:</span> ${(ts.weekStart||'').substring(0,10)} → ${(ts.weekEnd||'').substring(0,10)}</div>
                        <div><span class="text-muted-foreground">Submitted:</span> ${ts.submittedAt ? ts.submittedAt.substring(0,10) : 'N/A'}</div>
                        ${ts.rejectionReason ? `<div class="col-span-2 text-red-600"><span class="font-medium">Rejection Reason:</span> ${ts.rejectionReason}</div>` : ''}
                    </div>
                    <div>
                        <h4 class="font-semibold mb-2">Time Entries (${(ts.timeCards||[]).length})</h4>
                        <table class="w-full text-sm border border-border rounded overflow-hidden">
                            <thead><tr class="bg-muted/50"><th class="p-2 text-left">Date</th><th class="p-2 text-left">Task</th><th class="p-2 text-right">Hours</th><th class="p-2 text-left">Notes</th></tr></thead>
                            <tbody>${cardsHtml || '<tr><td colspan="4" class="p-4 text-center text-muted-foreground">No entries</td></tr>'}</tbody>
                        </table>
                    </div>
                    ${historyHtml ? `<div><h4 class="font-semibold mb-2">Approval History</h4><div class="bg-muted/20 rounded p-3">${historyHtml}</div></div>` : ''}
                </div>
            `;
        })
        .catch(() => {
            document.getElementById('viewContent').innerHTML = '<p class="text-red-500">Error loading timesheet details.</p>';
        });
}

function closeViewModal() {
    document.getElementById('viewModal').classList.add('hidden');
    currentTimesheetId = null;
}

function approveTimesheet(timesheetId, userName) {
    if (!confirm('Approve timesheet for ' + userName + '?')) return;
    
    fetch('<?= BASE_URL ?>/?page=timesheet_ajax&action=approve_timesheet', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ timesheetId: timesheetId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Timesheet approved successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function rejectTimesheet(timesheetId, userName) {
    currentTimesheetId = timesheetId;
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.getElementById('rejectReason').value = '';
    currentTimesheetId = null;
}

function confirmReject() {
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) {
        alert('Please provide a rejection reason.');
        return;
    }
    
    fetch('<?= BASE_URL ?>/?page=timesheet_ajax&action=reject_timesheet', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ timesheetId: currentTimesheetId, reason: reason })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Timesheet rejected.');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function reopenTimesheet(timesheetId) {
    if (!confirm('Reopen this timesheet for editing?')) return;
    
    fetch('<?= BASE_URL ?>/?page=timesheet_ajax&action=reopen_timesheet', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ timesheetId: timesheetId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Timesheet reopened.');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

// Close modals on backdrop click
document.getElementById('viewModal').addEventListener('click', function(e) {
    if (e.target === this) closeViewModal();
});
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});
</script>
