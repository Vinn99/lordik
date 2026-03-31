<?php
/**
 * app/Models/ApplicationModel.php — Tim: Database
 * Logika status:
 *   - Alumni boleh lamar banyak lowongan
 *   - Saat satu diproses (reviewed/shortlisted) → lainnya jadi FROZEN
 *   - Ditolak → unfreeze, status alumni tidak berubah
 *   - Diterima → alumni=employed, semua lainnya rejected
 */
require_once BASE_PATH . '/app/Models/BaseModel.php';

class ApplicationModel extends BaseModel {
    protected static string $table = 'applications';

    public static function apply(int $userId, int $vacancyId, string $coverLetter = ''): array {
        $alumni = static::queryOne("SELECT id, work_status FROM alumni_profiles WHERE user_id=? LIMIT 1", [$userId]);
        if (!$alumni) return ['success'=>false,'message'=>'Lengkapi profil alumni terlebih dahulu.'];
        if ($alumni['work_status'] === 'employed')
            return ['success'=>false,'message'=>'Anda sudah berstatus Bekerja dan tidak dapat melamar lowongan baru.'];

        $vacancy = static::queryOne("SELECT * FROM job_vacancies WHERE id=? AND status='approved' AND deleted_at IS NULL LIMIT 1", [$vacancyId]);
        if (!$vacancy) return ['success'=>false,'message'=>'Lowongan tidak tersedia.'];
        if ($vacancy['deadline'] && strtotime($vacancy['deadline']) < strtotime('today'))
            return ['success'=>false,'message'=>'Lowongan sudah melewati batas waktu lamaran.'];

        $dup = static::queryOne("SELECT id,status FROM applications WHERE vacancy_id=? AND alumni_id=? LIMIT 1", [$vacancyId, $alumni['id']]);
        if ($dup) {
            if ($dup['status'] === 'frozen') return ['success'=>false,'message'=>'Lamaran pada posisi ini sedang ditangguhkan karena ada lamaran lain yang sedang diproses.'];
            return ['success'=>false,'message'=>'Anda sudah melamar pada lowongan ini.'];
        }

        try {
            static::execute("INSERT INTO applications (vacancy_id,alumni_id,cover_letter,status) VALUES (?,?,?,'pending')", [$vacancyId,$alumni['id'],$coverLetter]);
            $appId = static::lastId();
        } catch (PDOException $e) {
            if ($e->getCode()==23000) return ['success'=>false,'message'=>'Anda sudah melamar pada lowongan ini.'];
            throw $e;
        }

        logApplicationStatusChange($appId, null, 'pending', $userId, 'Lamaran dikirim');
        logActivity($userId, 'apply_vacancy', 'application', "Applied to vacancy_id={$vacancyId}");
        $co = static::queryOne("SELECT user_id FROM companies WHERE id=? LIMIT 1", [$vacancy['company_id']]);
        if ($co) sendNotification($co['user_id'],'Lamaran Baru',"Ada lamaran baru untuk posisi \"{$vacancy['title']}\"",'info','/company/applications.php');
        return ['success'=>true,'id'=>$appId];
    }

    public static function updateStatus(int $appId, string $newStatus, int $byUserId, string $notes = ''): array {
        if (!in_array($newStatus, ['pending','reviewed','shortlisted','rejected','accepted'], true))
            return ['success'=>false,'message'=>'Status tidak valid.'];

        $app = static::getById($appId);
        if (!$app) return ['success'=>false,'message'=>'Lamaran tidak ditemukan.'];

        if (!isAdmin()) {
            $row = static::queryOne("SELECT c.user_id FROM job_vacancies jv JOIN companies c ON c.id=jv.company_id WHERE jv.id=? LIMIT 1", [$app['vacancy_id']]);
            if (!$row || (int)$row['user_id'] !== $byUserId)
                return ['success'=>false,'message'=>'Tidak memiliki izin mengubah status ini.'];
        }

        $oldStatus  = $app['status'];
        $alumniId   = $app['alumni_id'];
        $pdo        = static::db();

        $pdo->beginTransaction();
        try {
            static::execute("UPDATE applications SET status=?,notes=?,updated_by=? WHERE id=?", [$newStatus,$notes,$byUserId,$appId]);

            // FREEZE: saat diproses → pending lain jadi frozen
            if (in_array($newStatus, ['reviewed','shortlisted'])) {
                static::execute("UPDATE applications SET status='frozen' WHERE alumni_id=? AND id!=? AND status='pending'", [$alumniId,$appId]);
            }

            // UNFREEZE: saat ditolak → frozen kembali ke pending (jika tidak ada yg masih diproses)
            if ($newStatus === 'rejected') {
                $stillActive = static::count("SELECT COUNT(*) FROM applications WHERE alumni_id=? AND id!=? AND status IN ('reviewed','shortlisted')", [$alumniId,$appId]);
                if ($stillActive === 0)
                    static::execute("UPDATE applications SET status='pending' WHERE alumni_id=? AND status='frozen'", [$alumniId]);
            }

            // ACCEPT: alumni=employed + tolak semua lamaran lain
            if ($newStatus === 'accepted') {
                static::execute("UPDATE alumni_profiles SET work_status='employed' WHERE id=?", [$alumniId]);
                static::execute("UPDATE applications SET status='rejected',notes='Otomatis ditolak karena lamaran lain diterima' WHERE alumni_id=? AND id!=? AND status IN ('pending','frozen','reviewed','shortlisted')", [$alumniId,$appId]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        logApplicationStatusChange($appId, $oldStatus, $newStatus, $byUserId, $notes);
        logActivity($byUserId, 'update_app_status', 'application', "app_id={$appId} {$oldStatus}→{$newStatus}");

        $alumniUserId = static::queryOne("SELECT ap.user_id FROM applications a JOIN alumni_profiles ap ON ap.id=a.alumni_id WHERE a.id=? LIMIT 1", [$appId])['user_id'] ?? null;
        if ($alumniUserId) {
            $labels = ['reviewed'=>'Sedang Ditinjau','shortlisted'=>'Masuk Shortlist','accepted'=>'Diterima! 🎉','rejected'=>'Tidak Diterima','frozen'=>'Ditangguhkan'];
            $types  = ['accepted'=>'success','rejected'=>'error'];
            sendNotification($alumniUserId,'Update Status Lamaran',"Lamaran \"{$app['vacancy_title']}\" — ".($labels[$newStatus]??$newStatus),$types[$newStatus]??'info','/alumni/applications.php');
        }
        return ['success'=>true];
    }

    public static function getById(int $id): ?array {
        return static::queryOne(
            "SELECT a.*, jv.title as vacancy_title, jv.company_id, jv.deadline as vacancy_deadline,
                    ap.full_name as alumni_name, ap.user_id as alumni_user_id, ap.id as alumni_id,
                    c.company_name
             FROM applications a
             JOIN job_vacancies jv ON jv.id=a.vacancy_id
             JOIN alumni_profiles ap ON ap.id=a.alumni_id
             JOIN companies c ON c.id=jv.company_id
             WHERE a.id=? LIMIT 1", [$id]);
    }

    public static function getStatusLogs(int $appId): array {
        return static::query("SELECT asl.*, u.username FROM application_status_logs asl LEFT JOIN users u ON u.id=asl.changed_by WHERE asl.application_id=? ORDER BY asl.created_at ASC", [$appId]);
    }

    public static function listByAlumni(int $userId): array {
        return static::query(
            "SELECT a.*, jv.title as vacancy_title, jv.deadline as vacancy_deadline,
                    c.company_name, c.logo_path, c.city
             FROM applications a
             JOIN job_vacancies jv ON jv.id=a.vacancy_id
             JOIN alumni_profiles ap ON ap.id=a.alumni_id
             JOIN companies c ON c.id=jv.company_id
             WHERE ap.user_id=?
             ORDER BY FIELD(a.status,'shortlisted','reviewed','pending','frozen','accepted','rejected'), a.applied_at DESC",
            [$userId]);
    }

    public static function listByCompany(int $companyId, array $filters=[], int $page=1): array {
        $where  = ["jv.company_id=?","jv.deleted_at IS NULL"];
        $params = [$companyId];
        if (!empty($filters['vacancy_id'])) { $where[]="a.vacancy_id=?"; $params[]=(int)$filters['vacancy_id']; }
        if (!empty($filters['status']))     { $where[]="a.status=?";     $params[]=$filters['status']; }
        if (!empty($filters['search']))     { $where[]="(ap.full_name LIKE ? OR ap.jurusan LIKE ?)"; $params[]="%{$filters['search']}%"; $params[]="%{$filters['search']}%"; }
        $sql   = implode(' AND ',$where);
        $total = static::count("SELECT COUNT(*) FROM applications a JOIN job_vacancies jv ON jv.id=a.vacancy_id JOIN alumni_profiles ap ON ap.id=a.alumni_id WHERE {$sql}", $params);
        $pager = static::buildPagination($total,$page);
        $p2    = array_merge($params,[$pager['per_page'],$pager['offset']]);
        $rows  = static::query("SELECT a.*,jv.title as vacancy_title,ap.full_name,ap.jurusan,ap.phone,ap.cv_path,ap.photo_path,ap.gender,ap.birth_date,ap.graduation_year,ap.skills,ap.work_status,ap.id as alumni_profile_id FROM applications a JOIN job_vacancies jv ON jv.id=a.vacancy_id JOIN alumni_profiles ap ON ap.id=a.alumni_id WHERE {$sql} ORDER BY FIELD(a.status,'shortlisted','reviewed','pending','frozen','accepted','rejected'),a.applied_at DESC LIMIT ? OFFSET ?", $p2);
        return ['data'=>$rows,'pager'=>$pager,'total'=>$total];
    }

    public static function listAll(array $filters=[], int $page=1): array {
        $where=['1=1']; $params=[];
        if (!empty($filters['status']))     { $where[]="a.status=?";     $params[]=$filters['status']; }
        if (!empty($filters['vacancy_id'])){ $where[]="a.vacancy_id=?"; $params[]=(int)$filters['vacancy_id']; }
        $sql=$implode=implode(' AND ',$where);
        $total=static::count("SELECT COUNT(*) FROM applications a WHERE {$sql}",$params);
        $pager=static::buildPagination($total,$page);
        $p2=array_merge($params,[$pager['per_page'],$pager['offset']]);
        $rows=static::query("SELECT a.*,jv.title as vacancy_title,ap.full_name,c.company_name FROM applications a JOIN job_vacancies jv ON jv.id=a.vacancy_id JOIN alumni_profiles ap ON ap.id=a.alumni_id JOIN companies c ON c.id=jv.company_id WHERE {$sql} ORDER BY a.applied_at DESC LIMIT ? OFFSET ?",$p2);
        return ['data'=>$rows,'pager'=>$pager,'total'=>$total];
    }

    public static function listByVacancy(int $vacancyId): array {
        return static::query("SELECT a.*,ap.full_name,ap.jurusan,ap.photo_path,ap.gender,ap.birth_date,ap.id as alumni_profile_id FROM applications a JOIN alumni_profiles ap ON ap.id=a.alumni_id WHERE a.vacancy_id=? ORDER BY FIELD(a.status,'shortlisted','reviewed','pending','frozen','accepted','rejected'),a.applied_at DESC",[$vacancyId]);
    }

    public static function countByStatus(int $companyId): array {
        $rows=static::query("SELECT a.status,COUNT(*) as cnt FROM applications a JOIN job_vacancies jv ON jv.id=a.vacancy_id WHERE jv.company_id=? AND jv.deleted_at IS NULL GROUP BY a.status",[$companyId]);
        $r=[];foreach($rows as $row)$r[$row['status']]=(int)$row['cnt'];return $r;
    }
}
