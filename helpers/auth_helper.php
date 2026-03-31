<?php
// helpers/auth_helper.php

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role'     => $_SESSION['role'],
        'email'    => $_SESSION['email'],
    ];
}

function currentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentRole(): string {
    return $_SESSION['role'] ?? '';
}

function isAdmin(): bool   { return currentRole() === ROLE_ADMIN; }
function isAlumni(): bool  { return currentRole() === ROLE_ALUMNI; }
function isCompany(): bool { return currentRole() === ROLE_COMPANY; }

function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Silakan login terlebih dahulu.');
        redirect('/auth/login.php');
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array(currentRole(), $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../views/errors/403.php';
        exit;
    }
}

function requireAdmin(): void   { requireRole(ROLE_ADMIN); }
function requireAlumni(): void  { requireRole(ROLE_ALUMNI); }
function requireCompany(): void { requireRole(ROLE_COMPANY); }

function loginUser(array $user): void {
    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];
    $_SESSION['email']    = $user['email'];
    $_SESSION['last_activity'] = time();
}

function logoutUser(): void {
    $userId = currentUserId();
    session_unset();
    session_destroy();
    // Log logout via new session
    session_name(SESSION_NAME);
    session_start();
    logActivity($userId, 'logout', 'auth', 'User logged out');
}

function getUserById(int $id): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getUserByUsername(string $username): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$username]);
    return $stmt->fetch() ?: null;
}

function getUserByEmail(string $email): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$email]);
    return $stmt->fetch() ?: null;
}

function updateLastLogin(int $userId): void {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
}
