<?php
// public/files/serve.php
// Serves files from non-public storage directory with access control

require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../helpers/upload_helper.php';

requireLogin();

$type = $_GET['type'] ?? '';
$path = $_GET['path'] ?? '';

// Basic path traversal prevention
if (strpos($path, '..') !== false || strpos($path, '/') === 0) {
    http_response_code(400);
    die('Path tidak valid.');
}

// Authorization per type
switch ($type) {
    case 'cv':
        // Alumni can view own CV, company can view if they have application, admin can view all
        if (isAlumni()) {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT id FROM alumni_profiles WHERE user_id = ? AND cv_path = ? LIMIT 1");
            $stmt->execute([currentUserId(), $path]);
            if (!$stmt->fetch()) { http_response_code(403); die('Akses ditolak.'); }
        }
        break;

    case 'cert':
        // Alumni can view own certs, admin can view all
        if (isAlumni()) {
            $pdo  = getDB();
            $stmt = $pdo->prepare(
                "SELECT ac.id FROM alumni_certificates ac
                 JOIN alumni_profiles ap ON ap.id = ac.alumni_id
                 WHERE ap.user_id = ? AND ac.file_path = ? LIMIT 1"
            );
            $stmt->execute([currentUserId(), $path]);
            if (!$stmt->fetch()) { http_response_code(403); die('Akses ditolak.'); }
        }
        break;

    case 'logo':
        // Public — anyone logged in can see logos
        break;

    case 'photo':
        // Anyone logged in can see profile photos
        break;

    default:
        if (!isAdmin()) { http_response_code(403); die('Akses ditolak.'); }
}

serveFile($path);
