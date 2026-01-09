<?php
/**
 * TimeWorks Module
 *
 * @author System Generated
 */

require_once '../../includes/init.php';

// Handle actions
if (isset($_GET['action']) && $_GET['action'] === 'import') {
    include 'import_json.php';
    exit;
}

// Normal page checks
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['timeworks']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$page_title = "TimeWorks";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);
include '../../components/header.php';
include '../../components/sidebar.php';
include 'views/index.php';
include '../../components/footer.php';
