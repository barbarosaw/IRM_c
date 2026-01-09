<?php
/**
 * AbroadWorks Management System - Dashboard Widget
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

// Check if user has permission to view this widget
if (!has_permission('dashboard-widget-view')) {
    return;
}

// Get system statistics
$total_modules = 0;
$active_modules = 0;

try {
    $stmt = $db->query("SELECT COUNT(*) as total, SUM(is_active) as active FROM modules");
    $result = $stmt->fetch();
    
    $total_modules = $result['total'] ?? 0;
    $active_modules = $result['active'] ?? 0;
} catch (Exception $e) {
    // Ignore errors
}
?>

<div class="col-md-6 mb-4">
    <div class="card h-100">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-tachometer-alt me-2"></i> System Overview
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <span class="info-box-text text-center text-muted">Total Modules</span>
                            <span class="info-box-number text-center text-muted mb-0"><?php echo $total_modules; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <span class="info-box-text text-center text-muted">Active Modules</span>
                            <span class="info-box-number text-center text-muted mb-0"><?php echo $active_modules; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <h6 class="text-muted">System Status</h6>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        PHP Memory Limit
                        <span class="badge bg-primary rounded-pill"><?php echo ini_get('memory_limit'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        PHP Max Execution Time
                        <span class="badge bg-primary rounded-pill"><?php echo ini_get('max_execution_time'); ?>s</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        PHP Upload Max Filesize
                        <span class="badge bg-primary rounded-pill"><?php echo ini_get('upload_max_filesize'); ?></span>
                    </li>
                </ul>
            </div>
        </div>
        <div class="card-footer text-center">
            <a href="<?php echo $root_path; ?>modules/dashboard/" class="btn btn-sm btn-outline-primary">View Dashboard</a>
        </div>
    </div>
</div>
