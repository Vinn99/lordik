<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Admin — ' . APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css?v=<?= filemtime(BASE_PATH.'/public/assets/css/admin.css') ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>

<?php
$unreadNotif = countUnreadNotifications(currentUserId());
$unreadChat  = 0;
if (class_exists('ChatModule')) $unreadChat = ChatModule::countUnread(currentUserId());

$_uri = $_SERVER['REQUEST_URI'] ?? '';
function isActiveSection(string $path): string {
    global $_uri;
    return (str_contains($_uri, $path)) ? 'active' : '';
}

$_pdo = getDB();
$_pendingVacancies = (int)$_pdo->query("SELECT COUNT(*) FROM job_vacancies WHERE status='submitted' AND deleted_at IS NULL")->fetchColumn();

$grpPengguna = str_contains($_uri, '/admin/users')
            || str_contains($_uri, '/admin/alumni')
            || str_contains($_uri, '/admin/company')
            || str_contains($_uri, '/admin/user_credential');
$grpLowongan = str_contains($_uri, '/admin/vacancy')
            || str_contains($_uri, '/admin/applications');
$grpLaporan  = str_contains($_uri, '/admin/reports');
$grpSistem   = str_contains($_uri, '/admin/logs');
?>

<div id="sidebarOverlay" onclick="closeSidebar()"></div>

<aside id="adminSidebar">
    <a class="sidebar-brand" href="<?= APP_URL ?>/admin/dashboard.php">
        <img src="<?= APP_URL ?> /assets/images/logo.jpg" alt="LORDIK Logo" class="brand-icon">
        <div>
            <div class="brand-text">LORDIK</div>
            <div class="brand-sub">Admin Panel</div>
        </div>
    </a>

    <nav class="sidebar-nav" id="sidebarNav">

        <a href="<?= APP_URL ?>/admin/dashboard.php"
           class="sidebar-item <?= isActiveSection('/admin/dashboard') ?>">
            <i class="bi bi-speedometer2"></i><span>Dashboard</span>
        </a>

        <!-- GROUP: Pengguna -->
        <button class="sidebar-group-toggle <?= $grpPengguna ? 'open' : '' ?>"
                type="button" data-target="grpPengguna">
            <span class="sidebar-group-left">
                <i class="bi bi-people-fill"></i><span>Pengguna</span>
            </span>
            <i class="bi bi-chevron-right group-caret"></i>
        </button>
        <div class="sidebar-group-body <?= $grpPengguna ? 'show' : '' ?>" id="grpPengguna">
            <a href="<?= APP_URL ?>/admin/users.php"
               class="sidebar-item <?= (isActiveSection('/admin/users') || isActiveSection('/admin/user_credential')) ? 'active' : '' ?>">
                <i class="bi bi-person-lines-fill"></i><span>Manajemen Akun</span>
            </a>
            <a href="<?= APP_URL ?>/admin/alumni/list.php"
               class="sidebar-item <?= isActiveSection('/admin/alumni') ?>">
                <i class="bi bi-mortarboard"></i><span>Alumni</span>
            </a>
            <a href="<?= APP_URL ?>/admin/company/list.php"
               class="sidebar-item <?= isActiveSection('/admin/company') ?>">
                <i class="bi bi-building"></i><span>Perusahaan</span>
            </a>
        </div>

        <!-- GROUP: Lowongan -->
        <button class="sidebar-group-toggle <?= $grpLowongan ? 'open' : '' ?>"
                type="button" data-target="grpLowongan">
            <span class="sidebar-group-left">
                <i class="bi bi-briefcase"></i><span>Lowongan & Lamaran</span>
            </span>
            <i class="bi bi-chevron-right group-caret"></i>
        </button>
        <div class="sidebar-group-body <?= $grpLowongan ? 'show' : '' ?>" id="grpLowongan">
            <a href="<?= APP_URL ?>/admin/vacancy/list.php"
               class="sidebar-item <?= (str_contains($_uri,'/admin/vacancy') && !str_contains($_uri,'submitted')) ? 'active' : '' ?>">
                <i class="bi bi-list-ul"></i><span>Semua Lowongan</span>
            </a>
            <a href="<?= APP_URL ?>/admin/vacancy/list.php?status=submitted"
               class="sidebar-item <?= (str_contains($_uri,'/admin/vacancy') && str_contains($_uri,'submitted')) ? 'active' : '' ?>">
                <i class="bi bi-hourglass-split"></i><span>Menunggu Review</span>
                <?php if ($_pendingVacancies > 0): ?>
                <span class="sidebar-badge"><?= $_pendingVacancies ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= APP_URL ?>/admin/applications.php"
               class="sidebar-item <?= isActiveSection('/admin/applications') ?>">
                <i class="bi bi-inbox"></i><span>Semua Lamaran</span>
            </a>
        </div>

        <!-- GROUP: Laporan -->
        <button class="sidebar-group-toggle <?= $grpLaporan ? 'open' : '' ?>"
                type="button" data-target="grpLaporan">
            <span class="sidebar-group-left">
                <i class="bi bi-graph-up-arrow"></i><span>Laporan</span>
            </span>
            <i class="bi bi-chevron-right group-caret"></i>
        </button>
        <div class="sidebar-group-body <?= $grpLaporan ? 'show' : '' ?>" id="grpLaporan">
            <a href="<?= APP_URL ?>/admin/reports.php"
               class="sidebar-item <?= (str_contains($_uri,'/admin/reports') && !str_contains($_uri,'tab=')) ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line"></i><span>Statistik</span>
            </a>
            <a href="<?= APP_URL ?>/admin/reports.php?tab=rekap_alumni"
               class="sidebar-item <?= str_contains($_uri,'rekap_alumni') ? 'active' : '' ?>">
                <i class="bi bi-person-lines-fill"></i><span>Rekap Alumni</span>
            </a>
            <a href="<?= APP_URL ?>/admin/reports.php?tab=rekap_company"
               class="sidebar-item <?= str_contains($_uri,'rekap_company') ? 'active' : '' ?>">
                <i class="bi bi-building-check"></i><span>Rekap Perusahaan</span>
            </a>
            <a href="<?= APP_URL ?>/admin/reports.php?tab=preview"
               class="sidebar-item <?= str_contains($_uri,'tab=preview') ? 'active' : '' ?>">
                <i class="bi bi-download"></i><span>Export Data</span>
            </a>
        </div>

        <!-- GROUP: Sistem -->
        <button class="sidebar-group-toggle <?= $grpSistem ? 'open' : '' ?>"
                type="button" data-target="grpSistem">
            <span class="sidebar-group-left">
                <i class="bi bi-gear-fill"></i><span>Sistem</span>
            </span>
            <i class="bi bi-chevron-right group-caret"></i>
        </button>
        <div class="sidebar-group-body <?= $grpSistem ? 'show' : '' ?>" id="grpSistem">
            <a href="<?= APP_URL ?>/admin/logs.php"
               class="sidebar-item <?= isActiveSection('/admin/logs') ?>">
                <i class="bi bi-journal-text"></i><span>Activity Log</span>
            </a>
        </div>

    </nav>

    <div class="sidebar-user">
        <div class="avatar-xs">
            <i class="bi bi-shield-fill-check" style="color:rgba(255,255,255,.6)"></i>
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= e(currentUser()['username'] ?? 'Admin') ?></div>
            <div class="sidebar-user-role">Administrator</div>
        </div>
        <form method="POST" action="<?= APP_URL ?>/auth/logout.php" class="d-inline">
            <?= csrfField() ?>
            <button type="submit" class="sidebar-user-btn" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </div>
</aside>

<header id="adminTopbar">
    <button class="topbar-hamburger" onclick="toggleSidebar()" aria-label="Menu">
        <i class="bi bi-list"></i>
    </button>
    <div class="topbar-title" id="topbarPageTitle">
        <?= e($pageTitle ?? APP_NAME) ?>
    </div>
    <div class="topbar-actions">
        <a href="<?= APP_URL ?>/chat/" class="topbar-icon-btn" title="Pesan">
            <i class="bi bi-chat-dots"></i>
            <?php if ($unreadChat > 0): ?>
            <span class="topbar-notif-badge"><?= $unreadChat ?></span>
            <?php endif; ?>
        </a>
        <div class="dropdown">
            <button class="topbar-icon-btn" data-bs-toggle="dropdown" title="Notifikasi">
                <i class="bi bi-bell"></i>
                <?php if ($unreadNotif > 0): ?>
                <span class="topbar-notif-badge"><?= $unreadNotif ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end notif-dropdown">
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
                    <div class="text-muted" style="font-size:.7rem"><?= formatDate($n['created_at'], 'd M Y H:i') ?></div>
                </a>
                <?php endforeach; else: ?>
                <div class="dropdown-item text-muted text-center py-3 small">Tidak ada notifikasi</div>
                <?php endif; ?>
                <div class="border-top d-flex small">
                    <a class="dropdown-item text-center py-2 text-muted" href="<?= APP_URL ?>/notifications/mark_all.php">
                        <i class="bi bi-check-all me-1"></i>Tandai semua
                    </a>
                    <a class="dropdown-item text-center py-2 text-primary fw-semibold" href="<?= APP_URL ?>/notifications/list.php">
                        Lihat semua →
                    </a>
                </div>
            </div>
        </div>
        <div class="dropdown">
            <button class="topbar-icon-btn d-flex align-items-center gap-1 px-2" style="width:auto;padding:.4rem .6rem"
                    data-bs-toggle="dropdown">
                <div class="avatar-xs flex-shrink-0"><i class="bi bi-person-fill"></i></div>
                <i class="bi bi-chevron-down" style="font-size:.65rem;color:#94a3b8"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:10px;min-width:190px;font-size:.85rem">
                <li class="px-3 py-2 border-bottom">
                    <div class="fw-semibold small"><?= e(currentUser()['username'] ?? '') ?></div>
                    <div class="text-muted" style="font-size:.75rem"><?= e(currentUser()['email'] ?? '') ?></div>
                    <span class="badge bg-danger mt-1" style="font-size:.65rem">Administrator</span>
                </li>
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
    </div>
</header>

<main id="adminContent">

<?php foreach (getFlash() as $flash): ?>
<div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show mb-3" role="alert">
    <?= $flash['message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endforeach; ?>
