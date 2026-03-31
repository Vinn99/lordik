<?php
/**
 * app/Models/UserModel.php — Tim: Database
 * Model untuk tabel users.
 */
require_once BASE_PATH . '/app/Models/BaseModel.php';

class UserModel extends BaseModel
{
    protected static string $table = 'users';

    // ─────────────────────────────────────────────
    // Admin User Management
    // ─────────────────────────────────────────────

    public static function create(array $data): array
    {
        if (empty($data['username'])) return ['success' => false, 'message' => 'Username wajib diisi.'];
        if (empty($data['email']))    return ['success' => false, 'message' => 'Email wajib diisi.'];
        $role = $data['role'] ?? '';
        if (!in_array($role, [ROLE_ALUMNI, ROLE_COMPANY, ROLE_ADMIN], true))
            return ['success' => false, 'message' => 'Role tidak valid.'];

        $exists = static::queryOne(
            "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1",
            [$data['username'], $data['email']]
        );
        if ($exists) return ['success' => false, 'message' => 'Username atau email sudah digunakan.'];

        $tempPwd = 'Temp@' . random_int(1000, 9999);
        $hash    = password_hash($tempPwd, PASSWORD_BCRYPT, ['cost' => 12]);

        static::execute(
            "INSERT INTO users (username, email, password, role, force_change_pwd) VALUES (?, ?, ?, ?, 1)",
            [sanitize($data['username']), sanitize($data['email']), $hash, $role]
        );
        $userId = static::lastId();

        return [
            'success'      => true,
            'user_id'      => $userId,
            'temp_password'=> $tempPwd,
            'username'     => $data['username'],
            'email'        => $data['email'],
            'role'         => $role,
        ];
    }

    public static function toggleActive(int $userId): void
    {
        static::execute(
            "UPDATE users SET is_active = NOT is_active WHERE id = ?",
            [$userId]
        );
    }

    public static function softDelete(int $userId): void
    {
        static::execute(
            "UPDATE users SET deleted_at = NOW(), is_active = 0 WHERE id = ?",
            [$userId]
        );
    }

    public static function resetPassword(int $userId): array
    {
        $user = static::queryOne("SELECT username, email, role FROM users WHERE id = ? LIMIT 1", [$userId]);
        if (!$user) return ['success' => false, 'message' => 'User tidak ditemukan.'];

        $tempPwd = 'Tmp' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6) . random_int(10, 99);
        $hash    = password_hash($tempPwd, PASSWORD_BCRYPT, ['cost' => 12]);

        static::execute(
            "UPDATE users SET password = ?, force_change_pwd = 1 WHERE id = ?",
            [$hash, $userId]
        );

        return [
            'success'      => true,
            'temp_password'=> $tempPwd,
            'username'     => $user['username'],
            'email'        => $user['email'],
            'role'         => $user['role'],
        ];
    }

    public static function findById(int $id): ?array
    {
        return static::queryOne(
            "SELECT * FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1",
            [$id]
        );
    }

    public static function listAll(array $filters = [], int $limit = PER_PAGE, int $offset = 0): array
    {
        $where  = ["deleted_at IS NULL"];
        $params = [];

        if (!empty($filters['role'])) {
            $where[]  = "role = ?";
            $params[] = $filters['role'];
        }
        if (!empty($filters['search'])) {
            $where[]  = "(username LIKE ? OR email LIKE ?)";
            $s        = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
        }

        $whereSQL = implode(' AND ', $where);
        $total    = static::count("SELECT COUNT(*) FROM users WHERE {$whereSQL}", $params);

        $params[] = $limit;
        $params[] = $offset;

        $data = static::query(
            "SELECT * FROM users WHERE {$whereSQL} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            $params
        );

        return ['data' => $data, 'total' => $total];
    }

    // ─── Methods used by AuthController ──────────────────────

    public static function findByCredential(string $username, string $password): ?array
    {
        $user = static::queryOne(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1 AND deleted_at IS NULL LIMIT 1",
            [$username, $username]
        );
        if (!$user) return null;
        if (!password_verify($password, $user['password'])) {
            logActivity($user['id'], 'login_failed', 'auth', "Failed login: {$username}");
            return null;
        }
        return $user;
    }

    public static function touchLogin(int $userId): void
    {
        static::execute("UPDATE users SET last_login = NOW() WHERE id = ?", [$userId]);
    }

    public static function findByNisNisn(string $nis, string $nisn): ?array
    {
        return static::queryOne(
            "SELECT u.* FROM users u
             JOIN alumni_profiles ap ON ap.user_id = u.id
             WHERE ap.nis = ? AND ap.nisn = ? AND u.deleted_at IS NULL LIMIT 1",
            [$nis, $nisn]
        );
    }

    public static function changePassword(int $userId, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        static::execute(
            "UPDATE users SET password = ?, force_change_pwd = 0 WHERE id = ?",
            [$hash, $userId]
        );
    }

}
