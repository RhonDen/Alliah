-- Create table for tooth selection data per appointment
CREATE TABLE IF NOT EXISTS appointment_teeth (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    tooth_number VARCHAR(3) NOT NULL,
    tooth_type ENUM('permanent', 'primary') NOT NULL DEFAULT 'permanent',
    procedure_type ENUM('extraction', 'filling', 'root_canal', 'crown', 'implant', 'other') NOT NULL DEFAULT 'extraction',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tooth_per_appointment (appointment_id, tooth_number, tooth_type)
);

-- Add requires_teeth to services so the admin UI can show the tooth chart button only when needed
ALTER TABLE services
    ADD COLUMN requires_teeth TINYINT(1) NOT NULL DEFAULT 0;

-- Mark services that require tooth selection
UPDATE services SET requires_teeth = 1 WHERE name IN ('Extraction', 'Filling', 'Root Canal', 'Crown', 'Implant', 'Tooth Extraction');

-- Mark services that do not require tooth selection
UPDATE services SET requires_teeth = 0 WHERE name IN ('Dental Checkup', 'Teeth Cleaning', 'Teeth Whitening', 'Consultation', 'Orthodontics', 'Pediatric Dentistry', 'Periodontal Treatment');
