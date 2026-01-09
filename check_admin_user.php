<?php
/**
 * Check admin user in the database
 */

// Include database connection
require_once 'config/database.php';

// Check if admin user exists
try {
    $result = $db->query("SELECT * FROM users WHERE email = 'admin@example.com'");
    $exists = $result->rowCount() > 0;
    
    echo "Admin user exists: " . ($exists ? "Yes" : "No") . "\n";
    
    if ($exists) {
        $admin = $result->fetch(PDO::FETCH_ASSOC);
        echo "Admin ID: " . $admin['id'] . "\n";
        echo "Admin Name: " . $admin['name'] . "\n";
        echo "Admin Email: " . $admin['email'] . "\n";
        echo "Admin Password Hash: " . $admin['password'] . "\n";
        echo "Admin Is Active: " . $admin['is_active'] . "\n";
        
        // Check if admin has receivings permissions
        $result = $db->query("
            SELECT COUNT(*) FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            JOIN roles r ON rp.role_id = r.id
            JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = {$admin['id']} AND p.code = 'receivings-access'
        ");
        $has_permission = $result->fetchColumn() > 0;
        
        echo "Admin has receivings-access permission: " . ($has_permission ? "Yes" : "No") . "\n";
        
        if (!$has_permission) {
            echo "Adding receivings-access permission to admin...\n";
            
            // Get admin role ID
            $result = $db->query("SELECT id FROM roles WHERE name = 'Admin'");
            $admin_role_id = $result->fetchColumn();
            
            if ($admin_role_id) {
                // Get receivings-access permission ID
                $result = $db->query("SELECT id FROM permissions WHERE code = 'receivings-access'");
                $permission_id = $result->fetchColumn();
                
                if ($permission_id) {
                    // Add permission to admin role
                    $sql = "
                    INSERT INTO role_permissions (role_id, permission_id)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE role_id = role_id
                    ";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$admin_role_id, $permission_id]);
                    
                    echo "Receivings-access permission added to admin role.\n";
                } else {
                    echo "Receivings-access permission not found.\n";
                }
            } else {
                echo "Admin role not found.\n";
            }
        }
    } else {
        echo "Creating admin user...\n";
        
        // Create admin user
        $sql = "
        INSERT INTO `users` (`name`, `email`, `password`, `is_active`, `created_at`, `updated_at`) 
        VALUES ('Admin User', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW(), NOW())
        ";
        
        $db->exec($sql);
        echo "Admin user created successfully.\n";
        
        // Get admin user ID
        $admin_id = $db->lastInsertId();
        
        // Get admin role ID
        $result = $db->query("SELECT id FROM roles WHERE name = 'Admin'");
        $admin_role_id = $result->fetchColumn();
        
        if ($admin_role_id) {
            // Assign admin role to admin user
            $sql = "
            INSERT INTO user_roles (user_id, role_id) 
            VALUES (?, ?)
            ";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$admin_id, $admin_role_id]);
            
            echo "Admin role assigned to admin user.\n";
        } else {
            echo "Admin role not found.\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
