<?php
/**
 * app/Controllers/Alumni/ApplicationController.php — Tim: Backend
 */

class Alumni_ApplicationController extends BaseController {
    public function apply(int $vacancyId, string $coverLetter = ''): array {
        requireAlumni();
        return ApplicationModel::apply(currentUserId(), $vacancyId, $coverLetter);
    }

    public function list(): void {
        requireAlumni();
                $applications = ApplicationModel::listByAlumni(currentUserId());
        $pageTitle    = 'Lamaran Saya — ' . APP_NAME;
        $this->layoutHeader($pageTitle);
        $this->view('alumni/applications', compact('applications','pageTitle'));
        $this->layoutFooter();
    }

    public function detail(int $appId): void {
        requireLogin();
                $app = ApplicationModel::getById($appId);
        if (!$app) { setFlash('danger','Lamaran tidak ditemukan.'); redirect('/alumni/applications.php'); }
        if (isAlumni() && (int)$app['alumni_user_id'] !== currentUserId()) {
            http_response_code(403); setFlash('danger','Akses ditolak.'); redirect('/alumni/applications.php');
        }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            validateCsrf();
            $msg = trim($_POST['message']??'');
            if ($msg) MessageModel::send($appId, currentUserId(), $msg);
            redirect($_SERVER['REQUEST_URI']);
        }
        MessageModel::markRead($appId, currentUserId());
        $messages   = MessageModel::getByApp($appId, currentUserId());
        $logs       = ApplicationModel::getStatusLogs($appId);
        $pageTitle  = 'Detail Lamaran — ' . APP_NAME;
        $this->layoutHeader($pageTitle);
        $this->view('alumni/application_detail', compact('app','messages','logs','pageTitle'));
        $this->layoutFooter();
    }
}
