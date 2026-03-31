<?php
// public/auth/change_password.php

requireLogin();

$force  = isset($_GET['force']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $currentPwd = $_POST['current_password'] ?? '';
    $newPwd     = $_POST['new_password'] ?? '';
    $confirmPwd = $_POST['confirm_password'] ?? '';

    if ($newPwd !== $confirmPwd) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    } elseif (!$force) {
        // Verify current password
        $user = getUserById(currentUserId());
        if (!password_verify($currentPwd, $user['password'])) {
            $errors[] = 'Password saat ini tidak benar.';
        }
    }

    if (!$errors) {
        $result = AuthModule::forceChangePassword(currentUserId(), $newPwd);
        if ($result['success']) {
            setFlash('success', 'Password berhasil diubah.');
            redirect('/' . currentRole() . '/dashboard.php');
        } else {
            $errors[] = $result['message'];
        }
    }
}

$pageTitle = 'Ganti Password — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>
<div class="mb-3"><?= backButton('', 'Kembali') ?></div>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <?php if ($force): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Perhatian!</strong> Anda harus mengganti password sebelum melanjutkan.
        </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-key me-2"></i>Ganti Password</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrfField() ?>
                    <?php if (!$force): ?>
                    <div class="mb-3">
                        <label class="form-label">Password Saat Ini</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="new_password" class="form-control" required
                               placeholder="Min. 8 karakter, huruf besar, kecil, angka">
                        <div class="form-text">Password harus mengandung minimal 8 karakter, huruf besar, huruf kecil, dan angka.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-2"></i>Simpan Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
