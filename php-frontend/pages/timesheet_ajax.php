<?php
/**
 * Timesheet AJAX Handler
 * Handles all AJAX requests for timesheet operations
 */

require_once __DIR__ . '/../includes/TimesheetModel.php';

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$model = new TimesheetModel();
$action = $_GET['action'] ?? '';
$userId = $user['uid'] ?? '';

$response = ['success' => false, 'message' => 'Invalid action'];

try {
    switch ($action) {
        case 'get_entry':
            // Get single time card entry
            $entryId = $_GET['id'] ?? '';
            $entry = $model->getTimeCard($entryId);
            
            if ($entry && $entry['userId'] === $userId) {
                $response = ['success' => true, 'entry' => $entry];
            } else {
                $response = ['success' => false, 'message' => 'Entry not found or access denied'];
            }
            break;
            
        case 'save_entry':
            // Save or update time card entry
            $input = json_decode(file_get_contents('php://input'), true);
            
            $data = [
                'id'          => $input['entryId']    ?? '',
                'timesheetId' => $input['timesheetId'] ?? '',
                'userId'      => $userId,
                'entryDate'   => $input['entryDate']   ?? '',
                'taskId'      => $input['taskId']      ?? '',
                'hoursWorked' => $input['hoursWorked'] ?? 0,
                'description' => $input['description'] ?? '',
                'priority'    => $input['priority']    ?? 'medium',
                'cardStatus'  => $input['cardStatus']  ?? 'pending',
            ];
            
            // Check if timesheet can be edited
            if (!$model->canEditTimesheet($data['timesheetId'])) {
                $response = ['success' => false, 'errors' => ['Timesheet cannot be edited (already submitted or approved)']];
                break;
            }
            
            // Validate
            $errors = $model->validateTimeEntry($data);
            if (!empty($errors)) {
                $response = ['success' => false, 'errors' => $errors];
                break;
            }
            
            // Save entry
            $saved = $model->saveTimeCard($data);
            
            // Recalculate timesheet total
            $model->recalculateTotalHours($data['timesheetId']);
            
            $response = ['success' => true, 'entry' => $saved];
            break;
            
        case 'delete_entry':
            // Delete time card entry
            $input = json_decode(file_get_contents('php://input'), true);
            $entryId = $input['entryId'] ?? '';
            
            $entry = $model->getTimeCard($entryId);
            if (!$entry || $entry['userId'] !== $userId) {
                $response = ['success' => false, 'message' => 'Entry not found'];
                break;
            }
            
            // Check if timesheet can be edited
            if (!$model->canEditTimesheet($entry['timesheetId'])) {
                $response = ['success' => false, 'message' => 'Timesheet cannot be edited'];
                break;
            }
            
            $model->deleteTimeCard($entryId);
            $model->recalculateTotalHours($entry['timesheetId']);
            
            $response = ['success' => true];
            break;
            
        case 'submit_timesheet':
            // Submit timesheet for approval
            $timesheetId = $_GET['timesheetId'] ?? '';
            $timesheet = $model->getTimesheet($timesheetId);
            
            if (!$timesheet || $timesheet['userId'] !== $userId) {
                $response = ['success' => false, 'message' => 'Timesheet not found'];
                break;
            }
            
            if (($timesheet['status'] ?? '') !== 'Draft') {
                $response = ['success' => false, 'message' => 'Timesheet is already submitted'];
                break;
            }
            
            // Check if there are any entries
            $timeCards = $model->getTimeCardsByTimesheet($timesheetId);
            if (empty($timeCards)) {
                $response = ['success' => false, 'message' => 'Cannot submit empty timesheet'];
                break;
            }
            
            $model->updateTimesheetStatus($timesheetId, 'Submitted', null, null);
            
            // Update all time cards to submitted status
            foreach ($timeCards as $card) {
                $model->api->updateDocument('timeCards', $card['id'], ['status' => 'Submitted']);
            }
            
            $response = ['success' => true];
            break;
            
        case 'approve_timesheet':
            // Admin / Super Admin / Ultra Super Admin: Approve timesheet
            if (!canApproveTimesheets()) {
                $response = ['success' => false, 'message' => 'Access denied. Admin or above required.'];
                break;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $timesheetId = $input['timesheetId'] ?? '';
            $comments = $input['comments'] ?? '';
            
            $timesheet = $model->getTimesheet($timesheetId);
            if (!$timesheet) {
                $response = ['success' => false, 'message' => 'Timesheet not found'];
                break;
            }
            
            $model->updateTimesheetStatus($timesheetId, 'Approved', $userId, null);
            
            // Update all time cards
            $timeCards = $model->getTimeCardsByTimesheet($timesheetId);
            foreach ($timeCards as $card) {
                $model->api->updateDocument('timeCards', $card['id'], ['status' => 'Approved']);
            }
            
            $response = ['success' => true];
            break;
            
        case 'reject_timesheet':
            // Admin / Super Admin / Ultra Super Admin: Reject timesheet
            if (!canApproveTimesheets()) {
                $response = ['success' => false, 'message' => 'Access denied. Admin or above required.'];
                break;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $timesheetId = $input['timesheetId'] ?? '';
            $reason = $input['reason'] ?? '';
            
            if (empty($reason)) {
                $response = ['success' => false, 'message' => 'Rejection reason is required'];
                break;
            }
            
            $timesheet = $model->getTimesheet($timesheetId);
            if (!$timesheet) {
                $response = ['success' => false, 'message' => 'Timesheet not found'];
                break;
            }
            
            $model->updateTimesheetStatus($timesheetId, 'Rejected', $userId, $reason);
            
            // Update all time cards
            $timeCards = $model->getTimeCardsByTimesheet($timesheetId);
            foreach ($timeCards as $card) {
                $model->api->updateDocument('timeCards', $card['id'], ['status' => 'Rejected']);
            }
            
            $response = ['success' => true];
            break;
            
        case 'reopen_timesheet':
            // Admin / Super Admin / Ultra Super Admin: Reopen rejected timesheet
            if (!canApproveTimesheets()) {
                $response = ['success' => false, 'message' => 'Access denied. Admin or above required.'];
                break;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $timesheetId = $input['timesheetId'] ?? '';
            
            $timesheet = $model->getTimesheet($timesheetId);
            if (!$timesheet || ($timesheet['status'] ?? '') !== 'Rejected') {
                $response = ['success' => false, 'message' => 'Only rejected timesheets can be reopened'];
                break;
            }
            
            $model->updateTimesheetStatus($timesheetId, 'Draft', $userId, null);
            
            // Reset time cards to draft
            $timeCards = $model->getTimeCardsByTimesheet($timesheetId);
            foreach ($timeCards as $card) {
                $model->api->updateDocument('timeCards', $card['id'], ['status' => 'Draft']);
            }
            
            $response = ['success' => true];
            break;
            
        case 'move_entry':
            // Drag & drop: move a time card to a different date
            $input = json_decode(file_get_contents('php://input'), true);
            $entryId     = $input['entryId']     ?? '';
            $newDate     = $input['newDate']      ?? '';
            $timesheetId = $input['timesheetId']  ?? '';

            $entry = $model->getTimeCard($entryId);
            if (!$entry || $entry['userId'] !== $userId) {
                $response = ['success' => false, 'message' => 'Entry not found'];
                break;
            }
            if (!$model->canEditTimesheet($timesheetId)) {
                $response = ['success' => false, 'message' => 'Timesheet cannot be edited'];
                break;
            }
            if (empty($newDate)) {
                $response = ['success' => false, 'message' => 'Invalid date'];
                break;
            }

            $model->api->updateDocument('timeCards', $entryId, [
                'entryDate' => $newDate . 'T00:00:00Z',
                'updatedAt' => gmdate('c'),
            ]);
            $model->recalculateTotalHours($timesheetId);
            $response = ['success' => true];
            break;

        case 'get_report_data':
            // Get data for reports/charts
            $reportType = $_GET['type'] ?? 'weekly';
            $startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-4 weeks'));
            $endDate = $_GET['end'] ?? date('Y-m-d');
            
            if ($reportType === 'detail') {
                // Get full timesheet detail for admin view modal
                $timesheetId = $_GET['timesheetId'] ?? '';
                $timesheet = $model->getTimesheet($timesheetId);
                if (!$timesheet) {
                    $response = ['success' => false, 'message' => 'Not found'];
                    break;
                }
                $timeCards = $model->getTimeCardsByTimesheet($timesheetId);
                foreach ($timeCards as &$card) {
                    $task = $model->getTask($card['taskId'] ?? '');
                    $card['taskName'] = $task['name'] ?? 'Unknown';
                }
                $history = $model->getApprovalHistory($timesheetId);
                $response = ['success' => true, 'data' => array_merge($timesheet, [
                    'timeCards' => $timeCards,
                    'history' => $history,
                ])];
                break;
            } elseif ($reportType === 'by_task') {
                $data = $model->getHoursByTask($userId, $startDate, $endDate);
            } elseif ($reportType === 'all_users' && isAdmin()) {
                $data = $model->getAllUsersTimesheetSummary($startDate, $endDate);
            } else {
                $data = $model->getUserHoursByWeek($userId, $startDate, $endDate);
            }
            
            $response = ['success' => true, 'data' => $data];
            break;
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
