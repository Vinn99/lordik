<?php
// public/admin/alumni/list.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireAdmin();

$filters = [
    'jurusan'         => $_GET['jurusan'] ?? '',
    'work_status'     => $_GET['work_status'] ?? '',
    'graduation_year' => $_GET['graduation_year'] ?? '',
    'search'          => $_GET['search'] ?? '',
];
$page   = max(1, (int)($_GET['page'] ?? 1));
$result = AlumniModel::searchAlumni($filters, PER_PAGE, ($page - 1) * PER_PAGE);
$paging = paginate($result['total'], $page);

$pageTitle = 'Data Alumni — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <?= backButton() ?>
<h4 class="fw-bold mb-0"><i class="bi bi-mortarboard me-2 text-primary"></i>Data Alumni</h4>
    <span class="badge bg-primary rounded-pill"><?= $result['total'] ?> alumni</span>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Cari nama / NIS / NISN..." value="<?= e($filters['search']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <select name="jurusan" class="form-select form-select-sm">
                    <option value="">Jurusan</option>
                    <?php foreach (['RPL','DKV','AKL','MPK','BDP','LP3K','LPB','ULW'] as $j): ?>
                    <option value="<?= $j ?>" <?= $filters['jurusan'] === $j ? 'selected' : '' ?>><?= $j ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="work_status" class="form-select form-select-sm">
                    <option value="">Status Kerja</option>
                    <?php foreach (['unemployed','employed','entrepreneur','continuing_edu'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filters['work_status'] === $s ? 'selected' : '' ?>><?= workStatusLabel($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" name="graduation_year" class="form-control form-control-sm"
                       placeholder="Angkatan" min="2000" max="<?= date('Y') ?>"
                       value="<?= e($filters['graduation_year']) ?>">
            </div>
            <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Filter</button></div>
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
                        <th>#</th><th>Foto</th><th>Nama</th><th>NIS</th><th>Jurusan</th><th>Angkatan</th>
                        <th>Status Kerja</th><th>CV</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['data'] as $i => $alumni): ?>
                    <tr>
                        <td><?= $paging['offset'] + $i + 1 ?></td>
                        <td class="fw-semibold">
                          <div class="d-flex align-items-center gap-2">
                            <div class="avatar-sm">
                              <?php if (!empty($alumni['photo_path'])): ?>
                              <img src="<?= APP_URL ?>/files/serve.php?type=photo&path=<?= urlencode($alumni['photo_path']) ?>" alt="">
                              <?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
                            </div>
                            <?= e($alumni['full_name']) ?>
                          </div>
                        </td>
                        <td class="small font-monospace"><?= e($alumni['nis']) ?></td>
                        <td><?= e($alumni['jurusan']) ?></td>
                        <td><?= e($alumni['graduation_year']) ?></td>
                        <td><?= statusBadge($alumni['work_status']) ?></td>
                        <td>
                            <?php if ($alumni['cv_path']): ?>
                            <a href="<?= APP_URL ?>/files/serve.php?type=cv&path=<?= urlencode($alumni['cv_path']) ?>"
                               target="_blank" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </a>
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= APP_URL ?>/admin/alumni/detail.php?id=<?= $alumni['id'] ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($result['data'])): ?>
                    <tr><td colspan="8" class="text-muted text-center py-4">Tidak ada data alumni.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($paging['total_pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Menampilkan <?= $paging['offset'] + 1 ?>–<?= min($paging['offset'] + PER_PAGE, $paging['total']) ?> dari <?= $paging['total'] ?></small>
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
