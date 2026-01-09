<?php
/**
 * AbroadWorks Management System - Inventory Subscription Type Model
 *
 * @author System Generated
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class InventorySubscriptionType {
    private $db;
    
    /**
     * Constructor
     */
    public function __construct($db = null) {
        global $database;
        $this->db = $db ?? $database;
    }
    
    /**
     * Get all subscription types
     */
    public function getAll($filters = []) {
        $sql = "SELECT * FROM inventory_subscription_types WHERE 1=1";
        $params = [];
        
        if (!empty($filters['is_active'])) {
            $sql .= " AND is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        if (!empty($filters['category'])) {
            $sql .= " AND category = ?";
            $params[] = $filters['category'];
        }
        
        $sql .= " ORDER BY name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get subscription type by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM inventory_subscription_types WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new subscription type
     */
    public function create($data) {
        $sql = "INSERT INTO inventory_subscription_types (
            name, description, icon, color, category, features, 
            is_active, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['icon'] ?? null,
            $data['color'] ?? null,
            $data['category'] ?? 'general',
            $data['features'] ?? null,
            $data['is_active'] ?? 1,
            $_SESSION['user_id'] ?? 1
        ]);
    }
    
    /**
     * Update subscription type
     */
    public function update($id, $data) {
        $sql = "UPDATE inventory_subscription_types SET 
            name = ?, description = ?, icon = ?, color = ?, 
            category = ?, features = ?, is_active = ?, 
            updated_by = ?, updated_at = NOW()
            WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['icon'] ?? null,
            $data['color'] ?? null,
            $data['category'] ?? 'general',
            $data['features'] ?? null,
            $data['is_active'] ?? 1,
            $_SESSION['user_id'] ?? 1,
            $id
        ]);
    }
    
    /**
     * Delete subscription type
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM inventory_subscription_types WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Get all active subscription types
     */
    public function getAllActive() {
        return $this->getAll(['is_active' => 1]);
    }
    
    /**
     * Get subscription type statistics
     */
    public function getStats() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_types,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_types,
                COUNT(DISTINCT category) as categories_count
            FROM inventory_subscription_types
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get categories list
     */
    public function getCategories() {
        $stmt = $this->db->prepare("
            SELECT DISTINCT category, COUNT(*) as count 
            FROM inventory_subscription_types 
            WHERE is_active = 1 
            GROUP BY category 
            ORDER BY category
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all subscription types with usage statistics
     */
    public function getAllWithStats() {
        $stmt = $this->db->prepare("
            SELECT st.*, 
                   COALESCE(items_stats.items_count, 0) as items_count,
                   COALESCE(items_stats.active_items_count, 0) as active_items_count,
                   COALESCE(assignment_stats.assignments_count, 0) as assignments_count,
                   COALESCE(items_stats.total_monthly_cost, 0) as total_monthly_cost,
                   COALESCE(items_stats.total_annual_cost, 0) as total_annual_cost
            FROM inventory_subscription_types st
            LEFT JOIN (
                SELECT subscription_type_id,
                       COUNT(*) as items_count,
                       COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_items_count,
                       COALESCE(SUM(CASE WHEN is_active = 1 THEN monthly_cost ELSE 0 END), 0) as total_monthly_cost,
                       COALESCE(SUM(CASE WHEN is_active = 1 THEN annual_cost ELSE 0 END), 0) as total_annual_cost
                FROM inventory_items
                WHERE subscription_type_id IS NOT NULL
                GROUP BY subscription_type_id
            ) items_stats ON st.id = items_stats.subscription_type_id
            LEFT JOIN (
                SELECT i.subscription_type_id,
                       COUNT(DISTINCT a.id) as assignments_count
                FROM inventory_items i
                LEFT JOIN inventory_assignments a ON i.id = a.item_id AND a.is_active = 1 AND a.status = 'active'
                WHERE i.subscription_type_id IS NOT NULL
                GROUP BY i.subscription_type_id
            ) assignment_stats ON st.id = assignment_stats.subscription_type_id
            WHERE st.is_active = 1
            ORDER BY st.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if name already exists
     */
    public function nameExists($name, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM inventory_subscription_types WHERE name = ?";
        $params = [$name];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
}