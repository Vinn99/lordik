<?php
// helpers/activity_log.php

function logActivity(?int $userId, string $action, string $module, string $description = ''): void {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, action, module, description, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $userId,
            $action,
            $module,
            $description,
            getClientIp(),
            getUserAgent(),
        ]);
    } catch (PDOException $e) {
        error_log("Activity log failed: " . $e->getMessage());
    }
}

function logPasswordReset(int $userId, string $method, bool $success): void {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO password_reset_logs (user_id, reset_method, ip_address, success)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $method, getClientIp(), (int)$success]);
    } catch (PDOException $e) {
        error_log("Password reset log failed: " . $e->getMessage());
    }
}

function logApplicationStatusChange(int $applicationId, ?string $oldStatus, string $newStatus, ?int $changedBy, string $notes = ''): void {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO application_status_logs (application_id, old_status, new_status, changed_by, notes)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$applicationId, $oldStatus, $newStatus, $changedBy, $notes]);
    } catch (PDOException $e) {
        error_log("Application status log failed: " . $e->getMessage());
    }
}

function getActivityLogs(int $limit = 50, int $offset = 0, ?int $userId = null): array {
    $pdo = getDB();
    $sql = "SELECT al.*, u.username FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id";
    $params = [];
    if ($userId !== null) {
        $sql .= " WHERE al.user_id = ?";
        $params[] = $userId;
    }
    $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
