<?php
// config/app.php
// ✅ Auto-detect URL — works for localhost/lordik dan lordik.test

function detectAppUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';

    // Jika akses via lordik.test atau subdomain → root
    if (preg_match('/^lordik\./i', $host)) {
        return $scheme . '://' . $host;
    }

    // Jika akses via localhost/lordik → extract subfolder
    if (preg_match('#^(/[^/]+/lordik)#', $script, $m)) {
        return $scheme . '://' . $host . $m[1];
    }
    if (preg_match('#^(/lordik)#', $script, $m)) {
        return $scheme . '://' . $host . $m[1];
    }

    return $scheme . '://' . $host . '/lordik';
}

define('APP_NAME',    'LORDIK - Loker Cerdik');
define('APP_URL',     detectAppUrl());
define('APP_VERSION', '1.0.0');

// Session
define('SESSION_TIMEOUT', 1800);      // 30 menit
define('SESSION_NAME',    'LORDIK_SESSION');

// Upload paths (absolut, di luar public/)
define('UPLOAD_DIR',  dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('EXPORT_DIR',  dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'exports' . DIRECTORY_SEPARATOR);

// Batas ukuran file
define('MAX_CV_SIZE',   5 * 1024 * 1024);   // 5 MB
define('MAX_CERT_SIZE', 2 * 1024 * 1024);   // 2 MB
define('MAX_LOGO_SIZE', 1 * 1024 * 1024);   // 1 MB

// MIME types yang diizinkan
define('ALLOWED_CV_MIME',   ['application/pdf']);
define('ALLOWED_CERT_MIME', ['application/pdf', 'image/jpeg', 'image/png']);
define('ALLOWED_LOGO_MIME', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Roles
define('ROLE_ADMIN',   'admin');
define('ROLE_ALUMNI',  'alumni');
define('ROLE_COMPANY', 'company');

// Pagination
define('PER_PAGE', 15);
