<?php
/**
 * TimeWorks Module - Main Dashboard View
 *
 * @author ikinciadam@gmail.com
 */

// Get dashboard statistics
$stats = [];

// Total users
$stmt = $db->query("SELECT COUNT(*) as total FROM twr_users WHERE status = 'active'");
$stats['total_users'] = $stmt->fetchColumn();

// Total projects
$stmt = $db->query("SELECT COUNT(*) as total FROM twr_projects WHERE status = 'active'");
$stats['total_projects'] = $stmt->fetchColumn();

// Active projects (in progress)
$stmt = $db->query("SELECT COUNT(*) as total FROM twr_projects WHERE status = 'active' AND progress = 'In Progress'");
$stats['active_projects'] = $stmt->fetchColumn();

// Total user-project assignments
$stmt = $db->query("SELECT COUNT(*) as total FROM twr_user_projects");
$stats['total_assignments'] = $stmt->fetchColumn();

// Recent sync info
$stmt = $db->query("SELECT * FROM twr_sync_log ORDER BY completed_at DESC LIMIT 1");
$lastSync = $stmt->fetch();

// Get projects by status
$stmt = $db->query("
    SELECT progress, COUNT(*) as count
    FROM twr_projects
    WHERE status = 'active'
    GROUP BY progress
");
$projectsByProgress = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get recent users (last 10)
$stmt = $db->query("
    SELECT user_id, full_name, email, roles, last_login_local, created_at
    FROM twr_users
    WHERE status = 'active'
    ORDER BY synced_at DESC
    LIMIT 10
");
$recentUsers = $stmt->fetchAll();

// Get top projects by member count
$stmt = $db->query("
    SELECT name, member_count, progress, is_billable, task_count
    FROM twr_projects
    WHERE status = 'active'
    ORDER BY member_count DESC
    LIMIT 8
");
$topProjects = $stmt->fetchAll();

// Get user distribution by role
$stmt = $db->query("
    SELECT roles, COUNT(*) as count
    FROM twr_users
    WHERE status = 'active'
    GROUP BY roles
    ORDER BY count DESC
");
$usersByRole = $stmt->fetchAll();
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-clock"></i> TimeWorks Dashboard
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item active">TimeWorks</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- Statistics Cards -->
            <div class="row">
                <!-- Total Users -->
                <div class="col-lg-6 col-12">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo number_format($stats['total_users']); ?></h3>
                            <p>Active Users</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <a href="users.php" class="small-box-footer">
                            View All Users <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Total Projects -->
                <div class="col-lg-6 col-12">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo number_format($stats['total_projects']); ?></h3>
                            <p>Active Projects</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                        <a href="projects.php" class="small-box-footer">
                            View All Projects <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-md-12 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-tools"></i> Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong class="text-muted">Core Functions</strong>
                            </div>
                            <a href="users.php" class="btn btn-primary mr-2 mb-2">
                                <i class="fas fa-users"></i> Users
                            </a>
                            <a href="projects.php" class="btn btn-success mr-2 mb-2">
                                <i class="fas fa-project-diagram"></i> Projects
                            </a>
                            <a href="shifts.php" class="btn btn-info mr-2 mb-2">
                                <i class="fas fa-calendar-alt"></i> Shifts
                            </a>
                            <a href="clients.php" class="btn btn-secondary mr-2 mb-2">
                                <i class="fas fa-building"></i> Clients
                            </a>
                            <a href="sync.php" class="btn btn-warning mr-2 mb-2">
                                <i class="fas fa-sync-alt"></i> Sync Data
                            </a>

                            <hr>

                            <div class="mb-3">
                                <strong class="text-muted">Attendance & Leave</strong>
                            </div>
                            <a href="late-management.php" class="btn btn-danger mr-2 mb-2">
                                <i class="fas fa-user-clock"></i> Late Management
                            </a>
                            <a href="leave-requests.php" class="btn btn-info mr-2 mb-2">
                                <i class="fas fa-calendar-check"></i> Leave Requests
                            </a>
                            <a href="category-management.php" class="btn btn-secondary mr-2 mb-2">
                                <i class="fas fa-tags"></i> Categories
                            </a>

                            <hr>

                            <div class="mb-3">
                                <strong class="text-muted">Reports</strong>
                            </div>
                            <a href="daily-report.php" class="btn btn-primary mr-2 mb-2">
                                <i class="fas fa-calendar-day"></i> Daily Report
                            </a>
                            <a href="reports.php" class="btn btn-dark mr-2 mb-2">
                                <i class="fas fa-chart-line"></i> Activity Reports
                            </a>
                            <a href="reports/by-category.php" class="btn btn-success mr-2 mb-2">
                                <i class="fas fa-chart-pie"></i> Category Reports
                            </a>
                            <a href="reports/period-report.php" class="btn btn-warning mr-2 mb-2">
                                <i class="fas fa-calendar-week"></i> Period Reports
                            </a>

                            <hr>

                            <div class="mb-3">
                                <strong class="text-muted">Settings & Tools</strong>
                            </div>
                            <a href="faq-management.php" class="btn btn-outline-primary mr-2 mb-2">
                                <i class="fas fa-question-circle"></i> FAQ Management
                            </a>
                            <a href="password-reset.php" target="_blank" class="btn btn-outline-secondary mr-2 mb-2">
                                <i class="fas fa-key"></i> Password Reset Page
                            </a>
                            <a href="faq.php" target="_blank" class="btn btn-outline-info mr-2 mb-2">
                                <i class="fas fa-external-link-alt"></i> Public FAQ
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Last Sync Info -->
            <?php if ($lastSync): ?>
            <div class="row">
                <div class="col-md-12">
                    <div class="alert <?php echo $lastSync['status'] === 'success' ? 'alert-success' : 'alert-warning'; ?>">
                        <i class="fas fa-info-circle"></i>
                        <strong>Last Sync:</strong>
                        <?php echo date('M j, Y H:i', strtotime($lastSync['completed_at'])); ?>
                        |
                        <strong>Records Processed:</strong> <?php echo number_format($lastSync['records_processed']); ?>
                        |
                        <strong>Duration:</strong> <?php echo $lastSync['duration_seconds']; ?>s
                        |
                        <strong>Status:</strong>
                        <span class="badge badge-<?php echo $lastSync['status'] === 'success' ? 'success' : 'warning'; ?>">
                            <?php echo strtoupper($lastSync['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Top Projects by Members -->
                <div class="col-md-6">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-trophy"></i> Top Projects by Team Size</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($topProjects)): ?>
                                <p class="text-muted">No projects found.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Project</th>
                                                <th>Members</th>
                                                <th>Tasks</th>
                                                <th>Progress</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topProjects as $project):
                                                $progressBadge = [
                                                    'Pending' => 'secondary',
                                                    'In Progress' => 'primary',
                                                    'Completed' => 'success',
                                                    'On Hold' => 'warning'
                                                ];
                                                $badgeClass = $progressBadge[$project['progress']] ?? 'info';
                                            ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($project['name']); ?>
                                                        <?php if ($project['is_billable']): ?>
                                                            <i class="fas fa-dollar-sign text-success" title="Billable"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-info">
                                                            <?php echo number_format($project['member_count']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-secondary">
                                                            <?php echo number_format($project['task_count']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $badgeClass; ?>">
                                                            <?php echo htmlspecialchars($project['progress']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Users by Role -->
                <div class="col-md-6">
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-user-tag"></i> Users by Role</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($usersByRole)): ?>
                                <p class="text-muted">No user data available.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Role</th>
                                                <th>Count</th>
                                                <th>Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $totalRoleUsers = array_sum(array_column($usersByRole, 'count'));
                                            foreach ($usersByRole as $role):
                                                $percentage = ($role['count'] / $totalRoleUsers) * 100;
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($role['roles']); ?></td>
                                                    <td>
                                                        <span class="badge badge-primary">
                                                            <?php echo number_format($role['count']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="progress" style="height: 20px; position: relative;">
                                                            <div class="progress-bar bg-success" role="progressbar"
                                                                 style="width: <?php echo $percentage; ?>%"
                                                                 aria-valuenow="<?php echo $percentage; ?>"
                                                                 aria-valuemin="0" aria-valuemax="100">
                                                            </div>
                                                            <span style="position: absolute; width: 100%; text-align: center; line-height: 20px; color: #000; font-weight: 600;">
                                                                <?php echo number_format($percentage, 1); ?>%
                                                            </span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </div>
</div>
