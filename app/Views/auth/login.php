<?php
// public/auth/login.php

if (isLoggedIn()) redirect('/' . currentRole() . '/dashboard.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errors[] = 'Username dan password wajib diisi.';
    } else {
        $result = AuthModule::login($username, $password);
        if ($result['success']) {
            if ($result['force_change_pwd']) {
                setFlash('warning', 'Silakan ganti password Anda terlebih dahulu.');
                redirect('/auth/change_password.php?force=1');
            }
            redirect('/' . $result['role'] . '/dashboard.php');
        } else {
            $errors[] = $result['message'];
        }
    }
}

$pageTitle = 'Login — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        /* ── LEFT PANEL (Frontend / User) ─────────────── */
        .panel-left {
            background: linear-gradient(155deg, #1d4ed8 0%, #2563eb 50%, #7c3aed 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2.5rem;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .panel-left::before {
            content: '';
            position: absolute;
            width: 320px; height: 320px;
            background: rgba(255,255,255,.05);
            border-radius: 50%;
            top: -80px; right: -80px;
        }
        .panel-left::after {
            content: '';
            position: absolute;
            width: 200px; height: 200px;
            background: rgba(255,255,255,.04);
            border-radius: 50%;
            bottom: -40px; left: -40px;
        }
        .panel-left-content { position: relative; z-index: 1; text-align: center; max-width: 380px; }
        .panel-left .brand-icon {
            width: 72px; height: 72px;
            background: rgba(255,255,255,.15);
            backdrop-filter: blur(8px);
            border-radius: 20px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 2rem; color: #fff;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255,255,255,.2);
        }
        .panel-left h1 { font-size: 2rem; font-weight: 800; letter-spacing: -.5px; margin-bottom: .5rem; }
        .panel-left .tagline { opacity: .8; font-size: .95rem; line-height: 1.6; margin-bottom: 2rem; }
        .feature-list { list-style: none; text-align: left; display: inline-block; }
        .feature-list li {
            display: flex; align-items: center; gap: .6rem;
            font-size: .875rem; opacity: .85;
            margin-bottom: .5rem;
        }
        .feature-list li i {
            width: 20px; height: 20px;
            background: rgba(255,255,255,.15);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; flex-shrink: 0;
        }

        /* ── RIGHT PANEL (Backend / Admin) ─────────────── */
        .panel-right {
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .login-box {
            width: 100%;
            max-width: 400px;
        }
        .login-box .login-header {
            margin-bottom: 2rem;
        }
        .login-box .login-header h2 {
            font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: .25rem;
        }
        .login-box .login-header p { color: #64748b; font-size: .9rem; }

        .form-control, .form-select {
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: .65rem .95rem;
            font-size: .9rem;
            transition: border-color .15s, box-shadow .15s;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 .2rem rgba(37,99,235,.12);
        }
        .input-group .form-control { border-radius: 0 10px 10px 0 !important; }
        .input-group-text {
            background: #f8fafc; border: 1.5px solid #e2e8f0; border-right: 0;
            border-radius: 10px 0 0 10px !important; color: #94a3b8;
        }
        .input-group:focus-within .input-group-text { border-color: #2563eb; }
        .input-group .btn-outline-secondary {
            border: 1.5px solid #e2e8f0; border-left: 0;
            border-radius: 0 10px 10px 0 !important;
            color: #94a3b8; font-size: .9rem;
        }

        .btn-login {
            background: #2563eb; border: none;
            color: #fff; border-radius: 10px;
            padding: .7rem 1.5rem; font-weight: 600;
            font-size: .95rem; width: 100%;
            cursor: pointer; transition: background .15s, transform .1s;
        }
        .btn-login:hover { background: #1d4ed8; transform: translateY(-1px); }
        .btn-login:active { transform: translateY(0); }

        .form-label { font-size: .83rem; font-weight: 600; color: #374151; margin-bottom: .4rem; }

        .divider {
            display: flex; align-items: center; gap: 1rem;
            color: #94a3b8; font-size: .8rem; margin: 1.25rem 0;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: #e2e8f0;
        }

        /* Admin quick-access hint */
        .admin-hint {
            display: flex; align-items: center; gap: .6rem;
            padding: .65rem .9rem;
            background: #fff7ed; border: 1.5px solid #fed7aa;
            border-radius: 10px; font-size: .8rem; color: #92400e;
            margin-top: .75rem;
        }
        .admin-hint i { color: #f59e0b; }

        /* Alert */
        .alert { border-radius: 10px; font-size: .875rem; }

        /* ── Back Button ─────────────────────────────── */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            font-size: .82rem;
            color: #6b7280;
            text-decoration: none;
            padding: .35rem .7rem;
            border-radius: 8px;
            transition: background .15s, color .15s;
            position: absolute;
            top: 1.25rem;
            left: 1.5rem;
            z-index: 10;
        }
        .btn-back:hover { background: rgba(0,0,0,.06); color: #374151; }
        .btn-back i { font-size: .85rem; }

        /* ── MOBILE ─────────────────────────────────── */
        @media (max-width: 768px) {
            body { grid-template-columns: 1fr; }
            .panel-left { display: none !important; }
            .panel-right { min-height: 100vh; padding: 2.5rem 1.25rem 2rem; }
            .btn-back {
                top: .85rem;
                left: 1rem;
            }
        }

        /* ── SMALL MOBILE ────────────────────────────── */
        @media (max-width: 400px) {
            .panel-right { padding: 2.5rem .85rem 1.5rem; }
            .login-box .login-header h2 { font-size: 1.3rem; }
        }

        /* ── LARGE DESKTOP ───────────────────────────── */
        @media (min-width: 1280px) {
            .panel-left { padding: 3rem 3.5rem; }
            .panel-right { padding: 2.5rem 3rem; }
        }
    </style>
</head>
<body>

<!-- ── LEFT: Branding / Features ─────────────────── -->
<div class="panel-left">
    <div class="panel-left-content">
        <img src="<?= APP_URL ?> /assets/images/logo.jpg" alt="LORDIK Logo" class="brand-icon">
        <h1>LORDIK</h1>
        <p class="tagline">
            Platform Bursa Kerja Khusus SMK — menghubungkan alumni dengan peluang karir terbaik.
        </p>
        <ul class="feature-list">
            <li><i class="bi bi-check-lg"></i> Cari lowongan yang sesuai jurusan Anda</li>
            <li><i class="bi bi-check-lg"></i> Lamar dan pantau status secara real-time</li>
            <li><i class="bi bi-check-lg"></i> Chat langsung dengan HRD perusahaan</li>
            <li><i class="bi bi-check-lg"></i> Kelola profil dan CV Anda dengan mudah</li>
        </ul>
    </div>
</div>

<!-- ── RIGHT: Login Form ──────────────────────────── -->
<div class="panel-right" style="position:relative;">
    <a href="<?= APP_URL ?>" class="btn-back">
        <i class="bi bi-arrow-left"></i> Beranda
    </a>
    <div class="login-box">

        <div class="login-header">
            <h2>Selamat Datang</h2>
            <p>Masuk ke akun Anda untuk melanjutkan.</p>
        </div>

        <?php if ($errors): ?>
        <div class="alert alert-danger d-flex gap-2 mb-3">
            <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
            <div><?php foreach ($errors as $e): ?><?= e($e) ?><?php endforeach; ?></div>
        </div>
        <?php endif; ?>

        <?php foreach (getFlash() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show mb-3">
            <?= $flash['message'] ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endforeach; ?>

        <form method="POST">
            <?= csrfField() ?>

            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control"
                           placeholder="Masukkan username Anda"
                           value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label mb-0">Password</label>
                    <a href="<?= APP_URL ?>/auth/reset_password.php"
                       class="text-decoration-none" style="font-size:.8rem;color:#2563eb">Lupa password?</a>
                </div>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="pwdInput" class="form-control"
                           placeholder="Masukkan password Anda" required>
                    <button type="button" class="btn btn-outline-secondary px-3" onclick="togglePwd()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
            </button>
        </form>

        <div class="divider">atau</div>

        <!-- Reset password quick links -->
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/auth/reset_password.php?tab=alumni"
               class="btn btn-sm w-50 text-center py-2"
               style="border:1.5px solid #e2e8f0;border-radius:10px;font-size:.8rem;color:#374151;text-decoration:none;background:#fff">
                <i class="bi bi-mortarboard me-1 text-info"></i>Reset Alumni
            </a>
            <a href="<?= APP_URL ?>/auth/reset_password.php?tab=company"
               class="btn btn-sm w-50 text-center py-2"
               style="border:1.5px solid #e2e8f0;border-radius:10px;font-size:.8rem;color:#374151;text-decoration:none;background:#fff">
                <i class="bi bi-building me-1 text-success"></i>Reset Perusahaan
            </a>
        </div>

        <!-- Subtle admin hint -->
        <div class="admin-hint mt-3">
            <i class="bi bi-shield-lock-fill"></i>
            <span>Admin? Gunakan username dan password yang sudah ditetapkan.</span>
        </div>

        <div class="text-center mt-4" style="font-size:.75rem;color:#94a3b8">
            &copy; <?= date('Y') ?> LORDIK — Sistem Informasi Bursa Kerja Khusus SMK
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePwd() {
    const inp = document.getElementById('pwdInput');
    const ico = document.getElementById('eyeIcon');
    inp.type = inp.type === 'password' ? 'text' : 'password';
    ico.className = inp.type === 'text' ? 'bi bi-eye-slash' : 'bi bi-eye';
}
</script>
</body>
</html>
