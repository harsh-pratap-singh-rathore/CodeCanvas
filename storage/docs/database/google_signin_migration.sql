-- Database migration to add Google Sign-In support
-- Run this script on the target database before enabling Google Sign-In

ALTER TABLE `users` 
ADD COLUMN `google_id` VARCHAR(255) NULL UNIQUE AFTER `email`,
ADD COLUMN `auth_provider` ENUM('local', 'google') NOT NULL DEFAULT 'local' AFTER `google_id`;

-- Note: The password_hash column should be allowed to be NULL or empty for 'google' auth_provider users.
-- Assuming password_hash is currently NOT NULL, you might need to change it if you want to support users without any password:
ALTER TABLE `users` MODIFY `password_hash` VARCHAR(255) NULL;
