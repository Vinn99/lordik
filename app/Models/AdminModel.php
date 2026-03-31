<?php
/**
 * app/Models/AdminModel.php — Tim: Database
 * Model untuk operasi admin (statistik, export, user mgmt).
 */

class AdminModel extends BaseModel {
    protected static string $table = 'users';

    // ─────────────────────────────────────────────
    // User Management
    // ─────────────────────────────────────────────

    public static function createUser(array $data, int $adminId): array {
        $errors = [];
        if (empty($data['username'])) $errors[] = 'Username wajib diisi.';
        if (empty($data['email'])) $errors[] = 'Email wajib diisi.';
        if (empty($data['role']) || !in_array($data['role'], [ROLE_ALUMNI, ROLE_COMPANY, ROLE_ADMIN])) {
            $errors[] = 'Role tidak valid.';
        }
        if (!empty($errors)) return ['success' => false, 'errors' => $errors];

        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$data['username'], $data['email']]);
        if ($stmt->fetch()) return ['success' => false, 'message' => 'Username atau email sudah digunakan.'];

        $tempPwd = 'Temp@' . random_int(1000, 9999);
        $hash    = password_hash($tempPwd, PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt2 = $pdo->prepare(
            "INSERT INTO users (username, email, password, role, force_change_pwd)
             VALUES (?, ?, ?, ?, 1)"
        );
        $stmt2->execute([
            sanitize($data['username']),
            sanitize($data['email']),
            $hash,
            $data['role'],
        ]);
        $userId = $pdo->lastInsertId();

        // Auto-create profile stub for alumni — create whenever role is alumni
        // Profile is created with whatever data is available; alumni can complete it later
        if ($data['role'] === ROLE_ALUMNI) {
            try {
                $nis  = sanitize(trim($data['nis']  ?? ''));
                $nisn = sanitize(trim($data['nisn'] ?? ''));
                $name = sanitize(trim($data['full_name'] ?? ($data['username'] ?? '')));
                // nis and nisn are NOT NULL UNIQUE — generate unique placeholders if missing
                if ($nis  === '') $nis  = 'NIS-'  . $userId . '-' . time();
                if ($nisn === '') $nisn = 'NISN-' . $userId . '-' . time();
                $pdo->prepare(
                    "INSERT INTO alumni_profiles (user_id, nis, nisn, full_name, gender, jurusan, graduation_year)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $userId, $nis, $nisn, $name,
                    $data['gender'] ?? 'male',
                    sanitize($data['jurusan'] ?? 'RPL'),
                    (int)($data['graduation_year'] ?? date('Y')),
                ]);
            } catch (PDOException $e) { /* skip if duplicate NIS/NISN */ }
        }

        // Auto-create company profile stub
        if ($data['role'] === ROLE_COMPANY && !empty($data['company_name'])) {
            try {
                $pdo->prepare(
                    "INSERT INTO companies (user_id, company_name, industry, city)
                     VALUES (?, ?, ?, ?)"
                )->execute([
                    $userId,
                    sanitize($data['company_name']),
                    sanitize($data['industry'] ?? ''),
                    sanitize($data['city'] ?? ''),
                ]);
            } catch (PDOException $e) { /* skip */ }
        }

        logActivity($adminId, 'create_user', 'admin', "Created user: {$data['username']} role={$data['role']}");

        return ['success' => true, 'user_id' => $userId, 'temp_password' => $tempPwd,
                'username' => $data['username'], 'email' => $data['email'], 'role' => $data['role']];
    }

    // Bulk create from CSV rows
    public static function bulkCreateUsers(array $rows, int $adminId): array {
        $created = [];
        $failed  = [];

        foreach ($rows as $i => $row) {
            $row['username'] = $row['username'] ?? trim($row[0] ?? '');
            $row['email']    = $row['email']    ?? trim($row[1] ?? '');
            $row['role']     = $row['role']      ?? trim($row[2] ?? 'alumni');

            if (empty($row['username']) || empty($row['email'])) {
                $failed[] = ['row' => $i + 2, 'data' => $row, 'reason' => 'Username/email kosong'];
                continue;
            }

            $result = self::createUser($row, $adminId);
            if ($result['success']) {
                $created[] = [
                    'username' => $row['username'],
                    'email'    => $row['email'],
                    'role'     => $row['role'],
                    'password' => $result['temp_password'],
                ];
            } else {
                $failed[] = ['row' => $i + 2, 'data' => $row, 'reason' => $result['message'] ?? 'Error'];
            }
        }

        return ['created' => $created, 'failed' => $failed];
    }

    public static function toggleUserActive(int $targetId, int $adminId): array {
        $pdo  = getDB();
        $stmt = $pdo->prepare("SELECT is_active, username FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$targetId]);
        $user = $stmt->fetch();
        if (!$user) return ['success' => false, 'message' => 'User tidak ditemukan.'];

        $newStatus = $user['is_active'] ? 0 : 1;
        $stmt2     = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt2->execute([$newStatus, $targetId]);

        $action = $newStatus ? 'activate_user' : 'deactivate_user';
        logActivity($adminId, $action, 'admin', "User {$user['username']} is_active={$newStatus}");

        return ['success' => true, 'is_active' => $newStatus];
    }

    public static function softDeleteUser(int $targetId, int $adminId): array {
        $pdo  = getDB();
        $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ? AND role != 'admin'");
        $stmt->execute([$targetId]);
        logActivity($adminId, 'delete_user', 'admin', "Soft deleted user_id={$targetId}");
        return ['success' => true];
    }

    public static function listUsers(array $filters = [], int $limit = PER_PAGE, int $offset = 0): array {
        $pdo    = getDB();
        $where  = ["deleted_at IS NULL"];
        $params = [];

        if (!empty($filters['role'])) {
            $where[]  = "role = ?";
            $params[] = $filters['role'];
        }
        if (!empty($filters['search'])) {
            $where[]  = "(username LIKE ? OR email LIKE ?)";
            $s        = '%' . $filters['search'] . '%';
            $params[] = $s;
            $params[] = $s;
        }

        $whereSQL  = implode(' AND ', $where);
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$whereSQL}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $params[] = $limit;
        $params[] = $offset;
        $stmt     = $pdo->prepare("SELECT id, username, email, role, is_active, force_change_pwd, last_login, created_at FROM users WHERE {$whereSQL} ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute($params);
        return ['data' => $stmt->fetchAll(), 'total' => $total];
    }

    // ─────────────────────────────────────────────
    // Dashboard Statistics
    // ─────────────────────────────────────────────

    public static function getDashboardStats(): array {
        $pdo = getDB();

        $stats = [];

        // Total alumni
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'alumni' AND deleted_at IS NULL");
        $stmt->execute();
        $stats['total_alumni'] = (int)$stmt->fetchColumn();

        // Alumni bekerja
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM alumni_profiles WHERE work_status = 'employed'");
        $stmt->execute();
        $stats['employed_alumni'] = (int)$stmt->fetchColumn();

        // Alumni tidak bekerja
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM alumni_profiles WHERE work_status = 'unemployed'");
        $stmt->execute();
        $stats['unemployed_alumni'] = (int)$stmt->fetchColumn();

        // Lowongan aktif (approved)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_vacancies WHERE status = 'approved' AND deleted_at IS NULL");
        $stmt->execute();
        $stats['active_vacancies'] = (int)$stmt->fetchColumn();

        // Lowongan pending
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM job_vacancies WHERE status = 'submitted' AND deleted_at IS NULL");
        $stmt->execute();
        $stats['pending_vacancies'] = (int)$stmt->fetchColumn();

        // Total perusahaan
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'company' AND deleted_at IS NULL");
        $stmt->execute();
        $stats['total_companies'] = (int)$stmt->fetchColumn();

        // Total lamaran
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM applications");
        $stmt->execute();
        $stats['total_applications'] = (int)$stmt->fetchColumn();

        // Statistik per jurusan
        $stmt = $pdo->prepare(
            "SELECT jurusan,
                    COUNT(*) as total,
                    SUM(CASE WHEN work_status='employed'       THEN 1 ELSE 0 END) as employed,
                    SUM(CASE WHEN work_status='unemployed'     THEN 1 ELSE 0 END) as unemployed,
                    SUM(CASE WHEN work_status='entrepreneur'   THEN 1 ELSE 0 END) as entrepreneur,
                    SUM(CASE WHEN work_status='continuing_edu' THEN 1 ELSE 0 END) as continuing_edu
             FROM alumni_profiles
             GROUP BY jurusan
             ORDER BY total DESC"
        );
        $stmt->execute();
        $stats['by_jurusan'] = $stmt->fetchAll();

        // Statistik work_status breakdown
        $stmt = $pdo->prepare(
            "SELECT work_status, COUNT(*) as total FROM alumni_profiles GROUP BY work_status"
        );
        $stmt->execute();
        $stats['work_status_breakdown'] = $stmt->fetchAll();

        // Monthly application trend (last 6 months)
        $stmt = $pdo->prepare(
            "SELECT DATE_FORMAT(applied_at, '%Y-%m') as month, COUNT(*) as total
             FROM applications
             WHERE applied_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY month
             ORDER BY month ASC"
        );
        $stmt->execute();
        $stats['monthly_applications'] = $stmt->fetchAll();

        return $stats;
    }

    // ─────────────────────────────────────────────
    // Export
    // ─────────────────────────────────────────────

    public static function exportAlumniCSV(array $filters = []): string {
        // Export sebagai Excel XML (.xls) agar terbuka sebagai tabel rapi di Excel/LibreOffice
        $result = AlumniModel::searchAlumni($filters, 10000, 0);
        $rows   = $result['data'];

        $dir = EXPORT_DIR;
        if (!is_dir($dir)) mkdir($dir, 0750, true);

        $filename = 'alumni_export_' . date('Ymd_His') . '.xls';
        $filepath = $dir . $filename;

        $headers = ['NIS', 'NISN', 'Nama Lengkap', 'Jurusan', 'Angkatan', 'Status Kerja', 'No HP', 'Email'];

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"';
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";
        $xml .= '<Styles>';
        $xml .= '<Style ss:ID="header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#2563EB" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/></Style>';
        $xml .= '<Style ss:ID="cell"><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
        $xml .= '<Style ss:ID="cellAlt"><Alignment ss:Vertical="Center"/><Interior ss:Color="#F8FAFC" ss:Pattern="Solid"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#E2E8F0"/></Borders></Style>';
        $xml .= '</Styles>';
        $xml .= '<Worksheet ss:Name="Data Alumni"><Table>';

        foreach ([80, 90, 160, 140, 80, 120, 110, 180] as $w) {
            $xml .= '<Column ss:Width="' . $w . '"/>';
        }

        $xml .= '<Row ss:Height="22">';
        foreach ($headers as $h) {
            $xml .= '<Cell ss:StyleID="header"><Data ss:Type="String">' . htmlspecialchars($h, ENT_XML1) . '</Data></Cell>';
        }
        $xml .= '</Row>';

        foreach ($rows as $i => $row) {
            $style = ($i % 2 === 0) ? 'cell' : 'cellAlt';
            $xml .= '<Row ss:Height="18">';
            $cells = [
                $row['nis'] ?? '',
                $row['nisn'] ?? '',
                $row['full_name'] ?? '',
                $row['jurusan'] ?? '',
                $row['graduation_year'] ?? '',
                workStatusLabel($row['work_status'] ?? ''),
                $row['phone'] ?? '',
                $row['email'] ?? '',
            ];
            foreach ($cells as $cell) {
                $xml .= '<Cell ss:StyleID="' . $style . '"><Data ss:Type="String">' . htmlspecialchars((string)$cell, ENT_XML1) . '</Data></Cell>';
            }
            $xml .= '</Row>';
        }

        $xml .= '</Table>';
        $xml .= '<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">';
        $xml .= '<FreezePanes/><FrozenNoSplit/><SplitHorizontal>1</SplitHorizontal>';
        $xml .= '<TopRowBottomPane>1</TopRowBottomPane><ActivePane>2</ActivePane>';
        $xml .= '</WorksheetOptions>';
        $xml .= '</Worksheet></Workbook>';

        file_put_contents($filepath, $xml);

        logActivity(currentUserId(), 'export_alumni_excel', 'admin', "Exported alumni Excel: {$filename}");
        return $filepath;
    }

    public static function exportAlumniPDF(array $filters = []): string {
        $result = AlumniModel::searchAlumni($filters, 10000, 0);
        $rows   = $result['data'];
        $stats  = self::getDashboardStats();

        $dir = EXPORT_DIR;
        if (!is_dir($dir)) mkdir($dir, 0750, true);

        $filename = 'alumni_report_' . date('Ymd_His') . '.html';
        $filepath = $dir . $filename;

        ob_start();
        include BASE_PATH . '/resources/views/admin/export_pdf_template.php';
        $html = ob_get_clean();

        file_put_contents($filepath, $html);
        logActivity(currentUserId(), 'export_alumni_pdf', 'admin', "Exported alumni PDF template: {$filename}");
        return $filepath;
    }
}
