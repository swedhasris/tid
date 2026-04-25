<?php
/**
 * Auth Helper for Timesheet Module
 * Integrates with existing Firebase Auth / Firestore user system
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../firestore-client.php';

class TimesheetAuth {
    public static function getCurrentUser(): ?array {
        // Priority 1: X-User-Id header (from React frontend)
        $userId = $_SERVER['HTTP_X_USER_ID'] ?? '';
        // Priority 2: Query parameter
        if (!$userId) {
            $userId = $_GET['user_id'] ?? '';
        }
        // Priority 3: POST body
        if (!$userId && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = $input['user_id'] ?? '';
        }

        if (!$userId) {
            return null;
        }

        try {
            $client = new FirestoreClient();
            $userDoc = $client->getDocument('users', $userId);
            if ($userDoc) {
                return [
                    'uid' => $userId,
                    'name' => $userDoc['name'] ?? 'User',
                    'email' => $userDoc['email'] ?? '',
                    'role' => $userDoc['role'] ?? 'user',
                ];
            }
        } catch (Exception $e) {
            error_log("TimesheetAuth error: " . $e->getMessage());
        }

        // Fallback: allow basic auth when Firestore is unavailable
        return [
            'uid' => $userId,
            'name' => 'User',
            'email' => '',
            'role' => 'user',
        ];
    }

    public static function requireAuth(): array {
        $user = self::getCurrentUser();
        if (!$user) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized. Please provide user_id.']);
            exit;
        }
        return $user;
    }

    public static function requireAdmin(): array {
        $user = self::requireAuth();
        $adminRoles = ['admin', 'super_admin'];
        if (!in_array($user['role'], $adminRoles, true)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden. Admin access required.']);
            exit;
        }
        return $user;
    }

    public static function isAdmin(): bool {
        $user = self::getCurrentUser();
        if (!$user) return false;
        return in_array($user['role'], ['admin', 'super_admin'], true);
    }
}
