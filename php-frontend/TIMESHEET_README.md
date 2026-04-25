# Timesheet Management System

A complete timesheet tracking module added to the Connect IT system.

## Features

### 1. Weekly Timesheet
- Monday-Sunday grid view
- Daily hour tracking
- Auto-calculation of weekly totals
- Week navigation (previous/next)

### 2. Time Entry Management
- Add entries by day
- Select from task dropdown
- Add notes/description
- Edit before submission
- Validation (max 24 hrs/day, no duplicates)

### 3. Task Management
- Pre-populated task list
- Categories: Support, Project, Development, Administrative, Technical
- Tasks stored in Firestore

### 4. Approval Workflow
- **Draft** → Edit freely
- **Submitted** → Pending admin review
- **Approved** → Locked, approved by manager
- **Rejected** → Can be reopened with reason

### 5. Admin Approval
- Filter by status
- View all employee timesheets
- Approve/Reject with comments
- Reopen rejected timesheets

### 6. Reports & Analytics
- Weekly hours bar chart
- Hours by task pie chart
- Task breakdown table
- Admin: All users summary

## File Structure

```
php-frontend/
├── includes/
│   └── TimesheetModel.php          # Core model class
├── pages/
│   ├── timesheet.php               # Weekly timesheet view
│   ├── timesheet_ajax.php          # AJAX handlers
│   ├── timesheet_approvals.php     # Admin approval page
│   └── timesheet_reports.php       # Reports & charts
├── sql/
│   └── timesheet_schema.sql        # Database schema
└── TIMESHEET_README.md            # This file
```

## Database Collections (Firestore)

### tasks
- name, description, category, isActive

### timesheets
- userId, weekStart, weekEnd, status, totalHours
- submittedAt, approvedAt, approvedBy, rejectionReason

### timeCards
- timesheetId, userId, entryDate, taskId
- hoursWorked, description, status

### timesheetApprovals (audit trail)
- timesheetId, action, actionBy, actionAt, comments

## Access URLs

- **My Timesheet:** `?page=timesheet`
- **Reports:** `?page=timesheet_reports`
- **Approvals (Admin):** `?page=timesheet_approvals`

## Security

- Role-based access control
- Users can only edit own timesheets
- Admins can approve/reject all
- Validation on server side
- CSRF protection on forms

## Chart.js

Reports use Chart.js (loaded from CDN):
- Bar chart for weekly hours
- Doughnut chart for task breakdown

## Validation Rules

1. Hours must be > 0 and ≤ 24 per entry
2. Total per day ≤ 24 hours
3. No duplicate entries for same date+task
4. Can only edit Draft or Rejected timesheets
5. Cannot submit empty timesheets
