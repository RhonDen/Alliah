-- SMS OTP booking migration
-- Run this once against an existing database before enabling the new flow.

ALTER TABLE users MODIFY email VARCHAR(255) NULL;
ALTER TABLE users MODIFY password VARCHAR(255) NULL;

ALTER TABLE users
    ADD COLUMN otp_code VARCHAR(10) NULL,
    ADD COLUMN otp_expires_at DATETIME NULL;

UPDATE users SET email = NULL WHERE TRIM(email) = '';

UPDATE users
SET mobile = CASE
    WHEN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mobile, '-', ''), ' ', ''), '(', ''), ')', ''), '+', '') REGEXP '^09[0-9]{9}$'
        THEN CONCAT('63', SUBSTRING(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mobile, '-', ''), ' ', ''), '(', ''), ')', ''), '+', ''), 2))
    WHEN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mobile, '-', ''), ' ', ''), '(', ''), ')', ''), '+', '') REGEXP '^639[0-9]{9}$'
        THEN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mobile, '-', ''), ' ', ''), '(', ''), ')', ''), '+', '')
    ELSE mobile
END;

ALTER TABLE users
    ADD UNIQUE INDEX unique_mobile (mobile);

ALTER TABLE appointments
    MODIFY status ENUM('pending', 'approved', 'rejected', 'completed', 'no_show') DEFAULT 'pending';

ALTER TABLE appointments
    ADD INDEX idx_appointments_schedule (appointment_date, appointment_time, status),
    ADD INDEX idx_appointments_user_status (user_id, status);
