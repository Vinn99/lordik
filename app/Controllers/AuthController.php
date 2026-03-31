<?php
/**
 * AuthController — Tim Backend
 */

class AuthController
{
    public static function login(): void
    {
        validateCsrf();
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = UserModel::findByCredential($username, $password);
        if (!$user) {
            setFlash('danger', 'Username/email atau password salah.');
            redirect('/auth/login.php');
        }

        // Regenerate session untuk keamanan
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();

        UserModel::touchLogin($user['id']);
        logActivity($user['id'], 'login', 'auth', 'Login berhasil');

        if ($user['force_change_pwd']) {
            redirect('/auth/change_password.php');
        }
        redirect('/' . $user['role'] . '/dashboard.php');
    }

    public static function logout(): void
    {
        if (isLoggedIn()) logActivity(currentUserId(), 'logout', 'auth', 'Logout');
        session_destroy();
        redirect('/auth/login.php');
    }

    public static function changePassword(): void
    {
        requireLogin();
        validateCsrf();
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new !== $confirm) { setFlash('danger', 'Konfirmasi password tidak cocok.'); redirect('/auth/change_password.php'); }
        $errors = self::_validatePassword($new);
        if ($errors) { setFlash('danger', implode(' ', $errors)); redirect('/auth/change_password.php'); }

        $user = UserModel::findById(currentUserId());
        if (!password_verify($current, $user['password'])) {
            setFlash('danger', 'Password lama tidak sesuai.');
            redirect('/auth/change_password.php');
        }

        UserModel::changePassword(currentUserId(), $new);
        logActivity(currentUserId(), 'change_password', 'auth', 'Password diubah');
        setFlash('success', 'Password berhasil diubah.');
        redirect('/' . currentRole() . '/dashboard.php');
    }

    public static function resetPasswordAlumni(): void
    {
        validateCsrf();
        $nis     = sanitize($_POST['nis'] ?? '');
        $nisn    = sanitize($_POST['nisn'] ?? '');
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new !== $confirm) { setFlash('danger', 'Password tidak cocok.'); redirect('/auth/reset_password.php?tab=alumni'); }
        $errors = self::_validatePassword($new);
        if ($errors) { setFlash('danger', implode(' ', $errors)); redirect('/auth/reset_password.php?tab=alumni'); }

        $user = UserModel::findByNisNisn($nis, $nisn);
        if (!$user) { setFlash('danger', 'NIS/NISN tidak ditemukan.'); redirect('/auth/reset_password.php?tab=alumni'); }

        UserModel::changePassword($user['id'], $new);
        logPasswordReset($user['id'], 'nis_nisn', true);
        setFlash('success', 'Password berhasil direset. Silakan login.');
        redirect('/auth/login.php');
    }

    private static function _validatePassword(string $pwd): array
    {
        $e = [];
        if (strlen($pwd) < 8)              $e[] = 'Minimal 8 karakter.';
        if (!preg_match('/[A-Z]/', $pwd))  $e[] = 'Harus ada huruf besar.';
        if (!preg_match('/[a-z]/', $pwd))  $e[] = 'Harus ada huruf kecil.';
        if (!preg_match('/[0-9]/', $pwd))  $e[] = 'Harus ada angka.';
        return $e;
    }
}
