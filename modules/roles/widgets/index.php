<?php
/**
 * AbroadWorks Management System - Roles Widget
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

// Check if user has permission to view this widget
if (!has_permission('roles-widget-view')) {
    return;
}

// Include model if not already included
if (!class_exists('Role')) {
    require_once $root_path . 'modules/roles/models/Role.php';
}

// Initialize model
$roleModel = new Role();

// Get roles with counts
$roles = $roleModel->getAllRolesWithCounts();

// Calculate total permissions and users
$total_permissions = 0;
$total_users = 0;
foreach ($roles as $role) {
    $total_permissions += $role['permission_count'];
    $total_users += $role['user_count'];
}
?>

<div class="col-md-6 mb-4">
    <div class="card h-100">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-user-tag me-2"></i> Roles & Permissions
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <span class="info-box-text text-center text-muted">Total Roles</span>
                            <span class="info-box-number text-center text-muted mb-0"><?php echo count($roles); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <span class="info-box-text text-center text-muted">Permissions</span>
                            <span class="info-box-number text-center text-primary mb-0"><?php echo $total_permissions; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <span class="info-box-text text-center text-muted">Assigned Users</span>
                            <span class="info-box-number text-center text-success mb-0"><?php echo $total_users; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($roles)): ?>
                <div class="mt-4">
                    <h6 class="text-muted">Role Overview</h6>
                    <ul class="list-group list-group-flush">
                        <?php foreach (array_slice($roles, 0, 5) as $role): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($role['name']); ?></strong>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars(substr($role['description'] ?? '', 0, 50)) . (strlen($role['description'] ?? '') > 50 ? '...' : ''); ?></small>
                                </div>
                                <div>
                                    <span class="badge bg-info me-1"><?php echo $role['permission_count']; ?> perms</span>
                                    <span class="badge bg-primary"><?php echo $role['user_count']; ?> users</span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer text-center">
            <a href="<?php echo $root_path; ?>modules/roles/" class="btn btn-sm btn-outline-primary">Manage Roles</a>
            <?php if (has_permission('roles-manage')): ?>
                <a href="<?php echo $root_path; ?>role-add.php" class="btn btn-sm btn-outline-success">Add New Role</a>
            <?php endif; ?>
        </div>
    </div>
</div>
