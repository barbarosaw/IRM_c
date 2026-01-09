<?php
/**
 * AbroadWorks Management System - Dashboard Model
 *
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

class Dashboard {
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
     * Get system statistics
     * 
     * @return array System statistics
     */
    public function getSystemStats() {
        $stats = [
            'users' => [
                'total' => 0,
                'active' => 0,
                'inactive' => 0
            ],
            'vendors' => [
                'total' => 0,
                'active' => 0,
                'inactive' => 0
            ],
            'modules' => [
                'total' => 0,
                'active' => 0,
                'inactive' => 0
            ]
        ];
        
        try {
            // User statistics
            $stmt = $this->db->query("SELECT COUNT(*) as total, SUM(is_active) as active FROM users WHERE is_owner = 0");
            $user_result = $stmt->fetch();
            
            $stats['users'] = [
                'total' => $user_result['total'] ?? 0,
                'active' => $user_result['active'] ?? 0,
                'inactive' => ($user_result['total'] ?? 0) - ($user_result['active'] ?? 0)
            ];
            
            // Vendor statistics
            $stmt = $this->db->query("SELECT COUNT(*) as total, SUM(is_active) as active FROM vendors");
            $vendor_result = $stmt->fetch();
            
            $stats['vendors'] = [
                'total' => $vendor_result['total'] ?? 0,
                'active' => $vendor_result['active'] ?? 0,
                'inactive' => ($vendor_result['total'] ?? 0) - ($vendor_result['active'] ?? 0)
            ];
            
            // Module statistics
            $stmt = $this->db->query("SELECT COUNT(*) as total, SUM(is_active) as active FROM modules");
            $module_result = $stmt->fetch();
            
            $stats['modules'] = [
                'total' => $module_result['total'] ?? 0,
                'active' => $module_result['active'] ?? 0,
                'inactive' => ($module_result['total'] ?? 0) - ($module_result['active'] ?? 0)
            ];
        } catch (Exception $e) {
            // Log error
            log_error("Error fetching system statistics: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Get recent login activity
     * 
     * @param int $limit Number of records to return
     * @return array Recent login activity
     */
    public function getRecentLogins($limit = 5) {
        $logins = [];
        
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, u.name as user_name 
                FROM activity_logs a
                JOIN users u ON a.user_id = u.id
                WHERE a.action = 'auth' AND a.description LIKE 'User logged in%'
                ORDER BY a.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $logins = $stmt->fetchAll();
        } catch (Exception $e) {
            // Log error
            log_error("Error fetching recent logins: " . $e->getMessage());
        }
        
        return $logins;
    }
    
    /**
     * Get system information
     * 
     * @return array System information
     */
    public function getSystemInfo() {
        $info = [
            'php_version' => phpversion(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'session_lifetime' => get_setting('session_lifetime', '120'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'maintenance_mode' => is_in_maintenance_mode()
        ];
        
        return $info;
    }
}
