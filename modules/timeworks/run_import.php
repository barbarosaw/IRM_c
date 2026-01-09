<?php
/**
 * AbroadWorks Management System - TimeWorks JSON Import Runner
 * Web interface to run JSON import
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

// Check user permission
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

if (!has_permission('settings-manage')) {
    die('You do not have permission to run imports');
}

// Set execution limits
set_time_limit(300);
ini_set('memory_limit', '512M');

// Run import
include 'import_json.php';
?>
