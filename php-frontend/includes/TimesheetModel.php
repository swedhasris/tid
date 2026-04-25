<?php
/**
 * Timesheet Model - MVC Pattern
 * Handles all database operations for Timesheet Management System
 */

require_once __DIR__ . '/firebase-api.php';

class TimesheetModel {
    public FirebaseAPI $api;
    
    public function __construct() {
        $this->api = new FirebaseAPI();
    }
    
    // ==================== TASKS ====================
    
    /**
     * Get all active tasks
     */
    public function getTasks(): array {
        try {
            return $this->api->listDocuments('tasks');
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get single task by ID
     */
    public function getTask(string $taskId): ?array {
        try {
            return $this->api->getDocument('tasks', $taskId);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Create a new task
     */
    public function createTask(array $data): array {
        $taskData = [
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? '',
            'category' => $data['category'] ?? 'General',
            'isActive' => $data['isActive'] ?? true,
            'createdAt' => gmdate('c'),
            'updatedAt' => gmdate('c'),
        ];
        
        return $this->api->createDocument('tasks', $taskData);
    }
    
    // ==================== TIMESHEETS ====================
    
    /**
     * Get or create timesheet for a specific week
     */
    public function getOrCreateTimesheet(string $userId, string $weekStart): array {
        // Calculate week end (Sunday)
        $startDate = new DateTime($weekStart);
        $endDate = clone $startDate;
        $endDate->modify('+6 days');
        $weekEnd = $endDate->format('Y-m-d');
        
        // Check if timesheet exists
        $timesheets = $this->api->query('timesheets', [
            'userId' => $userId,
            'weekStart' => $weekStart . 'T00:00:00Z'
        ]);
        
        if (!empty($timesheets)) {
            return $timesheets[0];
        }
        
        // Create new timesheet
        $timesheetData = [
            'userId' => $userId,
            'weekStart' => $weekStart . 'T00:00:00Z',
            'weekEnd' => $weekEnd . 'T23:59:59Z',
            'status' => 'Draft',
            'totalHours' => 0,
            'submittedAt' => null,
            'approvedAt' => null,
            'approvedBy' => null,
            'rejectionReason' => null,
            'createdAt' => gmdate('c'),
            'updatedAt' => gmdate('c'),
        ];
        
        return $this->api->createDocument('timesheets', $timesheetData);
    }
    
    /**
     * Get timesheet by ID
     */
    public function getTimesheet(string $timesheetId): ?array {
        return $this->api->getDocument('timesheets', $timesheetId);
    }
    
    /**
     * Get all timesheets for a user
     */
    public function getUserTimesheets(string $userId, int $limit = 50): array {
        return $this->api->query('timesheets', ['userId' => $userId], 'weekStart', 'DESC');
    }
    
    /**
     * Get timesheets by status (for admin)
     */
    public function getTimesheetsByStatus(string $status): array {
        return $this->api->query('timesheets', ['status' => $status], 'submittedAt', 'DESC');
    }
    
    /**
     * Get all timesheets (admin)
     */
    public function getAllTimesheets(int $limit = 100): array {
        return $this->api->listDocuments('timesheets', $limit);
    }
    
    /**
     * Update timesheet status
     */
    public function updateTimesheetStatus(string $timesheetId, string $status, ?string $approvedBy = null, ?string $rejectionReason = null): void {
        $updateData = [
            'status' => $status,
            'updatedAt' => gmdate('c'),
        ];
        
        if ($status === 'Submitted') {
            $updateData['submittedAt'] = gmdate('c');
        } elseif ($status === 'Approved') {
            $updateData['approvedAt'] = gmdate('c');
            $updateData['approvedBy'] = $approvedBy;
        } elseif ($status === 'Rejected') {
            $updateData['rejectionReason'] = $rejectionReason;
        }
        
        $this->api->updateDocument('timesheets', $timesheetId, $updateData);
        
        // Add to approval history
        $this->addApprovalHistory($timesheetId, $status, $approvedBy ?? 'System', $rejectionReason);
    }
    
    /**
     * Recalculate total hours for timesheet
     */
    public function recalculateTotalHours(string $timesheetId): void {
        $timeCards = $this->getTimeCardsByTimesheet($timesheetId);
        $total = array_sum(array_column($timeCards, 'hoursWorked'));
        
        $this->api->updateDocument('timesheets', $timesheetId, [
            'totalHours' => $total,
            'updatedAt' => gmdate('c'),
        ]);
    }
    
    // ==================== TIME CARDS ====================
    
    /**
     * Get all time cards for a timesheet
     */
    public function getTimeCardsByTimesheet(string $timesheetId): array {
        return $this->api->query('timeCards', ['timesheetId' => $timesheetId], 'entryDate', 'ASC');
    }
    
    /**
     * Get time cards for a specific date
     */
    public function getTimeCardsByDate(string $userId, string $date): array {
        return $this->api->query('timeCards', [
            'userId' => $userId,
            'entryDate' => $date . 'T00:00:00Z'
        ]);
    }
    
    /**
     * Get time card by ID
     */
    public function getTimeCard(string $timeCardId): ?array {
        return $this->api->getDocument('timeCards', $timeCardId);
    }
    
    /**
     * Create or update time card
     */
    public function saveTimeCard(array $data): array {
        // Check for duplicate
        $existing = $this->api->query('timeCards', [
            'timesheetId' => $data['timesheetId'],
            'entryDate' => $data['entryDate'] . 'T00:00:00Z',
            'taskId' => $data['taskId']
        ]);
        
        $timeCardData = [
            'timesheetId' => $data['timesheetId'],
            'userId'      => $data['userId'],
            'entryDate'   => $data['entryDate'] . 'T00:00:00Z',
            'taskId'      => $data['taskId'],
            'hoursWorked' => (float) $data['hoursWorked'],
            'description' => $data['description'] ?? '',
            'priority'    => $data['priority']    ?? 'medium',
            'cardStatus'  => $data['cardStatus']  ?? 'pending',
            'status'      => $data['status']      ?? 'Draft',
            'updatedAt'   => gmdate('c'),
        ];
        
        if (!empty($existing)) {
            // Update existing
            $timeCardId = $existing[0]['id'];
            $this->api->updateDocument('timeCards', $timeCardId, $timeCardData);
            return array_merge($timeCardData, ['id' => $timeCardId]);
        } else {
            // Create new
            $timeCardData['createdAt'] = gmdate('c');
            return $this->api->createDocument('timeCards', $timeCardData);
        }
    }
    
    /**
     * Delete time card
     */
    public function deleteTimeCard(string $timeCardId): void {
        $this->api->deleteDocument('timeCards', $timeCardId);
    }
    
    /**
     * Get daily hours summary for a timesheet
     */
    public function getDailyHoursSummary(string $timesheetId): array {
        $timeCards = $this->getTimeCardsByTimesheet($timesheetId);
        $summary = [];
        
        foreach ($timeCards as $card) {
            $date = is_string($card['entryDate']) ? substr($card['entryDate'], 0, 10) : date('Y-m-d');
            if (!isset($summary[$date])) {
                $summary[$date] = 0;
            }
            $summary[$date] += (float) ($card['hoursWorked'] ?? 0);
        }
        
        return $summary;
    }
    
    // ==================== APPROVAL HISTORY ====================
    
    /**
     * Add approval history entry
     */
    public function addApprovalHistory(string $timesheetId, string $action, string $actionBy, ?string $comments = null): void {
        $historyData = [
            'timesheetId' => $timesheetId,
            'action' => $action,
            'actionBy' => $actionBy,
            'actionAt' => gmdate('c'),
            'comments' => $comments ?? '',
        ];
        
        $this->api->createDocument('timesheetApprovals', $historyData);
    }
    
    /**
     * Get approval history for timesheet
     */
    public function getApprovalHistory(string $timesheetId): array {
        return $this->api->query('timesheetApprovals', ['timesheetId' => $timesheetId], 'actionAt', 'DESC');
    }
    
    // ==================== REPORTS ====================
    
    /**
     * Get user hours by week
     */
    public function getUserHoursByWeek(string $userId, string $startDate, string $endDate): array {
        $allTimesheets = $this->getUserTimesheets($userId, 100);
        $filtered = [];
        
        foreach ($allTimesheets as $ts) {
            $weekStart = is_string($ts['weekStart']) ? substr($ts['weekStart'], 0, 10) : '';
            if ($weekStart >= $startDate && $weekStart <= $endDate) {
                $filtered[] = $ts;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Get hours by task for reporting
     */
    public function getHoursByTask(string $userId, string $startDate, string $endDate): array {
        $timesheets = $this->getUserHoursByWeek($userId, $startDate, $endDate);
        $taskHours = [];
        
        foreach ($timesheets as $ts) {
            $timeCards = $this->getTimeCardsByTimesheet($ts['id']);
            foreach ($timeCards as $card) {
                $taskId = $card['taskId'] ?? 'unknown';
                $task = $this->getTask($taskId);
                $taskName = $task['name'] ?? 'Unknown Task';
                
                if (!isset($taskHours[$taskId])) {
                    $taskHours[$taskId] = [
                        'taskId' => $taskId,
                        'taskName' => $taskName,
                        'totalHours' => 0,
                    ];
                }
                $taskHours[$taskId]['totalHours'] += (float) ($card['hoursWorked'] ?? 0);
            }
        }
        
        return array_values($taskHours);
    }
    
    /**
     * Get all users' timesheet summary (admin report)
     */
    public function getAllUsersTimesheetSummary(string $startDate, string $endDate): array {
        $allTimesheets = $this->api->listDocuments('timesheets', 1000);
        $userSummary = [];
        
        foreach ($allTimesheets as $ts) {
            $weekStart = is_string($ts['weekStart']) ? substr($ts['weekStart'], 0, 10) : '';
            if ($weekStart < $startDate || $weekStart > $endDate) continue;
            
            $userId = $ts['userId'] ?? 'unknown';
            if (!isset($userSummary[$userId])) {
                $userSummary[$userId] = [
                    'userId' => $userId,
                    'totalHours' => 0,
                    'timesheetCount' => 0,
                    'approvedCount' => 0,
                    'pendingCount' => 0,
                ];
            }
            
            $userSummary[$userId]['totalHours'] += (float) ($ts['totalHours'] ?? 0);
            $userSummary[$userId]['timesheetCount']++;
            
            if (($ts['status'] ?? '') === 'Approved') {
                $userSummary[$userId]['approvedCount']++;
            } elseif (($ts['status'] ?? '') === 'Submitted') {
                $userSummary[$userId]['pendingCount']++;
            }
        }
        
        return array_values($userSummary);
    }
    
    // ==================== VALIDATION ====================
    
    /**
     * Validate time entry
     */
    public function validateTimeEntry(array $data): array {
        $errors = [];
        
        // Check hours
        $hours = (float) ($data['hoursWorked'] ?? 0);
        if ($hours <= 0) {
            $errors[] = 'Hours must be greater than 0';
        }
        if ($hours > 24) {
            $errors[] = 'Hours cannot exceed 24 per entry';
        }
        
        // Check daily limit
        $date = $data['entryDate'] ?? '';
        $userId = $data['userId'] ?? '';
        $timesheetId = $data['timesheetId'] ?? '';
        $existingCards = $this->getTimeCardsByTimesheet($timesheetId);
        $dailyTotal = 0;
        foreach ($existingCards as $card) {
            $cardDate = is_string($card['entryDate']) ? substr($card['entryDate'], 0, 10) : '';
            if ($cardDate === $date && ($card['id'] ?? '') !== ($data['id'] ?? '')) {
                $dailyTotal += (float) ($card['hoursWorked'] ?? 0);
            }
        }
        
        if (($dailyTotal + $hours) > 24) {
            $errors[] = 'Total hours for a day cannot exceed 24';
        }
        
        // Check task
        if (empty($data['taskId'])) {
            $errors[] = 'Task is required';
        }
        
        return $errors;
    }
    
    /**
     * Check if timesheet can be edited
     */
    public function canEditTimesheet(string $timesheetId): bool {
        $timesheet = $this->getTimesheet($timesheetId);
        if (!$timesheet) return false;
        
        $status = $timesheet['status'] ?? 'Draft';
        return in_array($status, ['Draft', 'Rejected']);
    }
}
