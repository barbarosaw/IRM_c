<?php
/**
 * Check if modules table exists and create it if not
 */

// Include database connection
require_once 'config/database.php';

// Check if modules table exists
try {
    $result = $db->query("SHOW TABLES LIKE 'modules'");
    $exists = $result->rowCount() > 0;
    
    echo "Modules table exists: " . ($exists ? "Yes" : "No") . "\n";
    
    if (!$exists) {
        echo "Creating modules table...\n";
        
        // Create modules table
        $sql = "
        CREATE TABLE IF NOT EXISTS `modules` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `code` varchar(255) NOT NULL,
          `description` text DEFAULT NULL,
          `is_active` tinyint(1) NOT NULL DEFAULT 1,
          `created_at` datetime NOT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $db->exec($sql);
        echo "Modules table created successfully.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
