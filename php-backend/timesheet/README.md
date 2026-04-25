# Timesheet Management System

A fully self-contained ITIL-style timesheet module built in plain PHP with Bootstrap 5 UI.

## Features

- **Weekly timesheet grid** (Mon–Sun) with per-day and per-week totals
- **Time card entries** — log hours per task per day with notes
- **Duplicate prevention** — same date + task combination blocked
- **24-hour daily limit** enforced server-side
- **Approval workflow** — Draft → Submitted → Approved / Rejected
- **Admin panel** — view all timesheets, approve/reject with comments
- **Reports** — monthly bar chart + status doughnut chart (Chart.js)
- **AJAX-powered** — no page reloads
- **Zero-config SQLite fallback** — works without MySQL for local dev

## Pages

| URL | Description |
|-----|-------------|
| `/timesheet` | Weekly dashboard (user) |
| `/timesheet/admin` | Approval panel (admin only) |
| `/timesheet/reports` | Monthly reports + charts |

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/timesheet/tasks` | List active tasks |
| GET | `/api/timesheet/week?week_start=YYYY-MM-DD` | Get/create weekly timesheet |
| POST | `/api/timesheet/entry` | Create time card |
| PUT | `/api/timesheet/entry/{id}` | Update time card |
| DELETE | `/api/timesheet/entry/{id}` | Delete time card |
| POST | `/api/timesheet/submit` | Submit timesheet for approval |
| GET | `/api/timesheet/admin/list` | Admin: list all timesheets |
| GET | `/api/timesheet/admin/detail/{id}` | Admin: timesheet detail |
| POST | `/api/timesheet/admin/approve` | Admin: approve timesheet |
| POST | `/api/timesheet/admin/reject` | Admin: reject timesheet |
| GET | `/api/timesheet/reports/monthly` | Monthly report for current user |

## Setup

### 1. Start the PHP server

```bash
php -S localhost:8000 php-backend/index.php
```

### 2. Access the timesheet

Open: `http://localhost:8000/timesheet?user_id=YOUR_FIREBASE_UID`

Or set `timesheet_user` in localStorage:
```js
localStorage.setItem('timesheet_user', JSON.stringify({ uid: 'user123', role: 'user', name: 'John' }))
```

For admin access use `role: 'admin'` or `role: 'super_admin'`.

### 3. MySQL (optional)

Set environment variables before starting PHP:
```bash
export DB_HOST=localhost
export DB_NAME=timesheet_db
export DB_USER=root
export DB_PASS=yourpassword
```

Then run the schema:
```bash
mysql -u root -p < php-backend/timesheet/schema.sql
```

Without MySQL, the system auto-creates a SQLite database at `php-backend/timesheet/timesheet.db`.

## File Structure

```
php-backend/timesheet/
├── Database.php          # PDO connection (MySQL + SQLite fallback)
├── AuthHelper.php        # Firebase Auth integration
├── api.php               # Standalone API router (reference)
├── schema.sql            # MySQL schema
├── timesheet.db          # SQLite auto-created database
├── models/
│   ├── Timesheet.php     # Timesheet model
│   ├── TimeCard.php      # Time card model
│   └── Task.php          # Task model
└── views/
    ├── dashboard.php     # Weekly timesheet UI
    ├── admin.php         # Admin approval UI
    └── reports.php       # Reports + charts UI
```
