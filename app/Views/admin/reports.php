<?php
// public/admin/reports.php — v2: Rekap Alumni, Rekap Perusahaan, Preview Export
require_once BASE_PATH . '/helpers/notification_helper.php';

requireAdmin();

$pdo = getDB();

// ── Filters ──────────────────────────────────────────────────
$jurusan      = $_GET['jurusan']         ?? '';
$workStatus   = $_GET['work_status']     ?? '';
$gradYear     = $_GET['graduation_year'] ?? '';
$tab          = $_GET['tab']             ?? 'statistik';  // statistik | rekap_alumni | rekap_company | preview

// ── EXPORT (actual download) ──────────────────────────────────
if (isset($_GET['export'])) {
    $filters = compact('jurusan', 'workStatus', 'gradYear');
    $filters = ['jurusan' => $jurusan, 'work_status' => $workStatus, 'graduation_year' => $gradYear];

    if ($_GET['export'] === 'csv') {
        $filepath = AdminModel::exportAlumniCSV($filters);
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: max-age=0');
        readfile($filepath); exit;
    }
    if ($_GET['export'] === 'pdf') {
        $filepath = AdminModel::exportAlumniPDF($filters);
        header('Content-Type: text/html; charset=UTF-8');
        readfile($filepath); exit;
    }
    if ($_GET['export'] === 'csv_company') {
        // Export company sebagai Excel XML (.xls)
        $stmt = $pdo->query(
            "SELECT c.company_name, c.industry, c.city, c.phone, u.email,
                    c.verified, c.created_at,
                    COUNT(jv.id) as total_vacancies,
                    SUM(jv.status='approved') as active_vacancies
             FROM companies c
             JOIN users u ON u.id = c.user_id
             LEFT JOIN job_vacancies jv ON jv.company_id = c.id AND jv.deleted_at IS NULL
             GROUP BY c.id ORDER BY c.company_name"
        );
        $rows = $stmt->fetchAll();
        $fname = 'perusahaan_' . date('Ymd_His') . '.xls';

        $headers = ['Nama Perusahaan','Industri','Kota','Telepon','Email','Terverifikasi','Total Lowongan','Lowongan Aktif','Terdaftar'];

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";
        $xml .= '<Styles>';
        $xml .= '<Style ss:ID="header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#059669" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
        $xml .= '<Style ss:ID="cell"><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
        $xml .= '<Style ss:ID="cellAlt"><Alignment ss:Vertical="Center"/><Interior ss:Color="#F0FDF4" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
        $xml .= '</Styles>';
        $xml .= '<Worksheet ss:Name="Data Perusahaan"><Table>';

        foreach ([150, 110, 100, 100, 160, 90, 100, 100, 100] as $w) {
            $xml .= '<Column ss:Width="' . $w . '"/>';
        }

        $xml .= '<Row ss:Height="22">';
        foreach ($headers as $h) {
            $xml .= '<Cell ss:StyleID="header"><Data ss:Type="String">' . htmlspecialchars($h, ENT_XML1) . '</Data></Cell>';
        }
        $xml .= '</Row>';

        foreach ($rows as $i => $r) {
            $style = ($i % 2 === 0) ? 'cell' : 'cellAlt';
            $xml .= '<Row ss:Height="18">';
            $cells = [
                $r['company_name'], $r['industry'], $r['city'], $r['phone'], $r['email'],
                $r['verified'] ? 'Ya' : 'Tidak',
                $r['total_vacancies'], $r['active_vacancies'],
                date('d/m/Y', strtotime($r['created_at']))
            ];
            foreach ($cells as $cell) {
                $xml .= '<Cell ss:StyleID="' . $style . '"><Data ss:Type="String">' . htmlspecialchars((string)$cell, ENT_XML1) . '</Data></Cell>';
            }
            $xml .= '</Row>';
        }

        $xml .= '</Table></Worksheet></Workbook>';

        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header("Content-Disposition: attachment; filename=\"{$fname}\"");
        header('Cache-Control: max-age=0');
        echo $xml; exit;
    }
}

// ── Data for all tabs ──────────────────────────────────────────
$stats = AdminModel::getDashboardStats();

// Rekap Alumni data
$alumniFilters = ['jurusan' => $jurusan, 'work_status' => $workStatus, 'graduation_year' => $gradYear];
$alumniResult  = AlumniModel::searchAlumni($alumniFilters, 1000, 0);
$alumniData    = $alumniResult['data'];

// Rekap Company data
$compStmt = $pdo->prepare(
    "SELECT c.*, u.email, u.username,
            COUNT(DISTINCT jv.id) as total_vacancies,
            COUNT(DISTINCT CASE WHEN jv.status='approved' THEN jv.id END) as active_vacancies,
            COUNT(DISTINCT a.id) as total_applications,
            COUNT(DISTINCT CASE WHEN a.status='accepted' THEN a.id END) as accepted_applications
     FROM companies c
     JOIN users u ON u.id = c.user_id
     LEFT JOIN job_vacancies jv ON jv.company_id = c.id AND jv.deleted_at IS NULL
     LEFT JOIN applications a ON a.vacancy_id = jv.id
     GROUP BY c.id ORDER BY c.company_name"
);
$compStmt->execute();
$companyData = $compStmt->fetchAll();

// Jurusan list for filter
$jurusanList = ['RPL','DKV','AKL','MPK','BDP','LP3K','LPB','ULW'];

$pageTitle = 'Laporan & Statistik — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-3">
    <?= backButton('/admin/dashboard.php') ?>
    <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart me-2 text-primary"></i>Laporan & Statistik</h4>
</div>

<!-- Tabs -->
<ul class="nav nav-pills mb-4 gap-1" id="reportTabs">
    <?php
    $tabs = [
        'statistik'    => ['bi-graph-up',           'Statistik'],
        'rekap_alumni' => ['bi-person-lines-fill',   'Rekap Alumni'],
        'rekap_company'=> ['bi-building-check',      'Rekap Perusahaan'],
        'preview'      => ['bi-eye',                 'Preview & Export'],
    ];
    foreach ($tabs as $k => [$icon, $label]):
    ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab === $k ? 'active' : 'bg-white border text-secondary' ?>"
           href="?tab=<?= $k ?>">
            <i class="bi bi-<?= $icon ?> me-1"></i><?= $label ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'statistik'): ?>
<!-- ══════════════════════════════════════════════════
     TAB 1: Statistik
════════════════════════════════════════════════════ -->

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <?php $cards = [
        ['Total Alumni',     $stats['total_alumni'],       'primary', 'mortarboard'],
        ['Bekerja',          $stats['employed_alumni'],    'success', 'briefcase'],
        ['Belum Bekerja',    $stats['unemployed_alumni'],  'danger',  'clock'],
        ['Lowongan Aktif',   $stats['active_vacancies'],   'warning', 'clipboard-check'],
        ['Pending Review',   $stats['pending_vacancies'],  'info',    'hourglass'],
        ['Total Perusahaan', $stats['total_companies'],    'purple',  'building'],
        ['Total Lamaran',    $stats['total_applications'], 'secondary','inbox'],
    ];
    foreach ($cards as [$label, $val, $color, $icon]): ?>
    <div class="col-6 col-sm-4 col-xl">
        <div class="card border-0 shadow-sm text-center py-3 stat-card"
             style="border-top:3px solid var(--bs-<?= $color === 'purple' ? 'primary' : $color ?>)!important">
            <div class="fw-bold fs-4 <?= $color === 'purple' ? 'text-primary' : "text-{$color}" ?>"><?= number_format($val) ?></div>
            <div class="text-muted small mt-1"><i class="bi bi-<?= $icon ?> me-1"></i><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom fw-semibold">Status Kerja Alumni</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="workStatusChart" style="max-height:260px"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom fw-semibold">Tren Lamaran 6 Bulan Terakhir</div>
            <div class="card-body">
                <canvas id="trendChart" style="max-height:260px"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Per-jurusan table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom fw-semibold">
        <i class="bi bi-table me-2 text-primary"></i>Statistik Per Jurusan
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Jurusan</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">Bekerja</th>
                        <th class="text-center">Wirausaha</th>
                        <th class="text-center">Lanjut Studi</th>
                        <th class="text-center">Belum Bekerja</th>
                        <th>% Bekerja</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stJ = $pdo->query(
                        "SELECT jurusan,
                                COUNT(*) as total,
                                SUM(work_status='employed') as employed,
                                SUM(work_status='entrepreneur') as entrepreneur,
                                SUM(work_status='continuing_edu') as continuing_edu,
                                SUM(work_status='unemployed') as unemployed
                         FROM alumni_profiles GROUP BY jurusan ORDER BY jurusan"
                    );
                    foreach ($stJ->fetchAll() as $row):
                        $pct = $row['total'] > 0 ? round($row['employed'] / $row['total'] * 100) : 0;
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= e($row['jurusan']) ?></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= $row['total'] ?></span></td>
                        <td class="text-center text-success fw-semibold"><?= $row['employed'] ?></td>
                        <td class="text-center text-info"><?= $row['entrepreneur'] ?></td>
                        <td class="text-center text-primary"><?= $row['continuing_edu'] ?></td>
                        <td class="text-center text-danger"><?= $row['unemployed'] ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:8px;min-width:60px">
                                    <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                                </div>
                                <span class="small fw-semibold"><?= $pct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const wsData   = <?= json_encode(array_column($stats['work_status_breakdown'], 'total')) ?>;
const wsLabels = <?= json_encode(array_map(fn($r) => ucwords(str_replace('_',' ',$r['work_status'])), $stats['work_status_breakdown'])) ?>;
new Chart(document.getElementById('workStatusChart'), {
    type: 'doughnut',
    data: { labels: wsLabels, datasets: [{ data: wsData, backgroundColor:['#22c55e','#ef4444','#3b82f6','#f59e0b'], borderWidth:2 }]},
    options: { plugins: { legend: { position:'bottom' } } }
});
const monthly = <?= json_encode($stats['monthly_applications']) ?>;
new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: monthly.map(r => r.month),
        datasets: [{ label:'Lamaran', data: monthly.map(r => r.total), backgroundColor:'rgba(59,130,246,.7)', borderRadius:6 }]
    },
    options: { scales: { y: { beginAtZero:true } }, plugins: { legend: { display:false } } }
});
</script>


<?php elseif ($tab === 'rekap_alumni'): ?>
<!-- ══════════════════════════════════════════════════
     TAB 2: Rekap Alumni (#4)
════════════════════════════════════════════════════ -->

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="rekap_alumni">
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Jurusan</label>
                <select name="jurusan" class="form-select form-select-sm">
                    <option value="">Semua Jurusan</option>
                    <?php foreach ($jurusanList as $j): ?>
                    <option value="<?= $j ?>" <?= $jurusan === $j ? 'selected' : '' ?>><?= $j ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Status Kerja</label>
                <select name="work_status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <?php foreach (['unemployed','employed','entrepreneur','continuing_edu'] as $s): ?>
                    <option value="<?= $s ?>" <?= $workStatus === $s ? 'selected' : '' ?>><?= workStatusLabel($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Angkatan</label>
                <input type="number" name="graduation_year" class="form-control form-control-sm"
                       min="2000" max="<?= date('Y') ?>" placeholder="Semua"
                       value="<?= e($gradYear) ?>">
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="?tab=rekap_alumni" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Summary mini-cards -->
<?php
$totalFiltered  = count($alumniData);
$employedCount  = count(array_filter($alumniData, fn($r) => $r['work_status'] === 'employed'));
$entrepreneurC  = count(array_filter($alumniData, fn($r) => $r['work_status'] === 'entrepreneur'));
$unemployedC    = count(array_filter($alumniData, fn($r) => $r['work_status'] === 'unemployed'));
$hasCvCount     = count(array_filter($alumniData, fn($r) => !empty($r['cv_path'])));
?>
<div class="row g-3 mb-3">
    <?php $mc = [
        ['Total Alumni',    $totalFiltered, 'primary'],
        ['Bekerja',         $employedCount, 'success'],
        ['Wirausaha',       $entrepreneurC, 'info'],
        ['Belum Bekerja',   $unemployedC,   'danger'],
        ['Sudah Upload CV', $hasCvCount,    'warning'],
    ]; foreach ($mc as [$l,$v,$c]): ?>
    <div class="col-6 col-sm">
        <div class="card border-0 shadow-sm text-center py-2">
            <div class="fw-bold fs-5 text-<?= $c ?>"><?= $v ?></div>
            <div class="text-muted small"><?= $l ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-people me-2 text-primary"></i>Data Alumni (<?= $totalFiltered ?>)</span>
        <a href="?tab=preview&<?= http_build_query(['jurusan'=>$jurusan,'work_status'=>$workStatus,'graduation_year'=>$gradYear]) ?>"
           class="btn btn-sm btn-outline-primary">
            <i class="bi bi-eye me-1"></i>Preview & Export
        </a>
    </div>
    <div class="rekap-preview">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light sticky-top">
                <tr>
                    <th>#</th>
                    <th>Foto</th>
                    <th>Nama Lengkap</th>
                    <th>NIS</th>
                    <th>Jurusan</th>
                    <th>Angkatan</th>
                    <th>Status Kerja</th>
                    <th>No. HP</th>
                    <th>Email</th>
                    <th>CV</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($alumniData): foreach ($alumniData as $i => $a): ?>
                <tr>
                    <td class="text-muted small"><?= $i + 1 ?></td>
                    <td>
                        <div class="avatar-sm">
                            <?php if ($a['photo_path']): ?>
                            <img src="<?= APP_URL ?>/files/serve.php?type=photo&path=<?= urlencode($a['photo_path']) ?>" alt="">
                            <?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
                        </div>
                    </td>
                    <td class="fw-semibold"><?= e($a['full_name']) ?></td>
                    <td class="small text-muted"><?= e($a['nis']) ?></td>
                    <td><span class="badge bg-light text-dark"><?= e($a['jurusan']) ?></span></td>
                    <td><?= e($a['graduation_year']) ?></td>
                    <td><?= statusBadge($a['work_status']) ?></td>
                    <td class="small"><?= e($a['phone'] ?: '-') ?></td>
                    <td class="small text-muted"><?= e($a['email']) ?></td>
                    <td>
                        <?php if ($a['cv_path']): ?>
                        <a href="<?= APP_URL ?>/files/serve.php?type=cv&path=<?= urlencode($a['cv_path']) ?>"
                           target="_blank" class="btn btn-sm btn-outline-danger py-0 px-1">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </a>
                        <?php else: ?>
                        <span class="text-muted small">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= APP_URL ?>/admin/alumni/detail.php?id=<?= $a['id'] ?>"
                           class="btn btn-sm btn-outline-primary py-0 px-2">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach;
                else: ?>
                <tr><td colspan="11" class="text-muted text-center py-4">Tidak ada data alumni sesuai filter.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<?php elseif ($tab === 'rekap_company'): ?>
<!-- ══════════════════════════════════════════════════
     TAB 3: Rekap Perusahaan (#5)
════════════════════════════════════════════════════ -->

<!-- Summary -->
<?php
$totalCo     = count($companyData);
$verifiedCo  = count(array_filter($companyData, fn($r) => $r['verified']));
$activeCo    = count(array_filter($companyData, fn($r) => $r['active_vacancies'] > 0));
$totalVac    = array_sum(array_column($companyData, 'total_vacancies'));
$totalApps   = array_sum(array_column($companyData, 'total_applications'));
?>
<div class="row g-3 mb-3">
    <?php $mc = [
        ['Total Perusahaan', $totalCo,   'primary'],
        ['Terverifikasi',    $verifiedCo,'success'],
        ['Punya Lowongan',   $activeCo,  'info'],
        ['Total Lowongan',   $totalVac,  'warning'],
        ['Total Lamaran',    $totalApps, 'secondary'],
    ]; foreach ($mc as [$l,$v,$c]): ?>
    <div class="col-6 col-sm">
        <div class="card border-0 shadow-sm text-center py-2">
            <div class="fw-bold fs-5 text-<?= $c ?>"><?= $v ?></div>
            <div class="text-muted small"><?= $l ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-building me-2 text-success"></i>Data Perusahaan (<?= $totalCo ?>)</span>
        <a href="?tab=rekap_company&export=csv_company" class="btn btn-sm btn-success">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
    </div>
    <div class="rekap-preview">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light sticky-top">
                <tr>
                    <th>#</th>
                    <th>Logo</th>
                    <th>Nama Perusahaan</th>
                    <th>Industri</th>
                    <th>Kota</th>
                    <th>Email</th>
                    <th>Terverifikasi</th>
                    <th class="text-center">Lowongan</th>
                    <th class="text-center">Aktif</th>
                    <th class="text-center">Lamaran</th>
                    <th class="text-center">Diterima</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companyData as $i => $co): ?>
                <tr>
                    <td class="text-muted small"><?= $i + 1 ?></td>
                    <td>
                        <div class="avatar-sm" style="border-radius:8px">
                            <?php if ($co['logo_path']): ?>
                            <img src="<?= APP_URL ?>/files/serve.php?type=logo&path=<?= urlencode($co['logo_path']) ?>"
                                 style="object-fit:contain;padding:2px" alt="">
                            <?php else: ?>
                            <i class="bi bi-building"></i>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="fw-semibold"><?= e($co['company_name']) ?></td>
                    <td class="small text-muted"><?= e($co['industry'] ?: '-') ?></td>
                    <td class="small"><?= e($co['city'] ?: '-') ?></td>
                    <td class="small text-muted"><?= e($co['email']) ?></td>
                    <td>
                        <?php if ($co['verified']): ?>
                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Ya</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">Belum</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><span class="badge bg-secondary"><?= $co['total_vacancies'] ?></span></td>
                    <td class="text-center"><span class="badge bg-success"><?= $co['active_vacancies'] ?></span></td>
                    <td class="text-center"><span class="badge bg-primary"><?= $co['total_applications'] ?></span></td>
                    <td class="text-center"><span class="badge bg-warning text-dark"><?= $co['accepted_applications'] ?></span></td>
                    <td>
                        <a href="<?= APP_URL ?>/admin/company/detail.php?company_id=<?= $co['id'] ?>"
                           class="btn btn-sm btn-outline-primary py-0 px-2">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$companyData): ?>
                <tr><td colspan="12" class="text-muted text-center py-4">Belum ada data perusahaan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<?php elseif ($tab === 'preview'): ?>
<!-- ══════════════════════════════════════════════════
     TAB 4: Preview & Export (#6)
════════════════════════════════════════════════════ -->

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-bottom fw-semibold">
        <i class="bi bi-funnel me-2 text-primary"></i>Filter Data Export
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="tab" value="preview">
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Jurusan</label>
                <select name="jurusan" class="form-select form-select-sm">
                    <option value="">Semua Jurusan</option>
                    <?php foreach ($jurusanList as $j): ?>
                    <option value="<?= $j ?>" <?= $jurusan === $j ? 'selected':'' ?>><?= $j ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold small">Status Kerja</label>
                <select name="work_status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <?php foreach (['unemployed','employed','entrepreneur','continuing_edu'] as $s): ?>
                    <option value="<?= $s ?>" <?= $workStatus === $s ? 'selected':'' ?>><?= workStatusLabel($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold small">Angkatan</label>
                <input type="number" name="graduation_year" class="form-control form-control-sm"
                       min="2000" max="<?= date('Y') ?>" placeholder="Semua" value="<?= e($gradYear) ?>">
            </div>
            <div class="col-auto d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-eye me-1"></i>Preview
                </button>
                <a href="?tab=preview" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Preview count + export buttons -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">
            <i class="bi bi-table me-2 text-info"></i>
            Preview Data Alumni
            <span class="badge bg-primary ms-2"><?= count($alumniData) ?> record</span>
            <?php if ($jurusan || $workStatus || $gradYear): ?>
            <span class="badge bg-warning text-dark ms-1">Filter aktif</span>
            <?php endif; ?>
        </span>
        <div class="d-flex gap-2">
            <?php $exportQuery = http_build_query(['jurusan'=>$jurusan,'work_status'=>$workStatus,'graduation_year'=>$gradYear]); ?>
            <a href="?export=csv&<?= $exportQuery ?>"
               class="btn btn-sm btn-success"
               onclick="return confirm('Download Excel untuk <?= count($alumniData) ?> alumni?')">
                <i class="bi bi-file-earmark-excel me-1"></i>
                Download Excel (<?= count($alumniData) ?>)
            </a>
            <a href="?export=pdf&<?= $exportQuery ?>"
               target="_blank"
               class="btn btn-sm btn-danger">
                <i class="bi bi-file-earmark-pdf me-1"></i>
                Cetak / PDF (<?= count($alumniData) ?>)
            </a>
        </div>
    </div>

    <?php if (!$alumniData): ?>
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
        Tidak ada data alumni sesuai filter yang dipilih.
    </div>
    <?php else: ?>

    <!-- Info summary sebelum export -->
    <div class="card-body border-bottom bg-light py-2">
        <div class="row g-3 text-center">
            <?php
            $prevEmployed = count(array_filter($alumniData, fn($r) => $r['work_status'] === 'employed'));
            $prevEntrepr  = count(array_filter($alumniData, fn($r) => $r['work_status'] === 'entrepreneur'));
            $prevUnemploy = count(array_filter($alumniData, fn($r) => $r['work_status'] === 'unemployed'));
            $prevContinue = count(array_filter($alumniData, fn($r) => $r['work_status'] === 'continuing_edu'));
            $prevPct = count($alumniData) > 0 ? round($prevEmployed / count($alumniData) * 100) : 0;
            ?>
            <div class="col"><div class="fw-bold text-success fs-5"><?= $prevEmployed ?></div><div class="text-muted small">Bekerja</div></div>
            <div class="col"><div class="fw-bold text-info fs-5"><?= $prevEntrepr ?></div><div class="text-muted small">Wirausaha</div></div>
            <div class="col"><div class="fw-bold text-primary fs-5"><?= $prevContinue ?></div><div class="text-muted small">Lanjut Studi</div></div>
            <div class="col"><div class="fw-bold text-danger fs-5"><?= $prevUnemploy ?></div><div class="text-muted small">Belum Bekerja</div></div>
            <div class="col">
                <div class="fw-bold text-dark fs-5"><?= $prevPct ?>%</div>
                <div class="text-muted small">Tingkat Penempatan</div>
                <div class="progress mt-1" style="height:4px">
                    <div class="progress-bar bg-success" style="width:<?= $prevPct ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview table (scrollable) -->
    <div class="rekap-preview">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light sticky-top">
                <tr>
                    <th>#</th>
                    <th>Nama</th>
                    <th>NIS</th>
                    <th>NISN</th>
                    <th>Jurusan</th>
                    <th>Angkatan</th>
                    <th>Status Kerja</th>
                    <th>No. HP</th>
                    <th>Email</th>
                    <th>CV</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alumniData as $i => $a): ?>
                <tr>
                    <td class="text-muted small"><?= $i + 1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar-sm">
                                <?php if ($a['photo_path']): ?>
                                <img src="<?= APP_URL ?>/files/serve.php?type=photo&path=<?= urlencode($a['photo_path']) ?>" alt="">
                                <?php else: ?><i class="bi bi-person-fill"></i><?php endif; ?>
                            </div>
                            <span class="fw-semibold small"><?= e($a['full_name']) ?></span>
                        </div>
                    </td>
                    <td class="small text-muted"><?= e($a['nis']) ?></td>
                    <td class="small text-muted"><?= e($a['nisn']) ?></td>
                    <td><span class="badge bg-light text-dark"><?= e($a['jurusan']) ?></span></td>
                    <td class="small"><?= e($a['graduation_year']) ?></td>
                    <td><?= statusBadge($a['work_status']) ?></td>
                    <td class="small"><?= e($a['phone'] ?: '-') ?></td>
                    <td class="small text-muted"><?= e($a['email']) ?></td>
                    <td>
                        <?php if ($a['cv_path']): ?>
                        <a href="<?= APP_URL ?>/files/serve.php?type=cv&path=<?= urlencode($a['cv_path']) ?>"
                           target="_blank" class="btn btn-sm btn-outline-danger py-0 px-1">
                            <i class="bi bi-file-earmark-pdf"></i>
                        </a>
                        <?php else: ?><span class="text-muted small">-</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card-footer bg-light d-flex justify-content-between align-items-center">
        <span class="text-muted small">
            Menampilkan <strong><?= count($alumniData) ?></strong> record
            <?php if ($jurusan || $workStatus || $gradYear): ?>
            dengan filter:
            <?php if ($jurusan): ?><span class="badge bg-info ms-1"><?= e($jurusan) ?></span><?php endif; ?>
            <?php if ($workStatus): ?><span class="badge bg-secondary ms-1"><?= workStatusLabel($workStatus) ?></span><?php endif; ?>
            <?php if ($gradYear): ?><span class="badge bg-primary ms-1">Angkatan <?= e($gradYear) ?></span><?php endif; ?>
            <?php endif; ?>
        </span>
        <div class="d-flex gap-2">
            <a href="?export=csv&<?= $exportQuery ?>" class="btn btn-sm btn-success">
                <i class="bi bi-file-earmark-excel me-1"></i>Download Excel
            </a>
            <a href="?export=pdf&<?= $exportQuery ?>" target="_blank" class="btn btn-sm btn-danger">
                <i class="bi bi-printer me-1"></i>Cetak PDF
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
