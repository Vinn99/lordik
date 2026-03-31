<?php
/**
 * app/Models/VacancyModel.php — Tim: Database
 * Model untuk tabel job_vacancies.
 */
require_once BASE_PATH . '/app/Models/BaseModel.php';

class VacancyModel extends BaseModel {
    protected static string $table = 'job_vacancies';

    public static function submit(int $userId, array $data): array {
        $pdo     = getDB();
        $company = self::getCompanyByUserId($userId);
        if (!$company) return ['success' => false, 'message' => 'Profil perusahaan tidak ditemukan.'];

        $errors = self::validateVacancyData($data);
        if ($errors) return ['success' => false, 'errors' => $errors];

        $stmt = $pdo->prepare(
            "INSERT INTO job_vacancies
                (company_id, title, description, requirements, location, salary_min, salary_max,
                 job_type, jurusan_required, slots, deadline, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')"
        );
        $stmt->execute([
            $company['id'],
            sanitize($data['title']),
            $data['description'],
            $data['requirements'] ?? '',
            sanitize($data['location'] ?? ''),
            !empty($data['salary_min']) ? (float)$data['salary_min'] : null,
            !empty($data['salary_max']) ? (float)$data['salary_max'] : null,
            $data['job_type'],
            sanitize($data['jurusan_required'] ?? ''),
            max(1, (int)($data['slots'] ?? 1)),
            !empty($data['deadline']) ? $data['deadline'] : null,
        ]);
        $vacancyId = $pdo->lastInsertId();

        logActivity($userId, 'submit_vacancy', 'vacancy', "Company submitted vacancy: {$data['title']}");

        // Notify all admins
        self::notifyAdminsNewVacancy($vacancyId, $data['title'], $company['company_name']);

        return ['success' => true, 'id' => $vacancyId];
    }

    public static function approve(int $vacancyId, int $adminUserId, string $note = ''): array {
        return self::changeStatus($vacancyId, 'approved', $adminUserId, $note);
    }

    public static function reject(int $vacancyId, int $adminUserId, string $note = ''): array {
        return self::changeStatus($vacancyId, 'rejected', $adminUserId, $note);
    }

    public static function close(int $vacancyId, int $userId): array {
        $vacancy = self::getById($vacancyId);
        if (!$vacancy) return ['success' => false, 'message' => 'Lowongan tidak ditemukan.'];

        // Only admin or the company owner can close
        if (!isAdmin()) {
            $company = self::getCompanyByUserId($userId);
            if (!$company || $company['id'] !== (int)$vacancy['company_id']) {
                return ['success' => false, 'message' => 'Tidak memiliki izin.'];
            }
        }

        return self::changeStatus($vacancyId, 'closed', $userId, '');
    }

    public static function changeStatus(int $vacancyId, string $status, int $byUserId, string $note = ''): array {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "UPDATE job_vacancies SET status = ?, admin_note = ?, approved_by = ?, approved_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$status, $note, $byUserId, $vacancyId]);

        logActivity($byUserId, "vacancy_{$status}", 'vacancy', "Vacancy id={$vacancyId} changed to {$status}");

        // Notify company
        $vacancy = self::getById($vacancyId);
        if ($vacancy) {
            $companyUserId = self::getCompanyUserId((int)$vacancy['company_id']);
            if ($companyUserId) {
                $msg = $status === 'approved'
                    ? "Lowongan \"{$vacancy['title']}\" Anda telah disetujui."
                    : "Lowongan \"{$vacancy['title']}\" Anda ditolak. Catatan: {$note}";
                sendNotification($companyUserId, "Update Lowongan", $msg,
                    $status === 'approved' ? 'success' : 'error',
                    '/vacancy/detail.php?id=' . $vacancyId
                );
            }
        }

        return ['success' => true];
    }

    public static function getById(int $id): ?array {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT jv.*, c.company_name, c.logo_path, c.city
             FROM job_vacancies jv
             JOIN companies c ON c.id = jv.company_id
             WHERE jv.id = ? AND jv.deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function list(array $filters = [], int $limit = PER_PAGE, int $offset = 0): array {
        $pdo    = getDB();
        $where  = ["jv.deleted_at IS NULL"];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]  = "jv.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['company_id'])) {
            $where[]  = "jv.company_id = ?";
            $params[] = (int)$filters['company_id'];
        }
        if (!empty($filters['job_type'])) {
            $where[]  = "jv.job_type = ?";
            $params[] = $filters['job_type'];
        }
        if (!empty($filters['jurusan'])) {
            $where[]  = "(jv.jurusan_required = ? OR jv.jurusan_required IS NULL OR jv.jurusan_required = '')";
            $params[] = $filters['jurusan'];
        }
        if (!empty($filters['search'])) {
            $where[]  = "(jv.title LIKE ? OR c.company_name LIKE ?)";
            $s        = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
        }

        $whereSQL  = implode(' AND ', $where);
        $countSQL  = "SELECT COUNT(*) FROM job_vacancies jv JOIN companies c ON c.id = jv.company_id WHERE {$whereSQL}";
        $countStmt = $pdo->prepare($countSQL);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $params[] = $limit;
        $params[] = $offset;
        $stmt     = $pdo->prepare(
            "SELECT jv.*, c.company_name, c.logo_path, c.city
             FROM job_vacancies jv
             JOIN companies c ON c.id = jv.company_id
             WHERE {$whereSQL}
             ORDER BY jv.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function softDelete(int $vacancyId, int $adminUserId): void {
        $pdo  = getDB();
        $stmt = $pdo->prepare("UPDATE job_vacancies SET deleted_at = NOW() WHERE id = ?");
        $stmt->execute([$vacancyId]);
        logActivity($adminUserId, 'delete_vacancy', 'vacancy', "Soft deleted vacancy id={$vacancyId}");
    }

    private static function getCompanyByUserId(int $userId): ?array {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    private static function getCompanyUserId(int $companyId): ?int {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT user_id FROM companies WHERE id = ? LIMIT 1");
        $stmt->execute([$companyId]);
        $row  = $stmt->fetch();
        return $row ? (int)$row['user_id'] : null;
    }

    private static function notifyAdminsNewVacancy(int $vacancyId, string $title, string $companyName): void {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute();
        $admins = $stmt->fetchAll();
        foreach ($admins as $admin) {
            sendNotification(
                $admin['id'],
                'Lowongan Baru Menunggu Persetujuan',
                "{$companyName} mengajukan lowongan: {$title}",
                'info',
                '/admin/vacancy/detail.php?id=' . $vacancyId
            );
        }
    }

    public static function validateVacancyData(array $data): array {
        $errors = [];
        if (empty($data['title'])) $errors[] = 'Judul lowongan wajib diisi.';
        if (empty($data['description'])) $errors[] = 'Deskripsi lowongan wajib diisi.';
        $validTypes = ['full_time', 'part_time', 'contract', 'internship'];
        if (empty($data['job_type']) || !in_array($data['job_type'], $validTypes)) {
            $errors[] = 'Tipe pekerjaan tidak valid.';
        }
        return $errors;
    }

    /**
     * Alias untuk getById — digunakan oleh AdminController.
     */
    public static function findById(int $id): ?array
    {
        return self::getById($id);
    }


    // ─── Methods used by VacancyController ───────────────────

    public static function getCompanyIdByUserId(int $userId): ?int
    {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row  = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }

    public static function create(int $companyId, array $data): int
    {
        $errors = self::validateVacancyData($data);
        if ($errors) throw new \InvalidArgumentException(implode(', ', $errors));

        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO job_vacancies
                (company_id, title, description, requirements, location, salary_min, salary_max,
                 job_type, jurusan_required, slots, deadline, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')"
        );
        $stmt->execute([
            $companyId,
            sanitize($data['title']),
            $data['description'],
            $data['requirements'] ?? '',
            sanitize($data['location'] ?? ''),
            !empty($data['salary_min']) ? (float)$data['salary_min'] : null,
            !empty($data['salary_max']) ? (float)$data['salary_max'] : null,
            $data['job_type'] ?? 'full_time',
            sanitize($data['jurusan_required'] ?? ''),
            max(1, (int)($data['slots'] ?? 1)),
            !empty($data['deadline']) ? $data['deadline'] : null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $vacancyId, array $data): void
    {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "UPDATE job_vacancies SET
                title = ?, description = ?, requirements = ?, location = ?,
                salary_min = ?, salary_max = ?, job_type = ?, jurusan_required = ?,
                slots = ?, deadline = ?
             WHERE id = ?"
        );
        $stmt->execute([
            sanitize($data['title']),
            $data['description'],
            $data['requirements'] ?? '',
            sanitize($data['location'] ?? ''),
            !empty($data['salary_min']) ? (float)$data['salary_min'] : null,
            !empty($data['salary_max']) ? (float)$data['salary_max'] : null,
            $data['job_type'] ?? 'full_time',
            sanitize($data['jurusan_required'] ?? ''),
            max(1, (int)($data['slots'] ?? 1)),
            !empty($data['deadline']) ? $data['deadline'] : null,
            $vacancyId,
        ]);
    }

}
