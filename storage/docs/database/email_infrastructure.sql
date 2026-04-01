-- CodeCanvas Email Infrastructure — Database Migration
-- Run this in phpMyAdmin or MySQL CLI: mysql -u root codecanvas < email_infrastructure.sql

-- ─── OTP Tokens Table ──────────────────────────────────────────────
-- Stores hashed password-reset OTP codes with expiry tracking.

CREATE TABLE IF NOT EXISTS `otp_tokens` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `email`       VARCHAR(255) NOT NULL,
    `token_hash`  VARCHAR(255) NOT NULL            COMMENT 'bcrypt hash of the OTP, never store plaintext',
    `expires_at`  DATETIME     NOT NULL             COMMENT '10 minutes from creation',
    `used`        TINYINT(1)   NOT NULL DEFAULT 0   COMMENT '1 = consumed, prevents replay',
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_email_used`    (`email`, `used`),
    INDEX `idx_expires_at`    (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Cleanup Procedure (Optional) ──────────────────────────────────
-- Run periodically to purge expired/used tokens and keep the table lean.
-- Can be scheduled via MySQL Event Scheduler or a cron job.

DELIMITER //
CREATE EVENT IF NOT EXISTS `cleanup_expired_otp_tokens`
  ON SCHEDULE EVERY 1 HOUR
  DO
  BEGIN
    DELETE FROM `otp_tokens`
    WHERE `used` = 1
       OR `expires_at` < NOW() - INTERVAL 24 HOUR;
  END //
DELIMITER ;
