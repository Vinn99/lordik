-- Migration 001: Tambah status 'frozen' pada tabel applications
-- Tim: Database
-- Jalankan sekali jika upgrade dari v6 ke v7

ALTER TABLE applications 
  MODIFY COLUMN status 
    ENUM('pending','frozen','reviewed','shortlisted','rejected','accepted') 
    NOT NULL DEFAULT 'pending';

-- Verifikasi
-- SELECT COLUMN_TYPE FROM information_schema.COLUMNS 
-- WHERE TABLE_NAME='applications' AND COLUMN_NAME='status';
