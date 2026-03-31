<?php
/**
 * app/Controllers/Admin/ApplicationController.php — Tim: Backend
 * Mengelola update status lamaran
 */

class Admin_ApplicationController extends BaseController {
    public function updateStatus(): void {
        requireLogin(); requireRole(ROLE_COMPANY, ROLE_ADMIN);
        $this->requirePost();
        $appId     = (int)($_POST['application_id'] ?? 0);
        $newStatus = $this->input('status');
        $notes     = $this->input('notes');
        $result    = ApplicationModel::updateStatus($appId, $newStatus, currentUserId(), $notes);
        if ($result['success']) setFlash('success','Status lamaran berhasil diperbarui.');
        else setFlash('danger', $result['message']);
        $referer = $_SERVER['HTTP_REFERER'] ?? (APP_URL.'/company/applications.php');
        redirect($referer);
    }
}
