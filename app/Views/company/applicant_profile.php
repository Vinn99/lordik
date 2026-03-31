<?php
// public/company/applicant_profile.php
// Perusahaan dapat melihat profil lengkap pelamar yang apply ke lowongan mereka
require_once BASE_PATH . '/helpers/notification_helper.php';

requireCompany();

$appId   = (int)($_GET['app_id'] ?? 0);
$pdo     = getDB();

// Verify this application belongs to company's vacancy
$stmt = $pdo->prepare(
    "SELECT a.*, ap.*, u.email, u.username,
            jv.title as vacancy_title, jv.company_id,
            c.id as my_company_id
     FROM applications a
     JOIN alumni_profiles ap ON ap.id = a.alumni_id
     JOIN users u ON u.id = ap.user_id
     JOIN job_vacancies jv ON jv.id = a.vacancy_id
     JOIN companies c ON c.user_id = ?
     WHERE a.id = ? AND jv.company_id = c.id
     LIMIT 1"
);
$stmt->execute([currentUserId(), $appId]);
$data = $stmt->fetch();

if (!$data) {
    setFlash('danger', 'Data tidak ditemukan atau Anda tidak memiliki akses.');
    redirect('/company/applications.php');
}

// Get certificates
$certStmt = $pdo->prepare("SELECT * FROM alumni_certificates WHERE alumni_id = ? ORDER BY issued_date DESC");
$certStmt->execute([$data['id']]);
$certificates = $certStmt->fetchAll();

// Calculate age
$age = null;
if ($data['birth_date']) {
    $age = (int)date_diff(new DateTime($data['birth_date']), new DateTime())->y;
}

$pageTitle = e($data['full_name']) . ' — Profil Pelamar';
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-4">
    <?= backButton('/company/applications.php') ?>
    <div>
        <h4 class="fw-bold mb-0">Profil Pelamar</h4>
        <div class="text-muted small">Melamar untuk: <strong><?= e($data['vacancy_title']) ?></strong></div>
    </div>
</div>

<div class="row g-4">

    <!-- LEFT: Profile card -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-4 text-center">
                <!-- Photo -->
                <div class="avatar-circle-lg mx-auto mb-3">
                    <?php if ($data['photo_path']): ?>
                    <img src="<?= APP_URL ?>/files/serve.php?type=photo&path=<?= urlencode($data['photo_path']) ?>" alt="Foto">
                    <?php else: ?>
                    <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </div>
                <h5 class="fw-bold mb-1"><?= e($data['full_name']) ?></h5>
                <div class="text-muted small mb-2"><?= e($data['jurusan']) ?> · Angkatan <?= e($data['graduation_year']) ?></div>
                <div class="d-flex justify-content-center gap-2 flex-wrap mb-3">
                    <span class="badge <?= $data['gender']==='female'?'bg-pink-subtle text-pink border border-pink':'bg-info-subtle text-info border border-info-subtle' ?>" style="<?= $data['gender']==='female'?'background:#fdf2f8;color:#c026d3;border-color:#e879f9!important':'' ?>">
                        <i class="bi bi-<?= $data['gender']==='female'?'gender-female':'gender-male' ?> me-1"></i>
                        <?= $data['gender']==='female'?'Perempuan':'Laki-laki' ?>
                    </span>
                    <?php if ($age): ?>
                    <span class="badge bg-light text-dark border"><i class="bi bi-calendar3 me-1"></i><?= $age ?> tahun</span>
                    <?php endif; ?>
                    <?= statusBadge($data['work_status']) ?>
                </div>
                <!-- Status lamaran -->
                <div class="border-top pt-3">
                    <div class="text-muted small mb-1">Status Lamaran</div>
                    <?= statusBadge($data['status']) ?>
                </div>
            </div>
        </div>

        <!-- Contact info -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom fw-semibold small"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Kontak</div>
            <div class="card-body py-2 px-3">
                <div class="table-wrap">
<table class="table table-sm table-borderless mb-0">
                    <?php if ($data['phone']): ?>
                    <tr>
                        <td class="text-muted small" style="width:90px"><i class="bi bi-telephone me-1"></i>Telepon</td>
                        <td class="small fw-semibold"><?= e($data['phone']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted small"><i class="bi bi-envelope me-1"></i>Email</td>
                        <td class="small fw-semibold"><?= e($data['email']) ?></td>
                    </tr>
                    <?php if ($data['address']): ?>
                    <tr>
                        <td class="text-muted small"><i class="bi bi-geo-alt me-1"></i>Alamat</td>
                        <td class="small"><?= e($data['address']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($data['birth_date']): ?>
                    <tr>
                        <td class="text-muted small"><i class="bi bi-calendar me-1"></i>Lahir</td>
                        <td class="small"><?= formatDate($data['birth_date'], 'd M Y') ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
</div>
            </div>
        </div>

        <!-- CV Download -->
        <?php if ($data['cv_path']): ?>
        <a href="<?= APP_URL ?>/files/serve.php?type=cv&path=<?= urlencode($data['cv_path']) ?>"
           target="_blank" class="btn btn-danger w-100 mb-3">
            <i class="bi bi-file-earmark-pdf me-2"></i>Download CV / Resume
        </a>
        <?php endif; ?>

        <!-- Chat button -->
        <a href="<?= APP_URL ?>/alumni/application_detail.php?id=<?= $appId ?>"
           class="btn btn-outline-primary w-100">
            <i class="bi bi-chat-dots me-2"></i>Buka Chat & Update Status
        </a>
    </div>

    <!-- RIGHT: Detail -->
    <div class="col-lg-8">

        <!-- Bio / Skills -->
        <?php if ($data['bio'] || $data['skills']): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom fw-semibold small"><i class="bi bi-person-badge me-2 text-info"></i>Tentang Pelamar</div>
            <div class="card-body p-4">
                <?php if ($data['bio']): ?>
                <p class="text-secondary mb-3" style="white-space:pre-wrap"><?= e($data['bio']) ?></p>
                <?php endif; ?>
                <?php if ($data['skills']): ?>
                <div>
                    <div class="fw-semibold small mb-2">Skills</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach (explode(',', $data['skills']) as $skill): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle px-3 py-1">
                            <?= e(trim($skill)) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cover letter -->
        <?php if ($data['cover_letter']): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom fw-semibold small"><i class="bi bi-envelope-open me-2 text-success"></i>Surat Lamaran</div>
            <div class="card-body p-4">
                <div class="text-secondary" style="white-space:pre-wrap;line-height:1.7"><?= e($data['cover_letter']) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Certificates -->
        <?php if ($certificates): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-bottom fw-semibold small">
                <i class="bi bi-award me-2 text-warning"></i>Sertifikat (<?= count($certificates) ?>)
            </div>
            <div class="card-body p-3">
                <div class="row g-2">
                    <?php foreach ($certificates as $cert): ?>
                    <div class="col-sm-6">
                        <div class="border rounded-2 p-3 d-flex align-items-start gap-3 h-100">
                            <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0"
                                 style="width:36px;height:36px">
                                <i class="bi bi-award-fill text-warning"></i>
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <div class="fw-semibold small"><?= e($cert['cert_name']) ?></div>
                                <div class="text-muted" style="font-size:.75rem"><?= e($cert['issuer']) ?></div>
                                <?php if ($cert['issued_date']): ?>
                                <div class="text-muted" style="font-size:.72rem"><?= formatDate($cert['issued_date'], 'M Y') ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if ($cert['file_path']): ?>
                            <a href="<?= APP_URL ?>/files/serve.php?type=cert&path=<?= urlencode($cert['file_path']) ?>"
                               target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2 flex-shrink-0">
                                <i class="bi bi-eye" style="font-size:.8rem"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Academic info -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom fw-semibold small"><i class="bi bi-book me-2 text-primary"></i>Data Akademis</div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-sm-6 col-md-4">
                        <div class="text-muted small mb-1">NIS</div>
                        <div class="fw-semibold"><?= e($data['nis']) ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="text-muted small mb-1">NISN</div>
                        <div class="fw-semibold"><?= e($data['nisn']) ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="text-muted small mb-1">Jurusan</div>
                        <div class="fw-semibold"><?= e($data['jurusan']) ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="text-muted small mb-1">Tahun Lulus</div>
                        <div class="fw-semibold"><?= e($data['graduation_year']) ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="text-muted small mb-1">Status Kerja</div>
                        <?= statusBadge($data['work_status']) ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
