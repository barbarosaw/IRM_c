<?php
/**
 * AbroadWorks Management System - Email Templates Module
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

// Check if user has permission to manage email templates
if (!has_permission('timeworks_email_templates')) {
    header('Location: ../../access-denied.php');
    exit;
}

// Get email templates
$templates = [];
try {
    $stmt = $db->query("SELECT * FROM email_templates ORDER BY name ASC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error loading email templates: " . $e->getMessage());
}

// Get email settings for preview
$emailSettings = [];
try {
    $stmt = $db->query("SELECT `key`, `value` FROM settings WHERE `group` = 'email'");
    while ($row = $stmt->fetch()) {
        $emailSettings[$row['key']] = $row['value'];
    }
} catch (PDOException $e) {
    error_log("Error loading email settings: " . $e->getMessage());
}

// Set page title
$page_title = "Email Templates";

// Set root path for components
$root_path = '../../';

// Include header
include '../../components/header.php';

// Include sidebar
include '../../components/sidebar.php';

// Include view
include 'views/index.php';

// Include footer
include '../../components/footer.php';
