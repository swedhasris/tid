<?php
/**
 * Tickets Page - PHP Version
 * Replicates React Tickets.tsx
 * 
 * @var array $tickets List of tickets
 * @var array $agents List of agents
 * @var string $action Current action (new, etc.)
 * @var string $filter Current filter
 */

// Filter tickets
$filteredTickets = array_filter($tickets, function($t) use ($filter) {
    $user = getCurrentUser();
    
    switch ($filter) {
        case 'assigned_to_me':
            return ($t['assignedTo'] ?? '') === ($user['uid'] ?? '');
        case 'open':
            return !in_array($t['status'] ?? '', ['Resolved', 'Closed']);
        case 'unassigned':
            return empty($t['assignedTo']);
        case 'resolved':
            return in_array($t['status'] ?? '', ['Resolved', 'Closed']);
        default:
            return true;
    }
});

// Sort by createdAt desc
usort($filteredTickets, function($a, $b) {
    $aTime = $a['createdAt'] ?? '';
    $bTime = $b['createdAt'] ?? '';
    if (is_array($aTime) && isset($aTime['seconds'])) $aTime = $aTime['seconds'];
    if (is_array($bTime) && isset($bTime['seconds'])) $bTime = $bTime['seconds'];
    return strtotime($bTime) <=> strtotime($aTime);
});

// Get SLA policies for calculations
try {
    $slaPolicies = $api->listDocuments('sla_policies');
} catch (Exception $e) {
    $slaPolicies = [];
}

function calculateSLAStatus($deadline, $metAt, $isPaused) {
    if ($metAt) return ['status' => 'met', 'display' => 'MET ✓', 'class' => 'text-green-600', 'badge' => 'badge-low'];
    
    if (empty($deadline)) return ['status' => 'none', 'display' => '—', 'class' => 'text-muted-foreground', 'badge' => ''];

    $now          = time();
    $deadlineTime = strtotime($deadline);

    if ($deadlineTime === false) return ['status' => 'none', 'display' => '—', 'class' => 'text-muted-foreground', 'badge' => ''];

    if ($deadlineTime <= $now) {
        $over = $now - $deadlineTime;
        $h = floor($over / 3600);
        $m = floor(($over % 3600) / 60);
        $s = $over % 60;
        return ['status' => 'breached', 'display' => sprintf('-%02d:%02d:%02d', $h, $m, $s), 'class' => 'text-red-600 font-mono font-bold', 'badge' => 'badge-critical'];
    }

    if ($isPaused) {
        $diff = $deadlineTime - $now;
        $h = floor($diff / 3600);
        $m = floor(($diff % 3600) / 60);
        $s = $diff % 60;
        return ['status' => 'paused', 'display' => sprintf('%02d:%02d:%02d', $h, $m, $s), 'class' => 'text-orange-500 font-mono font-bold', 'badge' => 'badge-moderate'];
    }

    $diff = $deadlineTime - $now;
    $h = floor($diff / 3600);
    $m = floor(($diff % 3600) / 60);
    $s = $diff % 60;
    $display = sprintf('%02d:%02d:%02d', $h, $m, $s);

    // At Risk = less than 20% time remaining
    $totalTime = $deadlineTime - ($now - ($deadlineTime - $now)); // approximate
    $pct = ($diff / max($deadlineTime - ($now - 86400), 1)) * 100;

    if ($diff < 3600) { // less than 1 hour = at risk
        return ['status' => 'at_risk', 'display' => $display, 'class' => 'text-yellow-600 font-mono font-bold', 'badge' => 'badge-high', 'deadline_ts' => $deadlineTime];
    }

    return ['status' => 'active', 'display' => $display, 'class' => 'text-blue-600 font-mono font-bold', 'badge' => 'badge-low', 'deadline_ts' => $deadlineTime];
}
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">
                <?= $filter ? ucfirst(str_replace('_', ' ', $filter)) . ' Tickets' : 'All Tickets' ?>
            </h1>
            <p class="text-muted-foreground">Manage and track IT support requests.</p>
        </div>
        <button onclick="openModal()" class="btn btn-primary">
            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Create Ticket
        </button>
    </div>

    <!-- Filters -->
    <div class="card overflow-hidden p-0">
        <div class="p-4 border-b border-border flex items-center justify-between bg-muted/30">
            <div class="flex items-center gap-4">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"></i>
                    <input 
                        type="text" 
                        id="tableSearch"
                        placeholder="Search tickets..." 
                        onkeyup="filterTable()"
                        class="pl-9 pr-4 py-2 bg-white border border-border rounded-md text-sm w-64 focus:ring-2 focus:ring-sn-green outline-none"
                    />
                </div>
                <?php if ($filter): ?>
                <a href="<?= BASE_URL ?>/?page=tickets" class="text-sm text-muted-foreground hover:text-foreground">
                    <i data-lucide="x" class="w-4 h-4 inline"></i> Clear Filter
                </a>
                <?php endif; ?>
            </div>
            <div class="text-sm text-muted-foreground">
                Showing <?= count($filteredTickets) ?> tickets
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full" id="ticketsTable">
                <thead>
                    <tr class="bg-muted/50 border-b border-border">
                        <th class="data-table-header p-2">Number</th>
                        <th class="data-table-header p-2">Short Description</th>
                        <th class="data-table-header p-2">Reporting User</th>
                        <th class="data-table-header p-2">Priority</th>
                        <th class="data-table-header p-2">State</th>
                        <th class="data-table-header p-2">Category</th>
                        <th class="data-table-header p-2">Assignment</th>
                        <th class="data-table-header p-2">Assigned To</th>
                        <th class="data-table-header p-2">Task</th>
                        <th class="data-table-header p-2">SLA</th>
                    </tr>
                    <tr class="bg-white border-b border-border">
                        <td class="p-1.5"><input placeholder="Filter" class="w-full p-1 border border-border rounded text-[11px]" onkeyup="filterColumn(0, this.value)"></td>
                        <td class="p-1.5"><input placeholder="Filter" class="w-full p-1 border border-border rounded text-[11px]" onkeyup="filterColumn(1, this.value)"></td>
                        <td class="p-1.5"><input placeholder="Filter" class="w-full p-1 border border-border rounded text-[11px]" onkeyup="filterColumn(2, this.value)"></td>
                        <td class="p-1.5"><input placeholder="Filter" class="w-full p-1 border border-border rounded text-[11px]" onkeyup="filterColumn(3, this.value)"></td>
                        <td class="p-1.5"><input placeholder="Filter" class="w-full p-1 border border-border rounded text-[11px]" onkeyup="filterColumn(4, this.value)"></td>
                        <td class="p-1.5"><input placeholder="Filter" class="w-full p-1 border border-border rounded text-[11px]" onkeyup="filterColumn(5, this.value)"></td>
                        <td class="p-1.5"><input placeholder="Filter" class="w-full p-1 border border-border rounded text-[11px]" onkeyup="filterColumn(6, this.value)"></td>
                        <td class="p-1.5"><input placeholder="Filter" class="w-full p-1 border border-border rounded text-[11px]" onkeyup="filterColumn(7, this.value)"></td>
                        <td class="p-1.5"><input placeholder="Filter" class="w-full p-1 border border-border rounded text-[11px]" onkeyup="filterColumn(8, this.value)"></td>
                        <td class="p-1.5"></td>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredTickets)): ?>
                    <tr>
                        <td colspan="9" class="p-8 text-center text-muted-foreground">
                            No tickets found.
                            <?php if ($filter): ?>
                            <a href="<?= BASE_URL ?>/?page=tickets" class="text-blue-600 hover:underline ml-1">View all tickets</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: 
                        foreach ($filteredTickets as $ticket): 
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
                            
                            $isPaused = in_array($ticket['status'] ?? '', ['On Hold', 'Waiting for Customer']);
                    ?>
                    <tr class="data-table-row border-b border-border">
                        <td class="p-2">
                            <a href="<?= BASE_URL ?>/?page=ticket&id=<?= $ticket['id'] ?>" class="font-mono text-[11px] font-bold text-blue-600 hover:underline">
                                <?= htmlspecialchars($ticket['number'] ?? substr($ticket['id'], 0, 8)) ?>
                            </a>
                        </td>
                        <td class="p-2 text-[11px] font-medium"><?= htmlspecialchars($ticket['title'] ?? '') ?></td>
                        <td class="p-2 text-[11px]"><?= htmlspecialchars($ticket['caller'] ?? '') ?></td>
                        <td class="p-2">
                            <span class="badge <?= $badgeClass ?>"><?= $priority ?></span>
                        </td>
                        <td class="p-2 text-[11px]"><?= $ticket['status'] ?? 'New' ?></td>
                        <td class="p-2 text-[11px]"><?= htmlspecialchars($ticket['category'] ?? '') ?></td>
                        <td class="p-2 text-[11px]"><?= htmlspecialchars($ticket['assignmentGroup'] ?? '(empty)') ?></td>
                        <td class="p-2 text-[11px]"><?= htmlspecialchars($assignedAgent['name'] ?? '(empty)') ?></td>
                        <td class="p-2 text-[11px]">
                            <?php if (!empty($ticket['taskId'])): ?>
                            <a href="<?= BASE_URL ?>/?page=task_view&id=<?= urlencode($ticket['taskId']) ?>"
                               class="inline-flex items-center gap-1 text-blue-600 hover:underline font-medium">
                                <i data-lucide="link" class="w-3 h-3"></i>
                                <?= htmlspecialchars($ticket['taskName'] ?? $ticket['taskId']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted-foreground italic">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-2">
                            <div class="flex flex-col gap-1 min-w-[90px]">
                                <?php
                                $isPaused = in_array($ticket['status'] ?? '', ['On Hold', 'Waiting for Customer']);
                                $slaResp = calculateSLAStatus($ticket['responseDeadline'] ?? '', $ticket['firstResponseAt'] ?? '', $isPaused);
                                $slaRes  = calculateSLAStatus($ticket['resolutionDeadline'] ?? '', $ticket['resolvedAt'] ?? '', $isPaused);
                                ?>
                                <div class="flex items-center gap-1">
                                    <span class="text-[9px] uppercase text-muted-foreground font-bold w-8">Resp</span>
                                    <span class="text-[11px] font-mono font-bold <?= $slaResp['class'] ?>"
                                          <?php if (in_array($slaResp['status'], ['active','at_risk']) && !empty($ticket['responseDeadline'])): ?>
                                          data-deadline="<?= strtotime($ticket['responseDeadline']) ?>"
                                          data-sla-timer="resp-<?= $ticket['id'] ?>"
                                          <?php endif; ?>>
                                        <?= $slaResp['display'] ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <span class="text-[9px] uppercase text-muted-foreground font-bold w-8">Res</span>
                                    <span class="text-[11px] font-mono font-bold <?= $slaRes['class'] ?>"
                                          <?php if (in_array($slaRes['status'], ['active','at_risk']) && !empty($ticket['resolutionDeadline'])): ?>
                                          data-deadline="<?= strtotime($ticket['resolutionDeadline']) ?>"
                                          data-sla-timer="res-<?= $ticket['id'] ?>"
                                          <?php endif; ?>>
                                        <?= $slaRes['display'] ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Ticket Modal -->
<div id="createModal" class="modal-backdrop hidden">
    <div class="modal">
        <div class="p-4 border-b border-border flex items-center justify-between bg-muted/30">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-muted-foreground">Incident</span>
                <span class="text-sm font-bold">New Record</span>
            </div>
            <button onclick="closeModal()" class="p-1 hover:bg-muted rounded">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <form method="POST" action="<?= BASE_URL ?>/?page=tickets&action=create" class="p-6 overflow-y-auto max-h-[70vh]">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4">
                <!-- Left Column -->
                <div class="space-y-4">
                    <div class="grid grid-cols-3 items-center gap-4">
                        <label class="text-[11px] text-right font-medium text-muted-foreground uppercase">Number</label>
                        <input disabled class="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs font-mono" id="incNumber" value="" />
                    </div>
                    <div class="grid grid-cols-3 items-center gap-4">
                        <label class="text-[11px] text-right font-medium uppercase flex items-center justify-end gap-1">
                            <span class="text-red-500">*</span> Reporting User
                        </label>
                        <div class="col-span-2 flex gap-1">
                            <input name="caller" required placeholder="Who is reporting this?" class="flex-grow p-1.5 border border-border rounded text-xs h-8" />
                        </div>
                    </div>
                    <div class="grid grid-cols-3 items-center gap-4">
                        <label class="text-[11px] text-right font-medium text-muted-foreground uppercase flex items-center justify-end gap-1">
                            Affected User
                        </label>
                        <div class="col-span-2 flex gap-1">
                            <input name="affectedUser" placeholder="Who is affected? (if different)" class="flex-grow p-1.5 border border-border rounded text-xs h-8" />
                        </div>
                    </div>
                    <div class="grid grid-cols-3 items-center gap-4">
                        <label class="text-[11px] text-right font-medium text-muted-foreground uppercase">Category</label>
                        <select name="category" class="col-span-2 p-1.5 border border-border rounded text-xs h-8">
                            <option>Inquiry / Help</option>
                            <option>Software</option>
                            <option>Hardware</option>
                            <option>Network</option>
                            <option>Database</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-3 items-center gap-4">
                        <label class="text-[11px] text-right font-medium text-muted-foreground uppercase">Subcategory</label>
                        <select name="subcategory" class="col-span-2 p-1.5 border border-border rounded text-xs h-8">
                            <option value="">-- None --</option>
                            <option>Antivirus</option>
                            <option>Email</option>
                            <option>Operating System</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-3 items-center gap-4">
                        <label class="text-[11px] text-right font-medium text-muted-foreground uppercase">Service</label>
                        <select name="service" class="col-span-2 p-1.5 border border-border rounded text-xs h-8">
                            <option value="">-- Select Service --</option>
                            <option value="Email Service">Email Service</option>
                            <option value="VPN / Remote Access">VPN / Remote Access</option>
                            <option value="Internet Connectivity">Internet Connectivity</option>
                            <option value="Active Directory / Login">Active Directory / Login</option>
                            <option value="File Storage / Shared Drive">File Storage / Shared Drive</option>
                            <option value="Printing Service">Printing Service</option>
                            <option value="ERP / Business Application">ERP / Business Application</option>
                            <option value="IT Security">IT Security</option>
                            <option value="Hardware Repair">Hardware Repair</option>
                            <option value="Software Installation">Software Installation</option>
                            <option value="Database Service">Database Service</option>
                            <option value="Cloud Services">Cloud Services</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-3 items-center gap-4">
                        <label class="text-[11px] text-right font-medium text-muted-foreground uppercase">Config Item</label>
                        <input name="cmdbItem" class="col-span-2 p-1.5 border border-border rounded text-xs h-8" />
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-4">
                    <div class="grid grid-cols-3 items-center gap-4">
                        <label class="text-[11px] text-right font-medium text-muted-foreground uppercase">State</label>
                        <select disabled class="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs h-8">
                            <option>New</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-3 items-center gap-4">
                        <label class="text-[11px] text-right font-medium text-muted-foreground uppercase">Impact</label>
                        <select name="impact" id="impact" onchange="updatePriority()" class="col-span-2 p-1.5 border border-border rounded text-xs h-8">
                            <option value="1 - High">1 - High</option>
                            <option value="2 - Medium" selected>2 - Medium</option>
                            <option value="3 - Low">3 - Low</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-3 items-center gap-4">
                        <label class="text-[11px] text-right font-medium text-muted-foreground uppercase">Urgency</label>
                        <select name="urgency" id="urgency" onchange="updatePriority()" class="col-span-2 p-1.5 border border-border rounded text-xs h-8">
                            <option value="1 - High">1 - High</option>
                            <option value="2 - Medium" selected>2 - Medium</option>
                            <option value="3 - Low">3 - Low</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-3 items-center gap-4">
                        <label class="text-[11px] text-right font-medium text-muted-foreground uppercase">Priority</label>
                        <input id="priority" disabled class="col-span-2 p-1.5 bg-muted/30 border border-border rounded text-xs font-bold text-blue-600 h-8" value="3 - Moderate" />
                    </div>
                    <div class="grid grid-cols-3 items-center gap-4">
                        <label class="text-[11px] text-right font-medium text-muted-foreground uppercase">Assign Group</label>
                        <select name="assignmentGroup" class="col-span-2 p-1.5 border border-border rounded text-xs h-8">
                            <option value="">-- Auto Assign --</option>
                            <option value="Service Desk">Service Desk</option>
                            <option value="Network Team">Network Team</option>
                            <option value="Hardware Support">Hardware Support</option>
                            <option value="App Support">App Support</option>
                            <option value="DBA Team">DBA Team</option>
                            <option value="Security Team">Security Team</option>
                            <option value="Infrastructure Team">Infrastructure Team</option>
                            <option value="DevOps Team">DevOps Team</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-3 items-center gap-4">
                        <label class="text-[11px] text-right font-medium text-muted-foreground uppercase">Assigned to</label>
                        <select name="assignedTo" class="col-span-2 p-1.5 border border-border rounded text-xs h-8">
                            <option value="">-- Auto Assign --</option>
                            <?php foreach ($agents as $agent): ?>
                            <option value="<?= $agent['uid'] ?? $agent['id'] ?>"><?= htmlspecialchars($agent['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Full Width Fields -->
            <div class="mt-8 space-y-4">
                <!-- Task Link (mandatory) -->
                <div class="grid grid-cols-6 items-center gap-4">
                    <label class="text-[11px] text-right font-medium uppercase flex items-center justify-end gap-1">
                        <span class="text-red-500">*</span> Linked Task
                    </label>
                    <div class="col-span-5">
                        <select name="taskId" id="taskId" required
                                class="w-full p-1.5 border-2 border-sn-green rounded text-xs h-8 bg-white focus:ring-2 focus:ring-sn-green outline-none">
                            <option value="">-- Select a Task (required) --</option>
                            <?php foreach (($tasks ?? []) as $task): ?>
                            <option value="<?= htmlspecialchars($task['id']) ?>">
                                <?= htmlspecialchars($task['name']) ?>
                                <?php if (!empty($task['category'])): ?> · <?= htmlspecialchars($task['category']) ?><?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-[10px] text-muted-foreground mt-1">
                            <i data-lucide="link" class="w-3 h-3 inline"></i>
                            Every ticket must be linked to a task.
                            <a href="<?= BASE_URL ?>/?page=tasks" target="_blank" class="text-blue-600 hover:underline ml-1">Browse tasks →</a>
                        </p>
                    </div>
                </div>
                <div class="grid grid-cols-6 items-center gap-4">
                    <label class="text-[11px] text-right font-medium uppercase flex items-center justify-end gap-1">
                        <span class="text-red-500">*</span> Short description
                    </label>
                    <input name="title" required class="col-span-5 p-1.5 border border-border rounded text-xs h-8" />
                </div>
                <div class="grid grid-cols-6 items-start gap-4">
                    <label class="text-[11px] text-right font-medium uppercase mt-1">Description</label>
                    <textarea name="description" rows="4" class="col-span-5 p-1.5 border border-border rounded text-xs resize-none h-32"></textarea>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end gap-3 pt-6 border-t border-border mt-8">
                <button type="button" onclick="closeModal()" class="px-6 h-8 text-[11px] font-bold uppercase tracking-wider btn-outline">
                    Cancel
                </button>
                <button type="submit" class="bg-sn-green text-sn-dark hover:opacity-90 px-8 h-8 text-[11px] font-bold uppercase tracking-wider shadow-sm btn-primary">
                    Submit
                </button>
            </div>
        </form>
    </div>
</div>

<script>
lucide.createIcons();

// Modal functions
function openModal() {
    document.getElementById('createModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    // Generate preview INC number
    const num = 'INC' + (Math.floor(1000000 + Math.random() * 9000000));
    document.getElementById('incNumber').value = num;
}

function closeModal() {
    document.getElementById('createModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Close modal on backdrop click
document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// Auto-select assignment group based on category
document.querySelector('select[name="category"]').addEventListener('change', function() {
    const map = {
        'Network':        'Network Team',
        'Hardware':       'Hardware Support',
        'Software':       'App Support',
        'Database':       'DBA Team',
        'Inquiry / Help': 'Service Desk',
    };
    const group = map[this.value] || 'Service Desk';
    const sel = document.querySelector('select[name="assignmentGroup"]');
    if (sel) sel.value = group;
});

// Priority calculation
function updatePriority() {
    const impact = document.getElementById('impact').value;
    const urgency = document.getElementById('urgency').value;
    
    const i = parseInt(impact[0]);
    const u = parseInt(urgency[0]);
    const sum = i + u;
    
    let priority = '4 - Low';
    if (sum <= 2) priority = '1 - Critical';
    else if (sum === 3) priority = '2 - High';
    else if (sum === 4) priority = '3 - Moderate';
    
    document.getElementById('priority').value = priority;
}

// Table filtering
function filterTable() {
    const search = document.getElementById('tableSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#ticketsTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
}

function filterColumn(colIndex, value) {
    const rows = document.querySelectorAll('#ticketsTable tbody tr');
    const lowerValue = value.toLowerCase();
    
    rows.forEach(row => {
        const cell = row.cells[colIndex];
        if (!cell) return;
        
        const cellText = cell.textContent.toLowerCase();
        const matches = cellText.includes(lowerValue);
        
        // Check if row should be shown based on all column filters
        let showRow = matches;
        const inputs = document.querySelectorAll('#ticketsTable thead tr:nth-child(2) input');
        inputs.forEach((input, idx) => {
            if (idx !== colIndex && input.value) {
                const otherCell = row.cells[idx];
                if (otherCell && !otherCell.textContent.toLowerCase().includes(input.value.toLowerCase())) {
                    showRow = false;
                }
            }
        });
        
        row.style.display = showRow ? '' : 'none';
    });
}

// Auto-open modal if action=new
<?php if ($action === 'new'): ?>
openModal();
<?php endif; ?>

// Live SLA countdown for PHP tickets table
(function() {
    function updateSlaTimers() {
        document.querySelectorAll('[data-sla-timer]').forEach(function(el) {
            var deadlineTs = parseInt(el.getAttribute('data-deadline'), 10);
            if (!deadlineTs) return;
            var now = Math.floor(Date.now() / 1000);
            var diff = deadlineTs - now;
            if (diff <= 0) {
                var over = Math.abs(diff);
                var h = Math.floor(over / 3600);
                var m = Math.floor((over % 3600) / 60);
                var s = over % 60;
                el.textContent = '-' + pad(h) + ':' + pad(m) + ':' + pad(s);
                el.className = el.className.replace(/text-blue-600|text-yellow-600/g, 'text-red-600');
            } else {
                var h = Math.floor(diff / 3600);
                var m = Math.floor((diff % 3600) / 60);
                var s = diff % 60;
                el.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
                if (diff < 3600 && !el.className.includes('text-red-600')) {
                    el.className = el.className.replace('text-blue-600', 'text-yellow-600');
                }
            }
        });
    }
    function pad(n) { return String(n).padStart(2, '0'); }
    updateSlaTimers();
    setInterval(updateSlaTimers, 1000);
})();
</script>
