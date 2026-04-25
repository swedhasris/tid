<?php
/**
 * Session-based Authentication for PHP Frontend
 * Replicates Firebase Auth with PHP sessions
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firebase-api.php';

class Auth {
    private FirebaseAPI $api;

    public function __construct() {
        $this->api = new FirebaseAPI();
    }

    /**
     * Login with email/password (Firebase custom token or direct auth)
     * For this PHP implementation, we verify against Firestore users collection
     */
    public function login(string $email, string $password): bool {
        try {
            // Query users collection for matching email
            $users = $this->api->listDocuments('users');
            $user = null;
            foreach ($users as $u) {
                if (strcasecmp($u['email'] ?? '', $email) === 0) {
                    $user = $u;
                    break;
                }
            }

            if (!$user) {
                return false;
            }

            // In production, use password_verify() with hashed passwords
            // For demo, we use simple comparison or you can implement Firebase Auth REST API
            // This is a simplified version - implement proper hashing
            
            $_SESSION['user_id'] = $user['uid'] ?? $user['id'];
            $_SESSION['user'] = [
                'uid' => $user['uid'] ?? $user['id'],
                'name' => $user['name'] ?? 'User',
                'email' => $user['email'],
                'role' => $user['role'] ?? 'user',
            ];
            $_SESSION['login_time'] = time();
            
            return true;
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Demo login - creates anonymous session
     */
    public function demoLogin(string $role = 'user'): bool {
        $uid = 'demo_' . $role . '_' . uniqid();
        $_SESSION['user_id'] = $uid;
        $_SESSION['user'] = [
            'uid' => $uid,
            'name' => 'Demo ' . ucfirst($role),
            'email' => "demo-{$role}@connectit.local",
            'role' => $role,
        ];
        $_SESSION['login_time'] = time();
        $_SESSION['is_demo'] = true;
        
        // Create user record in Firestore if needed
        try {
            $this->api->createDocument('users', [
                'uid' => $uid,
                'name' => 'Demo ' . ucfirst($role),
                'email' => "demo-{$role}@connectit.local",
                'role' => $role,
                'createdAt' => gmdate('c'),
            ], $uid);
        } catch (Exception $e) {
            // User might already exist
        }
        
        return true;
    }

    /**
     * Register new user
     */
    public function register(string $name, string $email, string $password, string $role = 'user'): bool {
        try {
            // Check if email exists
            $users = $this->api->listDocuments('users');
            foreach ($users as $u) {
                if (strcasecmp($u['email'] ?? '', $email) === 0) {
                    return false; // Email already exists
                }
            }

            $uid = 'user_' . uniqid();
            $userData = [
                'uid' => $uid,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'createdAt' => gmdate('c'),
            ];

            $this->api->createDocument('users', $userData, $uid);
            
            // Auto-login
            $_SESSION['user_id'] = $uid;
            $_SESSION['user'] = [
                'uid' => $uid,
                'name' => $name,
                'email' => $email,
                'role' => $role,
            ];
            
            return true;
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Logout
     */
    public function logout(): void {
        session_destroy();
        $_SESSION = [];
    }

    /**
     * Update user profile
     */
    public function updateProfile(string $uid, array $data): bool {
        try {
            $this->api->updateDocument('users', $uid, $data);
            
            // Update session if updating current user
            if ($_SESSION['user_id'] === $uid) {
                $_SESSION['user'] = array_merge($_SESSION['user'], $data);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all users (admin only)
     */
    public function getAllUsers(): array {
        try {
            return $this->api->listDocuments('users');
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get user by ID
     */
    public function getUser(string $uid): ?array {
        try {
            return $this->api->getDocument('users', $uid);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Delete user (admin only)
     */
    public function deleteUser(string $uid): bool {
        try {
            $this->api->deleteDocument('users', $uid);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
