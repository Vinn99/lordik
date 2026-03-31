<?php
// public/alumni/dashboard.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireAlumni();

$profile    = AlumniModel::getProfile(currentUserId());
$hasProfile = $profile !== null;

// Get recent applications
$applications = [];
if ($hasProfile) {
    $applications = AlumniModel::getApplicationHistory(currentUserId());
}

// Get latest vacancies
$latestVacancies = VacancyModel::list(['status' => 'approved'], 6, 0);

$pageTitle = 'Dashboard Alumni — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<?php if (!$hasProfile): ?>
<div class="alert alert-warning border-start border-5 border-warning shadow-sm">
    <div class="d-flex align-items-center gap-3">
        <i class="bi bi-exclamation-circle-fill fs-3 text-warning"></i>
        <div>
            <h6 class="mb-1">Profil Belum Lengkap</h6>
            <p class="mb-2 small">Lengkapi profil Anda untuk mulai melamar lowongan pekerjaan.</p>
            <a href="<?= APP_URL ?>/alumni/profile.php" class="btn btn-sm btn-warning fw-semibold">
                <i class="bi bi-person-plus me-1"></i>Lengkapi Profil Sekarang
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Welcome Card -->
<div class="card border-0 shadow-sm mb-4" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6)">
    <div class="card-body text-white p-3 p-md-4 d-flex align-items-center gap-3 flex-wrap flex-sm-nowrap">
        <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:56px;height:56px;min-width:56px;">
            <i class="bi bi-person-fill fs-2"></i>
        </div>
        <div class="min-width-0">
            <h5 class="mb-1 fw-bold text-truncate">Selamat datang, <?= e($profile['full_name'] ?? currentUser()['username']) ?>!</h5>
            <p class="mb-0 opacity-75 small">
                <?php if ($profile): ?>
                    <?= e($profile['jurusan']) ?> — Angkatan <?= e($profile['graduation_year']) ?>
                    <span class="d-none d-sm-inline">&nbsp;|&nbsp;</span>
                    <span class="d-block d-sm-inline">Status: <strong><?= workStatusLabel($profile['work_status']) ?></strong></span>
                <?php else: ?>
                    Akun Alumni LORDIK
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<!-- Stats -->
<?php if ($hasProfile): ?>
<div class="row g-3 mb-4">
    <?php
    $statusCounts = array_count_values(array_column($applications, 'status'));
    $total        = count($applications);
    $accepted     = $statusCounts['accepted'] ?? 0;
    $pending      = $statusCounts['pending'] ?? 0;
    ?>
    <div class="col-4">
        <div class="card border-0 shadow-sm text-center p-2 p-md-3 h-100">
            <div class="h2 fw-bold text-primary mb-0"><?= $total ?></div>
            <div class="text-muted small">Total Lamaran</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card border-0 shadow-sm text-center p-2 p-md-3 h-100">
            <div class="h2 fw-bold text-warning mb-0"><?= $pending ?></div>
            <div class="text-muted small">Menunggu</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card border-0 shadow-sm text-center p-2 p-md-3 h-100">
            <div class="h2 fw-bold text-success mb-0"><?= $accepted ?></div>
            <div class="text-muted small">Diterima</div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Recent Applications -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-file-earmark-text me-2 text-primary"></i>Lamaran Terbaru</span>
                <a href="<?= APP_URL ?>/alumni/applications.php" class="text-primary small">Lihat semua</a>
            </div>
            <div class="list-group list-group-flush">
                <?php if ($applications): ?>
                    <?php foreach (array_slice($applications, 0, 5) as $app): ?>
                    <a href="<?= APP_URL ?>/alumni/application_detail.php?id=<?= $app['id'] ?>"
                       class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold small"><?= e($app['vacancy_title']) ?></div>
                                <div class="text-muted" style="font-size:.8rem"><?= e($app['company_name']) ?></div>
                            </div>
                            <?= statusBadge($app['status']) ?>
                        </div>
                        <div class="text-muted mt-1" style="font-size:.75rem"><?= formatDate($app['applied_at'], 'd M Y') ?></div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="list-group-item text-muted text-center py-4">
                    <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                    Belum ada lamaran. <a href="<?= APP_URL ?>/vacancy/list.php">Cari lowongan</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Latest Vacancies -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-briefcase me-2 text-success"></i>Lowongan Terbaru</span>
                <a href="<?= APP_URL ?>/vacancy/list.php" class="text-primary small">Lihat semua</a>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($latestVacancies['data'] as $v): ?>
                <a href="<?= APP_URL ?>/vacancy/detail.php?id=<?= $v['id'] ?>"
                   class="list-group-item list-group-item-action">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-semibold small"><?= e($v['title']) ?></div>
                            <div class="text-muted" style="font-size:.8rem"><?= e($v['company_name']) ?> — <?= e($v['city']) ?></div>
                        </div>
                        <span class="badge bg-light text-dark"><?= e(str_replace('_', ' ', $v['job_type'])) ?></span>
                    </div>
                    <?php if ($v['deadline']): ?>
                    <div class="text-muted mt-1" style="font-size:.75rem">
                        <i class="bi bi-calendar-event"></i> Deadline: <?= formatDate($v['deadline']) ?>
                    </div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
                <?php if (empty($latestVacancies['data'])): ?>
                <div class="list-group-item text-muted text-center py-4">Belum ada lowongan aktif</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
