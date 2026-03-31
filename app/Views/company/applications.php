<?php
/**
 * app/Views/company/applications.php — Tim: Frontend
 * Daftar pelamar dengan card layout, foto, info penting, filter cepat
 */
requireCompany();

$pdo = getDB();
$co  = $pdo->prepare("SELECT id FROM companies WHERE user_id=? LIMIT 1");
$co->execute([currentUserId()]); $company = $co->fetch();
if (!$company) { setFlash('warning','Profil perusahaan belum dibuat.'); redirect('/company/profile.php'); }

$filters = ['vacancy_id'=>$_GET['vacancy_id']??'','status'=>$_GET['status']??'','search'=>$_GET['search']??''];
$page    = max(1,(int)($_GET['page']??1));
$result  = ApplicationModel::listByCompany($company['id'], $filters, $page);
$stats   = ApplicationModel::countByStatus($company['id']);
$vacStmt = $pdo->prepare("SELECT id,title FROM job_vacancies WHERE company_id=? AND deleted_at IS NULL ORDER BY created_at DESC");
$vacStmt->execute([$company['id']]); $vacancyList = $vacStmt->fetchAll();

$statusConfig = [
    ''            => ['Semua',        'secondary', array_sum($stats)],
    'pending'     => ['Pending',      'warning',   $stats['pending']??0],
    'frozen'      => ['Ditangguhkan', 'secondary', $stats['frozen']??0],
    'reviewed'    => ['Ditinjau',     'info',      $stats['reviewed']??0],
    'shortlisted' => ['Shortlist',    'primary',   $stats['shortlisted']??0],
    'accepted'    => ['Diterima',     'success',   $stats['accepted']??0],
    'rejected'    => ['Ditolak',      'danger',    $stats['rejected']??0],
];

$pageTitle = 'Manajemen Pelamar — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-3">
    <?= backButton('/company/dashboard.php') ?>
    <h4 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Manajemen Pelamar</h4>
</div>

<!-- Status quick filter -->
<div class="d-flex gap-1 flex-wrap mb-3">
    <?php foreach ($statusConfig as $sv => [$sl,$sc,$cnt]):
        $active = $filters['status'] === $sv; ?>
    <a href="?<?= http_build_query(array_merge($filters,['status'=>$sv,'page'=>1])) ?>"
       class="btn btn-sm <?= $active?"btn-{$sc}":"btn-outline-{$sc}" ?>">
        <?= $sl ?>
        <span class="badge <?= $active?"bg-white text-{$sc}":"bg-{$sc} text-white" ?> ms-1"><?= $cnt ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Search/Filter bar -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
            <div class="col-12 col-md-4">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Cari nama / jurusan..." value="<?= e($filters['search']) ?>">
            </div>
            <div class="col-12 col-md-5">
                <select name="vacancy_id" class="form-select form-select-sm">
                    <option value="">Semua Lowongan</option>
                    <?php foreach ($vacancyList as $v): ?>
                    <option value="<?=$v['id']?>" <?=$filters['vacancy_id']==(string)$v['id']?'selected':''?>>
                        <?= e(truncate($v['title'],60)) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-fill flex-md-grow-0">Filter</button>
                <a href="?" class="btn btn-sm btn-outline-secondary flex-fill flex-md-grow-0">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="text-muted small mb-3">
    Menampilkan <strong><?= count($result['data']) ?></strong> dari <strong><?= $result['total'] ?></strong> pelamar
</div>

<?php if ($result['data']): ?>
<div class="row g-3 mb-4">
    <?php foreach ($result['data'] as $app):
        $age = ageFromDate($app['birth_date']);
        $isFrozen = $app['status'] === 'frozen';
        $borderColors = ['pending'=>'warning','frozen'=>'secondary','reviewed'=>'info','shortlisted'=>'primary','accepted'=>'success','rejected'=>'danger'];
        $bc = $borderColors[$app['status']] ?? 'secondary';
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm h-100 <?=$isFrozen?'opacity-75':''?>"
             style="border-top:3px solid var(--bs-<?=$bc?>)!important;border-radius:12px;transition:.15s"
             onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
            <div class="card-body p-3">
                <!-- Photo + name + status -->
                <div class="d-flex gap-3 align-items-start mb-2">
                    <div class="avatar-sm flex-shrink-0" style="width:48px;height:48px;min-width:48px;border-radius:10px">
                        <?php if ($app['photo_path']): ?>
                        <img src="<?= APP_URL ?>/files/serve.php?type=photo&path=<?= urlencode($app['photo_path']) ?>" alt=""
                             style="border-radius:10px">
                        <?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
                    </div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="fw-bold text-truncate"><?= e($app['full_name']) ?></div>
                        <?= statusBadge($app['status']) ?>
                        <?php if ($isFrozen): ?>
                        <div style="font-size:.68rem;color:#6c757d;margin-top:.2rem">
                            <i class="bi bi-info-circle me-1"></i>Ditangguhkan sementara
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info chips -->
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <?php if ($app['gender']): ?>
                    <span class="badge bg-light text-dark border" style="font-size:.68rem">
                        <i class="bi bi-<?=$app['gender']==='female'?'gender-female text-danger':'gender-male text-info'?> me-1"></i>
                        <?=$app['gender']==='female'?'Perempuan':'Laki-laki'?>
                    </span>
                    <?php endif; ?>
                    <?php if ($age): ?>
                    <span class="badge bg-light text-dark border" style="font-size:.68rem">
                        <i class="bi bi-person me-1"></i><?=$age?> tahun
                    </span>
                    <?php endif; ?>
                    <span class="badge bg-light text-dark border" style="font-size:.68rem">
                        <i class="bi bi-mortarboard text-primary me-1"></i><?= e($app['jurusan']) ?>
                    </span>
                    <?php if ($app['graduation_year']): ?>
                    <span class="badge bg-light text-dark border" style="font-size:.68rem">
                        <?= e($app['graduation_year']) ?>
                    </span>
                    <?php endif; ?>
                </div>

                <div class="text-muted text-truncate small mb-1">
                    <i class="bi bi-briefcase me-1"></i><?= e($app['vacancy_title']) ?>
                </div>
                <div class="text-muted" style="font-size:.7rem">
                    <i class="bi bi-clock me-1"></i>Melamar <?= formatDate($app['applied_at'],'d M Y') ?>
                </div>
            </div>
            <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
                <div class="d-flex gap-2">
                    <a href="<?= APP_URL ?>/company/applicant_profile.php?app_id=<?= $app['id'] ?>"
                       class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-person-lines-fill me-1"></i>Profil
                    </a>
                    <a href="<?= APP_URL ?>/alumni/application_detail.php?id=<?= $app['id'] ?>"
                       class="btn btn-sm btn-outline-secondary flex-fill">
                        <i class="bi bi-chat-dots me-1"></i>Chat
                    </a>
                    <?php if ($app['cv_path']): ?>
                    <a href="<?= APP_URL ?>/files/serve.php?type=cv&path=<?= urlencode($app['cv_path']) ?>"
                       target="_blank" class="btn btn-sm btn-outline-danger" title="CV">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="text-center py-5">
    <i class="bi bi-inbox display-4 text-muted d-block mb-3"></i>
    <h5 class="text-muted">Belum ada pelamar</h5>
    <?php if ($filters['status']||$filters['vacancy_id']||$filters['search']): ?>
    <a href="?" class="btn btn-outline-secondary btn-sm mt-2">Reset Filter</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="d-flex justify-content-center"><?= paginationLinks($result['pager'], $_GET) ?></div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
