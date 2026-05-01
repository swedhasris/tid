<?php
/**
 * Authentication for PHP Frontend
 * Uses Firebase Auth REST API for real login + Firestore for profiles
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firebase-api.php';

class Auth {
    private FirebaseAPI $api;

    public function __construct() {
        $this->api = new FirebaseAPI();
    }

    /**
     * Login via Firebase Auth REST API (email + password)
     * Works for users registered in BOTH React and PHP frontends
     */
    public function login(string $email, string $password): bool {
        try {
            // Use Firebase Auth REST API to verify credentials
            $url = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=' . FIREBASE_API_KEY;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'email'             => $email,
                'password'          => $password,
                'returnSecureToken' => true,
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);

            if ($httpCode !== 200 || empty($data['localId'])) {
                // Firebase Auth failed — try Firestore password_hash fallback
                return $this->loginWithHash($email, $password);
            }

            $uid = $data['localId'];

            // Fetch profile from Firestore
            $profile = $this->api->getDocument('users', $uid);
            if (!$profile) {
                // Create profile if missing (first login via React)
                $profile = [
                    'uid'   => $uid,
                    'name'  => $data['displayName'] ?? explode('@', $email)[0],
                    'email' => $email,
                    'role'  => 'user',
                ];
                $this->api->createDocument('users', $profile, $uid);
            }

            $this->setSession($uid, $profile);
            return true;

        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return $this->loginWithHash($email, $password);
        }
    }

    /**
     * Fallback: verify against password_hash stored in Firestore (PHP-registered users)
     */
    private function loginWithHash(string $email, string $password): bool {
        try {
            $users = $this->api->listDocuments('users');
            foreach ($users as $u) {
                if (strcasecmp($u['email'] ?? '', $email) !== 0) continue;
                $hash = $u['password_hash'] ?? '';
                if ($hash && password_verify($password, $hash)) {
                    $uid = $u['uid'] ?? $u['id'];
                    $this->setSession($uid, $u);
                    return true;
                }
            }
        } catch (Exception $e) {
            error_log("Hash login error: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Demo login — all 6 roles, writes to Firestore so React sees it too
     */
    public function demoLogin(string $role = 'user'): bool {
        $validRoles = array_keys(ROLE_LEVELS);
        if (!in_array($role, $validRoles)) $role = 'user';

        $labels = ROLE_LABELS;
        $uid    = 'demo_' . $role . '_' . uniqid();
        $name   = 'Demo ' . ($labels[$role] ?? ucfirst($role));
        $email  = "demo-{$role}@connectit.local";

        $profile = [
            'uid'    => $uid,
            'name'   => $name,
            'email'  => $email,
            'role'   => $role,
            'isDemo' => true,
        ];

        // Write to Firestore so React app sees this demo user
        try {
            $this->api->createDocument('users', array_merge($profile, [
                'createdAt' => gmdate('c'),
            ]), $uid);
        } catch (Exception $e) {
            // Ignore — session still works
        }

        $this->setSession($uid, $profile);
        $_SESSION['is_demo'] = true;
        return true;
    }

    /**
     * Register new user via Firebase Auth REST API + Firestore profile
     */
    public function register(string $name, string $email, string $password, string $role = 'user'): bool {
        try {
            // Create Firebase Auth account
            $url = 'https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=' . FIREBASE_API_KEY;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'email'             => $email,
                'password'          => $password,
                'displayName'       => $name,
                'returnSecureToken' => true,
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $data = json_decode($response, true);

            if ($httpCode === 200 && !empty($data['localId'])) {
                $uid = $data['localId'];
            } else {
                // Firebase Auth failed — fall back to Firestore-only registration
                $uid = 'user_' . uniqid();
            }

            // Check email uniqueness in Firestore
            $existing = $this->api->listDocuments('users');
            foreach ($existing as $u) {
                if (strcasecmp($u['email'] ?? '', $email) === 0) {
                    return false;
                }
            }

            $profile = [
                'uid'           => $uid,
                'name'          => $name,
                'email'         => $email,
                'role'          => $role,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'createdAt'     => gmdate('c'),
            ];

            $this->api->createDocument('users', $profile, $uid);
            $this->setSession($uid, $profile);
            return true;

        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set PHP session from profile data
     */
    private function setSession(string $uid, array $profile): void {
        $_SESSION['user_id']    = $uid;
        $_SESSION['user']       = [
            'uid'   => $uid,
            'name'  => $profile['name']  ?? 'User',
            'email' => $profile['email'] ?? '',
            'role'  => $profile['role']  ?? 'user',
        ];
        $_SESSION['login_time'] = time();
    }

    /**
     * Logout
     */
    public function logout(): void {
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Update user profile in Firestore + session
     */
    public function updateProfile(string $uid, array $data): bool {
        try {
            $this->api->updateDocument('users', $uid, $data);
            if (($_SESSION['user_id'] ?? '') === $uid) {
                $_SESSION['user'] = array_merge($_SESSION['user'], $data);
            }
            return true;
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            return false;
        }
    }

    public function getAllUsers(): array {
        try { return $this->api->listDocuments('users'); } catch (Exception $e) { return []; }
    }

    public function getUser(string $uid): ?array {
        try { return $this->api->getDocument('users', $uid); } catch (Exception $e) { return null; }
    }

    public function deleteUser(string $uid): bool {
        try { $this->api->deleteDocument('users', $uid); return true; } catch (Exception $e) { return false; }
    }
}
