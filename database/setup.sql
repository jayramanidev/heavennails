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

-- Services table
CREATE TABLE IF NOT EXISTS services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_minutes INT NOT NULL DEFAULT 60,
    icon_class VARCHAR(50) DEFAULT 'fa-solid fa-sparkles',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default services
INSERT INTO services (name, description, price, duration_minutes, icon_class) VALUES
('Classic Manicure', 'Luxurious hand treatment with nail shaping, cuticle care, and polish perfection.', 500.00, 45, 'fa-solid fa-hand-sparkles'),
('Gel Extensions', 'Durable, glossy extensions that last weeks with flawless finish.', 1500.00, 90, 'fa-solid fa-wand-magic-sparkles'),
('Nail Art', 'Express yourself with custom designs, from minimal to intricate masterpieces.', 800.00, 60, 'fa-solid fa-palette'),
('Spa Pedicure', 'Complete foot rejuvenation with massage, scrub, and premium polish.', 700.00, 60, 'fa-solid fa-spa'),
('Acrylic Nails', 'Strong, sculpted nails with endless customization options.', 1200.00, 90, 'fa-regular fa-gem'),
('Nail Repair', 'Gentle restoration for damaged nails, bringing back natural strength.', 400.00, 30, 'fa-solid fa-heart-pulse')
ON DUPLICATE KEY UPDATE name = name;

