<?php
// helpers/notification_helper.php

function sendNotification(int $userId, string $title, string $body, string $type = 'info', string $link = ''): void {
    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO notifications (user_id, title, body, type, link) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $title, $body, $type, $link]);
    } catch (PDOException $e) {
        error_log("Notification failed: " . $e->getMessage());
    }
}

function countUnreadNotifications(int $userId): int {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getNotifications(int $userId, int $limit = 20): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?"
    );
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

function markNotificationRead(int $notifId, int $userId): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notifId, $userId]);
}

function markAllNotificationsRead(int $userId): void {
    $pdo  = getDB();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$userId]);
}
