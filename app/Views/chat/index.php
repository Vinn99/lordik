<?php
// public/chat/index.php

requireLogin();

$pdo    = getDB();
$userId = currentUserId();

// Get all conversations (applications) this user is party to
if (isAlumni()) {
    $stmt = $pdo->prepare(
        "SELECT a.id as app_id, a.status, a.applied_at,
                jv.title as vacancy_title, c.company_name, c.logo_path,
                (SELECT COUNT(*) FROM messages m WHERE m.application_id = a.id
                 AND m.sender_id != ? AND m.is_read = 0) as unread_count,
                (SELECT m2.message FROM messages m2 WHERE m2.application_id = a.id
                 ORDER BY m2.sent_at DESC LIMIT 1) as last_message,
                (SELECT m3.sent_at FROM messages m3 WHERE m3.application_id = a.id
                 ORDER BY m3.sent_at DESC LIMIT 1) as last_message_time
         FROM applications a
         JOIN job_vacancies jv ON jv.id = a.vacancy_id
         JOIN companies c ON c.id = jv.company_id
         JOIN alumni_profiles ap ON ap.id = a.alumni_id
         WHERE ap.user_id = ?
         ORDER BY last_message_time DESC, a.applied_at DESC"
    );
    $stmt->execute([$userId, $userId]);

} elseif (isCompany()) {
    $stmt = $pdo->prepare(
        "SELECT a.id as app_id, a.status, a.applied_at,
                jv.title as vacancy_title, ap.full_name as alumni_name, ap.jurusan,
                (SELECT COUNT(*) FROM messages m WHERE m.application_id = a.id
                 AND m.sender_id != ? AND m.is_read = 0) as unread_count,
                (SELECT m2.message FROM messages m2 WHERE m2.application_id = a.id
                 ORDER BY m2.sent_at DESC LIMIT 1) as last_message,
                (SELECT m3.sent_at FROM messages m3 WHERE m3.application_id = a.id
                 ORDER BY m3.sent_at DESC LIMIT 1) as last_message_time
         FROM applications a
         JOIN job_vacancies jv ON jv.id = a.vacancy_id
         JOIN companies c ON c.id = jv.company_id
         JOIN alumni_profiles ap ON ap.id = a.alumni_id
         WHERE c.user_id = ?
         ORDER BY last_message_time DESC, a.applied_at DESC"
    );
    $stmt->execute([$userId, $userId]);

} else {
    // Admin: see all conversations with messages
    $stmt = $pdo->prepare(
        "SELECT a.id as app_id, a.status, a.applied_at,
                jv.title as vacancy_title, c.company_name,
                ap.full_name as alumni_name, ap.jurusan,
                (SELECT COUNT(*) FROM messages m WHERE m.application_id = a.id AND m.is_read = 0) as unread_count,
                (SELECT m2.message FROM messages m2 WHERE m2.application_id = a.id
                 ORDER BY m2.sent_at DESC LIMIT 1) as last_message,
                (SELECT m3.sent_at FROM messages m3 WHERE m3.application_id = a.id
                 ORDER BY m3.sent_at DESC LIMIT 1) as last_message_time
         FROM applications a
         JOIN job_vacancies jv ON jv.id = a.vacancy_id
         JOIN companies c ON c.id = jv.company_id
         JOIN alumni_profiles ap ON ap.id = a.alumni_id
         WHERE EXISTS (SELECT 1 FROM messages mx WHERE mx.application_id = a.id)
         ORDER BY last_message_time DESC"
    );
    $stmt->execute();
}

$conversations = $stmt->fetchAll();

$pageTitle = 'Chat — ' . APP_NAME;
require_once __DIR__ . '/../../views/layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="fw-bold mb-0"><i class="bi bi-chat-dots me-2 text-primary"></i>Pesan</h4>
  <span class="badge bg-primary rounded-pill"><?= count($conversations) ?> percakapan</span>
</div>

<?php if ($conversations): ?>
<div class="row g-3">
  <?php foreach ($conversations as $conv): ?>
  <div class="col-12">
    <a href="<?= APP_URL ?>/alumni/application_detail.php?id=<?= $conv['app_id'] ?>"
       class="text-decoration-none">
      <div class="card border-0 shadow-sm <?= $conv['unread_count'] > 0 ? 'border-start border-primary border-3' : '' ?>"
           style="transition:box-shadow .2s" onmouseover="this.style.boxShadow='0 4px 15px rgba(0,0,0,.1)'" onmouseout="this.style.boxShadow=''">
        <div class="card-body py-3">
          <div class="d-flex align-items-center gap-3">
            <!-- Avatar -->
            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:48px;height:48px;background:#f1f5f9;font-size:1.4rem">
              <?= isAlumni() ? '🏢' : '👤' ?>
            </div>

            <!-- Content -->
            <div class="flex-grow-1 min-width-0">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-semibold text-dark">
                    <?= isAlumni() ? e($conv['company_name']) : e($conv['alumni_name'] ?? '') ?>
                    <?php if (isAdmin()): ?>
                    <span class="text-muted fw-normal">·</span>
                    <span class="text-muted"><?= e($conv['company_name'] ?? '') ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="small text-muted">
                    <i class="bi bi-briefcase me-1"></i><?= e($conv['vacancy_title']) ?>
                    <?php if (!isAlumni() && isset($conv['jurusan'])): ?>
                    · <span class="badge bg-light text-dark"><?= e($conv['jurusan']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="text-end flex-shrink-0 ms-2">
                  <div class="small text-muted">
                    <?= $conv['last_message_time'] ? formatDate($conv['last_message_time'], 'd M Y H:i') : formatDate($conv['applied_at'], 'd M Y') ?>
                  </div>
                  <?php if ($conv['unread_count'] > 0): ?>
                  <span class="badge bg-danger rounded-pill mt-1"><?= $conv['unread_count'] ?></span>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Last message preview -->
              <div class="mt-1 d-flex align-items-center justify-content-between gap-2">
                <div class="small text-muted text-truncate" style="max-width:400px">
                  <?php if ($conv['last_message']): ?>
                  <i class="bi bi-chat me-1"></i><?= e(truncate($conv['last_message'], 80)) ?>
                  <?php else: ?>
                  <i class="bi bi-chat-x me-1"></i><em>Belum ada pesan</em>
                  <?php endif; ?>
                </div>
                <?= statusBadge($conv['status']) ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<div class="text-center py-5">
  <div class="mb-3" style="font-size:4rem">💬</div>
  <h5 class="text-muted">Belum ada percakapan</h5>
  <p class="text-muted small">
    <?php if (isAlumni()): ?>
    Lamar lowongan untuk mulai chat dengan perusahaan.
    <a href="<?= APP_URL ?>/vacancy/list.php" class="d-block mt-2">Cari Lowongan →</a>
    <?php elseif (isCompany()): ?>
    Percakapan akan muncul setelah ada pelamar.
    <?php else: ?>
    Belum ada percakapan aktif.
    <?php endif; ?>
  </p>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../views/layouts/footer.php'; ?>
