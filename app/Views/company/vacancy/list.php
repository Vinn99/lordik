<?php
// public/company/vacancy/list.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireCompany();

$pdo     = getDB();
$stmt    = $pdo->prepare("SELECT id FROM companies WHERE user_id = ? LIMIT 1");
$stmt->execute([currentUserId()]);
$company = $stmt->fetch();

if (!$company) {
    setFlash('warning', 'Lengkapi profil perusahaan terlebih dahulu.');
    redirect('/company/profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    if ($_POST['_action'] === 'close') {
        $vacancyId = (int)$_POST['vacancy_id'];
        VacancyModel::close($vacancyId, currentUserId());
        setFlash('success', 'Lowongan ditutup.');
        redirect('/company/vacancy/list.php');
    }
}

$page   = max(1, (int)($_GET['page'] ?? 1));
$result = VacancyModel::list(['company_id' => $company['id']], PER_PAGE, ($page - 1) * PER_PAGE);
$paging = paginate($result['total'], $page);

$pageTitle = 'Lowongan Saya — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-3">
    <?= backButton('/company/dashboard.php') ?>
    <h4 class="fw-bold mb-0"><i class="bi bi-briefcase me-2 text-primary"></i>Lowongan Saya</h4>
</div>


<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-briefcase me-2 text-primary"></i>Lowongan Saya</h4>
    <a href="<?= APP_URL ?>/company/vacancy/create.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Ajukan Lowongan Baru
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-wrap">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Judul</th><th>Tipe</th><th>Deadline</th>
                        <th>Pelamar</th><th>Status</th><th>Catatan Admin</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['data'] as $i => $v): ?>
                    <?php
                        $stmtC = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE vacancy_id = ?");
                        $stmtC->execute([$v['id']]);
                        $appCount = $stmtC->fetchColumn();
                    ?>
                    <tr>
                        <td><?= $paging['offset'] + $i + 1 ?></td>
                        <td class="fw-semibold"><?= e($v['title']) ?></td>
                        <td><span class="badge bg-light text-dark"><?= ucwords(str_replace('_',' ',$v['job_type'])) ?></span></td>
                        <td class="small"><?= formatDate($v['deadline']) ?></td>
                        <td class="text-center">
                            <a href="<?= APP_URL ?>/company/applications.php?vacancy_id=<?= $v['id'] ?>"
                               class="badge bg-primary text-decoration-none"><?= $appCount ?></a>
                        </td>
                        <td><?= statusBadge($v['status']) ?></td>
                        <td class="small text-muted"><?= $v['admin_note'] ? e(truncate($v['admin_note'], 50)) : '-' ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?= APP_URL ?>/vacancy/detail.php?id=<?= $v['id'] ?>"
                                   class="btn btn-outline-secondary" title="Lihat"><i class="bi bi-eye"></i></a>
                                <?php if (in_array($v['status'], ['submitted','rejected'])): ?>
                                <a href="<?= APP_URL ?>/company/vacancy/edit.php?id=<?= $v['id'] ?>"
                                   class="btn btn-outline-warning" title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
                                <?php if ($v['status'] === 'approved'): ?>
                                <form method="POST" onsubmit="return confirm('Tutup lowongan ini?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="_action" value="close">
                                    <input type="hidden" name="vacancy_id" value="<?= $v['id'] ?>">
                                    <button type="submit" class="btn btn-outline-dark" title="Tutup"><i class="bi bi-lock"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($result['data'])): ?>
                    <tr><td colspan="8" class="text-muted text-center py-4">Belum ada lowongan. <a href="<?= APP_URL ?>/company/vacancy/create.php">Ajukan sekarang</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
