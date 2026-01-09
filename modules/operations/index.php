<?php
/**
 * Operations Module
 * 
 * @author System Generated
 */

// Include required files
require_once '../../includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check if module is active
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['operations']);
$is_active = $stmt->fetchColumn();

if (!$is_active) {
    header('Location: ../../module-inactive.php');
    exit;
}

// Set page title and root path for assets
$page_title = "Operations";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Include header and sidebar
include '../../components/header.php';
include '../../components/sidebar.php';

// Include the view
include 'views/index.php';

// Include footer
include '../../components/footer.php';
