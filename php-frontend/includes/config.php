<?php
/**
 * PHP Frontend Configuration
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

// ── Role Hierarchy ──────────────────────────────────────────
// user(1) < agent(2) < sub_admin(3) < admin(4) < super_admin(5) < ultra_super_admin(6)
define('ROLE_LEVELS', [
    'user'              => 1,
    'agent'             => 2,
    'sub_admin'         => 3,
    'admin'             => 4,
    'super_admin'       => 5,
    'ultra_super_admin' => 6,
]);

define('ROLE_LABELS', [
    'user'              => 'User',
    'agent'             => 'Support Agent',
    'sub_admin'         => 'Sub Admin',
    'admin'             => 'Administrator',
    'super_admin'       => 'Super Admin',
    'ultra_super_admin' => 'Ultra Super Admin',
]);

// ── Auth helpers ────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function getUserRole(): string {
    return $_SESSION['user']['role'] ?? 'user';
}

function getRoleLevel(): int {
    $levels = ROLE_LEVELS;
    return $levels[getUserRole()] ?? 1;
}

function hasRoleLevel(int $minLevel): bool {
    return getRoleLevel() >= $minLevel;
}

// Convenience helpers
function isAdmin(): bool           { return getRoleLevel() >= 4; }
function isSuperAdmin(): bool      { return getRoleLevel() >= 5; }
function isUltraSuperAdmin(): bool { return getUserRole() === 'ultra_super_admin'; }
function isAgent(): bool           { return getRoleLevel() >= 2; }
function isSubAdmin(): bool        { return getRoleLevel() >= 3; }

// Timesheet approvals: admin, super_admin, ultra_super_admin
function canApproveTimesheets(): bool { return getRoleLevel() >= 4; }

function canManageRole(string $targetRole): bool {
    $levels = ROLE_LEVELS;
    $myLevel = getRoleLevel();
    $targetLevel = $levels[$targetRole] ?? 1;
    return $myLevel > $targetLevel;
}

// ── Utilities ───────────────────────────────────────────────
function redirect(string $path): void {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect('/login');
    }
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
