<?php
/**
 * AbroadWorks Management System - Maintenance Mode Middleware
 * 
 * @author ikinciadam@gmail.com
 */

// Check if the system is in maintenance mode
function is_in_maintenance_mode() {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'maintenance_mode' LIMIT 1");
        $stmt->execute();
        return $stmt->fetchColumn() === '1';
    } catch (PDOException $e) {
        // If table doesn't exist yet or any other error, assume not in maintenance mode
        return false;
    }
}

// Get maintenance message
function get_maintenance_message() {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'maintenance_message' LIMIT 1");
        $stmt->execute();
        return $stmt->fetchColumn() ?: 'System is currently undergoing scheduled maintenance. Please check back later.';
    } catch (PDOException $e) {
        return 'System is currently undergoing scheduled maintenance. Please check back later.';
    }
}

// Check if the current user can bypass maintenance mode
function can_bypass_maintenance() {
    // ONLY owner can bypass maintenance mode
    return isset($_SESSION['is_owner']) && $_SESSION['is_owner'] == 1;
}

// Handle maintenance mode
if (!defined('MAINTENANCE_BYPASS') && is_in_maintenance_mode() && !can_bypass_maintenance()) {
    // These pages are always accessible
    $allowed_pages = ['login.php', 'logout.php', 'maintenance.php', 'error.php'];
    $current_page = basename($_SERVER['SCRIPT_NAME']);
    
    if (!in_array($current_page, $allowed_pages)) {
        // Redirect to maintenance page
        header('Location: maintenance.php');
        exit;
    }
}
