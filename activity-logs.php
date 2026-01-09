<?php
/**
 * AbroadWorks Management System - Activity Logs Redirect
 * 
 * @author ikinciadam@gmail.com
 */

// Include necessary files
require_once 'includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Redirect to logs module
header('Location: modules/logs/');
exit;
