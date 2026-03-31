<?php
// public/vacancy/detail.php — v2: applicant list for company, apply restriction UI
require_once BASE_PATH . '/helpers/notification_helper.php';

requireLogin();

$id      = (int)($_GET['id'] ?? 0);
$vacancy = VacancyModel::getById($id);

if (!$vacancy || ($vacancy['status'] !== 'approved' && !isAdmin() && !isCompany())) {
    http_response_code(404);
    setFlash('danger', 'Lowongan tidak ditemukan atau sudah tidak aktif.');
    redirect('/vacancy/list.php');
}

$pdo = getDB();
$profile        = null;
$alreadyApplied = false;
$hasActiveApp   = false;
$isEmployed     = false;
$canApply       = false;
$applyBlockMsg  = '';

if (isAlumni()) {
    $stP = $pdo->prepare("SELECT * FROM alumni_profiles WHERE user_id = ? LIMIT 1");
    $stP->execute([currentUserId()]);
    $profile = $stP->fetch();

    if ($profile) {
        // Check if already applied to THIS vacancy
        $stA = $pdo->prepare("SELECT id FROM applications WHERE vacancy_id = ? AND alumni_id = ? LIMIT 1");
        $stA->execute([$id, $profile['id']]);
        $alreadyApplied = (bool)$stA->fetch();

        // Check work status
        $isEmployed = $profile['work_status'] === 'employed';

        // Check active applications elsewhere
        $stAct = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE alumni_id = ? AND status IN ('pending','reviewed','shortlisted')");
        $stAct->execute([$profile['id']]);
        $hasActiveApp = (int)$stAct->fetchColumn() > 0;

        if ($isEmployed) {
            $applyBlockMsg = 'Anda sudah berstatus <strong>Bekerja</strong>. Tidak dapat melamar lowongan baru.';
        } elseif ($hasActiveApp && !$alreadyApplied) {
            $applyBlockMsg = 'Anda masih memiliki <strong>lamaran aktif</strong> yang sedang diproses. Tunggu hingga selesai atau batalkan sebelum melamar di tempat lain.';
        }

        $canApply = !$alreadyApplied && !$isEmployed && !$hasActiveApp;
    }
}

// Handle apply POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAlumni()) {
    validateCsrf();
    $result = ApplicationModel::apply(currentUserId(), $id, sanitize($_POST['cover_letter'] ?? ''));
    if ($result['success']) {
        setFlash('success', 'Lamaran berhasil dikirim! Pantau status di halaman Lamaran Saya.');
        redirect('/alumni/applications.php');
    } else {
        setFlash('danger', $result['message']);
    }
}

// Applicant list for company (only for their vacancy)
$applicants = [];
$isOwnVacancy = false;
if (isCompany()) {
    $stC = $pdo->prepare("SELECT c.id FROM companies c WHERE c.user_id = ? LIMIT 1");
    $stC->execute([currentUserId()]);
    $co = $stC->fetch();
    if ($co && (int)$vacancy['company_id'] === (int)$co['id']) {
        $isOwnVacancy = true;
        $stApps = $pdo->prepare(
            "SELECT a.*, ap.full_name, ap.photo_path, ap.gender, ap.birth_date, ap.jurusan
             FROM applications a
             JOIN alumni_profiles ap ON ap.id = a.alumni_id
             WHERE a.vacancy_id = ?
             ORDER BY FIELD(a.status,'shortlisted','pending','reviewed','accepted','rejected'), a.applied_at DESC"
        );
        $stApps->execute([$id]);
        $applicants = $stApps->fetchAll();
    }
}

// Applicant list for admin
if (isAdmin()) {
    $stApps = $pdo->prepare(
        "SELECT a.*, ap.full_name, ap.photo_path, ap.gender, ap.birth_date, ap.jurusan
         FROM applications a JOIN alumni_profiles ap ON ap.id = a.alumni_id
         WHERE a.vacancy_id = ?
         ORDER BY a.applied_at DESC"
    );
    $stApps->execute([$id]);
    $applicants = $stApps->fetchAll();
}

$deadline      = $vacancy['deadline'] ?? null;
$deadlinePassed = $deadline && strtotime($deadline) < strtotime('today');

$pageTitle = e($vacancy['title']) . ' — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<?= backButton('/vacancy/list.php') ?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= APP_URL ?>/vacancy/list.php">Lowongan</a></li>
        <li class="breadcrumb-item active"><?= e($vacancy['title']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <!-- MAIN CONTENT -->
    <div class="col-lg-8">

        <!-- Vacancy header card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-start gap-4 mb-3">
                    <?php if ($vacancy['logo_path']): ?>
                    <img src="<?= APP_URL ?>/files/serve.php?type=logo&path=<?= urlencode($vacancy['logo_path']) ?>"
                         alt="Logo" class="rounded" style="width:72px;height:72px;object-fit:contain;border:1px solid #e2e8f0;">
                    <?php else: ?>
                    <div class="bg-light rounded d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:72px;height:72px;min-width:72px;">
                        <i class="bi bi-building fs-1 text-secondary"></i>
                    </div>
                    <?php endif; ?>
                    <div class="flex-grow-1">
                        <h4 class="fw-bold mb-1"><?= e($vacancy['title']) ?></h4>
                        <h6 class="text-muted mb-2"><?= e($vacancy['company_name']) ?></h6>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-primary px-3 py-1">
                                <i class="bi bi-briefcase me-1"></i><?= ucwords(str_replace('_', ' ', $vacancy['job_type'])) ?>
                            </span>
                            <?php if ($vacancy['city']): ?>
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-geo-alt me-1"></i><?= e($vacancy['city']) ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($vacancy['jurusan_required']): ?>
                            <span class="badge bg-warning text-dark">
                                <i class="bi bi-mortarboard me-1"></i>Prioritas: <?= e($vacancy['jurusan_required']) ?>
                            </span>
                            <?php endif; ?>
                            <?php if ($vacancy['salary_min']): ?>
                            <span class="badge bg-success">
                                <i class="bi bi-cash me-1"></i><?= formatCurrency($vacancy['salary_min']) ?>
                                <?= $vacancy['salary_max'] ? ' – ' . formatCurrency($vacancy['salary_max']) : '+' ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($vacancy['description']): ?>
                <div class="mb-3">
                    <h6 class="fw-semibold mb-2"><i class="bi bi-file-text me-2 text-primary"></i>Deskripsi Pekerjaan</h6>
                    <div class="text-secondary" style="white-space:pre-wrap;line-height:1.7"><?= e($vacancy['description']) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($vacancy['requirements']): ?>
                <div>
                    <h6 class="fw-semibold mb-2"><i class="bi bi-list-check me-2 text-primary"></i>Persyaratan</h6>
                    <div class="text-secondary" style="white-space:pre-wrap;line-height:1.7"><?= e($vacancy['requirements']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Apply Form -->
        <?php if (isAlumni()): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom fw-semibold">
                <i class="bi bi-send me-2 text-primary"></i>Kirim Lamaran
            </div>
            <div class="card-body p-4">
                <?php if ($alreadyApplied): ?>
                <div class="alert alert-success d-flex gap-3 mb-0">
                    <i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>
                    <div>Anda sudah melamar posisi ini.
                        <a href="<?= APP_URL ?>/alumni/applications.php" class="alert-link">Pantau status lamaran →</a>
                    </div>
                </div>
                <?php elseif (!$profile): ?>
                <div class="alert alert-warning d-flex gap-3 mb-0">
                    <i class="bi bi-exclamation-circle-fill fs-5 flex-shrink-0"></i>
                    <div>Lengkapi profil terlebih dahulu.
                        <a href="<?= APP_URL ?>/alumni/profile.php" class="alert-link">Lengkapi Profil →</a>
                    </div>
                </div>
                <?php elseif ($deadlinePassed): ?>
                <div class="alert alert-danger d-flex gap-3 mb-0">
                    <i class="bi bi-x-circle-fill fs-5 flex-shrink-0"></i>
                    <div>Deadline lamaran sudah lewat.</div>
                </div>
                <?php elseif ($applyBlockMsg): ?>
                <div class="alert alert-warning d-flex gap-3 mb-0">
                    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                    <div><?= $applyBlockMsg ?>
                        <div class="mt-2">
                            <a href="<?= APP_URL ?>/alumni/applications.php" class="alert-link">Lihat lamaran aktif →</a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Surat Lamaran <span class="text-muted fw-normal small">(opsional tapi direkomendasikan)</span></label>
                        <textarea name="cover_letter" class="form-control" rows="5"
                                  placeholder="Perkenalkan diri Anda dan jelaskan mengapa cocok untuk posisi ini..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-2"></i>Kirim Lamaran
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Applicant list (company / admin) -->
        <?php if (($isOwnVacancy || isAdmin()) && !empty($applicants)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-people me-2 text-primary"></i>Pelamar (<?= count($applicants) ?>)</span>
                <?php if (isCompany()): ?>
                <a href="<?= APP_URL ?>/company/applications.php?vacancy_id=<?= $id ?>"
                   class="btn btn-sm btn-outline-primary">Lihat Semua →</a>
                <?php endif; ?>
            </div>
            <div class="card-body p-3">
                <div class="row g-2">
                    <?php
                    $statusColor = ['pending'=>'warning','reviewed'=>'info','shortlisted'=>'primary','accepted'=>'success','rejected'=>'danger'];
                    foreach ($applicants as $app):
                        $age = $app['birth_date'] ? (int)date_diff(new DateTime($app['birth_date']),new DateTime())->y : null;
                        $sc  = $statusColor[$app['status']] ?? 'secondary';
                        $detailUrl = isCompany()
                            ? APP_URL . '/company/applicant_profile.php?app_id=' . $app['id']
                            : APP_URL . '/alumni/application_detail.php?id='    . $app['id'];
                    ?>
                    <div class="col-sm-6 col-md-4">
                        <a href="<?= $detailUrl ?>" class="text-decoration-none">
                            <div class="border rounded-2 p-2 d-flex gap-2 align-items-center h-100"
                                 style="border-left:3px solid var(--bs-<?= $sc ?>)!important;transition:.15s"
                                 onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                                <div class="avatar-sm flex-shrink-0" style="width:38px;height:38px;min-width:38px">
                                    <?php if ($app['photo_path']): ?>
                                    <img src="<?= APP_URL ?>/files/serve.php?type=photo&path=<?= urlencode($app['photo_path']) ?>" alt="">
                                    <?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
                                </div>
                                <div class="flex-grow-1 min-width-0">
                                    <div class="fw-semibold small text-dark text-truncate"><?= e($app['full_name']) ?></div>
                                    <div class="text-muted" style="font-size:.72rem">
                                        <?= e($app['jurusan']) ?>
                                        <?php if ($age): ?> · <?= $age ?>th<?php endif; ?>
                                    </div>
                                    <?= statusBadge($app['status']) ?>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php elseif (($isOwnVacancy || isAdmin()) && empty($applicants)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>Belum ada pelamar untuk lowongan ini.
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- SIDEBAR -->
    <div class="col-lg-4">

        <!-- Info card -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom fw-semibold small">
                <i class="bi bi-info-circle me-2 text-primary"></i>Informasi Lowongan
            </div>
            <div class="card-body py-2 px-3">
                <div class="table-wrap">
<table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted small">Lokasi</td>
                        <td class="small fw-semibold"><?= e($vacancy['location'] ?: $vacancy['city'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Jurusan</td>
                        <td class="small fw-semibold"><?= e($vacancy['jurusan_required'] ?: 'Semua Jurusan') ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Kuota</td>
                        <td class="small fw-semibold"><?= e($vacancy['slots'] ?? '-') ?> orang</td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Tipe</td>
                        <td class="small fw-semibold"><?= ucwords(str_replace('_',' ',$vacancy['job_type'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Deadline</td>
                        <td class="small fw-semibold <?= $deadlinePassed ? 'text-danger' : '' ?>">
                            <?php if ($deadline): ?>
                            <?= formatDate($deadline, 'd M Y') ?>
                            <?php if ($deadlinePassed): ?><span class="badge bg-danger ms-1">Lewat</span><?php endif; ?>
                            <?php else: ?>Tidak ada deadline<?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Diposting</td>
                        <td class="small"><?= formatDate($vacancy['created_at'], 'd M Y') ?></td>
                    </tr>
                    <?php if (!empty($applicants)): ?>
                    <tr>
                        <td class="text-muted small">Pelamar</td>
                        <td class="small fw-semibold"><?= count($applicants) ?> orang</td>
                    </tr>
                    <?php endif; ?>
                </table>
</div>
            </div>
        </div>

        <!-- Apply eligibility hint for alumni -->
        <?php if (isAlumni() && $profile && !$alreadyApplied && !$deadlinePassed): ?>
        <div class="card border-0 <?= $canApply ? 'border-success' : 'border-warning' ?> shadow-sm mb-3"
             style="border:1px solid <?= $canApply ? '#22c55e' : '#f59e0b' ?>!important">
            <div class="card-body py-3 px-3">
                <?php if ($canApply): ?>
                <div class="d-flex gap-2 align-items-center">
                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                    <div class="small"><strong class="text-success">Anda bisa melamar!</strong><br>
                        <span class="text-muted">Status & lamaran aktif memenuhi syarat.</span>
                    </div>
                </div>
                <?php else: ?>
                <div class="d-flex gap-2 align-items-start">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-5 flex-shrink-0"></i>
                    <div class="small"><strong>Tidak dapat melamar</strong><br>
                        <span class="text-muted"><?= strip_tags($applyBlockMsg) ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
