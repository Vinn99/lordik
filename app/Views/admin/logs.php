<?php
// public/admin/logs.php
require_once BASE_PATH . '/helpers/activity_log.php';
require_once BASE_PATH . '/helpers/notification_helper.php';

requireAdmin();

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 30;
$offset = ($page - 1) * $limit;

$userId = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$logs   = getActivityLogs($limit, $offset, $userId);

// Total count
$pdo  = getDB();
$sql  = "SELECT COUNT(*) FROM activity_logs" . ($userId ? " WHERE user_id = ?" : "");
$stmt = $pdo->prepare($sql);
$userId ? $stmt->execute([$userId]) : $stmt->execute();
$total  = (int)$stmt->fetchColumn();
$paging = paginate($total, $page, $limit);

$pageTitle = 'Activity Log — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <?= backButton() ?>
<h4 class="fw-bold mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Activity Log</h4>
    <span class="badge bg-secondary"><?= number_format($total) ?> entri</span>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size:.875rem">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Aksi</th>
                        <th>Modul</th>
                        <th>Deskripsi</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $i => $log): ?>
                    <tr>
                        <td class="text-muted"><?= $paging['offset'] + $i + 1 ?></td>
                        <td class="text-muted"><?= formatDate($log['created_at'], 'd M Y H:i:s') ?></td>
                        <td>
                            <?php if ($log['username']): ?>
                            <a href="?user_id=<?= $log['user_id'] ?>" class="text-decoration-none fw-semibold">
                                <?= e($log['username']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">system</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark"><?= e($log['action']) ?></span>
                        </td>
                        <td class="text-muted"><?= e($log['module']) ?></td>
                        <td class="text-muted small"><?= e(truncate($log['description'] ?? '', 80)) ?></td>
                        <td class="text-muted small font-monospace"><?= e($log['ip_address']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-muted text-center py-4">Tidak ada log.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($paging['total_pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Total <?= number_format($total) ?> log</small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php
            $start = max(1, $page - 3);
            $end   = min($paging['total_pages'], $page + 3);
            if ($start > 1) echo '<li class="page-item"><a class="page-link" href="?page=1' . ($userId ? "&user_id={$userId}" : '') . '">1</a></li>';
            for ($p = $start; $p <= $end; $p++):
            ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?><?= $userId ? "&user_id={$userId}" : '' ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($end < $paging['total_pages']) echo "<li class='page-item'><a class='page-link' href='?page={$paging['total_pages']}'>Last</a></li>"; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
