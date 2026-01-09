<?php

/**
 * AbroadWorks Management System - Error Page
 * 
 * @author ikinciadam@gmail.com
 */

session_start();
$page_title = 'Error';

// Define MAINTENANCE_BYPASS to allow this page to be accessed even in maintenance mode
define('MAINTENANCE_BYPASS', true);

$error_code = $_GET['code'] ?? 500;
$error_message = '';

switch ($error_code) {
    case 400:
        $error_title = 'Bad Request';
        $error_message = 'The server cannot process the request due to a client error.';
        break;
    case 401:
        $error_title = 'Unauthorized';
        $error_message = 'Authentication is required to access this resource.';
        break;
    case 403:
        $error_title = 'Forbidden';
        $error_message = 'You do not have permission to access this resource.';
        break;
    case 404:
        $error_title = 'Page Not Found';
        $error_message = 'The page you are looking for does not exist or has been moved.';
        break;
    case 500:
        $error_title = 'Server Error';
        $error_message = 'An internal server error occurred. Please try again later.';
        break;
    case 503:
        $error_title = 'Service Unavailable';
        $error_message = 'The server is currently unavailable. Please try again later.';
        break;
    default:
        $error_title = 'Error';
        $error_message = 'An unexpected error has occurred.';
}

// Show detailed error message for debugging if provided
$debug_message = $_GET['debug'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $error_code; ?> <?php echo $error_title; ?> - AbroadWorks Management</title>

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
            color: #dc3545;
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

        .debug-box {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 0.25rem;
            color: #721c24;
            padding: 1rem;
            margin: 1.5rem 0;
            text-align: left;
            overflow-wrap: break-word;
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
        <div class="error-code"><?php echo $error_code; ?></div>
        <h1 class="error-title"><?php echo $error_title; ?></h1>
        <p class="error-message"><?php echo $error_message; ?></p>

        <?php if (!empty($debug_message) && isset($_SESSION['is_owner']) && $_SESSION['is_owner']): ?>
            <div class="debug-box">
                <h5>Debug Information (Only visible to owners)</h5>
                <p><?php echo nl2br(htmlspecialchars($debug_message)); ?></p>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-center">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="index.php" class="btn btn-primary btn-home me-2">
                    <i class="fas fa-home me-1"></i> Dashboard
                </a>
                <a href="logout.php" class="btn btn-danger me-2">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-primary btn-home me-2">
                    <i class="fas fa-sign-in-alt me-1"></i> Login
                </a>
            <?php endif; ?>

            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Go Back
            </a>

            <a href="logout.php" class="btn btn-danger me-2">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>