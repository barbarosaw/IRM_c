<?php
/**
 * AbroadWorks Management System - Check 2FA Settings
 * 
 * @author ikinciadam@gmail.com
 */

// Include database connection
require_once 'config/database.php';

// Query settings
$stmt = $db->prepare("SELECT * FROM settings WHERE `key` LIKE 'two_factor_%'");
$stmt->execute();
$settings = $stmt->fetchAll();

echo "2FA Settings:\n";
echo "=============\n";

foreach ($settings as $setting) {
    echo $setting['key'] . ": " . $setting['value'] . "\n";
}

// Check if users table has 2FA columns
try {
    $stmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'two_factor_%'");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "\nUsers Table 2FA Columns:\n";
    echo "=======================\n";
    
    if (count($columns) > 0) {
        foreach ($columns as $column) {
            echo $column['Field'] . "\n";
        }
    } else {
        echo "No 2FA columns found in users table.\n";
    }
} catch (PDOException $e) {
    echo "Error checking users table: " . $e->getMessage() . "\n";
}

// Check if recovery codes table exists
try {
    $stmt = $db->prepare("SHOW TABLES LIKE 'two_factor_recovery_codes'");
    $stmt->execute();
    $table_exists = $stmt->rowCount() > 0;
    
    echo "\nRecovery Codes Table:\n";
    echo "====================\n";
    
    if ($table_exists) {
        echo "two_factor_recovery_codes table exists.\n";
    } else {
        echo "two_factor_recovery_codes table does not exist.\n";
    }
} catch (PDOException $e) {
    echo "Error checking recovery codes table: " . $e->getMessage() . "\n";
}
