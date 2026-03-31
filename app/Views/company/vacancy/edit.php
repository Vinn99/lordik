<?php
// public/company/vacancy/edit.php

requireCompany();

$vacancyId = (int)($_GET['id'] ?? 0);
$vacancy   = VacancyModel::getById($vacancyId);

if (!$vacancy) {
    setFlash('danger', 'Lowongan tidak ditemukan.');
    redirect('/company/vacancy/list.php');
}

// Authorization: must be company's own vacancy
$pdo  = getDB();
$stmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$stmt->execute([currentUserId()]);
$company = $stmt->fetch();

if (!$company || (int)$vacancy['company_id'] !== (int)$company['id']) {
    setFlash('danger', 'Akses ditolak.');
    redirect('/company/vacancy/list.php');
}

// Can only edit if still submitted (not yet approved)
if (!in_array($vacancy['status'], ['submitted', 'rejected'])) {
    setFlash('warning', 'Lowongan yang sudah disetujui tidak dapat diedit. Hubungi admin.');
    redirect('/company/vacancy/list.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();

    $data = $_POST;

    // Validate
    if (empty($data['title']))       $errors[] = 'Judul lowongan wajib diisi.';
    if (empty($data['description'])) $errors[] = 'Deskripsi wajib diisi.';
    $validTypes = ['full_time', 'part_time', 'contract', 'internship'];
    if (empty($data['job_type']) || !in_array($data['job_type'], $validTypes)) {
        $errors[] = 'Tipe pekerjaan tidak valid.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            "UPDATE job_vacancies SET
                title = ?, description = ?, requirements = ?,
                location = ?, salary_min = ?, salary_max = ?,
                job_type = ?, jurusan_required = ?, slots = ?,
                deadline = ?, status = 'submitted'
             WHERE id = ?"
        );
        $stmt->execute([
            sanitize($data['title']),
            $data['description'],
            $data['requirements'] ?? '',
            sanitize($data['location'] ?? ''),
            !empty($data['salary_min']) ? (float)$data['salary_min'] : null,
            !empty($data['salary_max']) ? (float)$data['salary_max'] : null,
            $data['job_type'],
            sanitize($data['jurusan_required'] ?? ''),
            max(1, (int)($data['slots'] ?? 1)),
            !empty($data['deadline']) ? $data['deadline'] : null,
            $vacancyId,
        ]);

        logActivity(currentUserId(), 'edit_vacancy', 'vacancy', "Edited vacancy id={$vacancyId}");
        setFlash('success', 'Lowongan berhasil diperbarui dan dikirim ulang untuk review admin.');
        redirect('/company/vacancy/list.php');
    }
}

$jurusanList = ['RPL','DKV','AKL','MPK','BDP','LP3K','LPB','ULW', 'Semua Jurusan'];

$pageTitle = 'Edit Lowongan — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<?= backButton() ?>
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/company/vacancy/list.php">Lowongan Saya</a></li>
    <li class="breadcrumb-item active">Edit Lowongan</li>
  </ol>
</nav>

<?php if ($vacancy['status'] === 'rejected'): ?>
<div class="alert alert-warning d-flex gap-3 mb-4">
  <i class="bi bi-exclamation-triangle-fill fs-4"></i>
  <div>
    <strong>Lowongan Ditolak.</strong>
    <?php if ($vacancy['admin_note']): ?>
    Alasan: <?= e($vacancy['admin_note']) ?>
    <?php endif; ?>
    <div class="small mt-1">Perbaiki dan simpan untuk mengajukan ulang ke admin.</div>
  </div>
</div>
<?php endif; ?>

<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white border-bottom fw-semibold">
        <i class="bi bi-pencil me-2 text-warning"></i>Edit Lowongan
      </div>
      <div class="card-body p-4">

        <?php if ($errors): ?>
        <div class="alert alert-danger">
          <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST">
          <?= csrfField() ?>

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Judul Posisi <span class="text-danger">*</span></label>
              <input type="text" name="title" class="form-control"
                     value="<?= e($vacancy['title']) ?>" required maxlength="150">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Tipe Pekerjaan <span class="text-danger">*</span></label>
              <select name="job_type" class="form-select" required>
                <?php foreach (['full_time'=>'Full Time','part_time'=>'Part Time','contract'=>'Kontrak','internship'=>'Magang'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= $vacancy['job_type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Jurusan yang Dibutuhkan</label>
              <select name="jurusan_required" class="form-select">
                <option value="">Semua Jurusan</option>
                <?php foreach ($jurusanList as $j): ?>
                <option value="<?= $j ?>" <?= $vacancy['jurusan_required'] === $j ? 'selected' : '' ?>><?= $j ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Lokasi</label>
              <input type="text" name="location" class="form-control"
                     value="<?= e($vacancy['location'] ?? '') ?>" placeholder="Kota / WFH / Remote">
            </div>

            <div class="col-md-2">
              <label class="form-label fw-semibold">Kuota</label>
              <input type="number" name="slots" class="form-control" min="1"
                     value="<?= e($vacancy['slots'] ?? 1) ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label fw-semibold">Deadline Lamaran</label>
              <input type="date" name="deadline" class="form-control"
                     value="<?= e($vacancy['deadline'] ?? '') ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Gaji Minimum (Rp)</label>
              <input type="number" name="salary_min" class="form-control" min="0" step="50000"
                     value="<?= e($vacancy['salary_min'] ?? '') ?>" placeholder="Kosongkan jika negosiasi">
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Gaji Maksimum (Rp)</label>
              <input type="number" name="salary_max" class="form-control" min="0" step="50000"
                     value="<?= e($vacancy['salary_max'] ?? '') ?>" placeholder="Kosongkan jika negosiasi">
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Deskripsi Pekerjaan <span class="text-danger">*</span></label>
              <textarea name="description" class="form-control" rows="6" required
                        placeholder="Jelaskan tanggung jawab dan tugas..."><?= e($vacancy['description']) ?></textarea>
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Persyaratan</label>
              <textarea name="requirements" class="form-control" rows="4"
                        placeholder="Persyaratan pendidikan, skill, pengalaman..."><?= e($vacancy['requirements'] ?? '') ?></textarea>
            </div>
          </div>

          <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-warning fw-semibold">
              <i class="bi bi-save me-2"></i>Simpan & Ajukan Ulang
            </button>
            <a href="<?= APP_URL ?>/company/vacancy/list.php" class="btn btn-outline-secondary">
              Batal
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
