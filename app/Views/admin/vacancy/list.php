<?php
// public/admin/vacancy/list.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $vacancyId = (int)($_POST['vacancy_id'] ?? 0);
    $action    = $_POST['_action'] ?? '';
    $note      = sanitize($_POST['note'] ?? '');

    if ($action === 'approve') {
        VacancyModel::approve($vacancyId, currentUserId(), $note);
        setFlash('success', 'Lowongan berhasil disetujui.');
    } elseif ($action === 'reject') {
        if (empty($note)) {
            setFlash('danger', 'Catatan alasan penolakan wajib diisi.');
        } else {
            VacancyModel::reject($vacancyId, currentUserId(), $note);
            setFlash('success', 'Lowongan ditolak.');
        }
    } elseif ($action === 'close') {
        VacancyModel::close($vacancyId, currentUserId());
        setFlash('success', 'Lowongan ditutup.');
    } elseif ($action === 'delete') {
        VacancyModel::softDelete($vacancyId, currentUserId());
        setFlash('success', 'Lowongan dihapus.');
    }
    redirect('/admin/vacancy/list.php');
}

$filters = [
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? '',
];
$page   = max(1, (int)($_GET['page'] ?? 1));
$result = VacancyModel::list($filters, PER_PAGE, ($page - 1) * PER_PAGE);
$paging = paginate($result['total'], $page);

$pageTitle = 'Manajemen Lowongan — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-3">
    <?= backButton('/admin/dashboard.php') ?>
    <h4 class="fw-bold mb-0"><i class="bi bi-briefcase me-2 text-primary"></i>Manajemen Lowongan</h4>
</div>


<!-- Filter -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Cari judul / perusahaan..." value="<?= e($filters['search']) ?>">
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <?php foreach (['submitted','approved','rejected','closed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
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
                        <th>#</th>
                        <th>Judul</th>
                        <th>Perusahaan</th>
                        <th>Tipe</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['data'] as $i => $v): ?>
                    <tr>
                        <td><?= $paging['offset'] + $i + 1 ?></td>
                        <td>
                            <a href="<?= APP_URL ?>/admin/vacancy/detail.php?id=<?= $v['id'] ?>"
                               class="fw-semibold text-decoration-none text-dark">
                                <?= e($v['title']) ?>
                            </a>
                        </td>
                        <td><?= e($v['company_name']) ?></td>
                        <td><span class="badge bg-light text-dark"><?= e(str_replace('_', ' ', $v['job_type'])) ?></span></td>
                        <td class="small <?= $v['deadline'] && strtotime($v['deadline']) < time() ? 'text-danger' : 'text-muted' ?>">
                            <?= formatDate($v['deadline']) ?>
                        </td>
                        <td><?= statusBadge($v['status']) ?></td>
                        <td class="small text-muted"><?= formatDate($v['created_at'], 'd M Y') ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="<?= APP_URL ?>/admin/vacancy/detail.php?id=<?= $v['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="Detail">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($v['status'] === 'submitted'): ?>
                                <button class="btn btn-sm btn-outline-success"
                                        onclick="quickAction(<?= $v['id'] ?>, 'approve')"
                                        title="Approve"><i class="bi bi-check-lg"></i></button>
                                <button class="btn btn-sm btn-outline-danger"
                                        onclick="openRejectModal(<?= $v['id'] ?>)"
                                        title="Reject"><i class="bi bi-x-lg"></i></button>
                                <?php endif; ?>
                                <?php if ($v['status'] === 'approved'): ?>
                                <button class="btn btn-sm btn-outline-dark"
                                        onclick="quickAction(<?= $v['id'] ?>, 'close')"
                                        title="Tutup"><i class="bi bi-lock"></i></button>
                                <?php endif; ?>
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Hapus lowongan ini?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="_action" value="delete">
                                    <input type="hidden" name="vacancy_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Hapus">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($result['data'])): ?>
                    <tr><td colspan="8" class="text-muted text-center py-4">Tidak ada data lowongan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($paging['total_pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Total: <?= $paging['total'] ?> lowongan</small>
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

<!-- Quick Action Form (hidden) -->
<form id="quickActionForm" method="POST" style="display:none">
    <?= csrfField() ?>
    <input type="hidden" id="qaVacancyId" name="vacancy_id">
    <input type="hidden" id="qaAction" name="_action">
    <input type="hidden" id="qaNote" name="note">
</form>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Tolak Lowongan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label">Alasan Penolakan <span class="text-danger">*</span></label>
                <textarea id="rejectNote" class="form-control" rows="4"
                          placeholder="Berikan alasan penolakan yang jelas..."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" onclick="submitReject()">Tolak Lowongan</button>
            </div>
        </div>
    </div>
</div>

<script>
let rejectVacancyId = null;

function quickAction(id, action) {
    if (!confirm('Yakin melakukan aksi ini?')) return;
    document.getElementById('qaVacancyId').value = id;
    document.getElementById('qaAction').value    = action;
    document.getElementById('quickActionForm').submit();
}

function openRejectModal(id) {
    rejectVacancyId = id;
    document.getElementById('rejectNote').value = '';
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function submitReject() {
    const note = document.getElementById('rejectNote').value.trim();
    if (!note) { alert('Alasan penolakan wajib diisi.'); return; }
    document.getElementById('qaVacancyId').value = rejectVacancyId;
    document.getElementById('qaAction').value    = 'reject';
    document.getElementById('qaNote').value      = note;
    document.getElementById('quickActionForm').submit();
}
</script>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
