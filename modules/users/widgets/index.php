<?php
/**
 * AbroadWorks Management System - Users Widget
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

// Check if user has permission to view this widget
if (!has_permission('users-widget-view')) {
    return;
}

// Include model if not already included
if (!class_exists('User')) {
    require_once $root_path . 'modules/users/models/User.php';
}

// Initialize model
$userModel = new User();

// Get user statistics
$user_stats = $userModel->getUserStatistics();

// Get recent users
$recent_users = $userModel->getRecentUsers(5);
?>

<div class="col-md-6 mb-4">
    <div class="card h-100">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-users me-2"></i> User Statistics
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <span class="info-box-text text-center text-muted">Total Users</span>
                            <span class="info-box-number text-center text-muted mb-0"><?php echo $user_stats['total']; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <span class="info-box-text text-center text-muted">Active</span>
                            <span class="info-box-number text-center text-success mb-0"><?php echo $user_stats['active']; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <span class="info-box-text text-center text-muted">Inactive</span>
                            <span class="info-box-number text-center text-danger mb-0"><?php echo $user_stats['inactive']; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($recent_users)): ?>
                <div class="mt-4">
                    <h6 class="text-muted">Recently Added Users</h6>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recent_users as $user): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="<?php echo $root_path; ?>user-view.php?id=<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </a>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($user['email']); ?></small>
                                </div>
                                <span class="badge <?php echo $user['is_active'] ? 'bg-success' : 'bg-danger'; ?> rounded-pill">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer text-center">
            <a href="<?php echo $root_path; ?>modules/users/" class="btn btn-sm btn-outline-primary">Manage Users</a>
            <?php if (has_permission('users-manage')): ?>
                <a href="<?php echo $root_path; ?>user-add.php" class="btn btn-sm btn-outline-success">Add New User</a>
            <?php endif; ?>
        </div>
    </div>
</div>
