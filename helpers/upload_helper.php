<?php
// helpers/upload_helper.php

function ensureUploadDir(string $subDir = ''): string {
    $dir = UPLOAD_DIR . ($subDir ? rtrim($subDir, '/') . '/' : '');
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    return $dir;
}

/**
 * Handle file upload with MIME and size validation.
 * Returns the stored relative path or throws RuntimeException.
 */
function handleUpload(
    array $file,
    string $subDir,
    array $allowedMimes,
    int $maxSize,
    ?string $oldPath = null
): string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(uploadErrorMessage($file['error']));
    }

    if ($file['size'] > $maxSize) {
        throw new RuntimeException('Ukuran file melebihi batas maksimal (' . formatFileSize($maxSize) . ').');
    }

    // MIME validation using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('Tipe file tidak diizinkan. Diizinkan: ' . implode(', ', $allowedMimes));
    }

    $dir      = ensureUploadDir($subDir);
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('file_', true) . '.' . strtolower($ext);
    $destPath = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Gagal menyimpan file. Coba lagi.');
    }

    // Delete old file if exists
    if ($oldPath) {
        $oldFullPath = UPLOAD_DIR . ltrim($oldPath, '/');
        if (is_file($oldFullPath)) {
            unlink($oldFullPath);
        }
    }

    return $subDir . '/' . $filename;
}

function uploadErrorMessage(int $code): string {
    return match($code) {
        UPLOAD_ERR_INI_SIZE   => 'File melebihi batas upload server.',
        UPLOAD_ERR_FORM_SIZE  => 'File melebihi batas ukuran form.',
        UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian.',
        UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang dipilih.',
        UPLOAD_ERR_NO_TMP_DIR => 'Direktori temp tidak ditemukan.',
        UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
        default               => 'Error upload tidak diketahui.',
    };
}

function formatFileSize(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function serveFile(string $relativePath): void {
    $fullPath = UPLOAD_DIR . ltrim($relativePath, '/');
    if (!is_file($fullPath)) {
        http_response_code(404);
        die('File tidak ditemukan.');
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime     = $finfo->file($fullPath);
    $filename = basename($fullPath);

    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: private, max-age=3600');
    readfile($fullPath);
    exit;
}
