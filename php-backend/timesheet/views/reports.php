<?php
/**
 * Timesheet Reports View
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timesheet Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root { --sn-green: #81B532; --sn-dark: #0B141A; --sn-sidebar: #151B26; }
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .navbar-brand { font-weight: 700; color: var(--sn-green) !important; }
        .report-card { background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 1.5rem; margin-bottom: 1.5rem; }
        .stat-card { background: linear-gradient(135deg, var(--sn-sidebar) 0%, var(--sn-dark) 100%); color: white; border-radius: 12px; padding: 1.5rem; text-align: center; }
        .stat-card h3 { font-size: 2rem; font-weight: 700; color: var(--sn-green); margin: 0; }
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
                    <li class="nav-item"><a class="nav-link active" href="/timesheet/reports">Reports</a></li>
                </ul>
                <ul class="navbar-nav" id="adminNav">
                    <li class="nav-item"><a class="nav-link" href="/timesheet/admin"><i class="bi bi-shield-check me-1"></i>Admin</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Timesheet Reports</h4>
            <div class="d-flex gap-2">
                <select class="form-select form-select-sm" id="reportMonth" style="width: auto;">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo $i == date('n') ? 'selected' : ''; ?>><?php echo date('F', mktime(0,0,0,$i,1)); ?></option>
                    <?php endfor; ?>
                </select>
                <select class="form-select form-select-sm" id="reportYear" style="width: auto;">
                    <?php for ($y = date('Y'); $y >= date('Y')-2; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == date('Y') ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <button class="btn btn-primary btn-sm" onclick="loadReports()">Load</button>
            </div>
        </div>

        <div id="alertBox" class="alert d-none mb-3"></div>

        <div class="row mb-4" id="statsRow">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <small class="text-white-50">Total Hours</small>
                    <h3 id="statTotalHours">0</h3>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <small class="text-white-50">Weeks Logged</small>
                    <h3 id="statWeeks">0</h3>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <small class="text-white-50">Avg / Week</small>
                    <h3 id="statAvgWeek">0</h3>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <small class="text-white-50">Approval Rate</small>
                    <h3 id="statApprovalRate">0%</h3>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="report-card">
                    <h6 class="mb-3">Weekly Hours Breakdown</h6>
                    <canvas id="weeklyChart" height="250"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="report-card">
                    <h6 class="mb-3">Status Distribution</h6>
                    <canvas id="statusChart" height="250"></canvas>
                </div>
            </div>
        </div>

        <div class="report-card mt-4">
            <h6 class="mb-3">Monthly Detail</h6>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Week Start</th>
                            <th>Status</th>
                            <th class="text-end">Hours</th>
                        </tr>
                    </thead>
                    <tbody id="detailTable"></tbody>
                </table>
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
        let weeklyChart = null;
        let statusChart = null;

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

        function showSpinner(show) { document.getElementById('spinner').classList.toggle('d-none', !show); }
        function showAlert(msg, type = 'danger') {
            const box = document.getElementById('alertBox');
            box.className = `alert alert-${type} mb-3`; box.textContent = msg; box.classList.remove('d-none');
            setTimeout(() => box.classList.add('d-none'), 5000);
        }

        async function loadReports() {
            const year = document.getElementById('reportYear').value;
            const month = document.getElementById('reportMonth').value;
            showSpinner(true);
            try {
                const data = await apiGet(`/api/timesheet/reports/monthly?year=${year}&month=${month}`);
                if (data.error) { showAlert(data.error); showSpinner(false); return; }
                renderStats(data.data);
                renderCharts(data.data);
                renderTable(data.data);
            } catch(e) { showAlert('Failed to load reports'); }
            showSpinner(false);
        }

        function renderStats(data) {
            const total = data.reduce((s, r) => s + parseFloat(r.total_hours || 0), 0);
            const weeks = data.length;
            const avg = weeks > 0 ? (total / weeks).toFixed(1) : 0;
            const approved = data.filter(r => r.status === 'Approved').length;
            const rate = weeks > 0 ? Math.round((approved / weeks) * 100) : 0;

            document.getElementById('statTotalHours').textContent = total.toFixed(1);
            document.getElementById('statWeeks').textContent = weeks;
            document.getElementById('statAvgWeek').textContent = avg;
            document.getElementById('statApprovalRate').textContent = rate + '%';
        }

        function renderCharts(data) {
            const labels = data.map(d => d.week_start);
            const hours = data.map(d => parseFloat(d.total_hours || 0));
            const statusCounts = { Draft: 0, Submitted: 0, Approved: 0, Rejected: 0 };
            data.forEach(d => { if (statusCounts[d.status] !== undefined) statusCounts[d.status]++; });

            if (weeklyChart) weeklyChart.destroy();
            if (statusChart) statusChart.destroy();

            const ctx1 = document.getElementById('weeklyChart').getContext('2d');
            weeklyChart = new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Hours',
                        data: hours,
                        backgroundColor: '#81B532',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });

            const ctx2 = document.getElementById('statusChart').getContext('2d');
            statusChart = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(statusCounts),
                    datasets: [{
                        data: Object.values(statusCounts),
                        backgroundColor: ['#e9ecef', '#fff3cd', '#d4edda', '#f8d7da']
                    }]
                },
                options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
            });
        }

        function renderTable(data) {
            const tbody = document.getElementById('detailTable');
            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No data</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(r => `
                <tr>
                    <td>${r.week_start}</td>
                    <td><span class="badge bg-${r.status === 'Approved' ? 'success' : r.status === 'Rejected' ? 'danger' : r.status === 'Submitted' ? 'warning' : 'secondary'}">${r.status}</span></td>
                    <td class="text-end fw-bold">${parseFloat(r.total_hours || 0).toFixed(1)}h</td>
                </tr>
            `).join('');
        }

        detectUser();
        if (!userId) {
            document.body.innerHTML = '<div class="container py-5"><div class="alert alert-warning">Please log in to view reports.</div></div>';
        } else {
            if (userRole !== 'admin' && userRole !== 'super_admin') {
                document.getElementById('adminNav').style.display = 'none';
            }
            loadReports();
        }
    </script>
</body>
</html>
