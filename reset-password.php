<?php
/**
 * AbroadWorks Management System - Reset Password Redirect
 * 
 * @author ikinciadam@gmail.com
 */

// Get token from query string
$token = $_GET['token'] ?? '';

// Redirect to the modular auth reset password page
header('Location: modules/auth/?action=reset-password&token=' . urlencode($token));
exit;
