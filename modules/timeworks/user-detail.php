<?php
/**
 * TimeWorks Module - User Detail
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Permission check
if (!has_permission('timeworks_users_view')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['timeworks']);
$is_active = $stmt->fetchColumn();
if (!$is_active) {
    header('Location: ../../module-inactive.php');
    exit;
}

// Set timezone to EST
date_default_timezone_set('America/New_York');

$page_title = "TimeWorks - User Detail";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Get user ID from URL
$userId = $_GET['id'] ?? null;

if (!$userId) {
    header('Location: users.php');
    exit;
}

// Get user info
$stmt = $db->prepare("SELECT * FROM twr_users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

// Get user's projects
$stmt = $db->prepare("
    SELECT p.*, up.assigned_at
    FROM twr_projects p
    INNER JOIN twr_user_projects up ON p.project_id = up.project_id
    WHERE up.user_id = ?
    ORDER BY p.name ASC
");
$stmt->execute([$userId]);
$userProjects = $stmt->fetchAll();

// Get user's shifts
$stmt = $db->prepare("
    SELECT * FROM twr_user_shifts
    WHERE user_id = ?
    ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
");
$stmt->execute([$userId]);
$userShifts = $stmt->fetchAll();

// Get user's time entries (last 30 days)
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');

$stmt = $db->prepare("
    SELECT * FROM twr_time_entries_cache
    WHERE user_id = ?
    AND date BETWEEN ? AND ?
    ORDER BY date DESC
");
$stmt->execute([$userId, $startDate, $endDate]);
$timeEntries = $stmt->fetchAll();

// Calculate statistics
$totalHours = 0;
$totalDays = count($timeEntries);
foreach ($timeEntries as $entry) {
    $totalHours += $entry['total_hours'];
}
$avgHoursPerDay = $totalDays > 0 ? $totalHours / $totalDays : 0;

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($user['full_name']); ?>
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                        <li class="breadcrumb-item active">User Detail</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- User Info Card -->
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle"></i> User Information</h3>
                    <div class="card-tools">
                        <a href="users.php" class="btn btn-sm btn-light">
                            <i class="fas fa-arrow-left"></i> Back to Users
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th>Full Name:</th>
                                    <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Role:</th>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($user['roles']); ?></span></td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th>Timezone:</th>
                                    <td><?php echo htmlspecialchars($user['timezone']); ?></td>
                                </tr>
                                <tr>
                                    <th>Last Login:</th>
                                    <td>
                                        <?php
                                        if ($user['last_login_local']) {
                                            echo date('M j, Y H:i', strtotime($user['last_login_local']));
                                        } else {
                                            echo '<span class="text-muted">Never</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Created At:</th>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Last Synced:</th>
                                    <td><?php echo date('M j, Y H:i', strtotime($user['synced_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo count($userProjects); ?></h3>
                            <p>Projects</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-project-diagram"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo $totalDays; ?></h3>
                            <p>Active Days (30d)</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?php echo number_format($totalHours, 1); ?>h</h3>
                            <p>Total Hours (30d)</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?php echo number_format($avgHoursPerDay, 1); ?>h</h3>
                            <p>Avg Hours/Day</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Projects -->
                <div class="col-md-6">
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-project-diagram"></i> Assigned Projects</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($userProjects)): ?>
                                <p class="text-muted">No projects assigned.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Project</th>
                                                <th>Status</th>
                                                <th>Assigned</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($userProjects as $project): ?>
                                                <tr>
                                                    <td>
                                                        <a href="project-detail.php?id=<?php echo $project['project_id']; ?>">
                                                            <?php echo htmlspecialchars($project['name']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $project['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo ucfirst($project['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?php echo date('M j, Y', strtotime($project['assigned_at'])); ?></small>
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

                <!-- Weekly Schedule -->
                <div class="col-md-6">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-calendar-alt"></i> Weekly Schedule</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($userShifts)): ?>
                                <p class="text-muted">No schedule defined.</p>
                            <?php else: ?>
                                <table class="table table-sm">
                                    <?php foreach ($userShifts as $shift): ?>
                                        <tr>
                                            <th><?php echo $shift['day_of_week']; ?>:</th>
                                            <td>
                                                <?php if ($shift['is_off']): ?>
                                                    <span class="badge badge-secondary">OFF</span>
                                                <?php else: ?>
                                                    <?php echo date('H:i', strtotime($shift['start_time'])); ?> -
                                                    <?php echo date('H:i', strtotime($shift['end_time'])); ?>
                                                    <?php
                                                    $start = strtotime($shift['start_time']);
                                                    $end = strtotime($shift['end_time']);
                                                    $hours = ($end - $start) / 3600;
                                                    ?>
                                                    <small class="text-muted">(<?php echo number_format($hours, 1); ?>h)</small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Time Entries (Last 30 Days) -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history"></i> Activity History (Last 30 Days)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($timeEntries)): ?>
                        <p class="text-muted">No activity recorded in the last 30 days.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="timeEntriesTable" class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>First Check-In</th>
                                        <th>Last Check-Out</th>
                                        <th>Total Hours</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($timeEntries as $entry): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($entry['date'])); ?></td>
                                            <td><?php echo date('l', strtotime($entry['date'])); ?></td>
                                            <td>
                                                <?php echo $entry['first_check_in'] ? date('H:i', strtotime($entry['first_check_in'])) : '-'; ?>
                                            </td>
                                            <td>
                                                <?php echo $entry['last_check_out'] ? date('H:i', strtotime($entry['last_check_out'])) : '-'; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo number_format($entry['total_hours'], 2); ?>h</strong>
                                            </td>
                                            <td>
                                                <?php if ($entry['is_absent']): ?>
                                                    <span class="badge badge-danger">Absent</span>
                                                <?php else: ?>
                                                    <?php if ($entry['is_late']): ?>
                                                        <span class="badge badge-warning">Late</span>
                                                    <?php endif; ?>
                                                    <?php if ($entry['is_early_leave']): ?>
                                                        <span class="badge badge-warning">Early Leave</span>
                                                    <?php endif; ?>
                                                    <?php if (!$entry['is_late'] && !$entry['is_early_leave']): ?>
                                                        <span class="badge badge-success">On Time</span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
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

<script>
$(document).ready(function() {
    $('#timeEntriesTable').DataTable({
        responsive: true,
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
        order: [[0, 'desc']],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
    });
});
</script>

<?php include '../../components/footer.php'; ?>
