<?php
// public/alumni/profile.php
require_once BASE_PATH . '/helpers/upload_helper.php';
require_once BASE_PATH . '/helpers/notification_helper.php';

requireAlumni();

$errors  = [];
$profile = AlumniModel::getProfile(currentUserId());

// Handle profile form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['_action'] ?? 'save_profile';

    if ($action === 'save_profile') {
        $result = AlumniModel::createOrUpdateProfile(currentUserId(), $_POST);
        if ($result['success']) {
            setFlash('success', 'Profil berhasil disimpan.');
            redirect('/alumni/profile.php');
        } else {
            $errors = $result['errors'] ?? [];
        }
    }

    if ($action === 'upload_photo' && isset($_FILES['photo_file'])) {
        $result = AlumniModel::uploadPhoto(currentUserId(), $_FILES['photo_file']);
        if ($result['success']) {
            setFlash('success', 'Foto profil berhasil diupload.');
        } else {
            setFlash('danger', $result['message']);
        }
        redirect('/alumni/profile.php');
    }

    if ($action === 'upload_cv' && isset($_FILES['cv_file'])) {
        $result = AlumniModel::uploadCV(currentUserId(), $_FILES['cv_file']);
        if ($result['success']) {
            setFlash('success', 'CV berhasil diupload.');
        } else {
            setFlash('danger', $result['message']);
        }
        redirect('/alumni/profile.php');
    }

    if ($action === 'upload_cert') {
        $result = AlumniModel::uploadCertificate(
            currentUserId(),
            $_FILES['cert_file'],
            $_POST['cert_name'] ?? '',
            $_POST['cert_issuer'] ?? '',
            $_POST['cert_date'] ?? ''
        );
        if ($result['success']) {
            setFlash('success', 'Sertifikat berhasil diupload.');
        } else {
            setFlash('danger', $result['message']);
        }
        redirect('/alumni/profile.php');
    }

    if ($action === 'delete_cert') {
        $certId = (int)($_POST['cert_id'] ?? 0);
        $result = AlumniModel::deleteCertificate($certId, currentUserId());
        if ($result['success']) {
            setFlash('success', 'Sertifikat dihapus.');
        } else {
            setFlash('danger', $result['message']);
        }
        redirect('/alumni/profile.php');
    }

    if ($action === 'update_status') {
        $status = $_POST['work_status'] ?? '';
        AlumniModel::updateWorkStatus(currentUserId(), $status);
        setFlash('success', 'Status kerja diperbarui.');
        redirect('/alumni/profile.php');
    }
}

$profile = AlumniModel::getProfile(currentUserId()); // reload
$certs   = $profile ? AlumniModel::getCertificates($profile['id']) : [];

$jurusanList = ['RPL','DKV','AKL','MPK','BDP','LP3K','LPB','ULW'];

$pageTitle = 'Profil Alumni — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">

        <div class="d-flex align-items-center gap-3 mb-4">
            <button onclick="history.back()" class="btn-back"><i class="bi bi-arrow-left"></i> Kembali</button>
            <h4 class="fw-bold mb-0"><i class="bi bi-person me-2 text-primary"></i>Profil Saya</h4>
        </div>

        <!-- Foto Profil Card (#2) -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <!-- Avatar besar -->
                    <div class="avatar-circle-lg flex-shrink-0">
                        <?php if ($profile && $profile['photo_path']): ?>
                        <img src="<?= APP_URL ?>/files/serve.php?type=photo&path=<?= urlencode($profile['photo_path']) ?>" alt="Foto Profil">
                        <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold fs-5"><?= e($profile['full_name'] ?? 'Nama belum diisi') ?></div>
                        <?php if ($profile): ?>
                        <div class="text-muted small"><?= e($profile['jurusan']) ?> · Angkatan <?= e($profile['graduation_year']) ?></div>
                        <div class="mt-1"><?= statusBadge($profile['work_status']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($profile): ?>
                    <div>
                        <form method="POST" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                            <?= csrfField() ?>
                            <input type="hidden" name="_action" value="upload_photo">
                            <input type="file" name="photo_file" id="photoInput" class="d-none" accept=".jpg,.jpeg,.png,.webp"
                                   onchange="this.form.submit()">
                            <label for="photoInput" class="btn btn-sm btn-outline-primary mb-0" style="cursor:pointer">
                                <i class="bi bi-camera me-1"></i><?= $profile['photo_path'] ? 'Ganti Foto' : 'Upload Foto' ?>
                            </label>
                        </form>
                        <div class="text-muted mt-1" style="font-size:.7rem">JPG/PNG/WebP, maks 2MB</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Profile Info Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom fw-semibold">
                <i class="bi bi-person-lines-fill me-2 text-primary"></i>Data Diri
            </div>
            <div class="card-body p-4">
                <?php if ($errors): ?>
                <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="_action" value="save_profile">

                    <div class="row g-3">
                        <!-- NIS / NISN (only if no profile yet) -->
                        <?php if (!$profile): ?>
                        <div class="col-md-6">
                            <label class="form-label">NIS <span class="text-danger">*</span></label>
                            <input type="text" name="nis" class="form-control" required maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">NISN <span class="text-danger">*</span></label>
                            <input type="text" name="nisn" class="form-control" required maxlength="20">
                        </div>
                        <?php else: ?>
                        <div class="col-md-6">
                            <label class="form-label">NIS</label>
                            <input type="text" class="form-control" value="<?= e($profile['nis']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">NISN</label>
                            <input type="text" class="form-control" value="<?= e($profile['nisn']) ?>" disabled>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-8">
                            <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required
                                   value="<?= e($profile['full_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jenis Kelamin <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="">Pilih...</option>
                                <option value="male"   <?= ($profile['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="female" <?= ($profile['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Lahir</label>
                            <input type="date" name="birth_date" class="form-control"
                                   value="<?= e($profile['birth_date'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">No. HP</label>
                            <input type="text" name="phone" class="form-control" maxlength="20"
                                   value="<?= e($profile['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jurusan <span class="text-danger">*</span></label>
                            <select name="jurusan" class="form-select" required>
                                <option value="">Pilih Jurusan...</option>
                                <?php foreach ($jurusanList as $j): ?>
                                <option value="<?= $j ?>" <?= ($profile['jurusan'] ?? '') === $j ? 'selected' : '' ?>><?= $j ?></option>
                                <?php endforeach; ?>
                                <option value="Lainnya" <?= ($profile['jurusan'] ?? '') === 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tahun Lulus <span class="text-danger">*</span></label>
                            <input type="number" name="graduation_year" class="form-control"
                                   min="2000" max="<?= date('Y') + 1 ?>"
                                   value="<?= e($profile['graduation_year'] ?? date('Y')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status Pekerjaan <span class="text-danger">*</span></label>
                            <select name="work_status" class="form-select" required>
                                <?php foreach (['unemployed','employed','entrepreneur','continuing_edu'] as $ws): ?>
                                <option value="<?= $ws ?>" <?= ($profile['work_status'] ?? 'unemployed') === $ws ? 'selected' : '' ?>>
                                    <?= workStatusLabel($ws) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alamat</label>
                            <textarea name="address" class="form-control" rows="2"><?= e($profile['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Skill / Kompetensi</label>
                            <input type="text" name="skills" class="form-control"
                                   placeholder="Contoh: PHP, Laravel, MySQL"
                                   value="<?= e($profile['skills'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bio Singkat</label>
                            <textarea name="bio" class="form-control" rows="2"
                                      placeholder="Ceritakan sedikit tentang diri Anda..."><?= e($profile['bio'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i>Simpan Profil
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($profile): ?>

        <!-- CV Upload -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom fw-semibold">
                <i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Curriculum Vitae (CV)
            </div>
            <div class="card-body">
                <?php if ($profile['cv_path']): ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <i class="bi bi-file-earmark-pdf-fill text-danger fs-2"></i>
                    <div>
                        <div class="fw-semibold">CV tersedia</div>
                        <a href="<?= APP_URL ?>/files/serve.php?type=cv&path=<?= urlencode($profile['cv_path']) ?>"
                           target="_blank" class="small text-primary">Lihat CV</a>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-muted small mb-3">Belum ada CV. Upload CV Anda (PDF, maks 5MB).</p>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="_action" value="upload_cv">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <input type="file" name="cv_file" class="form-control" accept=".pdf" required>
                            <div class="form-text">Format PDF, maksimal 5 MB</div>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="bi bi-upload me-1"></i>
                                <?= $profile['cv_path'] ? 'Ganti CV' : 'Upload CV' ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Certificates -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-award me-2 text-warning"></i>Sertifikat & Penghargaan</span>
                <button class="btn btn-sm btn-warning" data-bs-toggle="collapse" data-bs-target="#certForm">
                    <i class="bi bi-plus me-1"></i>Tambah
                </button>
            </div>
            <div class="card-body">
                <!-- Add Cert Form (collapsed) -->
                <div class="collapse mb-4" id="certForm">
                    <div class="card card-body bg-light">
                        <form method="POST" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <input type="hidden" name="_action" value="upload_cert">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nama Sertifikat <span class="text-danger">*</span></label>
                                    <input type="text" name="cert_name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Penerbit</label>
                                    <input type="text" name="cert_issuer" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tanggal Terbit</label>
                                    <input type="date" name="cert_date" class="form-control">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">File Sertifikat <span class="text-danger">*</span></label>
                                    <input type="file" name="cert_file" class="form-control"
                                           accept=".pdf,.jpg,.jpeg,.png" required>
                                    <div class="form-text">PDF/JPG/PNG, maks 2MB</div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-warning btn-sm">Upload Sertifikat</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Cert List -->
                <?php if ($certs): ?>
                <div class="row g-2">
                    <?php foreach ($certs as $cert): ?>
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold small"><?= e($cert['cert_name']) ?></div>
                                    <div class="text-muted" style="font-size:.8rem">
                                        <?= e($cert['issuer'] ?: '-') ?> — <?= formatDate($cert['issued_date']) ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="<?= APP_URL ?>/files/serve.php?type=cert&path=<?= urlencode($cert['file_path']) ?>"
                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <form method="POST" onsubmit="return confirm('Hapus sertifikat ini?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="_action" value="delete_cert">
                                        <input type="hidden" name="cert_id" value="<?= $cert['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted small mb-0">Belum ada sertifikat.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
