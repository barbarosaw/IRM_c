<?php
/**
 * AbroadWorks Management System - Dashboard Module
 * 
 * @author ikinciadam@gmail.com
 */

// Define system constant to prevent direct access to module files
define('AW_SYSTEM', true);

// Include required files
require_once '../../includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check if user has access to this module
if (!has_module_access('dashboard')) {
    header('Location: ../../access-denied.php');
    exit;
}

// Get user statistics
$user_stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0
];

// Get access statistics
$recent_logins = [];

// Fetch statistics if user has module access
if (has_module_access('users') && is_module_visible('users')) {
    // User statistics
    $stmt = $db->query("SELECT COUNT(*) as total, SUM(is_active) as active FROM users WHERE is_owner = 0");
    $user_result = $stmt->fetch();
    
    $user_stats = [
        'total' => $user_result['total'] ?? 0,
        'active' => $user_result['active'] ?? 0,
        'inactive' => ($user_result['total'] ?? 0) - ($user_result['active'] ?? 0)    ];
}

if (has_module_access('logs') && is_module_visible('logs')) {
    // Recent login activity
    $stmt = $db->prepare("
        SELECT a.*, u.name as user_name 
        FROM activity_logs a
        JOIN users u ON a.user_id = u.id
        WHERE a.action = 'auth' AND a.description LIKE 'User logged in%'
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_logins = $stmt->fetchAll();
}

// Get all active modules for dashboard
$visible_modules = [];
$all_modules = get_all_modules(true);
foreach ($all_modules as $module) {
    if (is_module_visible($module['code'])) {
        $visible_modules[] = $module;
    }
}

// Set page title
$page_title = 'Dashboard';

// Set root path for components
$root_path = '../../';

// Include header
include '../../components/header.php';

// Include sidebar
include '../../components/sidebar.php';

// Include dashboard view
include 'views/index.php';

// Include footer
include '../../components/footer.php';
