<?php
// public/admin/applications.php

requireAdmin();

$filters = [
    'status'     => $_GET['status']     ?? '',
    'vacancy_id' => !empty($_GET['vacancy_id']) ? (int)$_GET['vacancy_id'] : null,
    'search'     => $_GET['search']     ?? '',
];

$page   = max(1, (int)($_GET['page'] ?? 1));
$pdo    = getDB();

// Build flexible query
$where  = ['1=1'];
$params = [];

if ($filters['status']) {
    $where[]  = "a.status = ?";
    $params[] = $filters['status'];
}
if ($filters['vacancy_id']) {
    $where[]  = "a.vacancy_id = ?";
    $params[] = $filters['vacancy_id'];
}
if ($filters['search']) {
    $where[]  = "(ap.full_name LIKE ? OR jv.title LIKE ? OR c.company_name LIKE ?)";
    $s        = '%' . $filters['search'] . '%';
    $params[] = $s; $params[] = $s; $params[] = $s;
}

$whereSQL  = implode(' AND ', $where);
$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM applications a
     JOIN job_vacancies jv ON jv.id = a.vacancy_id
     JOIN alumni_profiles ap ON ap.id = a.alumni_id
     JOIN companies c ON c.id = jv.company_id
     WHERE $whereSQL"
);
$countStmt->execute($params);
$total  = (int)$countStmt->fetchColumn();
$paging = paginate($total, $page);

$fetchParams = array_merge($params, [PER_PAGE, $paging['offset']]);
$stmt = $pdo->prepare(
    "SELECT a.*, jv.title as vacancy_title, c.company_name,
            ap.full_name, ap.jurusan, ap.cv_path
     FROM applications a
     JOIN job_vacancies jv ON jv.id = a.vacancy_id
     JOIN alumni_profiles ap ON ap.id = a.alumni_id
     JOIN companies c ON c.id = jv.company_id
     WHERE $whereSQL
     ORDER BY a.applied_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute($fetchParams);
$applications = $stmt->fetchAll();

// Stats summary
$stStats = $pdo->query(
    "SELECT status, COUNT(*) as cnt FROM applications GROUP BY status"
);
$statMap = [];
foreach ($stStats->fetchAll() as $r) $statMap[$r['status']] = $r['cnt'];

$pageTitle = 'Semua Lamaran — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <?= backButton() ?>
<h4 class="fw-bold mb-0"><i class="bi bi-inbox me-2 text-primary"></i>Semua Lamaran</h4>
  <span class="badge bg-primary rounded-pill"><?= $total ?> lamaran</span>
</div>

<!-- Status Summary Cards -->
<div class="row g-3 mb-3">
  <?php
  $summaryCards = [
    ['label'=>'Total', 'val'=> array_sum($statMap), 'color'=>'primary', 'status'=>''],
    ['label'=>'Pending', 'val'=> $statMap['pending'] ?? 0, 'color'=>'warning', 'status'=>'pending'],
    ['label'=>'Shortlisted', 'val'=> $statMap['shortlisted'] ?? 0, 'color'=>'info', 'status'=>'shortlisted'],
    ['label'=>'Diterima', 'val'=> $statMap['accepted'] ?? 0, 'color'=>'success', 'status'=>'accepted'],
    ['label'=>'Ditolak', 'val'=> $statMap['rejected'] ?? 0, 'color'=>'danger', 'status'=>'rejected'],
  ];
  foreach ($summaryCards as $card): ?>
  <div class="col-6 col-md">
    <a href="?status=<?= $card['status'] ?>" class="text-decoration-none">
      <div class="card border-0 shadow-sm text-center py-2 <?= $filters['status'] === $card['status'] ? 'border border-'.$card['color'] : '' ?>">
        <div class="fw-bold fs-4 text-<?= $card['color'] ?>"><?= $card['val'] ?></div>
        <div class="small text-muted"><?= $card['label'] ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="card shadow-sm border-0 mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <input type="text" name="search" class="form-control form-control-sm"
               placeholder="Cari nama alumni / posisi / perusahaan..."
               value="<?= e($filters['search']) ?>">
      </div>
      <div class="col-md-3">
        <select name="status" class="form-select form-select-sm">
          <option value="">Semua Status</option>
          <?php foreach (['pending','reviewed','shortlisted','rejected','accepted'] as $s): ?>
          <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>>
            <?= ucwords(str_replace('_', ' ', $s)) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
      </div>
      <div class="col-auto">
        <a href="?" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body p-0">
    <div class="table-wrap">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Nama Pelamar</th>
            <th>Posisi</th>
            <th>Perusahaan</th>
            <th>Jurusan</th>
            <th>Tanggal Lamar</th>
            <th>Status</th>
            <th>CV</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($applications as $i => $app): ?>
          <tr>
            <td class="text-muted small"><?= $paging['offset'] + $i + 1 ?></td>
            <td class="fw-semibold"><?= e($app['full_name']) ?></td>
            <td class="small"><?= e(truncate($app['vacancy_title'], 35)) ?></td>
            <td class="small text-muted"><?= e($app['company_name']) ?></td>
            <td><span class="badge bg-light text-dark"><?= e($app['jurusan']) ?></span></td>
            <td class="small text-muted"><?= formatDate($app['applied_at'], 'd M Y') ?></td>
            <td><?= statusBadge($app['status']) ?></td>
            <td>
              <?php if ($app['cv_path']): ?>
              <a href="<?= APP_URL ?>/files/serve.php?type=cv&path=<?= urlencode($app['cv_path']) ?>"
                 target="_blank" class="btn btn-sm btn-outline-danger py-0">
                <i class="bi bi-file-earmark-pdf"></i>
              </a>
              <?php else: ?>
              <span class="text-muted small">-</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= APP_URL ?>/alumni/application_detail.php?id=<?= $app['id'] ?>"
                 class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye me-1"></i>Detail
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($applications)): ?>
          <tr><td colspan="9" class="text-muted text-center py-4">Tidak ada lamaran ditemukan.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($paging['total_pages'] > 1): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small class="text-muted">Menampilkan <?= $paging['offset'] + 1 ?>–<?= min($paging['offset'] + PER_PAGE, $total) ?> dari <?= $total ?></small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($p = 1; $p <= $paging['total_pages']; $p++): ?>
      <li class="page-item <?= $p === $page ? 'active' : '' ?>">
        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
      </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
