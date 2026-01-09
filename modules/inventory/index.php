<?php
/**
 * Inventory Module
 * 
 * @author System Generated
 */

// Define system constant to allow access to models
define('AW_SYSTEM', true);

require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory view permission
if (!has_permission('view_inventory')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }
$page_title = "Inventory";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);
include '../../components/header.php';
include '../../components/sidebar.php';
include 'views/index.php';
include '../../components/footer.php';
