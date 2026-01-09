<?php
/**
 * AbroadWorks Management System - Module Inactive
 * 
 * @author ikinciadam@gmail.com
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$page_title = 'Module Inactive';
$module_code = isset($_GET['module']) ? clean_input($_GET['module']) : '';
$module_name = '';

// Get module details if module code is provided
if (!empty($module_code)) {
    $stmt = $db->prepare("SELECT name FROM modules WHERE code = ?");
    $stmt->execute([$module_code]);
    $module_name = $stmt->fetchColumn();
}

if (empty($module_name)) {
    $module_name = 'Unknown Module';
}

// Include header and sidebar
require_once 'components/header.php';
require_once 'components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Module Inactive</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">Module Inactive</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-ban text-danger" style="font-size: 5rem;"></i>
                    </div>
                    <h2 class="mb-3">Module is Currently Inactive</h2>
                    <p class="lead mb-4">
                        The <strong><?php echo htmlspecialchars($module_name); ?></strong> module is currently inactive.
                        <?php if ($module_code == 'vendor'): ?>
                            This module is currently under maintenance.
                        <?php endif; ?>
                        <?php if (is_admin()): ?>
                            As an administrator, you can activate this module from the Module Management page.
                        <?php else: ?>
                            Please contact your system administrator to activate this module.
                        <?php endif; ?>
                    </p>
                    
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary me-2">
                            <i class="fas fa-home me-1"></i> Go to Dashboard
                        </a>
                        
                        <?php if (is_admin()): ?>
                            <a href="module-management.php" class="btn btn-success">
                                <i class="fas fa-cogs me-1"></i> Manage Modules
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'components/footer.php'; ?>
