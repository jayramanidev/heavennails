-- Heaven Nails Main Database Script
-- Use this file to import your database structure and data into InfinityFree (phpMyAdmin)

-- 1. Create Database (Skip this if you are using InfinityFree's default database)
-- CREATE DATABASE IF NOT EXISTS heaven_nails CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE heaven_nails;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- 2. Tables Setup

-- Admins table
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin (password: heaven2026)
INSERT INTO `admins` (`username`, `password_hash`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Staff table
CREATE TABLE IF NOT EXISTS `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialty` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_emoji` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '💅',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default staff
INSERT INTO `staff` (`name`, `specialty`, `avatar_emoji`) VALUES
('Priya', 'Gel Extensions & Nail Art', '✨'),
('Neha', 'Classic Manicure & Pedicure', '💅'),
('Ananya', 'Acrylic Nails & 3D Art', '💎');

-- Services table
CREATE TABLE IF NOT EXISTS `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 60,
  `icon_class` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'fa-solid fa-sparkles',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default services
INSERT INTO `services` (`name`, `description`, `price`, `duration_minutes`, `icon_class`) VALUES
('Classic Manicure', 'Luxurious hand treatment with nail shaping, cuticle care, and polish perfection.', 500.00, 45, 'fa-solid fa-hand-sparkles'),
('Gel Extensions', 'Durable, glossy extensions that last weeks with flawless finish.', 1500.00, 90, 'fa-solid fa-wand-magic-sparkles'),
('Nail Art', 'Express yourself with custom designs, from minimal to intricate masterpieces.', 800.00, 60, 'fa-solid fa-palette'),
('Spa Pedicure', 'Complete foot rejuvenation with massage, scrub, and premium polish.', 700.00, 60, 'fa-solid fa-spa'),
('Acrylic Nails', 'Strong, sculpted nails with endless customization options.', 1200.00, 90, 'fa-regular fa-gem'),
('Nail Repair', 'Gentle restoration for damaged nails, bringing back natural strength.', 400.00, 30, 'fa-solid fa-heart-pulse');

-- Blocked Dates table
CREATE TABLE IF NOT EXISTS `blocked_dates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `block_date` date NOT NULL,
  `block_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_block_date` (`block_date`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `blocked_dates_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appointments table
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `services` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` time NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `staff_id` int(11) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 60,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_date` (`preferred_date`),
  KEY `idx_created` (`created_at`),
  KEY `fk_appointment_staff` (`staff_id`),
  KEY `idx_date_time_status` (`preferred_date`,`preferred_time`,`status`),
  CONSTRAINT `fk_appointment_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
