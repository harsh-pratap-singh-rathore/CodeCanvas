<?php
/**
 * SETUP SCRIPT
 * Initializes the database schema and default data.
 * Run this once to set up the project.
 */

// Error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Manual Config for Setup (to avoid dependency on existing DB)
define('DB_HOST', 'localhost');
define('DB_NAME', 'codecanvas');
define('DB_USER', 'root');
define('DB_PASS', '');

echo "<h1>CodeCanvas Setup</h1>";

try {
    // 1. Connect to MySQL Server (No DB selected)
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 2. Create Database
    echo "Checking database '" . DB_NAME . "'... ";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<span style='color:green'>Done</span><br>";
    
    // 3. Select Database
    $pdo->exec("USE `" . DB_NAME . "`");


    // 1. Create Users Table
    echo "Creating 'users' table... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `role` ENUM('user', 'admin') DEFAULT 'user',
            `status` ENUM('active', 'inactive') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_email` (`email`),
            INDEX `idx_role` (`role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "<span style='color:green'>Done</span><br>";

    // 2. Create Templates Table
    echo "Creating 'templates' table... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `templates` (
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
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "<span style='color:green'>Done</span><br>";

    // 3. Create Projects Table
    echo "Creating 'projects' table... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `projects` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `template_id` INT UNSIGNED NOT NULL,
            `project_name` VARCHAR(255) NOT NULL,
            `project_type` ENUM('personal', 'portfolio', 'business') NOT NULL,
            `brand_name` VARCHAR(255),
            `description` TEXT,
            `skills` TEXT,
            `contact` VARCHAR(255),
            `status` ENUM('draft', 'published') DEFAULT 'draft',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`template_id`) REFERENCES `templates`(`id`),
            INDEX `idx_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "<span style='color:green'>Done</span><br>";

    // 4. Insert Default Admin
    $adminEmail = 'admin@codecanvas.com';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    if (!$stmt->fetch()) {
        echo "Inserting default admin ($adminEmail)... ";
        $passHash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (email, password_hash, name, role) VALUES (?, ?, 'Admin User', 'admin')")
            ->execute([$adminEmail, $passHash]);
        echo "<span style='color:green'>Done</span><br>";
    } else {
        echo "Admin already exists.<br>";
    }

    // 5. Insert Default Templates
    $templates = [
        ['Minimal', 'minimal', 'portfolio', 'templates/minimal/', 'https://via.placeholder.com/300x200?text=Minimal'],
        ['Modern', 'modern', 'portfolio', 'templates/modern/', 'https://via.placeholder.com/300x200?text=Modern'],
        ['Business Pro', 'business-pro', 'business', 'templates/business-pro/', 'https://via.placeholder.com/300x200?text=Business']
    ];

    echo "Checking templates... ";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM templates");
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        echo "Inserting default templates... ";
        $insert = $pdo->prepare("INSERT INTO templates (name, slug, template_type, folder_path, thumbnail_url) VALUES (?, ?, ?, ?, ?)");
        foreach ($templates as $t) {
            $insert->execute($t);
        }
        echo "<span style='color:green'>Done</span><br>";
    } else {
        echo "$count templates found. Skipping insert.<br>";
    }

    echo "<h2>Setup Complete!</h2>";
    echo "<p><a href='public/index.html'>Go to Landing Page</a> | <a href='public/login.html'>Login</a></p>";

} catch (PDOException $e) {
    echo "<h2 style='color:red'>Setup Failed</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
