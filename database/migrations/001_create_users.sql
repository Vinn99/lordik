-- Migration: 001 — Tabel users
-- Tim: Database
-- Dibuat: 2024

USE lordik_db;

CREATE TABLE IF NOT EXISTS users (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username         VARCHAR(100) NOT NULL UNIQUE,
    email            VARCHAR(191) NOT NULL UNIQUE,
    password         VARCHAR(255) NOT NULL,
    role             ENUM('admin','alumni','company') NOT NULL DEFAULT 'alumni',
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    force_change_pwd TINYINT(1) NOT NULL DEFAULT 1,
    last_login       DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       DATETIME NULL,
    INDEX idx_role (role),
    INDEX idx_is_active (is_active),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
