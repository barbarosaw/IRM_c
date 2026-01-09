<?php
/**
 * Check roles in the database
 */

// Include database connection
require_once 'config/database.php';

// Check if roles table exists
try {
    $result = $db->query("SHOW TABLES LIKE 'roles'");
    $exists = $result->rowCount() > 0;
    
    echo "Roles table exists: " . ($exists ? "Yes" : "No") . "\n";
    
    if ($exists) {
        // Get all roles
        $result = $db->query("SELECT * FROM roles");
        $roles = $result->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Roles count: " . count($roles) . "\n";
        
        if (count($roles) > 0) {
            echo "Roles:\n";
            foreach ($roles as $role) {
                echo "- ID: " . $role['id'] . ", Name: " . $role['name'] . ", Description: " . $role['description'] . "\n";
            }
        } else {
            echo "Creating default roles...\n";
            
            // Create default roles
            $sql = "
            INSERT INTO `roles` (`name`, `description`, `created_at`, `updated_at`) VALUES
            ('Admin', 'Administrator with full access to all features', NOW(), NOW()),
            ('Manager', 'Manager with access to most features except system settings', NOW(), NOW()),
            ('Returns', 'Returns department staff', NOW(), NOW()),
            ('Buyer', 'Purchasing department staff', NOW(), NOW()),
            ('Vendor', 'External vendor with limited access', NOW(), NOW())
            ";
            
            $db->exec($sql);
            echo "Default roles created successfully.\n";
        }
        
        // Check if admin role exists
        $result = $db->query("SELECT * FROM roles WHERE name = 'Admin'");
        $admin_role_exists = $result->rowCount() > 0;
        
        echo "Admin role exists: " . ($admin_role_exists ? "Yes" : "No") . "\n";
        
        if (!$admin_role_exists) {
            echo "Creating admin role...\n";
            
            // Create admin role
            $sql = "
            INSERT INTO `roles` (`name`, `description`, `created_at`, `updated_at`) VALUES
            ('Admin', 'Administrator with full access to all features', NOW(), NOW())
            ";
            
            $db->exec($sql);
            echo "Admin role created successfully.\n";
        }
    } else {
        echo "Creating roles table...\n";
        
        // Create roles table
        $sql = "
        CREATE TABLE IF NOT EXISTS `roles` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `description` text DEFAULT NULL,
          `created_at` datetime NOT NULL,
          `updated_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        
        $db->exec($sql);
        echo "Roles table created successfully.\n";
        
        // Create default roles
        $sql = "
        INSERT INTO `roles` (`name`, `description`, `created_at`, `updated_at`) VALUES
        ('Admin', 'Administrator with full access to all features', NOW(), NOW()),
        ('Manager', 'Manager with access to most features except system settings', NOW(), NOW()),
        ('Returns', 'Returns department staff', NOW(), NOW()),
        ('Buyer', 'Purchasing department staff', NOW(), NOW()),
        ('Vendor', 'External vendor with limited access', NOW(), NOW())
        ";
        
        $db->exec($sql);
        echo "Default roles created successfully.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
