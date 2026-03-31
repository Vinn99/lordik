<?php
// public/notifications/mark_all.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireLogin();
markAllNotificationsRead(currentUserId());
$referer = $_SERVER['HTTP_REFERER'] ?? (APP_URL . '/' . currentRole() . '/dashboard.php');
header('Location: ' . $referer);
exit;
