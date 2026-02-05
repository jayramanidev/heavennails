-- Heaven Nails Database Schema Update
-- Run after initial setup.sql

USE heaven_nails;

-- Staff/Artists table
CREATE TABLE IF NOT EXISTS staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    specialty VARCHAR(255),
    avatar_emoji VARCHAR(10) DEFAULT '💅',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default staff members (using REPLACE for idempotency with UNIQUE constraint)
INSERT INTO staff (name, specialty, avatar_emoji) VALUES 
('Priya', 'Gel Extensions & Nail Art', '✨'),
('Neha', 'Classic Manicure & Pedicure', '💅'),
('Ananya', 'Acrylic Nails & 3D Art', '💎')
ON DUPLICATE KEY UPDATE specialty = VALUES(specialty), avatar_emoji = VALUES(avatar_emoji);

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

-- Add staff_id column to appointments if not exists (MySQL 8.0+ syntax)
-- Using stored procedure for compatibility
DELIMITER //
CREATE PROCEDURE add_appointment_columns()
BEGIN
    -- Add staff_id column if not exists
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'appointments' 
        AND COLUMN_NAME = 'staff_id'
    ) THEN
        ALTER TABLE appointments ADD COLUMN staff_id INT DEFAULT NULL;
    END IF;
    
    -- Add duration_minutes column if not exists
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'appointments' 
        AND COLUMN_NAME = 'duration_minutes'
    ) THEN
        ALTER TABLE appointments ADD COLUMN duration_minutes INT DEFAULT 60;
    END IF;
    
    -- Add foreign key constraint if not exists
    IF NOT EXISTS (
        SELECT * FROM information_schema.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'appointments' 
        AND CONSTRAINT_NAME = 'fk_appointment_staff'
    ) THEN
        ALTER TABLE appointments 
        ADD CONSTRAINT fk_appointment_staff 
        FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL;
    END IF;
    
    -- Add composite index if not exists
    IF NOT EXISTS (
        SELECT * FROM information_schema.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'appointments' 
        AND INDEX_NAME = 'idx_date_time_status'
    ) THEN
        ALTER TABLE appointments ADD INDEX idx_date_time_status (preferred_date, preferred_time, status);
    END IF;
END //
DELIMITER ;

-- Execute the procedure
CALL add_appointment_columns();

-- Clean up
DROP PROCEDURE IF EXISTS add_appointment_columns;
