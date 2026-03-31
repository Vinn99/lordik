<?php
// public/admin/company/detail.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireAdmin();

$companyId = (int)($_GET['company_id'] ?? 0);
$company   = CompanyModel::getById($companyId);
if (!$company) {
    setFlash('danger', 'Perusahaan tidak ditemukan.');
    redirect('/admin/company/list.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    if ($_POST['_action'] === 'toggle_verified') {
        $pdo  = getDB();
        $new  = $company['verified'] ? 0 : 1;
        $pdo->prepare("UPDATE companies SET verified = ? WHERE id = ?")->execute([$new, $companyId]);
        logActivity(currentUserId(), 'toggle_company_verified', 'admin', "company_id={$companyId} verified={$new}");
        setFlash('success', $new ? 'Perusahaan berhasil diverifikasi.' : 'Verifikasi dibatalkan.');
        redirect('/admin/company/detail.php?company_id=' . $companyId);
    }
    if ($_POST['_action'] === 'send_notification') {
        sendNotification($company['user_id'], sanitize($_POST['notif_title']), sanitize($_POST['notif_body']), 'info');
        setFlash('success', 'Notifikasi terkirim.');
        redirect('/admin/company/detail.php?company_id=' . $companyId);
    }
}

$vacancies = VacancyModel::list(['company_id' => $companyId], 50, 0);

// Stats
$pdo    = getDB();
$stmtS  = $pdo->prepare(
    "SELECT COUNT(a.id) as total_apps,
            SUM(a.status='accepted') as accepted
     FROM applications a
     JOIN job_vacancies jv ON jv.id = a.vacancy_id
     WHERE jv.company_id = ?"
);
$stmtS->execute([$companyId]);
$appStats = $stmtS->fetch();

$pageTitle = e($company['company_name']) . ' — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<?= backButton() ?>
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/admin/company/list.php">Perusahaan</a></li>
    <li class="breadcrumb-item active"><?= e($company['company_name']) ?></li>
  </ol>
</nav>

<div class="row g-4">
  <div class="col-lg-8">
    <!-- Profile Card -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body p-4">
        <div class="d-flex align-items-start gap-4 mb-4">
          <?php if ($company['logo_path']): ?>
          <img src="<?= APP_URL ?>/files/serve.php?type=logo&path=<?= urlencode($company['logo_path']) ?>"
               class="rounded-3" style="width:80px;height:80px;object-fit:contain;border:1px solid #e2e8f0;">
          <?php else: ?>
          <div class="bg-light rounded-3 d-flex align-items-center justify-content-center"
               style="width:80px;height:80px;min-width:80px;font-size:2.5rem">🏢</div>
          <?php endif; ?>
          <div>
            <h3 class="fw-bold mb-1"><?= e($company['company_name']) ?></h3>
            <div class="text-muted small mb-2"><?= e($company['email']) ?></div>
            <div class="d-flex gap-2">
              <?php if ($company['industry']): ?>
              <span class="badge bg-light text-dark border"><?= e($company['industry']) ?></span>
              <?php endif; ?>
              <?php if ($company['city']): ?>
              <span class="badge bg-light text-dark border"><i class="bi bi-geo-alt me-1"></i><?= e($company['city']) ?></span>
              <?php endif; ?>
              <span class="badge bg-<?= $company['verified'] ? 'success':'secondary' ?>">
                <?= $company['verified'] ? '✓ Verified':'Belum Verified' ?>
              </span>
            </div>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-sm-4">
            <div class="text-muted small">Telepon</div>
            <div class="fw-semibold small"><?= e($company['phone'] ?: '-') ?></div>
          </div>
          <div class="col-sm-4">
            <div class="text-muted small">Website</div>
            <div class="small">
              <?php if ($company['website']): ?>
              <a href="<?= e($company['website']) ?>" target="_blank" class="text-primary">
                <?= e(parse_url($company['website'], PHP_URL_HOST) ?: $company['website']) ?>
              </a>
              <?php else: ?>-<?php endif; ?>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="text-muted small">Terdaftar</div>
            <div class="fw-semibold small"><?= formatDate($company['created_at'],'d M Y') ?></div>
          </div>
        </div>

        <?php if ($company['address']): ?>
        <div class="mb-3">
          <div class="text-muted small">Alamat</div>
          <div class="small"><?= e($company['address']) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($company['description']): ?>
        <div>
          <div class="text-muted small mb-1">Deskripsi</div>
          <p class="small text-secondary mb-0" style="white-space:pre-wrap"><?= e($company['description']) ?></p>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Vacancy History -->
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white border-bottom fw-semibold d-flex justify-content-between">
        <span><i class="bi bi-briefcase me-2 text-primary"></i>Riwayat Lowongan</span>
        <span class="badge bg-secondary"><?= $vacancies['total'] ?></span>
      </div>
      <?php if ($vacancies['data']): ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr><th>Judul</th><th>Tipe</th><th>Deadline</th><th>Status</th><th>Pelamar</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($vacancies['data'] as $v):
              $stA = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE vacancy_id=?");
              $stA->execute([$v['id']]);
              $aCount = $stA->fetchColumn();
            ?>
            <tr>
              <td class="fw-semibold small"><?= e($v['title']) ?></td>
              <td><span class="badge bg-light text-dark"><?= ucwords(str_replace('_',' ',$v['job_type'])) ?></span></td>
              <td class="small text-muted"><?= formatDate($v['deadline']) ?></td>
              <td><?= statusBadge($v['status']) ?></td>
              <td class="text-center"><span class="badge bg-primary"><?= $aCount ?></span></td>
              <td>
                <a href="<?= APP_URL ?>/admin/vacancy/detail.php?id=<?= $v['id'] ?>"
                   class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="card-body text-muted text-center py-3">Belum ada lowongan.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Sidebar -->
  <div class="col-lg-4">
    <!-- Stats -->
    <div class="card shadow-sm border-0 mb-3">
      <div class="card-header bg-white border-bottom fw-semibold">Statistik</div>
      <div class="card-body p-0">
        <?php
        $statRows = [
          ['Lowongan Total', $vacancies['total'], 'primary'],
          ['Lowongan Aktif', collect_count($vacancies['data'], 'status', 'approved'), 'success'],
          ['Total Lamaran', $appStats['total_apps'] ?? 0, 'info'],
          ['Alumni Diterima', $appStats['accepted'] ?? 0, 'warning'],
        ];
        foreach ($statRows as [$label, $val, $color]):
        ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
          <span class="small text-muted"><?= $label ?></span>
          <span class="badge bg-<?= $color ?> rounded-pill"><?= $val ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Actions -->
    <div class="card shadow-sm border-0 mb-3">
      <div class="card-header bg-white border-bottom fw-semibold">Aksi Admin</div>
      <div class="card-body d-flex flex-column gap-2">
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="toggle_verified">
          <button type="submit" class="btn btn-<?= $company['verified'] ? 'outline-warning':'success' ?> w-100 btn-sm">
            <i class="bi bi-shield-<?= $company['verified'] ? 'x':'check' ?> me-1"></i>
            <?= $company['verified'] ? 'Batalkan Verifikasi':'Verifikasi Perusahaan' ?>
          </button>
        </form>
        <a href="<?= APP_URL ?>/admin/vacancy/list.php?company_id=<?= $companyId ?>"
           class="btn btn-outline-primary btn-sm">
          <i class="bi bi-briefcase me-1"></i>Lihat Semua Lowongan
        </a>
      </div>
    </div>

    <!-- Send Notification -->
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white border-bottom fw-semibold">
        <i class="bi bi-bell me-2 text-info"></i>Kirim Notifikasi
      </div>
      <div class="card-body">
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="_action" value="send_notification">
          <div class="mb-2">
            <input type="text" name="notif_title" class="form-control form-control-sm"
                   placeholder="Judul" required>
          </div>
          <div class="mb-2">
            <textarea name="notif_body" class="form-control form-control-sm" rows="3"
                      placeholder="Pesan..." required></textarea>
          </div>
          <button type="submit" class="btn btn-info btn-sm w-100 text-white">
            <i class="bi bi-send me-1"></i>Kirim
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
// Helper function
function collect_count(array $data, string $key, string $val): int {
    return count(array_filter($data, fn($r) => $r[$key] === $val));
}
?>
<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
