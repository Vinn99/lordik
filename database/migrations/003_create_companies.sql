-- Migration: 003 — Tabel companies, job_vacancies, applications
-- Tim: Database

USE lordik_db;

CREATE TABLE IF NOT EXISTS companies (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL UNIQUE,
    company_name VARCHAR(255) NOT NULL,
    industry     VARCHAR(100) NULL,
    description  TEXT NULL,
    city         VARCHAR(100) NULL,
    address      TEXT NULL,
    phone        VARCHAR(20)  NULL,
    website      VARCHAR(255) NULL,
    logo_path    VARCHAR(500) NULL,
    is_verified  TINYINT(1)   NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_vacancies (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id       INT UNSIGNED NOT NULL,
    title            VARCHAR(255) NOT NULL,
    description      TEXT NOT NULL,
    requirements     TEXT NULL,
    job_type         ENUM('full_time','part_time','contract','internship') NOT NULL DEFAULT 'full_time',
    location         VARCHAR(255) NULL,
    city             VARCHAR(100) NULL,
    salary_min       DECIMAL(12,2) NULL,
    salary_max       DECIMAL(12,2) NULL,
    slots            INT UNSIGNED NOT NULL DEFAULT 1,
    jurusan_required VARCHAR(100) NULL,
    deadline         DATE NULL,
    status           ENUM('pending','approved','rejected','closed') NOT NULL DEFAULT 'pending',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at       DATETIME NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS applications (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vacancy_id   INT UNSIGNED NOT NULL,
    alumni_id    INT UNSIGNED NOT NULL,
    cover_letter TEXT NULL,
    status       ENUM('pending','reviewed','shortlisted','accepted','rejected') NOT NULL DEFAULT 'pending',
    applied_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_app (vacancy_id, alumni_id),
    FOREIGN KEY (vacancy_id) REFERENCES job_vacancies(id) ON DELETE CASCADE,
    FOREIGN KEY (alumni_id)  REFERENCES alumni_profiles(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_alumni (alumni_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
