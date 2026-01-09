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
        if ($db === null) {
            global $db;
            $this->db = $db;
        } else {
            $this->db = $db;
        }
    }
    
    /**
     * Get all inventory items with subscription type info
     * 
     * @param bool $includeLicenseKeys Whether to include license keys (requires permission)
     * @return array Inventory items
     */
    public function getAll($includeLicenseKeys = false) {
        // Check if user can view license keys
        $canViewLicenseKeys = has_permission('view_license_keys');
        
        if ($includeLicenseKeys && !$canViewLicenseKeys) {
            $includeLicenseKeys = false;
        }
        
        $licenseKeyField = $includeLicenseKeys ? 'i.license_key' : 'NULL as license_key';
        
        $stmt = $this->db->prepare("
            SELECT i.*, 
                   st.name as subscription_type_name,
                   st.icon as subscription_type_icon,
                   st.color as subscription_type_color,
                   {$licenseKeyField},
                   uc.name as created_by_name,
                   (i.max_users - i.current_users) as available_users,
                   (i.max_teams - i.current_teams) as available_teams,
                   CASE 
                       WHEN i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(NOW(), INTERVAL i.renewal_notification_days DAY) 
                       THEN 1 ELSE 0 
                   END as expiry_warning
            FROM inventory_items i
            JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
            LEFT JOIN users uc ON i.created_by = uc.id
            WHERE i.is_active = 1
            ORDER BY i.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get inventory item by ID
     * 
     * @param int $id Item ID
     * @param bool $includeLicenseKey Whether to include license key
     * @return array|false Item data or false
     */
    public function getById($id, $includeLicenseKey = false) {
        // Check if user can view license keys
        $canViewLicenseKeys = has_permission('view_license_keys');
        
        if ($includeLicenseKey && !$canViewLicenseKeys) {
            $includeLicenseKey = false;
        }
        
        $licenseKeyField = $includeLicenseKey ? 'i.license_key' : 'NULL as license_key';
        
        $stmt = $this->db->prepare("
            SELECT i.*, 
                   st.name as subscription_type_name,
                   st.code as subscription_type_code,
                   st.color as subscription_type_color,
                   st.icon as subscription_type_icon,
                   {$licenseKeyField},
                   uc.name as created_by_name,
                   uu.name as updated_by_name,
                   (i.max_users - i.current_users) as available_users,
                   (i.max_teams - i.current_teams) as available_teams,
                   CASE 
                       WHEN i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(NOW(), INTERVAL i.renewal_notification_days DAY) 
                       THEN 1 ELSE 0 
                   END as expiry_warning
            FROM inventory_items i
            JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
            LEFT JOIN users uc ON i.created_by = uc.id
            LEFT JOIN users uu ON i.updated_by = uu.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            // Get assignments
            $item['assignments'] = $this->getItemAssignments($id);
        }
        
        return $item;
    }
    
    /**
     * Get item assignments
     * 
     * @param int $itemId Item ID
     * @return array Assignments
     */
    public function getItemAssignments($itemId) {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   CASE 
                       WHEN a.assignee_type = 'user' THEN u.name 
                       WHEN a.assignee_type = 'team' THEN t.name 
                   END as assignee_name,
                   ab.name as assigned_by_name
            FROM inventory_assignments a
            LEFT JOIN users u ON a.assignee_type = 'user' AND a.assignee_id = u.id
            LEFT JOIN inventory_teams t ON a.assignee_type = 'team' AND a.assignee_id = t.id
            LEFT JOIN users ab ON a.assigned_by = ab.id
            WHERE a.item_id = ? AND a.is_active = 1
            ORDER BY a.assigned_at DESC
        ");
        $stmt->execute([$itemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new inventory item
     * 
     * @param array $data Item data
     * @return int|false New item ID or false
     */
    public function create($data) {
        // Check permissions
        if (!has_permission('add_inventory')) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO inventory_items 
                (name, code, subscription_type_id, description, license_key, purchase_date, 
                 expiry_date, monthly_cost, annual_cost, currency, vendor_name, vendor_contact,
                 max_users, max_teams, assignment_type, status, renewal_notification_days, 
                 auto_renewal, notes, is_active, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $data['name'],
                $data['code'],
                $data['subscription_type_id'],
                $data['description'] ?? null,
                $data['license_key'] ?? null,
                $data['purchase_date'] ?? null,
                $data['expiry_date'] ?? null,
                $data['monthly_cost'] ?? null,
                $data['annual_cost'] ?? null,
                $data['currency'] ?? 'USD',
                $data['vendor_name'] ?? null,
                $data['vendor_contact'] ?? null,
                $data['max_users'] ?? 1,
                $data['max_teams'] ?? 1,
                $data['assignment_type'] ?? 'individual',
                $data['status'] ?? 'active',
                $data['renewal_notification_days'] ?? 30,
                $data['auto_renewal'] ?? 0,
                $data['notes'] ?? null,
                $data['is_active'] ?? 1,
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                $newId = $this->db->lastInsertId();
                
                // Log activity
                $hasLicenseKey = !empty($data['license_key']) ? ' (with license key)' : '';
                $this->logActivity('created', $newId, "Created inventory item: {$data['name']}{$hasLicenseKey}");
                
                return $newId;
            }
            return false;
            
        } catch (PDOException $e) {
            error_log("InventoryItem create error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update inventory item
     * 
     * @param int $id Item ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function update($id, $data) {
        // Check permissions
        if (!has_permission('edit_inventory')) {
            return false;
        }
        
        try {
            // Get original item for comparison
            $originalItem = $this->getById($id, true);
            
            $stmt = $this->db->prepare("
                UPDATE inventory_items 
                SET name = ?, code = ?, subscription_type_id = ?, description = ?, 
                    license_key = ?, purchase_date = ?, expiry_date = ?, monthly_cost = ?, 
                    annual_cost = ?, currency = ?, vendor_name = ?, vendor_contact = ?,
                    max_users = ?, max_teams = ?, assignment_type = ?, status = ?, 
                    renewal_notification_days = ?, auto_renewal = ?, notes = ?, 
                    is_active = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $data['name'],
                $data['code'],
                $data['subscription_type_id'],
                $data['description'] ?? null,
                $data['license_key'] ?? null,
                $data['purchase_date'] ?? null,
                $data['expiry_date'] ?? null,
                $data['monthly_cost'] ?? null,
                $data['annual_cost'] ?? null,
                $data['currency'] ?? 'USD',
                $data['vendor_name'] ?? null,
                $data['vendor_contact'] ?? null,
                $data['max_users'] ?? 1,
                $data['max_teams'] ?? 1,
                $data['assignment_type'] ?? 'individual',
                $data['status'] ?? 'active',
                $data['renewal_notification_days'] ?? 30,
                $data['auto_renewal'] ?? 0,
                $data['notes'] ?? null,
                $data['is_active'] ?? 1,
                $_SESSION['user_id'],
                $id
            ]);
            
            if ($result) {
                // Log activity with changes
                $changes = $this->getChanges($originalItem, $data);
                $this->logActivity('updated', $id, "Updated inventory item: {$data['name']}. Changes: {$changes}");
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("InventoryItem update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete inventory item (soft delete)
     * 
     * @param int $id Item ID
     * @return bool Success status
     */
    public function delete($id) {
        // Check permissions
        if (!has_permission('delete_inventory')) {
            return false;
        }
        
        try {
            $this->db->beginTransaction();
            
            $item = $this->getById($id);
            
            // Deactivate all assignments first
            $stmt = $this->db->prepare("
                UPDATE inventory_assignments 
                SET is_active = 0, unassigned_at = NOW(), unassigned_by = ?, updated_at = NOW()
                WHERE item_id = ? AND is_active = 1
            ");
            $stmt->execute([$_SESSION['user_id'], $id]);
            
            // Soft delete the item
            $stmt = $this->db->prepare("
                UPDATE inventory_items 
                SET is_active = 0, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$_SESSION['user_id'], $id]);
            
            if ($result) {
                $this->db->commit();
                
                // Log activity
                $this->logActivity('deleted', $id, "Deleted inventory item: {$item['name']}");
                
                return true;
            }
            
            $this->db->rollback();
            return false;
            
        } catch (PDOException $e) {
            $this->db->rollback();
            error_log("InventoryItem delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get items by subscription type
     * 
     * @param int $subscriptionTypeId Subscription type ID
     * @return array Items
     */
    public function getBySubscriptionType($subscriptionTypeId) {
        $stmt = $this->db->prepare("
            SELECT * FROM inventory_items 
            WHERE subscription_type_id = ? AND is_active = 1
            ORDER BY name ASC
        ");
        $stmt->execute([$subscriptionTypeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get items expiring soon
     * 
     * @param int $days Days threshold
     * @return array Expiring items
     */
    public function getExpiringSoon($days = 30) {
        $stmt = $this->db->prepare("
            SELECT i.*, st.name as subscription_type_name
            FROM inventory_items i
            JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
            WHERE i.is_active = 1 
                AND i.expiry_date IS NOT NULL 
                AND i.expiry_date <= DATE_ADD(NOW(), INTERVAL ? DAY)
                AND i.expiry_date >= NOW()
            ORDER BY i.expiry_date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get capacity statistics
     * 
     * @return array Capacity stats
     */
    public function getCapacityStats() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_items,
                SUM(max_users) as total_max_users,
                SUM(current_users) as total_current_users,
                SUM(max_teams) as total_max_teams,
                SUM(current_teams) as total_current_teams,
                SUM(CASE WHEN current_users >= max_users THEN 1 ELSE 0 END) as full_user_items,
                SUM(CASE WHEN current_teams >= max_teams THEN 1 ELSE 0 END) as full_team_items
            FROM inventory_items 
            WHERE is_active = 1
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get cost summary
     * 
     * @return array Cost summary
     */
    public function getCostSummary() {
        $stmt = $this->db->prepare("
            SELECT 
                currency,
                SUM(monthly_cost) as total_monthly_cost,
                SUM(annual_cost) as total_annual_cost,
                COUNT(*) as item_count
            FROM inventory_items 
            WHERE is_active = 1 AND status = 'active'
            GROUP BY currency
            ORDER BY currency
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get changes between original and new data
     * 
     * @param array $original Original data
     * @param array $new New data
     * @return string Changes description
     */
    private function getChanges($original, $new) {
        $changes = [];
        $trackFields = ['name', 'monthly_cost', 'annual_cost', 'max_users', 'max_teams', 'status'];
        
        foreach ($trackFields as $field) {
            if (isset($new[$field]) && $original[$field] != $new[$field]) {
                $changes[] = "{$field}: {$original[$field]} → {$new[$field]}";
            }
        }
        
        // Check license key changes (but don't log the actual key)
        if (isset($new['license_key']) && $original['license_key'] != $new['license_key']) {
            if (empty($original['license_key']) && !empty($new['license_key'])) {
                $changes[] = "license_key: added";
            } elseif (!empty($original['license_key']) && empty($new['license_key'])) {
                $changes[] = "license_key: removed";
            } elseif (!empty($original['license_key']) && !empty($new['license_key'])) {
                $changes[] = "license_key: updated";
            }
        }
        
        return empty($changes) ? 'No significant changes' : implode(', ', $changes);
    }
    
    /**
     * Log activity to activity_logs table
     * 
     * @param string $action Action performed
     * @param int $entityId Entity ID
     * @param string $description Description
     */
    private function logActivity($action, $entityId, $description) {
        global $db;
        
        try {
            $stmt = $db->prepare("
                INSERT INTO activity_logs 
                (user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $action,
                'inventory_item',
                $entityId,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (PDOException $e) {
            error_log("Activity log error: " . $e->getMessage());
        }
    }
    
    /**
     * Check if item code already exists
     * 
     * @param string $code Item code to check
     * @param int|null $excludeId Item ID to exclude from check (for updates)
     * @return bool True if code exists, false otherwise
     */
    public function codeExists($code, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) FROM inventory_items WHERE code = ? AND is_active = 1";
            $params = [$code];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (PDOException $e) {
            error_log("InventoryItem codeExists error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cost breakdown by subscription type
     * 
     * @return array Cost data by subscription type
     */
    public function getCostBySubscriptionType() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    st.name as subscription_type,
                    st.color,
                    COUNT(ii.id) as item_count,
                    SUM(COALESCE(ii.monthly_cost, 0)) as total_monthly_cost,
                    SUM(COALESCE(ii.annual_cost, 0)) as total_annual_cost,
                    ii.currency
                FROM inventory_subscription_types st
                LEFT JOIN inventory_items ii ON st.id = ii.subscription_type_id AND ii.is_active = 1
                WHERE st.is_active = 1
                GROUP BY st.id, ii.currency
                ORDER BY st.name, ii.currency
            ");
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("InventoryItem getCostBySubscriptionType error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get cost trends over time
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Cost trends data
     */
    public function getCostTrends($startDate, $endDate) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    SUM(COALESCE(monthly_cost, 0)) as monthly_cost,
                    SUM(COALESCE(annual_cost, 0)) as annual_cost,
                    COUNT(*) as items_added,
                    currency
                FROM inventory_items
                WHERE created_at BETWEEN ? AND ? 
                AND is_active = 1
                GROUP BY DATE_FORMAT(created_at, '%Y-%m'), currency
                ORDER BY month ASC
            ");
            
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("InventoryItem getCostTrends error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get capacity breakdown by subscription type
     * 
     * @return array Capacity data by subscription type
     */
    public function getCapacityBySubscriptionType() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    st.name as subscription_type,
                    st.color,
                    COUNT(ii.id) as total_items,
                    SUM(CASE WHEN ii.status = 'active' THEN 1 ELSE 0 END) as active_items,
                    SUM(COALESCE(ii.max_users, 0)) as total_user_capacity,
                    SUM(COALESCE(ii.max_teams, 0)) as total_team_capacity,
                    COUNT(ia.id) as current_assignments
                FROM inventory_subscription_types st
                LEFT JOIN inventory_items ii ON st.id = ii.subscription_type_id AND ii.is_active = 1
                LEFT JOIN inventory_assignments ia ON ii.id = ia.item_id AND ia.is_active = 1
                WHERE st.is_active = 1
                GROUP BY st.id
                ORDER BY st.name
            ");
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("InventoryItem getCapacityBySubscriptionType error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get utilization trends over time
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Utilization trends data
     */
    public function getUtilizationTrends($startDate, $endDate) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(ia.created_at, '%Y-%m') as month,
                    COUNT(DISTINCT ia.item_id) as items_assigned,
                    COUNT(ia.id) as total_assignments,
                    COUNT(DISTINCT CASE WHEN ia.assignee_type = 'user' THEN ia.assignee_id END) as unique_users,
                    COUNT(DISTINCT CASE WHEN ia.assignee_type = 'team' THEN ia.assignee_id END) as unique_teams
                FROM inventory_assignments ia
                WHERE ia.created_at BETWEEN ? AND ? 
                AND ia.is_active = 1
                GROUP BY DATE_FORMAT(ia.created_at, '%Y-%m')
                ORDER BY month ASC
            ");
            
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("InventoryItem getUtilizationTrends error: " . $e->getMessage());
            return [];
        }
    }
}
