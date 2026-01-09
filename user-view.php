<?php

/**
 * AbroadWorks Management System - View User Details
 * 
 * @author ikinciadam@gmail.com
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user has permission to view users
check_page_access('users-view');

$page_title = 'View User';
$user = null;
$user_roles = [];
$user_activity = [];

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: users.php');
    exit;
}

$user_id = (int)$_GET['id'];

// Get user details
$stmt = $db->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM sessions WHERE user_id = u.id AND expired = 0) as active_sessions
    FROM users u
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

// Get user roles
$stmt = $db->prepare("
    SELECT r.*
    FROM roles r
    JOIN user_roles ur ON r.id = ur.role_id
    WHERE ur.user_id = ?
");
$stmt->execute([$user_id]);
$user_roles = $stmt->fetchAll();

// Get user activity logs (limited to 10 most recent)
$stmt = $db->prepare("
    SELECT *
    FROM activity_logs
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$user_activity = $stmt->fetchAll();

// Include header and sidebar
require_once 'components/header.php';
require_once 'components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">User Details</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                        <li class="breadcrumb-item active">View User</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-4">
                    <div class="card card-primary card-outline">
                        <div class="card-body box-profile">
                            <div class="text-center">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img class="profile-user-img img-fluid img-circle"
                                        src="assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>"
                                        alt="User profile picture"
                                        style="width: 150px; height: 150px; object-fit: cover;">
                                <?php else: ?>
                                    <img class="profile-user-img img-fluid img-circle"
                                        src="assets/images/default-avatar.png"
                                        alt="User profile picture"
                                        style="width: 150px; height: 150px; object-fit: cover;">
                                <?php endif; ?>
                            </div>

                            <h3 class="profile-username text-center"><?php echo htmlspecialchars($user['name']); ?></h3>
                            <p class="text-muted text-center"><?php echo implode(', ', array_column($user_roles, 'name')); ?></p>

                            <ul class="list-group list-group-unbordered mb-3">
                                <li class="list-group-item">
                                    <b>Email</b> <a class="float-end"><?php echo htmlspecialchars($user['email']); ?></a>
                                </li>
                                <li class="list-group-item">
                                    <b>Status</b>
                                    <span class="float-end">
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </span>
                                </li>
                                <li class="list-group-item">
                                    <b>Created On</b> <span class="float-end"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                                </li>
                                <li class="list-group-item">
                                    <b>Last Login</b>
                                    <span class="float-end">
                                        <?php echo $user['last_login_at'] ? date('M d, Y H:i', strtotime($user['last_login_at'])) : 'Never'; ?>
                                    </span>
                                </li>
                                <li class="list-group-item">
                                    <b>Active Sessions</b> <span class="float-end"><?php echo $user['active_sessions']; ?></span>
                                </li>
                            </ul>

                            <?php if (has_permission('users-manage')): ?>
                                <div class="d-flex">
                                    <a href="user-edit.php?id=<?php echo $user['id']; ?>" class="btn btn-primary flex-grow-1 me-2">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <form method="post" action="users.php" class="flex-grow-1" onsubmit="return confirm('Are you sure you want to <?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?> this user?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                            <button type="submit" name="toggle_status" class="btn btn-<?php echo $user['is_active'] ? 'warning' : 'success'; ?> w-100">
                                                <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        <form method="post" action="terminate-session.php" class="flex-grow-1">
                                            <input type="hidden" name="terminate_all_forId" value="1">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="terminate_all" class="btn btn-danger w-100" onclick="return confirm('Are you sure you want to terminate sessions of this user?');">
                                                <i class="fas fa-ban me-2"></i> Terminate Sessions
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">User Roles</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($user_roles)): ?>
                                <p class="text-muted">No roles assigned to this user.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Role Name</th>
                                                <th>Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($user_roles as $role): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-primary"><?php echo htmlspecialchars($role['name']); ?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($role['description'] ?? 'No description'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (has_permission('logs-view')): ?>
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <h3 class="card-title">Recent Activity</h3>
                            </div>
                            <div class="card-body">
                                <?php if (empty($user_activity)): ?>
                                    <p class="text-muted">No activity recorded for this user.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Action</th>
                                                    <th>Description</th>
                                                    <th>Date/Time</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($user_activity as $activity): ?>
                                                    <tr>
                                                        <td>
                                                            <?php
                                                            $badge_class = 'bg-info';
                                                            if ($activity['action'] == 'create') $badge_class = 'bg-success';
                                                            if ($activity['action'] == 'update') $badge_class = 'bg-warning';
                                                            if ($activity['action'] == 'delete') $badge_class = 'bg-danger';
                                                            ?>
                                                            <span class="badge <?php echo $badge_class; ?>">
                                                                <?php echo htmlspecialchars(ucfirst($activity['action'])); ?>
                                                            </span>
                                                            <?php if ($activity['entity_type']): ?>
                                                                <small class="text-muted"><?php echo htmlspecialchars($activity['entity_type']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                                        <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <a href="activity-logs.php?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info mt-2">
                                        View All Activity
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'components/footer.php'; ?>