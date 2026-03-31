-- Seed: Data demo untuk pengujian
-- Tim: Database
-- JANGAN dijalankan di production!

USE lordik_db;

-- Admin default (password: Admin@1234)
INSERT IGNORE INTO users (username, email, password, role, force_change_pwd) VALUES
('admin', 'admin@lordik.local',
 '$2a$12$md1XeR3dizyRG0q119ex5uwhsD3qRwTEbu4c2LexemnohIYezq1Y6',
 'admin', 0);

-- username: admin
-- password: Admin123
