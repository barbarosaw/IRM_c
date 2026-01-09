<?php
/**
 * AbroadWorks Management System - Permission Model
 *
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class Permission {
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
     * Get all permissions
     * 
     * @return array All permissions
     */
    public function getAllPermissions() {
        $stmt = $this->db->query("SELECT * FROM permissions ORDER BY module, module_order, name");
        return $stmt->fetchAll();
    }
    
    /**
     * Get permission by ID
     * 
     * @param int $id Permission ID
     * @return array|false Permission data or false if not found
     */
    public function getPermissionById($id) {
        $stmt = $this->db->prepare("SELECT * FROM permissions WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get permissions by module
     * 
     * @param string $module Module code
     * @return array Permissions for module
     */
    public function getPermissionsByModule($module) {
        $stmt = $this->db->prepare("SELECT * FROM permissions WHERE module = ? ORDER BY module_order, name");
        $stmt->execute([$module]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get permissions grouped by module
     * 
     * @return array Permissions grouped by module
     */
    public function getPermissionsByModuleGrouped() {
        $permissions_by_module = [];
        
        try {
            // First get all modules
            $module_query = "SELECT * FROM modules ORDER BY `order`";
            $module_stmt = $this->db->query($module_query);
            $module_list = $module_stmt->fetchAll();
            
            // Initialize modules array
            foreach ($module_list as $module) {
                $permissions_by_module[$module['code']] = [
                    'name' => $module['name'],
                    'description' => $module['description'],
                    'icon' => $module['icon'],
                    'permissions' => []
                ];
            }
            
            // Now get all permissions
            $permission_query = "SELECT * FROM permissions ORDER BY module, module_order, name";
            $permission_stmt = $this->db->query($permission_query);
            $permissions = $permission_stmt->fetchAll();
            
            // Group permissions by module
            foreach ($permissions as $permission) {
                $module = $permission['module'] ?? 'other';
                if (!isset($permissions_by_module[$module])) {
                    $permissions_by_module[$module] = [
                        'name' => ucfirst($module),
                        'description' => 'Module permissions',
                        'icon' => 'fa-puzzle-piece',
                        'permissions' => []
                    ];
                }
                
                $permissions_by_module[$module]['permissions'][] = $permission;
            }
            
            return $permissions_by_module;
        } catch (Exception $e) {
            log_error('Error fetching permissions by module: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create new permission
     * 
     * @param string $name Permission name
     * @param string $code Permission code
     * @param string $description Permission description
     * @param string $module Module code
     * @param int $module_order Order within module
     * @return int|false New permission ID or false on failure
     */
    public function createPermission($name, $code, $description, $module = null, $module_order = 0) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO permissions (name, code, description, module, module_order, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([$name, $code, $description, $module, $module_order]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            log_error("Error creating permission: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update permission
     * 
     * @param int $id Permission ID
     * @param string $name Permission name
     * @param string $code Permission code
     * @param string $description Permission description
     * @param string $module Module code
     * @param int $module_order Order within module
     * @return bool Success status
     */
    public function updatePermission($id, $name, $code, $description, $module = null, $module_order = 0) {
        try {
            $stmt = $this->db->prepare("
                UPDATE permissions 
                SET name = ?, code = ?, description = ?, module = ?, module_order = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$name, $code, $description, $module, $module_order, $id]);
            return true;
        } catch (Exception $e) {
            log_error("Error updating permission: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete permission
     * 
     * @param int $id Permission ID
     * @return bool Success status
     */
    public function deletePermission($id) {
        try {
            $this->db->beginTransaction();
            
            // Delete role permissions
            $role_stmt = $this->db->prepare("DELETE FROM role_permissions WHERE permission_id = ?");
            $role_stmt->execute([$id]);
            
            // Delete permission
            $perm_stmt = $this->db->prepare("DELETE FROM permissions WHERE id = ?");
            $perm_stmt->execute([$id]);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            log_error("Error deleting permission: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get roles with permission
     * 
     * @param int $permission_id Permission ID
     * @return array Roles with permission
     */
    public function getRolesWithPermission($permission_id) {
        $stmt = $this->db->prepare("
            SELECT r.* 
            FROM roles r
            JOIN role_permissions rp ON r.id = rp.role_id
            WHERE rp.permission_id = ?
            ORDER BY r.name
        ");
        $stmt->execute([$permission_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Check if permission code exists
     * 
     * @param string $code Permission code
     * @param int $exclude_id Permission ID to exclude from check
     * @return bool Whether permission code exists
     */
    public function permissionCodeExists($code, $exclude_id = null) {
        $query = "SELECT COUNT(*) FROM permissions WHERE code = ?";
        $params = [$code];
        
        if ($exclude_id) {
            $query .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    }
}
