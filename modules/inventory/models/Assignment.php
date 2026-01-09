<?php
/**
 * AbroadWorks Management System - Inventory Assignment Model
 *
 * @author System Generated
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class InventoryAssignment {
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
     * Get all active assignments
     * 
     * @return array Assignments
     */
    public function getAllActive() {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   i.name as item_name,
                   i.code as item_code,
                   st.name as subscription_type_name,
                   CASE 
                       WHEN a.assignee_type = 'user' THEN u.name 
                       WHEN a.assignee_type = 'team' THEN t.name 
                   END as assignee_name,
                   CASE 
                       WHEN a.assignee_type = 'user' THEN u.email 
                       WHEN a.assignee_type = 'team' THEN t.department 
                   END as assignee_detail,
                   ab.name as assigned_by_name
            FROM inventory_assignments a
            JOIN inventory_items i ON a.item_id = i.id
            JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
            LEFT JOIN users u ON a.assignee_type = 'user' AND a.assignee_id = u.id
            LEFT JOIN inventory_teams t ON a.assignee_type = 'team' AND a.assignee_id = t.id
            LEFT JOIN users ab ON a.assigned_by = ab.id
            WHERE a.is_active = 1 AND a.status = 'active'
            ORDER BY a.assigned_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all assignments (including inactive)
     * 
     * @return array All assignments
     */
    public function getAll() {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   i.name as item_name,
                   i.code as item_code,
                   st.name as subscription_type_name,
                   st.color as subscription_type_color,
                   st.icon as subscription_type_icon,
                   CASE 
                       WHEN a.assignee_type = 'user' THEN u.name 
                       WHEN a.assignee_type = 'team' THEN t.name 
                   END as assignee_name,
                   CASE 
                       WHEN a.assignee_type = 'user' THEN u.email 
                       WHEN a.assignee_type = 'team' THEN t.department 
                   END as assignee_detail,
                   ab.name as assigned_by_name
            FROM inventory_assignments a
            JOIN inventory_items i ON a.item_id = i.id
            JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
            LEFT JOIN users u ON a.assignee_type = 'user' AND a.assignee_id = u.id
            LEFT JOIN inventory_teams t ON a.assignee_type = 'team' AND a.assignee_id = t.id
            LEFT JOIN users ab ON a.assigned_by = ab.id
            WHERE a.is_active = 1
            ORDER BY a.assigned_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get assignment by ID
     * 
     * @param int $id Assignment ID
     * @return array|false Assignment data or false
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   i.name as item_name,
                   i.code as item_code,
                   i.assignment_type as item_assignment_type,
                   CASE 
                       WHEN a.assignee_type = 'user' THEN u.name 
                       WHEN a.assignee_type = 'team' THEN t.name 
                   END as assignee_name,
                   ab.name as assigned_by_name,
                   ub.name as unassigned_by_name
            FROM inventory_assignments a
            JOIN inventory_items i ON a.item_id = i.id
            LEFT JOIN users u ON a.assignee_type = 'user' AND a.assignee_id = u.id
            LEFT JOIN inventory_teams t ON a.assignee_type = 'team' AND a.assignee_id = t.id
            LEFT JOIN users ab ON a.assigned_by = ab.id
            LEFT JOIN users ub ON a.unassigned_by = ub.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get assignments by item ID
     * 
     * @param int $itemId Item ID
     * @return array Assignments
     */
    public function getByItemId($itemId) {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   CASE 
                       WHEN a.assignee_type = 'user' THEN u.name 
                       WHEN a.assignee_type = 'team' THEN t.name 
                   END as assignee_name,
                   ab.name as assigned_by_name,
                   a.assignee_type as assignment_type
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
     * Get assignments by assignee
     * 
     * @param string $assigneeType 'user' or 'team'
     * @param int $assigneeId Assignee ID
     * @return array Assignments
     */
    public function getByAssignee($assigneeType, $assigneeId) {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   i.name as item_name,
                   i.code as item_code,
                   st.name as subscription_type_name,
                   ab.name as assigned_by_name
            FROM inventory_assignments a
            JOIN inventory_items i ON a.item_id = i.id
            JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
            LEFT JOIN users ab ON a.assigned_by = ab.id
            WHERE a.assignee_type = ? AND a.assignee_id = ? AND a.is_active = 1 AND a.status = 'active'
            ORDER BY a.assigned_at DESC
        ");
        $stmt->execute([$assigneeType, $assigneeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get assignments by team ID
     * 
     * @param int $teamId Team ID
     * @return array Team assignments with item details
     */
    public function getByTeamId($teamId) {
        $stmt = $this->db->prepare("
            SELECT a.*,
                   i.name as item_name,
                   i.code as item_code,
                   st.name as subscription_type_name,
                   st.color as subscription_type_color,
                   st.icon as subscription_type_icon,
                   ab.name as assigned_by_name
            FROM inventory_assignments a
            JOIN inventory_items i ON a.item_id = i.id
            JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
            LEFT JOIN users ab ON a.assigned_by = ab.id
            WHERE a.assignee_type = 'team' AND a.assignee_id = ? AND a.is_active = 1
            ORDER BY a.assigned_at DESC
        ");
        $stmt->execute([$teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new assignment
     * 
     * @param array $data Assignment data
     * @return int|false New assignment ID or false
     */
    public function create($data) {
        // Check permissions
        if (!has_permission('edit_inventory')) {
            return false;
        }
        
        try {
            $this->db->beginTransaction();
            
            // Validate assignment is possible
            if (!$this->canAssign($data['item_id'], $data['assignee_type'])) {
                $this->db->rollback();
                return false;
            }
            
            // Check if already assigned
            if ($this->isAlreadyAssigned($data['item_id'], $data['assignee_type'], $data['assignee_id'])) {
                $this->db->rollback();
                return false;
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO inventory_assignments 
                (item_id, assignee_type, assignee_id, assigned_by, assigned_at, 
                 usage_start_date, usage_end_date, notes, status, is_active, created_by, created_at) 
                VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, 1, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $data['item_id'],
                $data['assignee_type'],
                $data['assignee_id'],
                $_SESSION['user_id'],
                $data['usage_start_date'] ?? null,
                $data['usage_end_date'] ?? null,
                $data['notes'] ?? null,
                $data['status'] ?? 'active',
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                $newId = $this->db->lastInsertId();
                
                $this->db->commit();
                
                // Log activity
                $assigneeName = $this->getAssigneeName($data['assignee_type'], $data['assignee_id']);
                $itemName = $this->getItemName($data['item_id']);
                $this->logActivity('created', $newId, "Assigned {$itemName} to {$data['assignee_type']} {$assigneeName}");
                
                return $newId;
            }
            
            $this->db->rollback();
            return false;
            
        } catch (PDOException $e) {
            $this->db->rollback();
            error_log("Assignment create error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update assignment
     * 
     * @param int $id Assignment ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function update($id, $data) {
        // Check permissions
        if (!has_permission('edit_inventory')) {
            return false;
        }
        
        try {
            $original = $this->getById($id);
            
            $stmt = $this->db->prepare("
                UPDATE inventory_assignments 
                SET usage_start_date = ?, usage_end_date = ?, notes = ?, 
                    status = ?, updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $data['usage_start_date'] ?? null,
                $data['usage_end_date'] ?? null,
                $data['notes'] ?? null,
                $data['status'] ?? 'active',
                $_SESSION['user_id'],
                $id
            ]);
            
            if ($result) {
                // Log activity
                $changes = $this->getAssignmentChanges($original, $data);
                $this->logActivity('updated', $id, "Updated assignment for {$original['item_name']} to {$original['assignee_name']}. Changes: {$changes}");
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Assignment update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unassign (soft delete assignment)
     * 
     * @param int $id Assignment ID
     * @return bool Success status
     */
    public function unassign($id) {
        // Check permissions
        if (!has_permission('edit_inventory')) {
            return false;
        }
        
        try {
            $assignment = $this->getById($id);
            
            $stmt = $this->db->prepare("
                UPDATE inventory_assignments 
                SET is_active = 0, unassigned_at = NOW(), unassigned_by = ?, 
                    status = 'inactive', updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([$_SESSION['user_id'], $id]);
            
            if ($result) {
                // Log activity
                $this->logActivity('deleted', $id, "Unassigned {$assignment['item_name']} from {$assignment['assignee_name']}");
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Assignment unassign error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if item can be assigned to assignee type
     * 
     * @param int $itemId Item ID
     * @param string $assigneeType 'user' or 'team'
     * @return bool Can assign status
     */
    public function canAssign($itemId, $assigneeType) {
        $stmt = $this->db->prepare("
            SELECT assignment_type, max_users, current_users, max_teams, current_teams
            FROM inventory_items 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            return false;
        }
        
        // Check assignment type compatibility
        if ($item['assignment_type'] === 'individual' && $assigneeType !== 'user') {
            return false;
        }
        
        if ($item['assignment_type'] === 'team' && $assigneeType !== 'team') {
            return false;
        }
        
        // 'both' assignment_type allows both user and team assignments
        
        // Check capacity
        if ($assigneeType === 'user' && $item['current_users'] >= $item['max_users']) {
            return false;
        }
        
        if ($assigneeType === 'team' && $item['current_teams'] >= $item['max_teams']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if item is already assigned to assignee
     * 
     * @param int $itemId Item ID
     * @param string $assigneeType Assignee type
     * @param int $assigneeId Assignee ID
     * @return bool Already assigned status
     */
    public function isAlreadyAssigned($itemId, $assigneeType, $assigneeId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM inventory_assignments 
            WHERE item_id = ? AND assignee_type = ? AND assignee_id = ? 
                AND is_active = 1 AND status = 'active'
        ");
        $stmt->execute([$itemId, $assigneeType, $assigneeId]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get available items for assignment
     * 
     * @param string $assigneeType 'user' or 'team'
     * @return array Available items
     */
    public function getAvailableItems($assigneeType) {
        $condition = $assigneeType === 'user' ? 'current_users < max_users' : 'current_teams < max_teams';
        $assignmentTypeCondition = $assigneeType === 'user' ? "assignment_type IN ('individual', 'both')" : "assignment_type IN ('team', 'both')";
        
        $stmt = $this->db->prepare("
            SELECT i.*, st.name as subscription_type_name,
                   (i.max_users - i.current_users) as available_users,
                   (i.max_teams - i.current_teams) as available_teams
            FROM inventory_items i
            JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
            WHERE i.is_active = 1 AND i.status = 'active' 
                AND {$condition} AND {$assignmentTypeCondition}
            ORDER BY i.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get assignment statistics
     * 
     * @return array Assignment stats
     */
    public function getAssignmentStats() {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_assignments,
                SUM(CASE WHEN assignee_type = 'user' THEN 1 ELSE 0 END) as user_assignments,
                SUM(CASE WHEN assignee_type = 'team' THEN 1 ELSE 0 END) as team_assignments,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_assignments,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_assignments
            FROM inventory_assignments 
            WHERE is_active = 1
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get assignee name helper
     * 
     * @param string $assigneeType Assignee type
     * @param int $assigneeId Assignee ID
     * @return string Assignee name
     */
    private function getAssigneeName($assigneeType, $assigneeId) {
        if ($assigneeType === 'user') {
            $stmt = $this->db->prepare("SELECT name FROM users WHERE id = ?");
        } else {
            $stmt = $this->db->prepare("SELECT name FROM inventory_teams WHERE id = ?");
        }
        $stmt->execute([$assigneeId]);
        return $stmt->fetchColumn() ?: 'Unknown';
    }
    
    /**
     * Get item name helper
     * 
     * @param int $itemId Item ID
     * @return string Item name
     */
    private function getItemName($itemId) {
        $stmt = $this->db->prepare("SELECT name FROM inventory_items WHERE id = ?");
        $stmt->execute([$itemId]);
        return $stmt->fetchColumn() ?: 'Unknown';
    }
    
    /**
     * Get assignment changes
     * 
     * @param array $original Original data
     * @param array $new New data
     * @return string Changes description
     */
    private function getAssignmentChanges($original, $new) {
        $changes = [];
        $trackFields = ['usage_start_date', 'usage_end_date', 'status', 'notes'];
        
        foreach ($trackFields as $field) {
            if (isset($new[$field]) && $original[$field] != $new[$field]) {
                $changes[] = "{$field}: {$original[$field]} → {$new[$field]}";
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
                'inventory_assignment',
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
     * Delete assignment (soft delete)
     * 
     * @param int $id Assignment ID
     * @return bool Success status
     */
    public function delete($id) {
        try {
            // Get original assignment for logging
            $original = $this->getById($id);
            if (!$original) {
                return false;
            }
            
            $stmt = $this->db->prepare("
                UPDATE inventory_assignments 
                SET is_active = 0, status = 'removed', updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([$_SESSION['user_id'], $id]);
            
            if ($result) {
                $this->logActivity('delete', $id, 
                    "Assignment deleted: {$original['item_name']} from {$original['assignee_type']} {$original['assignee_name']}");
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("InventoryAssignment delete error: " . $e->getMessage());
            return false;
        }
    }
}
