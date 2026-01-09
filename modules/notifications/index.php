<?php
/**
 * Notifications Module
 * 
 * @author System Generated
 */

// Start output buffering to prevent headers already sent error
ob_start();

// Include required files
require_once '../../includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check if it's an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    error_log('AJAX request received in notifications/index.php');
    
    // Define AW_SYSTEM constant for model files
    define('AW_SYSTEM', true);
    
    // Only include the view for AJAX requests
    include 'views/index.php';
    exit;
}

// Check if module is active
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['notifications']);
$is_active = $stmt->fetchColumn();

if (!$is_active) {
    header('Location: ../../module-inactive.php');
    exit;
}

// Define AW_SYSTEM constant for model files
define('AW_SYSTEM', true);

// Set page title and root path for assets
$page_title = "Notifications";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Include header and sidebar
include '../../components/header.php';
include '../../components/sidebar.php';

// Include the view
include 'views/index.php';

// Include footer
include '../../components/footer.php';
