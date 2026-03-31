<?php
// public/notifications/list.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireLogin();

$pdo    = getDB();
$userId = currentUserId();
$page   = max(1, (int)($_GET['page'] ?? 1));
$filter = $_GET['filter'] ?? ''; // 'unread' | ''

// Count total
$where    = "user_id = ?";
$params   = [$userId];
if ($filter === 'unread') { $where .= " AND is_read = 0"; }

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE $where");
$countStmt->execute($params);
$total  = (int)$countStmt->fetchColumn();
$paging = paginate($total, $page, 20);

// Fetch notifications
$fetchParams = array_merge($params, [20, $paging['offset']]);
$stmt = $pdo->prepare(
    "SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?"
);
$stmt->execute($fetchParams);
$notifications = $stmt->fetchAll();

// Unread count badge
$unreadTotal = countUnreadNotifications($userId);

$pageTitle = 'Semua Notifikasi — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h4 class="fw-bold mb-0">
    <i class="bi bi-bell me-2 text-primary"></i>Notifikasi
    <?php if ($unreadTotal > 0): ?>
    <span class="badge bg-danger ms-1"><?= $unreadTotal ?> baru</span>
    <?php endif; ?>
  </h4>
  <div class="d-flex gap-2">
    <a href="?<?= $filter === 'unread' ? '' : 'filter=unread' ?>"
       class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline-primary' ?>">
      <i class="bi bi-envelope me-1"></i>Belum Dibaca
    </a>
    <?php if ($unreadTotal > 0): ?>
    <form method="POST" action="<?= APP_URL ?>/notifications/mark_all.php">
      <?= csrfField() ?>
      <button type="submit" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-check-all me-1"></i>Tandai Semua Dibaca
      </button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($notifications): ?>
<div class="card border-0 shadow-sm">
  <div class="list-group list-group-flush">
    <?php foreach ($notifications as $n):
      $iconMap = [
        'success' => ['bi-check-circle-fill', 'text-success'],
        'error'   => ['bi-x-circle-fill', 'text-danger'],
        'warning' => ['bi-exclamation-triangle-fill', 'text-warning'],
        'info'    => ['bi-info-circle-fill', 'text-info'],
      ];
      [$icon, $iconClass] = $iconMap[$n['type']] ?? ['bi-bell-fill', 'text-secondary'];
    ?>
    <a href="<?= APP_URL ?>/notifications/read.php?id=<?= $n['id'] ?>"
       class="list-group-item list-group-item-action py-3 px-4 <?= !$n['is_read'] ? 'bg-light' : '' ?>">
      <div class="d-flex align-items-start gap-3">
        <div class="mt-1 flex-shrink-0">
          <i class="bi <?= $icon ?> fs-5 <?= $iconClass ?>"></i>
        </div>
        <div class="flex-grow-1 min-width-0">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div class="fw-semibold <?= !$n['is_read'] ? 'text-dark' : 'text-secondary' ?>">
              <?= e($n['title']) ?>
            </div>
            <div class="text-muted small flex-shrink-0">
              <?= formatDate($n['created_at'], 'd M Y H:i') ?>
            </div>
          </div>
          <div class="small <?= !$n['is_read'] ? 'text-secondary' : 'text-muted' ?> mt-1">
            <?= e($n['body']) ?>
          </div>
        </div>
        <?php if (!$n['is_read']): ?>
        <div class="flex-shrink-0 mt-1">
          <span class="badge bg-primary rounded-pill" style="width:8px;height:8px;padding:0">&nbsp;</span>
        </div>
        <?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Pagination -->
<?php if ($paging['total_pages'] > 1): ?>
<div class="d-flex justify-content-between align-items-center mt-3">
  <small class="text-muted">Menampilkan <?= $paging['offset'] + 1 ?>–<?= min($paging['offset'] + 20, $total) ?> dari <?= $total ?></small>
  <nav><ul class="pagination pagination-sm mb-0">
    <?php for ($p = 1; $p <= $paging['total_pages']; $p++): ?>
    <li class="page-item <?= $p === $page ? 'active' : '' ?>">
      <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
    </li>
    <?php endfor; ?>
  </ul></nav>
</div>
<?php endif; ?>

<?php else: ?>
<div class="text-center py-5">
  <div class="mb-3" style="font-size:4rem">🔔</div>
  <h5 class="text-muted">
    <?= $filter === 'unread' ? 'Tidak ada notifikasi yang belum dibaca' : 'Belum ada notifikasi' ?>
  </h5>
  <?php if ($filter === 'unread'): ?>
  <a href="?" class="small">Lihat semua notifikasi</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
