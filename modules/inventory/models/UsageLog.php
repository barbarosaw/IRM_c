<?php
/**
 * AbroadWorks Management System - Inventory Usage Log Model
 *
 * @author System Generated
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class InventoryUsageLog {
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
     * Log usage activity
     * 
     * @param array $data Usage log data
     * @return int|false New log ID or false
     */
    public function logUsage($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO inventory_usage_logs 
                (item_id, assignment_id, user_id, action, description, usage_duration_minutes,
                 session_id, ip_address, user_agent, metadata, logged_at, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $data['item_id'],
                $data['assignment_id'] ?? null,
                $data['user_id'] ?? $_SESSION['user_id'],
                $data['action'],
                $data['description'] ?? null,
                $data['usage_duration_minutes'] ?? null,
                $data['session_id'] ?? session_id(),
                $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'],
                $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'],
                isset($data['metadata']) ? json_encode($data['metadata']) : null,
                $data['logged_at'] ?? date('Y-m-d H:i:s'),
            ]);
            
            if ($result) {
                return $this->db->lastInsertId();
            }
            return false;
            
        } catch (PDOException $e) {
            error_log("Usage log error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get usage logs for an item
     * 
     * @param int $itemId Item ID
     * @param int $limit Limit records
     * @return array Usage logs
     */
    public function getByItemId($itemId, $limit = 100) {
        $stmt = $this->db->prepare("
            SELECT ul.*, 
                   u.name as user_name,
                   u.email as user_email,
                   i.name as item_name
            FROM inventory_usage_logs ul
            JOIN users u ON ul.user_id = u.id
            JOIN inventory_items i ON ul.item_id = i.id
            WHERE ul.item_id = ?
            ORDER BY ul.logged_at DESC
            LIMIT ?
        ");
        $stmt->execute([$itemId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all usage logs with optional filters
     * 
     * @param array $filters Optional filters (item_id, user_id, action, days)
     * @param int $limit Limit records
     * @return array Usage logs
     */
    public function getAll($filters = [], $limit = 500) {
        try {
            $sql = "
                SELECT ul.*, 
                       u.name as user_name,
                       u.email as user_email,
                       i.name as item_name,
                       i.code as item_code
                FROM inventory_usage_logs ul
                JOIN users u ON ul.user_id = u.id
                JOIN inventory_items i ON ul.item_id = i.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Apply filters
            if (!empty($filters['item_id'])) {
                $sql .= " AND ul.item_id = ?";
                $params[] = $filters['item_id'];
            }
            if (!empty($filters['subscription_type_id'])) {
                $sql .= " AND i.subscription_type_id = ?";
                $params[] = $filters['subscription_type_id'];
            }
            if (!empty($filters['user_id'])) {
                $sql .= " AND ul.user_id = ?";
                $params[] = $filters['user_id'];
            }
            if (!empty($filters['action'])) {
                $sql .= " AND ul.action = ?";
                $params[] = $filters['action'];
            }
            if (!empty($filters['days'])) {
                $sql .= " AND ul.logged_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
                $params[] = $filters['days'];
            }
            if (!empty($filters['search'])) {
                $sql .= " AND (u.name LIKE ? OR i.name LIKE ? OR ul.description LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY ul.logged_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("UsageLog getAll error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get usage logs for a user
     * 
     * @param int $userId User ID
     * @param int $limit Limit records
     * @return array Usage logs
     */
    public function getByUserId($userId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT ul.*, 
                   i.name as item_name,
                   st.name as subscription_type_name
            FROM inventory_usage_logs ul
            JOIN inventory_items i ON ul.item_id = i.id
            JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
            WHERE ul.user_id = ?
            ORDER BY ul.logged_at DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent usage logs
     * 
     * @param int $limit Limit records
     * @return array Recent usage logs
     */
    public function getRecent($limit = 50) {
        $stmt = $this->db->prepare("
            SELECT ul.*, 
                   u.name as user_name,
                   i.name as item_name,
                   st.name as subscription_type_name
            FROM inventory_usage_logs ul
            JOIN users u ON ul.user_id = u.id
            JOIN inventory_items i ON ul.item_id = i.id
            JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
            ORDER BY ul.logged_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get usage statistics for an item
     * 
     * @param int $itemId Item ID
     * @param string $period Period (day, week, month)
     * @return array Usage statistics
     */
    public function getItemUsageStats($itemId, $period = 'month') {
        $dateCondition = $this->getDateCondition($period);
        
        $stmt = $this->db->prepare("
            SELECT 
                action,
                COUNT(*) as count,
                COUNT(DISTINCT user_id) as unique_users,
                AVG(usage_duration_minutes) as avg_duration,
                SUM(usage_duration_minutes) as total_duration
            FROM inventory_usage_logs 
            WHERE item_id = ? AND {$dateCondition}
            GROUP BY action
            ORDER BY count DESC
        ");
        $stmt->execute([$itemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user usage statistics
     * 
     * @param int $userId User ID
     * @param string $period Period (day, week, month)
     * @return array Usage statistics
     */
    public function getUserUsageStats($userId, $period = 'month') {
        $dateCondition = $this->getDateCondition($period);
        
        $stmt = $this->db->prepare("
            SELECT 
                i.name as item_name,
                st.name as subscription_type_name,
                COUNT(*) as access_count,
                AVG(ul.usage_duration_minutes) as avg_duration,
                SUM(ul.usage_duration_minutes) as total_duration,
                MAX(ul.logged_at) as last_access
            FROM inventory_usage_logs ul
            JOIN inventory_items i ON ul.item_id = i.id
            JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
            WHERE ul.user_id = ? AND {$dateCondition}
            GROUP BY ul.item_id
            ORDER BY access_count DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get overall usage statistics
     * 
     * @param string $period Period (day, week, month)
     * @return array Overall statistics
     */
    public function getOverallStats($period = 'month') {
        $dateCondition = $this->getDateCondition($period);
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_actions,
                COUNT(DISTINCT user_id) as active_users,
                COUNT(DISTINCT item_id) as active_items,
                AVG(usage_duration_minutes) as avg_session_duration,
                SUM(usage_duration_minutes) as total_usage_time
            FROM inventory_usage_logs 
            WHERE {$dateCondition}
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get most used items
     * 
     * @param string $period Period (day, week, month)
     * @param int $limit Limit records
     * @return array Most used items
     */
    public function getMostUsedItems($period = 'month', $limit = 10) {
        $dateCondition = $this->getDateCondition($period);
        
        $stmt = $this->db->prepare("
            SELECT 
                i.name as item_name,
                st.name as subscription_type_name,
                COUNT(*) as usage_count,
                COUNT(DISTINCT ul.user_id) as unique_users,
                AVG(ul.usage_duration_minutes) as avg_duration
            FROM inventory_usage_logs ul
            JOIN inventory_items i ON ul.item_id = i.id
            JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
            WHERE {$dateCondition}
            GROUP BY ul.item_id
            ORDER BY usage_count ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get most active users
     * 
     * @param string $period Period (day, week, month)
     * @param int $limit Limit records
     * @return array Most active users
     */
    public function getMostActiveUsers($period = 'month', $limit = 10) {
        $dateCondition = $this->getDateCondition($period);
        
        $stmt = $this->db->prepare("
            SELECT 
                u.name as user_name,
                u.email as user_email,
                COUNT(*) as action_count,
                COUNT(DISTINCT ul.item_id) as unique_items,
                SUM(ul.usage_duration_minutes) as total_duration
            FROM inventory_usage_logs ul
            JOIN users u ON ul.user_id = u.id
            WHERE {$dateCondition}
            GROUP BY ul.user_id
            ORDER BY action_count DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get usage trends over time
     * 
     * @param string $period Period (day, week, month)
     * @param int $days Number of days to analyze
     * @return array Usage trends
     */
    public function getUsageTrends($period = 'day', $days = 30) {
        $groupBy = $period === 'day' ? 'DATE(logged_at)' : 'WEEK(logged_at)';
        
        $stmt = $this->db->prepare("
            SELECT 
                {$groupBy} as period,
                COUNT(*) as total_actions,
                COUNT(DISTINCT user_id) as active_users,
                COUNT(DISTINCT item_id) as active_items,
                AVG(usage_duration_minutes) as avg_duration
            FROM inventory_usage_logs 
            WHERE logged_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY {$groupBy}
            ORDER BY period DESC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Clean old usage logs
     * 
     * @param int $days Keep logs newer than this many days
     * @return int Number of deleted logs
     */
    public function cleanOldLogs($days = 365) {
        // Check permissions
        if (!has_permission('delete_inventory')) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("
                DELETE FROM inventory_usage_logs 
                WHERE logged_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            
            $deletedCount = $stmt->rowCount();
            
            // Log activity
            if ($deletedCount > 0) {
                global $db;
                $logStmt = $db->prepare("
                    INSERT INTO activity_logs 
                    (user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $logStmt->execute([
                    $_SESSION['user_id'],
                    'maintenance',
                    'inventory_usage_log',
                    null,
                    "Cleaned {$deletedCount} old usage logs older than {$days} days",
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
            }
            
            return $deletedCount;
            
        } catch (PDOException $e) {
            error_log("Usage log cleanup error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get date condition for queries
     * 
     * @param string $period Period (day, week, month)
     * @return string SQL date condition
     */
    private function getDateCondition($period) {
        switch ($period) {
            case 'day':
                return "logged_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            case 'week':
                return "logged_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
            case 'month':
                return "logged_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            case 'year':
                return "logged_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            default:
                return "logged_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
        }
    }
    
    /**
     * Log license key access
     * 
     * @param int $itemId Item ID
     * @param string $action Action performed
     * @return bool Success status
     */
    public function logLicenseKeyAccess($itemId, $action = 'license_key_viewed') {
        return $this->logUsage([
            'item_id' => $itemId,
            'action' => $action,
            'description' => 'License key accessed by user',
            'metadata' => [
                'sensitive_data_access' => true,
                'permission_checked' => has_permission('view_license_keys')
            ]
        ]);
    }
    
    /**
     * Get usage statistics by date range
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Usage statistics
     */
    public function getUsageStatsByDateRange($startDate, $endDate) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(logged_at) as date,
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(DISTINCT item_id) as unique_items,
                    action,
                    COUNT(*) as action_count
                FROM inventory_usage_logs
                WHERE DATE(logged_at) BETWEEN ? AND ?
                GROUP BY DATE(logged_at), action
                ORDER BY date DESC, action_count DESC
            ");
            
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("UsageLog getUsageStatsByDateRange error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user activity summary
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array User activity data
     */
    public function getUserActivitySummary($startDate, $endDate) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    u.id as user_id,
                    u.name as user_name,
                    u.email as user_email,
                    COUNT(ul.id) as total_activities,
                    COUNT(DISTINCT ul.item_id) as unique_items_used,
                    MAX(ul.logged_at) as last_activity,
                    MIN(ul.logged_at) as first_activity
                FROM users u
                LEFT JOIN inventory_usage_logs ul ON u.id = ul.user_id 
                    AND DATE(ul.logged_at) BETWEEN ? AND ?
                WHERE u.is_active = 1
                GROUP BY u.id
                HAVING total_activities > 0
                ORDER BY total_activities DESC, last_activity DESC
            ");
            
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("UsageLog getUserActivitySummary error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent usage logs for a specific assignment
     * 
     * @param int $assignmentId Assignment ID
     * @param int $limit Number of records to return (default 10)
     * @return array Recent usage logs
     */
    public function getRecentByAssignment($assignmentId, $limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    ul.*,
                    u.first_name,
                    u.last_name,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM inventory_usage_logs ul
                LEFT JOIN users u ON ul.user_id = u.id
                WHERE ul.assignment_id = ?
                ORDER BY ul.logged_at DESC, ul.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$assignmentId, (int)$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("UsageLog getRecentByAssignment error: " . $e->getMessage());
            return [];
        }
    }
}
