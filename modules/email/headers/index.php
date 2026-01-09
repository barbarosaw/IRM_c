<?php
/**
 * AbroadWorks Management System - Email Headers Module
 *
 * @author ikinciadam@gmail.com
 */

// Define system constant to prevent direct access to module files
define('AW_SYSTEM', true);

// Include required files
require_once '../../../includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../login.php');
    exit;
}

// Check if user has permission
if (!has_permission('timeworks_email_templates')) {
    header('Location: ../../../access-denied.php');
    exit;
}

// Get email headers
$headers = [];
try {
    $stmt = $db->prepare("SELECT * FROM email_template_parts WHERE type = 'header' ORDER BY name ASC");
    $stmt->execute();
    $headers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error loading email headers: " . $e->getMessage());
}

// Set page title
$page_title = "Email Headers";

// Set root path for components
$root_path = '../../../';

// Include header
include '../../../components/header.php';

// Include sidebar
include '../../../components/sidebar.php';

// Include view
include 'views/index.php';

// Include footer
include '../../../components/footer.php';
