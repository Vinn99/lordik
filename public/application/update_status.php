<?php
// public/application/update_status.php — Tim: Backend
require_once __DIR__ . '/../../core/bootstrap.php';
requireLogin(); requireRole(ROLE_COMPANY, ROLE_ADMIN);
validateCsrf();
$result = ApplicationModel::updateStatus(
    (int)($_POST['application_id'] ?? 0),
    sanitize($_POST['status'] ?? ''),
    currentUserId(),
    sanitize($_POST['notes'] ?? '')
);
if ($result['success']) setFlash('success','Status lamaran berhasil diperbarui.');
else setFlash('danger', $result['message']);
redirect($_SERVER['HTTP_REFERER'] ?? '/company/applications.php');
