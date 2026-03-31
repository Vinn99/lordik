<?php
// config/database.php
// ✅ Konfigurasi default Laragon (MySQL root tanpa password)

define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'lordik_db');
define('DB_USER',    'root');
define('DB_PASS',    '');           // Laragon default: kosong
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("DB Connection failed: " . $e->getMessage());
            $msg = $e->getMessage();
            if (str_contains($msg, 'Access denied')) {
                die('<div style="font-family:sans-serif;padding:2rem;background:#fee2e2;border-radius:8px;border-left:4px solid #ef4444;margin:2rem;"><b>❌ Database Error:</b> Username/password salah.<br>Edit <code>config/database.php</code> → ubah DB_USER dan DB_PASS</div>');
            }
            if (str_contains($msg, 'Unknown database')) {
                die('<div style="font-family:sans-serif;padding:2rem;background:#fef9c3;border-radius:8px;border-left:4px solid #eab308;margin:2rem;"><b>⚠️ Database Belum Dibuat!</b><br><br>Langkah:<ol style="margin-top:.5rem;padding-left:1.5rem"><li>Buka <a href="http://localhost/phpmyadmin">phpMyAdmin</a></li><li>Klik <b>New</b> → nama database: <b>lordik_db</b></li><li>Pilih collation: <b>utf8mb4_unicode_ci</b> → Create</li><li>Klik tab <b>Import</b> → pilih file <b>config/schema.sql</b> → Go</li></ol></div>');
            }
            if (str_contains($msg, "Can't connect") || str_contains($msg, 'refused') || str_contains($msg, "php_network")) {
                die('<div style="font-family:sans-serif;padding:2rem;background:#fee2e2;border-radius:8px;border-left:4px solid #ef4444;margin:2rem;"><b>❌ MySQL Tidak Berjalan!</b><br>Buka Laragon → klik tombol <b>Start All</b> (pastikan MySQL menyala)</div>');
            }
            die('<div style="font-family:sans-serif;padding:2rem;background:#fee2e2;border-radius:8px;border-left:4px solid #ef4444;margin:2rem;"><b>Database Error:</b> ' . htmlspecialchars($msg) . '</div>');
        }
    }
    return $pdo;
}
