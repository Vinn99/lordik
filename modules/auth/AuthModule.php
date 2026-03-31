<?php
// modules/auth/AuthModule.php


class AuthModule {

    public static function login(string $username, string $password): array {
        $user = getUserByUsername($username);

        if (!$user) {
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }

        if (!$user['is_active'] || $user['deleted_at'] !== null) {
            return ['success' => false, 'message' => 'Akun Anda tidak aktif. Hubungi admin.'];
        }

        if (!password_verify($password, $user['password'])) {
            logActivity($user['id'], 'login_failed', 'auth', 'Failed login attempt for: ' . $username);
            return ['success' => false, 'message' => 'Username atau password salah.'];
        }

        loginUser($user);
        updateLastLogin($user['id']);
        logActivity($user['id'], 'login', 'auth', 'User logged in successfully');

        return [
            'success'          => true,
            'force_change_pwd' => (bool)$user['force_change_pwd'],
            'role'             => $user['role'],
        ];
    }

    public static function forceChangePassword(int $userId, string $newPassword): array {
        $errors = self::validatePassword($newPassword);
        if ($errors) {
            return ['success' => false, 'message' => implode(' ', $errors)];
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo  = getDB();
        $stmt = $pdo->prepare("UPDATE users SET password = ?, force_change_pwd = 0 WHERE id = ?");
        $stmt->execute([$hash, $userId]);

        logActivity($userId, 'force_change_password', 'auth', 'User changed initial password');
        logPasswordReset($userId, 'admin', true);

        return ['success' => true];
    }

    public static function resetPasswordAlumni(string $nis, string $nisn, string $newPassword): array {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT u.* FROM users u
             JOIN alumni_profiles ap ON ap.user_id = u.id
             WHERE ap.nis = ? AND ap.nisn = ? AND u.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$nis, $nisn]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'NIS/NISN tidak cocok dengan data alumni.'];
        }

        $errors = self::validatePassword($newPassword);
        if ($errors) {
            return ['success' => false, 'message' => implode(' ', $errors)];
        }

        $hash  = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt2 = $pdo->prepare("UPDATE users SET password = ?, force_change_pwd = 0 WHERE id = ?");
        $stmt2->execute([$hash, $user['id']]);

        logActivity($user['id'], 'reset_password', 'auth', 'Alumni reset password via NIS/NISN');
        logPasswordReset($user['id'], 'nis_nisn', true);

        return ['success' => true, 'message' => 'Password berhasil direset. Silakan login.'];
    }

    public static function resetPasswordCompany(string $email, string $pin, string $newPassword): array {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            "SELECT u.*, c.reset_pin FROM users u
             JOIN companies c ON c.user_id = u.id
             WHERE u.email = ? AND u.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !$user['reset_pin']) {
            return ['success' => false, 'message' => 'Email tidak ditemukan atau PIN belum diset.'];
        }

        if (!password_verify($pin, $user['reset_pin'])) {
            logPasswordReset($user['id'], 'reset_pin', false);
            return ['success' => false, 'message' => 'PIN tidak valid.'];
        }

        $errors = self::validatePassword($newPassword);
        if ($errors) {
            return ['success' => false, 'message' => implode(' ', $errors)];
        }

        $hash  = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt2 = $pdo->prepare("UPDATE users SET password = ?, force_change_pwd = 0 WHERE id = ?");
        $stmt2->execute([$hash, $user['id']]);

        logActivity($user['id'], 'reset_password', 'auth', 'Company reset password via PIN');
        logPasswordReset($user['id'], 'reset_pin', true);

        return ['success' => true, 'message' => 'Password berhasil direset. Silakan login.'];
    }

    public static function adminResetPassword(int $targetUserId, int $adminId): array {
        $pdo  = getDB();
        $uStmt = $pdo->prepare("SELECT username, email, role FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$targetUserId]);
        $uRow = $uStmt->fetch();

        $tempPwd = self::generateTempPassword();
        $hash    = password_hash($tempPwd, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $pdo->prepare("UPDATE users SET password = ?, force_change_pwd = 1 WHERE id = ?");
        $stmt->execute([$hash, $targetUserId]);

        logActivity($adminId, 'admin_reset_password', 'auth', "Admin reset password for user_id={$targetUserId}");
        logPasswordReset($targetUserId, 'admin', true);

        return ['success' => true, 'temp_password' => $tempPwd,
                'username' => $uRow['username'] ?? '', 'email' => $uRow['email'] ?? '', 'role' => $uRow['role'] ?? ''];
    }

    private static function validatePassword(string $password): array {
        $errors = [];
        if (strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password harus mengandung huruf besar.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password harus mengandung huruf kecil.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password harus mengandung angka.';
        }
        return $errors;
    }

    private static function generateTempPassword(): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $pwd   = 'Tmp' . substr(str_shuffle($chars), 0, 6) . random_int(10, 99);
        return $pwd;
    }
}
