<?php
/**
 * AbroadWorks Management System - Dashboard View
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Dashboard</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>index.php">Home</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Info boxes -->
            <div class="row">
                <?php if (has_module_access('users') && is_module_visible('users')): ?>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo $user_stats['total']; ?></h3>
                            <p>Total Users</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <a href="<?php echo $root_path; ?>users.php" class="small-box-footer">
                            More info <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo $user_stats['active']; ?></h3>
                            <p>Active Users</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <a href="<?php echo $root_path; ?>users.php" class="small-box-footer">
                            More info <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>                </div>
                <?php endif; ?>
            </div>
            <!-- Main row -->
            <div class="row">
                <!-- Left col -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Welcome to AbroadWorks Management System</h3>
                        </div>
                        <div class="card-body">
                            <p>This is your dashboard where you can manage users, vendors, roles and more.</p>
                            <p>Use the sidebar menu to navigate through the system features.</p>
                            
                            <?php if (is_in_maintenance_mode() && can_bypass_maintenance()): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-tools me-2"></i> <strong>Notice:</strong> The system is currently in maintenance mode. 
                                Only system owners can access the system. 
                                <a href="<?php echo $root_path; ?>settings.php" class="alert-link">Go to Settings</a> to disable maintenance mode.
                            </div>
                            <?php endif; ?>
                            
                            <!-- Module access cards -->
                            <?php if (count($visible_modules) > 0): ?>
                            <div class="mt-4">
                                <h4>Available Modules</h4>
                                <div class="row">
                                    <?php foreach ($visible_modules as $module): ?>
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <a href="<?php echo $root_path . 'modules/' . $module['code'] . '/'; ?>" class="text-decoration-none">
                                                <div class="card h-100 shadow-sm module-card">
                                                    <div class="card-body text-center">
                                                        <div class="mb-3">
                                                            <i class="fas <?php echo $module['icon']; ?> fa-2x text-primary"></i>
                                                        </div>
                                                        <h5 class="card-title"><?php echo htmlspecialchars($module['name']); ?></h5>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Widget Area -->
                            <div class="mt-4" id="dashboard-widgets">
                                <h4>Module Widgets</h4>
                                <div class="row">
                                    <?php 
                                    // Load module widgets
                                    foreach ($all_modules as $module) {
                                        // Check if user has permission to view this widget
                                        if (has_permission($module['code'] . '-widget-view')) {
                                            $widget_file = $root_path . "modules/{$module['code']}/widgets/index.php";
                                            
                                            // If widget exists, include it
                                            if (file_exists($widget_file)) {
                                                include $widget_file;
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                 
                </div>

                <!-- Left col -->
              


            </div>
        </div>
    </div>
</div>
