<?php
// public/admin/company/list.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireAdmin();

// Toggle verified
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    if ($_POST['_action'] === 'toggle_verified') {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT verified FROM companies WHERE id = ?");
        $stmt->execute([(int)$_POST['company_id']]);
        $row  = $stmt->fetch();
        if ($row) {
            $new = $row['verified'] ? 0 : 1;
            $pdo->prepare("UPDATE companies SET verified = ? WHERE id = ?")->execute([$new, (int)$_POST['company_id']]);
            logActivity(currentUserId(), 'toggle_company_verified', 'admin', "company_id={$_POST['company_id']} verified={$new}");
            setFlash('success', 'Status verifikasi perusahaan diperbarui.');
        }
        redirect('/admin/company/list.php');
    }
}

$search = $_GET['search'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$result = CompanyModel::listAll(PER_PAGE, ($page - 1) * PER_PAGE, $search);
$paging = paginate($result['total'], $page);

$pageTitle = 'Manajemen Perusahaan — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <?= backButton() ?>
<h4 class="fw-bold mb-0"><i class="bi bi-building me-2 text-primary"></i>Manajemen Perusahaan</h4>
  <span class="badge bg-primary rounded-pill"><?= $result['total'] ?> perusahaan</span>
</div>

<div class="card shadow-sm border-0 mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-5">
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Cari nama perusahaan / kota..." value="<?= e($search) ?>">
      </div>
      <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Cari</button></div>
      <div class="col-auto"><a href="?" class="btn btn-sm btn-outline-secondary">Reset</a></div>
    </form>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body p-0">
    <div class="table-wrap">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th><th>Perusahaan</th><th>Industri</th><th>Kota</th>
            <th>Email</th><th>Terverifikasi</th><th>Lowongan</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($result['data'] as $i => $co):
            $pdo = getDB();
            $stV = $pdo->prepare("SELECT COUNT(*) FROM job_vacancies WHERE company_id=? AND status='approved' AND deleted_at IS NULL");
            $stV->execute([$co['id']]);
            $vacCount = $stV->fetchColumn();
          ?>
          <tr>
            <td><?= $paging['offset'] + $i + 1 ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if ($co['logo_path']): ?>
                <img src="<?= APP_URL ?>/files/serve.php?type=logo&path=<?= urlencode($co['logo_path']) ?>"
                     style="width:32px;height:32px;object-fit:contain;border-radius:6px;" alt="">
                <?php else: ?>
                <div class="bg-light rounded d-flex align-items-center justify-content-center"
                     style="width:32px;height:32px;font-size:.9rem">🏢</div>
                <?php endif; ?>
                <span class="fw-semibold small"><?= e($co['company_name']) ?></span>
              </div>
            </td>
            <td class="small text-muted"><?= e($co['industry'] ?: '-') ?></td>
            <td class="small"><?= e($co['city'] ?: '-') ?></td>
            <td class="small text-muted"><?= e($co['email']) ?></td>
            <td>
              <?php if ($co['verified']): ?>
              <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Verified</span>
              <?php else: ?>
              <span class="badge bg-secondary">Belum</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <span class="badge bg-primary rounded-pill"><?= $vacCount ?></span>
            </td>
            <td>
              <div class="btn-group btn-group-sm">
                <a href="<?= APP_URL ?>/admin/company/detail.php?company_id=<?= $co['id'] ?>"
                   class="btn btn-outline-primary" title="Detail"><i class="bi bi-eye"></i></a>
                <form method="POST" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="_action" value="toggle_verified">
                  <input type="hidden" name="company_id" value="<?= $co['id'] ?>">
                  <button type="submit"
                          class="btn <?= $co['verified'] ? 'btn-outline-warning':'btn-outline-success' ?>"
                          title="<?= $co['verified'] ? 'Batalkan Verifikasi':'Verifikasi' ?>">
                    <i class="bi bi-<?= $co['verified'] ? 'shield-x':'shield-check' ?>"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($result['data'])): ?>
          <tr><td colspan="8" class="text-muted text-center py-4">Tidak ada data perusahaan.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($paging['total_pages'] > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small class="text-muted">Total: <?= $result['total'] ?> perusahaan</small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($p = 1; $p <= $paging['total_pages']; $p++): ?>
      <li class="page-item <?= $p === $page ? 'active':'' ?>">
        <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>"><?= $p ?></a>
      </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
