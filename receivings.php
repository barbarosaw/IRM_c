<?php
/**
 * AbroadWorks Management System - Receivings Router
 *
 * @author ikinciadam@gmail.com
 */

// Include required files
require_once 'includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user has access to this module
if (!has_permission('receivings-access')) {
    header('Location: access-denied.php');
    exit;
}

// Determine which view to show
$view = isset($_GET['view']) ? $_GET['view'] : 'weekly';

// Redirect to the appropriate module page
if ($view === 'monthly') {
    // Redirect to monthly view
    header('Location: modules/receivings/monthly.php');
} else {
    // Default to weekly view
    header('Location: modules/receivings/index.php');
}

exit;
