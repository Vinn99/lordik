<?php
// public/admin/vacancy/detail.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireAdmin();

$id      = (int)($_GET['id'] ?? 0);
$vacancy = VacancyModel::getById($id);
if (!$vacancy) {
    setFlash('danger', 'Lowongan tidak ditemukan.');
    redirect('/admin/vacancy/list.php');
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['_action'] ?? '';
    $note   = sanitize($_POST['note'] ?? '');

    if ($action === 'approve') {
        VacancyModel::approve($id, currentUserId(), $note);
        setFlash('success', 'Lowongan berhasil disetujui dan sekarang aktif.');
        redirect('/admin/vacancy/detail.php?id=' . $id);
    }
    if ($action === 'reject') {
        if (empty($note)) { setFlash('danger', 'Alasan penolakan wajib diisi.'); }
        else {
            VacancyModel::reject($id, currentUserId(), $note);
            setFlash('success', 'Lowongan ditolak.');
        }
        redirect('/admin/vacancy/detail.php?id=' . $id);
    }
    if ($action === 'close') {
        VacancyModel::close($id, currentUserId());
        setFlash('success', 'Lowongan ditutup.');
        redirect('/admin/vacancy/detail.php?id=' . $id);
    }
    if ($action === 'delete') {
        VacancyModel::softDelete($id, currentUserId());
        setFlash('success', 'Lowongan dihapus.');
        redirect('/admin/vacancy/list.php');
    }
}

// Reload setelah POST
$vacancy      = VacancyModel::getById($id);
$applications = ApplicationModel::listByVacancy($id);

$pageTitle = 'Detail Lowongan — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<?= backButton() ?>
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/vacancy/list.php">Lowongan</a></li>
    <li class="breadcrumb-item active"><?= e(truncate($vacancy['title'], 40)) ?></li>
  </ol>
</nav>

<div class="row g-4">
  <!-- Left: Detail -->
  <div class="col-lg-8">
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h3 class="fw-bold mb-1"><?= e($vacancy['title']) ?></h3>
            <p class="text-muted mb-0"><?= e($vacancy['company_name']) ?></p>
          </div>
          <?= statusBadge($vacancy['status']) ?>
        </div>

        <div class="row g-3 mb-4">
          <?php $infoItems = [
            ['icon'=>'briefcase','label'=>'Tipe','val'=>ucwords(str_replace('_',' ',$vacancy['job_type']))],
            ['icon'=>'geo-alt','label'=>'Lokasi','val'=>$vacancy['location']?:($vacancy['city']??'-')],
            ['icon'=>'mortarboard','label'=>'Jurusan','val'=>$vacancy['jurusan_required']?:'Semua Jurusan'],
            ['icon'=>'people','label'=>'Kuota','val'=>$vacancy['slots'].' orang'],
            ['icon'=>'calendar-event','label'=>'Deadline','val'=>formatDate($vacancy['deadline'])],
            ['icon'=>'calendar3','label'=>'Diajukan','val'=>formatDate($vacancy['created_at'],'d M Y H:i')],
          ]; foreach ($infoItems as $item): ?>
          <div class="col-sm-4">
            <div class="p-3 rounded-3 bg-light border">
              <div class="text-muted small"><i class="bi bi-<?= $item['icon'] ?> me-1"></i><?= $item['label'] ?></div>
              <div class="fw-semibold small mt-1"><?= e($item['val']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <?php if ($vacancy['salary_min'] || $vacancy['salary_max']): ?>
        <div class="alert alert-success py-2 mb-4">
          <i class="bi bi-cash-stack me-2"></i>
          <strong>Gaji:</strong>
          <?= formatCurrency($vacancy['salary_min']) ?>
          <?= $vacancy['salary_max'] ? ' — ' . formatCurrency($vacancy['salary_max']) : '' ?>
        </div>
        <?php endif; ?>

        <h6 class="fw-semibold mb-2">Deskripsi Pekerjaan</h6>
        <div class="text-secondary mb-4" style="white-space:pre-wrap;line-height:1.75"><?= e($vacancy['description']) ?></div>

        <?php if ($vacancy['requirements']): ?>
        <h6 class="fw-semibold mb-2">Persyaratan</h6>
        <div class="text-secondary" style="white-space:pre-wrap;line-height:1.75"><?= e($vacancy['requirements']) ?></div>
        <?php endif; ?>

        <?php if ($vacancy['admin_note']): ?>
        <div class="alert alert-info mt-4 mb-0">
          <i class="bi bi-info-circle me-2"></i><strong>Catatan Admin:</strong> <?= e($vacancy['admin_note']) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Applications Table -->
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white border-bottom fw-semibold d-flex justify-content-between">
        <span><i class="bi bi-people me-2 text-primary"></i>Pelamar (<?= count($applications) ?>)</span>
      </div>
      <?php if ($applications): ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th>Nama</th><th>Jurusan</th><th>Tanggal</th><th>Status</th><th>CV</th><th>Aksi</th></tr>
          </thead>
          <tbody>
            <?php foreach ($applications as $app): ?>
            <tr>
              <td class="fw-semibold"><?= e($app['full_name']) ?></td>
              <td><span class="badge bg-light text-dark"><?= e($app['jurusan']) ?></span></td>
              <td class="small text-muted"><?= formatDate($app['applied_at'],'d M Y') ?></td>
              <td><?= statusBadge($app['status']) ?></td>
              <td>
                <?php if ($app['cv_path']): ?>
                <a href="<?= APP_URL ?>/files/serve.php?type=cv&path=<?= urlencode($app['cv_path']) ?>"
                   target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i></a>
                <?php else: ?><span class="text-muted small">-</span><?php endif; ?>
              </td>
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
      <div class="card-body text-muted text-center py-4">Belum ada pelamar untuk lowongan ini.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Right: Actions -->
  <div class="col-lg-4">
    <!-- Action Card -->
    <div class="card shadow-sm border-0 mb-3">
      <div class="card-header bg-white border-bottom fw-semibold">
        <i class="bi bi-gear me-2 text-primary"></i>Tindakan Admin
      </div>
      <div class="card-body p-3">
        <?php if ($vacancy['status'] === 'submitted'): ?>
        <!-- APPROVE -->
        <form method="POST" class="mb-2">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="approve">
          <div class="mb-2">
            <label class="form-label small">Catatan Persetujuan (opsional)</label>
            <textarea name="note" class="form-control form-control-sm" rows="2"
                      placeholder="Catatan untuk perusahaan..."></textarea>
          </div>
          <button type="submit" class="btn btn-success w-100 fw-semibold">
            <i class="bi bi-check-circle me-2"></i>Setujui Lowongan
          </button>
        </form>
        <!-- REJECT -->
        <button class="btn btn-outline-danger w-100" data-bs-toggle="collapse" data-bs-target="#rejectForm">
          <i class="bi bi-x-circle me-2"></i>Tolak Lowongan
        </button>
        <div class="collapse mt-2" id="rejectForm">
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="_action" value="reject">
            <div class="mb-2">
              <label class="form-label small text-danger">Alasan Penolakan <span>*</span></label>
              <textarea name="note" class="form-control form-control-sm" rows="3" required
                        placeholder="Berikan alasan yang jelas..."></textarea>
            </div>
            <button type="submit" class="btn btn-danger w-100 btn-sm">Konfirmasi Tolak</button>
          </form>
        </div>

        <?php elseif ($vacancy['status'] === 'approved'): ?>
        <div class="alert alert-success py-2 mb-3">
          <i class="bi bi-check-circle me-1"></i> Lowongan aktif & tampil ke alumni
        </div>
        <form method="POST" onsubmit="return confirm('Tutup lowongan ini?')">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="close">
          <button type="submit" class="btn btn-dark w-100">
            <i class="bi bi-lock me-2"></i>Tutup Lowongan
          </button>
        </form>

        <?php elseif ($vacancy['status'] === 'rejected'): ?>
        <div class="alert alert-danger py-2 mb-3">
          <i class="bi bi-x-circle me-1"></i> Lowongan ditolak
          <?php if ($vacancy['admin_note']): ?>
          <div class="small mt-1"><?= e($vacancy['admin_note']) ?></div>
          <?php endif; ?>
        </div>
        <!-- Allow re-approve -->
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="approve">
          <button type="submit" class="btn btn-outline-success w-100 btn-sm">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Setujui Ulang
          </button>
        </form>

        <?php elseif ($vacancy['status'] === 'closed'): ?>
        <div class="alert alert-dark py-2">
          <i class="bi bi-lock me-1"></i> Lowongan sudah ditutup
        </div>
        <?php endif; ?>

        <!-- Delete always available -->
        <?php if ($vacancy['status'] !== 'approved'): ?>
        <hr>
        <form method="POST" onsubmit="return confirm('Hapus permanen lowongan ini?')">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="delete">
          <button type="submit" class="btn btn-outline-secondary w-100 btn-sm">
            <i class="bi bi-trash me-1"></i>Hapus Lowongan
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Info Perusahaan -->
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white border-bottom fw-semibold">
        <i class="bi bi-building me-2 text-success"></i>Perusahaan
      </div>
      <div class="card-body">
        <?php if ($vacancy['logo_path']): ?>
        <img src="<?= APP_URL ?>/files/serve.php?type=logo&path=<?= urlencode($vacancy['logo_path']) ?>"
             class="mb-2 rounded" style="max-height:60px;">
        <br>
        <?php endif; ?>
        <div class="fw-semibold"><?= e($vacancy['company_name']) ?></div>
        <?php if ($vacancy['city']): ?>
        <div class="text-muted small"><i class="bi bi-geo-alt me-1"></i><?= e($vacancy['city']) ?></div>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/admin/company/detail.php?company_id=<?= $vacancy['company_id'] ?>"
           class="btn btn-sm btn-outline-primary mt-2 w-100">Lihat Profil Perusahaan</a>
      </div>
    </div>
  </div>
</div>
<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
