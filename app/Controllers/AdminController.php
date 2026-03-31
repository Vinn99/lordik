<?php
/**
 * AdminController — Tim Backend
 */

class AdminController
{
    public static function createUser(): void
    {
        requireAdmin();
        validateCsrf();

        $result = UserModel::create($_POST);
        if (!$result['success']) {
            setFlash('danger', $result['message']);
            redirect('/admin/users.php?tab=create');
        }

        // Auto-create profile stubs
        if ($_POST['role'] === 'alumni' && !empty($_POST['full_name']) && !empty($_POST['nis'])) {
            try {
                AlumniModel::upsert($result['user_id'], array_merge($_POST, ['graduation_year' => $_POST['graduation_year'] ?? date('Y')]));
            } catch (\Exception $e) { /* skip duplicate NIS */ }
        }
        if ($_POST['role'] === 'company' && !empty($_POST['company_name'])) {
            try { CompanyModel::upsert($result['user_id'], $_POST); } catch (\Exception $e) { /* skip */ }
        }

        $cred = ['username' => $result['username'], 'email' => $result['email'],
                 'role' => $result['role'], 'password' => $result['temp_password']];
        if (!empty($_POST['full_name']))    $cred['full_name']    = $_POST['full_name'];
        if (!empty($_POST['nis']))          $cred['nis']          = $_POST['nis'];
        if (!empty($_POST['nisn']))         $cred['nisn']         = $_POST['nisn'];
        if (!empty($_POST['company_name'])) $cred['company_name'] = $_POST['company_name'];
        $_SESSION['new_credential'] = $cred;

        logActivity(currentUserId(), 'create_user', 'admin', "Created: {$result['username']} role={$result['role']}");
        redirect('/admin/user_credential.php');
    }

    public static function bulkCreateUsers(): void
    {
        requireAdmin();
        validateCsrf();
        $importRole = $_POST['import_role'] ?? 'alumni';
        $rows       = [];

        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $fh = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $headers = null;
            while (($line = fgetcsv($fh)) !== false) {
                if ($headers === null) { $headers = array_map('strtolower', array_map('trim', $line)); continue; }
                $row = [];
                foreach ($headers as $i => $h) $row[$h] = trim($line[$i] ?? '');
                $row['role'] = $row['role'] ?? $importRole;
                if (!empty($row['username']) && !empty($row['email'])) $rows[] = $row;
            }
            fclose($fh);
        } elseif (!empty($_POST['manual_rows'])) {
            foreach (explode("\n", trim($_POST['manual_rows'])) as $line) {
                $parts = array_map('trim', explode(',', $line));
                if (count($parts) >= 2 && $parts[0] && $parts[1]) {
                    $rows[] = ['username' => $parts[0], 'email' => $parts[1],
                               'full_name' => $parts[2] ?? '', 'nis' => $parts[3] ?? '', 'nisn' => $parts[4] ?? '',
                               'role' => $importRole, 'gender' => 'male',
                               'jurusan' => $_POST['default_jurusan'] ?? 'RPL',
                               'graduation_year' => $_POST['default_year'] ?? date('Y')];
                }
            }
        }

        if (!$rows) { setFlash('danger', 'Tidak ada data valid.'); redirect('/admin/users.php?tab=bulk'); }

        $created = []; $failed = [];
        foreach ($rows as $i => $row) {
            $r = UserModel::create($row);
            if ($r['success']) {
                if ($row['role'] === 'alumni' && !empty($row['full_name']) && !empty($row['nis'])) {
                    try { AlumniModel::upsert($r['user_id'], $row); } catch (\Exception $e) {}
                }
                $created[] = ['username' => $row['username'], 'email' => $row['email'],
                              'role' => $row['role'], 'password' => $r['temp_password']];
            } else {
                $failed[] = ['row' => $i + 2, 'data' => $row, 'reason' => $r['message']];
            }
        }
        $_SESSION['bulk_result'] = ['created' => $created, 'failed' => $failed];
        logActivity(currentUserId(), 'bulk_create', 'admin', "Bulk import: " . count($created) . " created");
        redirect('/admin/users.php?tab=bulk_result');
    }

    public static function toggleActive(): void
    {
        requireAdmin(); validateCsrf();
        UserModel::toggleActive((int)$_POST['user_id']);
        setFlash('success', 'Status akun berhasil diubah.');
        redirect('/admin/users.php');
    }

    public static function resetPassword(): void
    {
        requireAdmin(); validateCsrf();
        $result = UserModel::resetPassword((int)$_POST['user_id']);
        if ($result['success']) {
            $_SESSION['new_credential'] = $result;
            redirect('/admin/user_credential.php');
        }
        setFlash('danger', 'Gagal reset password.');
        redirect('/admin/users.php');
    }

    public static function deleteUser(): void
    {
        requireAdmin(); validateCsrf();
        UserModel::softDelete((int)$_POST['user_id']);
        setFlash('success', 'Akun berhasil dihapus.');
        redirect('/admin/users.php');
    }

    public static function updateAlumniWorkStatus(): void
    {
        requireAdmin(); validateCsrf();
        $alumniId = (int)$_POST['alumni_id'];
        $status   = sanitize($_POST['work_status']);
        AlumniModel::updateWorkStatus($alumniId, $status);
        setFlash('success', 'Status kerja alumni berhasil diubah.');
        $ref = $_SERVER['HTTP_REFERER'] ?? APP_URL . '/admin/alumni/list.php';
        header('Location: ' . $ref); exit;
    }

    public static function approveVacancy(): void
    {
        requireAdmin(); validateCsrf();
        $id   = (int)$_POST['vacancy_id'];
        $note = sanitize($_POST['admin_note'] ?? '');
        VacancyModel::changeStatus($id, 'approved', currentUserId(), $note);
        // Notif company
        $vacancy = VacancyModel::findById($id);
        if ($vacancy) {
            $co = CompanyModel::findById($vacancy['company_id']);
            if ($co) sendNotification($co['user_id'], 'Lowongan Disetujui',
                "Lowongan \"{$vacancy['title']}\" telah disetujui dan akan ditampilkan.", 'success', '/company/vacancy/list.php');
        }
        setFlash('success', 'Lowongan berhasil disetujui.');
        redirect('/admin/vacancy/list.php');
    }

    public static function rejectVacancy(): void
    {
        requireAdmin(); validateCsrf();
        $id   = (int)$_POST['vacancy_id'];
        $note = sanitize($_POST['admin_note'] ?? '');
        VacancyModel::changeStatus($id, 'rejected', currentUserId(), $note);
        $vacancy = VacancyModel::findById($id);
        if ($vacancy) {
            $co = CompanyModel::findById($vacancy['company_id']);
            if ($co) sendNotification($co['user_id'], 'Lowongan Ditolak',
                "Lowongan \"{$vacancy['title']}\" tidak disetujui. Alasan: {$note}", 'error', '/company/vacancy/list.php');
        }
        setFlash('success', 'Lowongan ditolak.');
        redirect('/admin/vacancy/list.php');
    }

    public static function verifyCompany(): void
    {
        requireAdmin(); validateCsrf();
        $id  = (int)$_POST['company_id'];
        $val = (int)$_POST['verified'];
        CompanyModel::setVerified($id, $val);
        setFlash('success', $val ? 'Perusahaan diverifikasi.' : 'Verifikasi dicabut.');
        $ref = $_SERVER['HTTP_REFERER'] ?? APP_URL . '/admin/company/list.php';
        header('Location: ' . $ref); exit;
    }
}
