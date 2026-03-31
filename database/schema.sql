-- ============================================================
-- LORDIK (Loker Cerdik) - Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS lordik_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE lordik_db;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(100) NOT NULL UNIQUE,
    email         VARCHAR(191) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    role          ENUM('admin','alumni','company') NOT NULL DEFAULT 'alumni',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    force_change_pwd TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=must change password on next login',
    last_login    DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME NULL,
    INDEX idx_role (role),
    INDEX idx_is_active (is_active),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: alumni_profiles
-- ============================================================
CREATE TABLE alumni_profiles (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL UNIQUE,
    nis           VARCHAR(20) NOT NULL UNIQUE COMMENT 'Nomor Induk Siswa',
    nisn          VARCHAR(20) NOT NULL UNIQUE COMMENT 'Nomor Induk Siswa Nasional',
    full_name     VARCHAR(200) NOT NULL,
    gender        ENUM('male','female') NOT NULL,
    birth_date    DATE NULL,
    phone         VARCHAR(20) NULL,
    address       TEXT NULL,
    jurusan       VARCHAR(100) NOT NULL COMMENT 'Program keahlian/jurusan',
    graduation_year YEAR NOT NULL,
    work_status   ENUM('unemployed','employed','entrepreneur','continuing_edu') NOT NULL DEFAULT 'unemployed',
    cv_path       VARCHAR(500) NULL COMMENT 'Path ke file CV (PDF)',
    photo_path    VARCHAR(500) NULL,
    skills        TEXT NULL COMMENT 'Daftar skill, comma-separated',
    bio           TEXT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_jurusan (jurusan),
    INDEX idx_work_status (work_status),
    INDEX idx_graduation_year (graduation_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: alumni_certificates
-- ============================================================
CREATE TABLE alumni_certificates (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alumni_id     INT UNSIGNED NOT NULL,
    cert_name     VARCHAR(200) NOT NULL,
    issuer        VARCHAR(200) NULL,
    issued_date   DATE NULL,
    file_path     VARCHAR(500) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alumni_id) REFERENCES alumni_profiles(id) ON DELETE CASCADE,
    INDEX idx_alumni_id (alumni_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: companies
-- ============================================================
CREATE TABLE companies (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL UNIQUE,
    company_name  VARCHAR(200) NOT NULL,
    industry      VARCHAR(100) NULL,
    address       TEXT NULL,
    city          VARCHAR(100) NULL,
    phone         VARCHAR(20) NULL,
    website       VARCHAR(255) NULL,
    description   TEXT NULL,
    logo_path     VARCHAR(500) NULL,
    reset_pin     VARCHAR(255) NULL COMMENT 'Bcrypt hashed PIN for password reset',
    verified      TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_verified (verified),
    INDEX idx_city (city)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: job_vacancies
-- ============================================================
CREATE TABLE job_vacancies (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id    INT UNSIGNED NOT NULL,
    title         VARCHAR(200) NOT NULL,
    description   TEXT NOT NULL,
    requirements  TEXT NULL,
    location      VARCHAR(200) NULL,
    salary_min    DECIMAL(15,2) NULL,
    salary_max    DECIMAL(15,2) NULL,
    job_type      ENUM('full_time','part_time','contract','internship') NOT NULL DEFAULT 'full_time',
    jurusan_required VARCHAR(100) NULL COMMENT 'Jurusan yang diprioritaskan, NULL = semua',
    slots         INT UNSIGNED NOT NULL DEFAULT 1,
    deadline      DATE NULL,
    status        ENUM('submitted','approved','rejected','closed') NOT NULL DEFAULT 'submitted',
    admin_note    TEXT NULL COMMENT 'Catatan dari admin saat approve/reject',
    approved_by   INT UNSIGNED NULL,
    approved_at   DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_company_id (company_id),
    INDEX idx_deadline (deadline),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: applications
-- ============================================================
CREATE TABLE applications (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vacancy_id    INT UNSIGNED NOT NULL,
    alumni_id     INT UNSIGNED NOT NULL,
    cover_letter  TEXT NULL,
    status        ENUM('pending','frozen','reviewed','shortlisted','rejected','accepted') NOT NULL DEFAULT 'pending',
    applied_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by    INT UNSIGNED NULL COMMENT 'Who last updated the status',
    notes         TEXT NULL COMMENT 'Internal notes from company/admin',
    UNIQUE KEY uq_vacancy_alumni (vacancy_id, alumni_id),
    FOREIGN KEY (vacancy_id) REFERENCES job_vacancies(id) ON DELETE CASCADE,
    FOREIGN KEY (alumni_id)  REFERENCES alumni_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_vacancy_id (vacancy_id),
    INDEX idx_alumni_id (alumni_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: application_status_logs
-- ============================================================
CREATE TABLE application_status_logs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    old_status    VARCHAR(50) NULL,
    new_status    VARCHAR(50) NOT NULL,
    changed_by    INT UNSIGNED NULL,
    notes         TEXT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by)     REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_application_id (application_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: messages (Chat internal per application)
-- ============================================================
CREATE TABLE messages (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    sender_id     INT UNSIGNED NOT NULL,
    message       TEXT NOT NULL,
    is_read       TINYINT(1) NOT NULL DEFAULT 0,
    sent_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)      REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_application_id (application_id),
    INDEX idx_sender_id (sender_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: notifications
-- ============================================================
CREATE TABLE notifications (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    title         VARCHAR(200) NOT NULL,
    body          TEXT NOT NULL,
    type          ENUM('info','success','warning','error') NOT NULL DEFAULT 'info',
    link          VARCHAR(500) NULL,
    is_read       TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: activity_logs
-- ============================================================
CREATE TABLE activity_logs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NULL COMMENT 'NULL = system action',
    action        VARCHAR(100) NOT NULL,
    module        VARCHAR(50) NOT NULL,
    description   TEXT NULL,
    ip_address    VARCHAR(45) NULL,
    user_agent    TEXT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_module (module),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: password_reset_logs
-- ============================================================
CREATE TABLE password_reset_logs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    reset_method  ENUM('nis_nisn','reset_pin','admin') NOT NULL,
    ip_address    VARCHAR(45) NULL,
    success       TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Default Admin Account (password: Admin@1234)
-- ============================================================
INSERT INTO users (username, email, password, role, is_active, force_change_pwd)
VALUES (
    'admin',
    'admin@lordik.sch.id',
    '$2y$12$LmH9VX5fGV3Vz9Nl3KqW3.yP4x1a2b3c4d5e6f7g8h9i0j1k2l3m4',
    'admin',
    1,
    0
);
-- NOTE: Run the following PHP to generate correct hash:
-- echo password_hash('Admin@1234', PASSWORD_BCRYPT, ['cost' => 12]);
-- Then update the INSERT above.
