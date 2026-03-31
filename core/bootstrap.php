<?php
/**
 * core/bootstrap.php — Tim: Backend
 * Titik masuk semua request.
 */
define('BASE_PATH', dirname(__DIR__));

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
}
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
    session_unset(); session_destroy(); session_name(SESSION_NAME); session_start();
}
if (!empty($_SESSION['user_id'])) $_SESSION['last_activity'] = time();

require_once BASE_PATH . '/helpers/functions.php';
require_once BASE_PATH . '/helpers/auth_helper.php';
require_once BASE_PATH . '/helpers/activity_log.php';
require_once BASE_PATH . '/helpers/notification_helper.php';
require_once BASE_PATH . '/helpers/upload_helper.php';

// Base classes
require_once BASE_PATH . '/app/Controllers/BaseController.php';
require_once BASE_PATH . '/app/Models/BaseModel.php';

// Models
require_once BASE_PATH . '/app/Models/NotificationModel.php';
require_once BASE_PATH . '/app/Models/UserModel.php';
require_once BASE_PATH . '/app/Models/AlumniModel.php';
require_once BASE_PATH . '/app/Models/CompanyModel.php';
require_once BASE_PATH . '/app/Models/VacancyModel.php';
require_once BASE_PATH . '/app/Models/ApplicationModel.php';
require_once BASE_PATH . '/app/Models/MessageModel.php';
require_once BASE_PATH . '/app/Models/AdminModel.php';

// Modules (business logic)
require_once BASE_PATH . '/modules/auth/AuthModule.php';
require_once BASE_PATH . '/modules/alumni/AlumniModule.php';
require_once BASE_PATH . '/modules/company/CompanyModule.php';
require_once BASE_PATH . '/modules/vacancy/VacancyModule.php';
require_once BASE_PATH . '/modules/application/ApplicationModule.php';
require_once BASE_PATH . '/modules/chat/ChatModule.php';
require_once BASE_PATH . '/modules/admin/AdminModule.php';

// Controllers
require_once BASE_PATH . '/app/Controllers/AdminController.php';
require_once BASE_PATH . '/app/Controllers/AuthController.php';
require_once BASE_PATH . '/app/Controllers/VacancyController.php';
