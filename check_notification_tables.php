<?php
/**
 * Check Notification Tables
 * 
 * This script checks if the notification tables exist in the database.
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
    
    // Check if messages table exists
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'messages'
    ");
    $messages_table_exists = (int)$stmt->fetchColumn();
    
    // Check if system_notifications table exists
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'system_notifications'
    ");
    $system_notifications_table_exists = (int)$stmt->fetchColumn();
    
    // Check if user_notifications table exists
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'user_notifications'
    ");
    $user_notifications_table_exists = (int)$stmt->fetchColumn();
    
    // Check if is_system column exists in users table
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM information_schema.columns 
        WHERE table_schema = DATABASE() 
        AND table_name = 'users' 
        AND column_name = 'is_system'
    ");
    $is_system_column_exists = (int)$stmt->fetchColumn();
    
    // Check if system user exists
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM `users` 
        WHERE `is_system` = 1 
        LIMIT 1
    ");
    $system_user_exists = (int)$stmt->fetchColumn();
    
    // Check if permissions exist
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM `permissions` 
        WHERE `code` = 'notifications-view' 
        LIMIT 1
    ");
    $permissions_exist = (int)$stmt->fetchColumn();
    
    // Output results
    echo "Messages table exists: " . ($messages_table_exists ? 'Yes' : 'No') . "\n";
    echo "System notifications table exists: " . ($system_notifications_table_exists ? 'Yes' : 'No') . "\n";
    echo "User notifications table exists: " . ($user_notifications_table_exists ? 'Yes' : 'No') . "\n";
    echo "Is system column exists in users table: " . ($is_system_column_exists ? 'Yes' : 'No') . "\n";
    echo "System user exists: " . ($system_user_exists ? 'Yes' : 'No') . "\n";
    echo "Permissions exist: " . ($permissions_exist ? 'Yes' : 'No') . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
