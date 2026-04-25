-- Timesheet Management System - Database Schema
-- For Firestore (NoSQL) or MySQL

-- =====================================================
-- For MySQL (if using relational database)
-- =====================================================

-- Tasks table (for dropdown selection)
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Timesheets table (weekly container)
CREATE TABLE IF NOT EXISTS timesheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(100) NOT NULL,
    week_start DATE NOT NULL,
    week_end DATE NOT NULL,
    status ENUM('Draft', 'Submitted', 'Approved', 'Rejected') DEFAULT 'Draft',
    total_hours DECIMAL(5,2) DEFAULT 0.00,
    submitted_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    approved_by VARCHAR(100),
    rejection_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_week (user_id, week_start),
    INDEX idx_status (status)
);

-- Time Cards table (daily entries)
CREATE TABLE IF NOT EXISTS time_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timesheet_id INT NOT NULL,
    user_id VARCHAR(100) NOT NULL,
    entry_date DATE NOT NULL,
    task_id INT,
    hours_worked DECIMAL(4,2) NOT NULL,
    description TEXT,
    status ENUM('Draft', 'Submitted', 'Approved', 'Rejected') DEFAULT 'Draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (timesheet_id) REFERENCES timesheets(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    UNIQUE KEY unique_entry (timesheet_id, entry_date, task_id),
    CHECK (hours_worked > 0 AND hours_worked <= 24),
    INDEX idx_user_date (user_id, entry_date),
    INDEX idx_timesheet (timesheet_id)
);

-- Approval History table (audit trail)
CREATE TABLE IF NOT EXISTS timesheet_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timesheet_id INT NOT NULL,
    action ENUM('Submitted', 'Approved', 'Rejected', 'Reopened') NOT NULL,
    action_by VARCHAR(100) NOT NULL,
    action_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    comments TEXT,
    FOREIGN KEY (timesheet_id) REFERENCES timesheets(id) ON DELETE CASCADE
);

-- Insert default tasks
INSERT INTO tasks (name, description, category) VALUES
('General Support', 'General IT support tasks', 'Support'),
('Ticket Resolution', 'Working on assigned tickets', 'Support'),
('Project Work', 'Project-related development or implementation', 'Project'),
('Training', 'Training and learning activities', 'Development'),
('Meeting', 'Team or client meetings', 'Administrative'),
('Documentation', 'Creating or updating documentation', 'Administrative'),
('System Maintenance', 'Server or system maintenance', 'Technical'),
('Bug Fix', 'Fixing reported bugs', 'Development'),
('Feature Development', 'Developing new features', 'Development'),
('Code Review', 'Reviewing team code', 'Development');

-- =====================================================
-- For Firestore (NoSQL) - Used with existing system
-- =====================================================

/*
Collections structure for Firestore:

1. tasks (collection)
   - name: string
   - description: string
   - category: string
   - isActive: boolean
   - createdAt: timestamp

2. timesheets (collection)
   - userId: string
   - weekStart: timestamp
   - weekEnd: timestamp
   - status: string (Draft|Submitted|Approved|Rejected)
   - totalHours: number
   - submittedAt: timestamp (nullable)
   - approvedAt: timestamp (nullable)
   - approvedBy: string (nullable)
   - rejectionReason: string (nullable)
   - createdAt: timestamp
   - updatedAt: timestamp

3. timeCards (collection)
   - timesheetId: string (reference)
   - userId: string
   - entryDate: timestamp
   - taskId: string (reference)
   - hoursWorked: number
   - description: string
   - status: string
   - createdAt: timestamp
   - updatedAt: timestamp

4. timesheetApprovals (collection)
   - timesheetId: string (reference)
   - action: string
   - actionBy: string
   - actionAt: timestamp
   - comments: string
*/
