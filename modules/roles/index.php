<?php
/**
 * AbroadWorks Management System - Roles Module
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
if (!has_module_access('roles')) {
    header('Location: ../../access-denied.php');
    exit;
}

// Check if module is active
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = 'roles'");
$stmt->execute();
$is_active = $stmt->fetchColumn();

// If module is not active and user is not an owner, redirect to module-inactive page
if ($is_active === false || $is_active == 0) {
    if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
        header('Location: ../../module-inactive.php?module=roles');
        exit;
    }
}

// Include models
require_once 'models/Role.php';
require_once 'models/Permission.php';

// Initialize models
$roleModel = new Role();
$permissionModel = new Permission();

// Handle role deletion
if (isset($_POST['delete_role']) && isset($_POST['role_id']) && has_permission('roles-manage')) {
    $role_id = (int)$_POST['role_id'];
    
    // Check if role is in use
    $user_count = $roleModel->isRoleInUse($role_id);
    
    if ($user_count > 0) {
        show_message("Cannot delete this role because it is assigned to $user_count user(s).", "danger");
    } else {
        if ($roleModel->deleteRole($role_id)) {
            show_message("Role deleted successfully!", "success");
            
            // Log the activity
            log_activity($_SESSION['user_id'], 'delete', 'role', "Deleted role ID: $role_id");
        } else {
            show_message("Error deleting role.", "danger");
        }
    }
}

// Get all roles with their permission counts
$roles = $roleModel->getAllRolesWithCounts();

// Set page title
$page_title = 'Roles Management';

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
