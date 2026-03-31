<?php
// public/admin/user_credential.php
// Halaman khusus menampilkan kredensial akun setelah dibuat
requireAdmin();

// Ambil data dari session (set saat createUser)
$cred = $_SESSION['new_credential'] ?? null;
if (!$cred) {
    setFlash('warning', 'Tidak ada data kredensial untuk ditampilkan.');
    redirect('/admin/users.php');
}
// Hapus dari session setelah ditampilkan
unset($_SESSION['new_credential']);

$pageTitle = 'Kredensial Akun Baru — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header_admin.php';
?>

<div class="row justify-content-center">
<div class="col-lg-7">

<!-- Header -->
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?= APP_URL ?>/admin/users.php" class="btn-back"><i class="bi bi-arrow-left"></i> Kembali ke Daftar Akun</a>
</div>

<div class="card border-0 shadow" style="border-top:4px solid #22c55e !important; border-radius:16px;">
    <div class="card-body p-4">

        <!-- Success header -->
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 mb-3"
                 style="width:64px;height:64px">
                <i class="bi bi-check-circle-fill text-success fs-2"></i>
            </div>
            <h4 class="fw-bold mb-1">Akun Berhasil Dibuat!</h4>
            <p class="text-muted small mb-0">Catat atau cetak informasi di bawah sebelum menutup halaman ini.</p>
        </div>

        <!-- Credential box -->
        <div class="rounded-3 border p-4 mb-4" style="background:#f8fafc;">
            <div class="row g-3 mb-3">
                <div class="col-sm-4 text-muted small fw-semibold">Role</div>
                <div class="col-sm-8">
                    <?php
                    $roleColors = ['alumni'=>'info','company'=>'success','admin'=>'danger'];
                    $roleLabel  = ['alumni'=>'Alumni','company'=>'Perusahaan','admin'=>'Admin'];
                    $r = $cred['role'] ?? 'alumni';
                    ?>
                    <span class="badge bg-<?= $roleColors[$r] ?? 'secondary' ?> px-3 py-2">
                        <?= $roleLabel[$r] ?? $r ?>
                    </span>
                </div>
            </div>

            <?php $fields = [
                ['Username',    $cred['username'] ?? '-',  'person-fill',          'username'],
                ['Email',       $cred['email'] ?? '-',     'envelope-fill',        'email'],
                ['Password',    $cred['password'] ?? '-',  'key-fill',             'password'],
            ];
            if (!empty($cred['full_name'])) $fields[] = ['Nama Lengkap', $cred['full_name'], 'person-vcard-fill', null];
            if (!empty($cred['nis']))        $fields[] = ['NIS',          $cred['nis'],       'hash',              null];
            if (!empty($cred['nisn']))       $fields[] = ['NISN',         $cred['nisn'],      'hash',              null];
            if (!empty($cred['company_name'])) $fields[] = ['Perusahaan', $cred['company_name'], 'building-fill', null];
            foreach ($fields as [$label, $val, $icon, $copyId]): ?>
            <div class="row g-2 align-items-center py-2 border-top">
                <div class="col-sm-4 text-muted small fw-semibold">
                    <i class="bi bi-<?= $icon ?> me-2 text-primary"></i><?= $label ?>
                </div>
                <div class="col-sm-8 d-flex align-items-center gap-2">
                    <?php if ($label === 'Password'): ?>
                    <code id="val-password" class="fs-5 fw-bold text-danger px-3 py-1 rounded"
                          style="background:#fff5f5;letter-spacing:2px;"><?= e($val) ?></code>
                    <?php else: ?>
                    <span id="val-<?= $copyId ?? 'x' ?>" class="fw-semibold"><?= e($val) ?></span>
                    <?php endif; ?>
                    <?php if ($copyId): ?>
                    <button class="btn btn-sm btn-outline-secondary py-0 px-2 copy-btn"
                            data-target="val-<?= $copyId ?>" title="Salin">
                        <i class="bi bi-clipboard" style="font-size:.8rem"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Warning note -->
        <div class="alert alert-warning d-flex gap-3 mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
            <div class="small">
                <strong>Penting!</strong> Password sementara ini hanya ditampilkan <strong>sekali</strong>.
                Pengguna akan diminta mengganti password saat pertama login.
                Pastikan sudah dicatat atau diserahkan langsung ke pengguna.
            </div>
        </div>

        <!-- Action buttons -->
        <div class="d-flex gap-3 flex-wrap justify-content-between">
            <button onclick="printCredential()" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-2"></i>Cetak
            </button>
            <div class="d-flex gap-2">
                <button onclick="copyAll()" class="btn btn-primary">
                    <i class="bi bi-clipboard-check me-2"></i>Salin Semua
                </button>
                <a href="<?= APP_URL ?>/admin/users.php?action=create" class="btn btn-success">
                    <i class="bi bi-person-plus me-2"></i>Buat Akun Lagi
                </a>
            </div>
        </div>
    </div>
</div>

</div>
</div>

<!-- Print template (hidden) -->
<div id="printArea" class="d-none">
    <h2 style="font-family:sans-serif;border-bottom:2px solid #1e3a8a;padding-bottom:10px;color:#1e3a8a">
        LORDIK — Informasi Akun Baru
    </h2>
    <table style="font-family:sans-serif;width:100%;border-collapse:collapse;margin-top:20px">
        <tr style="background:#f1f5f9"><td style="padding:10px;border:1px solid #e2e8f0;font-weight:600">Role</td>
            <td style="padding:10px;border:1px solid #e2e8f0"><?= e(strtoupper($r)) ?></td></tr>
        <tr><td style="padding:10px;border:1px solid #e2e8f0;font-weight:600">Username</td>
            <td style="padding:10px;border:1px solid #e2e8f0"><?= e($cred['username'] ?? '') ?></td></tr>
        <tr style="background:#f1f5f9"><td style="padding:10px;border:1px solid #e2e8f0;font-weight:600">Email</td>
            <td style="padding:10px;border:1px solid #e2e8f0"><?= e($cred['email'] ?? '') ?></td></tr>
        <tr><td style="padding:10px;border:1px solid #e2e8f0;font-weight:600;color:#c00">Password</td>
            <td style="padding:10px;border:1px solid #e2e8f0;font-weight:700;font-size:1.2em;color:#c00;letter-spacing:2px"><?= e($cred['password'] ?? '') ?></td></tr>
        <?php if (!empty($cred['full_name'])): ?>
        <tr style="background:#f1f5f9"><td style="padding:10px;border:1px solid #e2e8f0;font-weight:600">Nama</td>
            <td style="padding:10px;border:1px solid #e2e8f0"><?= e($cred['full_name']) ?></td></tr>
        <?php endif; ?>
    </table>
    <p style="font-family:sans-serif;margin-top:20px;font-size:.85rem;color:#666">
        Dicetak: <?= date('d M Y H:i') ?> &nbsp;|&nbsp; Ganti password segera setelah login pertama.
    </p>
</div>

<script>
// Copy single field
document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const el = document.getElementById(this.dataset.target);
        if (el) {
            navigator.clipboard.writeText(el.textContent.trim()).then(() => {
                const icon = this.querySelector('i');
                icon.className = 'bi bi-clipboard-check text-success';
                setTimeout(() => icon.className = 'bi bi-clipboard', 1800);
            });
        }
    });
});

// Copy all credentials
function copyAll() {
    const text = [
        'Username : <?= addslashes($cred['username'] ?? '') ?>',
        'Email    : <?= addslashes($cred['email'] ?? '') ?>',
        'Password : <?= addslashes($cred['password'] ?? '') ?>',
        <?php if (!empty($cred['full_name'])): ?>'Nama     : <?= addslashes($cred['full_name']) ?>',<?php endif; ?>
        <?php if (!empty($cred['nis'])): ?>'NIS      : <?= addslashes($cred['nis']) ?>',<?php endif; ?>
    ].join('\n');
    navigator.clipboard.writeText(text).then(() => {
        const btn = event.target.closest('button');
        btn.innerHTML = '<i class="bi bi-clipboard-check me-2"></i>Tersalin!';
        btn.classList.replace('btn-primary', 'btn-success');
        setTimeout(() => {
            btn.innerHTML = '<i class="bi bi-clipboard-check me-2"></i>Salin Semua';
            btn.classList.replace('btn-success', 'btn-primary');
        }, 2000);
    });
}

function printCredential() {
    const printArea = document.getElementById('printArea');

    // Pindahkan ke body agar tidak di dalam #adminContent yang di-hide saat print
    document.body.appendChild(printArea);
    printArea.classList.remove('d-none');

    window.print();

    // Sembunyikan kembali setelah print selesai
    window.addEventListener('afterprint', function() {
        printArea.classList.add('d-none');
    }, { once: true });
}
</script>

<?php require_once BASE_PATH . '/app/Views/layouts/footer_admin.php'; ?>
