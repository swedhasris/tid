<?php
/**
 * PHP Backend Router
 * Replicates the Node.js/Express backend functionality.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firestore-client.php';

// CORS headers for API requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load config if available (Firestore features will be unavailable otherwise)
$firestoreAvailable = AppConfig::load();

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

// Remove trailing slash for consistency
$path = rtrim($path, '/') ?: '/';

require_once __DIR__ . '/ai-endpoints.php';

/**
 * Helper to send JSON response.
 */
function jsonResponse(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Helper to parse JSON body.
 */
function getJsonBody(): ?array {
    $input = file_get_contents('php://input');
    if (!$input) return null;
    $decoded = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(400, ['error' => 'Invalid JSON body']);
    }
    return $decoded;
}

/**
 * Helper: convert Firestore timestamp values to ms for sorting.
 */
function timestampToMs($value): int {
    if (!$value) return 0;
    if (is_string($value)) {
        $t = strtotime($value);
        return $t !== false ? $t * 1000 : 0;
    }
    if (is_array($value) && isset($value['seconds'])) {
        return (int) ($value['seconds'] * 1000 + ($value['nanos'] ?? 0) / 1000000);
    }
    return 0;
}

/**
 * API Routes
 */

// 1. Health Check
if ($path === '/api/health' && $method === 'GET') {
    jsonResponse(200, ['status' => 'ok']);
}

// 2. DB Test
if ($path === '/api/db-test' && $method === 'GET') {
    try {
        $client = new FirestoreClient();
        $docs = $client->listDocuments('tickets');
        jsonResponse(200, [
            'status' => 'connected',
            'project' => AppConfig::getProjectId(),
            'database' => AppConfig::getDatabaseId(),
            'count' => count($docs),
        ]);
    } catch (Exception $e) {
        jsonResponse(500, [
            'status' => 'error',
            'error' => $e->getMessage(),
            'project' => AppConfig::getProjectId(),
            'database' => AppConfig::getDatabaseId(),
        ]);
    }
}

// 3. Get All Tickets
if ($path === '/api/tickets/all' && $method === 'GET') {
    try {
        $client = new FirestoreClient();
        $tickets = $client->listDocuments('tickets');
        // Sort by createdAt descending in memory
        usort($tickets, function ($a, $b) {
            return timestampToMs($b['createdAt'] ?? null) <=> timestampToMs($a['createdAt'] ?? null);
        });
        jsonResponse(200, $tickets);
    } catch (Exception $e) {
        error_log('Failed to fetch tickets: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch tickets']);
    }
}

// 4. Get Open Tickets
if ($path === '/api/tickets/open' && $method === 'GET') {
    try {
        $client = new FirestoreClient();
        $tickets = $client->listDocuments('tickets');
        $open = array_filter($tickets, function ($t) {
            $status = $t['status'] ?? '';
            return $status !== 'Resolved' && $status !== 'Closed';
        });
        usort($open, function ($a, $b) {
            return timestampToMs($b['createdAt'] ?? null) <=> timestampToMs($a['createdAt'] ?? null);
        });
        jsonResponse(200, array_values($open));
    } catch (Exception $e) {
        error_log('Failed to fetch open tickets: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch open tickets']);
    }
}

// 5. Get Tickets Assigned to User
if (preg_match('#^/api/tickets/assigned/(.+)$#', $path, $matches) && $method === 'GET') {
    $userId = $matches[1];
    try {
        $client = new FirestoreClient();
        $tickets = $client->listDocuments('tickets');
        $assigned = array_filter($tickets, function ($t) use ($userId) {
            return ($t['assignedTo'] ?? '') === $userId;
        });
        usort($assigned, function ($a, $b) {
            return timestampToMs($b['createdAt'] ?? null) <=> timestampToMs($a['createdAt'] ?? null);
        });
        jsonResponse(200, array_values($assigned));
    } catch (Exception $e) {
        error_log('Failed to fetch assigned tickets: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch assigned tickets']);
    }
}

// 6. Get Unassigned Tickets
if ($path === '/api/tickets/unassigned' && $method === 'GET') {
    try {
        $client = new FirestoreClient();
        $tickets = $client->listDocuments('tickets');
        $unassigned = array_filter($tickets, function ($t) {
            return ($t['assignedTo'] ?? '') === '';
        });
        usort($unassigned, function ($a, $b) {
            return timestampToMs($b['createdAt'] ?? null) <=> timestampToMs($a['createdAt'] ?? null);
        });
        jsonResponse(200, array_values($unassigned));
    } catch (Exception $e) {
        error_log('Failed to fetch unassigned tickets: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch unassigned tickets']);
    }
}

// 7. Get Resolved Tickets
if ($path === '/api/tickets/resolved' && $method === 'GET') {
    try {
        $client = new FirestoreClient();
        $tickets = $client->listDocuments('tickets');
        $resolved = array_filter($tickets, function ($t) {
            $status = $t['status'] ?? '';
            return $status === 'Resolved' || $status === 'Closed';
        });
        jsonResponse(200, array_values($resolved));
    } catch (Exception $e) {
        error_log('Failed to fetch resolved tickets: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch resolved tickets']);
    }
}

// 8. Create Ticket
if ($path === '/api/tickets/create' && $method === 'POST') {
    $body = getJsonBody();
    if ($body === null) {
        jsonResponse(400, ['error' => 'Invalid JSON body']);
    }

    try {
        $client = new FirestoreClient();

        // Auto-assignment based on category
        $assignmentGroup = $body['assignmentGroup'] ?? '';
        if (!$assignmentGroup) {
            switch ($body['category'] ?? '') {
                case 'Network': $assignmentGroup = 'Network Team'; break;
                case 'Hardware': $assignmentGroup = 'Hardware Support'; break;
                case 'Software': $assignmentGroup = 'App Support'; break;
                case 'Database': $assignmentGroup = 'DBA Team'; break;
                default: $assignmentGroup = 'Service Desk';
            }
        }

        $now = gmdate('c'); // ISO8601
        $caller = $body['caller'] ?? 'System';

        $ticketData = array_merge($body, [
            'assignmentGroup' => $assignmentGroup,
            'createdAt' => ['timestampValue' => 'REQUEST_TIME'],
            'updatedAt' => ['timestampValue' => 'REQUEST_TIME'],
            'history' => [
                [
                    'action' => 'Ticket Created via API',
                    'timestamp' => $now,
                    'user' => $caller,
                ]
            ],
        ]);

        // Priority notification for high priority
        $priority = $body['priority'] ?? '';
        if ($priority === '1 - Critical' || $priority === '2 - High') {
            $ticketData['history'][] = [
                'action' => 'Manager Notified (High Priority)',
                'timestamp' => $now,
                'user' => 'System Automation',
            ];
        }

        $result = $client->createDocument('tickets', $ticketData);
        jsonResponse(200, $result);
    } catch (Exception $e) {
        error_log('Error creating ticket: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to create ticket: ' . $e->getMessage()]);
    }
}

// 9. Update Ticket
if (preg_match('#^/api/tickets/([^/]+)$#', $path, $matches) && $method === 'PUT') {
    $id = $matches[1];
    $body = getJsonBody();
    if ($body === null) {
        jsonResponse(400, ['error' => 'Invalid JSON body']);
    }

    try {
        $client = new FirestoreClient();
        $updateData = array_merge($body, [
            'updatedAt' => ['timestampValue' => 'REQUEST_TIME'],
        ]);
        $client->updateDocument('tickets', $id, $updateData);
        jsonResponse(200, array_merge(['id' => $id], $body));
    } catch (Exception $e) {
        error_log('Error updating ticket: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to update ticket']);
    }
}

// 10. Delete Ticket
if (preg_match('#^/api/tickets/([^/]+)$#', $path, $matches) && $method === 'DELETE') {
    $id = $matches[1];
    try {
        $client = new FirestoreClient();
        $client->deleteDocument('tickets', $id);
        jsonResponse(200, ['message' => 'Ticket deleted successfully']);
    } catch (Exception $e) {
        error_log('Error deleting ticket: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to delete ticket']);
    }
}

// 11. Manual SLA Escalation Trigger
if ($path === '/api/tickets/trigger-escalation' && $method === 'POST') {
    try {
        require_once __DIR__ . '/sla-engine.php';
        runEscalation();
        jsonResponse(200, ['message' => 'Escalation check triggered manually']);
    } catch (Exception $e) {
        error_log('Error triggering escalation: ' . $e->getMessage());
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

/* ============================================================
   TIMESHEET API ROUTES
   ============================================================ */

require_once __DIR__ . '/timesheet/AuthHelper.php';
require_once __DIR__ . '/timesheet/models/Task.php';
require_once __DIR__ . '/timesheet/models/Timesheet.php';
require_once __DIR__ . '/timesheet/models/TimeCard.php';

// GET /api/timesheet/tasks
if ($path === '/api/timesheet/tasks' && $method === 'GET') {
    try {
        $tasks = Task::getAll();
        jsonResponse(200, $tasks);
    } catch (Exception $e) {
        error_log('Timesheet tasks error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch tasks']);
    }
}

// GET /api/timesheet/week?week_start=YYYY-MM-DD
if ($path === '/api/timesheet/week' && $method === 'GET') {
    $user = TimesheetAuth::requireAuth();
    $weekStart = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
    try {
        $timesheet = Timesheet::getOrCreate($user['uid'], $user['name'], $weekStart);
        $timeCards = TimeCard::getByTimesheet($timesheet['id']);
        jsonResponse(200, [
            'timesheet' => $timesheet,
            'time_cards' => $timeCards,
        ]);
    } catch (Exception $e) {
        error_log('Timesheet week error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch timesheet']);
    }
}

// POST /api/timesheet/entry
if ($path === '/api/timesheet/entry' && $method === 'POST') {
    $user = TimesheetAuth::requireAuth();
    $body = getJsonBody();
    if (!$body) jsonResponse(400, ['error' => 'Invalid JSON body']);

    $weekStart = $body['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
    $entryDate = $body['entry_date'] ?? '';
    $taskId = isset($body['task_id']) ? (int) $body['task_id'] : null;
    $hours = isset($body['hours_worked']) ? (float) $body['hours_worked'] : 0;
    $description = $body['description'] ?? '';

    if (!$entryDate || $hours <= 0) {
        jsonResponse(400, ['error' => 'entry_date and hours_worked are required']);
    }
    if ($hours > 24) {
        jsonResponse(400, ['error' => 'Hours per entry cannot exceed 24']);
    }

    try {
        $timesheet = Timesheet::getOrCreate($user['uid'], $user['name'], $weekStart);
        if ($timesheet['status'] !== 'Draft') {
            jsonResponse(400, ['error' => 'Cannot edit a submitted or approved timesheet']);
        }

        $dayTotal = TimeCard::getDayTotal($timesheet['id'], $entryDate);
        if ($dayTotal + $hours > 24) {
            jsonResponse(400, ['error' => 'Total hours per day cannot exceed 24']);
        }

        if (TimeCard::exists($timesheet['id'], $entryDate, $taskId)) {
            jsonResponse(400, ['error' => 'Duplicate entry for this date and task']);
        }

        $id = TimeCard::create($timesheet['id'], $entryDate, $taskId, $hours, $description);
        Timesheet::updateTotalHours($timesheet['id']);
        jsonResponse(200, ['id' => $id, 'message' => 'Time entry created']);
    } catch (Exception $e) {
        error_log('Timesheet entry create error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to create time entry']);
    }
}

// PUT /api/timesheet/entry/:id
if (preg_match('#^/api/timesheet/entry/(\d+)$#', $path, $matches) && $method === 'PUT') {
    $user = TimesheetAuth::requireAuth();
    $entryId = (int) $matches[1];
    $body = getJsonBody();
    if (!$body) jsonResponse(400, ['error' => 'Invalid JSON body']);

    try {
        $card = TimeCard::getById($entryId);
        if (!$card) jsonResponse(404, ['error' => 'Time entry not found']);

        $timesheet = Timesheet::getById($card['timesheet_id']);
        if (!$timesheet || $timesheet['user_id'] !== $user['uid']) {
            jsonResponse(403, ['error' => 'You do not own this time entry']);
        }
        if ($timesheet['status'] !== 'Draft') {
            jsonResponse(400, ['error' => 'Cannot edit a submitted or approved timesheet']);
        }

        $taskId = isset($body['task_id']) ? (int) $body['task_id'] : (int) $card['task_id'];
        $hours = isset($body['hours_worked']) ? (float) $body['hours_worked'] : (float) $card['hours_worked'];
        $description = $body['description'] ?? $card['description'];
        $entryDate = $body['entry_date'] ?? $card['entry_date'];

        if ($hours <= 0 || $hours > 24) {
            jsonResponse(400, ['error' => 'Hours must be between 0.01 and 24']);
        }

        $dayTotal = TimeCard::getDayTotal($timesheet['id'], $entryDate);
        $adjustedTotal = $dayTotal - (float) $card['hours_worked'] + $hours;
        if ($adjustedTotal > 24) {
            jsonResponse(400, ['error' => 'Total hours per day cannot exceed 24']);
        }

        TimeCard::update($entryId, $taskId, $hours, $description);
        Timesheet::updateTotalHours($timesheet['id']);
        jsonResponse(200, ['message' => 'Time entry updated']);
    } catch (Exception $e) {
        error_log('Timesheet entry update error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to update time entry']);
    }
}

// DELETE /api/timesheet/entry/:id
if (preg_match('#^/api/timesheet/entry/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $user = TimesheetAuth::requireAuth();
    $entryId = (int) $matches[1];

    try {
        $card = TimeCard::getById($entryId);
        if (!$card) jsonResponse(404, ['error' => 'Time entry not found']);

        $timesheet = Timesheet::getById($card['timesheet_id']);
        if (!$timesheet || $timesheet['user_id'] !== $user['uid']) {
            jsonResponse(403, ['error' => 'You do not own this time entry']);
        }
        if ($timesheet['status'] !== 'Draft') {
            jsonResponse(400, ['error' => 'Cannot delete from a submitted or approved timesheet']);
        }

        TimeCard::delete($entryId);
        Timesheet::updateTotalHours($timesheet['id']);
        jsonResponse(200, ['message' => 'Time entry deleted']);
    } catch (Exception $e) {
        error_log('Timesheet entry delete error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to delete time entry']);
    }
}

// POST /api/timesheet/submit
if ($path === '/api/timesheet/submit' && $method === 'POST') {
    $user = TimesheetAuth::requireAuth();
    $body = getJsonBody();
    $timesheetId = isset($body['timesheet_id']) ? (int) $body['timesheet_id'] : 0;

    try {
        $timesheet = Timesheet::getById($timesheetId);
        if (!$timesheet || $timesheet['user_id'] !== $user['uid']) {
            jsonResponse(403, ['error' => 'Unauthorized']);
        }
        if ($timesheet['status'] !== 'Draft') {
            jsonResponse(400, ['error' => 'Timesheet is already ' . $timesheet['status']]);
        }
        Timesheet::submit($timesheetId);
        jsonResponse(200, ['message' => 'Timesheet submitted for approval']);
    } catch (Exception $e) {
        error_log('Timesheet submit error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to submit timesheet']);
    }
}

// GET /api/timesheet/admin/list
if ($path === '/api/timesheet/admin/list' && $method === 'GET') {
    TimesheetAuth::requireAdmin();
    $status = $_GET['status'] ?? null;
    try {
        $timesheets = Timesheet::getAllForAdmin($status);
        jsonResponse(200, $timesheets);
    } catch (Exception $e) {
        error_log('Timesheet admin list error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch timesheets']);
    }
}

// GET /api/timesheet/admin/detail/:id  ← NEW
if (preg_match('#^/api/timesheet/admin/detail/(\d+)$#', $path, $matches) && $method === 'GET') {
    TimesheetAuth::requireAdmin();
    $timesheetId = (int) $matches[1];
    try {
        $timesheet = Timesheet::getById($timesheetId);
        if (!$timesheet) jsonResponse(404, ['error' => 'Timesheet not found']);
        $timeCards = TimeCard::getByTimesheet($timesheetId);
        jsonResponse(200, ['timesheet' => $timesheet, 'time_cards' => $timeCards]);
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// POST /api/timesheet/admin/approve
if ($path === '/api/timesheet/admin/approve' && $method === 'POST') {
    TimesheetAuth::requireAdmin();
    $body = getJsonBody();
    $timesheetId = isset($body['timesheet_id']) ? (int) $body['timesheet_id'] : 0;
    $comment = $body['comment'] ?? null;

    try {
        Timesheet::approve($timesheetId, $comment);
        jsonResponse(200, ['message' => 'Timesheet approved']);
    } catch (Exception $e) {
        error_log('Timesheet approve error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to approve timesheet']);
    }
}

// POST /api/timesheet/admin/reject
if ($path === '/api/timesheet/admin/reject' && $method === 'POST') {
    TimesheetAuth::requireAdmin();
    $body = getJsonBody();
    $timesheetId = isset($body['timesheet_id']) ? (int) $body['timesheet_id'] : 0;
    $comment = $body['comment'] ?? '';

    if (!$comment) {
        jsonResponse(400, ['error' => 'Rejection comment is required']);
    }

    try {
        Timesheet::reject($timesheetId, $comment);
        jsonResponse(200, ['message' => 'Timesheet rejected']);
    } catch (Exception $e) {
        error_log('Timesheet reject error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to reject timesheet']);
    }
}

// GET /api/timesheet/reports/weekly
if ($path === '/api/timesheet/reports/weekly' && $method === 'GET') {
    $user = TimesheetAuth::requireAuth();
    $weekStart = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
    try {
        $hours = Timesheet::getWeeklyHoursByUser($user['uid'], $weekStart);
        jsonResponse(200, ['week_start' => $weekStart, 'daily_hours' => $hours]);
    } catch (Exception $e) {
        error_log('Timesheet weekly report error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch weekly report']);
    }
}

// GET /api/timesheet/reports/monthly
if ($path === '/api/timesheet/reports/monthly' && $method === 'GET') {
    $user = TimesheetAuth::requireAuth();
    $year = (int) ($_GET['year'] ?? date('Y'));
    $month = (int) ($_GET['month'] ?? date('n'));
    try {
        $isAdmin = TimesheetAuth::isAdmin();
        if ($isAdmin && isset($_GET['all_users'])) {
            $data = Timesheet::getAllUsersMonthlyReport($year, $month);
        } else {
            $data = Timesheet::getMonthlyReport($user['uid'], $year, $month);
        }
        jsonResponse(200, ['year' => $year, 'month' => $month, 'data' => $data]);
    } catch (Exception $e) {
        error_log('Timesheet monthly report error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch monthly report']);
    }
}

/* ============================================================
   TIMESHEET PAGE ROUTES (Bootstrap HTML UI)
   ============================================================ */

if ($path === '/timesheet' && $method === 'GET') {
    require_once __DIR__ . '/timesheet/views/dashboard.php';
    exit;
}

if ($path === '/timesheet/admin' && $method === 'GET') {
    require_once __DIR__ . '/timesheet/views/admin.php';
    exit;
}

if ($path === '/timesheet/reports' && $method === 'GET') {
    require_once __DIR__ . '/timesheet/views/reports.php';
    exit;
}

// 12. Static file serving for production (non-API routes)
$distPath = __DIR__ . '/../dist';
if (strpos($path, '/api/') !== 0) {
    $filePath = $distPath . $path;
    if (is_file($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'html' => 'text/html',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
        ];
        $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $contentType);
        readfile($filePath);
        exit;
    }

    // SPA fallback: serve index.html for client-side routing
    $indexPath = $distPath . '/index.html';
    if (is_file($indexPath)) {
        header('Content-Type: text/html');
        readfile($indexPath);
        exit;
    }
}

// Unknown API route
if (strpos($path, '/api/') === 0) {
    jsonResponse(404, ['error' => 'Not found']);
}

// Nothing matched and no dist/index.html
http_response_code(404);
echo 'Not found';
