-- ============================================
-- CODECANVAS AI BUILDER MASTER DATABASE
-- Full Clean Reset & Remapping
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing database and recreate fresh
DROP DATABASE IF EXISTS `codecanvas`;
CREATE DATABASE `codecanvas` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `codecanvas`;

-- ============================================
-- TABLE 1: USERS
-- ============================================
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `google_id` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) DEFAULT NULL,
    `name` VARCHAR(100) NOT NULL,
    `profile_pic` VARCHAR(255) DEFAULT NULL,
    `role` ENUM('user', 'admin') DEFAULT 'user',
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`),
    INDEX `idx_google_id` (`google_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 2: TEMPLATES (AI-Driven)
-- ============================================
CREATE TABLE `templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `thumbnail_url` VARCHAR(255) DEFAULT NULL,
    `folder_path` VARCHAR(255) NOT NULL,
    `is_ai_enabled` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 3: PROJECTS (AI Portfolio Instances)
-- ============================================
CREATE TABLE `projects` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `template_id` INT UNSIGNED DEFAULT NULL,
    `project_name` VARCHAR(255) NOT NULL,
    `html_path` VARCHAR(255) DEFAULT NULL,
    `publish_status` ENUM('draft', 'publishing', 'deployed', 'failed') DEFAULT 'draft',
    `status` ENUM('active', 'archived') DEFAULT 'active',
    `custom_slug` VARCHAR(100) DEFAULT NULL UNIQUE,
    `deployment_url` VARCHAR(255) DEFAULT NULL,
    `blueprint_json` LONGTEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_publish_status` (`publish_status`),
    INDEX `idx_custom_slug` (`custom_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- INITIAL DATA SETUP
-- ============================================

-- Seed Master Admin (Password: admin123)
INSERT INTO `users` (`email`, `password_hash`, `name`, `role`, `status`) VALUES 
('admin@codecanvas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin User', 'admin', 'active');

-- Seed Demo User (Password: user123)
INSERT INTO `users` (`email`, `password_hash`, `name`, `role`, `status`) VALUES 
('user@codecanvas.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo User', 'user', 'active');

-- Seed AI Master Template
INSERT INTO `templates` (`name`, `slug`, `folder_path`) VALUES 
('AI Master Template', 'ai-master', 'templates/ai-master/');

SET FOREIGN_KEY_CHECKS = 1;
