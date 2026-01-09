<?php
/**
 * Check if receivings module exists in modules table and add it if not
 */

// Include database connection
require_once 'config/database.php';

// Check if modules table exists
try {
    $result = $db->query("SHOW TABLES LIKE 'modules'");
    $exists = $result->rowCount() > 0;
    
    echo "Modules table exists: " . ($exists ? "Yes" : "No") . "\n";
    
    if (!$exists) {
        echo "Please run check_modules_table.php first to create the modules table.\n";
        exit;
    }
    
    // Check if receivings module exists
    $result = $db->query("SELECT * FROM modules WHERE code = 'receivings'");
    $exists = $result->rowCount() > 0;
    
    echo "Receivings module exists: " . ($exists ? "Yes" : "No") . "\n";
    
    if (!$exists) {
        echo "Adding receivings module...\n";
        
        // Add receivings module
        $sql = "
        INSERT INTO `modules` (`name`, `code`, `description`, `is_active`, `created_at`, `updated_at`)
        VALUES ('Receivings', 'receivings', 'Manage weekly and monthly receivings', 1, NOW(), NOW())
        ";
        
        $db->exec($sql);
        echo "Receivings module added successfully.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
