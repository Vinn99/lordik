<?php
/**
 * app/Models/AlumniModel.php — Tim: Database
 * Model untuk alumni_profiles dan alumni_certificates.
 */
require_once BASE_PATH . '/app/Models/BaseModel.php';

class AlumniModel extends BaseModel {
    protected static string $table = 'alumni_profiles';

    public static function getProfile(int $userId): ?array {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT ap.*, u.username, u.email
             FROM alumni_profiles ap
             JOIN users u ON u.id = ap.user_id
             WHERE ap.user_id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function getProfileById(int $profileId): ?array {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT ap.*, u.username, u.email
             FROM alumni_profiles ap
             JOIN users u ON u.id = ap.user_id
             WHERE ap.id = ? LIMIT 1"
        );
        $stmt->execute([$profileId]);
        return $stmt->fetch() ?: null;
    }

    public static function profileExists(int $userId): bool {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id FROM alumni_profiles WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return (bool)$stmt->fetch();
    }

    public static function createOrUpdateProfile(int $userId, array $data): array {
        $pdo     = getDB();
        $errors  = self::validateProfileData($data, $userId);
        if ($errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $exists = self::profileExists($userId);

        if ($exists) {
            $stmt = $pdo->prepare(
                "UPDATE alumni_profiles SET
                    full_name = ?, gender = ?, birth_date = ?, phone = ?, address = ?,
                    jurusan = ?, graduation_year = ?, work_status = ?, skills = ?, bio = ?
                 WHERE user_id = ?"
            );
            $stmt->execute([
                sanitize($data['full_name']),
                $data['gender'],
                $data['birth_date'] ?: null,
                sanitize($data['phone'] ?? ''),
                sanitize($data['address'] ?? ''),
                sanitize($data['jurusan']),
                (int)$data['graduation_year'],
                $data['work_status'],
                sanitize($data['skills'] ?? ''),
                sanitize($data['bio'] ?? ''),
                $userId,
            ]);
            logActivity($userId, 'update_profile', 'alumni', 'Alumni updated profile');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO alumni_profiles
                    (user_id, nis, nisn, full_name, gender, birth_date, phone, address,
                     jurusan, graduation_year, work_status, skills, bio)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $userId,
                sanitize($data['nis']),
                sanitize($data['nisn']),
                sanitize($data['full_name']),
                $data['gender'],
                $data['birth_date'] ?: null,
                sanitize($data['phone'] ?? ''),
                sanitize($data['address'] ?? ''),
                sanitize($data['jurusan']),
                (int)$data['graduation_year'],
                $data['work_status'],
                sanitize($data['skills'] ?? ''),
                sanitize($data['bio'] ?? ''),
            ]);
            logActivity($userId, 'create_profile', 'alumni', 'Alumni created profile');
        }

        return ['success' => true];
    }

    public static function uploadPhoto(int $userId, array $file): array {
        $profile = self::getProfile($userId);
        if (!$profile) return ['success' => false, 'message' => 'Profil belum dibuat.'];
        try {
            $path = handleUpload($file, 'photos', ['image/jpeg','image/png','image/webp'], 2 * 1024 * 1024, $profile['photo_path'] ?? null);
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
        $pdo = getDB();
        $pdo->prepare("UPDATE alumni_profiles SET photo_path = ? WHERE user_id = ?")->execute([$path, $userId]);
        logActivity($userId, 'upload_photo', 'alumni', 'Alumni uploaded profile photo');
        return ['success' => true, 'path' => $path];
    }

    public static function uploadCV(int $userId, array $file): array {
        $profile = self::getProfile($userId);
        if (!$profile) {
            return ['success' => false, 'message' => 'Profil belum dibuat.'];
        }

        try {
            $path = handleUpload($file, 'cv', ALLOWED_CV_MIME, MAX_CV_SIZE, $profile['cv_path']);
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $pdo  = getDB();
        $stmt = $pdo->prepare("UPDATE alumni_profiles SET cv_path = ? WHERE user_id = ?");
        $stmt->execute([$path, $userId]);

        logActivity($userId, 'upload_cv', 'alumni', 'Alumni uploaded CV');
        return ['success' => true, 'path' => $path];
    }

    public static function uploadCertificate(int $userId, array $file, string $certName, string $issuer, string $issuedDate): array {
        $profile = self::getProfile($userId);
        if (!$profile) {
            return ['success' => false, 'message' => 'Profil belum dibuat.'];
        }

        try {
            $path = handleUpload($file, 'certificates', ALLOWED_CERT_MIME, MAX_CERT_SIZE);
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "INSERT INTO alumni_certificates (alumni_id, cert_name, issuer, issued_date, file_path)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $profile['id'],
            sanitize($certName),
            sanitize($issuer),
            $issuedDate ?: null,
            $path,
        ]);

        logActivity($userId, 'upload_certificate', 'alumni', "Alumni uploaded certificate: {$certName}");
        return ['success' => true];
    }

    public static function deleteCertificate(int $certId, int $userId): array {
        $pdo    = getDB();
        $profile = self::getProfile($userId);
        if (!$profile) return ['success' => false, 'message' => 'Profil tidak ditemukan.'];

        $stmt = $pdo->prepare("SELECT * FROM alumni_certificates WHERE id = ? AND alumni_id = ? LIMIT 1");
        $stmt->execute([$certId, $profile['id']]);
        $cert = $stmt->fetch();

        if (!$cert) return ['success' => false, 'message' => 'Sertifikat tidak ditemukan.'];

        // Delete file
        $fullPath = UPLOAD_DIR . ltrim($cert['file_path'], '/');
        if (is_file($fullPath)) unlink($fullPath);

        $stmt2 = $pdo->prepare("DELETE FROM alumni_certificates WHERE id = ?");
        $stmt2->execute([$certId]);

        logActivity($userId, 'delete_certificate', 'alumni', "Alumni deleted certificate id={$certId}");
        return ['success' => true];
    }

    public static function getCertificates(int $alumniProfileId): array {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT * FROM alumni_certificates WHERE alumni_id = ? ORDER BY issued_date DESC");
        $stmt->execute([$alumniProfileId]);
        return $stmt->fetchAll();
    }

    public static function updateWorkStatus(int $userId, string $status): void {
        $pdo  = getDB();
        $stmt = $pdo->prepare("UPDATE alumni_profiles SET work_status = ? WHERE user_id = ?");
        $stmt->execute([$status, $userId]);
        logActivity($userId, 'update_work_status', 'alumni', "Work status changed to: {$status}");
    }

    public static function getApplicationHistory(int $userId): array {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT a.*, jv.title as vacancy_title, c.company_name
             FROM applications a
             JOIN alumni_profiles ap ON ap.id = a.alumni_id
             JOIN job_vacancies jv ON jv.id = a.vacancy_id
             JOIN companies c ON c.id = jv.company_id
             WHERE ap.user_id = ?
             ORDER BY a.applied_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function searchAlumni(array $filters = [], int $limit = PER_PAGE, int $offset = 0): array {
        $pdo   = getDB();
        $where = ["u.deleted_at IS NULL"];
        $params= [];

        if (!empty($filters['jurusan'])) {
            $where[] = "ap.jurusan = ?";
            $params[] = $filters['jurusan'];
        }
        if (!empty($filters['work_status'])) {
            $where[] = "ap.work_status = ?";
            $params[] = $filters['work_status'];
        }
        if (!empty($filters['graduation_year'])) {
            $where[] = "ap.graduation_year = ?";
            $params[] = (int)$filters['graduation_year'];
        }
        if (!empty($filters['search'])) {
            $where[] = "(ap.full_name LIKE ? OR ap.nis LIKE ? OR ap.nisn LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$s, $s, $s]);
        }

        $whereSQL = implode(' AND ', $where);
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM alumni_profiles ap JOIN users u ON u.id = ap.user_id WHERE {$whereSQL}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $params[] = $limit;
        $params[] = $offset;
        $stmt = $pdo->prepare(
            "SELECT ap.*, u.email
             FROM alumni_profiles ap
             JOIN users u ON u.id = ap.user_id
             WHERE {$whereSQL}
             ORDER BY ap.full_name ASC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll(), 'total' => $total];
    }

    private static function validateProfileData(array $data, int $userId): array {
        $errors = [];
        if (empty($data['full_name'])) $errors[] = 'Nama lengkap wajib diisi.';
        if (empty($data['gender']) || !in_array($data['gender'], ['male', 'female'])) $errors[] = 'Jenis kelamin tidak valid.';
        if (empty($data['jurusan'])) $errors[] = 'Jurusan wajib diisi.';
        if (empty($data['graduation_year']) || !is_numeric($data['graduation_year'])) $errors[] = 'Tahun lulus tidak valid.';

        $validStatuses = ['unemployed', 'employed', 'entrepreneur', 'continuing_edu'];
        if (empty($data['work_status']) || !in_array($data['work_status'], $validStatuses)) {
            $errors[] = 'Status pekerjaan tidak valid.';
        }

        // Only validate NIS/NISN on create
        if (!self::profileExists($userId)) {
            if (empty($data['nis'])) $errors[] = 'NIS wajib diisi.';
            if (empty($data['nisn'])) $errors[] = 'NISN wajib diisi.';

            // Check uniqueness
            $pdo = getDB();
            if (!empty($data['nis'])) {
                $stmt = $pdo->prepare("SELECT id FROM alumni_profiles WHERE nis = ? AND user_id != ?");
                $stmt->execute([$data['nis'], $userId]);
                if ($stmt->fetch()) $errors[] = 'NIS sudah digunakan.';
            }
            if (!empty($data['nisn'])) {
                $stmt = $pdo->prepare("SELECT id FROM alumni_profiles WHERE nisn = ? AND user_id != ?");
                $stmt->execute([$data['nisn'], $userId]);
                if ($stmt->fetch()) $errors[] = 'NISN sudah digunakan.';
            }
        }

        return $errors;
    }

    /**
     * Alias untuk createOrUpdateProfile — digunakan oleh AdminController.
     */
    public static function upsert(int $userId, array $data): array
    {
        return self::createOrUpdateProfile($userId, $data);
    }

}
