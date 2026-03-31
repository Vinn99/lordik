<?php
// public/company/vacancy/create.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireCompany();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $result = VacancyModel::submit(currentUserId(), $_POST);
    if ($result['success']) {
        setFlash('success', 'Lowongan berhasil diajukan dan sedang menunggu persetujuan admin.');
        redirect('/company/vacancy/list.php');
    } else {
        $errors = $result['errors'] ?? [$result['message'] ?? 'Gagal mengajukan lowongan.'];
    }
}

$jurusanList = ['RPL','DKV','AKL','MPK','BDP','LP3K','LPB','ULW'];
$pageTitle   = 'Ajukan Lowongan — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="mb-3"><?= backButton('/company/vacancy/list.php') ?></div>
<div class="row justify-content-center">
    <div class="col-lg-9">
        <h4 class="fw-bold mb-4"><i class="bi bi-plus-circle me-2 text-primary"></i>Ajukan Lowongan Baru</h4>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <?php if ($errors): ?>
                <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Judul Posisi <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required maxlength="200"
                                   value="<?= e($_POST['title'] ?? '') ?>" placeholder="Contoh: Programmer PHP Junior">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tipe Pekerjaan <span class="text-danger">*</span></label>
                            <select name="job_type" class="form-select" required>
                                <?php foreach (['full_time'=>'Full Time','part_time'=>'Part Time','contract'=>'Kontrak','internship'=>'Magang'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= ($_POST['job_type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Jurusan Diprioritaskan</label>
                            <select name="jurusan_required" class="form-select">
                                <option value="">Semua Jurusan</option>
                                <?php foreach ($jurusanList as $j): ?>
                                <option value="<?= $j ?>" <?= ($_POST['jurusan_required'] ?? '') === $j ? 'selected' : '' ?>><?= $j ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Kuota</label>
                            <input type="number" name="slots" class="form-control" min="1"
                                   value="<?= e($_POST['slots'] ?? 1) ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Gaji Minimum (Rp)</label>
                            <input type="number" name="salary_min" class="form-control" min="0"
                                   value="<?= e($_POST['salary_min'] ?? '') ?>" placeholder="0 = tidak dicantumkan">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Gaji Maksimum (Rp)</label>
                            <input type="number" name="salary_max" class="form-control" min="0"
                                   value="<?= e($_POST['salary_max'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Lokasi Kerja</label>
                            <input type="text" name="location" class="form-control"
                                   value="<?= e($_POST['location'] ?? '') ?>"
                                   placeholder="Kota / alamat kantor">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Deadline Lamaran</label>
                            <input type="date" name="deadline" class="form-control"
                                   min="<?= date('Y-m-d') ?>"
                                   value="<?= e($_POST['deadline'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Deskripsi Pekerjaan <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="6" required
                                      placeholder="Jelaskan secara detail tugas dan tanggung jawab posisi ini..."><?= e($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Persyaratan</label>
                            <textarea name="requirements" class="form-control" rows="5"
                                      placeholder="Daftar kualifikasi, keahlian, pengalaman yang dibutuhkan..."><?= e($_POST['requirements'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="alert alert-info small mt-3 mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Lowongan yang Anda ajukan akan ditinjau oleh admin sebelum ditampilkan ke alumni.
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-send me-2"></i>Ajukan Lowongan
                        </button>
                        <a href="<?= APP_URL ?>/company/vacancy/list.php" class="btn btn-outline-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
