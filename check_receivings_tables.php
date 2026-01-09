<?php
/**
 * Check if receivings tables exist and create them if not
 */

// Include database connection
require_once 'config/database.php';

// Check if receivings tables exist
try {
    // Check weekly receivings table
    $result = $db->query("SHOW TABLES LIKE 'receivings'");
    $exists = $result->rowCount() > 0;
    
    echo "Receivings table exists: " . ($exists ? "Yes" : "No") . "\n";
    
    if (!$exists) {
        echo "Creating receivings table...\n";
        
        // Create receivings table
        $sql = "
        CREATE TABLE IF NOT EXISTS `receivings` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `order_id` varchar(255) NOT NULL,
          `type` varchar(50) NOT NULL,
          `qty` int(11) NOT NULL,
          `status` varchar(50) NOT NULL,
          `date` date NOT NULL,
          `user_id` int(11) NOT NULL,
          `credit_amount` decimal(10,2) DEFAULT 0.00,
          `priority` int(11) DEFAULT 0,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `date` (`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $db->exec($sql);
        echo "Receivings table created successfully.\n";
    }
    
    // Check receiving notes table
    $result = $db->query("SHOW TABLES LIKE 'receiving_notes'");
    $exists = $result->rowCount() > 0;
    
    echo "Receiving notes table exists: " . ($exists ? "Yes" : "No") . "\n";
    
    if (!$exists) {
        echo "Creating receiving notes table...\n";
        
        // Create receiving notes table
        $sql = "
        CREATE TABLE IF NOT EXISTS `receiving_notes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `receiving_id` int(11) NOT NULL,
          `note` text NOT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `receiving_id` (`receiving_id`),
          CONSTRAINT `receiving_notes_ibfk_1` FOREIGN KEY (`receiving_id`) REFERENCES `receivings` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $db->exec($sql);
        echo "Receiving notes table created successfully.\n";
    }
    
    // Check monthly receivings table
    $result = $db->query("SHOW TABLES LIKE 'monthly_receivings'");
    $exists = $result->rowCount() > 0;
    
    echo "Monthly receivings table exists: " . ($exists ? "Yes" : "No") . "\n";
    
    if (!$exists) {
        echo "Creating monthly receivings table...\n";
        
        // Create monthly receivings table
        $sql = "
        CREATE TABLE IF NOT EXISTS `monthly_receivings` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `order_id` varchar(255) NOT NULL,
          `type` varchar(50) NOT NULL,
          `qty` int(11) NOT NULL,
          `status` varchar(50) NOT NULL,
          `date` date NOT NULL,
          `user_id` int(11) NOT NULL,
          `priority` int(11) DEFAULT 0,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `date` (`date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $db->exec($sql);
        echo "Monthly receivings table created successfully.\n";
    }
    
    // Check monthly receiving notes table
    $result = $db->query("SHOW TABLES LIKE 'monthly_receiving_notes'");
    $exists = $result->rowCount() > 0;
    
    echo "Monthly receiving notes table exists: " . ($exists ? "Yes" : "No") . "\n";
    
    if (!$exists) {
        echo "Creating monthly receiving notes table...\n";
        
        // Create monthly receiving notes table
        $sql = "
        CREATE TABLE IF NOT EXISTS `monthly_receiving_notes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `receiving_id` int(11) NOT NULL,
          `note` text NOT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `receiving_id` (`receiving_id`),
          CONSTRAINT `monthly_receiving_notes_ibfk_1` FOREIGN KEY (`receiving_id`) REFERENCES `monthly_receivings` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $db->exec($sql);
        echo "Monthly receiving notes table created successfully.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
