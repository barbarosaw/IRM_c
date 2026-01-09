<?php
/**
 * Phone Calls Module
 * 
 * @author System Generated
 */

require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['phone_calls']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }
$page_title = "Phone Calls";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);
include '../../components/header.php';
include '../../components/sidebar.php';
include 'views/index.php';
include '../../components/footer.php';
