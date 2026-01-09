<?php
/**
 * AbroadWorks Management System - Roles Redirect
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

// Redirect to roles module
header('Location: modules/roles/');
exit;
