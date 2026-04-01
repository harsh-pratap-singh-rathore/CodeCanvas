USE `codecanvas`;

-- ============================================
-- TABLE 1: USERS
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
    `preview_image_path` VARCHAR(255) DEFAULT NULL,
    `preview_fallback_path` VARCHAR(255) DEFAULT NULL,
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
    `project_type` ENUM('personal', 'portfolio', 'business') DEFAULT 'portfolio',
    `custom_slug` VARCHAR(100) DEFAULT NULL,
    `brand_name` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `skills` TEXT DEFAULT NULL,
    `contact` VARCHAR(255) DEFAULT NULL,
    -- 'status' for logical state (active/archived/draft)
    `status` ENUM('draft', 'published', 'archived', 'active') DEFAULT 'draft',
    -- 'publish_status' for deployment state
    `publish_status` ENUM('draft', 'building', 'deployed', 'failed', 'publishing', 'published') DEFAULT 'draft',
    `build_log` TEXT DEFAULT NULL,
    `live_url` VARCHAR(255) DEFAULT NULL,
    `netlify_site_id` VARCHAR(100) DEFAULT NULL,
    `content_json` LONGTEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`template_id`) REFERENCES `templates`(`id`) ON DELETE RESTRICT,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_template_id` (`template_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_custom_slug` (`custom_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 4: MESSAGES (From Contact Forms)
-- ============================================
CREATE TABLE `messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT UNSIGNED NOT NULL,
    `visitor_name` VARCHAR(100) NOT NULL,
    `visitor_email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `message` TEXT NOT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE 5: NOTIFICATIONS
-- ============================================
CREATE TABLE `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `type` ENUM('message', 'system', 'project_status') DEFAULT 'system',
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT DEFAULT NULL,
    `link` VARCHAR(255) DEFAULT NULL,
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_read` (`is_read`),
    INDEX `idx_type` (`type`)
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
-- DEFAULT DATA: TEMPLATES (Comprehensive List)
-- ============================================
INSERT INTO `templates` (`name`, `slug`, `template_type`, `folder_path`, `thumbnail_url`, `status`) VALUES
('Developer Portfolio', 'developer-portfolio', 'portfolio', 'templates/developer/', 'https://via.placeholder.com/300x200?text=Developer', 'active'),
('Business Portfolio', 'business-portfolio', 'business', 'templates/business/', 'https://via.placeholder.com/300x200?text=Business', 'active'),
('Shop Template', 'shop-template', 'business', 'templates/shop/', 'https://via.placeholder.com/300x200?text=Shop', 'active'),
('Normal Portfolio', 'normal-portfolio', 'personal', 'templates/normal/', 'https://via.placeholder.com/300x200?text=Normal', 'active'),
('Minimal Portfolio', 'minimal', 'portfolio', 'templates/minimal/', 'https://via.placeholder.com/300x200?text=Minimal', 'active'),
('Modern Portfolio', 'modern', 'portfolio', 'templates/modern/', 'https://via.placeholder.com/300x200?text=Modern', 'active'),
('Classic Portfolio', 'classic', 'portfolio', 'templates/classic/', 'https://via.placeholder.com/300x200?text=Classic', 'active'),
('Elegant Portfolio', 'elegant', 'portfolio', 'templates/elegant/', 'https://via.placeholder.com/300x200?text=Elegant', 'active'),
('Personal Basic', 'personal-basic', 'personal', 'templates/personal-basic/', 'https://via.placeholder.com/300x200?text=Personal', 'active'),
('Business Pro', 'business-pro', 'business', 'templates/business-pro/', 'https://via.placeholder.com/300x200?text=Business', 'active');

-- ============================================
-- VERIFICATION QUERIES
-- ============================================
-- Check tables exist
SHOW TABLES;

-- Check users (should show 2)
SELECT id, email, name, role, status FROM users;

-- Check templates (should show 10)
SELECT id, name, slug, template_type, status FROM templates;

-- ============================================
-- DATABASE BUILD COMPLETE
-- ============================================
