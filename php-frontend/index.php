<?php
/**
 * Main Router - PHP Frontend for Connect IT
 * Replaces React Router with PHP routing
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/firebase-api.php';

$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? '';
$filter = $_GET['filter'] ?? '';

// Public pages (no login required)
$publicPages = ['login', 'register', 'logout'];

// Check authentication
if (!in_array($page, $publicPages) && !isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    redirect('/login');
}

// Initialize auth and API
$auth = new Auth();
$api = new FirebaseAPI();

// Route handling
try {
    switch ($page) {
        case 'login':
            if (isLoggedIn()) {
                redirect('/dashboard');
            }
            
            $error = '';
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $demoRole = $_POST['demo_login'] ?? '';
                
                if ($demoRole) {
                    // Demo login
                    if ($auth->demoLogin($demoRole)) {
                        setFlash('success', 'Welcome, Demo ' . ucfirst($demoRole) . '!');
                        $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . '/?page=dashboard';
                        unset($_SESSION['redirect_after_login']);
                        header('Location: ' . $redirect);
                        exit;
                    }
                } elseif ($email && $password) {
                    // Regular login
                    if ($auth->login($email, $password)) {
                        setFlash('success', 'Welcome back!');
                        $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . '/?page=dashboard';
                        unset($_SESSION['redirect_after_login']);
                        header('Location: ' . $redirect);
                        exit;
                    } else {
                        $error = 'Invalid email or password.';
                    }
                }
            }
            
            // Render login page without layout
            include __DIR__ . '/pages/login.php';
            break;

        case 'register':
            if (isLoggedIn()) {
                redirect('/dashboard');
            }
            
            $error = '';
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $name = $_POST['name'] ?? '';
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'user';
                
                if ($name && $email && $password) {
                    if ($auth->register($name, $email, $password, $role)) {
                        setFlash('success', 'Account created successfully!');
                        redirect('/dashboard');
                    } else {
                        $error = 'Email already registered.';
                    }
                }
            }
            
            include __DIR__ . '/pages/register.php';
            break;

        case 'logout':
            $auth->logout();
            setFlash('success', 'You have been logged out.');
            redirect('/login');
            break;

        case 'dashboard':
            $tickets = [];
            try {
                $tickets = $api->listDocuments('tickets');
            } catch (Exception $e) {
                $error = 'Failed to load tickets';
            }
            
            // Calculate stats
            $stats = [
                'total' => count($tickets),
                'open' => count(array_filter($tickets, fn($t) => !in_array($t['status'] ?? '', ['Resolved', 'Closed']))),
                'resolved' => count(array_filter($tickets, fn($t) => in_array($t['status'] ?? '', ['Resolved', 'Closed']))),
                'critical' => count(array_filter($tickets, fn($t) => str_contains($t['priority'] ?? '', 'Critical'))),
            ];
            
            renderLayout('Dashboard', function($data) use ($stats, $tickets) {
                include __DIR__ . '/pages/dashboard.php';
            }, ['stats' => $stats, 'tickets' => $tickets]);
            break;

        case 'tickets':
            $tickets = [];
            $agents  = [];
            $tasks   = [];
            
            try {
                $tickets = $api->listDocuments('tickets');
                $users   = $api->listDocuments('users');
                $agents  = array_filter($users, fn($u) => in_array($u['role'] ?? '', ['agent', 'admin']));
                $tasks   = $api->listDocuments('tasks');
            } catch (Exception $e) {
                $error = 'Failed to load tickets';
            }
            
            // Handle ticket creation
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
                $user = getCurrentUser();
                $priority = calculatePriority($_POST['impact'] ?? '2 - Medium', $_POST['urgency'] ?? '2 - Medium');
                
                // Get SLA policy
                $slaPolicies = $api->listDocuments('sla_policies');
                $matchingPolicy = null;
                foreach ($slaPolicies as $p) {
                    if (($p['priority'] ?? '') === $priority) {
                        $matchingPolicy = $p;
                        break;
                    }
                }
                $responseHours = $matchingPolicy['responseTimeHours'] ?? 4;
                $resolutionHours = $matchingPolicy['resolutionTimeHours'] ?? 24;
                
                $now = time();
                $responseDeadline   = $now + $responseHours * 3600;
                // Resolution clock starts after response deadline ends
                $resolutionDeadline = $responseDeadline + $resolutionHours * 3600;
                $responseDeadlineStr   = gmdate('c', $responseDeadline);
                $resolutionDeadlineStr = gmdate('c', $resolutionDeadline);
                
                // Auto-assignment
                $category = $_POST['category'] ?? '';
                $assignmentGroup = match($category) {
                    'Network' => 'Network Team',
                    'Hardware' => 'Hardware Support',
                    'Software' => 'App Support',
                    'Database' => 'DBA Team',
                    default => 'Service Desk',
                };

                // Validate task selection
                $taskId = $_POST['taskId'] ?? '';
                if (empty($taskId)) {
                    setFlash('error', 'Please select a task to link this ticket to.');
                    redirect('/tickets?action=new');
                }
                $linkedTask = null;
                try { $linkedTask = $api->getDocument('tasks', $taskId); } catch (Exception $e) {}
                if (!$linkedTask) {
                    setFlash('error', 'Selected task does not exist. Please choose a valid task.');
                    redirect('/tickets?action=new');
                }

                // Auto-assign to agent with least workload if none selected
                $assignedTo = $_POST['assignedTo'] ?? '';
                if (empty($assignedTo)) {
                    try {
                        $allUsers2   = $api->listDocuments('users');
                        $agentUsers2 = array_filter($allUsers2, fn($u) => in_array($u['role'] ?? '', ['agent', 'admin']));
                        $allTickets2 = $api->listDocuments('tickets');
                        $workload2   = [];
                        foreach ($agentUsers2 as $ag) {
                            $uid = $ag['uid'] ?? $ag['id'];
                            $workload2[$uid] = 0;
                        }
                        foreach ($allTickets2 as $t) {
                            $aid = $t['assignedTo'] ?? '';
                            if ($aid && isset($workload2[$aid]) && !in_array($t['status'] ?? '', ['Resolved', 'Closed'])) {
                                $workload2[$aid]++;
                            }
                        }
                        if (!empty($workload2)) { asort($workload2); $assignedTo = array_key_first($workload2); }
                    } catch (Exception $e) {}
                }
                
                $ticketData = [
                    'number'              => 'INC' . mt_rand(1000000, 9999999),
                    'caller'              => $_POST['caller'] ?? '',
                    'affectedUser'        => $_POST['affectedUser'] ?? '',
                    'category'            => $category,
                    'subcategory'         => $_POST['subcategory'] ?? '',
                    'service'             => $_POST['service'] ?? '',
                    'cmdbItem'            => $_POST['cmdbItem'] ?? '',
                    'title'               => $_POST['title'] ?? '',
                    'description'         => $_POST['description'] ?? '',
                    'channel'             => 'Web',
                    'impact'              => $_POST['impact'] ?? '2 - Medium',
                    'urgency'             => $_POST['urgency'] ?? '2 - Medium',
                    'priority'            => $priority,
                    'assignmentGroup'     => $assignmentGroup,
                    'assignedTo'          => $assignedTo,
                    'taskId'              => $taskId,
                    'taskName'            => $linkedTask['name'] ?? '',
                    'status'              => 'New',
                    'createdBy'           => $user['uid'] ?? '',
                    'responseDeadline'    => $responseDeadlineStr,
                    'resolutionDeadline'  => $resolutionDeadlineStr,
                    'responseSlaStatus'   => 'In Progress',
                    'resolutionSlaStatus' => 'In Progress',
                    'totalPausedTime'     => 0,
                    'history'             => [
                        ['action' => 'Ticket Created', 'timestamp' => gmdate('c'), 'user' => $user['name'] ?? 'System'],
                        ['action' => 'Linked to task: ' . ($linkedTask['name'] ?? $taskId), 'timestamp' => gmdate('c'), 'user' => 'System'],
                    ],
                ];
                
                // Add priority notification
                if (str_contains($priority, 'Critical') || str_contains($priority, 'High')) {
                    $ticketData['history'][] = ['action' => 'Manager Notified (High Priority)', 'timestamp' => gmdate('c'), 'user' => 'System Automation'];
                }
                
                try {
                    $result = $api->createDocument('tickets', $ticketData);
                    setFlash('success', 'Ticket ' . $result['number'] . ' created and linked to task "' . ($linkedTask['name'] ?? '') . '"!');
                    redirect('/task_view?id=' . urlencode($taskId));
                } catch (Exception $e) {
                    setFlash('error', 'Failed to create ticket: ' . $e->getMessage());
                }
            }
            
            // Handle ticket update
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
                $ticketId = $_POST['ticket_id'] ?? '';
                if ($ticketId) {
                    $updateData = [
                        'status' => $_POST['status'] ?? '',
                        'assignedTo' => $_POST['assignedTo'] ?? '',
                        'updatedAt' => gmdate('c'),
                    ];
                    
                    // Add history entry
                    $ticket = $api->getDocument('tickets', $ticketId);
                    $history = $ticket['history'] ?? [];
                    $user = getCurrentUser();
                    if ($_POST['status'] ?? '' !== ($ticket['status'] ?? '')) {
                        $history[] = ['action' => 'Status updated to ' . $_POST['status'], 'timestamp' => gmdate('c'), 'user' => $user['name'] ?? 'System'];
                    }
                    if ($_POST['assignedTo'] ?? '' !== ($ticket['assignedTo'] ?? '')) {
                        $assignedUser = $api->getDocument('users', $_POST['assignedTo']);
                        $history[] = ['action' => 'Assigned to ' . ($assignedUser['name'] ?? 'None'), 'timestamp' => gmdate('c'), 'user' => $user['name'] ?? 'System'];
                    }
                    $updateData['history'] = $history;
                    
                    try {
                        $api->updateDocument('tickets', $ticketId, $updateData);
                        setFlash('success', 'Ticket updated successfully!');
                        redirect('/tickets');
                    } catch (Exception $e) {
                        setFlash('error', 'Failed to update ticket');
                    }
                }
            }
            
            renderLayout('Tickets', function($data) use ($tickets, $agents, $action, $filter, $tasks) {
                include __DIR__ . '/pages/tickets.php';
            }, ['tickets' => $tickets, 'agents' => $agents]);
            break;

        case 'ticket':
            $ticketId = $_GET['id'] ?? '';
            if (!$ticketId) {
                redirect('/tickets');
            }
            
            try {
                $ticket = $api->getDocument('tickets', $ticketId);
                $users = $api->listDocuments('users');
                $agents = array_filter($users, fn($u) => in_array($u['role'] ?? '', ['agent', 'admin']));
                
                if (!$ticket) {
                    setFlash('error', 'Ticket not found');
                    redirect('/tickets');
                }
                
                // Handle comment/history addition
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
                    $history = $ticket['history'] ?? [];
                    $user = getCurrentUser();
                    $history[] = [
                        'action' => 'Comment: ' . $_POST['comment'],
                        'timestamp' => gmdate('c'),
                        'user' => $user['name'] ?? 'System'
                    ];
                    
                    $api->updateDocument('tickets', $ticketId, [
                        'history' => $history,
                        'updatedAt' => gmdate('c')
                    ]);
                    
                    setFlash('success', 'Comment added');
                    header('Location: ' . $_SERVER['REQUEST_URI']);
                    exit;
                }
                
                renderLayout('Ticket ' . ($ticket['number'] ?? ''), function($data) use ($ticket, $agents) {
                    include __DIR__ . '/pages/ticket_detail.php';
                }, ['ticket' => $ticket, 'agents' => $agents]);
                
            } catch (Exception $e) {
                setFlash('error', 'Error loading ticket');
                redirect('/tickets');
            }
            break;

        case 'users':
            if (!isAdmin()) {
                setFlash('error', 'Access denied');
                redirect('/dashboard');
            }
            
            $users = [];
            try {
                $users = $api->listDocuments('users');
            } catch (Exception $e) {
                $error = 'Failed to load users';
            }
            
            renderLayout('User Management', function($data) use ($users) {
                include __DIR__ . '/pages/users.php';
            }, ['users' => $users]);
            break;

        case 'sla':
            if (!isAdmin()) {
                setFlash('error', 'Access denied');
                redirect('/dashboard');
            }
            
            $policies = [];
            try {
                $policies = $api->listDocuments('sla_policies');
            } catch (Exception $e) {
                $error = 'Failed to load SLA policies';
            }
            
            renderLayout('SLA Management', function($data) use ($policies) {
                include __DIR__ . '/pages/sla.php';
            }, ['policies' => $policies]);
            break;

        case 'settings':
            if (!isAdmin()) {
                setFlash('error', 'Access denied');
                redirect('/dashboard');
            }
            
            renderLayout('Settings', function($data) {
                include __DIR__ . '/pages/settings.php';
            });
            break;

        case 'reports':
            $tickets = [];
            try {
                $tickets = $api->listDocuments('tickets');
            } catch (Exception $e) {
                $error = 'Failed to load data';
            }
            
            renderLayout('Reports', function($data) use ($tickets) {
                include __DIR__ . '/pages/reports.php';
            }, ['tickets' => $tickets]);
            break;

        case 'catalog':
            renderLayout('Service Catalog', function($data) {
                include __DIR__ . '/pages/catalog.php';
            });
            break;

        case 'kb':
            renderLayout('Knowledge Base', function($data) {
                include __DIR__ . '/pages/knowledge_base.php';
            });
            break;

        case 'conversations':
            renderLayout('Conversations', function($data) {
                include __DIR__ . '/pages/conversations.php';
            });
            break;

        case 'history':
            renderLayout('System Activity', function($data) {
                include __DIR__ . '/pages/history.php';
            });
            break;

        case 'problem':
            renderLayout('Problem Management', function($data) {
                include __DIR__ . '/pages/problem.php';
            });
            break;

        case 'change':
            renderLayout('Change Management', function($data) {
                include __DIR__ . '/pages/change.php';
            });
            break;

        case 'cmdb':
            renderLayout('CMDB Assets', function($data) {
                include __DIR__ . '/pages/cmdb.php';
            });
            break;

        case 'approvals':
            renderLayout('My Approvals', function($data) {
                include __DIR__ . '/pages/approvals.php';
            });
            break;

        // Task Management
        case 'tasks':
            $allTasks = [];
            $allTickets = [];
            try {
                $allTasks   = $api->listDocuments('tasks');
                $allTickets = $api->listDocuments('tickets');
            } catch (Exception $e) {}
            // Attach ticket counts to each task
            foreach ($allTasks as &$t) {
                $t['ticketCount'] = count(array_filter($allTickets, fn($tk) => ($tk['taskId'] ?? '') === $t['id']));
            }
            unset($t);
            renderLayout('Tasks', function($data) use ($allTasks) {
                include __DIR__ . '/pages/tasks.php';
            });
            break;

        case 'task_view':
            $taskId = $_GET['id'] ?? '';
            if (!$taskId) { redirect('/tasks'); }
            $taskDetail  = null;
            $taskTickets = [];
            $allUsers    = [];
            try {
                $taskDetail  = $api->getDocument('tasks', $taskId);
                $allTickets  = $api->listDocuments('tickets');
                $allUsers    = $api->listDocuments('users');
                $taskTickets = array_values(array_filter($allTickets, fn($t) => ($t['taskId'] ?? '') === $taskId));
                // Sort newest first
                usort($taskTickets, function($a, $b) {
                    $at = is_array($a['createdAt'] ?? '') ? ($a['createdAt']['seconds'] ?? 0) : strtotime($a['createdAt'] ?? '');
                    $bt = is_array($b['createdAt'] ?? '') ? ($b['createdAt']['seconds'] ?? 0) : strtotime($b['createdAt'] ?? '');
                    return $bt <=> $at;
                });
            } catch (Exception $e) {}
            if (!$taskDetail) { setFlash('error', 'Task not found'); redirect('/tasks'); }
            renderLayout('Task: ' . ($taskDetail['name'] ?? ''), function($data) use ($taskDetail, $taskTickets, $allUsers) {
                include __DIR__ . '/pages/task_view.php';
            });
            break;

        // Timesheet Management Module
        case 'timesheet':
            renderLayout('Timesheet', function($data) {
                include __DIR__ . '/pages/timesheet.php';
            });
            break;

        case 'timesheet_approvals':
            renderLayout('Timesheet Approvals', function($data) {
                include __DIR__ . '/pages/timesheet_approvals.php';
            });
            break;

        case 'timesheet_reports':
            renderLayout('Timesheet Reports', function($data) {
                include __DIR__ . '/pages/timesheet_reports.php';
            });
            break;

        case 'timesheet_ajax':
            // AJAX handler - returns JSON, no layout
            include __DIR__ . '/pages/timesheet_ajax.php';
            break;

        case 'my_tickets':
            $currentUser = getCurrentUser();
            $myTickets   = [];
            $allUsers    = [];
            try {
                $allTickets = $api->listDocuments('tickets');
                $allUsers   = $api->listDocuments('users');
                $myUid = $currentUser['uid'] ?? '';
                $myTickets = array_values(array_filter($allTickets, function($t) use ($myUid) {
                    return ($t['assignedTo'] ?? '') === $myUid || ($t['createdBy'] ?? '') === $myUid;
                }));
            } catch (Exception $e) { $myTickets = []; }
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
                $ticketId  = $_POST['ticket_id'] ?? '';
                $newStatus = $_POST['new_status'] ?? '';
                if ($ticketId && in_array($newStatus, ['New','In Progress','On Hold','Resolved','Closed'])) {
                    $cu = getCurrentUser();
                    $t  = $api->getDocument('tickets', $ticketId);
                    if ($t && (($t['assignedTo'] ?? '') === ($cu['uid'] ?? '') || isAdmin())) {
                        $history   = $t['history'] ?? [];
                        $history[] = ['action' => 'Status changed to ' . $newStatus, 'timestamp' => gmdate('c'), 'user' => $cu['name'] ?? 'System'];
                        $api->updateDocument('tickets', $ticketId, ['status' => $newStatus, 'history' => $history, 'updatedAt' => gmdate('c')]);
                        setFlash('success', 'Ticket status updated.');
                    }
                }
                redirect('/my_tickets');
            }
            renderLayout('My Assigned Tickets', function($data) use ($myTickets, $allUsers, $currentUser) {
                include __DIR__ . '/pages/my_tickets.php';
            });
            break;

        case 'approved_tickets':
            $allTickets = [];
            $allUsers   = [];
            try {
                $allTickets = $api->listDocuments('tickets');
                $allUsers   = $api->listDocuments('users');
            } catch (Exception $e) {}
            $approvedTickets = array_values(array_filter($allTickets, function($t) {
                return in_array($t['status'] ?? '', ['Resolved', 'Closed']);
            }));
            usort($approvedTickets, function($a, $b) {
                $at = is_array($a['updatedAt'] ?? '') ? ($a['updatedAt']['seconds'] ?? 0) : strtotime($a['updatedAt'] ?? '');
                $bt = is_array($b['updatedAt'] ?? '') ? ($b['updatedAt']['seconds'] ?? 0) : strtotime($b['updatedAt'] ?? '');
                return $bt <=> $at;
            });
            renderLayout('Approved Tickets', function($data) use ($approvedTickets, $allUsers) {
                include __DIR__ . '/pages/approved_tickets.php';
            });
            break;

        case 'access_control':
            if (!isAdmin()) { setFlash('error', 'Access denied'); redirect('/dashboard'); }
            $allUsers = [];
            try { $allUsers = $api->listDocuments('users'); } catch (Exception $e) {}
            // Handle role/access updates
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $targetUid = $_POST['target_uid'] ?? '';
                $action2   = $_POST['access_action'] ?? '';
                if ($targetUid) {
                    $targetUser = $api->getDocument('users', $targetUid);
                    $targetRole = $targetUser['role'] ?? 'user';
                    $levels = ROLE_LEVELS;
                    if (($levels[$targetRole] ?? 1) < getRoleLevel()) {
                        if ($action2 === 'grant')   $api->updateDocument('users', $targetUid, ['disabled' => false]);
                        if ($action2 === 'remove')  $api->updateDocument('users', $targetUid, ['disabled' => true]);
                        if ($action2 === 'role' && isset($_POST['new_role'])) {
                            $newRole = $_POST['new_role'];
                            if (($levels[$newRole] ?? 1) < getRoleLevel())
                                $api->updateDocument('users', $targetUid, ['role' => $newRole]);
                        }
                        if ($action2 === 'module_toggle' && isset($_POST['module_key'])) {
                            $modKey   = $_POST['module_key'];
                            $allow    = ($_POST['module_allow'] ?? '1') === '1';
                            $existing = $targetUser['restrictedModules'] ?? [];
                            if ($allow) {
                                $existing = array_values(array_filter($existing, fn($m) => $m !== $modKey));
                            } else {
                                if (!in_array($modKey, $existing)) $existing[] = $modKey;
                            }
                            $api->updateDocument('users', $targetUid, ['restrictedModules' => $existing]);
                        }
                        if ($action2 === 'restrict_all') {
                            $allMods = ['tickets','conversations','catalog','kb','approvals','history','timesheet','timesheet_reports','timesheet_approvals','problem','change','cmdb','reports','sla','users','settings','access_control','approved_tickets'];
                            $api->updateDocument('users', $targetUid, ['restrictedModules' => $allMods]);
                        }
                        if ($action2 === 'allow_all') {
                            $api->updateDocument('users', $targetUid, ['restrictedModules' => []]);
                        }
                        if ($action2 === 'create_user') {
                            $name  = $_POST['new_name']  ?? '';
                            $email = $_POST['new_email'] ?? '';
                            $role  = $_POST['new_role']  ?? 'user';
                            $pass  = $_POST['new_password'] ?? '';
                            if ($name && $email && $pass) {
                                $uid = 'user_' . uniqid();
                                $api->createDocument('users', [
                                    'uid'           => $uid,
                                    'name'          => $name,
                                    'email'         => $email,
                                    'role'          => $role,
                                    'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
                                    'disabled'      => false,
                                    'createdBy'     => getCurrentUser()['uid'] ?? '',
                                    'createdAt'     => gmdate('c'),
                                ], $uid);
                                setFlash('success', "User $name created successfully.");
                            }
                        }
                    }
                }
                redirect('/access_control');
            }
            renderLayout('Access Control', function($data) use ($allUsers) {
                include __DIR__ . '/pages/access_control.php';
            });
            break;
            break;

        default:
            http_response_code(404);
            renderLayout('Page Not Found', function($data) {
                echo '<div class="text-center py-12"><h1 class="text-2xl font-bold mb-4">Page Not Found</h1><p>The requested page does not exist.</p><a href="' . BASE_URL . '/?page=dashboard" class="btn btn-primary mt-4">Go to Dashboard</a></div>';
            });
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo '<div class="p-4 bg-red-50 text-red-700">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Helper function to calculate priority
function calculatePriority(string $impact, string $urgency): string {
    $i = (int) ($impact[0] ?? 2);
    $u = (int) ($urgency[0] ?? 2);
    $sum = $i + $u;
    
    return match(true) {
        $sum <= 2 => '1 - Critical',
        $sum === 3 => '2 - High',
        $sum === 4 => '3 - Moderate',
        default => '4 - Low',
    };
}
