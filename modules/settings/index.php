<?php
/**
 * AbroadWorks Management System - Settings Module
 * 
 * @author ikinciadam@gmail.com
 */

// Define system constant to prevent direct access to module files
define('AW_SYSTEM', true);

// Include required files
require_once '../../includes/init.php';
require_once '../../includes/backup.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check if user has access to this module
if (!has_module_access('settings')) {
    header('Location: ../../access-denied.php');
    exit;
}

// Check if module is active
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = 'settings'");
$stmt->execute();
$is_active = $stmt->fetchColumn();

// If module is not active and user is not an owner, redirect to module-inactive page
if ($is_active === false || $is_active == 0) {
    if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
        header('Location: ../../module-inactive.php?module=settings');
        exit;
    }
}

// Include model
require_once 'models/Setting.php';

// Initialize model
$settingModel = new Setting();

// Get all settings
$settings = $settingModel->getAllSettings();

// Get settings groups for API section
$settings_groups = [];
try {
    $stmt = $db->query("SELECT `key`, `group` FROM settings");
    while ($row = $stmt->fetch()) {
        $settings_groups[$row['key']] = $row['group'];
    }
} catch (PDOException $e) {
    error_log("Error getting settings groups: " . $e->getMessage());
}

// Get all tables for backup exclusion
$all_tables = $settingModel->getAllTables();

// Get all roles for default role setting
$roles = $settingModel->getAllRoles();

// Get list of available backups
$backups = get_backup_files();

// Success and error messages
$success_message = '';
$error_message = '';

// Handle backup creation if requested
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_backup']) && isset($_SESSION['is_owner']) && $_SESSION['is_owner']) {
    $exclude_tables = isset($_POST['exclude_tables']) ? $_POST['exclude_tables'] : [];
    
    $backup_file = create_database_backup($exclude_tables);
    
    if ($backup_file) {
        $success_message = "Backup created successfully: " . basename($backup_file);
    } else {
        $error_message = "Failed to create backup. Check server permissions.";
    }
}

// Handle backup restoration if requested
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restore_backup']) && isset($_SESSION['is_owner']) && $_SESSION['is_owner']) {
    $backup_file = $_POST['backup_file'];
    
    if (restore_database_backup($backup_file)) {
        $success_message = "Database restored successfully from: " . $backup_file;
    } else {
        $error_message = "Failed to restore database from backup.";
    }
}

// Handle backup deletion if requested
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_backup']) && isset($_SESSION['is_owner']) && $_SESSION['is_owner']) {
    $backup_file = $_POST['backup_file'];
    $backup_path = '../../backups/' . $backup_file;
    
    if (file_exists($backup_path) && unlink($backup_path)) {
        $success_message = "Backup file deleted: " . $backup_file;
        
        // Refresh backup list
        $backups = get_backup_files();
    } else {
        $error_message = "Failed to delete backup file.";
    }
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    // Checkboxlar işaretli değilse 0 olarak set et
    if (!isset($_POST['settings']['two_factor_enabled'])) {
        $_POST['settings']['two_factor_enabled'] = '0';
    }
    if (!isset($_POST['settings']['two_factor_enforce'])) {
        $_POST['settings']['two_factor_enforce'] = '0';
    }

    try {
        $updated = $settingModel->updateSettings($_POST['settings']);
        
        // Log the activity
        log_activity($_SESSION['user_id'], 'update', 'settings', "Updated system settings");
        
        $success_message = "Settings updated successfully!";
        
        // Refresh settings
        $settings = $settingModel->getAllSettings();
        
    } catch (Exception $e) {
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

// Handle maintenance mode toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_maintenance'])) {
    $new_state = ($settings['maintenance_mode'] == '1') ? '0' : '1';
    $action = ($new_state == '1') ? 'enabled' : 'disabled';
    
    try {
        if ($settingModel->toggleMaintenanceMode($new_state == '1')) {
            // Update settings array
            $settings['maintenance_mode'] = $new_state;
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'update', 'settings', "Maintenance mode $action");
            
            $success_message = "Maintenance mode has been $action.";
        } else {
            $error_message = "Error toggling maintenance mode.";
        }
    } catch (Exception $e) {
        $error_message = "Error toggling maintenance mode: " . $e->getMessage();
    }
}

// Set page title
$page_title = "System Settings";

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
