<?php
/**
 * app/Views/vacancy/list.php — Tim: Frontend
 */
requireLogin();

$filters = [
    'status'   => 'approved',
    'job_type' => $_GET['job_type'] ?? '',
    'jurusan'  => $_GET['jurusan']  ?? '',
    'search'   => $_GET['search']   ?? '',
];
$page   = max(1,(int)($_GET['page']??1));
$result = VacancyModel::list($filters, PER_PAGE, ($page-1)*PER_PAGE);
$paging = paginate($result['total'], $page);

$pdo = getDB();
$appCounts = [];
$stmt = $pdo->query("SELECT vacancy_id, COUNT(*) as cnt FROM applications GROUP BY vacancy_id");
foreach ($stmt->fetchAll() as $r) $appCounts[$r['vacancy_id']] = $r['cnt'];

$myApplied = []; $hasActiveApp = false; $isEmployed = false;
if (isAlumni()) {
    $pr = $pdo->prepare("SELECT id, work_status FROM alumni_profiles WHERE user_id=? LIMIT 1");
    $pr->execute([currentUserId()]); $myProfile = $pr->fetch();
    if ($myProfile) {
        $isEmployed = $myProfile['work_status'] === 'employed';
        $ra = $pdo->prepare("SELECT vacancy_id, status FROM applications WHERE alumni_id=?");
        $ra->execute([$myProfile['id']]);
        foreach ($ra->fetchAll() as $r) $myApplied[$r['vacancy_id']] = $r['status'];
        $stAct = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE alumni_id=? AND status IN ('reviewed','shortlisted')");
        $stAct->execute([$myProfile['id']]); $hasActiveApp = (int)$stAct->fetchColumn() > 0;
    }
}

$pageTitle = 'Daftar Lowongan — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= APP_URL ?>" class="btn-back flex-shrink-0"><i class="bi bi-arrow-left"></i> <span class="d-none d-sm-inline">Beranda</span></a>
    <div class="min-width-0">
        <h5 class="fw-bold mb-0 text-truncate"><i class="bi bi-briefcase me-2 text-primary"></i>Lowongan Tersedia</h5>
        <div class="text-muted" style="font-size:.78rem"><?= $result['total'] ?> lowongan aktif</div>
    </div>
</div>

<?php if (isAlumni() && ($hasActiveApp || $isEmployed)): ?>
<div class="alert alert-info border-0 d-flex gap-3 align-items-start mb-3 py-2 px-3" style="border-radius:10px">
    <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-1"></i>
    <div class="small">
        <?php if ($isEmployed): ?>
        Status Anda: <strong>Bekerja</strong>. Lamaran baru tidak dapat dikirim.
        <?php else: ?>
        Anda memiliki <strong>lamaran yang sedang diproses</strong>.
        Lamaran baru bisa dikirim setelah proses tersebut selesai.
        <a href="<?= APP_URL ?>/alumni/applications.php" class="alert-link">Lihat lamaran →</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Filter -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0"
                           placeholder="Cari posisi atau perusahaan..." value="<?= e($filters['search']) ?>">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="job_type" class="form-select form-select-sm">
                    <option value="">Semua Tipe</option>
                    <?php foreach (['full_time'=>'Full Time','part_time'=>'Part Time','contract'=>'Kontrak','internship'=>'Magang'] as $v=>$l): ?>
                    <option value="<?=$v?>" <?=$filters['job_type']===$v?'selected':''?>><?=$l?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="jurusan" class="form-select form-select-sm">
                    <option value="">Semua Jurusan</option>
                    <?php foreach (['RPL','DKV','AKL','MPK','BDP','LP3K','LPB','ULW'] as $j): ?>
                    <option value="<?=$j?>" <?=$filters['jurusan']===$j?'selected':''?>><?=$j?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill flex-md-grow-0">Cari</button>
                <?php if ($filters['search']||$filters['job_type']||$filters['jurusan']): ?>
                <a href="?" class="btn btn-outline-secondary btn-sm flex-fill flex-md-grow-0">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($result['data']): ?>
<div class="row g-3 mb-4">
    <?php foreach ($result['data'] as $v):
        $appliedStatus = $myApplied[$v['id']] ?? null;
        $appCount      = $appCounts[$v['id']] ?? 0;
        $deadlinePast  = $v['deadline'] && strtotime($v['deadline']) < strtotime('today');
        $canApply      = isAlumni() && !$appliedStatus && !$isEmployed && !$hasActiveApp && !$deadlinePast;
        $statusColors  = ['pending'=>'warning','frozen'=>'secondary','reviewed'=>'info','shortlisted'=>'primary','accepted'=>'success','rejected'=>'danger'];
        $borderColor   = $appliedStatus ? "border-top:3px solid var(--bs-".($statusColors[$appliedStatus]??'success').")!important" : '';
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 border-0 shadow-sm" style="<?=$borderColor?>;border-radius:12px;transition:.2s"
             onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.1)'"
             onmouseout="this.style.transform='';this.style.boxShadow=''">
            <div class="card-body p-3">
                <div class="d-flex gap-3 align-items-start mb-2">
                    <div class="bg-light rounded-2 d-flex align-items-center justify-content-center flex-shrink-0"
                         style="width:44px;height:44px;min-width:44px;border-radius:8px!important">
                        <?php if ($v['logo_path']): ?>
                        <img src="<?= APP_URL ?>/files/serve.php?type=logo&path=<?= urlencode($v['logo_path']) ?>"
                             style="width:40px;height:40px;object-fit:contain;border-radius:8px" alt="">
                        <?php else: ?><i class="bi bi-building fs-5 text-secondary"></i><?php endif; ?>
                    </div>
                    <div class="flex-grow-1 min-width-0">
                        <div class="fw-bold small text-truncate"><?= e($v['title']) ?></div>
                        <div class="text-muted text-truncate" style="font-size:.75rem"><?= e($v['company_name']) ?></div>
                    </div>
                    <?php if ($appliedStatus): ?>
                    <div class="flex-shrink-0"><?= statusBadge($appliedStatus) ?></div>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:.68rem">
                        <i class="bi bi-briefcase me-1"></i><?= ucwords(str_replace('_',' ',$v['job_type'])) ?>
                    </span>
                    <?php if ($v['city']): ?>
                    <span class="badge bg-light text-dark border" style="font-size:.68rem">
                        <i class="bi bi-geo-alt me-1"></i><?= e($v['city']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($v['salary_min']): ?>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle" style="font-size:.68rem">
                        <i class="bi bi-cash me-1"></i><?= formatCurrency($v['salary_min']) ?>+
                    </span>
                    <?php endif; ?>
                    <?php if ($v['jurusan_required']): ?>
                    <span class="badge bg-warning bg-opacity-15 text-warning-emphasis border border-warning-subtle" style="font-size:.68rem">
                        <i class="bi bi-mortarboard me-1"></i><?= e($v['jurusan_required']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-3 text-muted" style="font-size:.7rem">
                    <span><i class="bi bi-people me-1"></i><?= $appCount ?> pelamar</span>
                    <?php if ($v['slots']): ?>
                    <span><i class="bi bi-person-check me-1"></i><?= $v['slots'] ?> kuota</span>
                    <?php endif; ?>
                    <?php if ($v['deadline']): ?>
                    <span class="<?= $deadlinePast?'text-danger fw-semibold':'' ?>">
                        <i class="bi bi-calendar3 me-1"></i>
                        <?= $deadlinePast?'Lewat ':'Tutup '.formatDate($v['deadline'],'d M Y') ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-footer bg-white border-top-0 pt-0 pb-3 px-3">
                <div class="d-flex gap-2">
                    <a href="<?= APP_URL ?>/vacancy/detail.php?id=<?= $v['id'] ?>"
                       class="btn btn-sm btn-outline-primary flex-fill">Detail</a>
                    <?php if ($canApply): ?>
                    <a href="<?= APP_URL ?>/vacancy/detail.php?id=<?= $v['id'] ?>#lamar"
                       class="btn btn-sm btn-primary flex-fill">Lamar</a>
                    <?php elseif ($appliedStatus && in_array($appliedStatus,['pending','frozen','reviewed','shortlisted'])): ?>
                    <a href="<?= APP_URL ?>/alumni/applications.php"
                       class="btn btn-sm btn-outline-success flex-fill">Cek Status</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="text-center py-5">
    <i class="bi bi-search display-4 text-muted d-block mb-3"></i>
    <h5 class="text-muted">Tidak ada lowongan ditemukan</h5>
    <?php if ($filters['search']||$filters['job_type']||$filters['jurusan']): ?>
    <a href="?" class="btn btn-outline-primary btn-sm mt-2">Lihat Semua Lowongan</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="d-flex justify-content-center"><?= paginationLinks($paging, $_GET) ?></div>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
