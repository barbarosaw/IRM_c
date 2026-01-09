<?php
/**
 * AbroadWorks Management System - Maintenance Mode Page
 * 
 * @author ikinciadam@gmail.com
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/maintenance.php';

// If maintenance mode is disabled, redirect to home
if (!is_in_maintenance_mode()) {
    header('Location: index.php');
    exit;
}

// If user is owner, they can bypass maintenance mode
if (can_bypass_maintenance()) {
    header('Location: index.php');
    exit;
}

// Get maintenance message from settings
$maintenance_message = get_maintenance_message();

// Get company name and site name from settings if available
$company_name = function_exists('get_setting') ? get_setting('company_name', 'AbroadWorks Management') : 'AbroadWorks Management';
$site_name = function_exists('get_setting') ? get_setting('site_name', 'AbroadWorks Management System') : 'AbroadWorks Management System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode - <?php echo htmlspecialchars($site_name); ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .maintenance-container {
            max-width: 600px;
            padding: 2rem;
            background-color: #fff;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            text-align: center;
        }
        .maintenance-icon {
            font-size: 5rem;
            color: #ffc107;
            margin-bottom: 1.5rem;
        }
        h1 {
            color: #343a40;
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        p {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        .btn-login {
            background-color: #6a9ec0;
            border-color: #6a9ec0;
        }
        .btn-login:hover {
            background-color: #5a8eb0;
            border-color: #5a8eb0;
        }
        .footer {
            margin-top: 2rem;
            color: #adb5bd;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        
        <h1>System Maintenance</h1>
        
        <p><?php echo htmlspecialchars($maintenance_message); ?></p>
        
        <div class="my-4">
            <a href="login.php" class="btn btn-primary btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </a>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($company_name); ?></p>
        </div>
    </div>
</body>
</html>
