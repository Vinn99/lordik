<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/frontend.css?v=<?= filemtime(BASE_PATH.'/public/assets/css/frontend.css') ?>">
</head>
<body>

<?php
$unreadNotif = countUnreadNotifications(currentUserId());
$unreadChat  = 0;
if (class_exists('ChatModule')) $unreadChat = ChatModule::countUnread(currentUserId());

// Foto profil
$_fp = null;
$_pdo = getDB();
if (isAlumni()) {
    $st = $_pdo->prepare("SELECT photo_path FROM alumni_profiles WHERE user_id = ? LIMIT 1");
    $st->execute([currentUserId()]);
    $_fp = $st->fetch()['photo_path'] ?? null;
} elseif (isCompany()) {
    $st = $_pdo->prepare("SELECT logo_path FROM companies WHERE user_id = ? LIMIT 1");
    $st->execute([currentUserId()]);
    $_fp = $st->fetch()['logo_path'] ?? null;
}

// Current URI for active link detection
$_uri = $_SERVER['REQUEST_URI'] ?? '';
function feIsActive(string $path): string {
    global $_uri;
    return str_contains($_uri, $path) ? 'active' : '';
}
?>

<!-- ══════════════════════════════════════════════
     TOP NAVBAR
══════════════════════════════════════════════ -->
<nav id="frontendNav">

    <!-- Brand -->
    <a href="<?= APP_URL ?>" class="fe-brand">
        <span class="fe-brand-icon"><img src="<?= APP_URL ?>/assets/images/logo.jpg" alt="LORDIK Logo" style="width:100%;height:100%;object-fit:cover;border-radius:9px"></span>
        <span class="fe-brand-name d-none d-sm-inline">LORDIK</span>
    </a>

    <!-- Main nav links (desktop) -->
    <?php if (isAlumni()): ?>
    <ul class="fe-nav-links">
        <li><a href="<?= APP_URL ?>/alumni/dashboard.php" class="<?= feIsActive('/alumni/dashboard') ?>">
            <i class="bi bi-house"></i> Beranda
        </a></li>
        <li><a href="<?= APP_URL ?>/vacancy/list.php" class="<?= feIsActive('/vacancy/') ?>">
            <i class="bi bi-search"></i> Cari Lowongan
        </a></li>
        <li><a href="<?= APP_URL ?>/alumni/applications.php" class="<?= feIsActive('/alumni/applications') ?>">
            <i class="bi bi-file-earmark-text"></i> Lamaran Saya
        </a></li>
        <li><a href="<?= APP_URL ?>/alumni/profile.php" class="<?= feIsActive('/alumni/profile') ?>">
            <i class="bi bi-person"></i> Profil
        </a></li>
    </ul>

    <?php elseif (isCompany()): ?>
    <ul class="fe-nav-links">
        <li><a href="<?= APP_URL ?>/company/dashboard.php" class="<?= feIsActive('/company/dashboard') ?>">
            <i class="bi bi-house"></i> Beranda
        </a></li>
        <li><a href="<?= APP_URL ?>/company/vacancy/list.php" class="<?= feIsActive('/company/vacancy') ?>">
            <i class="bi bi-briefcase"></i> Lowongan Saya
        </a></li>
        <li><a href="<?= APP_URL ?>/company/applications.php" class="<?= feIsActive('/company/applications') || feIsActive('/company/applicant') ?>">
            <i class="bi bi-people"></i> Pelamar
        </a></li>
        <li><a href="<?= APP_URL ?>/company/profile.php" class="<?= feIsActive('/company/profile') ?>">
            <i class="bi bi-building"></i> Profil Perusahaan
        </a></li>
    </ul>
    <?php endif; ?>

    <!-- Right side -->
    <div class="fe-nav-right">
        <?php if (!isLoggedIn()): ?>
        <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-primary btn-sm">
            <i class="bi bi-box-arrow-in-right me-1"></i>Login
        </a>
        <?php endif; ?>
        <?php if (isLoggedIn()): ?>

        <!-- Chat -->
        <a href="<?= APP_URL ?>/chat/" class="fe-icon-btn" title="Pesan">
            <i class="bi bi-chat-dots"></i>
            <?php if ($unreadChat > 0): ?>
            <span class="fe-notif-dot"><?= $unreadChat ?></span>
            <?php endif; ?>
        </a>

        <!-- Notifications -->
        <div class="dropdown">
            <button class="fe-icon-btn" data-bs-toggle="dropdown" title="Notifikasi">
                <i class="bi bi-bell"></i>
                <?php if ($unreadNotif > 0): ?>
                <span class="fe-notif-dot"><?= $unreadNotif ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0 border-0 shadow"
                 style="width:310px;max-height:380px;overflow-y:auto;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.12)!important">
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <span class="fw-semibold small">Notifikasi</span>
                    <?php if ($unreadNotif > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?= $unreadNotif ?> baru</span>
                    <?php endif; ?>
                </div>
                <?php $notifs = getNotifications(currentUserId(), 8);
                if ($notifs): foreach ($notifs as $n): ?>
                <a class="dropdown-item py-2 px-3 small <?= !$n['is_read'] ? 'bg-light' : '' ?>"
                   href="<?= APP_URL ?>/notifications/read.php?id=<?= $n['id'] ?>">
                    <div class="fw-semibold text-truncate"><?= e($n['title']) ?></div>
                    <div class="text-muted"><?= e(truncate($n['body'], 60)) ?></div>
                    <div class="text-muted" style="font-size:.7rem"><?= formatDate($n['created_at'],'d M Y H:i') ?></div>
                </a>
                <?php endforeach; else: ?>
                <div class="dropdown-item text-muted text-center py-3 small">Tidak ada notifikasi</div>
                <?php endif; ?>
                <div class="border-top d-flex small">
                    <a class="dropdown-item text-center py-2 text-muted" href="<?= APP_URL ?>/notifications/mark_all.php">
                        <i class="bi bi-check-all me-1"></i>Tandai semua
                    </a>
                    <a class="dropdown-item text-center py-2 fw-semibold" style="color:#2563eb" href="<?= APP_URL ?>/notifications/list.php">
                        Lihat semua →
                    </a>
                </div>
            </div>
        </div>

        <!-- User avatar dropdown -->
        <div class="dropdown">
            <button class="d-flex align-items-center gap-2 border-0 bg-transparent p-0 cursor-pointer"
                    data-bs-toggle="dropdown" style="cursor:pointer">
                <div class="fe-avatar">
                    <?php if ($_fp): ?>
                    <img src="<?= APP_URL ?>/files/serve.php?type=<?= isAlumni() ? 'photo' : 'logo' ?>&path=<?= urlencode($_fp) ?>" alt="">
                    <?php else: ?>
                    <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </div>
                <i class="bi bi-chevron-down d-none d-md-inline" style="font-size:.65rem;color:#9ca3af"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;min-width:210px;font-size:.875rem">
                <li class="px-3 py-2 border-bottom">
                    <?php if ($_fp): ?>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <div class="avatar-circle" style="width:32px;height:32px;min-width:32px">
                            <img src="<?= APP_URL ?>/files/serve.php?type=<?= isAlumni() ? 'photo' : 'logo' ?>&path=<?= urlencode($_fp) ?>" alt="">
                        </div>
                        <div>
                            <div class="fw-semibold small"><?= e(currentUser()['username'] ?? '') ?></div>
                            <div class="text-muted" style="font-size:.72rem"><?= e(currentUser()['email'] ?? '') ?></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="fw-semibold small"><?= e(currentUser()['username'] ?? '') ?></div>
                    <div class="text-muted" style="font-size:.72rem"><?= e(currentUser()['email'] ?? '') ?></div>
                    <?php endif; ?>
                    <span class="badge mt-1 <?= isAlumni() ? 'bg-info' : 'bg-success' ?>" style="font-size:.68rem">
                        <?= isAlumni() ? 'Alumni' : 'Perusahaan' ?>
                    </span>
                </li>
                <?php if (isAlumni()): ?>
                <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/alumni/profile.php">
                    <i class="bi bi-person me-2 text-secondary"></i>Profil Saya</a></li>
                <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/alumni/applications.php">
                    <i class="bi bi-file-earmark-text me-2 text-secondary"></i>Lamaran Saya</a></li>
                <?php else: ?>
                <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/company/profile.php">
                    <i class="bi bi-building me-2 text-secondary"></i>Profil Perusahaan</a></li>
                <?php endif; ?>
                <li><a class="dropdown-item py-2" href="<?= APP_URL ?>/auth/change_password.php">
                    <i class="bi bi-key me-2 text-secondary"></i>Ganti Password</a></li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <form method="POST" action="<?= APP_URL ?>/auth/logout.php">
                        <?= csrfField() ?>
                        <button type="submit" class="dropdown-item text-danger py-2">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </button>
                    </form>
                </li>
            </ul>
        </div>

        <?php endif; // isLoggedIn ?>
        <!-- Mobile hamburger -->
        <button class="fe-hamburger" onclick="toggleMobileMenu()" id="hamburgerBtn">
            <i class="bi bi-list"></i>
        </button>
    </div>
</nav>

<!-- Mobile menu -->
<div id="mobileMenu">
    <?php if (isAlumni()): ?>
    <a href="<?= APP_URL ?>/alumni/dashboard.php"><i class="bi bi-house me-2"></i>Beranda</a>
    <a href="<?= APP_URL ?>/vacancy/list.php"><i class="bi bi-search me-2"></i>Cari Lowongan</a>
    <a href="<?= APP_URL ?>/alumni/applications.php"><i class="bi bi-file-earmark-text me-2"></i>Lamaran Saya</a>
    <a href="<?= APP_URL ?>/alumni/profile.php"><i class="bi bi-person me-2"></i>Profil</a>
    <?php else: ?>
    <a href="<?= APP_URL ?>/company/dashboard.php"><i class="bi bi-house me-2"></i>Beranda</a>
    <a href="<?= APP_URL ?>/company/vacancy/list.php"><i class="bi bi-briefcase me-2"></i>Lowongan</a>
    <a href="<?= APP_URL ?>/company/applications.php"><i class="bi bi-people me-2"></i>Pelamar</a>
    <a href="<?= APP_URL ?>/company/profile.php"><i class="bi bi-building me-2"></i>Profil</a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/chat/"><i class="bi bi-chat-dots me-2"></i>Pesan
        <?php if ($unreadChat > 0): ?><span class="nav-badge ms-1"><?= $unreadChat ?></span><?php endif; ?>
    </a>
    <hr>
    <a href="<?= APP_URL ?>/auth/change_password.php"><i class="bi bi-key me-2 text-secondary"></i>Ganti Password</a>
    <form method="POST" action="<?= APP_URL ?>/auth/logout.php" style="margin:0">
        <?= csrfField() ?>
        <button type="submit" style="all:unset;display:flex;align-items:center;gap:.5rem;padding:.65rem .7rem;border-radius:8px;color:#ef4444;font-size:.9rem;width:100%;cursor:pointer;margin-bottom:.1rem">
            <i class="bi bi-box-arrow-right"></i>Logout
        </button>
    </form>
</div>

<!-- ══════════════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════════════ -->
<div class="fe-content">

<!-- Flash messages -->
<?php foreach (getFlash() as $flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show mb-3" role="alert">
    <?= $flash['message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endforeach; ?>

