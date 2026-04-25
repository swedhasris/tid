<?php
/**
 * Timesheet Reports - Analytics and Charts
 */

require_once __DIR__ . '/../includes/TimesheetModel.php';

$user = getCurrentUser();
$userId = $user['uid'] ?? '';
$model = new TimesheetModel();

// Date range (default: last 4 weeks)
$endDate = $_GET['end'] ?? date('Y-m-d');
$startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-4 weeks', strtotime($endDate)));

// Get user timesheets
$timesheets = $model->getUserHoursByWeek($userId, $startDate, $endDate);

// Get hours by task for pie chart
$hoursByTask = $model->getHoursByTask($userId, $startDate, $endDate);

// Calculate statistics
$totalHours = array_sum(array_column($timesheets, 'totalHours'));
$totalWeeks = count($timesheets);
$avgHoursPerWeek = $totalWeeks > 0 ? $totalHours / $totalWeeks : 0;

// Approved vs pending
$approvedHours = array_sum(array_filter(array_map(function($ts) {
    return ($ts['status'] ?? '') === 'Approved' ? ($ts['totalHours'] ?? 0) : 0;
}, $timesheets)));

// Admin: get all users summary
$allUsersSummary = [];
if (isAdmin()) {
    $allUsersSummary = $model->getAllUsersTimesheetSummary($startDate, $endDate);
    // Get user names
    $users = $model->api->listDocuments('users');
    foreach ($allUsersSummary as &$summary) {
        foreach ($users as $u) {
            if (($u['uid'] ?? $u['id']) === $summary['userId']) {
                $summary['userName'] = $u['name'] ?? 'Unknown';
                break;
            }
        }
    }
}

// Format data for charts
$weekLabels = [];
$weekHours = [];
foreach (array_reverse($timesheets) as $ts) {
    $weekStart = is_string($ts['weekStart']) ? substr($ts['weekStart'], 0, 10) : '';
    $weekLabels[] = date('M d', strtotime($weekStart));
    $weekHours[] = (float) ($ts['totalHours'] ?? 0);
}

$taskLabels = array_column($hoursByTask, 'taskName');
$taskHours = array_column($hoursByTask, 'totalHours');
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold text-sn-dark">Timesheet Reports</h1>
            <p class="text-muted-foreground">Analytics and insights for your logged hours</p>
        </div>
        <div class="flex items-center gap-2">
            <input type="date" id="startDate" value="<?= $startDate ?>" class="p-2 border border-border rounded text-sm">
            <span class="text-muted-foreground">to</span>
            <input type="date" id="endDate" value="<?= $endDate ?>" class="p-2 border border-border rounded text-sm">
            <button onclick="updateDateRange()" class="btn btn-primary">
                <i data-lucide="filter" class="w-4 h-4 mr-2"></i>
                Filter
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="card p-5">
            <div class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">Total Hours</div>
            <div class="text-3xl font-bold text-sn-dark mt-2"><?= number_format($totalHours, 1) ?></div>
            <div class="text-sm text-muted-foreground mt-1"><?= $totalWeeks ?> weeks</div>
        </div>
        <div class="card p-5">
            <div class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">Weekly Average</div>
            <div class="text-3xl font-bold text-blue-600 mt-2"><?= number_format($avgHoursPerWeek, 1) ?></div>
            <div class="text-sm text-muted-foreground mt-1">hours/week</div>
        </div>
        <div class="card p-5">
            <div class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">Approved Hours</div>
            <div class="text-3xl font-bold text-green-600 mt-2"><?= number_format($approvedHours, 1) ?></div>
            <div class="text-sm text-muted-foreground mt-1"><?= number_format($totalHours > 0 ? ($approvedHours/$totalHours)*100 : 0, 0) ?>% of total</div>
        </div>
        <div class="card p-5">
            <div class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">Tasks</div>
            <div class="text-3xl font-bold text-purple-600 mt-2"><?= count($hoursByTask) ?></div>
            <div class="text-sm text-muted-foreground mt-1">different tasks</div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Weekly Hours Bar Chart -->
        <div class="card p-6">
            <h3 class="font-semibold text-lg mb-4">Weekly Hours</h3>
            <div class="h-64 relative">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>

        <!-- Hours by Task Pie Chart -->
        <div class="card p-6">
            <h3 class="font-semibold text-lg mb-4">Hours by Task</h3>
            <div class="h-64 relative flex items-center justify-center">
                <canvas id="taskChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Hours by Task Table -->
    <div class="card overflow-hidden">
        <div class="p-4 border-b border-border bg-muted/30">
            <h3 class="font-semibold">Hours by Task Breakdown</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-muted/50 border-b border-border">
                        <th class="data-table-header p-3">Task</th>
                        <th class="data-table-header p-3 text-right">Total Hours</th>
                        <th class="data-table-header p-3 text-right">% of Total</th>
                        <th class="data-table-header p-3">Visual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $maxHours = !empty($taskHours) ? max($taskHours) : 1;
                    foreach ($hoursByTask as $task): 
                        $percentage = $totalHours > 0 ? ($task['totalHours'] / $totalHours) * 100 : 0;
                        $barWidth = $maxHours > 0 ? ($task['totalHours'] / $maxHours) * 100 : 0;
                    ?>
                    <tr class="border-b border-border hover:bg-muted/10">
                        <td class="p-3 font-medium"><?= htmlspecialchars($task['taskName']) ?></td>
                        <td class="p-3 text-right font-bold"><?= number_format($task['totalHours'], 2) ?> hrs</td>
                        <td class="p-3 text-right"><?= number_format($percentage, 1) ?>%</td>
                        <td class="p-3 w-48">
                            <div class="h-2 bg-muted rounded-full overflow-hidden">
                                <div class="h-full bg-sn-green rounded-full" style="width: <?= $barWidth ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($hoursByTask)): ?>
                    <tr>
                        <td colspan="4" class="p-8 text-center text-muted-foreground">
                            No task data available for selected date range.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Admin: All Users Summary -->
    <?php if (isAdmin() && !empty($allUsersSummary)): ?>
    <div class="card overflow-hidden">
        <div class="p-4 border-b border-border bg-muted/30">
            <h3 class="font-semibold">All Users Summary (<?= $startDate ?> to <?= $endDate ?>)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-muted/50 border-b border-border">
                        <th class="data-table-header p-3">User</th>
                        <th class="data-table-header p-3 text-right">Total Hours</th>
                        <th class="data-table-header p-3 text-right">Timesheets</th>
                        <th class="data-table-header p-3 text-right">Approved</th>
                        <th class="data-table-header p-3 text-right">Pending</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allUsersSummary as $summary): ?>
                    <tr class="border-b border-border hover:bg-muted/10">
                        <td class="p-3 font-medium"><?= htmlspecialchars($summary['userName'] ?? 'Unknown') ?></td>
                        <td class="p-3 text-right font-bold"><?= number_format($summary['totalHours'], 2) ?> hrs</td>
                        <td class="p-3 text-right"><?= $summary['timesheetCount'] ?></td>
                        <td class="p-3 text-right text-green-600"><?= $summary['approvedCount'] ?></td>
                        <td class="p-3 text-right text-blue-600"><?= $summary['pendingCount'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Load Chart.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
lucide.createIcons();

// Chart data from PHP
const weekLabels = <?= json_encode($weekLabels) ?>;
const weekHours = <?= json_encode($weekHours) ?>;
const taskLabels = <?= json_encode($taskLabels) ?>;
const taskHours = <?= json_encode($taskHours) ?>;

// Colors for pie chart
const pieColors = [
    '#62d84e', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6',
    '#ec4899', '#10b981', '#6366f1', '#14b8a6', '#f97316'
];

// Weekly Hours Bar Chart
const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
new Chart(weeklyCtx, {
    type: 'bar',
    data: {
        labels: weekLabels.length > 0 ? weekLabels : ['No Data'],
        datasets: [{
            label: 'Hours Worked',
            data: weekHours.length > 0 ? weekHours : [0],
            backgroundColor: '#62d84e',
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 10 }
            }
        }
    }
});

// Hours by Task Pie Chart
const taskCtx = document.getElementById('taskChart').getContext('2d');
new Chart(taskCtx, {
    type: 'doughnut',
    data: {
        labels: taskLabels.length > 0 ? taskLabels : ['No Data'],
        datasets: [{
            data: taskHours.length > 0 ? taskHours : [1],
            backgroundColor: taskLabels.length > 0 ? pieColors.slice(0, taskLabels.length) : ['#e5e7eb'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { boxWidth: 12, font: { size: 11 } }
            }
        }
    }
});

// Update date range
function updateDateRange() {
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    window.location.href = '<?= BASE_URL ?>/?page=timesheet_reports&start=' + start + '&end=' + end;
}
</script>
