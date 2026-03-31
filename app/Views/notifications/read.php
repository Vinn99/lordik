<?php
// public/notifications/read.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireLogin();

$id = (int)($_GET['id'] ?? 0);

$pdo  = getDB();
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$id, currentUserId()]);
$notif = $stmt->fetch();

if ($notif) {
    markNotificationRead($id, currentUserId());
    $link = $notif['link'] ?: '/' . currentRole() . '/dashboard.php';
    redirect($link);
}

redirect('/' . currentRole() . '/dashboard.php');
