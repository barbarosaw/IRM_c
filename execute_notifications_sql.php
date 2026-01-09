<?php
/**
 * AbroadWorks Management System - Execute Notifications SQL
 * 
 * This script executes the SQL statements to create the notifications module tables.
 * 
 * @author System Generated
 */

// Include required files
require_once 'includes/init.php';

// Check if user is logged in and is admin


// Set page title
$page_title = "Execute Notifications SQL";

// Function to execute SQL file
function execute_sql_file($db, $file) {
    $success = true;
    $error_message = '';
    
    try {
        // Read the SQL file
        $sql = file_get_contents($file);
        
        // Execute the SQL directly
        $db->exec($sql);
        
    } catch (PDOException $e) {
        $success = false;
        $error_message = "Database error: " . $e->getMessage();
    }
    
    return ['success' => $success, 'message' => $error_message];
}

// Execute the SQL file
$result = execute_sql_file($db, 'database/schema_notifications.sql');

// Include header and sidebar
include 'components/header.php';
include 'components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Execute Notifications SQL</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">Execute Notifications SQL</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">SQL Execution Result</h3>
                </div>
                <div class="card-body">
                    <?php if ($result['success']): ?>
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle me-2"></i>Success!</h5>
                            <p>The notifications module tables have been created successfully.</p>
                        </div>
                        <p>The following tables were created or updated:</p>
                        <ul>
                            <li><code>messages</code> - For storing user messages</li>
                            <li><code>system_notifications</code> - For storing system notifications</li>
                            <li><code>user_notifications</code> - For mapping notifications to users</li>
                        </ul>
                        <p>The following permissions were added:</p>
                        <ul>
                            <li><code>notifications-view</code> - Can view notifications</li>
                            <li><code>notifications-manage</code> - Can manage notifications</li>
                            <li><code>messages-send</code> - Can send messages to other users</li>
                        </ul>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-exclamation-triangle me-2"></i>Error!</h5>
                            <p>There was an error executing the SQL statements:</p>
                            <pre><?php echo $result['message']; ?></pre>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
                    <?php if ($result['success']): ?>
                        <a href="modules/notifications/" class="btn btn-success ms-2">Go to Notifications Module</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>
