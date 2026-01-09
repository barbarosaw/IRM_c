<?php
/**
 * AbroadWorks Management System - Role Model
 *
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class Role {
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
     * Get all roles with permission and user counts
     * 
     * @return array All roles with counts
     */
    public function getAllRolesWithCounts() {
        $query = "
            SELECT r.*, 
                   COUNT(DISTINCT rp.permission_id) as permission_count,
                   COUNT(DISTINCT ur.user_id) as user_count
            FROM roles r
            LEFT JOIN role_permissions rp ON r.id = rp.role_id
            LEFT JOIN user_roles ur ON r.id = ur.role_id
            GROUP BY r.id
            ORDER BY r.name
        ";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll();
    }
    
    /**
     * Get all roles
     * 
     * @return array All roles
     */
    public function getAllRoles() {
        $stmt = $this->db->query("SELECT * FROM roles ORDER BY name");
        return $stmt->fetchAll();
    }
    
    /**
     * Get role by ID
     * 
     * @param int $id Role ID
     * @return array|false Role data or false if not found
     */
    public function getRoleById($id) {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get role permissions
     * 
     * @param int $role_id Role ID
     * @return array Role permissions
     */
    public function getRolePermissions($role_id) {
        $stmt = $this->db->prepare("
            SELECT p.* 
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.module, p.name
        ");
        $stmt->execute([$role_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get role permission IDs
     * 
     * @param int $role_id Role ID
     * @return array Role permission IDs
     */
    public function getRolePermissionIds($role_id) {
        $stmt = $this->db->prepare("
            SELECT permission_id 
            FROM role_permissions
            WHERE role_id = ?
        ");
        $stmt->execute([$role_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Create new role
     * 
     * @param string $name Role name
     * @param string $description Role description
     * @param array $permissions Permission IDs
     * @return int|false New role ID or false on failure
     */
    public function createRole($name, $description, $permissions = []) {
        try {
            $this->db->beginTransaction();
            
            // Insert role
            $stmt = $this->db->prepare("
                INSERT INTO roles (name, description, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([$name, $description]);
            $role_id = $this->db->lastInsertId();
            
            // Assign permissions
            if (!empty($permissions)) {
                $perm_values = [];
                $perm_params = [];
                
                foreach ($permissions as $perm_id) {
                    $perm_values[] = "(?, ?)";
                    $perm_params[] = $role_id;
                    $perm_params[] = $perm_id;
                }
                
                $perm_query = "INSERT INTO role_permissions (role_id, permission_id) VALUES " . implode(', ', $perm_values);
                $perm_stmt = $this->db->prepare($perm_query);
                $perm_stmt->execute($perm_params);
            }
            
            $this->db->commit();
            return $role_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            log_error("Error creating role: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update role
     * 
     * @param int $id Role ID
     * @param string $name Role name
     * @param string $description Role description
     * @param array $permissions Permission IDs
     * @return bool Success status
     */
    public function updateRole($id, $name, $description, $permissions = []) {
        try {
            $this->db->beginTransaction();
            
            // Update role
            $stmt = $this->db->prepare("
                UPDATE roles 
                SET name = ?, description = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$name, $description, $id]);
            
            // Delete existing permissions
            $delete_stmt = $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $delete_stmt->execute([$id]);
            
            // Assign new permissions
            if (!empty($permissions)) {
                $perm_values = [];
                $perm_params = [];
                
                foreach ($permissions as $perm_id) {
                    $perm_values[] = "(?, ?)";
                    $perm_params[] = $id;
                    $perm_params[] = $perm_id;
                }
                
                $perm_query = "INSERT INTO role_permissions (role_id, permission_id) VALUES " . implode(', ', $perm_values);
                $perm_stmt = $this->db->prepare($perm_query);
                $perm_stmt->execute($perm_params);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            log_error("Error updating role: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete role
     * 
     * @param int $id Role ID
     * @return bool Success status
     */
    public function deleteRole($id) {
        try {
            $this->db->beginTransaction();
            
            // Delete role permissions
            $perm_stmt = $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $perm_stmt->execute([$id]);
            
            // Delete role
            $role_stmt = $this->db->prepare("DELETE FROM roles WHERE id = ?");
            $role_stmt->execute([$id]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            log_error("Error deleting role: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if role is in use
     * 
     * @param int $id Role ID
     * @return int Number of users with this role
     */
    public function isRoleInUse($id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_roles WHERE role_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get users with role
     * 
     * @param int $role_id Role ID
     * @return array Users with role
     */
    public function getUsersWithRole($role_id) {
        $stmt = $this->db->prepare("
            SELECT u.* 
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            WHERE ur.role_id = ?
            ORDER BY u.name
        ");
        $stmt->execute([$role_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Check if role name exists
     * 
     * @param string $name Role name
     * @param int $exclude_id Role ID to exclude from check
     * @return bool Whether role name exists
     */
    public function roleNameExists($name, $exclude_id = null) {
        $query = "SELECT COUNT(*) FROM roles WHERE name = ?";
        $params = [$name];
        
        if ($exclude_id) {
            $query .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    }
}
