-- Migration: 002 — Tabel alumni_profiles
-- Tim: Database

USE lordik_db;

CREATE TABLE IF NOT EXISTS alumni_profiles (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    nis             VARCHAR(20)  NOT NULL UNIQUE,
    nisn            VARCHAR(20)  NOT NULL UNIQUE,
    full_name       VARCHAR(255) NOT NULL,
    gender          ENUM('male','female') NOT NULL DEFAULT 'male',
    birth_date      DATE NULL,
    phone           VARCHAR(20) NULL,
    address         TEXT NULL,
    jurusan         VARCHAR(100) NOT NULL,
    graduation_year YEAR NOT NULL,
    photo_path      VARCHAR(500) NULL,
    cv_path         VARCHAR(500) NULL,
    bio             TEXT NULL,
    skills          TEXT NULL,
    work_status     ENUM('employed','entrepreneur','studying','unemployed') NOT NULL DEFAULT 'unemployed',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_work_status (work_status),
    INDEX idx_jurusan (jurusan),
    INDEX idx_graduation_year (graduation_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
