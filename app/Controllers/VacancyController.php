<?php
/**
 * VacancyController — Tim Backend
 */

class VacancyController
{
    public static function create(): void
    {
        requireCompany(); validateCsrf();
        $companyId = VacancyModel::getCompanyIdByUserId(currentUserId());
        if (!$companyId) { setFlash('warning', 'Lengkapi profil perusahaan dulu.'); redirect('/company/profile.php'); }
        $errors = self::_validate($_POST);
        if ($errors) { setFlash('danger', implode('<br>', $errors)); redirect('/company/vacancy/create.php'); }
        $id = VacancyModel::create($companyId, $_POST);
        // Notif admin
        $pdo = getDB();
        $admins = $pdo->query("SELECT id FROM users WHERE role='admin' AND is_active=1 AND deleted_at IS NULL")->fetchAll();
        foreach ($admins as $a) sendNotification($a['id'], 'Lowongan Baru Menunggu Persetujuan',
            "Lowongan baru \"{$_POST['title']}\" menunggu review.", 'info', '/admin/vacancy/list.php');
        logActivity(currentUserId(), 'create_vacancy', 'vacancy', "vacancy_id={$id}");
        setFlash('success', 'Lowongan berhasil dibuat dan menunggu persetujuan admin.');
        redirect('/company/vacancy/list.php');
    }

    public static function update(): void
    {
        requireCompany(); validateCsrf();
        $id = (int)($_POST['vacancy_id'] ?? 0);
        $vacancy = VacancyModel::findById($id);
        $companyId = VacancyModel::getCompanyIdByUserId(currentUserId());
        if (!$vacancy || (int)$vacancy['company_id'] !== $companyId) {
            setFlash('danger', 'Akses ditolak.'); redirect('/company/vacancy/list.php');
        }
        $errors = self::_validate($_POST);
        if ($errors) { setFlash('danger', implode('<br>', $errors)); redirect('/company/vacancy/edit.php?id=' . $id); }
        VacancyModel::update($id, $_POST);
        logActivity(currentUserId(), 'update_vacancy', 'vacancy', "vacancy_id={$id}");
        setFlash('success', 'Lowongan berhasil diperbarui.');
        redirect('/company/vacancy/list.php');
    }

    public static function close(): void
    {
        requireCompany(); validateCsrf();
        $id        = (int)$_POST['vacancy_id'];
        $companyId = VacancyModel::getCompanyIdByUserId(currentUserId());
        $vacancy   = VacancyModel::findById($id);
        if (!$vacancy || (int)$vacancy['company_id'] !== $companyId) {
            setFlash('danger', 'Akses ditolak.'); redirect('/company/vacancy/list.php');
        }
        VacancyModel::changeStatus($id, 'closed', currentUserId());
        setFlash('success', 'Lowongan ditutup.');
        redirect('/company/vacancy/list.php');
    }

    private static function _validate(array $data): array
    {
        $e = [];
        if (empty($data['title']))       $e[] = 'Judul lowongan wajib diisi.';
        if (empty($data['description'])) $e[] = 'Deskripsi wajib diisi.';
        return $e;
    }
}
