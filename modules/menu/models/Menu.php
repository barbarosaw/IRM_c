<?php
/**
 * AbroadWorks Management System - Menu Model
 *
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class Menu {
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
     * Get all menu items
     * 
     * @return array All menu items
     */
    public function getAllMenuItems() {
        $stmt = $this->db->query("SELECT * FROM menu_items ORDER BY display_order");
        return $stmt->fetchAll();
    }
    
    /**
     * Get menu item by ID
     * 
     * @param int $id Menu item ID
     * @return array|false Menu item data or false if not found
     */
    public function getMenuItemById($id) {
        $stmt = $this->db->prepare("SELECT * FROM menu_items WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get menu items as a hierarchical tree
     * 
     * @return array Menu items organized in a tree structure
     */
    public function getMenuTree() {
        $menu_items = $this->getAllMenuItems();
        
        // Organize menu items into a hierarchical structure
        $menu_tree = [];
        $menu_lookup = [];

        // First, create a lookup array
        foreach ($menu_items as $item) {
            $menu_lookup[$item['id']] = $item;
            $menu_lookup[$item['id']]['children'] = [];
        }

        // Then, build the tree
        foreach ($menu_items as $item) {
            if ($item['parent_id'] === null) {
                $menu_tree[] = &$menu_lookup[$item['id']];
            } else {
                $menu_lookup[$item['parent_id']]['children'][] = &$menu_lookup[$item['id']];
            }
        }
        
        return $menu_tree;
    }
    
    /**
     * Add a new menu item
     * 
     * @param string $name Menu item name
     * @param string $url Menu item URL
     * @param string $icon Menu item icon
     * @param string|null $permission Permission required to view this menu item
     * @param int|null $parent_id Parent menu item ID
     * @param int $display_order Display order
     * @param bool $is_active Whether the menu item is active
     * @return int|false New menu item ID or false on failure
     */
    public function addMenuItem($name, $url, $icon, $permission = null, $parent_id = null, $display_order = 0, $is_active = true) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO menu_items (parent_id, name, url, icon, permission, display_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $parent_id, 
                $name, 
                $url, 
                $icon, 
                $permission, 
                $display_order, 
                $is_active ? 1 : 0
            ]);
            
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            log_error("Error adding menu item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update a menu item
     * 
     * @param int $id Menu item ID
     * @param string $name Menu item name
     * @param string $url Menu item URL
     * @param string $icon Menu item icon
     * @param string|null $permission Permission required to view this menu item
     * @param int|null $parent_id Parent menu item ID
     * @param int $display_order Display order
     * @param bool $is_active Whether the menu item is active
     * @return bool Success status
     */
    public function updateMenuItem($id, $name, $url, $icon, $permission = null, $parent_id = null, $display_order = 0, $is_active = true) {
        try {
            $stmt = $this->db->prepare("
                UPDATE menu_items 
                SET parent_id = ?, name = ?, url = ?, icon = ?, permission = ?, display_order = ?, is_active = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $parent_id, 
                $name, 
                $url, 
                $icon, 
                $permission, 
                $display_order, 
                $is_active ? 1 : 0,
                $id
            ]);
            
            return true;
        } catch (Exception $e) {
            log_error("Error updating menu item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a menu item
     * 
     * @param int $id Menu item ID
     * @return bool Success status
     */
    public function deleteMenuItem($id) {
        try {
            // First check if this item has children
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM menu_items WHERE parent_id = ?");
            $stmt->execute([$id]);
            $has_children = $stmt->fetchColumn() > 0;
            
            if ($has_children) {
                return false; // Cannot delete if it has children
            }
            
            $stmt = $this->db->prepare("DELETE FROM menu_items WHERE id = ?");
            $stmt->execute([$id]);
            
            return true;
        } catch (Exception $e) {
            log_error("Error deleting menu item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update menu items order
     * 
     * @param array $items Array of items with id and order
     * @return bool Success status
     */
    public function updateMenuOrder($items) {
        try {
            $this->db->beginTransaction();
            
            foreach ($items as $item) {
                if (isset($item['id']) && isset($item['order'])) {
                    $stmt = $this->db->prepare("UPDATE menu_items SET display_order = ? WHERE id = ?");
                    $stmt->execute([(int)$item['order'], (int)$item['id']]);
                }
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            log_error("Error updating menu order: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a menu item has children
     * 
     * @param int $id Menu item ID
     * @return bool Whether the menu item has children
     */
    public function hasChildren($id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM menu_items WHERE parent_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get all permissions for dropdown
     * 
     * @return array All permissions
     */
    public function getAllPermissions() {
        $stmt = $this->db->query("SELECT id, code, name FROM permissions ORDER BY module, name");
        return $stmt->fetchAll();
    }
}
