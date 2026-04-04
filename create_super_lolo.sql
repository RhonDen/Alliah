-- Restore DB + Super Lolo Admin (run in phpMyAdmin SQL tab)
-- Ctrl+A, Ctrl+C this file → phpMyAdmin → SQL → Ctrl+V → Go

DROP TABLE IF EXISTS appointments, services, users;
SOURCE setup.sql;

INSERT INTO users (role, first_name, last_name, email, mobile, age, password) VALUES 
('admin', 'Super', 'Lolo', 'super@lolo.com', '09123456789', 30, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE first_name='Super';

-- Login: phone 09123456789, password 'password'
