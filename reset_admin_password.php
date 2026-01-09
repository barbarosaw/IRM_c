<?php
/**
 * Reset admin user password
 */

// Include database connection
require_once 'config/database.php';

// Reset admin user password
try {
    // Get admin user ID
    $result = $db->query("SELECT id FROM users WHERE email = 'ikinciadam@gmail.com'");
    $admin_id = $result->fetchColumn();
    
    if ($admin_id) {
        echo "Admin user ID: " . $admin_id . "\n";
        
        // Generate a new password hash for 'password'
        $new_password = 'password';
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        echo "New password: " . $new_password . "\n";
        echo "New password hash: " . $new_password_hash . "\n";
        
        // Update admin user password
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$new_password_hash, $admin_id]);
        
        echo "Admin user password updated successfully.\n";
    } else {
        echo "Admin user not found.\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
