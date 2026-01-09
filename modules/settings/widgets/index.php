<?php
/**
 * AbroadWorks Management System - Settings Widget
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

// Check if user has permission to view this widget
if (!has_permission('settings-view')) {
    return;
}

// Get maintenance mode status
$maintenance_mode = get_setting('maintenance_mode', '0') == '1';
?>

<div class="col-md-6 mb-4">
    <div class="card h-100">
        <div class="card-header bg-<?php echo $maintenance_mode ? 'warning' : 'primary'; ?> text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-cogs me-2"></i> System Settings
            </h5>
        </div>
        <div class="card-body">
            <?php if ($maintenance_mode): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Maintenance Mode is Active</strong>
                    <p class="mb-0">Only administrators can access the system.</p>
                </div>
            <?php endif; ?>
            
            <div class="mb-3">
                <strong>System Information:</strong>
                <ul class="list-unstyled mt-2">
                    <li><i class="fas fa-server me-2"></i> PHP Version: <?php echo phpversion(); ?></li>
                    <li><i class="fas fa-database me-2"></i> Database: MySQL</li>
                    <li><i class="fas fa-clock me-2"></i> Server Time: <?php echo date('Y-m-d H:i:s'); ?></li>
                    <li><i class="fas fa-globe me-2"></i> Timezone: <?php echo get_setting('timezone', 'UTC'); ?></li>
                </ul>
            </div>
            
            <?php if (has_permission('settings-manage')): ?>
                <div class="text-center">
                    <a href="<?php echo $root_path; ?>modules/settings/" class="btn btn-primary">
                        <i class="fas fa-cog me-1"></i> Manage Settings
                    </a>
                    
                    <?php if (has_permission('settings-manage') && isset($_SESSION['is_owner']) && $_SESSION['is_owner']): ?>
                        <a href="<?php echo $root_path; ?>modules/settings/#backup-restore" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-download me-1"></i> Backup & Restore
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
