<?php
// public/admin/users.php — v2: credential page, bulk import, tabbed create
require_once BASE_PATH . '/helpers/notification_helper.php';

requireAdmin();

$tab    = $_GET['tab'] ?? 'list';
$errors = [];

// Excel Template download
if ($tab === 'excel_template') {
    $type = $_GET['type'] ?? 'alumni';
    $fname = "template_import_{$type}.xls";

    if ($type === 'alumni') {
        $headers = ['username','email','full_name','nis','nisn','gender','jurusan','graduation_year'];
        $samples = [
            ['budi.santoso','budi@example.com','Budi Santoso','1234','56789012','male','RPL', date('Y')],
            ['siti.rahayu','siti@example.com','Siti Rahayu','1235','56789013','female','RPL', date('Y')],
        ];
        $sheetName = 'Template Alumni';
        $headerColor = '#1d4ed8';
    } else {
        $headers = ['username','email','company_name','industry','city'];
        $samples = [
            ['cv.maju','maju@example.com','CV Maju Jaya','Manufaktur','Jakarta'],
            ['pt.digital','digital@example.com','PT Digital Nusantara','Teknologi','Surabaya'],
        ];
        $sheetName = 'Template Perusahaan';
        $headerColor = '#059669';
    }

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";
    $xml .= '<Styles>';
    $xml .= '<Style ss:ID="header"><Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="11"/><Interior ss:Color="' . $headerColor . '" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#FFFFFF"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#FFFFFF"/></Borders></Style>';
    $xml .= '<Style ss:ID="cell"><Font ss:Size="10"/><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
    $xml .= '<Style ss:ID="cellAlt"><Font ss:Size="10"/><Alignment ss:Vertical="Center"/><Interior ss:Color="#F8FAFC" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
    $xml .= '<Style ss:ID="note"><Font ss:Italic="1" ss:Color="#64748B" ss:Size="9"/></Style>';
    $xml .= '</Styles>';
    $xml .= '<Worksheet ss:Name="' . htmlspecialchars($sheetName, ENT_XML1) . '"><Table>';

    // Column widths
    $colWidths = ($type === 'alumni')
        ? [110, 140, 140, 80, 90, 70, 70, 90]
        : [110, 140, 160, 110, 100];
    foreach ($colWidths as $w) {
        $xml .= '<Column ss:Width="' . $w . '"/>';
    }

    // Header row
    $xml .= '<Row ss:Height="26">';
    foreach ($headers as $h) {
        $xml .= '<Cell ss:StyleID="header"><Data ss:Type="String">' . htmlspecialchars(strtoupper($h), ENT_XML1) . '</Data></Cell>';
    }
    $xml .= '</Row>';

    // Sample rows
    foreach ($samples as $i => $row) {
        $style = ($i % 2 === 0) ? 'cell' : 'cellAlt';
        $xml .= '<Row ss:Height="20">';
        foreach ($row as $cell) {
            $xml .= '<Cell ss:StyleID="' . $style . '"><Data ss:Type="String">' . htmlspecialchars((string)$cell, ENT_XML1) . '</Data></Cell>';
        }
        $xml .= '</Row>';
    }

    $xml .= '</Table></Worksheet>';

    // Petunjuk sheet
    $xml .= '<Worksheet ss:Name="Petunjuk"><Table>';
    $xml .= '<Column ss:Width="400"/>';
    $petunjuk = [
        'PETUNJUK PENGISIAN TEMPLATE IMPORT',
        '',
        '1. Isi data pada sheet "' . $sheetName . '" mulai baris ke-2 (baris 1 adalah header)',
        '2. Jangan mengubah nama kolom pada baris pertama',
        '3. Kolom WAJIB diisi: username dan email',
        '4. Username tidak boleh mengandung spasi',
        '5. Email harus valid dan belum terdaftar di sistem',
        ($type === 'alumni') ? '6. Kolom gender diisi: male atau female' : '6. Kolom industry: contoh Manufaktur, Teknologi, Perdagangan, Jasa, dll',
        ($type === 'alumni') ? '7. Kolom jurusan: RPL, DKV, AKL, MPK, BDP, LP3K, LPB, ULW' : '7. Kolom city: nama kota perusahaan',
        ($type === 'alumni') ? '8. Kolom graduation_year: 4 digit tahun, contoh ' . date('Y') : '',
        '',
        'Simpan file ini tetap dalam format .xls atau ekspor ke .csv sebelum diupload.',
    ];
    foreach ($petunjuk as $p) {
        $xml .= '<Row><Cell><Data ss:Type="String">' . htmlspecialchars($p, ENT_XML1) . '</Data></Cell></Row>';
    }
    $xml .= '</Table></Worksheet>';
    $xml .= '</Workbook>';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"{$fname}\"");
    header('Cache-Control: max-age=0');
    echo $xml; exit;
}

// CSV upload still supported for bulk import - parse both CSV and XLS


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $postAction = $_POST['_action'] ?? '';

    if ($postAction === 'create') {
        $result = AdminModel::createUser($_POST, currentUserId());
        if ($result['success']) {
            $cred = ['username' => $result['username'], 'email' => $result['email'],
                     'role' => $result['role'], 'password' => $result['temp_password']];
            if (!empty($_POST['full_name']))    $cred['full_name']    = $_POST['full_name'];
            if (!empty($_POST['nis']))          $cred['nis']          = $_POST['nis'];
            if (!empty($_POST['nisn']))         $cred['nisn']         = $_POST['nisn'];
            if (!empty($_POST['company_name'])) $cred['company_name'] = $_POST['company_name'];
            $_SESSION['new_credential'] = $cred;
            redirect('/admin/user_credential.php');
        } else {
            $errors = $result['errors'] ?? [$result['message'] ?? 'Gagal membuat akun.'];
            $tab = 'create';
        }
    }

    if ($postAction === 'bulk_import') {
        $importRole = $_POST['import_role'] ?? 'alumni';
        $rows = [];
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $tmpPath  = $_FILES['csv_file']['tmp_name'];
            $origName = strtolower($_FILES['csv_file']['name'] ?? '');
            $ext      = pathinfo($origName, PATHINFO_EXTENSION);

            // ── Helper: map a flat array of cells to an associative row ──────
            $mapRow = function(array $headers, array $cells) use ($importRole): array {
                $row = [];
                foreach ($headers as $i => $h) $row[$h] = trim($cells[$i] ?? '');
                if (empty($row['role']))   $row['role']   = $importRole;
                if (empty($row['gender'])) $row['gender'] = 'male';
                return $row;
            };

            if ($ext === 'csv' || $ext === 'txt') {
                // ── Plain CSV ────────────────────────────────────────────────
                $fh      = fopen($tmpPath, 'r');
                $headers = null;
                while (($line = fgetcsv($fh)) !== false) {
                    if ($headers === null) {
                        $headers = array_map('strtolower', array_map('trim', $line));
                        continue;
                    }
                    $row = $mapRow($headers, $line);
                    if (!empty($row['username']) && !empty($row['email'])) $rows[] = $row;
                }
                fclose($fh);

            } elseif ($ext === 'xls') {
                // ── XML Spreadsheet (.xls generated by our template) ─────────
                $content = @file_get_contents($tmpPath);
                if ($content !== false) {
                    // Detect whether it is the XML-Spreadsheet format we generate
                    if (strpos($content, 'urn:schemas-microsoft-com:office:spreadsheet') !== false) {
                        libxml_use_internal_errors(true);
                        $xml = simplexml_load_string($content);
                        if ($xml) {
                            $ssNs = 'urn:schemas-microsoft-com:office:spreadsheet';

                            // Find the first worksheet that is NOT "Petunjuk"
                            foreach ($xml->Worksheet as $sheet) {
                                $sheetName = (string)($sheet->attributes($ssNs)['Name'] ?? '');
                                if (stripos($sheetName, 'petunjuk') !== false) continue;

                                $headers = null;
                                foreach ($sheet->Table->Row as $xmlRow) {
                                    // XML Spreadsheet cells may carry ss:Index for sparse columns
                                    $cells  = [];
                                    $colIdx = 0; // 0-based running position
                                    foreach ($xmlRow->Cell as $cell) {
                                        $cellAttrs = $cell->attributes($ssNs);
                                        // ss:Index is 1-based; jump if present
                                        if (isset($cellAttrs['Index'])) {
                                            $colIdx = (int)$cellAttrs['Index'] - 1;
                                        }
                                        // Fill gaps with empty strings
                                        while (count($cells) < $colIdx) $cells[] = '';
                                        $cells[] = trim((string)$cell->Data);
                                        // MergeAcross expands cell across N extra columns
                                        $merge = isset($cellAttrs['MergeAcross']) ? (int)$cellAttrs['MergeAcross'] : 0;
                                        for ($m = 0; $m < $merge; $m++) $cells[] = '';
                                        $colIdx = count($cells);
                                    }
                                    if ($headers === null) {
                                        $headers = array_map('strtolower', $cells);
                                        continue;
                                    }
                                    // Skip blank rows (all cells empty)
                                    if (implode('', $cells) === '') continue;
                                    $row = $mapRow($headers, $cells);
                                    if (!empty($row['username']) && !empty($row['email'])) $rows[] = $row;
                                }
                                break; // only process first data sheet
                            }
                        }
                    } else {
                        // Fallback: try CSV parsing (some .xls are really CSV)
                        $fh      = fopen($tmpPath, 'r');
                        $headers = null;
                        while (($line = fgetcsv($fh)) !== false) {
                            if ($headers === null) {
                                $headers = array_map('strtolower', array_map('trim', $line));
                                continue;
                            }
                            $row = $mapRow($headers, $line);
                            if (!empty($row['username']) && !empty($row['email'])) $rows[] = $row;
                        }
                        fclose($fh);
                    }
                }

            } elseif ($ext === 'xlsx') {
                // ── OOXML (.xlsx) — read via ZipArchive + SimpleXML ──────────
                // Helper: convert Excel column letters (A, B, ... Z, AA ...) to 0-based index
                $colLetterToIndex = function(string $col): int {
                    $col = strtoupper($col);
                    $idx = 0;
                    for ($i = 0, $len = strlen($col); $i < $len; $i++) {
                        $idx = $idx * 26 + (ord($col[$i]) - ord('A') + 1);
                    }
                    return $idx - 1; // 0-based
                };

                $zip = new ZipArchive();
                if ($zip->open($tmpPath) === true) {
                    // Read shared strings
                    $sharedStrings = [];
                    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
                    if ($ssXml) {
                        $ss = simplexml_load_string($ssXml);
                        if ($ss) {
                            foreach ($ss->si as $si) {
                                // Concatenate all <t> nodes (handles rich text)
                                $t = '';
                                foreach ($si->r as $r) { $t .= (string)$r->t; }
                                if ($t === '') $t = (string)$si->t;
                                $sharedStrings[] = $t;
                            }
                        }
                    }
                    // Read sheet1
                    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                    $zip->close();
                    if ($sheetXml) {
                        $sheet = simplexml_load_string($sheetXml);
                        if ($sheet) {
                            $headers = null;
                            foreach ($sheet->sheetData->row as $xmlRow) {
                                $cells = [];
                                foreach ($xmlRow->c as $c) {
                                    // r attribute is like "A1", "B3" — extract column letters
                                    $ref    = (string)($c->attributes()->r ?? '');
                                    $colStr = preg_replace('/[^A-Za-z]/', '', $ref);
                                    $colPos = $colStr !== '' ? $colLetterToIndex($colStr) : count($cells);
                                    // Fill gaps for sparse cells
                                    while (count($cells) < $colPos) $cells[] = '';
                                    $t   = (string)($c->attributes()->t ?? '');
                                    $val = (string)($c->v ?? '');
                                    if ($t === 's') {
                                        $val = $sharedStrings[(int)$val] ?? '';
                                    } elseif ($t === 'str' || $t === 'inlineStr') {
                                        $val = (string)($c->is->t ?? $c->f ?? $val);
                                    }
                                    $cells[] = trim($val);
                                }
                                if ($headers === null) {
                                    $headers = array_map('strtolower', $cells);
                                    continue;
                                }
                                if (implode('', $cells) === '') continue; // skip blank rows
                                $row = $mapRow($headers, $cells);
                                if (!empty($row['username']) && !empty($row['email'])) $rows[] = $row;
                            }
                        }
                    }
                }
            }

        } elseif (!empty($_POST['manual_rows'])) {
            foreach (explode("\n", trim($_POST['manual_rows'])) as $line) {
                $line = trim($line);
                // Skip blank lines and header-like lines
                if ($line === '') continue;
                $parts = array_map('trim', explode(',', $line));
                // Skip if first part looks like a column header
                if (strtolower($parts[0]) === 'username') continue;
                if (count($parts) >= 2 && $parts[0] && $parts[1]) {
                    if ($importRole === 'company') {
                        // Format: username,email,company_name,industry,city
                        $rows[] = [
                            'username'     => $parts[0],
                            'email'        => $parts[1],
                            'company_name' => $parts[2] ?? '',
                            'industry'     => $parts[3] ?? '',
                            'city'         => $parts[4] ?? '',
                            'role'         => $importRole,
                        ];
                    } else {
                        // Format: username,email,full_name,nis,nisn,gender,jurusan,graduation_year
                        $rows[] = [
                            'username'        => $parts[0],
                            'email'           => $parts[1],
                            'full_name'       => $parts[2] ?? '',
                            'nis'             => $parts[3] ?? '',
                            'nisn'            => $parts[4] ?? '',
                            'role'            => $importRole,
                            'gender'          => !empty($parts[5]) ? $parts[5] : 'male',
                            'jurusan'         => $parts[6] ?? '',
                            'graduation_year' => $parts[7] ?? date('Y'),
                        ];
                    }
                }
            }
        }
        if ($rows) {
            $_SESSION['bulk_result'] = AdminModel::bulkCreateUsers($rows, currentUserId());
            redirect('/admin/users.php?tab=bulk_result');
        } else {
            $errors[] = 'Tidak ada data valid untuk diimport. Pastikan file berformat .xls (template), .xlsx, atau .csv, dan kolom username serta email telah diisi.';
            $tab = 'bulk';
        }
    }

    if ($postAction === 'toggle_active') {
        AdminModel::toggleUserActive((int)$_POST['user_id'], currentUserId());
        setFlash('success', 'Status akun berhasil diubah.');
        redirect('/admin/users.php');
    }

    if ($postAction === 'reset_password') {
        $result = AuthModule::adminResetPassword((int)$_POST['user_id'], currentUserId());
        if ($result['success']) {
            $pdo = getDB();
            $u   = $pdo->prepare("SELECT username, email, role FROM users WHERE id = ? LIMIT 1");
            $u->execute([(int)$_POST['user_id']]);
            $uRow = $u->fetch();
            $_SESSION['new_credential'] = [
                'username' => $uRow['username'] ?? '-',
                'email'    => $uRow['email']    ?? '-',
                'role'     => $uRow['role']     ?? '-',
                'password' => $result['temp_password'],
            ];
            redirect('/admin/user_credential.php');
        }
        setFlash('danger', 'Gagal reset password.');
        redirect('/admin/users.php');
    }

    if ($postAction === 'delete') {
        AdminModel::softDeleteUser((int)$_POST['user_id'], currentUserId());
        setFlash('success', 'Akun berhasil dihapus.');
        redirect('/admin/users.php');
    }
}

$bulkResult = null;
if ($tab === 'bulk_result') {
    $bulkResult = $_SESSION['bulk_result'] ?? null;
    unset($_SESSION['bulk_result']);
    if (!$bulkResult) redirect('/admin/users.php?tab=bulk');
}

$filters = ['role' => $_GET['role'] ?? '', 'search' => $_GET['search'] ?? ''];
$page    = max(1, (int)($_GET['page'] ?? 1));
$result  = AdminModel::listUsers($filters, PER_PAGE, ($page - 1) * PER_PAGE);
$paging  = paginate($result['total'], $page);
$jurusanList = ['RPL','DKV','AKL','MPK','BDP','LP3K','LPB','ULW'];

$pageTitle = 'Manajemen Pengguna — ' . APP_NAME;
require_once BASE_PATH . '/app/Views/layouts/header.php';
?>

<div class="d-flex align-items-center gap-3 mb-3">
    <?= backButton('/admin/dashboard.php') ?>
    <h4 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Manajemen Pengguna</h4>
</div>

<ul class="nav nav-pills mb-4 gap-1 flex-wrap">
    <?php foreach ([['list','bi-list-ul','Daftar Akun'],['create','bi-person-plus','Buat Akun'],['bulk','bi-upload','Import Massal']] as [$k,$icon,$label]): ?>
    <li class="nav-item">
        <a class="nav-link <?= ($tab==$k||($tab=='bulk_result'&&$k=='bulk'))?'active':'bg-white border text-secondary' ?>"
           href="?tab=<?= $k ?>">
            <i class="bi bi-<?= $icon ?> me-1"></i><?= $label ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'list'): ?>
<!-- LIST -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="list">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Cari username / email..." value="<?= e($filters['search']) ?>">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select form-select-sm">
                    <option value="">Semua Role</option>
                    <option value="alumni"  <?= $filters['role']==='alumni'  ? 'selected':'' ?>>Alumni</option>
                    <option value="company" <?= $filters['role']==='company' ? 'selected':'' ?>>Perusahaan</option>
                    <option value="admin"   <?= $filters['role']==='admin'   ? 'selected':'' ?>>Admin</option>
                </select>
            </div>
            <div class="col-auto d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <a href="?tab=list" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <span class="small fw-semibold">Total <strong><?= $result['total'] ?></strong> akun</span>
        <a href="?tab=create" class="btn btn-sm btn-primary"><i class="bi bi-person-plus me-1"></i>Buat Akun</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $i => $u):
                    $rc=['alumni'=>'info','company'=>'success','admin'=>'danger'];
                    $rl=['alumni'=>'Alumni','company'=>'Perusahaan','admin'=>'Admin']; ?>
                <tr class="<?= !$u['is_active']?'text-muted':'' ?>">
                    <td class="small text-muted"><?= $paging['offset']+$i+1 ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($u['username']) ?></div>
                        <?php if ($u['force_change_pwd']): ?>
                        <span class="badge bg-warning text-dark" style="font-size:.65rem"><i class="bi bi-exclamation me-1"></i>Belum ganti pwd</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted"><?= e($u['email']) ?></td>
                    <td><span class="badge bg-<?= $rc[$u['role']]??'secondary' ?>"><?= $rl[$u['role']]??$u['role'] ?></span></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                        <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Nonaktif</span>
                        <?php endif; ?>
                    </td>
                    <td class="small text-muted">
                        <?= $u['last_login'] ? formatDate($u['last_login'],'d M Y H:i') : '<em>Belum pernah</em>' ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <form method="POST" class="d-inline">
                                <?= csrfField() ?><input type="hidden" name="_action" value="toggle_active">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm <?= $u['is_active']?'btn-outline-warning':'btn-outline-success' ?>"
                                        title="<?= $u['is_active']?'Nonaktifkan':'Aktifkan' ?>">
                                    <i class="bi bi-<?= $u['is_active']?'pause-circle':'play-circle' ?>"></i>
                                </button>
                            </form>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Reset password <?= e($u['username']) ?>?')">
                                <?= csrfField() ?><input type="hidden" name="_action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary" title="Reset password">
                                    <i class="bi bi-key"></i>
                                </button>
                            </form>
                            <?php if (!($u['role']==='admin' && $u['id']===currentUserId())): ?>
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Hapus akun <?= e($u['username']) ?>?')">
                                <?= csrfField() ?><input type="hidden" name="_action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash3"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($result['data'])): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada akun ditemukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paging['total_pages']>1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Hal. <?= $page ?>/<?= $paging['total_pages'] ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($p=1;$p<=$paging['total_pages'];$p++): ?>
            <li class="page-item <?= $p===$page?'active':'' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'create'): ?>
<!-- CREATE -->
<?php if ($errors): ?><div class="alert alert-danger mb-3"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="row justify-content-center"><div class="col-lg-8">
<div class="card border-0 shadow-sm" style="border-radius:16px">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-1">Buat Akun Baru</h5>
        <p class="text-muted small mb-4">Password sementara otomatis dibuat dan ditampilkan setelah akun berhasil disimpan.</p>

        <!-- Role tabs -->
        <div class="btn-group w-100 mb-4" role="group">
            <?php $rTabs=[['alumni','bi-mortarboard','Alumni','info'],['company','bi-building','Perusahaan','success'],['admin','bi-shield-check','Admin','danger']];
            $activeRole = $_POST['role'] ?? 'alumni';
            foreach($rTabs as [$rv,$ri,$rl,$rc]): ?>
            <button type="button"
                    class="btn btn-outline-<?= $rc ?> <?= $activeRole===$rv?'active':'' ?> role-tab-btn"
                    onclick="switchRole('<?= $rv ?>')">
                <i class="bi bi-<?= $ri ?> me-1"></i><?= $rl ?>
            </button>
            <?php endforeach; ?>
        </div>

        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="_action" value="create">
            <input type="hidden" name="role" id="roleInput" value="<?= e($activeRole) ?>">

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control"
                           value="<?= e($_POST['username'] ?? '') ?>" placeholder="budi.santoso" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control"
                           value="<?= e($_POST['email'] ?? '') ?>" placeholder="budi@email.com" required>
                </div>
            </div>

            <hr class="my-3">

            <!-- Alumni fields -->
            <div id="fields-alumni" class="role-fields <?= $activeRole==='alumni'?'':'d-none' ?>">
                <p class="text-muted small mb-3"><i class="bi bi-info-circle me-1"></i>Data alumni opsional — bisa diisi sekarang untuk menghemat waktu.</p>
                <div class="row g-3">
                    <div class="col-12"><input type="text" name="full_name" class="form-control" placeholder="Nama Lengkap" value="<?= e($_POST['full_name'] ?? '') ?>"></div>
                    <div class="col-6"><input type="text" name="nis" class="form-control" placeholder="NIS" value="<?= e($_POST['nis'] ?? '') ?>"></div>
                    <div class="col-6"><input type="text" name="nisn" class="form-control" placeholder="NISN" value="<?= e($_POST['nisn'] ?? '') ?>"></div>
                    <div class="col-4">
                        <select name="gender" class="form-select">
                            <option value="male">Laki-laki</option>
                            <option value="female">Perempuan</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <select name="jurusan" class="form-select">
                            <?php foreach($jurusanList as $j): ?><option><?= $j ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <input type="number" name="graduation_year" class="form-control"
                               value="<?= date('Y') ?>" min="2000" max="<?= date('Y')+1 ?>" placeholder="Angkatan">
                    </div>
                </div>
            </div>

            <!-- Company fields -->
            <div id="fields-company" class="role-fields <?= $activeRole==='company'?'':'d-none' ?>">
                <p class="text-muted small mb-3"><i class="bi bi-info-circle me-1"></i>Data perusahaan opsional.</p>
                <div class="row g-3">
                    <div class="col-12"><input type="text" name="company_name" class="form-control" placeholder="Nama Perusahaan" value="<?= e($_POST['company_name'] ?? '') ?>"></div>
                    <div class="col-6"><input type="text" name="industry" class="form-control" placeholder="Industri" value="<?= e($_POST['industry'] ?? '') ?>"></div>
                    <div class="col-6"><input type="text" name="city" class="form-control" placeholder="Kota" value="<?= e($_POST['city'] ?? '') ?>"></div>
                </div>
            </div>

            <!-- Admin fields -->
            <div id="fields-admin" class="role-fields <?= $activeRole==='admin'?'':'d-none' ?>">
                <div class="alert alert-warning small"><i class="bi bi-shield-exclamation me-2"></i>Akun Admin memiliki akses penuh. Buat hanya jika benar-benar diperlukan.</div>
            </div>

            <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                <a href="?tab=list" class="btn btn-outline-secondary">Batal</a>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i>Buat & Tampilkan Password
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
</div></div>
<script>
function switchRole(role) {
    document.getElementById('roleInput').value = role;
    document.querySelectorAll('.role-fields').forEach(el => el.classList.add('d-none'));
    document.getElementById('fields-' + role)?.classList.remove('d-none');
    document.querySelectorAll('.role-tab-btn').forEach(btn => {
        const active = btn.getAttribute('onclick').includes("'" + role + "'");
        btn.classList.toggle('active', active);
    });
}
</script>

<?php elseif ($tab === 'bulk'): ?>
<!-- BULK IMPORT -->
<?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Import File Excel / CSV</div>
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="_action" value="bulk_import">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Jenis Akun <span class="text-danger">*</span></label>
                        <select name="import_role" class="form-select form-select-sm" id="fileRole">
                            <option value="alumni">Alumni</option>
                            <option value="company">Perusahaan</option>
                        </select>
                    </div>

                    <!-- Peringatan sesuai jenis akun -->
                    <div id="fileGuideAlumni" class="alert alert-warning border-warning p-2 small mb-3" style="background:#fffbeb">
                        <i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i>
                        <strong>Pastikan file yang diupload berisi data akun alumni.</strong>
                        Gunakan <strong>Template Alumni</strong> dan pastikan kolom <code>jurusan</code> serta <code>graduation_year</code> sudah terisi dengan benar.
                    </div>
                    <div id="fileGuideCompany" class="alert alert-warning border-warning p-2 small mb-3" style="display:none;background:#fffbeb">
                        <i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i>
                        <strong>Pastikan file yang diupload berisi data akun perusahaan.</strong>
                        Gunakan <strong>Template Perusahaan</strong> dan jangan mencampurkan data alumni di file ini.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">File Excel / CSV <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,.xls,.xlsx,.txt">
                        <div class="form-text">Kolom wajib: <code>username, email</code> &mdash; Format: <code>.xls</code> (template), <code>.xlsx</code>, <code>.csv</code></div>
                    </div>
                    <div class="alert border p-2 d-flex align-items-center gap-2 mb-3" style="background:#f0fdf4;border-color:#bbf7d0!important">
                        <i class="bi bi-file-earmark-excel text-success fs-5 flex-shrink-0"></i>
                        <div class="small">
                            Download template Excel lalu isi data, kemudian upload kembali.<br>
                            <a href="?tab=excel_template&type=alumni" class="fw-semibold text-success"><i class="bi bi-download me-1"></i>Template Alumni (.xls)</a>
                            &nbsp;|&nbsp;
                            <a href="?tab=excel_template&type=company" class="fw-semibold text-success"><i class="bi bi-download me-1"></i>Template Perusahaan (.xls)</a>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-upload me-2"></i>Import Data
                    </button>
                </form>
                <script>
                (function() {
                    var roleEl       = document.getElementById('fileRole');
                    var guideAlumni  = document.getElementById('fileGuideAlumni');
                    var guideCompany = document.getElementById('fileGuideCompany');
                    function update() {
                        var isAlumni = roleEl.value === 'alumni';
                        guideAlumni.style.display  = isAlumni ? '' : 'none';
                        guideCompany.style.display = isAlumni ? 'none' : '';
                    }
                    roleEl.addEventListener('change', update);
                    update();
                })();
                </script>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-keyboard me-2 text-primary"></i>Input Manual (tanpa file)</div>
            <div class="card-body p-4">
                <form method="POST" id="manualForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="_action" value="bulk_import">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Jenis Akun</label>
                        <select name="import_role" class="form-select form-select-sm" id="manualRole">
                            <option value="alumni">Alumni</option>
                            <option value="company">Perusahaan</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Daftar Akun <span class="text-danger">*</span></label>
                        <textarea name="manual_rows" class="form-control font-monospace" rows="9"
                                  id="manualTextarea"
                                  placeholder="username,email,nama_lengkap,nis,nisn&#10;budi.s,budi@mail.com,Budi Santoso,1001,9900001&#10;siti.r,siti@mail.com,Siti Rahayu,1002,9900002"></textarea>
                    </div>

                    <!-- Panduan format berdasarkan jenis akun -->
                    <div id="manualGuideAlumni" class="mb-3">
                        <div class="alert alert-info border-info p-3 mb-2 small" style="background:#eff6ff">
                            <div class="fw-semibold mb-1"><i class="bi bi-mortarboard me-1"></i>Format untuk Akun Alumni:</div>
                            <code class="d-block mb-1">username,email,nama_lengkap,nis,nisn,gender,jurusan,angkatan</code>
                            <div class="text-muted mt-1">Contoh: <code>budi.s,budi@mail.com,Budi Santoso,1001,9900001,male,RPL,2024</code></div>
                        </div>
                        <div class="alert alert-warning border-warning p-2 small mb-0" style="background:#fffbeb">
                            <i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i>
                            <strong>Pastikan akun yang dimasukkan benar-benar akun alumni.</strong>
                            Kolom <code>jurusan</code> wajib diisi (RPL, DKV, AKL, MPK, BDP, LP3K, LPB, ULW)
                            dan kolom <code>angkatan</code> diisi dengan 4 digit tahun (contoh: 2024).
                            Jika gender dikosongkan, akan diset <em>male</em> secara default.
                        </div>
                    </div>

                    <div id="manualGuideCompany" class="mb-3" style="display:none">
                        <div class="alert alert-success border-success p-3 mb-2 small" style="background:#f0fdf4">
                            <div class="fw-semibold mb-1"><i class="bi bi-building me-1"></i>Format untuk Akun Perusahaan:</div>
                            <code class="d-block mb-1">username,email,nama_perusahaan,industri,kota</code>
                            <div class="text-muted mt-1">Contoh: <code>cv.maju,maju@mail.com,CV Maju Jaya,Manufaktur,Jakarta</code></div>
                        </div>
                        <div class="alert alert-warning border-warning p-2 small mb-0" style="background:#fffbeb">
                            <i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i>
                            <strong>Pastikan akun yang dimasukkan benar-benar akun perusahaan.</strong>
                            Bukan akun alumni yang salah dimasukkan di sini.
                            Kolom <code>industri</code> contohnya: Manufaktur, Teknologi, Perdagangan, Jasa, dll.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-people me-2"></i>Buat Semua Akun
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var roleEl       = document.getElementById('manualRole');
    var textarea     = document.getElementById('manualTextarea');
    var guideAlumni  = document.getElementById('manualGuideAlumni');
    var guideCompany = document.getElementById('manualGuideCompany');

    var cfg = {
        alumni: {
            placeholder: 'username,email,nama_lengkap,nis,nisn,gender,jurusan,angkatan\nbudi.s,budi@mail.com,Budi Santoso,1001,9900001,male,RPL,2024\nsiti.r,siti@mail.com,Siti Rahayu,1002,9900002,female,RPL,2024'
        },
        company: {
            placeholder: 'username,email,nama_perusahaan,industri,kota\ncv.maju,maju@mail.com,CV Maju Jaya,Manufaktur,Jakarta\npt.digital,digital@mail.com,PT Digital Nusantara,Teknologi,Surabaya'
        }
    };

    function update() {
        var role = roleEl.value;
        var isAlumni = (role === 'alumni');
        textarea.placeholder = cfg[role].placeholder;
        guideAlumni.style.display  = isAlumni ? '' : 'none';
        guideCompany.style.display = isAlumni ? 'none' : '';
    }

    roleEl.addEventListener('change', update);
    update();
})();
</script>

<?php elseif ($tab === 'bulk_result' && $bulkResult): ?>
<!-- BULK RESULT -->
<div class="row g-3 mb-4">
    <?php foreach ([['Berhasil Dibuat', count($bulkResult['created']), 'success','check-circle'],
                   ['Gagal / Dilewati', count($bulkResult['failed']), 'danger','x-circle']] as [$l,$v,$c,$i]): ?>
    <div class="col-sm-4 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="fw-bold fs-2 text-<?= $c ?>"><?= $v ?></div>
            <div class="text-muted small"><i class="bi bi-<?= $i ?> me-1"></i><?= $l ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($bulkResult['created']): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-success"><i class="bi bi-check-circle me-2"></i>Berhasil (<?= count($bulkResult['created']) ?>)</span>
        <button onclick="printBulkCredentials()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Cetak</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>#</th><th>Username</th><th>Email</th><th>Role</th>
                <th style="color:#c00">Password Sementara</th></tr></thead>
            <tbody>
                <?php foreach ($bulkResult['created'] as $i => $c): ?>
                <tr>
                    <td class="small text-muted"><?= $i+1 ?></td>
                    <td class="fw-semibold"><?= e($c['username']) ?></td>
                    <td class="small text-muted"><?= e($c['email']) ?></td>
                    <td><span class="badge bg-info"><?= e($c['role']) ?></span></td>
                    <td>
                        <code class="fw-bold text-danger" style="letter-spacing:1px"><?= e($c['password']) ?></code>
                        <button class="btn btn-link p-0 ms-2 copy-pwd-btn" data-val="<?= e($c['password']) ?>">
                            <i class="bi bi-clipboard text-muted" style="font-size:.8rem"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        <div class="alert alert-warning mb-0 small d-flex gap-2">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
            Password hanya ditampilkan sekali. Cetak atau catat sebelum menutup halaman ini.
        </div>
    </div>
</div>

<!-- Print Area untuk Bulk Import (hidden, hanya muncul saat cetak) -->
<div id="printArea">
    <h2 style="font-family:sans-serif;border-bottom:2px solid #1e3a8a;padding-bottom:10px;color:#1e3a8a;margin-bottom:6px">
        LORDIK — Daftar Kredensial Akun (Import Massal)
    </h2>
    <p style="font-family:sans-serif;font-size:.85rem;color:#666;margin-bottom:16px">
        Dicetak: <?= date('d M Y H:i') ?> &nbsp;|&nbsp;
        Total berhasil: <?= count($bulkResult['created']) ?> akun &nbsp;|&nbsp;
        Ganti password segera setelah login pertama.
    </p>
    <table style="font-family:sans-serif;width:100%;border-collapse:collapse;font-size:10pt">
        <thead>
            <tr style="background:#1e3a8a;color:#fff">
                <th style="padding:8px 10px;border:1px solid #cbd5e1;text-align:left">#</th>
                <th style="padding:8px 10px;border:1px solid #cbd5e1;text-align:left">Username</th>
                <th style="padding:8px 10px;border:1px solid #cbd5e1;text-align:left">Email</th>
                <th style="padding:8px 10px;border:1px solid #cbd5e1;text-align:left">Role</th>
                <th style="padding:8px 10px;border:1px solid #cbd5e1;text-align:left;color:#ffd700">Password Sementara</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bulkResult['created'] as $idx => $c): ?>
            <tr style="background:<?= $idx % 2 === 0 ? '#f8fafc' : '#fff' ?>">
                <td style="padding:7px 10px;border:1px solid #e2e8f0;color:#64748b"><?= $idx + 1 ?></td>
                <td style="padding:7px 10px;border:1px solid #e2e8f0;font-weight:600"><?= e($c['username']) ?></td>
                <td style="padding:7px 10px;border:1px solid #e2e8f0;color:#475569"><?= e($c['email']) ?></td>
                <td style="padding:7px 10px;border:1px solid #e2e8f0"><?= e(strtoupper($c['role'])) ?></td>
                <td style="padding:7px 10px;border:1px solid #e2e8f0;font-weight:700;color:#c00;letter-spacing:2px;font-size:11pt"><?= e($c['password']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p style="font-family:sans-serif;margin-top:20px;font-size:.8rem;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:10px">
        ⚠️ Dokumen ini bersifat rahasia. Serahkan kredensial langsung kepada masing-masing pengguna.
        Password akan diminta diganti saat login pertama kali.
    </p>
</div>

<script>
document.querySelectorAll('.copy-pwd-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        navigator.clipboard.writeText(this.dataset.val).then(() => {
            const i = this.querySelector('i');
            i.className = 'bi bi-clipboard-check text-success';
            setTimeout(() => i.className = 'bi bi-clipboard text-muted', 1800);
        });
    });
});

function printBulkCredentials() {
    const printArea = document.getElementById('printArea');
    // Pindahkan ke body agar tidak di dalam #adminContent yang di-hide saat print
    document.body.appendChild(printArea);
    printArea.classList.remove('d-none');
    window.print();
    // Sembunyikan kembali setelah print selesai
    window.addEventListener('afterprint', function() {
        printArea.classList.add('d-none');
    }, { once: true });
}
</script>
<?php endif; ?>

<?php if ($bulkResult['failed']): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-bottom fw-semibold text-danger">
        <i class="bi bi-x-circle me-2"></i>Gagal (<?= count($bulkResult['failed']) ?>)
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Baris</th><th>Data</th><th>Alasan</th></tr></thead>
            <tbody>
                <?php foreach ($bulkResult['failed'] as $f): ?>
                <tr>
                    <td class="small text-muted"><?= $f['row'] ?></td>
                    <td class="small"><?= e($f['data']['username'] ?? '-') ?> / <?= e($f['data']['email'] ?? '-') ?></td>
                    <td class="small text-danger"><?= e($f['reason']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="d-flex gap-2">
    <a href="?tab=bulk" class="btn btn-primary"><i class="bi bi-upload me-2"></i>Import Lagi</a>
    <a href="?tab=list" class="btn btn-outline-secondary"><i class="bi bi-list-ul me-2"></i>Daftar Akun</a>
</div>

<?php endif; ?>

<?php require_once BASE_PATH . '/app/Views/layouts/footer.php'; ?>
