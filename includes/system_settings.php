<?php
/**
 * AbroadWorks Management System - System Settings Helper
 * 
 * @author ikinciadam@gmail.com
 */

// Create settings table if it doesn't exist
try {
    $db->query("
        CREATE TABLE IF NOT EXISTS `settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `key` varchar(255) NOT NULL UNIQUE,
            `value` text DEFAULT NULL,
            `group` varchar(255) DEFAULT 'general',
            `is_public` tinyint(1) DEFAULT 0,
            `created_at` datetime NOT NULL,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    error_log("Error creating settings table: " . $e->getMessage());
}

// Default settings definitions
$default_settings = [
    // General Settings
    'site_name' => [
        'value' => 'AbroadWorks Management System',
        'group' => 'general'
    ],
    'site_description' => [
        'value' => 'Internal Resources Management System',
        'group' => 'general'
    ],
    'date_format' => [
        'value' => 'Y-m-d',
        'group' => 'general'
    ],
    'time_format' => [
        'value' => 'H:i',
        'group' => 'general'
    ],
    'timezone' => [
        'value' => 'Europe/Istanbul',
        'group' => 'general'
    ],
    'system_theme' => [
        'value' => 'default',
        'group' => 'general'
    ],
    
    // Company Settings
    'company_name' => [
        'value' => 'AbroadWorks',
        'group' => 'company'
    ],
    'company_email' => [
        'value' => 'info@abroadworks.com',
        'group' => 'company'
    ],
    'company_phone' => [
        'value' => '+1 234 567 8900',
        'group' => 'company'
    ],
    'company_address' => [
        'value' => '123 Main Street, City, Country',
        'group' => 'company'
    ],
    
    // System Settings
    'maintenance_mode' => [
        'value' => '0',
        'group' => 'system'
    ],
    'maintenance_message' => [
        'value' => 'System is currently undergoing scheduled maintenance. Please check back later.',
        'group' => 'system'
    ],
    'pagination_limit' => [
        'value' => '25',
        'group' => 'system'
    ],
    'log_retention_days' => [
        'value' => '90',
        'group' => 'system'
    ],
    
    // Security Settings
    'session_lifetime' => [
        'value' => '120',
        'group' => 'security'
    ],
    'enable_registration' => [
        'value' => '0',
        'group' => 'security'
    ],
    'approval_required' => [
        'value' => '1',
        'group' => 'security'
    ],
    'default_role' => [
        'value' => '',
        'group' => 'security'
    ],
    
    // Debug Settings
    'display_errors' => [
        'value' => '1',
        'group' => 'debug'
    ],
    'log_errors' => [
        'value' => '1',
        'group' => 'debug'
    ],
    'error_reporting' => [
        'value' => 'E_ALL',
        'group' => 'debug'
    ],
    'max_execution_time' => [
        'value' => '60',
        'group' => 'debug'
    ],
    'memory_limit' => [
        'value' => '128M',
        'group' => 'debug'
    ],
    'upload_max_filesize' => [
        'value' => '10M',
        'group' => 'debug'
    ],
    'post_max_size' => [
        'value' => '12M',
        'group' => 'debug'
    ],
    
    // Backup Settings
    'backup_exclude_tables' => [
        'value' => 'sessions,activity_logs',
        'group' => 'backup'
    ],
    'backup_count' => [
        'value' => '5',
        'group' => 'backup'
    ],
    
    // API Settings
    'fingerprint_api_key' => [
        'value' => 'YOUR_FINGERPRINT_API_KEY_HERE',
        'group' => 'api'
    ],
    'pwpush_api_token' => [
        'value' => '',
        'group' => 'api'
    ],
    'pwpush_user_email' => [
        'value' => '',
        'group' => 'api'
    ],
    'pwpush_base_url' => [
        'value' => 'https://pwpush.com',
        'group' => 'api'
    ],
    'pwpush_expire_days' => [
        'value' => '7',
        'group' => 'api'
    ],
    'pwpush_expire_views' => [
        'value' => '5',
        'group' => 'api'
    ]
];

// Insert default settings if they don't exist
foreach ($default_settings as $key => $setting) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM settings WHERE `key` = ?");
    $stmt->execute([$key]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO settings (`key`, `value`, `group`, `created_at`, `updated_at`) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt->execute([$key, $setting['value'], $setting['group']]);
    }
}
