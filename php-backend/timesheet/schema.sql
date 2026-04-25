-- Timesheet Management System Database Schema
-- Run this SQL to create the required tables

CREATE DATABASE IF NOT EXISTS timesheet_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE timesheet_db;

-- Tasks / Projects table
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Timesheets table (one per user per week)
CREATE TABLE IF NOT EXISTS timesheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(128) NOT NULL,
    user_name VARCHAR(255),
    week_start DATE NOT NULL,
    status ENUM('Draft', 'Submitted', 'Approved', 'Rejected') DEFAULT 'Draft',
    total_hours DECIMAL(5,2) DEFAULT 0.00,
    admin_comment TEXT,
    submitted_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_week (user_id, week_start)
) ENGINE=InnoDB;

-- Time Cards table (individual daily entries)
CREATE TABLE IF NOT EXISTS time_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timesheet_id INT NOT NULL,
    entry_date DATE NOT NULL,
    task_id INT,
    hours_worked DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (timesheet_id) REFERENCES timesheets(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    UNIQUE KEY unique_timesheet_date_task (timesheet_id, entry_date, task_id)
) ENGINE=InnoDB;

-- Insert default tasks
INSERT INTO tasks (name, description) VALUES
('General Support', 'General IT support and troubleshooting'),
('Ticket Resolution', 'Resolving assigned support tickets'),
('Project Work', 'Working on internal projects'),
('Meeting', 'Team or client meetings'),
('Training', 'Learning and development activities'),
('Documentation', 'Writing documentation and knowledge base articles'),
('System Maintenance', 'Server and infrastructure maintenance');
