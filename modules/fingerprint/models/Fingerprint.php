<?php
/**
 * AbroadWorks Management System - Fingerprint Model
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class Fingerprint {
    private $db;
    
    public function __construct($connection = null) {
        if ($connection) {
            $this->db = $connection;
        } else {
            global $db;
            $this->db = $db;
        }
    }
    
    /**
     * Get all fingerprints with pagination
     * 
     * @param int $page Current page
     * @param int $limit Items per page
     * @param array $filters Filters to apply
     * @return array
     */
    public function getAllFingerprints($page = 1, $limit = 25, $filters = []) {
        $offset = ($page - 1) * $limit;
        $where_conditions = [];
        $params = [];
        
        // Apply filters
        if (!empty($filters['user_id'])) {
            $where_conditions[] = "user_id LIKE ?";
            $params[] = '%' . $filters['user_id'] . '%';
        }
        
        if (!empty($filters['ip'])) {
            $where_conditions[] = "ip LIKE ?";
            $params[] = '%' . $filters['ip'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(timestamp) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(timestamp) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM fingerprints 
                {$where_clause}
                ORDER BY timestamp DESC 
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting fingerprints: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get total count of fingerprints
     * 
     * @param array $filters Filters to apply
     * @return int
     */
    public function getTotalCount($filters = []) {
        $where_conditions = [];
        $params = [];
        
        // Apply same filters as getAllFingerprints
        if (!empty($filters['user_id'])) {
            $where_conditions[] = "user_id LIKE ?";
            $params[] = '%' . $filters['user_id'] . '%';
        }
        
        if (!empty($filters['ip'])) {
            $where_conditions[] = "ip LIKE ?";
            $params[] = '%' . $filters['ip'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(timestamp) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(timestamp) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM fingerprints {$where_clause}");
            $stmt->execute($params);
            
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting fingerprints count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get dashboard statistics
     * 
     * @return array
     */
    public function getDashboardStats() {
        $stats = [];
        
        try {
            // Total fingerprints
            $stmt = $this->db->query("SELECT COUNT(*) FROM fingerprints");
            $stats['total_fingerprints'] = $stmt->fetchColumn();
            
            // Today's fingerprints
            $stmt = $this->db->query("SELECT COUNT(*) FROM fingerprints WHERE DATE(timestamp) = CURDATE()");
            $stats['today_fingerprints'] = $stmt->fetchColumn();
            
            // This week's fingerprints
            $stmt = $this->db->query("SELECT COUNT(*) FROM fingerprints WHERE YEARWEEK(timestamp) = YEARWEEK(NOW())");
            $stats['week_fingerprints'] = $stmt->fetchColumn();
            
            // Unique users
            $stmt = $this->db->query("SELECT COUNT(DISTINCT user_id) FROM fingerprints");
            $stats['unique_users'] = $stmt->fetchColumn();
            
            // Unique IPs
            $stmt = $this->db->query("SELECT COUNT(DISTINCT ip) FROM fingerprints");
            $stats['unique_ips'] = $stmt->fetchColumn();
            
        } catch (PDOException $e) {
            error_log("Error getting dashboard stats: " . $e->getMessage());
            $stats = [
                'total_fingerprints' => 0,
                'today_fingerprints' => 0,
                'week_fingerprints' => 0,
                'unique_users' => 0,
                'unique_ips' => 0
            ];
        }
        
        return $stats;
    }
    
    /**
     * Get top browsers
     * 
     * @param int $limit Number of results
     * @return array
     */
    public function getTopBrowsers($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    CASE 
                        WHEN user_agent LIKE '%Chrome%' AND user_agent NOT LIKE '%Edg%' THEN 'Chrome'
                        WHEN user_agent LIKE '%Firefox%' THEN 'Firefox'
                        WHEN user_agent LIKE '%Safari%' AND user_agent NOT LIKE '%Chrome%' THEN 'Safari'
                        WHEN user_agent LIKE '%Edg%' THEN 'Edge'
                        WHEN user_agent LIKE '%Opera%' THEN 'Opera'
                        ELSE 'Other'
                    END as browser,
                    COUNT(*) as count,
                    ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM fingerprints)), 2) as percentage
                FROM fingerprints 
                GROUP BY browser 
                ORDER BY count DESC 
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting top browsers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get daily activity for last 30 days
     * 
     * @return array
     */
    public function getDailyActivity() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    DATE(timestamp) as date,
                    COUNT(*) as count
                FROM fingerprints 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(timestamp)
                ORDER BY date ASC
            ");
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting daily activity: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get top countries by IP
     * 
     * @param int $limit Number of results
     * @return array
     */
    public function getTopCountries($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    SUBSTRING_INDEX(ip, '.', 2) as ip_prefix,
                    COUNT(*) as count
                FROM fingerprints 
                GROUP BY ip_prefix 
                ORDER BY count DESC 
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting top countries: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent fingerprints with user statistics
     * 
     * @param int $page Current page
     * @param int $limit Items per page
     * @param string $orderBy Column to order by
     * @param string $orderDir Order direction (ASC/DESC)
     * @param array $filters Filters to apply
     * @return array
     */
    public function getRecentFingerprints($page = 1, $limit = 25, $orderBy = 'last_activity', $orderDir = 'DESC', $filters = []) {
        $offset = ($page - 1) * $limit;
        $allowedColumns = ['user_id', 'ip', 'total_sessions', 'total_ip_changes', 'last_activity', 'first_activity'];
        
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'last_activity';
        }
        
        if (!in_array(strtoupper($orderDir), ['ASC', 'DESC'])) {
            $orderDir = 'DESC';
        }
        
        // Build WHERE conditions for filters
        $having_conditions = [];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $having_conditions[] = "user_id LIKE ?";
            $params[] = '%' . $filters['user_id'] . '%';
        }
        
        if (!empty($filters['ip'])) {
            $having_conditions[] = "ip LIKE ?";
            $params[] = '%' . $filters['ip'] . '%';
        }
        
        // For date filters, we need WHERE clause before GROUP BY
        $where_conditions = [];
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(timestamp) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(timestamp) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $having_clause = !empty($having_conditions) ? 'HAVING ' . implode(' AND ', $having_conditions) : '';
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    user_id,
                    SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT ip ORDER BY timestamp DESC SEPARATOR ', '), ',', 1) as ip,
                    user_agent,
                    COUNT(*) as total_sessions,
                    COUNT(DISTINCT ip) as total_ip_changes,
                    MAX(timestamp) as last_activity,
                    MIN(timestamp) as first_activity
                FROM fingerprints 
                {$where_clause}
                GROUP BY user_id
                {$having_clause}
                ORDER BY {$orderBy} {$orderDir}
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting recent fingerprints: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get total count of unique users
     * 
     * @param array $filters Filters to apply
     * @return int
     */
    public function getTotalUsersCount($filters = []) {
        // Build WHERE conditions for filters
        $having_conditions = [];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $having_conditions[] = "user_id LIKE ?";
            $params[] = '%' . $filters['user_id'] . '%';
        }
        
        if (!empty($filters['ip'])) {
            $having_conditions[] = "ip LIKE ?";
            $params[] = '%' . $filters['ip'] . '%';
        }
        
        // For date filters, we need WHERE clause before GROUP BY
        $where_conditions = [];
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "DATE(timestamp) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "DATE(timestamp) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $having_clause = !empty($having_conditions) ? 'HAVING ' . implode(' AND ', $having_conditions) : '';
        
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM (
                    SELECT user_id
                    FROM fingerprints 
                    {$where_clause}
                    GROUP BY user_id
                    {$having_clause}
                ) as filtered_users
            ");
            
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting total users count: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get all fingerprints for a specific user
     * 
     * @param string $userId User ID to get fingerprints for
     * @return array
     */
    public function getUserFingerprints($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT fp.*, 
                       DATE_FORMAT(fp.timestamp, '%Y-%m-%d %H:%i:%s') as formatted_timestamp,
                       fp.ip as ip_address,
                       fp.user_agent as browser
                FROM fingerprints fp 
                WHERE fp.user_id = ?
                ORDER BY fp.timestamp DESC
                LIMIT 50
            ");
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting user fingerprints: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get detailed information about IP address usage
     * 
     * @param string $ip IP address to get details for
     * @return array
     */
    public function getIpDetails($ip) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    user_id,
                    COUNT(*) as session_count,
                    MAX(timestamp) as last_activity,
                    user_agent as browser,
                    DATE_FORMAT(MAX(timestamp), '%Y-%m-%d %H:%i:%s') as formatted_last_activity
                FROM fingerprints 
                WHERE ip = ?
                GROUP BY user_id, user_agent
                ORDER BY last_activity DESC
            ");
            
            $stmt->execute([$ip]);
            $results = $stmt->fetchAll();
            
            // Format the results for frontend
            foreach ($results as &$result) {
                $result['last_activity'] = $result['formatted_last_activity'];
            }
            
            return $results;
        } catch (PDOException $e) {
            error_log("Error getting IP details: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get top users by activity count
     * 
     * @param int $limit Number of results
     * @return array
     */
    public function getTopUsers($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    user_id,
                    COUNT(*) as activity_count,
                    COUNT(DISTINCT ip) as unique_ips,
                    MAX(timestamp) as last_seen
                FROM fingerprints 
                GROUP BY user_id 
                ORDER BY activity_count DESC 
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting top users: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get suspicious IP activities (same IP used by multiple users)
     * 
     * @param int $limit Number of results
     * @return array
     */
    public function getSuspiciousIPs($limit = 20) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    ip,
                    COUNT(DISTINCT user_id) as user_count,
                    COUNT(*) as total_sessions,
                    GROUP_CONCAT(DISTINCT user_id ORDER BY user_id SEPARATOR ', ') as users,
                    MAX(timestamp) as last_activity
                FROM fingerprints 
                GROUP BY ip 
                HAVING user_count > 1
                ORDER BY user_count DESC, total_sessions DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting suspicious IPs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get geographic distribution of IPs for mapping
     * 
     * @return array
     */
    public function getGeographicData() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    ip,
                    COUNT(*) as activity_count,
                    COUNT(DISTINCT user_id) as unique_users,
                    MAX(timestamp) as last_activity
                FROM fingerprints 
                GROUP BY ip 
                ORDER BY activity_count DESC
                LIMIT 1000
            ");
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting geographic data: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get unusual login times (activities outside normal hours)
     * 
     * @return array
     */
    public function getUnusualLoginTimes() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    user_id,
                    ip,
                    timestamp,
                    HOUR(timestamp) as login_hour,
                    user_agent,
                    CASE 
                        WHEN HOUR(timestamp) BETWEEN 0 AND 5 THEN 'Late Night'
                        WHEN HOUR(timestamp) BETWEEN 6 AND 8 THEN 'Early Morning'
                        WHEN HOUR(timestamp) BETWEEN 22 AND 23 THEN 'Late Evening'
                        ELSE 'Normal'
                    END as time_category
                FROM fingerprints 
                WHERE HOUR(timestamp) NOT BETWEEN 9 AND 21
                ORDER BY timestamp DESC
                LIMIT 100
            ");
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting unusual login times: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get rapid location changes (IP changes within short time)
     * 
     * @return array
     */
    public function getRapidLocationChanges() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    f1.user_id,
                    f1.ip as first_ip,
                    f2.ip as second_ip,
                    f1.timestamp as first_time,
                    f2.timestamp as second_time,
                    TIMESTAMPDIFF(MINUTE, f1.timestamp, f2.timestamp) as time_diff_minutes
                FROM fingerprints f1
                JOIN fingerprints f2 ON f1.user_id = f2.user_id 
                WHERE f1.ip != f2.ip 
                AND f2.timestamp > f1.timestamp
                AND TIMESTAMPDIFF(HOUR, f1.timestamp, f2.timestamp) <= 2
                ORDER BY time_diff_minutes ASC
                LIMIT 50
            ");
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting rapid location changes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get multiple browser usage by same user
     * 
     * @return array
     */
    public function getMultipleBrowserUsage() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    user_id,
                    COUNT(DISTINCT 
                        CASE 
                            WHEN user_agent LIKE '%Chrome%' AND user_agent NOT LIKE '%Edg%' THEN 'Chrome'
                            WHEN user_agent LIKE '%Firefox%' THEN 'Firefox'
                            WHEN user_agent LIKE '%Safari%' AND user_agent NOT LIKE '%Chrome%' THEN 'Safari'
                            WHEN user_agent LIKE '%Edg%' THEN 'Edge'
                            WHEN user_agent LIKE '%Opera%' THEN 'Opera'
                            ELSE 'Other'
                        END
                    ) as browser_count,
                    GROUP_CONCAT(DISTINCT 
                        CASE 
                            WHEN user_agent LIKE '%Chrome%' AND user_agent NOT LIKE '%Edg%' THEN 'Chrome'
                            WHEN user_agent LIKE '%Firefox%' THEN 'Firefox'
                            WHEN user_agent LIKE '%Safari%' AND user_agent NOT LIKE '%Chrome%' THEN 'Safari'
                            WHEN user_agent LIKE '%Edg%' THEN 'Edge'
                            WHEN user_agent LIKE '%Opera%' THEN 'Opera'
                            ELSE 'Other'
                        END
                        ORDER BY user_agent SEPARATOR ', '
                    ) as browsers_used,
                    COUNT(*) as total_sessions,
                    MAX(timestamp) as last_activity
                FROM fingerprints 
                GROUP BY user_id 
                HAVING browser_count > 1
                ORDER BY browser_count DESC, total_sessions DESC
                LIMIT 50
            ");
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting multiple browser usage: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Detect VPN/Proxy usage based on IP patterns
     * 
     * @return array
     */
    public function getVPNProxyDetection() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    ip,
                    user_id,
                    COUNT(*) as activity_count,
                    CASE 
                        WHEN ip LIKE '10.%' OR ip LIKE '192.168.%' OR ip LIKE '172.%' THEN 'Private Network'
                        WHEN ip REGEXP '^(46\.166\.|185\.220\.|109\.70\.|195\.123\.)' THEN 'Known VPN Range'
                        WHEN COUNT(DISTINCT user_id) > 5 THEN 'Shared IP (Possible Proxy)'
                        WHEN ip REGEXP '^(103\.|45\.|104\.|107\.)' THEN 'Cloud/VPS Provider'
                        ELSE 'Regular'
                    END as ip_type,
                    MAX(timestamp) as last_seen
                FROM fingerprints 
                GROUP BY ip, user_id
                HAVING ip_type != 'Regular'
                ORDER BY activity_count DESC
                LIMIT 100
            ");
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting VPN/Proxy detection: " . $e->getMessage());
            return [];
        }
    }

    public function getConcurrentSessions() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    user_id,
                    ip,
                    session_id,
                    COUNT(*) as session_count,
                    GROUP_CONCAT(DISTINCT timestamp ORDER BY timestamp DESC SEPARATOR ', ') as session_times,
                    MAX(timestamp) as last_activity
                FROM fingerprints 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
                  AND session_id IS NOT NULL
                GROUP BY user_id, session_id
                HAVING session_count > 1
                ORDER BY session_count DESC, last_activity DESC
                LIMIT 20
            ");
            
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error getting concurrent sessions: " . $e->getMessage());
            return [];
        }
    }
}
