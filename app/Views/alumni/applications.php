<?php
/**
 * app/Views/alumni/applications.php — Tim: Frontend
 * Riwayat & status semua lamaran alumni, dengan logika frozen
 */
requireAlumni();

$applications = ApplicationModel::listByAlumni(currentUserId());

// Kelompokkan
$active    = array_filter($applications, fn($a) => in_array($a['status'],['reviewed','shortlisted']));
$pending   = array_filter($applications, fn($a) => $a['status']==='pending');
$frozen    = array_filter($applications, fn($a) => $a['status']==='frozen');
$closed    = array_filter($applications, fn($a) => in_array($a['status'],['accepted','rejected']));

$hasActive = !empty($active);
$pageTitle = 'Lamaran Saya — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <?= backButton('/alumni/dashboard.php') ?>
    <div>
        <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2 text-primary"></i>Lamaran Saya</h4>
        <div class="text-muted small"><?= count($applications) ?> total lamaran</div>
    </div>
</div>

<?php if ($hasActive): ?>
<!-- Banner: ada lamaran yg sedang diproses -->
<div class="alert d-flex gap-3 mb-4 border-0" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border-left:4px solid #2563eb!important;border-radius:12px">
    <i class="bi bi-hourglass-split text-primary fs-4 flex-shrink-0"></i>
    <div>
        <div class="fw-semibold text-primary">Lamaran Sedang Diproses</div>
        <div class="small text-secondary mt-1">
            Anda memiliki lamaran yang sedang ditinjau atau masuk shortlist.
            Lamaran lain ditangguhkan sementara. Lamaran baru bisa dikirim kembali setelah status diperbarui.
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($applications)): ?>
<div class="text-center py-5">
    <i class="bi bi-inbox display-4 text-muted d-block mb-3"></i>
    <h5 class="text-muted">Belum ada lamaran</h5>
    <p class="text-muted small">Temukan lowongan yang sesuai dan mulai lamar sekarang!</p>
    <a href="<?= APP_URL ?>/vacancy/list.php" class="btn btn-primary mt-2">
        <i class="bi bi-search me-2"></i>Cari Lowongan
    </a>
</div>
<?php else: ?>

<?php
// Render group
function renderAppCards(array $apps, string $groupTitle, string $icon, string $color): void {
    if (empty($apps)) return;
    $statusConfig = [
        'pending'     => ['warning','text-dark','clock',           'Pending — Menunggu tinjauan'],
        'frozen'      => ['secondary','','pause-circle',           'Ditangguhkan sementara'],
        'reviewed'    => ['info','','eye',                         'Sedang Ditinjau'],
        'shortlisted' => ['primary','','star-fill',                'Masuk Shortlist! 🌟'],
        'accepted'    => ['success','','check-circle-fill',        'Diterima! 🎉'],
        'rejected'    => ['danger','','x-circle-fill',             'Tidak Diterima'],
    ];
    ?>
    <div class="mb-4">
        <h6 class="fw-semibold text-<?= $color ?> mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-<?= $icon ?>"></i><?= $groupTitle ?>
            <span class="badge bg-<?= $color ?> bg-opacity-20 text-<?= $color ?> ms-1"><?= count($apps) ?></span>
        </h6>
        <div class="row g-3">
        <?php foreach ($apps as $app):
            [$bg,$tc,$ic,$desc] = $statusConfig[$app['status']] ?? ['secondary','','circle',''];
            $isFrozen = $app['status'] === 'frozen';
            $deadlinePast = $app['vacancy_deadline'] && strtotime($app['vacancy_deadline']) < strtotime('today');
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100 <?= $isFrozen ? 'opacity-75' : '' ?>"
                 style="border-top:3px solid var(--bs-<?= $bg ?>)!important;border-radius:12px">
                <div class="card-body p-3">
                    <!-- Logo + judul -->
                    <div class="d-flex gap-3 align-items-start mb-2">
                        <div class="rounded-2 bg-light d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:40px;height:40px;min-width:40px">
                            <?php if ($app['logo_path']): ?>
                            <img src="<?= APP_URL ?>/files/serve.php?type=logo&path=<?= urlencode($app['logo_path']) ?>"
                                 style="width:36px;height:36px;object-fit:contain;border-radius:6px" alt="">
                            <?php else: ?>
                            <i class="bi bi-building text-secondary"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1 min-width-0">
                            <div class="fw-semibold small text-truncate"><?= e($app['vacancy_title']) ?></div>
                            <div class="text-muted" style="font-size:.75rem"><?= e($app['company_name']) ?></div>
                        </div>
                    </div>

                    <!-- Status badge + desc -->
                    <div class="mb-2">
                        <?= statusBadge($app['status']) ?>
                        <?php if (!$isFrozen): ?>
                        <div class="text-muted mt-1" style="font-size:.72rem"><?= $desc ?></div>
                        <?php else: ?>
                        <div class="frozen-notice mt-1">
                            <i class="bi bi-pause-circle me-1"></i>
                            Ditangguhkan — menunggu hasil lamaran lain yang sedang diproses
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Meta -->
                    <div class="text-muted d-flex gap-3 flex-wrap" style="font-size:.72rem">
                        <span><i class="bi bi-clock me-1"></i><?= formatDate($app['applied_at'],'d M Y') ?></span>
                        <?php if ($app['city']): ?>
                        <span><i class="bi bi-geo-alt me-1"></i><?= e($app['city']) ?></span>
                        <?php endif; ?>
                        <?php if ($deadlinePast && !in_array($app['status'],['accepted','rejected'])): ?>
                        <span class="text-danger fw-semibold"><i class="bi bi-alarm me-1"></i>Deadline lewat</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
                    <a href="<?= APP_URL ?>/alumni/application_detail.php?id=<?= $app['id'] ?>"
                       class="btn btn-sm btn-outline-primary w-100">
                        <i class="bi bi-eye me-1"></i>Detail & Chat
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php
}

renderAppCards(array_values($active),  'Sedang Diproses',  'hourglass-split', 'primary');
renderAppCards(array_values($pending), 'Menunggu Tinjauan','clock',           'warning');
renderAppCards(array_values($frozen),  'Ditangguhkan',     'pause-circle',    'secondary');
renderAppCards(array_values($closed),  'Selesai',          'archive',         'dark');
?>
<?php endif; ?>

<div class="mt-4">
    <a href="<?= APP_URL ?>/vacancy/list.php" class="btn btn-outline-primary">
        <i class="bi bi-plus-circle me-2"></i>Lamar Lowongan Lain
    </a>
</div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
