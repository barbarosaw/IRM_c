<?php
/**
 * AbroadWorks Management System - Access Denied Page
 * 
 * @author ikinciadam@gmail.com
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in, otherwise redirect to login page
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$page_title = 'Access Denied';
require_once 'components/header.php';
require_once 'components/sidebar.php';

// Get current page if available
$current_page = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
$page_name = $current_page ? basename($current_page) : 'the requested page';

// Log the access denied attempt
log_activity($_SESSION['user_id'], 'security', null, "Access denied to $page_name");
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Access Denied</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">Access Denied</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card card-danger card-outline">
                <div class="card-body text-center py-5">
                    <i class="fas fa-exclamation-triangle text-danger fa-4x mb-4"></i>
                    <h3 class="text-danger">Access Denied</h3>
                    <p class="lead my-4">
                        Sorry, you do not have permission to access this page or feature. 
                        <br>Please contact an administrator if you believe this is an error.
                    </p>
                    <div>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-home me-2"></i> Return to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'components/footer.php'; ?>
