<?php
/**
 * Assign Admin role to admin user
 */

// Include database connection
require_once 'config/database.php';

// Assign Admin role to admin user
try {
    // Get admin user ID
    $result = $db->query("SELECT id FROM users WHERE email = 'admin@example.com'");
    $admin_id = $result->fetchColumn();
    
    if ($admin_id) {
        echo "Admin user ID: " . $admin_id . "\n";
        
        // Get Admin role ID
        $result = $db->query("SELECT id FROM roles WHERE name = 'Admin'");
        $admin_role_id = $result->fetchColumn();
        
        if ($admin_role_id) {
            echo "Admin role ID: " . $admin_role_id . "\n";
            
            // Check if admin user already has Admin role
            $result = $db->query("SELECT COUNT(*) FROM user_roles WHERE user_id = {$admin_id} AND role_id = {$admin_role_id}");
            $has_role = $result->fetchColumn() > 0;
            
            echo "Admin user has Admin role: " . ($has_role ? "Yes" : "No") . "\n";
            
            if (!$has_role) {
                echo "Assigning Admin role to admin user...\n";
                
                // Assign Admin role to admin user
                $sql = "
                INSERT INTO user_roles (user_id, role_id) 
                VALUES (?, ?)
                ";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([$admin_id, $admin_role_id]);
                
                echo "Admin role assigned to admin user successfully.\n";
            }
        } else {
            echo "Admin role not found.\n";
        }
    } else {
        echo "Admin user not found.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
