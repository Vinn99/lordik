<?php
// modules/application/ApplicationModule.php


class ApplicationModule {

    public static function apply(int $userId, int $vacancyId, string $coverLetter = ''): array {
        $pdo = getDB();

        // Get alumni profile
        $stmt = $pdo->prepare("SELECT id FROM alumni_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $alumni = $stmt->fetch();
        if (!$alumni) return ['success' => false, 'message' => 'Lengkapi profil alumni terlebih dahulu.'];

        // Check vacancy is approved
        $stmt2 = $pdo->prepare("SELECT * FROM job_vacancies WHERE id = ? AND status = 'approved' AND deleted_at IS NULL LIMIT 1");
        $stmt2->execute([$vacancyId]);
        $vacancy = $stmt2->fetch();
        if (!$vacancy) return ['success' => false, 'message' => 'Lowongan tidak tersedia.'];

        // Check deadline
        if ($vacancy['deadline'] && strtotime($vacancy['deadline']) < strtotime('today')) {
            return ['success' => false, 'message' => 'Lowongan sudah melewati deadline.'];
        }

        // #5 Cek status kerja — yang sudah bekerja tidak dapat melamar
        $stWork = $pdo->prepare("SELECT work_status FROM alumni_profiles WHERE id = ? LIMIT 1");
        $stWork->execute([$alumni['id']]);
        $workRow = $stWork->fetch();
        if ($workRow && $workRow['work_status'] === 'employed') {
            return ['success' => false, 'message' => 'Anda sudah berstatus Bekerja dan tidak dapat melamar lowongan baru. Update status kerja jika informasi ini tidak akurat.'];
        }

        // #5 Cek lamaran aktif — hanya boleh punya 1 lamaran aktif (pending/reviewed/shortlisted)
        $stActive = $pdo->prepare(
            "SELECT COUNT(*) FROM applications WHERE alumni_id = ? AND status IN ('pending','reviewed','shortlisted') LIMIT 1"
        );
        $stActive->execute([$alumni['id']]);
        if ((int)$stActive->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Anda masih memiliki lamaran aktif yang sedang diproses. Tunggu hasil lamaran sebelumnya atau batalkan terlebih dahulu.'];
        }

        // Prevent duplicate
        $stmt3 = $pdo->prepare("SELECT id FROM applications WHERE vacancy_id = ? AND alumni_id = ? LIMIT 1");
        $stmt3->execute([$vacancyId, $alumni['id']]);
        if ($stmt3->fetch()) {
            return ['success' => false, 'message' => 'Anda sudah melamar pada lowongan ini.'];
        }

        try {
            $stmt4 = $pdo->prepare(
                "INSERT INTO applications (vacancy_id, alumni_id, cover_letter, status)
                 VALUES (?, ?, ?, 'pending')"
            );
            $stmt4->execute([$vacancyId, $alumni['id'], $coverLetter]);
            $appId = $pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return ['success' => false, 'message' => 'Anda sudah melamar pada lowongan ini.'];
            }
            throw $e;
        }

        logApplicationStatusChange($appId, null, 'pending', $userId, 'Application submitted');
        logActivity($userId, 'apply_vacancy', 'application', "Applied to vacancy_id={$vacancyId}");

        // Notify company
        self::notifyCompany($vacancy['company_id'], "Lamaran Baru", 
            "Ada lamaran baru untuk posisi {$vacancy['title']}",
            '/company/application/detail.php?id=' . $appId
        );

        return ['success' => true, 'id' => $appId];
    }

    public static function updateStatus(int $appId, string $newStatus, int $byUserId, string $notes = ''): array {
        $validStatuses = ['pending', 'reviewed', 'shortlisted', 'rejected', 'accepted'];
        if (!in_array($newStatus, $validStatuses, true)) {
            return ['success' => false, 'message' => 'Status tidak valid.'];
        }

        $pdo = getDB();
        $app = self::getById($appId);
        if (!$app) return ['success' => false, 'message' => 'Lamaran tidak ditemukan.'];

        // Authorization check: company must own the vacancy, or admin
        if (!isAdmin()) {
            $stmt = $pdo->prepare(
                "SELECT c.user_id FROM job_vacancies jv
                 JOIN companies c ON c.id = jv.company_id
                 WHERE jv.id = ? LIMIT 1"
            );
            $stmt->execute([$app['vacancy_id']]);
            $row = $stmt->fetch();
            if (!$row || (int)$row['user_id'] !== $byUserId) {
                return ['success' => false, 'message' => 'Tidak memiliki izin.'];
            }
        }

        $oldStatus = $app['status'];
        $stmt2 = $pdo->prepare(
            "UPDATE applications SET status = ?, notes = ?, updated_by = ? WHERE id = ?"
        );
        $stmt2->execute([$newStatus, $notes, $byUserId, $appId]);

        logApplicationStatusChange($appId, $oldStatus, $newStatus, $byUserId, $notes);
        logActivity($byUserId, 'update_application_status', 'application',
            "Application id={$appId} status: {$oldStatus} → {$newStatus}");

        // Notify alumni
        $alumniUserId = self::getAlumniUserIdFromApp($appId);
        if ($alumniUserId) {
            $statusLabel = ucwords(str_replace('_', ' ', $newStatus));
            sendNotification(
                $alumniUserId,
                'Update Status Lamaran',
                "Status lamaran Anda untuk posisi \"{$app['vacancy_title']}\" berubah menjadi: {$statusLabel}",
                $newStatus === 'accepted' ? 'success' : ($newStatus === 'rejected' ? 'error' : 'info'),
                '/alumni/applications.php'
            );
        }

        // If accepted, update alumni work status
        if ($newStatus === 'accepted') {
            $stmt3 = $pdo->prepare(
                "UPDATE alumni_profiles SET work_status = 'employed'
                 WHERE id = (SELECT alumni_id FROM applications WHERE id = ?)"
            );
            $stmt3->execute([$appId]);
        }

        return ['success' => true];
    }

    public static function getById(int $id): ?array {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT a.*, jv.title as vacancy_title, jv.company_id,
                    ap.full_name as alumni_name, ap.user_id as alumni_user_id,
                    c.company_name
             FROM applications a
             JOIN job_vacancies jv ON jv.id = a.vacancy_id
             JOIN alumni_profiles ap ON ap.id = a.alumni_id
             JOIN companies c ON c.id = jv.company_id
             WHERE a.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function getStatusLogs(int $appId): array {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT asl.*, u.username
             FROM application_status_logs asl
             LEFT JOIN users u ON u.id = asl.changed_by
             WHERE asl.application_id = ?
             ORDER BY asl.created_at ASC"
        );
        $stmt->execute([$appId]);
        return $stmt->fetchAll();
    }

    public static function listByVacancy(int $vacancyId): array {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT a.*, ap.full_name, ap.jurusan, ap.phone, ap.cv_path
             FROM applications a
             JOIN alumni_profiles ap ON ap.id = a.alumni_id
             WHERE a.vacancy_id = ?
             ORDER BY a.applied_at DESC"
        );
        $stmt->execute([$vacancyId]);
        return $stmt->fetchAll();
    }

    public static function listAll(array $filters = [], int $limit = PER_PAGE, int $offset = 0): array {
        $pdo    = getDB();
        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = "a.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['vacancy_id'])) {
            $where[]  = "a.vacancy_id = ?";
            $params[] = (int)$filters['vacancy_id'];
        }

        $whereSQL  = implode(' AND ', $where);
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM applications a WHERE {$whereSQL}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $params[] = $limit;
        $params[] = $offset;
        $stmt     = $pdo->prepare(
            "SELECT a.*, jv.title as vacancy_title, ap.full_name, c.company_name
             FROM applications a
             JOIN job_vacancies jv ON jv.id = a.vacancy_id
             JOIN alumni_profiles ap ON ap.id = a.alumni_id
             JOIN companies c ON c.id = jv.company_id
             WHERE {$whereSQL}
             ORDER BY a.applied_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);
        return ['data' => $stmt->fetchAll(), 'total' => $total];
    }

    private static function getAlumniUserIdFromApp(int $appId): ?int {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT ap.user_id FROM applications a
             JOIN alumni_profiles ap ON ap.id = a.alumni_id
             WHERE a.id = ? LIMIT 1"
        );
        $stmt->execute([$appId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['user_id'] : null;
    }

    private static function notifyCompany(int $companyId, string $title, string $body, string $link = ''): void {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ? LIMIT 1");
        $stmt->execute([$companyId]);
        $row  = $stmt->fetch();
        if ($row) sendNotification($row['user_id'], $title, $body, 'info', $link);
    }
}
