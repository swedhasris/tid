<?php
/**
 * Timesheet Dashboard View
 * Bootstrap 5 weekly timesheet grid
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timesheet Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sn-green: #81B532;
            --sn-dark: #0B141A;
            --sn-sidebar: #151B26;
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
            color: var(--sn-green) !important;
        }
        .timesheet-grid {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        .day-column {
            min-height: 200px;
            border-right: 1px solid #e9ecef;
            padding: 1rem;
        }
        .day-column:last-child {
            border-right: none;
        }
        .day-header {
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
        .day-total {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--sn-green);
        }
        .time-entry {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            border-left: 3px solid var(--sn-green);
            cursor: pointer;
            transition: all 0.2s;
        }
        .time-entry:hover {
            background: #e9ecef;
            transform: translateX(2px);
        }
        .time-entry .hours {
            font-weight: 700;
            color: var(--sn-dark);
        }
        .btn-add-entry {
            width: 100%;
            border: 2px dashed #dee2e6;
            background: transparent;
            color: #6c757d;
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 0.8rem;
            transition: all 0.2s;
        }
        .btn-add-entry:hover {
            border-color: var(--sn-green);
            color: var(--sn-green);
            background: rgba(129, 181, 50, 0.05);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-Draft { background: #e9ecef; color: #495057; }
        .status-Submitted { background: #fff3cd; color: #856404; }
        .status-Approved { background: #d4edda; color: #155724; }
        .status-Rejected { background: #f8d7da; color: #721c24; }
        .week-total-card {
            background: linear-gradient(135deg, var(--sn-sidebar) 0%, var(--sn-dark) 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
        }
        .week-total-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--sn-green);
        }
        .nav-link.active {
            background-color: var(--sn-green) !important;
            color: var(--sn-dark) !important;
            font-weight: 600;
        }
        .modal-header {
            background: var(--sn-sidebar);
            color: white;
        }
        .btn-primary {
            background-color: var(--sn-green);
            border-color: var(--sn-green);
            color: var(--sn-dark);
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #6fa32a;
            border-color: #6fa32a;
            color: var(--sn-dark);
        }
        .spinner-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: var(--sn-sidebar);">
        <div class="container">
            <a class="navbar-brand" href="/timesheet"><i class="bi bi-clock-history me-2"></i>Timesheet</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navMenu">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="/timesheet">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/timesheet/reports">Reports</a></li>
                </ul>
                <ul class="navbar-nav" id="adminNav">
                    <li class="nav-item"><a class="nav-link" href="/timesheet/admin"><i class="bi bi-shield-check me-1"></i>Admin</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="mb-1">Weekly Timesheet</h4>
                <p class="text-muted mb-0" id="weekLabel">Loading...</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <span id="statusBadge" class="status-badge status-Draft">Draft</span>
                <button class="btn btn-outline-secondary btn-sm" onclick="changeWeek(-7)"><i class="bi bi-chevron-left"></i></button>
                <button class="btn btn-outline-secondary btn-sm" onclick="changeWeek(7)"><i class="bi bi-chevron-right"></i></button>
                <button class="btn btn-outline-secondary btn-sm" onclick="loadCurrentWeek()">Today</button>
                <button id="submitBtn" class="btn btn-primary btn-sm" onclick="submitTimesheet()">
                    <i class="bi bi-send me-1"></i>Submit Week
                </button>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="week-total-card">
                    <div class="text-white-50 mb-1">Week Total</div>
                    <h3 id="weekTotalHours">0.0</h3>
                    <small class="text-white-50">hours logged</small>
                </div>
            </div>
            <div class="col-md-9 d-flex align-items-center">
                <div id="alertBox" class="alert w-100 mb-0 d-none"></div>
            </div>
        </div>

        <div class="timesheet-grid p-3">
            <div class="row" id="weekGrid"></div>
        </div>
    </div>

    <!-- Entry Modal -->
    <div class="modal fade" id="entryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Time Entry</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="entryForm">
                        <input type="hidden" id="entryId">
                        <input type="hidden" id="entryDate">
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" class="form-control" id="formDate" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Task / Project</label>
                            <select class="form-select" id="formTask" required>
                                <option value="">Select a task...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Hours Worked</label>
                            <input type="number" class="form-control" id="formHours" step="0.25" min="0.25" max="24" required>
                            <div class="form-text">Max 24 hours per day total</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="formDescription" rows="3" placeholder="What did you work on?"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger" id="deleteEntryBtn" onclick="deleteEntry()" style="display:none">Delete</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveEntry()">Save Entry</button>
                </div>
            </div>
        </div>
    </div>

    <div class="spinner-overlay d-none" id="spinner">
        <div class="spinner-border text-success" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        let currentWeekStart = getMonday(new Date());
        let timesheetData = { timesheet: {}, time_cards: [] };
        let tasks = [];
        let userId = '';
        let userRole = '';

        function getMonday(d) {
            d = new Date(d);
            const day = d.getDay();
            const diff = d.getDate() - day + (day === 0 ? -6 : 1);
            return new Date(d.setDate(diff));
        }

        function formatDate(d) {
            return d.toISOString().split('T')[0];
        }

        function formatDisplayDate(d) {
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }

        // Detect user from localStorage (demo user, Firebase, or URL params)
        function detectUser() {
            const demo = localStorage.getItem('demo_user');
            if (demo) {
                try {
                    const u = JSON.parse(demo);
                    userId = u.uid || '';
                    userRole = u.role || 'user';
                    return;
                } catch(e) {}
            }
            const tsUser = localStorage.getItem('timesheet_user');
            if (tsUser) {
                try {
                    const u = JSON.parse(tsUser);
                    userId = u.uid || '';
                    userRole = u.role || 'user';
                    return;
                } catch(e) {}
            }
            // Fallback: URL query parameters
            const params = new URLSearchParams(window.location.search);
            const qUserId = params.get('user_id');
            if (qUserId) {
                userId = qUserId;
                userRole = params.get('role') || 'user';
            }
        }

        async function apiGet(path) {
            const res = await fetch(path, { headers: { 'X-User-Id': userId } });
            return res.json();
        }

        async function apiPost(path, body) {
            const res = await fetch(path, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-User-Id': userId },
                body: JSON.stringify(body)
            });
            return res.json();
        }

        async function apiPut(path, body) {
            const res = await fetch(path, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-User-Id': userId },
                body: JSON.stringify(body)
            });
            return res.json();
        }

        async function apiDelete(path) {
            const res = await fetch(path, {
                method: 'DELETE',
                headers: { 'X-User-Id': userId }
            });
            return res.json();
        }

        function showSpinner(show) {
            document.getElementById('spinner').classList.toggle('d-none', !show);
        }

        function showAlert(message, type = 'danger') {
            const box = document.getElementById('alertBox');
            box.className = `alert alert-${type} w-100 mb-0`;
            box.textContent = message;
            box.classList.remove('d-none');
            setTimeout(() => box.classList.add('d-none'), 5000);
        }

        async function loadTasks() {
            try {
                tasks = await apiGet('/api/timesheet/tasks');
                const select = document.getElementById('formTask');
                select.innerHTML = '<option value="">Select a task...</option>';
                tasks.forEach(t => {
                    select.innerHTML += `<option value="${t.id}">${t.name}</option>`;
                });
            } catch(e) {
                console.error('Failed to load tasks', e);
            }
        }

        async function loadWeek() {
            showSpinner(true);
            try {
                const weekStr = formatDate(currentWeekStart);
                document.getElementById('weekLabel').textContent = `Week of ${formatDisplayDate(currentWeekStart)}`;
                timesheetData = await apiGet(`/api/timesheet/week?week_start=${weekStr}`);
                renderGrid();
                updateStatus();
            } catch(e) {
                showAlert('Failed to load timesheet data');
            }
            showSpinner(false);
        }

        function renderGrid() {
            const grid = document.getElementById('weekGrid');
            grid.innerHTML = '';
            let weekTotal = 0;

            for (let i = 0; i < 7; i++) {
                const date = new Date(currentWeekStart);
                date.setDate(date.getDate() + i);
                const dateStr = formatDate(date);
                const dayCards = timesheetData.time_cards.filter(c => c.entry_date === dateStr);
                const dayTotal = dayCards.reduce((sum, c) => sum + parseFloat(c.hours_worked || 0), 0);
                weekTotal += dayTotal;

                const isReadOnly = timesheetData.timesheet.status !== 'Draft';

                let cardsHtml = dayCards.map(c => {
                    const task = tasks.find(t => t.id == c.task_id);
                    const taskName = task ? task.name : (c.task_name || 'Unknown');
                    return `
                        <div class="time-entry" onclick="${isReadOnly ? '' : `openEditModal(${c.id})`}">
                            <div class="d-flex justify-content-between">
                                <span class="hours">${parseFloat(c.hours_worked).toFixed(2)}h</span>
                                <small class="text-muted">${taskName}</small>
                            </div>
                            ${c.description ? `<small class="text-muted d-block text-truncate">${c.description}</small>` : ''}
                        </div>
                    `;
                }).join('');

                grid.innerHTML += `
                    <div class="col day-column">
                        <div class="day-header d-flex justify-content-between">
                            <span>${dayNames[i]}</span>
                            <span class="day-total">${dayTotal.toFixed(1)}h</span>
                        </div>
                        <div class="mb-2 text-muted small">${formatDisplayDate(date)}</div>
                        ${cardsHtml}
                        ${!isReadOnly ? `<button class="btn-add-entry" onclick="openAddModal('${dateStr}')">+ Add Entry</button>` : ''}
                    </div>
                `;
            }

            document.getElementById('weekTotalHours').textContent = weekTotal.toFixed(1);
        }

        function updateStatus() {
            const status = timesheetData.timesheet.status || 'Draft';
            const badge = document.getElementById('statusBadge');
            badge.textContent = status;
            badge.className = `status-badge status-${status}`;

            const submitBtn = document.getElementById('submitBtn');
            if (status !== 'Draft') {
                submitBtn.style.display = 'none';
            } else {
                submitBtn.style.display = 'inline-block';
            }
        }

        function changeWeek(days) {
            currentWeekStart.setDate(currentWeekStart.getDate() + days);
            loadWeek();
        }

        function loadCurrentWeek() {
            currentWeekStart = getMonday(new Date());
            loadWeek();
        }

        const entryModal = new bootstrap.Modal(document.getElementById('entryModal'));

        function openAddModal(dateStr) {
            if (timesheetData.timesheet.status !== 'Draft') {
                showAlert('Cannot edit a submitted or approved timesheet', 'warning');
                return;
            }
            document.getElementById('entryId').value = '';
            document.getElementById('formDate').value = dateStr;
            document.getElementById('formTask').value = '';
            document.getElementById('formHours').value = '';
            document.getElementById('formDescription').value = '';
            document.getElementById('modalTitle').textContent = 'Add Time Entry';
            document.getElementById('deleteEntryBtn').style.display = 'none';
            entryModal.show();
        }

        function openEditModal(id) {
            if (timesheetData.timesheet.status !== 'Draft') return;
            const card = timesheetData.time_cards.find(c => c.id == id);
            if (!card) return;
            document.getElementById('entryId').value = card.id;
            document.getElementById('formDate').value = card.entry_date;
            document.getElementById('formTask').value = card.task_id || '';
            document.getElementById('formHours').value = card.hours_worked;
            document.getElementById('formDescription').value = card.description || '';
            document.getElementById('modalTitle').textContent = 'Edit Time Entry';
            document.getElementById('deleteEntryBtn').style.display = 'inline-block';
            entryModal.show();
        }

        async function saveEntry() {
            const id = document.getElementById('entryId').value;
            const date = document.getElementById('formDate').value;
            const taskId = document.getElementById('formTask').value;
            const hours = parseFloat(document.getElementById('formHours').value);
            const description = document.getElementById('formDescription').value;

            if (!date || !taskId || !hours) {
                showAlert('Please fill in all required fields', 'warning');
                return;
            }

            const payload = {
                week_start: formatDate(currentWeekStart),
                entry_date: date,
                task_id: parseInt(taskId),
                hours_worked: hours,
                description: description
            };

            showSpinner(true);
            try {
                let result;
                if (id) {
                    result = await apiPut(`/api/timesheet/entry/${id}`, payload);
                } else {
                    result = await apiPost('/api/timesheet/entry', payload);
                }
                if (result.error) {
                    showAlert(result.error, 'danger');
                } else {
                    entryModal.hide();
                    await loadWeek();
                    showAlert(id ? 'Entry updated' : 'Entry created', 'success');
                }
            } catch(e) {
                showAlert('Failed to save entry');
            }
            showSpinner(false);
        }

        async function deleteEntry() {
            const id = document.getElementById('entryId').value;
            if (!id) return;
            if (!confirm('Are you sure you want to delete this entry?')) return;

            showSpinner(true);
            try {
                const result = await apiDelete(`/api/timesheet/entry/${id}`);
                if (result.error) {
                    showAlert(result.error);
                } else {
                    entryModal.hide();
                    await loadWeek();
                    showAlert('Entry deleted', 'success');
                }
            } catch(e) {
                showAlert('Failed to delete entry');
            }
            showSpinner(false);
        }

        async function submitTimesheet() {
            if (!timesheetData.timesheet.id) return;
            if (!confirm('Submit this timesheet for approval? You will not be able to edit it after submission.')) return;

            showSpinner(true);
            try {
                const result = await apiPost('/api/timesheet/submit', { timesheet_id: timesheetData.timesheet.id });
                if (result.error) {
                    showAlert(result.error);
                } else {
                    await loadWeek();
                    showAlert('Timesheet submitted for approval', 'success');
                }
            } catch(e) {
                showAlert('Failed to submit timesheet');
            }
            showSpinner(false);
        }

        function init() {
            detectUser();
            if (!userId) {
                document.body.innerHTML = '<div class="container py-5"><div class="alert alert-warning">Please log in to access the timesheet system.</div></div>';
                return;
            }
            // Hide admin link for non-admins
            if (userRole !== 'admin' && userRole !== 'super_admin') {
                document.getElementById('adminNav').style.display = 'none';
            }
            loadTasks();
            loadWeek();
        }

        init();
    </script>
</body>
</html>
