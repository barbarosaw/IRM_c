<?php
/**
 * AbroadWorks Management System - Inventory Item Model
 *
 * @author System Generated
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class InventoryItem {
    private $db;
    
    /**
     * Constructor
     */
    public function __construct($db = null) {
        global $database;
        $this->db = $db ?? $database;
    }
    
    /**
     * Get all items
     */
    public function getAll($filters = []) {
        $sql = "SELECT i.*, st.name as subscription_type_name, st.color as subscription_type_color 
                FROM inventory_items i 
                LEFT JOIN inventory_subscription_types st ON i.subscription_type_id = st.id 
                WHERE 1=1";
        $params = [];
        
        if (!empty($filters['is_active'])) {
            $sql .= " AND i.is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['subscription_type_id'])) {
            $sql .= " AND i.subscription_type_id = ?";
            $params[] = $filters['subscription_type_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND i.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY i.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get items by subscription type
     */
    public function getBySubscriptionType($subscriptionTypeId) {
        $stmt = $this->db->prepare("
            SELECT i.*, st.name as subscription_type_name, st.color as subscription_type_color 
            FROM inventory_items i 
            LEFT JOIN inventory_subscription_types st ON i.subscription_type_id = st.id 
            WHERE i.subscription_type_id = ? 
            ORDER BY i.name ASC
        ");
        $stmt->execute([$subscriptionTypeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get item by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT i.*, st.name as subscription_type_name, st.color as subscription_type_color 
            FROM inventory_items i 
            LEFT JOIN inventory_subscription_types st ON i.subscription_type_id = st.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get assignment count for an item
     */
    public function getAssignmentCount($itemId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM inventory_assignments 
            WHERE item_id = ? AND is_active = 1 AND status = 'active'
        ");
        $stmt->execute([$itemId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Create new item
     */
    public function create($data) {
        $sql = "INSERT INTO inventory_items (
            name, code, subscription_type_id, description, license_key, 
            purchase_date, expiry_date, monthly_cost, annual_cost, 
            vendor_name, vendor_contact, max_users, max_teams, 
            assignment_type, status, is_active, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['code'],
            $data['subscription_type_id'],
            $data['description'] ?? null,
            $data['license_key'] ?? null,
            $data['purchase_date'] ?? null,
            $data['expiry_date'] ?? null,
            $data['monthly_cost'] ?? 0,
            $data['annual_cost'] ?? 0,
            $data['vendor_name'] ?? null,
            $data['vendor_contact'] ?? null,
            $data['max_users'] ?? null,
            $data['max_teams'] ?? null,
            $data['assignment_type'] ?? 'both',
            $data['status'] ?? 'active',
            $data['is_active'] ?? 1,
            $_SESSION['user_id'] ?? 1
        ]);
    }
    
    /**
     * Update item
     */
    public function update($id, $data) {
        $sql = "UPDATE inventory_items SET 
            name = ?, code = ?, subscription_type_id = ?, description = ?, 
            license_key = ?, purchase_date = ?, expiry_date = ?, 
            monthly_cost = ?, annual_cost = ?, vendor_name = ?, vendor_contact = ?, 
            max_users = ?, max_teams = ?, assignment_type = ?, 
            status = ?, is_active = ?, updated_by = ?, updated_at = NOW()
            WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['code'],
            $data['subscription_type_id'],
            $data['description'] ?? null,
            $data['license_key'] ?? null,
            $data['purchase_date'] ?? null,
            $data['expiry_date'] ?? null,
            $data['monthly_cost'] ?? 0,
            $data['annual_cost'] ?? 0,
            $data['vendor_name'] ?? null,
            $data['vendor_contact'] ?? null,
            $data['max_users'] ?? null,
            $data['max_teams'] ?? null,
            $data['assignment_type'] ?? 'both',
            $data['status'] ?? 'active',
            $data['is_active'] ?? 1,
            $_SESSION['user_id'] ?? 1,
            $id
        ]);
    }
    
    /**
     * Delete item
     */
    public function delete($id) {
        // First check if item has assignments
        if ($this->getAssignmentCount($id) > 0) {
            throw new Exception('Cannot delete item with active assignments.');
        }
        
        $stmt = $this->db->prepare("DELETE FROM inventory_items WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Check if code already exists
     */
    public function codeExists($code, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM inventory_items WHERE code = ?";
        $params = [$code];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get active items only
     */
    public function getActive() {
        $stmt = $this->db->prepare("
            SELECT i.*, st.name as subscription_type_name, st.color as subscription_type_color 
            FROM inventory_items i 
            LEFT JOIN inventory_subscription_types st ON i.subscription_type_id = st.id 
            WHERE i.is_active = 1 
            ORDER BY i.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get item statistics
     */
    public function getStats() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_items,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as status_active,
                COALESCE(SUM(CASE WHEN is_active = 1 THEN monthly_cost ELSE 0 END), 0) as total_monthly_cost,
                COALESCE(SUM(CASE WHEN is_active = 1 THEN annual_cost ELSE 0 END), 0) as total_annual_cost
            FROM inventory_items
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Remove all assignments for an item
     */
    public function removeAllAssignments($itemId) {
        $stmt = $this->db->prepare("
            UPDATE inventory_assignments 
            SET is_active = 0, status = 'removed', updated_at = NOW(), updated_by = ? 
            WHERE item_id = ? AND is_active = 1
        ");
        return $stmt->execute([$_SESSION['user_id'] ?? 1, $itemId]);
    }
}
?>
