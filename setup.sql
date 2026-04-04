CREATE DATABASE IF NOT EXISTS alliah;
USE alliah;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('client', 'admin') DEFAULT 'client',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    mobile VARCHAR(20) NOT NULL UNIQUE,
    age INT NOT NULL,
    password VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Services table
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    duration INT DEFAULT 30,
    price DECIMAL(10,2) DEFAULT 0.00,
    UNIQUE KEY unique_service_name (name)
);

-- Appointments table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    admin_message TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    UNIQUE KEY unique_booking (user_id, appointment_date, appointment_time),
    KEY idx_appointments_schedule (appointment_date, appointment_time, status),
    KEY idx_appointments_user_status (user_id, status)
);

-- Insert default services (ignore if exists)
INSERT IGNORE INTO services (name) VALUES
('Dental Checkup'),
('Teeth Cleaning'),
('Fillings'),
('Root Canal'),
('Tooth Extraction'),
('Teeth Whitening'),
('Orthodontics'),
('Dental Implants'),
('Pediatric Dentistry'),
('Periodontal Treatment'),
('Consultation');

-- Insert default admin (ignore if exists)
INSERT IGNORE INTO users (role, first_name, last_name, email, mobile, age, password) VALUES
('admin', 'Admin', 'User', 'admin@alliah.com', '1234567890', 30, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
