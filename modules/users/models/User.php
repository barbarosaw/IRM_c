<?php
/**
 * AbroadWorks Management System - User Model
 *
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class User {
    private $db;
    
    /**
     * Constructor
     */
    public function __construct($db = null) {
        if ($db === null) {
            global $db;
            $this->db = $db;
        } else {
            $this->db = $db;
        }
    }
    
    /**
     * Get all users with their roles
     * 
     * @param bool $include_owner Whether to include owner accounts
     * @return array Users with their roles
     */
    public function getAllUsers($include_owner = false) {
        $query = "
            SELECT u.id, u.name, u.email, u.is_active, u.created_at, u.last_login_at,
                   GROUP_CONCAT(r.name SEPARATOR ', ') AS roles
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
        ";
        
        if (!$include_owner) {
            $query .= " WHERE u.is_owner = 0 AND u.is_system = 0";
        }
        
        $query .= " GROUP BY u.id ORDER BY u.id";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll();
    }
    
    /**
     * Get user by ID
     * 
     * @param int $id User ID
     * @return array|false User data or false if not found
     */
    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get user with roles by ID
     * 
     * @param int $id User ID
     * @return array|false User data with roles or false if not found
     */
    public function getUserWithRolesById($id) {
        $query = "
            SELECT u.*, GROUP_CONCAT(r.name SEPARATOR ', ') AS role_names,
                   GROUP_CONCAT(r.id SEPARATOR ',') AS role_ids
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.id = ?
            GROUP BY u.id
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get user roles
     * 
     * @param int $user_id User ID
     * @return array User roles
     */
    public function getUserRoles($user_id) {
        $stmt = $this->db->prepare("
            SELECT r.* 
            FROM roles r
            JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Create new user
     * 
     * @param array $data User data
     * @param array $roles User roles
     * @return int|false New user ID or false on failure
     */
    public function createUser($data, $roles = []) {
        try {
            $this->db->beginTransaction();
            
            // Hash password
            $data['password'] = password_hash_safe($data['password']);
            
            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (name, email, password, is_active, is_owner, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['password'],
                $data['is_active'] ?? 1,
                $data['is_owner'] ?? 0
            ]);
            
            $user_id = $this->db->lastInsertId();
            
            // Assign roles
            if (!empty($roles)) {
                $role_values = [];
                $role_params = [];
                
                foreach ($roles as $role_id) {
                    $role_values[] = "(?, ?)";
                    $role_params[] = $user_id;
                    $role_params[] = $role_id;
                }
                
                $role_query = "INSERT INTO user_roles (user_id, role_id) VALUES " . implode(', ', $role_values);
                $role_stmt = $this->db->prepare($role_query);
                $role_stmt->execute($role_params);
            }
            
            $this->db->commit();
            return $user_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            log_error("Error creating user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user
     * 
     * @param int $id User ID
     * @param array $data User data
     * @param array $roles User roles
     * @return bool Success status
     */
    public function updateUser($id, $data, $roles = null) {
        try {
            $this->db->beginTransaction();
            
            // Build update query
            $update_fields = [];
            $params = [];
            
            if (isset($data['name'])) {
                $update_fields[] = "name = ?";
                $params[] = $data['name'];
            }
            
            if (isset($data['email'])) {
                $update_fields[] = "email = ?";
                $params[] = $data['email'];
            }
            
            if (!empty($data['password'])) {
                $update_fields[] = "password = ?";
                $params[] = password_hash_safe($data['password']);
            }
            
            if (isset($data['is_active'])) {
                $update_fields[] = "is_active = ?";
                $params[] = $data['is_active'];
            }
            
            $update_fields[] = "updated_at = NOW()";
            
            // Add user ID to params
            $params[] = $id;
            
            // Update user
            if (!empty($update_fields)) {
                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET " . implode(', ', $update_fields) . "
                    WHERE id = ?
                ");
                
                $stmt->execute($params);
            }
            
            // Update roles if provided
            if ($roles !== null) {
                // Delete existing roles
                $delete_stmt = $this->db->prepare("DELETE FROM user_roles WHERE user_id = ?");
                $delete_stmt->execute([$id]);
                
                // Assign new roles
                if (!empty($roles)) {
                    $role_values = [];
                    $role_params = [];
                    
                    foreach ($roles as $role_id) {
                        $role_values[] = "(?, ?)";
                        $role_params[] = $id;
                        $role_params[] = $role_id;
                    }
                    
                    $role_query = "INSERT INTO user_roles (user_id, role_id) VALUES " . implode(', ', $role_values);
                    $role_stmt = $this->db->prepare($role_query);
                    $role_stmt->execute($role_params);
                }
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            log_error("Error updating user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete user
     * 
     * @param int $id User ID
     * @return bool Success status
     */
    public function deleteUser($id) {
        try {
            $this->db->beginTransaction();
            
            // Delete user roles
            $role_stmt = $this->db->prepare("DELETE FROM user_roles WHERE user_id = ?");
            $role_stmt->execute([$id]);
            
            // Delete user
            $user_stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $user_stmt->execute([$id]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            log_error("Error deleting user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Toggle user status
     * 
     * @param int $id User ID
     * @param int $status New status (0 or 1)
     * @return bool Success status
     */
    public function toggleUserStatus($id, $status) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$status, $id]);
            return true;
        } catch (Exception $e) {
            log_error("Error toggling user status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if email exists
     * 
     * @param string $email Email to check
     * @param int $exclude_id User ID to exclude from check
     * @return bool Whether email exists
     */
    public function emailExists($email, $exclude_id = null) {
        $query = "SELECT COUNT(*) FROM users WHERE email = ?";
        $params = [$email];
        
        if ($exclude_id) {
            $query .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get user activity
     * 
     * @param int $user_id User ID
     * @param int $limit Maximum number of records to return
     * @return array User activity
     */
    public function getUserActivity($user_id, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT * FROM activity_logs
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get recent users
     * 
     * @param int $limit Maximum number of records to return
     * @return array Recent users
     */
    public function getRecentUsers($limit = 5) {
        $stmt = $this->db->prepare("
            SELECT * FROM users
            WHERE is_owner = 0
            ORDER BY created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get user statistics
     * 
     * @return array User statistics
     */
    public function getUserStatistics() {
        $stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0
        ];
        
        try {
            // Total users
            $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE is_owner = 0");
            $stats['total'] = $stmt->fetchColumn();
            
            // Active users
            $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE is_owner = 0 AND is_active = 1");
            $stats['active'] = $stmt->fetchColumn();
            
            // Inactive users
            $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE is_owner = 0 AND is_active = 0");
            $stats['inactive'] = $stmt->fetchColumn();
        } catch (Exception $e) {
            log_error("Error getting user statistics: " . $e->getMessage());
        }
        
        return $stats;
    }
}
