<?php
/**
 * Timesheet API Router
 * Handles all /api/timesheet/* endpoints.
 * Included by php-backend/index.php — do not run standalone.
 *
 * Routes:
 *   GET  /api/timesheet/tasks                  — list active tasks
 *   GET  /api/timesheet/week?week_start=YYYY-MM-DD — get/create timesheet + time cards
 *   POST /api/timesheet/entry                  — create time card
 *   PUT  /api/timesheet/entry/{id}             — update time card
 *   DELETE /api/timesheet/entry/{id}           — delete time card
 *   POST /api/timesheet/submit                 — submit timesheet for approval
 *   GET  /api/timesheet/admin/list             — admin: list all timesheets
 *   POST /api/timesheet/admin/approve          — admin: approve timesheet
 *   POST /api/timesheet/admin/reject           — admin: reject timesheet
 *   GET  /api/timesheet/admin/detail/{id}      — admin: timesheet detail with cards
 *   GET  /api/timesheet/reports/monthly        — monthly report for current user
 *   GET  /api/timesheet/reports/admin/monthly  — admin: all users monthly report
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AuthHelper.php';
require_once __DIR__ . '/models/Timesheet.php';
require_once __DIR__ . '/models/TimeCard.php';
require_once __DIR__ . '/models/Task.php';

// Only handle /api/timesheet/* paths
if (strpos($path, '/api/timesheet') !== 0) {
    return; // Not our route — let index.php continue
}

// ─── GET /api/timesheet/tasks ─────────────────────────────────────────────────
if ($path === '/api/timesheet/tasks' && $method === 'GET') {
    $user = TimesheetAuth::requireAuth();
    try {
        jsonResponse(200, Task::getAll());
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// ─── GET /api/timesheet/week ──────────────────────────────────────────────────
if ($path === '/api/timesheet/week' && $method === 'GET') {
    $user = TimesheetAuth::requireAuth();

    $weekStart = $_GET['week_start'] ?? '';
    if (!$weekStart || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
        // Default to current Monday
        $weekStart = date('Y-m-d', strtotime('monday this week'));
    }

    // Ensure it's actually a Monday
    $weekStartTs = strtotime($weekStart);
    $dayOfWeek = (int) date('N', $weekStartTs); // 1=Mon, 7=Sun
    if ($dayOfWeek !== 1) {
        $weekStart = date('Y-m-d', strtotime('monday this week', $weekStartTs));
    }

    try {
        $timesheet = Timesheet::getOrCreate($user['uid'], $user['name'], $weekStart);
        $timeCards = TimeCard::getByTimesheet((int) $timesheet['id']);
        jsonResponse(200, [
            'timesheet' => $timesheet,
            'time_cards' => $timeCards,
        ]);
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// ─── POST /api/timesheet/entry ────────────────────────────────────────────────
if ($path === '/api/timesheet/entry' && $method === 'POST') {
    $user = TimesheetAuth::requireAuth();
    $body = getJsonBody();

    if (!$body) {
        jsonResponse(400, ['error' => 'Invalid JSON body']);
    }

    $weekStart   = trim($body['week_start'] ?? '');
    $entryDate   = trim($body['entry_date'] ?? '');
    $taskId      = isset($body['task_id']) ? (int) $body['task_id'] : null;
    $hoursWorked = isset($body['hours_worked']) ? (float) $body['hours_worked'] : 0;
    $description = trim($body['description'] ?? '');

    // Validation
    $errors = [];
    if (!$weekStart || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
        $errors[] = 'Invalid week_start date';
    }
    if (!$entryDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
        $errors[] = 'Invalid entry_date';
    }
    if (!$taskId || $taskId <= 0) {
        $errors[] = 'task_id is required';
    }
    if ($hoursWorked <= 0 || $hoursWorked > 24) {
        $errors[] = 'hours_worked must be between 0.25 and 24';
    }
    if (!empty($errors)) {
        jsonResponse(400, ['error' => implode('. ', $errors)]);
    }

    // Ensure entry_date is within the week
    $weekStartTs = strtotime($weekStart);
    $weekEndTs   = $weekStartTs + (6 * 86400);
    $entryTs     = strtotime($entryDate);
    if ($entryTs < $weekStartTs || $entryTs > $weekEndTs) {
        jsonResponse(400, ['error' => 'entry_date must be within the selected week']);
    }

    try {
        $timesheet = Timesheet::getOrCreate($user['uid'], $user['name'], $weekStart);

        if ($timesheet['status'] !== 'Draft') {
            jsonResponse(403, ['error' => 'Cannot add entries to a ' . $timesheet['status'] . ' timesheet']);
        }

        // Check for duplicate date+task
        if (TimeCard::exists((int) $timesheet['id'], $entryDate, $taskId)) {
            jsonResponse(409, ['error' => 'An entry for this date and task already exists']);
        }

        // Check daily max hours (24h limit)
        $dayTotal = TimeCard::getDayTotal((int) $timesheet['id'], $entryDate);
        if ($dayTotal + $hoursWorked > 24) {
            jsonResponse(400, ['error' => "Adding {$hoursWorked}h would exceed the 24-hour daily limit (current: {$dayTotal}h)"]);
        }

        $id = TimeCard::create((int) $timesheet['id'], $entryDate, $taskId, $hoursWorked, $description ?: null);
        $card = TimeCard::getById($id);
        jsonResponse(201, $card);
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// ─── PUT /api/timesheet/entry/{id} ───────────────────────────────────────────
if (preg_match('#^/api/timesheet/entry/(\d+)$#', $path, $m) && $method === 'PUT') {
    $user   = TimesheetAuth::requireAuth();
    $cardId = (int) $m[1];
    $body   = getJsonBody();

    if (!$body) {
        jsonResponse(400, ['error' => 'Invalid JSON body']);
    }

    $taskId      = isset($body['task_id']) ? (int) $body['task_id'] : null;
    $hoursWorked = isset($body['hours_worked']) ? (float) $body['hours_worked'] : 0;
    $description = trim($body['description'] ?? '');

    // Validation
    if (!$taskId || $taskId <= 0) {
        jsonResponse(400, ['error' => 'task_id is required']);
    }
    if ($hoursWorked <= 0 || $hoursWorked > 24) {
        jsonResponse(400, ['error' => 'hours_worked must be between 0.25 and 24']);
    }

    try {
        $card = TimeCard::getById($cardId);
        if (!$card) {
            jsonResponse(404, ['error' => 'Time card not found']);
        }

        // Verify ownership via timesheet
        $timesheet = Timesheet::getById((int) $card['timesheet_id']);
        if (!$timesheet || $timesheet['user_id'] !== $user['uid']) {
            jsonResponse(403, ['error' => 'Access denied']);
        }
        if ($timesheet['status'] !== 'Draft') {
            jsonResponse(403, ['error' => 'Cannot edit a ' . $timesheet['status'] . ' timesheet']);
        }

        // Check duplicate (exclude current card)
        if (TimeCard::exists((int) $timesheet['id'], $card['entry_date'], $taskId, $cardId)) {
            jsonResponse(409, ['error' => 'An entry for this date and task already exists']);
        }

        // Check daily max hours
        $dayTotal = TimeCard::getDayTotal((int) $timesheet['id'], $card['entry_date']);
        $currentHours = (float) $card['hours_worked'];
        if (($dayTotal - $currentHours + $hoursWorked) > 24) {
            jsonResponse(400, ['error' => 'Update would exceed the 24-hour daily limit']);
        }

        TimeCard::update($cardId, $taskId, $hoursWorked, $description ?: null);
        jsonResponse(200, TimeCard::getById($cardId));
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// ─── DELETE /api/timesheet/entry/{id} ────────────────────────────────────────
if (preg_match('#^/api/timesheet/entry/(\d+)$#', $path, $m) && $method === 'DELETE') {
    $user   = TimesheetAuth::requireAuth();
    $cardId = (int) $m[1];

    try {
        $card = TimeCard::getById($cardId);
        if (!$card) {
            jsonResponse(404, ['error' => 'Time card not found']);
        }

        $timesheet = Timesheet::getById((int) $card['timesheet_id']);
        if (!$timesheet || $timesheet['user_id'] !== $user['uid']) {
            jsonResponse(403, ['error' => 'Access denied']);
        }
        if ($timesheet['status'] !== 'Draft') {
            jsonResponse(403, ['error' => 'Cannot delete entries from a ' . $timesheet['status'] . ' timesheet']);
        }

        TimeCard::delete($cardId);
        jsonResponse(200, ['message' => 'Entry deleted']);
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// ─── POST /api/timesheet/submit ───────────────────────────────────────────────
if ($path === '/api/timesheet/submit' && $method === 'POST') {
    $user = TimesheetAuth::requireAuth();
    $body = getJsonBody();
    $timesheetId = (int) ($body['timesheet_id'] ?? 0);

    if (!$timesheetId) {
        jsonResponse(400, ['error' => 'timesheet_id is required']);
    }

    try {
        $timesheet = Timesheet::getById($timesheetId);
        if (!$timesheet) {
            jsonResponse(404, ['error' => 'Timesheet not found']);
        }
        if ($timesheet['user_id'] !== $user['uid']) {
            jsonResponse(403, ['error' => 'Access denied']);
        }
        if ($timesheet['status'] !== 'Draft') {
            jsonResponse(400, ['error' => 'Only Draft timesheets can be submitted']);
        }
        if ((float) $timesheet['total_hours'] <= 0) {
            jsonResponse(400, ['error' => 'Cannot submit an empty timesheet. Please add at least one time entry.']);
        }

        Timesheet::submit($timesheetId);
        jsonResponse(200, ['message' => 'Timesheet submitted for approval']);
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// ─── GET /api/timesheet/admin/list ───────────────────────────────────────────
if ($path === '/api/timesheet/admin/list' && $method === 'GET') {
    TimesheetAuth::requireAdmin();
    $status = $_GET['status'] ?? null;
    try {
        jsonResponse(200, Timesheet::getAllForAdmin($status));
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// ─── GET /api/timesheet/admin/detail/{id} ────────────────────────────────────
if (preg_match('#^/api/timesheet/admin/detail/(\d+)$#', $path, $m) && $method === 'GET') {
    TimesheetAuth::requireAdmin();
    $timesheetId = (int) $m[1];
    try {
        $timesheet = Timesheet::getById($timesheetId);
        if (!$timesheet) {
            jsonResponse(404, ['error' => 'Timesheet not found']);
        }
        $timeCards = TimeCard::getByTimesheet($timesheetId);
        jsonResponse(200, ['timesheet' => $timesheet, 'time_cards' => $timeCards]);
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// ─── POST /api/timesheet/admin/approve ───────────────────────────────────────
if ($path === '/api/timesheet/admin/approve' && $method === 'POST') {
    TimesheetAuth::requireAdmin();
    $body = getJsonBody();
    $timesheetId = (int) ($body['timesheet_id'] ?? 0);
    $comment     = trim($body['comment'] ?? '');

    if (!$timesheetId) {
        jsonResponse(400, ['error' => 'timesheet_id is required']);
    }

    try {
        $timesheet = Timesheet::getById($timesheetId);
        if (!$timesheet) {
            jsonResponse(404, ['error' => 'Timesheet not found']);
        }
        if ($timesheet['status'] !== 'Submitted') {
            jsonResponse(400, ['error' => 'Only Submitted timesheets can be approved']);
        }

        Timesheet::approve($timesheetId, $comment ?: null);
        jsonResponse(200, ['message' => 'Timesheet approved']);
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// ─── POST /api/timesheet/admin/reject ────────────────────────────────────────
if ($path === '/api/timesheet/admin/reject' && $method === 'POST') {
    TimesheetAuth::requireAdmin();
    $body = getJsonBody();
    $timesheetId = (int) ($body['timesheet_id'] ?? 0);
    $comment     = trim($body['comment'] ?? '');

    if (!$timesheetId) {
        jsonResponse(400, ['error' => 'timesheet_id is required']);
    }
    if (!$comment) {
        jsonResponse(400, ['error' => 'A rejection comment is required']);
    }

    try {
        $timesheet = Timesheet::getById($timesheetId);
        if (!$timesheet) {
            jsonResponse(404, ['error' => 'Timesheet not found']);
        }
        if ($timesheet['status'] !== 'Submitted') {
            jsonResponse(400, ['error' => 'Only Submitted timesheets can be rejected']);
        }

        Timesheet::reject($timesheetId, $comment);
        jsonResponse(200, ['message' => 'Timesheet rejected']);
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// ─── GET /api/timesheet/reports/monthly ──────────────────────────────────────
if ($path === '/api/timesheet/reports/monthly' && $method === 'GET') {
    $user  = TimesheetAuth::requireAuth();
    $year  = (int) ($_GET['year']  ?? date('Y'));
    $month = (int) ($_GET['month'] ?? date('n'));

    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
        jsonResponse(400, ['error' => 'Invalid year or month']);
    }

    try {
        $data = Timesheet::getMonthlyReport($user['uid'], $year, $month);
        jsonResponse(200, ['year' => $year, 'month' => $month, 'data' => $data]);
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// ─── GET /api/timesheet/reports/admin/monthly ────────────────────────────────
if ($path === '/api/timesheet/reports/admin/monthly' && $method === 'GET') {
    TimesheetAuth::requireAdmin();
    $year  = (int) ($_GET['year']  ?? date('Y'));
    $month = (int) ($_GET['month'] ?? date('n'));

    try {
        $data = Timesheet::getAllUsersMonthlyReport($year, $month);
        jsonResponse(200, ['year' => $year, 'month' => $month, 'data' => $data]);
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// ─── GET /api/timesheet/tasks/manage (admin) ─────────────────────────────────
if ($path === '/api/timesheet/tasks/manage' && $method === 'POST') {
    TimesheetAuth::requireAdmin();
    $body = getJsonBody();
    $name = trim($body['name'] ?? '');
    $desc = trim($body['description'] ?? '');

    if (!$name) {
        jsonResponse(400, ['error' => 'Task name is required']);
    }

    try {
        $id = Task::create($name, $desc ?: null);
        jsonResponse(201, ['id' => $id, 'name' => $name, 'description' => $desc]);
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}
