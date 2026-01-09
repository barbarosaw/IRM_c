<?php
/**
 * AbroadWorks Management System - Users Module
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
if (!has_module_access('users')) {
    header('Location: ../../access-denied.php');
    exit;
}

// Check if module is active
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = 'users'");
$stmt->execute();
$is_active = $stmt->fetchColumn();

// If module is not active and user is not an owner, redirect to module-inactive page
if ($is_active === false || $is_active == 0) {
    if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
        header('Location: ../../module-inactive.php?module=users');
        exit;
    }
}

// Include models
require_once 'models/User.php';
require_once 'models/Role.php';

// Initialize models
$userModel = new User();
$roleModel = new Role();

// Handle user activation/deactivation
if (isset($_POST['toggle_status']) && isset($_POST['user_id']) && has_permission('users-manage')) {
    $user_id = (int)$_POST['user_id'];
    $current_status = (int)$_POST['current_status'];
    $new_status = $current_status ? 0 : 1;
    
    if ($userModel->toggleUserStatus($user_id, $new_status)) {
        $action = $new_status ? 'activated' : 'deactivated';
        show_message("User has been $action successfully.", "success");
        
        // Log the activity
        log_activity($_SESSION['user_id'], 'update', 'user', "User ID: $user_id status changed to $action");
    } else {
        show_message("Failed to update user status.", "danger");
    }
}

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id']) && has_permission('users-manage')) {
    $user_id = (int)$_POST['user_id'];
    
    // Don't allow deleting yourself
    if ($user_id == $_SESSION['user_id']) {
        show_message("You cannot delete your own account.", "danger");
    } else {
        if ($userModel->deleteUser($user_id)) {
            show_message("User has been deleted successfully.", "success");
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'delete', 'user', "User ID: $user_id was deleted");
        } else {
            show_message("Failed to delete user.", "danger");
        }
    }
}

// Get all users with their roles (excluding owner account)
$users = $userModel->getAllUsers();

// Set page title
$page_title = 'Users Management';

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
