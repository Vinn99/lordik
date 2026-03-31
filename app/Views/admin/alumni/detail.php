<?php
// public/admin/alumni/detail.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireAdmin();

$profileId = (int)($_GET['id'] ?? 0);
$profile   = AlumniModel::getProfileById($profileId);
if (!$profile) {
    setFlash('danger', 'Profil alumni tidak ditemukan.');
    redirect('/admin/alumni/list.php');
}

// Admin update work_status override
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    if ($_POST['_action'] === 'update_status') {
        $pdo  = getDB();
        $stmt = $pdo->prepare("UPDATE alumni_profiles SET work_status = ? WHERE id = ?");
        $stmt->execute([$_POST['work_status'], $profileId]);
        logActivity(currentUserId(), 'admin_update_alumni_status', 'admin',
            "Admin updated alumni id={$profileId} work_status={$_POST['work_status']}");
        setFlash('success', 'Status kerja alumni berhasil diupdate.');
        redirect('/admin/alumni/detail.php?id=' . $profileId);
    }
    if ($_POST['_action'] === 'send_notification') {
        sendNotification(
            $profile['user_id'],
            sanitize($_POST['notif_title']),
            sanitize($_POST['notif_body']),
            'info'
        );
        logActivity(currentUserId(), 'admin_send_notification', 'admin',
            "Admin sent notification to user_id={$profile['user_id']}");
        setFlash('success', 'Notifikasi berhasil dikirim.');
        redirect('/admin/alumni/detail.php?id=' . $profileId);
    }
}

$certs        = AlumniModel::getCertificates($profileId);
$applications = AlumniModel::getApplicationHistory($profile['user_id']);

$pageTitle = e($profile['full_name']) . ' — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<?= backButton() ?>
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/alumni/list.php">Data Alumni</a></li>
    <li class="breadcrumb-item active"><?= e($profile['full_name']) ?></li>
  </ol>
</nav>

<div class="row g-4">
  <!-- Profile -->
  <div class="col-lg-8">
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body p-4">
        <div class="d-flex align-items-start gap-4 mb-4">
          <div class="avatar-detail">
            <?php if ($profile['photo_path']): ?>
            <img src="<?= APP_URL ?>/files/serve.php?type=photo&path=<?= urlencode($profile['photo_path']) ?>" alt="Foto">
            <?php else: ?>
            <i class="bi bi-person-fill"></i>
            <?php endif; ?>
          </div>
          <div class="flex-grow-1">
            <h3 class="fw-bold mb-1"><?= e($profile['full_name']) ?></h3>
            <div class="text-muted mb-2"><?= e($profile['email']) ?></div>
            <div class="d-flex flex-wrap gap-2">
              <span class="badge bg-primary"><?= e($profile['jurusan']) ?></span>
              <span class="badge bg-secondary">Angkatan <?= e($profile['graduation_year']) ?></span>
              <?= statusBadge($profile['work_status']) ?>
            </div>
          </div>
        </div>

        <div class="row g-3">
          <?php $fields = [
            ['NIS', $profile['nis']],
            ['NISN', $profile['nisn']],
            ['Jenis Kelamin', $profile['gender'] === 'male' ? 'Laki-laki' : 'Perempuan'],
            ['Tanggal Lahir', formatDate($profile['birth_date'])],
            ['No. HP', $profile['phone'] ?: '-'],
            ['Alamat', $profile['address'] ?: '-'],
          ]; foreach ($fields as [$label, $val]): ?>
          <div class="col-md-4">
            <div class="text-muted small"><?= $label ?></div>
            <div class="fw-semibold small"><?= e($val) ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <?php if ($profile['skills']): ?>
        <div class="mt-3">
          <div class="text-muted small mb-1">Skills</div>
          <div class="d-flex flex-wrap gap-1">
            <?php foreach (explode(',', $profile['skills']) as $skill): ?>
            <span class="badge bg-light text-dark border"><?= e(trim($skill)) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($profile['bio']): ?>
        <div class="mt-3">
          <div class="text-muted small mb-1">Bio</div>
          <p class="small text-secondary mb-0"><?= e($profile['bio']) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- CV & Certificates -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-header bg-white border-bottom fw-semibold">
        <i class="bi bi-files me-2 text-primary"></i>Dokumen
      </div>
      <div class="card-body">
        <div class="d-flex gap-3 align-items-center mb-3">
          <div class="fw-semibold small">CV:</div>
          <?php if ($profile['cv_path']): ?>
          <a href="<?= APP_URL ?>/files/serve.php?type=cv&path=<?= urlencode($profile['cv_path']) ?>"
             target="_blank" class="btn btn-sm btn-danger">
            <i class="bi bi-file-earmark-pdf me-1"></i>Lihat CV
          </a>
          <?php else: ?>
          <span class="text-muted small">Belum upload CV</span>
          <?php endif; ?>
        </div>

        <?php if ($certs): ?>
        <div class="fw-semibold small mb-2">Sertifikat (<?= count($certs) ?>):</div>
        <div class="row g-2">
          <?php foreach ($certs as $cert): ?>
          <div class="col-md-6">
            <div class="p-2 border rounded-2 d-flex justify-content-between align-items-center">
              <div>
                <div class="small fw-semibold"><?= e($cert['cert_name']) ?></div>
                <div class="text-muted" style="font-size:.75rem"><?= e($cert['issuer'] ?: '-') ?> · <?= formatDate($cert['issued_date']) ?></div>
              </div>
              <a href="<?= APP_URL ?>/files/serve.php?type=cert&path=<?= urlencode($cert['file_path']) ?>"
                 target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="text-muted small">Belum ada sertifikat.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Application History -->
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white border-bottom fw-semibold">
        <i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Lamaran (<?= count($applications) ?>)
      </div>
      <?php if ($applications): ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th>Posisi</th><th>Perusahaan</th><th>Tanggal</th><th>Status</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($applications as $app): ?>
            <tr>
              <td class="fw-semibold small"><?= e($app['vacancy_title']) ?></td>
              <td class="small text-muted"><?= e($app['company_name']) ?></td>
              <td class="small text-muted"><?= formatDate($app['applied_at'],'d M Y') ?></td>
              <td><?= statusBadge($app['status']) ?></td>
              <td>
                <a href="<?= APP_URL ?>/alumni/application_detail.php?id=<?= $app['id'] ?>"
                   class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body text-muted text-center py-3">Belum ada riwayat lamaran.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right Sidebar: Admin Tools -->
  <div class="col-lg-4">
    <!-- Update Status -->
    <div class="card shadow-sm border-0 mb-3">
      <div class="card-header bg-white border-bottom fw-semibold">
        <i class="bi bi-pencil-square me-2 text-warning"></i>Update Status Kerja
      </div>
      <div class="card-body">
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="update_status">
          <div class="mb-3">
            <select name="work_status" class="form-select">
              <?php foreach (['unemployed','employed','entrepreneur','continuing_edu'] as $s): ?>
              <option value="<?= $s ?>" <?= $profile['work_status'] === $s ? 'selected':'' ?>>
                <?= workStatusLabel($s) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-warning w-100 btn-sm fw-semibold">
            <i class="bi bi-save me-1"></i>Simpan Status
          </button>
        </form>
      </div>
    </div>

    <!-- Send Notification -->
    <div class="card shadow-sm border-0 mb-3">
      <div class="card-header bg-white border-bottom fw-semibold">
        <i class="bi bi-bell me-2 text-info"></i>Kirim Notifikasi
      </div>
      <div class="card-body">
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="send_notification">
          <div class="mb-2">
            <input type="text" name="notif_title" class="form-control form-control-sm"
                   placeholder="Judul notifikasi" required>
          </div>
          <div class="mb-2">
            <textarea name="notif_body" class="form-control form-control-sm" rows="3"
                      placeholder="Isi pesan..." required></textarea>
          </div>
          <button type="submit" class="btn btn-info w-100 btn-sm text-white">
            <i class="bi bi-send me-1"></i>Kirim
          </button>
        </form>
      </div>
    </div>

    <!-- Quick Info -->
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white border-bottom fw-semibold">Info Akun</div>
      <div class="list-group list-group-flush">
        <div class="list-group-item d-flex justify-content-between py-2">
          <span class="small text-muted">Username</span>
          <span class="small fw-semibold"><?= e($profile['username']) ?></span>
        </div>
        <div class="list-group-item d-flex justify-content-between py-2">
          <span class="small text-muted">Email</span>
          <span class="small"><?= e($profile['email']) ?></span>
        </div>
        <div class="list-group-item d-flex justify-content-between py-2">
          <span class="small text-muted">Total Lamaran</span>
          <span class="badge bg-primary rounded-pill"><?= count($applications) ?></span>
        </div>
        <div class="list-group-item d-flex justify-content-between py-2">
          <span class="small text-muted">Sertifikat</span>
          <span class="badge bg-secondary rounded-pill"><?= count($certs) ?></span>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
