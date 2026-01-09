<?php
/**
 * Create Notification Tables
 * 
 * This script creates the notification tables directly using PDO.
 */

// Database connection settings
$db_host = 'localhost';
$db_name = 'vrm-p';
$db_user = 'root';
$db_pass = '';

try {
    // Create PDO connection
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    
    // Set error mode to exception
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create messages table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `messages` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `sender_id` int(11) NOT NULL,
          `receiver_id` int(11) NOT NULL,
          `subject` varchar(255) DEFAULT NULL,
          `message` text NOT NULL,
          `is_read` tinyint(1) DEFAULT 0,
          `is_system` tinyint(1) DEFAULT 0,
          `created_at` datetime NOT NULL,
          `read_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `sender_id` (`sender_id`),
          KEY `receiver_id` (`receiver_id`),
          FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Create system_notifications table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `system_notifications` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `type` varchar(50) NOT NULL,
          `title` varchar(255) NOT NULL,
          `message` text NOT NULL,
          `entity_type` varchar(50) DEFAULT NULL,
          `entity_id` int(11) DEFAULT NULL,
          `created_at` datetime NOT NULL,
          PRIMARY KEY (`id`),
          KEY `type` (`type`),
          KEY `entity_type` (`entity_type`, `entity_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Create user_notifications table
    $db->exec("
        CREATE TABLE IF NOT EXISTS `user_notifications` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `notification_id` int(11) NOT NULL,
          `is_read` tinyint(1) DEFAULT 0,
          `read_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `notification_id` (`notification_id`),
          FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
          FOREIGN KEY (`notification_id`) REFERENCES `system_notifications` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Check if is_system column exists in users table
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM information_schema.columns 
        WHERE table_schema = DATABASE() 
        AND table_name = 'users' 
        AND column_name = 'is_system'
    ");
    $column_exists = (int)$stmt->fetchColumn();
    
    // Add is_system column to users table if it doesn't exist
    if ($column_exists === 0) {
        $db->exec("
            ALTER TABLE `users` 
            ADD COLUMN `is_system` tinyint(1) DEFAULT 0 AFTER `is_owner`
        ");
    }
    
    // Check if system user exists
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM `users` 
        WHERE `is_system` = 1 
        LIMIT 1
    ");
    $system_user_exists = (int)$stmt->fetchColumn();
    
    // Create a system user for system notifications if not exists
    if ($system_user_exists === 0) {
        $db->exec("
            INSERT INTO `users` (`name`, `email`, `password`, `is_active`, `is_system`, `created_at`, `updated_at`)
            VALUES ('System', 'system@system.local', '', 1, 1, NOW(), NOW())
        ");
    }
    
    // Check if permissions exist
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM `permissions` 
        WHERE `code` = 'notifications-view' 
        LIMIT 1
    ");
    $permissions_exist = (int)$stmt->fetchColumn();
    
    // Add permissions for notifications if they don't exist
    if ($permissions_exist === 0) {
        $db->exec("
            INSERT INTO `permissions` (`name`, `code`, `description`, `created_at`, `updated_at`)
            VALUES 
            ('View Notifications', 'notifications-view', 'Can view notifications', NOW(), NOW()),
            ('Manage Notifications', 'notifications-manage', 'Can manage notifications', NOW(), NOW()),
            ('Send Messages', 'messages-send', 'Can send messages to other users', NOW(), NOW())
        ");
    }
    
    echo "Notification tables created successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
