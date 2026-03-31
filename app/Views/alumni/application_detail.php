<?php
// public/alumni/application_detail.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireLogin();

$appId = (int)($_GET['id'] ?? 0);
$app   = ApplicationModel::getById($appId);

if (!$app) {
    setFlash('danger', 'Lamaran tidak ditemukan.');
    redirect('/alumni/applications.php');
}

// Authorization: alumni must own, company must own the vacancy, admin can view all
if (isAlumni() && (int)$app['alumni_user_id'] !== currentUserId()) {
    http_response_code(403);
    setFlash('danger', 'Akses ditolak.');
    redirect('/alumni/applications.php');
}

// Handle chat POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $message = trim($_POST['message'] ?? '');
    if ($message) {
        $result = MessageModel::sendMessage($appId, currentUserId(), $message);
        if (!$result['success']) setFlash('danger', $result['message']);
    }
    redirect($_SERVER['REQUEST_URI']);
}

// Mark messages as read
MessageModel::markRead($appId, currentUserId());

$messages = MessageModel::getMessages($appId, currentUserId());
$logs     = ApplicationModel::getStatusLogs($appId);

$pageTitle = 'Detail Lamaran — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<?= backButton() ?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= isAlumni() ? APP_URL.'/alumni/applications.php' : APP_URL.'/company/applications.php' ?>">Lamaran</a></li>
        <li class="breadcrumb-item active">Detail Lamaran #<?= $appId ?></li>
    </ol>
</nav>

<div class="row g-4">
    <!-- Left: Application Info + Status History -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom fw-semibold">Info Lamaran</div>
            <div class="card-body">
                <div class="table-wrap">
<table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted small">Posisi</td>
                        <td class="small fw-semibold"><?= e($app['vacancy_title']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Perusahaan</td>
                        <td class="small"><?= e($app['company_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Pelamar</td>
                        <td class="small"><?= e($app['alumni_name']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Status</td>
                        <td><?= statusBadge($app['status']) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted small">Dilamar</td>
                        <td class="small"><?= formatDate($app['applied_at'], 'd M Y H:i') ?></td>
                    </tr>
                </table>
</div>

                <?php if ($app['cover_letter']): ?>
                <hr>
                <h6 class="small fw-semibold">Cover Letter</h6>
                <p class="small text-secondary" style="white-space:pre-wrap;"><?= e($app['cover_letter']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status History -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom fw-semibold">
                <i class="bi bi-clock-history me-2"></i>Riwayat Status
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach ($logs as $log): ?>
                    <li class="list-group-item py-2">
                        <div class="d-flex justify-content-between">
                            <div>
                                <?php if ($log['old_status']): ?>
                                <small class="text-muted"><?= statusBadge($log['old_status']) ?></small>
                                <i class="bi bi-arrow-right text-muted mx-1"></i>
                                <?php endif; ?>
                                <?= statusBadge($log['new_status']) ?>
                            </div>
                            <small class="text-muted"><?= formatDate($log['created_at'], 'd M Y H:i') ?></small>
                        </div>
                        <?php if ($log['notes']): ?>
                        <div class="small text-muted mt-1"><?= e($log['notes']) ?></div>
                        <?php endif; ?>
                        <div class="small text-muted">oleh: <?= e($log['username'] ?? 'system') ?></div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- Update Status (company/admin) -->
        <?php if (isCompany() || isAdmin()): ?>
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header bg-white border-bottom fw-semibold">Update Status</div>
            <div class="card-body">
                <form method="POST" action="<?= APP_URL ?>/application/update_status.php">
                    <?= csrfField() ?>
                    <input type="hidden" name="application_id" value="<?= $appId ?>">
                    <div class="mb-2">
                        <select name="status" class="form-select form-select-sm" required>
                            <?php foreach (['pending','reviewed','shortlisted','rejected','accepted'] as $s): ?>
                            <option value="<?= $s ?>" <?= $app['status'] === $s ? 'selected' : '' ?>>
                                <?= ucwords(str_replace('_', ' ', $s)) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <textarea name="notes" class="form-control form-control-sm" rows="2"
                                  placeholder="Catatan (opsional)"></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary w-100">Update Status</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right: Chat -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0" style="height:600px;display:flex;flex-direction:column;">
            <div class="card-header bg-white border-bottom fw-semibold">
                <i class="bi bi-chat-dots me-2 text-primary"></i>
                Chat — <?= e($app['vacancy_title']) ?>
            </div>
            <!-- Messages -->
            <div class="card-body overflow-auto flex-fill p-3" id="chatBox">
                <?php if ($messages): ?>
                    <?php foreach ($messages as $msg):
                        $isMine = (int)$msg['sender_id'] === currentUserId();
                    ?>
                    <div class="d-flex <?= $isMine ? 'justify-content-end' : 'justify-content-start' ?> mb-3">
                        <div class="<?= $isMine ? 'bg-primary text-white' : 'bg-light text-dark' ?> rounded-3 px-3 py-2"
                             style="max-width:75%;">
                            <?php if (!$isMine): ?>
                            <div class="fw-semibold small mb-1">
                                <?= e($msg['username']) ?>
                                <span class="badge bg-secondary ms-1" style="font-size:.7rem"><?= e($msg['role']) ?></span>
                            </div>
                            <?php endif; ?>
                            <div style="white-space:pre-wrap;"><?= e($msg['message']) ?></div>
                            <div class="mt-1 <?= $isMine ? 'text-white-50' : 'text-muted' ?>" style="font-size:.7rem;text-align:<?= $isMine ? 'right' : 'left' ?>">
                                <?= formatDate($msg['sent_at'], 'd M Y H:i') ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-chat fs-2 d-block mb-2"></i>
                    Belum ada pesan. Mulai percakapan.
                </div>
                <?php endif; ?>
            </div>
            <!-- Chat input -->
            <div class="card-footer bg-white border-top p-3">
                <form method="POST" class="d-flex gap-2">
                    <?= csrfField() ?>
                    <input type="text" name="message" class="form-control"
                           placeholder="Ketik pesan..." autocomplete="off" required>
                    <button type="submit" class="btn btn-primary px-3">
                        <i class="bi bi-send"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-scroll chat to bottom
const chatBox = document.getElementById('chatBox');
if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
</script>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
