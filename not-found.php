<?php
/**
 * AbroadWorks Management System - Not Found Page
 * 
 * @author ikinciadam@gmail.com
 */

session_start();
$page_title = 'Page Not Found';

// Define MAINTENANCE_BYPASS to allow this page to be accessed even in maintenance mode
define('MAINTENANCE_BYPASS', true);

// Get the current requested URL
$requested_url = htmlspecialchars($_SERVER['REQUEST_URI']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found - AbroadWorks Management</title>
    
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
        .error-container {
            max-width: 600px;
            padding: 2rem;
            background-color: #fff;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            border-radius: 0.5rem;
            text-align: center;
        }
        .error-code {
            font-size: 6rem;
            font-weight: bold;
            color: #ffc107;
            margin-bottom: 1rem;
        }
        .error-title {
            font-size: 2rem;
            color: #343a40;
            margin-bottom: 1rem;
        }
        .error-message {
            color: #6c757d;
            margin-bottom: 2rem;
        }
        .requested-url {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            color: #6c757d;
            padding: 0.5rem;
            margin: 1rem 0;
            font-family: monospace;
            word-break: break-all;
        }
        .btn-home {
            background-color: #6a9ec0;
            border-color: #6a9ec0;
        }
        .btn-home:hover {
            background-color: #5a8eb0;
            border-color: #5a8eb0;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-message">The page you are looking for does not exist or has been moved.</p>
        
        <div class="requested-url">
            Requested URL: <?php echo $requested_url; ?>
        </div>
        
        <div class="d-flex justify-content-center">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="index.php" class="btn btn-primary btn-home me-2">
                    <i class="fas fa-home me-1"></i> Dashboard
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary btn-home me-2">
                    <i class="fas fa-sign-in-alt me-1"></i> Login
                </a>
            <?php endif; ?>
            
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Go Back
            </a>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
