<?php
/**
 * PHP Frontend Configuration
 * Converts React/Firebase app to PHP with session-based auth
 */

session_start();

// Firebase project configuration
define('FIREBASE_PROJECT_ID', 'studio-1364433017-7dd51');
define('FIREBASE_DATABASE_ID', 'ai-studio-81cf4ca1-6eb2-4c0c-b500-47f18cc09c6c');
define('FIREBASE_API_KEY', 'AIzaSyBeQ9SZluIwcL7UjTLMORYOjG5AS5ubDH8');
define('FIREBASE_AUTH_DOMAIN', 'studio-1364433017-7dd51.firebaseapp.com');

// Service account for Firestore REST API
$serviceAccountPath = __DIR__ . '/../../service-account.json';
$serviceAccount = file_exists($serviceAccountPath) ? json_decode(file_get_contents($serviceAccountPath), true) : null;

// Application URLs
define('BASE_URL', '/php-frontend');
define('API_BASE', 'https://firestore.googleapis.com/v1/projects/' . FIREBASE_PROJECT_ID . '/databases/' . FIREBASE_DATABASE_ID);

// Helper: Check if user is logged in
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Helper: Get current user
function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

// Helper: Get user role
function getUserRole(): string {
    return $_SESSION['user']['role'] ?? 'user';
}

// Helper: Check if user is admin
function isAdmin(): bool {
    $role = getUserRole();
    return in_array($role, ['admin', 'super_admin']);
}

// Helper: Check if user is agent
function isAgent(): bool {
    $role = getUserRole();
    return in_array($role, ['agent', 'admin', 'super_admin']);
}

// Helper: Redirect
function redirect(string $path): void {
    header('Location: ' . BASE_URL . $path);
    exit;
}

// Helper: Require login
function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect('/login');
    }
}

// CSRF Protection
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Flash messages
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
