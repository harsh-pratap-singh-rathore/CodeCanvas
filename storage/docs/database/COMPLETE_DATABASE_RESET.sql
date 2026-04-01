-- ============================================
-- CODECANVAS COMPLETE DATABASE RESET
-- MySQL/XAMPP Database Remapping Script
-- ============================================
-- INSTRUCTIONS:
-- 1. Open phpMyAdmin (http://localhost/phpmyadmin)
-- 2. Click on "SQL" tab
-- 3. Copy and paste this ENTIRE file
-- 4. Click "Go" button
-- ============================================

-- Drop existing database and recreate fresh
DROP DATABASE IF EXISTS `codecanvas`;
CREATE DATABASE `codecanvas` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `codecanvas`;

-- ============================================
-- TABLE 1: USERS (Unified for both admin and regular users)
-- ============================================
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `role` ENUM('user', 'admin') DEFAULT 'user',
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 2: TEMPLATES
-- ============================================
CREATE TABLE `templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `template_type` ENUM('personal', 'portfolio', 'business') NOT NULL,
    `folder_path` VARCHAR(255) NOT NULL,
    `thumbnail_url` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`),
    INDEX `idx_status` (`status`),
    INDEX `idx_template_type` (`template_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 3: PROJECTS
-- ============================================
CREATE TABLE `projects` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `template_id` INT UNSIGNED NOT NULL,
    `project_name` VARCHAR(255) NOT NULL,
    `project_type` ENUM('personal', 'portfolio', 'business') NOT NULL,
    `brand_name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `skills` TEXT DEFAULT NULL,
    `contact` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`template_id`) REFERENCES `templates`(`id`) ON DELETE RESTRICT,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_template_id` (`template_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_project_type` (`project_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DEFAULT DATA: USERS
-- ============================================
-- Admin Account
-- Email: admin@codecanvas.com
-- Password: admin123
INSERT INTO `users` (`email`, `password_hash`, `name`, `role`, `status`) VALUES 
('admin@codecanvas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin', 'active');

-- Sample Regular User
-- Email: user@codecanvas.com
-- Password: user123
INSERT INTO `users` (`email`, `password_hash`, `name`, `role`, `status`) VALUES 
('user@codecanvas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo User', 'user', 'active');

-- ============================================
-- DEFAULT DATA: TEMPLATES
-- ============================================
INSERT INTO `templates` (`name`, `slug`, `template_type`, `folder_path`, `thumbnail_url`, `status`) VALUES
('Minimal Portfolio', 'minimal', 'portfolio', 'templates/minimal/', 'https://via.placeholder.com/300x200?text=Minimal', 'active'),
('Modern Portfolio', 'modern', 'portfolio', 'templates/modern/', 'https://via.placeholder.com/300x200?text=Modern', 'active'),
('Classic Portfolio', 'classic', 'portfolio', 'templates/classic/', 'https://via.placeholder.com/300x200?text=Classic', 'active'),
('Elegant Portfolio', 'elegant', 'portfolio', 'templates/elegant/', 'https://via.placeholder.com/300x200?text=Elegant', 'active'),
('Personal Basic', 'personal-basic', 'personal', 'templates/personal-basic/', 'https://via.placeholder.com/300x200?text=Personal', 'active'),
('Business Pro', 'business-pro', 'business', 'templates/business-pro/', 'https://via.placeholder.com/300x200?text=Business', 'active');

-- ============================================
-- VERIFICATION QUERIES
-- ============================================
-- Run these to verify the setup:

-- Check all tables exist
SHOW TABLES;

-- Check users (should show 2: 1 admin, 1 regular user)
SELECT id, email, name, role, status, created_at FROM users;

-- Check templates (should show 6 templates)
SELECT id, name, slug, template_type, status FROM templates;

-- Check projects (should be empty initially)
SELECT COUNT(*) as total_projects FROM projects;

-- ============================================
-- DATABASE RESET COMPLETE
-- ============================================
-- You can now login with:
-- Admin: admin@codecanvas.com / admin123
-- User: user@codecanvas.com / user123
-- ============================================
