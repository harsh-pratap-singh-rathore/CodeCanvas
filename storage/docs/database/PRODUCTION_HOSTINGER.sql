-- ============================================================
-- CODECANVAS — PRODUCTION DATABASE
-- Hostinger: u193155059_codecanvas
-- Generated: 2026-02-28
-- ============================================================
-- HOW TO IMPORT:
--   1. Hostinger cPanel → phpMyAdmin → Select u193155059_codecanvas
--   2. Click "Import" tab → Choose this file → Go
--   NOTE: Do NOT run DROP DATABASE — Hostinger DB already exists.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+05:30";

-- ─────────────────────────────────────────────────────────────
-- TABLE 1: USERS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `email`         VARCHAR(255)     NOT NULL,
    `password_hash` VARCHAR(255)     NOT NULL,
    `name`          VARCHAR(100)     NOT NULL,
    `role`          ENUM('user','admin') DEFAULT 'user',
    `status`        ENUM('active','inactive') DEFAULT 'active',
    `created_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`),
    INDEX `idx_role`   (`role`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE 2: TEMPLATES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `templates` (
    `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`                  VARCHAR(100)  NOT NULL,
    `slug`                  VARCHAR(100)  NOT NULL,
    `template_type`         ENUM('personal','portfolio','business') NOT NULL,
    `folder_path`           VARCHAR(255)  NOT NULL,
    `thumbnail_url`         VARCHAR(255)  DEFAULT NULL,
    `preview_image_path`    VARCHAR(255)  DEFAULT NULL,
    `preview_fallback_path` VARCHAR(255)  DEFAULT NULL,
    `status`                ENUM('active','inactive') DEFAULT 'active',
    `created_at`            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug` (`slug`),
    INDEX `idx_status`        (`status`),
    INDEX `idx_template_type` (`template_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE 3: PROJECTS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `projects` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED  NOT NULL,
    `template_id`     INT UNSIGNED  NOT NULL,
    `project_name`    VARCHAR(255)  NOT NULL,
    `project_type`    ENUM('personal','portfolio','business') DEFAULT 'portfolio',
    `custom_slug`     VARCHAR(100)  DEFAULT NULL,
    `brand_name`      VARCHAR(255)  DEFAULT NULL,
    `description`     TEXT          DEFAULT NULL,
    `skills`          TEXT          DEFAULT NULL,
    `contact`         VARCHAR(255)  DEFAULT NULL,
    `status`          ENUM('draft','published','archived','active') DEFAULT 'draft',
    `publish_status`  ENUM('draft','building','deployed','failed','publishing','published') DEFAULT 'draft',
    `build_log`       TEXT          DEFAULT NULL,
    `live_url`        VARCHAR(255)  DEFAULT NULL,
    `netlify_site_id` VARCHAR(100)  DEFAULT NULL,
    `content_json`    LONGTEXT      DEFAULT NULL,
    `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)     ON DELETE CASCADE,
    FOREIGN KEY (`template_id`) REFERENCES `templates`(`id`) ON DELETE RESTRICT,
    INDEX `idx_user_id`    (`user_id`),
    INDEX `idx_template_id`(`template_id`),
    INDEX `idx_status`     (`status`),
    INDEX `idx_custom_slug`(`custom_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE 4: MESSAGES (portfolio contact forms)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `messages` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id`    INT UNSIGNED NOT NULL,
    `visitor_name`  VARCHAR(100) NOT NULL,
    `visitor_email` VARCHAR(255) NOT NULL,
    `subject`       VARCHAR(255) DEFAULT NULL,
    `message`       TEXT         NOT NULL,
    `is_read`       BOOLEAN      NOT NULL DEFAULT FALSE,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_is_read`    (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE 5: NOTIFICATIONS
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `type`       ENUM('message','system','project_status') DEFAULT 'system',
    `title`      VARCHAR(255) NOT NULL,
    `content`    TEXT         DEFAULT NULL,
    `link`       VARCHAR(255) DEFAULT NULL,
    `is_read`    BOOLEAN      NOT NULL DEFAULT FALSE,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_is_read` (`is_read`),
    INDEX `idx_type`    (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE 6: OTP TOKENS (password reset)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `otp_tokens` (
    `id`         INT          NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(255) NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL  COMMENT 'bcrypt hash — never store plaintext',
    `expires_at` DATETIME     NOT NULL  COMMENT 'SET via DATE_ADD(NOW(), INTERVAL 10 MINUTE)',
    `used`       TINYINT(1)   NOT NULL DEFAULT 0 COMMENT '1 = consumed, prevents replay',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email_used`  (`email`, `used`),
    INDEX `idx_expires_at`  (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- TABLE 7: ADMINS (separate admin table if used)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`         VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `name`          VARCHAR(100) DEFAULT NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ─────────────────────────────────────────────────────────────
-- SEED DATA: TEMPLATES
-- (Only the 4 templates that actually exist in /templates/)
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `templates` (`name`, `slug`, `template_type`, `folder_path`, `status`) VALUES
('Developer Portfolio', 'developer',         'portfolio', 'templates/developer/', 'active'),
('Business Portfolio',  'business',          'business',  'templates/business/',  'active'),
('Shop Template',       'shop',              'business',  'templates/shop/',      'active'),
('Normal Portfolio',    'normal',            'personal',  'templates/normal/',    'active');

-- ─────────────────────────────────────────────────────────────
-- SEED DATA: ADMIN USER
-- Email: admin@codecanvas.page
-- Password: CodeCanvas@Admin2026  (bcrypt hash below)
-- CHANGE THIS PASSWORD after first login!
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `users` (`email`, `password_hash`, `name`, `role`, `status`) VALUES
('admin@codecanvas.page',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'CodeCanvas Admin', 'admin', 'active');

-- NOTE: The hash above is for password 'password' (Laravel default test hash).
-- Run this in PHP to generate a proper hash for your admin password:
--   echo password_hash('YourSecurePassword', PASSWORD_DEFAULT);
-- Then UPDATE users SET password_hash='<new_hash>' WHERE email='admin@codecanvas.page';

-- ─────────────────────────────────────────────────────────────
-- OTP CLEANUP EVENT (optional — enable if MySQL events allowed)
-- ─────────────────────────────────────────────────────────────
-- Uncomment if your Hostinger plan supports MySQL Event Scheduler:
-- CREATE EVENT IF NOT EXISTS `cleanup_expired_otp_tokens`
--   ON SCHEDULE EVERY 1 HOUR
--   DO
--     DELETE FROM `otp_tokens` WHERE `used` = 1 OR `expires_at` < NOW() - INTERVAL 24 HOUR;

-- ─────────────────────────────────────────────────────────────
-- DONE — Verify with:
-- SHOW TABLES;
-- SELECT id, email, name, role FROM users;
-- SELECT id, name, slug FROM templates;
-- ─────────────────────────────────────────────────────────────
