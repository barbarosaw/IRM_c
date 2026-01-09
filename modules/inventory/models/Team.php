<?php
/**
 * AbroadWorks Management System - Inventory Team Model
 *
 * @author System Generated
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class InventoryTeam {
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
     * Get all active teams
     * 
     * @return array Teams
     */
    public function getAllActive() {
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   m.name as manager_name,
                   COUNT(tm.id) as member_count
            FROM inventory_teams t
            LEFT JOIN users m ON t.manager_id = m.id
            LEFT JOIN inventory_team_members tm ON t.id = tm.team_id AND tm.is_active = 1
            WHERE t.is_active = 1 
            GROUP BY t.id
            ORDER BY t.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all teams with statistics
     * 
     * @return array Teams with member count and assignment count
     */
    public function getAll() {
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   m.name as manager_name,
                   uc.name as created_by_name,
                   COUNT(DISTINCT tm.id) as member_count,
                   COUNT(DISTINCT ia.id) as assignment_count
            FROM inventory_teams t
            LEFT JOIN users m ON t.manager_id = m.id
            LEFT JOIN users uc ON t.created_by = uc.id
            LEFT JOIN inventory_team_members tm ON t.id = tm.team_id AND tm.is_active = 1
            LEFT JOIN inventory_assignments ia ON t.id = ia.assignee_id AND ia.assignee_type = 'team' AND ia.status = 'active'
            WHERE t.is_active = 1 
            GROUP BY t.id
            ORDER BY t.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get team by ID with members
     * 
     * @param int $id Team ID
     * @return array|false Team data with members or false
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   m.name as manager_name,
                   uc.name as created_by_name
            FROM inventory_teams t
            LEFT JOIN users m ON t.manager_id = m.id
            LEFT JOIN users uc ON t.created_by = uc.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($team) {
            $team['members'] = $this->getTeamMembers($id);
        }
        
        return $team;
    }
    
    /**
     * Get team members
     * 
     * @param int $teamId Team ID
     * @return array Team members
     */
    public function getTeamMembers($teamId) {
        $stmt = $this->db->prepare("
            SELECT tm.*, 
                   u.name as user_name, 
                   u.email as user_email,
                   tm.created_at as joined_at,
                   CASE WHEN tm.is_lead = 1 THEN 'manager' ELSE 'member' END as role
            FROM inventory_team_members tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.team_id = ? AND tm.is_active = 1
            ORDER BY tm.is_lead DESC, u.name ASC
        ");
        $stmt->execute([$teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Create new team
     * 
     * @param array $data Team data
     * @return int|false New team ID or false
     */
    public function create($data) {
        // Check permissions
        if (!has_permission('add_inventory')) {
            return false;
        }
        
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("
                INSERT INTO inventory_teams 
                (name, code, description, manager_id, department, is_active, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $data['name'],
                $data['code'],
                $data['description'] ?? null,
                $data['manager_id'] ?? null,
                $data['department'] ?? null,
                $data['is_active'] ?? 1,
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                $newId = $this->db->lastInsertId();
                
                // Add manager as team member if specified
                if (!empty($data['manager_id'])) {
                    $this->addMember($newId, $data['manager_id'], 'manager');
                }
                
                $this->db->commit();
                
                // Log activity
                $this->logActivity('created', $newId, "Created team: {$data['name']}");
                
                return $newId;
            }
            
            $this->db->rollback();
            return false;
            
        } catch (PDOException $e) {
            $this->db->rollback();
            error_log("Team create error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete team (soft delete)
     * 
     * @param int $id Team ID
     * @return bool Success status
     */
    public function delete($id) {
        try {
            $this->db->beginTransaction();
            
            // Get team name for logging
            $stmt = $this->db->prepare("SELECT name FROM inventory_teams WHERE id = ?");
            $stmt->execute([$id]);
            $teamName = $stmt->fetchColumn();
            
            if (!$teamName) {
                $this->db->rollBack();
                return false;
            }
            
            // Soft delete the team
            $stmt = $this->db->prepare("UPDATE inventory_teams SET is_active = 0 WHERE id = ?");
            $stmt->execute([$id]);
            
            // Deactivate all team members
            $stmt = $this->db->prepare("UPDATE inventory_team_members SET is_active = 0 WHERE team_id = ?");
            $stmt->execute([$id]);
            
            // Deactivate all team assignments
            $stmt = $this->db->prepare("UPDATE inventory_assignments SET status = 'cancelled' WHERE team_id = ? AND assignment_type = 'team'");
            $stmt->execute([$id]);
            
            $this->db->commit();
            
            // Log activity
            $this->logActivity('deleted', $id, "Deleted team: {$teamName}");
            
            return true;
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("InventoryTeam delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add member to team
     * 
     * @param int $teamId Team ID
     * @param int $userId User ID
     * @param string $role Member role
     * @return bool Success status
     */
    public function addMember($teamId, $userId, $role = 'member') {
        // Check permissions
        if (!has_permission('edit_inventory')) {
            return false;
        }
        
        try {
            // Check if user is already a member
            $stmt = $this->db->prepare("
                SELECT id FROM inventory_team_members 
                WHERE team_id = ? AND user_id = ? AND is_active = 1
            ");
            $stmt->execute([$teamId, $userId]);
            
            if ($stmt->fetch()) {
                return false; // Already a member
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO inventory_team_members 
                (team_id, user_id, role, joined_at, is_active, created_by, created_at) 
                VALUES (?, ?, ?, NOW(), 1, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $teamId,
                $userId,
                $role,
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                // Get user and team names for logging
                $user = $this->getUserById($userId);
                $team = $this->getById($teamId);
                
                $this->logActivity('updated', $teamId, "Added {$user['name']} to team {$team['name']} as {$role}");
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Team add member error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove member from team
     * 
     * @param int $teamId Team ID
     * @param int $userId User ID
     * @return bool Success status
     */
    public function removeMember($teamId, $userId) {
        // Check permissions
        if (!has_permission('edit_inventory')) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("
                UPDATE inventory_team_members 
                SET is_active = 0, left_at = NOW(), updated_by = ?, updated_at = NOW()
                WHERE team_id = ? AND user_id = ? AND is_active = 1
            ");
            
            $result = $stmt->execute([$_SESSION['user_id'], $teamId, $userId]);
            
            if ($result) {
                // Get user and team names for logging
                $user = $this->getUserById($userId);
                $team = $this->getById($teamId);
                
                $this->logActivity('updated', $teamId, "Removed {$user['name']} from team {$team['name']}");
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("Team remove member error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user by ID (helper method)
     * 
     * @param int $userId User ID
     * @return array|false User data or false
     */
    private function getUserById($userId) {
        $stmt = $this->db->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get available users for team assignment
     * 
     * @param int $teamId Team ID (to exclude current members)
     * @return array Available users
     */
    public function getAvailableUsers($teamId = null) {
        $query = "
            SELECT u.id, u.name, u.email
            FROM users u
            WHERE u.is_active = 1
        ";
        
        $params = [];
        if ($teamId) {
            $query .= " AND u.id NOT IN (
                SELECT tm.user_id 
                FROM inventory_team_members tm 
                WHERE tm.team_id = ? AND tm.is_active = 1
            )";
            $params[] = $teamId;
        }
        
        $query .= " ORDER BY u.name ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                'inventory_team',
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
     * Check if team code already exists
     * 
     * @param string $code Team code to check
     * @param int|null $excludeId Team ID to exclude from check (for updates)
     * @return bool True if code exists, false otherwise
     */
    public function codeExists($code, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) FROM inventory_teams WHERE code = ? AND is_active = 1";
            $params = [$code];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (PDOException $e) {
            error_log("InventoryTeam codeExists error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if team name already exists
     * 
     * @param string $name Team name to check
     * @param int|null $excludeId Team ID to exclude from check (for updates)
     * @return bool True if name exists, false otherwise
     */
    public function nameExists($name, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) FROM inventory_teams WHERE name = ? AND is_active = 1";
            $params = [$name];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (PDOException $e) {
            error_log("InventoryTeam nameExists error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update team
     * 
     * @param int $id Team ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("
                UPDATE inventory_teams 
                SET name = ?, code = ?, description = ?, department = ?, 
                    manager_id = ?, budget_limit = ?, is_active = ?, 
                    updated_by = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $data['name'],
                $data['code'] ?? null,
                $data['description'] ?? null,
                $data['department'] ?? null,
                $data['manager_id'] ?? null,
                $data['budget_limit'] ?? null,
                $data['is_active'] ?? 1,
                $_SESSION['user_id'],
                $id
            ]);
            
            if ($result) {
                $this->logActivity('updated', $id, "Updated team: {$data['name']}");
            }
            
            return $result;
            
        } catch (PDOException $e) {
            error_log("InventoryTeam update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get team members
     * 
     * @param int $teamId Team ID
     * @return array Team members
     */
    public function getMembers($teamId) {
        try {
            $stmt = $this->db->prepare("
                SELECT tm.*, u.name, u.email, u.avatar
                FROM inventory_team_members tm
                JOIN users u ON tm.user_id = u.id
                WHERE tm.team_id = ? AND tm.is_active = 1
                ORDER BY tm.role DESC, u.name
            ");
            
            $stmt->execute([$teamId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("InventoryTeam getMembers error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update team members
     * 
     * @param int $teamId Team ID
     * @param array $memberIds Array of user IDs
     * @return bool Success status
     */
    public function updateMembers($teamId, $memberIds) {
        try {
            $this->db->beginTransaction();
            
            // Remove existing members
            $stmt = $this->db->prepare("
                UPDATE inventory_team_members 
                SET is_active = 0, updated_at = NOW() 
                WHERE team_id = ?
            ");
            $stmt->execute([$teamId]);
            
            // Add new members
            if (!empty($memberIds)) {
                $stmt = $this->db->prepare("
                    INSERT INTO inventory_team_members 
                    (team_id, user_id, role, added_by, created_at) 
                    VALUES (?, ?, 'member', ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    is_active = 1, updated_at = NOW()
                ");
                
                foreach ($memberIds as $userId) {
                    $stmt->execute([$teamId, $userId, $_SESSION['user_id']]);
                }
            }
            
            $this->db->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->db->rollback();
            error_log("InventoryTeam updateMembers error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Alias for getAllActive() for consistency
     */
    public function getActive() {
        return $this->getAllActive();
    }
}
