<?php
/**
 * app/Models/CompanyModel.php — Tim: Database
 * Model untuk tabel companies.
 */
require_once BASE_PATH . '/app/Models/BaseModel.php';

class CompanyModel extends BaseModel {
    protected static string $table = 'companies';

    public static function getProfile(int $userId): ?array {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT c.*, u.email, u.username
             FROM companies c JOIN users u ON u.id = c.user_id
             WHERE c.user_id = ? LIMIT 1"
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function getById(int $companyId): ?array {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT c.*, u.email, u.username
             FROM companies c JOIN users u ON u.id = c.user_id
             WHERE c.id = ? LIMIT 1"
        );
        $stmt->execute([$companyId]);
        return $stmt->fetch() ?: null;
    }

    public static function profileExists(int $userId): bool {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return (bool)$stmt->fetch();
    }

    public static function createOrUpdateProfile(int $userId, array $data): array {
        $pdo    = getDB();
        $errors = [];

        if (empty($data['company_name'])) $errors[] = 'Nama perusahaan wajib diisi.';
        if ($errors) return ['success' => false, 'errors' => $errors];

        $exists = self::profileExists($userId);

        if ($exists) {
            $stmt = $pdo->prepare(
                "UPDATE companies SET
                    company_name = ?, industry = ?, address = ?, city = ?,
                    phone = ?, website = ?, description = ?
                 WHERE user_id = ?"
            );
            $stmt->execute([
                sanitize($data['company_name']),
                sanitize($data['industry'] ?? ''),
                sanitize($data['address'] ?? ''),
                sanitize($data['city'] ?? ''),
                sanitize($data['phone'] ?? ''),
                sanitize($data['website'] ?? ''),
                sanitize($data['description'] ?? ''),
                $userId,
            ]);
            logActivity($userId, 'update_company_profile', 'company', 'Company updated profile');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO companies (user_id, company_name, industry, address, city, phone, website, description)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $userId,
                sanitize($data['company_name']),
                sanitize($data['industry'] ?? ''),
                sanitize($data['address'] ?? ''),
                sanitize($data['city'] ?? ''),
                sanitize($data['phone'] ?? ''),
                sanitize($data['website'] ?? ''),
                sanitize($data['description'] ?? ''),
            ]);
            logActivity($userId, 'create_company_profile', 'company', 'Company created profile');
        }

        return ['success' => true];
    }

    public static function uploadLogo(int $userId, array $file): array {
        $profile = self::getProfile($userId);
        if (!$profile) return ['success' => false, 'message' => 'Profil perusahaan belum dibuat.'];

        try {
            $path = handleUpload($file, 'logos', ALLOWED_LOGO_MIME, MAX_LOGO_SIZE, $profile['logo_path']);
        } catch (RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        $pdo  = getDB();
        $stmt = $pdo->prepare("UPDATE companies SET logo_path = ? WHERE user_id = ?");
        $stmt->execute([$path, $userId]);

        logActivity($userId, 'upload_logo', 'company', 'Company uploaded logo');
        return ['success' => true, 'path' => $path];
    }

    public static function setResetPin(int $userId, string $pin): array {
        if (strlen($pin) < 6) {
            return ['success' => false, 'message' => 'PIN minimal 6 digit.'];
        }
        $hash = password_hash($pin, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo  = getDB();
        $stmt = $pdo->prepare("UPDATE companies SET reset_pin = ? WHERE user_id = ?");
        $stmt->execute([$hash, $userId]);
        logActivity($userId, 'set_reset_pin', 'company', 'Company set reset PIN');
        return ['success' => true];
    }

    public static function listAll(int $limit = PER_PAGE, int $offset = 0, string $search = ''): array {
        $pdo    = getDB();
        $where  = '';
        $params = [];

        if ($search) {
            $where    = "WHERE c.company_name LIKE ? OR c.city LIKE ?";
            $s        = '%' . $search . '%';
            $params[] = $s;
            $params[] = $s;
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM companies c {$where}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $params[] = $limit;
        $params[] = $offset;
        $stmt = $pdo->prepare(
            "SELECT c.*, u.email FROM companies c
             JOIN users u ON u.id = c.user_id {$where}
             ORDER BY c.company_name ASC LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);

        return ['data' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Alias untuk createOrUpdateProfile — digunakan oleh AdminController.
     */
    public static function upsert(int $userId, array $data): array
    {
        return self::createOrUpdateProfile($userId, $data);
    }

    /**
     * Alias untuk getById — digunakan oleh AdminController.
     */
    public static function findById(int $companyId): ?array
    {
        return self::getById($companyId);
    }

    /**
     * Set/unset verified status — digunakan oleh AdminController.
     */
    public static function setVerified(int $companyId, int $verified): void
    {
        $pdo  = getDB();
        $stmt = $pdo->prepare("UPDATE companies SET verified = ? WHERE id = ?");
        $stmt->execute([$verified, $companyId]);
        logActivity(0, 'set_company_verified', 'company', "Company id={$companyId} verified={$verified}");
    }

}
