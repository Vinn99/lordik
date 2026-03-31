<?php
// public/auth/reset_password.php

if (isLoggedIn()) redirect('/' . currentRole() . '/dashboard.php');

$tab    = $_GET['tab'] ?? 'alumni'; // 'alumni' or 'company'
$errors = [];
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $tab = sanitize($_POST['tab'] ?? 'alumni');

    if ($tab === 'alumni') {
        $nis     = sanitize($_POST['nis'] ?? '');
        $nisn    = sanitize($_POST['nisn'] ?? '');
        $newPwd  = $_POST['new_password'] ?? '';
        $confPwd = $_POST['confirm_password'] ?? '';

        if ($newPwd !== $confPwd) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        } elseif (empty($nis) || empty($nisn)) {
            $errors[] = 'NIS dan NISN wajib diisi.';
        } else {
            $result = AuthModule::resetPasswordAlumni($nis, $nisn, $newPwd);
            if ($result['success']) {
                setFlash('success', $result['message']);
                redirect('/auth/login.php');
            } else {
                $errors[] = $result['message'];
            }
        }
    } else {
        $email   = sanitize($_POST['email'] ?? '');
        $pin     = $_POST['pin'] ?? '';
        $newPwd  = $_POST['new_password'] ?? '';
        $confPwd = $_POST['confirm_password'] ?? '';

        if ($newPwd !== $confPwd) {
            $errors[] = 'Konfirmasi password tidak cocok.';
        } elseif (empty($email) || empty($pin)) {
            $errors[] = 'Email dan PIN wajib diisi.';
        } else {
            $result = AuthModule::resetPasswordCompany($email, $pin, $newPwd);
            if ($result['success']) {
                setFlash('success', $result['message']);
                redirect('/auth/login.php');
            } else {
                $errors[] = $result['message'];
            }
        }
    }
}

$pageTitle = 'Reset Password — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>body{background:linear-gradient(135deg,#1e3a8a,#3b82f6);min-height:100vh;}</style>
</head>
<body class="d-flex align-items-center justify-content-center py-5">
<div class="card shadow-lg mx-3" style="max-width:480px;width:100%;border-radius:1rem;">
    <div class="card-body p-5">
        <h4 class="fw-bold text-primary mb-1"><i class="bi bi-unlock me-2"></i>Reset Password</h4>
        <p class="text-muted small mb-4">Pilih metode reset password sesuai jenis akun Anda.</p>

        <?php if ($errors): ?>
        <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'alumni' ? 'active' : '' ?>" href="?tab=alumni">Alumni</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'company' ? 'active' : '' ?>" href="?tab=company">Perusahaan</a>
            </li>
        </ul>

        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="tab" value="<?= e($tab) ?>">

            <?php if ($tab === 'alumni'): ?>
            <div class="mb-3">
                <label class="form-label">NIS (Nomor Induk Siswa)</label>
                <input type="text" name="nis" class="form-control" required value="<?= e($_POST['nis'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">NISN</label>
                <input type="text" name="nisn" class="form-control" required value="<?= e($_POST['nisn'] ?? '') ?>">
            </div>
            <?php else: ?>
            <div class="mb-3">
                <label class="form-label">Email Perusahaan</label>
                <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Reset PIN</label>
                <input type="password" name="pin" class="form-control" required placeholder="PIN yang pernah Anda set">
            </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">Password Baru</label>
                <input type="password" name="new_password" class="form-control" required
                       placeholder="Min 8 karakter, huruf besar, kecil, angka">
            </div>
            <div class="mb-4">
                <label class="form-label">Konfirmasi Password Baru</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
        </form>
        <div class="text-center mt-3 small">
            <a href="<?= APP_URL ?>/auth/login.php"><i class="bi bi-arrow-left"></i> Kembali ke Login</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
