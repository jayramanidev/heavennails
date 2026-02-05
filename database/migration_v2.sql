-- Heaven Nails Database Schema Update
-- Run after initial setup.sql

USE heaven_nails;

-- Staff/Artists table
CREATE TABLE IF NOT EXISTS staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    specialty VARCHAR(255),
    avatar_emoji VARCHAR(10) DEFAULT '💅',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default staff members
INSERT INTO staff (name, specialty, avatar_emoji) VALUES 
('Priya', 'Gel Extensions & Nail Art', '✨'),
('Neha', 'Classic Manicure & Pedicure', '💅'),
('Ananya', 'Acrylic Nails & 3D Art', '💎')
ON DUPLICATE KEY UPDATE name = name;

-- Blocked dates/times table (holidays, sick days)
CREATE TABLE IF NOT EXISTS blocked_dates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    block_date DATE NOT NULL,
    block_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    reason VARCHAR(255),
    staff_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
    INDEX idx_block_date (block_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add staff_id column to appointments if not exists
ALTER TABLE appointments 
ADD COLUMN IF NOT EXISTS staff_id INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS duration_minutes INT DEFAULT 60,
ADD CONSTRAINT fk_appointment_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL;

-- Add index for availability checking
ALTER TABLE appointments ADD INDEX IF NOT EXISTS idx_date_time_status (preferred_date, preferred_time, status);
