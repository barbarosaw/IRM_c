<?php
/**
 * AbroadWorks Management System - Auth Widget
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

// Check if user has permission to view this widget
if (!has_permission('auth-widget-view')) {
    return;
}

// Get current user info
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    return;
}

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    return;
}

// Get last login time
$stmt = $db->prepare("
    SELECT created_at 
    FROM activity_logs 
    WHERE user_id = ? AND action = 'auth' AND description = 'User logged in'
    ORDER BY created_at DESC
    LIMIT 1, 1
");
$stmt->execute([$user_id]);
$last_login = $stmt->fetchColumn();

// Get 2FA status
$two_factor_enabled = $user['two_factor_enabled'] ?? 0;
?>

<div class="col-md-6 mb-4">
    <div class="card h-100">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-user-shield me-2"></i> Account Security
            </h5>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center mb-3">
                <div class="flex-shrink-0">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?php echo $root_path; ?><?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="rounded-circle" width="60" height="60">
                    <?php else: ?>
                        <img src="<?php echo $root_path; ?>assets/images/default-avatar.png" alt="Default Avatar" class="rounded-circle" width="60" height="60">
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h5 class="mb-0"><?php echo htmlspecialchars($user['name']); ?></h5>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><i class="fas fa-shield-alt me-2"></i> Two-Factor Authentication:</span>
                    <?php if ($two_factor_enabled): ?>
                        <span class="badge bg-success">Enabled</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Disabled</span>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><i class="fas fa-clock me-2"></i> Last Login:</span>
                    <span><?php echo $last_login ? date('Y-m-d H:i', strtotime($last_login)) : 'N/A'; ?></span>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-key me-2"></i> Password:</span>
                    <span>Last changed: <?php echo date('Y-m-d', strtotime($user['updated_at'])); ?></span>
                </div>
            </div>
            
            <div class="text-center">
                <a href="<?php echo $root_path; ?>profile.php" class="btn btn-primary">
                    <i class="fas fa-user-edit me-1"></i> Manage Profile
                </a>
                
                <?php if (!$two_factor_enabled): ?>
                    <a href="<?php echo $root_path; ?>profile.php#security" class="btn btn-outline-success ms-2">
                        <i class="fas fa-lock me-1"></i> Enable 2FA
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
