<?php
/**
 * ApplicationController — Tim Backend
 * Menangani semua request HTTP terkait lamaran pekerjaan.
 */


class ApplicationController
{
    /**
     * POST: Alumni mengajukan lamaran
     */
    public static function apply(): void
    {
        requireAlumni();
        validateCsrf();

        $vacancyId   = (int)($_POST['vacancy_id'] ?? 0);
        $coverLetter = sanitize($_POST['cover_letter'] ?? '');
        $userId      = currentUserId();

        // Pastikan profil alumni ada
        $profile = AlumniModel::findByUserId($userId);
        if (!$profile) {
            setFlash('warning', 'Lengkapi profil alumni terlebih dahulu.');
            redirect('/alumni/profile.php');
        }

        // Cek lowongan tersedia
        $vacancy = VacancyModel::findById($vacancyId);
        if (!$vacancy || $vacancy['status'] !== 'approved') {
            setFlash('danger', 'Lowongan tidak tersedia.');
            redirect('/vacancy/list.php');
        }

        // Cek deadline
        if ($vacancy['deadline'] && strtotime($vacancy['deadline']) < strtotime('today')) {
            setFlash('danger', 'Deadline lamaran sudah lewat.');
            redirect('/vacancy/detail.php?id=' . $vacancyId);
        }

        // Cek status kerja
        if ($profile['work_status'] === 'employed') {
            setFlash('danger', 'Anda sudah berstatus Bekerja dan tidak dapat melamar.');
            redirect('/vacancy/detail.php?id=' . $vacancyId);
        }

        // Cek apakah sudah melamar ke lowongan ini sebelumnya
        $pdo  = getDB();
        $chk  = $pdo->prepare("SELECT id, status FROM applications WHERE vacancy_id=? AND alumni_id=? LIMIT 1");
        $chk->execute([$vacancyId, $profile['id']]);
        $existing = $chk->fetch();
        if ($existing) {
            setFlash('info', 'Anda sudah pernah melamar pada lowongan ini. Status: ' . statusBadge($existing['status']));
            redirect('/alumni/applications.php');
        }

        // Buat lamaran
        $appId = ApplicationModel::create($vacancyId, $profile['id'], $coverLetter);
        ApplicationModel::_logStatusPublic($appId, null, 'pending', $userId, 'Lamaran dikirim oleh alumni.');

        // Notifikasi ke perusahaan
        $companyUser = $pdo->prepare("SELECT user_id FROM companies WHERE id=? LIMIT 1");
        $companyUser->execute([$vacancy['company_id']]);
        $cu = $companyUser->fetch();
        if ($cu) {
            sendNotification($cu['user_id'], 'Lamaran Baru',
                "Ada lamaran baru untuk posisi \"{$vacancy['title']}\"",
                'info', '/company/applications.php');
        }

        logActivity($userId, 'apply', 'application', "Applied vacancy_id={$vacancyId} app_id={$appId}");
        setFlash('success', 'Lamaran berhasil dikirim! Pantau status di halaman Lamaran Saya.');
        redirect('/alumni/applications.php');
    }

    /**
     * POST: Company/Admin mengubah status lamaran
     */
    public static function updateStatus(): void
    {
        requireLogin();
        validateCsrf();

        $appId     = (int)($_POST['application_id'] ?? 0);
        $newStatus = sanitize($_POST['status'] ?? '');
        $notes     = sanitize($_POST['notes'] ?? '');
        $userId    = currentUserId();

        $valid = ['pending','on_hold','reviewed','shortlisted','rejected','accepted'];
        if (!in_array($newStatus, $valid, true)) {
            setFlash('danger', 'Status tidak valid.');
            self::_goBack();
        }

        $app = ApplicationModel::findById($appId);
        if (!$app) {
            setFlash('danger', 'Lamaran tidak ditemukan.');
            self::_goBack();
        }

        // Otorisasi: admin boleh semua, company hanya lowongannya sendiri
        if (!isAdmin()) {
            $pdo  = getDB();
            $stmt = $pdo->prepare(
                "SELECT c.user_id FROM job_vacancies jv JOIN companies c ON c.id=jv.company_id WHERE jv.id=? LIMIT 1"
            );
            $stmt->execute([$app['vacancy_id']]);
            $row = $stmt->fetch();
            if (!$row || (int)$row['user_id'] !== $userId) {
                setFlash('danger', 'Tidak memiliki izin untuk mengubah lamaran ini.');
                self::_goBack();
            }
        }

        ApplicationModel::updateStatus($appId, $newStatus, $userId, $notes);

        // Notifikasi alumni
        $alumniUserId = ApplicationModel::getAlumniUserId($appId);
        if ($alumniUserId) {
            $labels = [
                'reviewed'    => 'Sedang Ditinjau',
                'shortlisted' => 'Lolos Seleksi Awal',
                'rejected'    => 'Tidak Diterima',
                'accepted'    => 'Diterima!',
                'on_hold'     => 'Ditunda Sementara',
            ];
            $label = $labels[$newStatus] ?? ucfirst($newStatus);
            sendNotification(
                $alumniUserId,
                'Update Status Lamaran',
                "Lamaran Anda untuk \"{$app['vacancy_title']}\" — {$label}",
                $newStatus === 'accepted' ? 'success' : ($newStatus === 'rejected' ? 'error' : 'info'),
                '/alumni/applications.php'
            );
        }

        logActivity($userId, 'update_status', 'application', "app_id={$appId} → {$newStatus}");
        setFlash('success', 'Status lamaran berhasil diperbarui.');
        self::_goBack();
    }

    private static function _goBack(): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? (APP_URL . '/company/applications.php');
        header('Location: ' . $ref);
        exit;
    }
}

// Make log method accessible (bridge for old code)
// Tambahkan method public wrapper di ApplicationModel
