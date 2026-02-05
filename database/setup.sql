-- Heaven Nails Database Setup
-- Run this script in your MySQL database (phpMyAdmin or CLI)

-- Create database (if not exists)
CREATE DATABASE IF NOT EXISTS heaven_nails CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE heaven_nails;

-- Appointments table
CREATE TABLE IF NOT EXISTS appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    services TEXT NOT NULL,
    preferred_date DATE NOT NULL,
    preferred_time TIME NOT NULL,
    notes TEXT,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_date (preferred_date),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admins table (optional - for database-driven auth)
CREATE TABLE IF NOT EXISTS admins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin (password: heaven2026)
-- In production, generate a new password hash using password_hash('yourpassword', PASSWORD_DEFAULT)
INSERT INTO admins (username, password_hash) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE username = username;

-- Sample booking for testing (optional - remove in production)
-- INSERT INTO appointments (client_name, email, phone, services, preferred_date, preferred_time, notes) VALUES
-- ('Test Client', 'test@example.com', '+91 98765 43210', '["Classic Manicure", "Spa Pedicure"]', CURDATE() + INTERVAL 3 DAY, '14:00', 'This is a test booking');
