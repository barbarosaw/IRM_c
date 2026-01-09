<?php
/**
 * AbroadWorks Management System - Initialization File
 * 
 * @author ikinciadam@gmail.com
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default timezone
$default_timezone = 'Europe/Istanbul';
date_default_timezone_set($default_timezone);

// Include core files
// Define root directory
$root_dir = dirname(__DIR__);

// Include core files with absolute paths
require_once $root_dir . '/config/database.php';
require_once $root_dir . '/includes/functions.php';

// Initialize system timezone from settings
if (function_exists('initialize_timezone')) {
    initialize_timezone();
}

// Include other common includes
if (file_exists($root_dir . '/includes/auth.php')) {
    require_once $root_dir . '/includes/auth.php';
}

// Include two factor helper and enforce 2FA if required
if (file_exists($root_dir . '/includes/two_factor.php')) {
    require_once $root_dir . '/includes/two_factor.php';
    enforce_2fa_if_required();
}

// Apply debug settings if function exists
if (function_exists('apply_debug_settings')) {
    apply_debug_settings();
}
