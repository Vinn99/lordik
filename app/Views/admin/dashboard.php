<?php
// public/admin/dashboard.php
require_once BASE_PATH . '/helpers/notification_helper.php';

requireAdmin();

$stats     = AdminModel::getDashboardStats();
$pageTitle = 'Dashboard Admin — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h4 class="fw-bold text-dark mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i>Dashboard Admin</h4>
        <p class="text-muted small">Ringkasan sistem LORDIK</p>
    </div>
</div>

<!-- Stat Cards Row 1 -->
<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #3b82f6!important;">
            <div class="card-body d-flex align-items-center gap-2 gap-md-3 p-2 p-md-3">
                <div class="bg-primary bg-opacity-10 rounded-circle d-none d-sm-flex align-items-center justify-content-center flex-shrink-0" style="width:46px;height:46px;min-width:46px">
                    <i class="bi bi-mortarboard-fill fs-4 text-primary"></i>
                </div>
                <div class="min-width-0">
                    <div class="h3 mb-0 fw-bold"><?= number_format($stats['total_alumni']) ?></div>
                    <div class="text-muted small text-truncate">Total Alumni</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #22c55e!important;">
            <div class="card-body d-flex align-items-center gap-2 gap-md-3 p-2 p-md-3">
                <div class="bg-success bg-opacity-10 rounded-circle d-none d-sm-flex align-items-center justify-content-center flex-shrink-0" style="width:46px;height:46px;min-width:46px">
                    <i class="bi bi-briefcase-fill fs-4 text-success"></i>
                </div>
                <div class="min-width-0">
                    <div class="h3 mb-0 fw-bold"><?= number_format($stats['employed_alumni']) ?></div>
                    <div class="text-muted small text-truncate">Alumni Bekerja</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #f59e0b!important;">
            <div class="card-body d-flex align-items-center gap-2 gap-md-3 p-2 p-md-3">
                <div class="bg-warning bg-opacity-10 rounded-circle d-none d-sm-flex align-items-center justify-content-center flex-shrink-0" style="width:46px;height:46px;min-width:46px">
                    <i class="bi bi-file-earmark-text-fill fs-4 text-warning"></i>
                </div>
                <div class="min-width-0">
                    <div class="h3 mb-0 fw-bold"><?= number_format($stats['active_vacancies']) ?></div>
                    <div class="text-muted small text-truncate">Lowongan Aktif</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100" style="border-left:4px solid #8b5cf6!important;">
            <div class="card-body d-flex align-items-center gap-2 gap-md-3 p-2 p-md-3">
                <div class="rounded-circle d-none d-sm-flex align-items-center justify-content-center flex-shrink-0" style="width:46px;height:46px;min-width:46px;background:rgba(139,92,246,.1)">
                    <i class="bi bi-building fs-4" style="color:#8b5cf6"></i>
                </div>
                <div class="min-width-0">
                    <div class="h3 mb-0 fw-bold"><?= number_format($stats['total_companies']) ?></div>
                    <div class="text-muted small text-truncate">Total Perusahaan</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <!-- Work Status Chart -->
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom fw-semibold">
                <i class="bi bi-pie-chart me-2 text-primary"></i>Status Alumni
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="workStatusChart" style="max-height:220px;max-width:220px;"></canvas>
            </div>
        </div>
    </div>
    <!-- Monthly Applications Chart -->
    <div class="col-12 col-md-6 col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-bottom fw-semibold">
                <i class="bi bi-bar-chart me-2 text-primary"></i>Tren Lamaran (6 Bulan Terakhir)
            </div>
            <div class="card-body">
                <canvas id="monthlyAppChart" style="max-height:220px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Jurusan Table + Pending Vacancies -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-table me-2 text-primary"></i>Statistik Per Jurusan</span>
            </div>
            <div class="card-body p-0">
                <div class="table-wrap">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Jurusan</th>
                                <th class="text-center">Total</th>
                                <th class="text-center">Bekerja</th>
                                <th class="text-center">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats['by_jurusan'] as $j): ?>
                            <tr>
                                <td><?= e($j['jurusan']) ?></td>
                                <td class="text-center"><?= $j['total'] ?></td>
                                <td class="text-center text-success fw-semibold"><?= $j['employed'] ?></td>
                                <td class="text-center">
                                    <?= $j['total'] > 0 ? round($j['employed'] / $j['total'] * 100) . '%' : '0%' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($stats['by_jurusan'])): ?>
                            <tr><td colspan="4" class="text-muted text-center py-3">Belum ada data</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-clock me-2 text-warning"></i>Lowongan Menunggu Persetujuan</span>
                <span class="badge bg-warning text-dark"><?= $stats['pending_vacancies'] ?></span>
            </div>
            <div class="card-body p-0">
                <?php
                $pending = VacancyModel::list(['status' => 'submitted'], 5, 0);
                ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($pending['data'] as $v): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold small"><?= e($v['title']) ?></div>
                            <div class="text-muted" style="font-size:.8rem"><?= e($v['company_name']) ?></div>
                        </div>
                        <a href="<?= APP_URL ?>/admin/vacancy/detail.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary">
                            Review
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <?php if (empty($pending['data'])): ?>
                    <li class="list-group-item text-muted text-center py-3">Tidak ada lowongan pending</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php if ($stats['pending_vacancies'] > 5): ?>
            <div class="card-footer text-center">
                <a href="<?= APP_URL ?>/admin/vacancy/list.php?status=submitted" class="text-primary small">Lihat semua</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom fw-semibold">
                <i class="bi bi-lightning me-2 text-warning"></i>Aksi Cepat
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6 col-sm-auto"><a href="<?= APP_URL ?>/admin/users.php?action=create&role=alumni" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-person-plus me-1"></i>Buat Alumni</a></div>
                    <div class="col-6 col-sm-auto"><a href="<?= APP_URL ?>/admin/users.php?action=create&role=company" class="btn btn-outline-success btn-sm w-100"><i class="bi bi-building-add me-1"></i>Buat Perusahaan</a></div>
                    <div class="col-6 col-sm-auto"><a href="<?= APP_URL ?>/admin/reports.php?export=csv" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV</a></div>
                    <div class="col-6 col-sm-auto"><a href="<?= APP_URL ?>/admin/reports.php?export=pdf" class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a></div>
                    <div class="col-6 col-sm-auto"><a href="<?= APP_URL ?>/admin/logs.php" class="btn btn-outline-dark btn-sm w-100"><i class="bi bi-journal-text me-1"></i>Activity Logs</a></div>
                    <div class="col-6 col-sm-auto"><a href="<?= APP_URL ?>/admin/applications.php" class="btn btn-outline-info btn-sm w-100"><i class="bi bi-inbox me-1"></i>Semua Lamaran</a></div>
                    <div class="col-6 col-sm-auto"><a href="<?= APP_URL ?>/admin/company/list.php" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-building me-1"></i>Data Perusahaan</a></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
// Work Status Pie Chart
const wsData = <?= json_encode(array_column($stats['work_status_breakdown'], 'total')) ?>;
const wsLabels = <?= json_encode(array_map(fn($r) => ucwords(str_replace('_', ' ', $r['work_status'])), $stats['work_status_breakdown'])) ?>;
new Chart(document.getElementById('workStatusChart'), {
    type: 'doughnut',
    data: {
        labels: wsLabels,
        datasets: [{
            data: wsData,
            backgroundColor: ['#ef4444','#22c55e','#3b82f6','#f59e0b'],
            borderWidth: 2,
        }]
    },
    options: { plugins: { legend: { position: 'bottom' } } }
});

// Monthly Applications Bar Chart
const maLabels = <?= json_encode(array_column($stats['monthly_applications'], 'month')) ?>;
const maData   = <?= json_encode(array_column($stats['monthly_applications'], 'total')) ?>;
new Chart(document.getElementById('monthlyAppChart'), {
    type: 'bar',
    data: {
        labels: maLabels,
        datasets: [{
            label: 'Jumlah Lamaran',
            data: maData,
            backgroundColor: 'rgba(59,130,246,0.7)',
            borderColor: '#3b82f6',
            borderWidth: 1,
            borderRadius: 6,
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
});
</script>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
