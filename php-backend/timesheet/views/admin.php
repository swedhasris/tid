<?php
/**
 * Timesheet Admin Approval View
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timesheet Admin - Approvals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --sn-green: #81B532; --sn-dark: #0B141A; --sn-sidebar: #151B26; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .navbar-brand { font-weight: 700; color: var(--sn-green) !important; }
        .timesheet-card { background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 1.5rem; margin-bottom: 1rem; }
        .status-badge { font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 20px; font-weight: 600; text-transform: uppercase; }
        .status-Draft { background: #e9ecef; color: #495057; }
        .status-Submitted { background: #fff3cd; color: #856404; }
        .status-Approved { background: #d4edda; color: #155724; }
        .status-Rejected { background: #f8d7da; color: #721c24; }
        .btn-primary { background-color: var(--sn-green); border-color: var(--sn-green); color: var(--sn-dark); font-weight: 600; }
        .btn-primary:hover { background-color: #6fa32a; border-color: #6fa32a; color: var(--sn-dark); }
        .spinner-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; z-index: 9999; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: var(--sn-sidebar);">
        <div class="container">
            <a class="navbar-brand" href="/timesheet"><i class="bi bi-clock-history me-2"></i>Timesheet</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="/timesheet">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="/timesheet/reports">Reports</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link active" href="/timesheet/admin"><i class="bi bi-shield-check me-1"></i>Admin</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Timesheet Approvals</h4>
            <div class="btn-group">
                <button class="btn btn-outline-secondary btn-sm" onclick="loadTimesheets()">All</button>
                <button class="btn btn-outline-warning btn-sm" onclick="loadTimesheets('Submitted')">Pending</button>
                <button class="btn btn-outline-success btn-sm" onclick="loadTimesheets('Approved')">Approved</button>
                <button class="btn btn-outline-danger btn-sm" onclick="loadTimesheets('Rejected')">Rejected</button>
            </div>
        </div>

        <div id="alertBox" class="alert d-none mb-3"></div>
        <div id="timesheetsList"></div>
    </div>

    <!-- Approve/Reject Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--sn-sidebar); color: white;">
                    <h5 class="modal-title" id="actionModalTitle">Action</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="actionTimesheetId">
                    <input type="hidden" id="actionType">
                    <div class="mb-3">
                        <label class="form-label">Comment</label>
                        <textarea class="form-control" id="actionComment" rows="3" placeholder="Add a comment (required for rejection)"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="performAction()">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--sn-sidebar); color: white;">
                    <h5 class="modal-title">Timesheet Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="detailContent"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="spinner-overlay d-none" id="spinner">
        <div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading...</span></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let userId = '';
        let userRole = '';
        let currentFilter = '';

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

        function showSpinner(show) { document.getElementById('spinner').classList.toggle('d-none', !show); }
        function showAlert(msg, type = 'danger') {
            const box = document.getElementById('alertBox');
            box.className = `alert alert-${type} mb-3`; box.textContent = msg; box.classList.remove('d-none');
            setTimeout(() => box.classList.add('d-none'), 5000);
        }

        async function loadTimesheets(status = '') {
            currentFilter = status;
            showSpinner(true);
            try {
                let url = '/api/timesheet/admin/list';
                if (status) url += '?status=' + status;
                const data = await apiGet(url);
                if (data.error) { showAlert(data.error); return; }
                renderTimesheets(data);
            } catch(e) { showAlert('Failed to load timesheets'); }
            showSpinner(false);
        }

        function renderTimesheets(list) {
            const container = document.getElementById('timesheetsList');
            if (!list || list.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-5">No timesheets found</div>';
                return;
            }
            container.innerHTML = list.map(ts => `
                <div class="timesheet-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${ts.user_name || 'Unknown'}</h6>
                            <small class="text-muted">Week of ${ts.week_start} &middot; ${parseFloat(ts.total_hours).toFixed(1)} hours</small>
                        </div>
                        <span class="status-badge status-${ts.status}">${ts.status}</span>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewDetail(${ts.id})">View Details</button>
                        ${ts.status === 'Submitted' ? `
                            <button class="btn btn-sm btn-success" onclick="openAction(${ts.id}, 'approve')">Approve</button>
                            <button class="btn btn-sm btn-danger" onclick="openAction(${ts.id}, 'reject')">Reject</button>
                        ` : ''}
                    </div>
                    ${ts.admin_comment ? `<div class="mt-2 small text-muted"><strong>Comment:</strong> ${ts.admin_comment}</div>` : ''}
                </div>
            `).join('');
        }

        const actionModal = new bootstrap.Modal(document.getElementById('actionModal'));
        const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));

        function openAction(id, type) {
            document.getElementById('actionTimesheetId').value = id;
            document.getElementById('actionType').value = type;
            document.getElementById('actionModalTitle').textContent = type === 'approve' ? 'Approve Timesheet' : 'Reject Timesheet';
            document.getElementById('actionComment').value = '';
            document.getElementById('actionComment').placeholder = type === 'reject' ? 'Rejection reason (required)' : 'Optional comment';
            actionModal.show();
        }

        async function performAction() {
            const id = document.getElementById('actionTimesheetId').value;
            const type = document.getElementById('actionType').value;
            const comment = document.getElementById('actionComment').value;

            if (type === 'reject' && !comment) { showAlert('Rejection comment is required', 'warning'); return; }

            showSpinner(true);
            try {
                const endpoint = type === 'approve' ? '/api/timesheet/admin/approve' : '/api/timesheet/admin/reject';
                const result = await apiPost(endpoint, { timesheet_id: parseInt(id), comment });
                if (result.error) { showAlert(result.error); }
                else { actionModal.hide(); loadTimesheets(currentFilter); showAlert('Action completed', 'success'); }
            } catch(e) { showAlert('Action failed'); }
            showSpinner(false);
        }

        async function viewDetail(id) {
            showSpinner(true);
            try {
                const data = await apiGet(`/api/timesheet/admin/detail/${id}`);
                if (data.error) { showAlert(data.error); showSpinner(false); return; }

                const ts = data.timesheet;
                const cards = data.time_cards || [];

                // Group cards by date
                const byDate = {};
                cards.forEach(c => {
                    if (!byDate[c.entry_date]) byDate[c.entry_date] = [];
                    byDate[c.entry_date].push(c);
                });

                let cardsHtml = Object.entries(byDate).sort().map(([date, entries]) => {
                    const dayTotal = entries.reduce((s, e) => s + parseFloat(e.hours_worked || 0), 0);
                    const rows = entries.map(e => `
                        <tr>
                            <td>${date}</td>
                            <td>${e.task_name || 'Unknown'}</td>
                            <td>${parseFloat(e.hours_worked).toFixed(2)}h</td>
                            <td class="text-muted small">${e.description || '-'}</td>
                        </tr>
                    `).join('');
                    return rows;
                }).join('');

                document.getElementById('detailContent').innerHTML = `
                    <div class="row mb-3">
                        <div class="col-6"><strong>User:</strong> ${ts.user_name || ts.user_id}</div>
                        <div class="col-6"><strong>Week:</strong> ${ts.week_start}</div>
                        <div class="col-6 mt-2"><strong>Status:</strong> <span class="status-badge status-${ts.status}">${ts.status}</span></div>
                        <div class="col-6 mt-2"><strong>Total Hours:</strong> ${parseFloat(ts.total_hours).toFixed(1)}h</div>
                        ${ts.admin_comment ? `<div class="col-12 mt-2"><strong>Comment:</strong> ${ts.admin_comment}</div>` : ''}
                    </div>
                    <table class="table table-sm table-hover">
                        <thead><tr><th>Date</th><th>Task</th><th>Hours</th><th>Notes</th></tr></thead>
                        <tbody>${cardsHtml || '<tr><td colspan="4" class="text-center text-muted">No entries</td></tr>'}</tbody>
                    </table>
                `;
                detailModal.show();
            } catch(e) { showAlert('Failed to load details'); }
            showSpinner(false);
        }

        detectUser();
        if (!userId) { document.body.innerHTML = '<div class="container py-5"><div class="alert alert-warning">Please log in as admin.</div></div>'; }
        else { loadTimesheets(); }
    </script>
</body>
</html>
