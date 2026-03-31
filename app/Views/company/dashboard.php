<?php
// public/company/dashboard.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireCompany();

$profile      = CompanyModel::getProfile(currentUserId());
$hasProfile   = $profile !== null;

$vacancyStats = ['total' => 0, 'approved' => 0, 'submitted' => 0];
$recentApps   = [];

if ($hasProfile) {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT status, COUNT(*) as cnt FROM job_vacancies WHERE company_id = ? AND deleted_at IS NULL GROUP BY status"
    );
    $stmt->execute([$profile['id']]);
    foreach ($stmt->fetchAll() as $row) {
        $vacancyStats[$row['status']] = (int)$row['cnt'];
        $vacancyStats['total'] += (int)$row['cnt'];
    }

    $stmt2 = $pdo->prepare(
        "SELECT a.*, jv.title as vacancy_title, ap.full_name
         FROM applications a
         JOIN job_vacancies jv ON jv.id = a.vacancy_id
         JOIN alumni_profiles ap ON ap.id = a.alumni_id
         WHERE jv.company_id = ?
         ORDER BY a.applied_at DESC LIMIT 5"
    );
    $stmt2->execute([$profile['id']]);
    $recentApps = $stmt2->fetchAll();
}

$pageTitle = 'Dashboard Perusahaan — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<?php if (!$hasProfile): ?>
<div class="alert alert-warning border-start border-5 border-warning">
    <h6 class="mb-1">Profil Perusahaan Belum Lengkap</h6>
    <p class="mb-2 small">Lengkapi profil perusahaan Anda sebelum mengajukan lowongan.</p>
    <a href="<?= APP_URL ?>/company/profile.php" class="btn btn-sm btn-warning">Lengkapi Profil</a>
</div>
<?php endif; ?>

<!-- Welcome -->
<div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,#059669,#34d399)">
    <div class="card-body text-white p-3 p-md-4">
        <?= backButton() ?>
<h5 class="fw-bold mb-1 mt-1 text-truncate"><?= e($profile['company_name'] ?? currentUser()['username']) ?></h5>
        <p class="mb-0 opacity-75 small">
            <?= e($profile['industry'] ?? '') ?>
            <?php if ($profile['city']): ?><span class="d-none d-sm-inline">&nbsp;|&nbsp;</span><span class="d-block d-sm-inline"><?= e($profile['city']) ?></span><?php endif; ?>
        </p>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-4">
        <div class="card border-0 shadow-sm text-center p-2 p-md-3 h-100">
            <div class="h2 fw-bold text-primary mb-0"><?= $vacancyStats['total'] ?></div>
            <div class="text-muted small">Total Lowongan</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card border-0 shadow-sm text-center p-2 p-md-3 h-100">
            <div class="h2 fw-bold text-success mb-0"><?= $vacancyStats['approved'] ?? 0 ?></div>
            <div class="text-muted small">Aktif</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card border-0 shadow-sm text-center p-2 p-md-3 h-100">
            <div class="h2 fw-bold text-warning mb-0"><?= $vacancyStats['submitted'] ?? 0 ?></div>
            <div class="text-muted small">Menunggu Approve</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Applications -->
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-inbox me-2 text-primary"></i>Pelamar Terbaru</span>
                <a href="<?= APP_URL ?>/company/applications.php" class="text-primary small">Lihat semua</a>
            </div>
            <?php if ($recentApps): ?>
            <div class="table-wrap">
                <table class="table table-hover align-middle mb-0" style="min-width:440px">
                    <thead class="table-light"><tr>
                        <th>Nama</th><th>Posisi</th><th>Status</th><th class="d-none d-sm-table-cell">Tanggal</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($recentApps as $app): ?>
                        <tr>
                            <td class="fw-semibold small"><?= e($app['full_name']) ?></td>
                            <td class="small"><?= e($app['vacancy_title']) ?></td>
                            <td><?= statusBadge($app['status']) ?></td>
                            <td class="small text-muted d-none d-sm-table-cell"><?= formatDate($app['applied_at'], 'd M Y') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-body text-muted text-center py-4">Belum ada pelamar.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom fw-semibold">Aksi Cepat</div>
            <div class="card-body d-flex flex-column gap-2">
                <a href="<?= APP_URL ?>/company/vacancy/create.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Ajukan Lowongan Baru
                </a>
                <a href="<?= APP_URL ?>/company/vacancy/list.php" class="btn btn-outline-primary">
                    <i class="bi bi-briefcase me-2"></i>Kelola Lowongan
                </a>
                <a href="<?= APP_URL ?>/company/applications.php" class="btn btn-outline-success">
                    <i class="bi bi-people me-2"></i>Lihat Semua Pelamar
                </a>
                <a href="<?= APP_URL ?>/company/profile.php" class="btn btn-outline-secondary">
                    <i class="bi bi-building me-2"></i>Edit Profil Perusahaan
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
