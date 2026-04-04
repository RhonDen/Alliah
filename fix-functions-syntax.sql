UPDATE users SET password = NULL WHERE role = 'client' AND password IS NOT NULL;
ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL;
ALTER TABLE users ADD UNIQUE KEY unique_mobile (mobile);

