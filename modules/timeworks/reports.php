<?php
/**
 * TimeWorks Module - Activity Reports
 *
 * Activity analysis report via API
 * - Check activity via API in chunks of 50
 * - Users without activity (first table)
 * - Users with activity (second table)
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Permission check
if (!has_permission('timeworks_reports_view')) {
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

$page_title = "TimeWorks - Activity Reports";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Define system constant for TimeWorksAPI
if (!defined('AW_SYSTEM')) {
    define('AW_SYSTEM', true);
}

// Load TimeWorks API
require_once $root_dir . '/modules/timeworks/models/TimeWorksAPI.php';
$api = new TimeWorksAPI($db);

// Handle AJAX requests for activity check
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'check_activity_chunk') {
        $offset = (int)($_POST['offset'] ?? 0);
        $startDate = $_POST['start_date'] ?? date('Y-m-d', strtotime('-60 days'));
        $endDate = $_POST['end_date'] ?? date('Y-m-d');
        $limit = 50;

        // Get only users WITHOUT activity (never checked OR no activity recorded)
        $stmt = $db->prepare("
            SELECT user_id, full_name, email, roles, last_login_local, created_at
            FROM twr_users
            WHERE status = 'active'
              AND (activity_checked_at IS NULL OR (activity_days = 0 AND activity_hours = 0))
            ORDER BY full_name ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $users = $stmt->fetchAll();

        if (empty($users)) {
            echo json_encode([
                'success' => true,
                'done' => true,
                'processed' => 0,
                'with_activity' => 0,
                'without_activity' => 0
            ]);
            exit;
        }

        $withActivity = 0;
        $withoutActivity = 0;
        $results = [];

        foreach ($users as $user) {
            // Call API to check activity
            $timeSheet = $api->getUserTimeSheet(
                $user['user_id'],
                $startDate,
                $endDate,
                'America/New_York',
                62, // Max days to check
                0
            );

            $hasActivity = false;
            $totalHours = 0;
            $activeDays = 0;
            $lastActivityDate = null;
            $firstActivityDate = null;

            // API returns report as associative array with date keys
            // Example: "2025-12-17" => { "total_user_duration_seconds": 29038, ... }
            if ($timeSheet && isset($timeSheet['report']) && is_array($timeSheet['report'])) {
                foreach ($timeSheet['report'] as $dateKey => $dayData) {
                    // Check for duration in seconds
                    $durationSeconds = 0;
                    if (isset($dayData['total_user_duration_seconds'])) {
                        $durationSeconds = (int)$dayData['total_user_duration_seconds'];
                    } elseif (isset($dayData['day_total_duration_second'])) {
                        $durationSeconds = (int)$dayData['day_total_duration_second'];
                    }

                    if ($durationSeconds > 0) {
                        $hasActivity = true;
                        $totalHours += $durationSeconds / 3600; // Convert seconds to hours
                        $activeDays++;

                        // Date is the key of the array
                        if (!$firstActivityDate || $dateKey < $firstActivityDate) {
                            $firstActivityDate = $dateKey;
                        }
                        if (!$lastActivityDate || $dateKey > $lastActivityDate) {
                            $lastActivityDate = $dateKey;
                        }
                    }
                }
            }

            // Save activity results to database
            $updateStmt = $db->prepare("
                UPDATE twr_users
                SET last_activity_date = ?,
                    activity_days = ?,
                    activity_hours = ?,
                    activity_checked_at = NOW()
                WHERE user_id = ?
            ");
            $updateStmt->execute([
                $lastActivityDate,
                $activeDays,
                round($totalHours, 2),
                $user['user_id']
            ]);

            if ($hasActivity) {
                $withActivity++;
                $avgHours = $activeDays > 0 ? $totalHours / $activeDays : 0;

                $results[] = [
                    'type' => 'active',
                    'user_id' => $user['user_id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'roles' => $user['roles'],
                    'active_days' => $activeDays,
                    'total_hours' => round($totalHours, 1),
                    'avg_hours' => round($avgHours, 1),
                    'last_activity' => $lastActivityDate ? date('M j, Y', strtotime($lastActivityDate)) : '-',
                    'first_activity' => $firstActivityDate ? date('M j, Y', strtotime($firstActivityDate)) : '-'
                ];
            } else {
                $withoutActivity++;
                $results[] = [
                    'type' => 'inactive',
                    'user_id' => $user['user_id'],
                    'full_name' => $user['full_name'],
                    'email' => $user['email'],
                    'roles' => $user['roles'],
                    'last_login' => $user['last_login_local'] ? date('M j, Y', strtotime($user['last_login_local'])) : 'Never',
                    'created_at' => date('M j, Y', strtotime($user['created_at']))
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'done' => false,
            'processed' => count($users),
            'with_activity' => $withActivity,
            'without_activity' => $withoutActivity,
            'results' => $results,
            'next_offset' => $offset + $limit
        ]);
        exit;
    }

    if ($_POST['action'] === 'get_user_count') {
        // Count users WITHOUT activity (those that need to be checked)
        $stmt = $db->query("
            SELECT COUNT(*) FROM twr_users
            WHERE status = 'active'
              AND (activity_checked_at IS NULL OR (activity_days = 0 AND activity_hours = 0))
        ");
        $totalUsers = $stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'total' => (int)$totalUsers
        ]);
        exit;
    }

    // Check single user activity for today
    if ($_POST['action'] === 'check_single_user') {
        $userId = $_POST['user_id'] ?? '';

        if (empty($userId)) {
            echo json_encode(['success' => false, 'message' => 'User ID required']);
            exit;
        }

        // Get user details
        $stmt = $db->prepare("SELECT user_id, full_name, email, roles FROM twr_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        // Check today's activity only
        $today = date('Y-m-d');

        $timeSheet = $api->getUserTimeSheet(
            $userId,
            $today,
            $today,
            'America/New_York',
            1,
            0
        );

        $hasActivity = false;
        $totalSeconds = 0;

        if ($timeSheet && isset($timeSheet['report']) && is_array($timeSheet['report'])) {
            foreach ($timeSheet['report'] as $dateKey => $dayData) {
                $durationSeconds = 0;
                if (isset($dayData['total_user_duration_seconds'])) {
                    $durationSeconds = (int)$dayData['total_user_duration_seconds'];
                } elseif (isset($dayData['day_total_duration_second'])) {
                    $durationSeconds = (int)$dayData['day_total_duration_second'];
                }

                if ($durationSeconds > 0) {
                    $hasActivity = true;
                    $totalSeconds = $durationSeconds;
                }
            }
        }

        // If activity found today, update the user record
        if ($hasActivity) {
            // Get current activity data and add today
            $stmt = $db->prepare("SELECT activity_days, activity_hours, last_activity_date FROM twr_users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $currentData = $stmt->fetch();

            $newDays = ($currentData['activity_days'] ?? 0) + 1;
            $newHours = ($currentData['activity_hours'] ?? 0) + ($totalSeconds / 3600);
            $lastActivity = $today; // Today is the new last activity date

            $updateStmt = $db->prepare("
                UPDATE twr_users
                SET last_activity_date = ?,
                    activity_days = ?,
                    activity_hours = ?,
                    activity_checked_at = NOW()
                WHERE user_id = ?
            ");
            $updateStmt->execute([$lastActivity, $newDays, round($newHours, 2), $userId]);
        } else {
            // Just update the check timestamp
            $updateStmt = $db->prepare("UPDATE twr_users SET activity_checked_at = NOW() WHERE user_id = ?");
            $updateStmt->execute([$userId]);
        }

        $totalHours = round($totalSeconds / 3600, 2);

        echo json_encode([
            'success' => true,
            'has_activity' => $hasActivity,
            'user_id' => $userId,
            'full_name' => $user['full_name'],
            'today_hours' => $totalHours,
            'message' => $hasActivity
                ? "Activity found for today: {$totalHours} hours"
                : "No activity recorded for today"
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Get all active users for initial display (including activity data)
$stmt = $db->query("
    SELECT user_id, full_name, email, roles, status, last_login_local, created_at,
           last_activity_date, activity_days, activity_hours, activity_checked_at
    FROM twr_users
    WHERE status = 'active'
    ORDER BY full_name ASC
");
$allUsers = $stmt->fetchAll();

// Calculate default date range
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-60 days'));

// Separate users by activity status (for users that have been checked)
$usersWithActivity = [];
$usersWithoutActivity = [];
$uncheckedUsers = [];
$lastCheckTime = null;

foreach ($allUsers as $user) {
    if ($user['activity_checked_at']) {
        if (!$lastCheckTime || $user['activity_checked_at'] > $lastCheckTime) {
            $lastCheckTime = $user['activity_checked_at'];
        }
        if ($user['activity_days'] > 0 || $user['activity_hours'] > 0) {
            $usersWithActivity[] = $user;
        } else {
            $usersWithoutActivity[] = $user;
        }
    } else {
        $uncheckedUsers[] = $user;
    }
}

$hasCheckedData = !empty($usersWithActivity) || !empty($usersWithoutActivity);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-chart-line"></i> Activity Reports
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Reports</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- Activity Check Card -->
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-search"></i> Activity Check</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" id="startDate" class="form-control" value="<?php echo $startDate; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>End Date</label>
                                <input type="date" id="endDate" class="form-control" value="<?php echo $endDate; ?>" max="<?php echo $endDate; ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Users to Check</label>
                                <input type="text" id="usersToCheckCount" class="form-control" value="<?php echo count($usersWithoutActivity) + count($uncheckedUsers); ?>" readonly>
                                <small class="text-muted">WITHOUT Activity / Unchecked</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" id="btnCheckActivity" class="btn btn-warning btn-block">
                                    <i class="fas fa-search"></i> Check WITHOUT Activity Users
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Progress (hidden initially) -->
                    <div id="progressSection" style="display: none;">
                        <hr>
                        <div class="progress" style="height: 25px;">
                            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <p class="mt-2 text-center" id="progressText">Processing...</p>
                        <div class="row mt-3">
                            <div class="col-md-4 text-center">
                                <h4 id="statProcessed">0</h4>
                                <small class="text-muted">Processed</small>
                            </div>
                            <div class="col-md-4 text-center">
                                <h4 id="statWithActivity" class="text-success">0</h4>
                                <small class="text-muted">With Activity</small>
                            </div>
                            <div class="col-md-4 text-center">
                                <h4 id="statWithoutActivity" class="text-danger">0</h4>
                                <small class="text-muted">Without Activity</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Statistics -->
            <?php
            $withActivityCount = count($usersWithActivity);
            $withoutActivityCount = count($usersWithoutActivity);
            $checkedTotal = $withActivityCount + $withoutActivityCount;
            $activityRate = $checkedTotal > 0 ? round(($withActivityCount / $checkedTotal) * 100, 1) : 0;
            ?>
            <div id="summaryStats" class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3 id="totalUsersCard"><?php echo count($allUsers); ?></h3>
                            <p>Total Active Users</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3 id="withActivityCard"><?php echo $hasCheckedData ? $withActivityCount : '-'; ?></h3>
                            <p>With Activity</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3 id="withoutActivityCard"><?php echo $hasCheckedData ? $withoutActivityCount : '-'; ?></h3>
                            <p>Without Activity</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3 id="activityRateCard"><?php echo $hasCheckedData ? $activityRate . '%' : '-%'; ?></h3>
                            <p>Activity Rate</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users WITHOUT Activity Table (First) -->
            <div class="card card-danger">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-times-circle"></i> Users WITHOUT Activity
                        <span id="inactiveCount" class="badge badge-light ml-2"><?php echo $withoutActivityCount; ?></span>
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-warning btn-sm" id="btnBulkEmail" title="Send bulk email to users">
                            <i class="fas fa-paper-plane"></i> Bulk Send Email
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($hasCheckedData && $lastCheckTime): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Last Check:</strong> <?php echo date('M j, Y H:i:s', strtotime($lastCheckTime)); ?> (EST)
                        - Click "Check Activity via API" to refresh data.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Note:</strong> Click "Check Activity via API" to scan users for activity in the selected date range.
                    </div>
                    <?php endif; ?>
                    <div class="table-responsive">
                        <table id="inactiveUsersTable" class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Last Login</th>
                                    <th>Account Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersWithoutActivity as $user): ?>
                                <tr data-user-id="<?php echo htmlspecialchars($user['user_id']); ?>">
                                    <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($user['roles']); ?></span></td>
                                    <td><?php echo $user['last_login_local'] ? date('M j, Y', strtotime($user['last_login_local'])) : '<span class="text-muted">Never</span>'; ?></td>
                                    <td><small class="text-muted"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></small></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info btn-check-today" data-user-id="<?php echo htmlspecialchars($user['user_id']); ?>" data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>" title="Check today's activity">
                                            <i class="fas fa-calendar-day"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-success btn-send-email" data-user-id="<?php echo htmlspecialchars($user['user_id']); ?>" data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>" data-user-email="<?php echo htmlspecialchars($user['email']); ?>" title="Send email">
                                            <i class="fas fa-envelope"></i>
                                        </button>
                                        <a href="users.php" class="btn btn-sm btn-primary" title="Manage users"><i class="fas fa-user-cog"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Users WITH Activity Table (Second) -->
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-check-circle"></i> Users WITH Activity
                        <span id="activeCount" class="badge badge-light ml-2"><?php echo $withActivityCount; ?></span>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="activeUsersTable" class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Active Days</th>
                                    <th>Total Hours</th>
                                    <th>Avg Hours/Day</th>
                                    <th>Last Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersWithActivity as $user):
                                    $avgHours = $user['activity_days'] > 0 ? $user['activity_hours'] / $user['activity_days'] : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($user['roles']); ?></span></td>
                                    <td><span class="badge badge-info"><?php echo $user['activity_days']; ?> days</span></td>
                                    <td><?php echo round($user['activity_hours'], 1); ?> hrs</td>
                                    <td><?php echo round($avgHours, 1); ?> hrs</td>
                                    <td><?php echo $user['last_activity_date'] ? date('M j, Y', strtotime($user['last_activity_date'])) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Single Email Modal -->
<div class="modal fade" id="singleEmailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-success">
                <h5 class="modal-title">
                    <i class="fas fa-envelope"></i> Send Email
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="singleEmailUserId">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label"><strong>Recipient:</strong></label>
                        <p id="singleEmailRecipient" class="text-primary mb-0"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><strong>Email Template:</strong></label>
                        <select id="singleEmailTemplate" class="form-control">
                            <option value="">-- Select Template --</option>
                        </select>
                    </div>
                </div>
                <hr>
                <div id="singleEmailPreviewSection" style="display: none;">
                    <h6><i class="fas fa-eye"></i> Preview</h6>
                    <div class="card">
                        <div class="card-header bg-light py-2">
                            <strong>Subject:</strong> <span id="singleEmailPreviewSubject"></span>
                        </div>
                        <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                            <div id="singleEmailPreviewBody"></div>
                        </div>
                    </div>
                </div>
                <div id="singleEmailLoading" class="text-center py-3" style="display: none;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading preview...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnSendSingleEmail" disabled>
                    <i class="fas fa-paper-plane"></i> Send Email
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Email Modal -->
<div class="modal fade" id="bulkEmailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="fas fa-paper-plane"></i> Bulk Send Email
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="bulkEmailConfig">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label"><strong>User Group:</strong></label>
                            <select id="bulkEmailUserGroup" class="form-control">
                                <option value="without_activity">Users WITHOUT Activity</option>
                                <option value="with_activity">Users WITH Activity</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><strong>Recipients:</strong></label>
                            <p class="form-control-plaintext">
                                <span id="bulkEmailRecipientCount" class="badge badge-primary">0</span> users will receive this email
                            </p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><strong>Email Template:</strong></label>
                        <select id="bulkEmailTemplate" class="form-control">
                            <option value="">-- Select Template --</option>
                        </select>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        <strong>Rate Limiting:</strong> Emails will be sent with a delay between each to avoid spam filters.
                        The delay is configured in Settings > Email.
                    </div>
                </div>
                <div id="bulkEmailProgress" style="display: none;">
                    <h5 class="text-center mb-3">Sending Emails...</h5>
                    <div class="progress mb-3" style="height: 25px;">
                        <div id="bulkEmailProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
                    </div>
                    <div class="row text-center">
                        <div class="col-4">
                            <h4 id="bulkEmailSent" class="text-success">0</h4>
                            <small>Sent</small>
                        </div>
                        <div class="col-4">
                            <h4 id="bulkEmailFailed" class="text-danger">0</h4>
                            <small>Failed</small>
                        </div>
                        <div class="col-4">
                            <h4 id="bulkEmailRemaining">0</h4>
                            <small>Remaining</small>
                        </div>
                    </div>
                    <div id="bulkEmailLog" class="mt-3" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="bulkEmailLogBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="btnBulkEmailClose">Cancel</button>
                <button type="button" class="btn btn-warning" id="btnStartBulkEmail" disabled>
                    <i class="fas fa-paper-plane"></i> Start Sending
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Count of users without activity (the ones we'll be checking)
    var usersToCheck = <?php echo count($usersWithoutActivity) + count($uncheckedUsers); ?>;
    var processedUsers = 0;
    var withActivityCount = 0;
    var withoutActivityCount = 0;
    var chunkSize = 50;
    var isProcessing = false;

    // Initialize empty DataTables
    var inactiveTable = $('#inactiveUsersTable').DataTable({
        responsive: true,
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
        order: [[0, 'asc']],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search users..."
        }
    });

    var activeTable = $('#activeUsersTable').DataTable({
        responsive: true,
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
        order: [[3, 'desc']],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search users..."
        }
    });

    // Check Activity Button Click
    $('#btnCheckActivity').on('click', function() {
        if (isProcessing) return;

        var startDate = $('#startDate').val();
        var endDate = $('#endDate').val();

        if (!startDate || !endDate) {
            Swal.fire('Error', 'Please select both start and end dates', 'error');
            return;
        }

        if (usersToCheck === 0) {
            Swal.fire('Info', 'No users without activity to check. All users have recorded activity.', 'info');
            return;
        }

        // Confirm action
        Swal.fire({
            title: 'Check WITHOUT Activity Users?',
            html: 'This will check <strong>' + usersToCheck + '</strong> users without recorded activity between<br><strong>' + startDate + '</strong> and <strong>' + endDate + '</strong><br><br>Process in chunks of 50 users.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Start Check',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                startActivityCheck(startDate, endDate);
            }
        });
    });

    // Single user check button click
    $(document).on('click', '.btn-check-today', function() {
        var btn = $(this);
        var userId = btn.data('user-id');
        var userName = btn.data('user-name');
        var row = btn.closest('tr');

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: 'reports.php',
            method: 'POST',
            data: {
                action: 'check_single_user',
                user_id: userId
            },
            dataType: 'json',
            timeout: 60000,
            success: function(response) {
                if (response.success) {
                    if (response.has_activity) {
                        // User has activity today - move to WITH Activity table
                        Swal.fire({
                            icon: 'success',
                            title: 'Activity Found!',
                            html: '<strong>' + escapeHtml(userName) + '</strong><br>' + response.message,
                            confirmButtonText: 'OK'
                        });

                        // Remove from inactive table and add to active table
                        inactiveTable.row(row).remove().draw();

                        activeTable.row.add([
                            '<strong>' + escapeHtml(userName) + '</strong>',
                            row.find('td:eq(1)').text(),
                            row.find('td:eq(2)').html(),
                            '<span class="badge badge-info">1 days</span>',
                            response.today_hours + ' hrs',
                            response.today_hours + ' hrs',
                            'Today'
                        ]).draw();

                        // Update counts
                        var inactiveCount = parseInt($('#inactiveCount').text()) - 1;
                        var activeCount = parseInt($('#activeCount').text()) + 1;
                        $('#inactiveCount').text(inactiveCount);
                        $('#activeCount').text(activeCount);
                        $('#withoutActivityCard').text(inactiveCount);
                        $('#withActivityCard').text(activeCount);
                    } else {
                        // No activity today
                        Swal.fire({
                            icon: 'info',
                            title: 'No Activity Today',
                            html: '<strong>' + escapeHtml(userName) + '</strong><br>' + response.message,
                            confirmButtonText: 'OK'
                        });
                        btn.prop('disabled', false).html('<i class="fas fa-calendar-day"></i> Check');
                    }
                } else {
                    Swal.fire('Error', response.message || 'Failed to check activity', 'error');
                    btn.prop('disabled', false).html('<i class="fas fa-calendar-day"></i> Check');
                }
            },
            error: function(xhr, status, error) {
                Swal.fire('Error', 'Failed to check activity: ' + error, 'error');
                btn.prop('disabled', false).html('<i class="fas fa-calendar-day"></i> Check');
            }
        });
    });

    function startActivityCheck(startDate, endDate) {
        isProcessing = true;
        processedUsers = 0;
        withActivityCount = 0;
        withoutActivityCount = 0;

        // Clear tables
        inactiveTable.clear().draw();
        activeTable.clear().draw();

        // Show progress section
        $('#progressSection').show();
        $('#btnCheckActivity').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        // Reset progress
        $('#progressBar').css('width', '0%').text('0%').removeClass('bg-success bg-danger').addClass('bg-primary progress-bar-animated');
        $('#progressText').text('Starting...');
        $('#statProcessed').text('0');
        $('#statWithActivity').text('0');
        $('#statWithoutActivity').text('0');

        // Start processing
        processChunk(0, startDate, endDate);
    }

    function processChunk(offset, startDate, endDate) {
        $.ajax({
            url: 'reports.php',
            method: 'POST',
            data: {
                action: 'check_activity_chunk',
                offset: offset,
                start_date: startDate,
                end_date: endDate
            },
            dataType: 'json',
            timeout: 300000, // 5 minute timeout for API calls
            success: function(response) {
                if (response.success) {
                    // Process results
                    if (response.results) {
                        response.results.forEach(function(user) {
                            if (user.type === 'inactive') {
                                inactiveTable.row.add([
                                    '<strong>' + escapeHtml(user.full_name) + '</strong>',
                                    escapeHtml(user.email),
                                    '<span class="badge badge-primary">' + escapeHtml(user.roles) + '</span>',
                                    user.last_login === 'Never' ? '<span class="text-muted">Never</span>' : user.last_login,
                                    '<small class="text-muted">' + user.created_at + '</small>',
                                    '<button type="button" class="btn btn-sm btn-info btn-check-today" data-user-id="' + user.user_id + '" data-user-name="' + escapeHtml(user.full_name) + '" title="Check today\'s activity"><i class="fas fa-calendar-day"></i></button> ' +
                                    '<button type="button" class="btn btn-sm btn-success btn-send-email" data-user-id="' + user.user_id + '" data-user-name="' + escapeHtml(user.full_name) + '" data-user-email="' + escapeHtml(user.email) + '" title="Send email"><i class="fas fa-envelope"></i></button> ' +
                                    '<a href="users.php" class="btn btn-sm btn-primary" title="Manage users"><i class="fas fa-user-cog"></i></a>'
                                ]);
                            } else {
                                activeTable.row.add([
                                    '<strong>' + escapeHtml(user.full_name) + '</strong>',
                                    escapeHtml(user.email),
                                    '<span class="badge badge-primary">' + escapeHtml(user.roles) + '</span>',
                                    '<span class="badge badge-info">' + user.active_days + ' days</span>',
                                    user.total_hours + ' hrs',
                                    user.avg_hours + ' hrs',
                                    user.last_activity
                                ]);
                            }
                        });
                    }

                    // Update counters
                    processedUsers += response.processed;
                    withActivityCount += response.with_activity;
                    withoutActivityCount += response.without_activity;

                    // Update UI
                    var percent = usersToCheck > 0 ? Math.round((processedUsers / usersToCheck) * 100) : 100;
                    $('#progressBar').css('width', percent + '%').text(percent + '%');
                    $('#progressText').text('Processed ' + processedUsers + ' of ' + usersToCheck + ' users...');
                    $('#statProcessed').text(processedUsers);
                    $('#statWithActivity').text(withActivityCount);
                    $('#statWithoutActivity').text(withoutActivityCount);

                    // Update counts on cards
                    $('#inactiveCount').text(withoutActivityCount);
                    $('#activeCount').text(withActivityCount);

                    // Redraw tables
                    inactiveTable.draw();
                    activeTable.draw();

                    // Check if done or continue
                    if (response.done || processedUsers >= usersToCheck) {
                        finishProcessing();
                    } else {
                        // Continue with next chunk after a short delay
                        setTimeout(function() {
                            processChunk(response.next_offset, startDate, endDate);
                        }, 500);
                    }
                } else {
                    showError('Failed to process chunk: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showError('Error processing chunk: ' + error);
            }
        });
    }

    function finishProcessing() {
        isProcessing = false;

        // Update button
        $('#btnCheckActivity').prop('disabled', false).html('<i class="fas fa-play"></i> Check Activity via API');

        // Update progress text
        $('#progressBar').removeClass('progress-bar-animated').addClass('bg-success');
        $('#progressText').text('Completed! Processed ' + processedUsers + ' users.');

        // Update summary cards
        $('#withActivityCard').text(withActivityCount);
        $('#withoutActivityCard').text(withoutActivityCount);
        var totalChecked = withActivityCount + withoutActivityCount;
        var activityRate = totalChecked > 0 ? ((withActivityCount / totalChecked) * 100).toFixed(1) : 0;
        $('#activityRateCard').text(activityRate + '%');

        // Show success message
        Swal.fire({
            icon: 'success',
            title: 'Activity Check Complete',
            html: '<strong>' + processedUsers + '</strong> users processed<br>' +
                  '<span class="text-success"><strong>' + withActivityCount + '</strong> with activity</span><br>' +
                  '<span class="text-danger"><strong>' + withoutActivityCount + '</strong> without activity</span>',
            confirmButtonText: 'OK'
        });
    }

    function showError(message) {
        isProcessing = false;
        $('#btnCheckActivity').prop('disabled', false).html('<i class="fas fa-play"></i> Check Activity via API');
        $('#progressBar').removeClass('progress-bar-animated').addClass('bg-danger');
        $('#progressText').text('Error: ' + message);

        Swal.fire('Error', message, 'error');
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // ===========================================
    // EMAIL FUNCTIONALITY
    // ===========================================

    var emailTemplates = [];
    var bulkEmailTotal = 0;
    var bulkEmailSent = 0;
    var bulkEmailFailed = 0;
    var bulkEmailProcessing = false;

    // Load email templates on page load
    loadEmailTemplates();

    function loadEmailTemplates() {
        $.ajax({
            url: 'api/send-email.php',
            method: 'POST',
            data: { action: 'get_templates' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    emailTemplates = response.templates;
                    var options = '<option value="">-- Select Template --</option>';
                    response.templates.forEach(function(t) {
                        options += '<option value="' + t.id + '">' + escapeHtml(t.name) + '</option>';
                    });
                    $('#singleEmailTemplate, #bulkEmailTemplate').html(options);
                }
            }
        });
    }

    // Single Email Button Click
    $(document).on('click', '.btn-send-email', function() {
        var userId = $(this).data('user-id');
        var userName = $(this).data('user-name');
        var userEmail = $(this).data('user-email');

        $('#singleEmailUserId').val(userId);
        $('#singleEmailRecipient').html('<strong>' + escapeHtml(userName) + '</strong><br><small>' + escapeHtml(userEmail) + '</small>');
        $('#singleEmailTemplate').val('');
        $('#singleEmailPreviewSection').hide();
        $('#btnSendSingleEmail').prop('disabled', true);
        $('#singleEmailModal').modal('show');
    });

    // Template selection change for single email
    $('#singleEmailTemplate').on('change', function() {
        var templateId = $(this).val();
        var userId = $('#singleEmailUserId').val();

        if (!templateId) {
            $('#singleEmailPreviewSection').hide();
            $('#btnSendSingleEmail').prop('disabled', true);
            return;
        }

        $('#singleEmailLoading').show();
        $('#singleEmailPreviewSection').hide();

        $.ajax({
            url: 'api/send-email.php',
            method: 'POST',
            data: {
                action: 'preview_email',
                template_id: templateId,
                user_id: userId
            },
            dataType: 'json',
            success: function(response) {
                $('#singleEmailLoading').hide();
                if (response.success) {
                    $('#singleEmailPreviewSubject').text(response.subject);
                    $('#singleEmailPreviewBody').html(response.body);
                    $('#singleEmailPreviewSection').show();
                    $('#btnSendSingleEmail').prop('disabled', false);
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                $('#singleEmailLoading').hide();
                Swal.fire('Error', 'Failed to load preview', 'error');
            }
        });
    });

    // Send Single Email
    $('#btnSendSingleEmail').on('click', function() {
        var btn = $(this);
        var templateId = $('#singleEmailTemplate').val();
        var userId = $('#singleEmailUserId').val();

        if (!templateId || !userId) {
            Swal.fire('Error', 'Please select a template', 'error');
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');

        $.ajax({
            url: 'api/send-email.php',
            method: 'POST',
            data: {
                action: 'send_single',
                template_id: templateId,
                user_id: userId
            },
            dataType: 'json',
            success: function(response) {
                btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send Email');
                if (response.success) {
                    $('#singleEmailModal').modal('hide');
                    Swal.fire('Success', response.message, 'success');
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send Email');
                Swal.fire('Error', 'Failed to send email', 'error');
            }
        });
    });

    // Bulk Email Button Click
    $('#btnBulkEmail').on('click', function() {
        $('#bulkEmailConfig').show();
        $('#bulkEmailProgress').hide();
        $('#bulkEmailTemplate').val('');
        $('#btnStartBulkEmail').prop('disabled', true);
        updateBulkRecipientCount();
        $('#bulkEmailModal').modal('show');
    });

    // User group change
    $('#bulkEmailUserGroup').on('change', function() {
        updateBulkRecipientCount();
    });

    // Template selection for bulk email
    $('#bulkEmailTemplate').on('change', function() {
        var templateId = $(this).val();
        $('#btnStartBulkEmail').prop('disabled', !templateId);
    });

    function updateBulkRecipientCount() {
        var userGroup = $('#bulkEmailUserGroup').val();

        $.ajax({
            url: 'api/send-email.php',
            method: 'POST',
            data: {
                action: 'get_recipients_count',
                user_group: userGroup
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    bulkEmailTotal = response.count;
                    $('#bulkEmailRecipientCount').text(response.count);
                }
            }
        });
    }

    // Start Bulk Email
    $('#btnStartBulkEmail').on('click', function() {
        var templateId = $('#bulkEmailTemplate').val();
        var userGroup = $('#bulkEmailUserGroup').val();

        if (!templateId) {
            Swal.fire('Error', 'Please select a template', 'error');
            return;
        }

        if (bulkEmailTotal === 0) {
            Swal.fire('Info', 'No recipients to send to', 'info');
            return;
        }

        Swal.fire({
            title: 'Confirm Bulk Send',
            html: 'You are about to send emails to <strong>' + bulkEmailTotal + '</strong> users.<br><br>This action cannot be undone. Continue?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Send',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                startBulkEmail(templateId, userGroup);
            }
        });
    });

    function startBulkEmail(templateId, userGroup) {
        bulkEmailProcessing = true;
        bulkEmailSent = 0;
        bulkEmailFailed = 0;

        // Update UI
        $('#bulkEmailConfig').hide();
        $('#bulkEmailProgress').show();
        $('#bulkEmailProgressBar').css('width', '0%').text('0%');
        $('#bulkEmailSent').text('0');
        $('#bulkEmailFailed').text('0');
        $('#bulkEmailRemaining').text(bulkEmailTotal);
        $('#bulkEmailLogBody').html('');
        $('#btnStartBulkEmail').hide();
        $('#btnBulkEmailClose').text('Cancel');

        // Prevent modal close during processing
        $('#bulkEmailModal').data('bs.modal')._config.backdrop = 'static';
        $('#bulkEmailModal').data('bs.modal')._config.keyboard = false;

        processBulkEmailChunk(templateId, userGroup, 0);
    }

    function processBulkEmailChunk(templateId, userGroup, offset) {
        if (!bulkEmailProcessing) {
            finishBulkEmail();
            return;
        }

        $.ajax({
            url: 'api/send-email.php',
            method: 'POST',
            data: {
                action: 'send_bulk_chunk',
                template_id: templateId,
                user_group: userGroup,
                offset: offset
            },
            dataType: 'json',
            timeout: 600000, // 10 minute timeout
            success: function(response) {
                if (response.success) {
                    // Update counters
                    bulkEmailSent += response.sent;
                    bulkEmailFailed += response.failed;

                    var processed = bulkEmailSent + bulkEmailFailed;
                    var remaining = bulkEmailTotal - processed;
                    var percent = bulkEmailTotal > 0 ? Math.round((processed / bulkEmailTotal) * 100) : 100;

                    // Update UI
                    $('#bulkEmailProgressBar').css('width', percent + '%').text(percent + '%');
                    $('#bulkEmailSent').text(bulkEmailSent);
                    $('#bulkEmailFailed').text(bulkEmailFailed);
                    $('#bulkEmailRemaining').text(remaining);

                    // Log results
                    if (response.results) {
                        response.results.forEach(function(r) {
                            var statusClass = r.status === 'sent' ? 'text-success' : 'text-danger';
                            var statusText = r.status === 'sent' ? '<i class="fas fa-check"></i> Sent' : '<i class="fas fa-times"></i> Failed';
                            $('#bulkEmailLogBody').append(
                                '<tr><td>' + escapeHtml(r.email) + '</td><td class="' + statusClass + '">' + statusText + '</td></tr>'
                            );
                        });
                        // Scroll to bottom
                        $('#bulkEmailLog').scrollTop($('#bulkEmailLog')[0].scrollHeight);
                    }

                    // Check if done
                    if (response.done || processed >= bulkEmailTotal) {
                        finishBulkEmail();
                    } else {
                        // Continue with next chunk
                        processBulkEmailChunk(templateId, userGroup, response.next_offset);
                    }
                } else {
                    Swal.fire('Error', response.message, 'error');
                    finishBulkEmail();
                }
            },
            error: function(xhr, status, error) {
                Swal.fire('Error', 'Failed to send emails: ' + error, 'error');
                finishBulkEmail();
            }
        });
    }

    function finishBulkEmail() {
        bulkEmailProcessing = false;

        $('#bulkEmailProgressBar').removeClass('progress-bar-animated');
        if (bulkEmailFailed === 0) {
            $('#bulkEmailProgressBar').addClass('bg-success');
        } else if (bulkEmailSent === 0) {
            $('#bulkEmailProgressBar').addClass('bg-danger');
        } else {
            $('#bulkEmailProgressBar').addClass('bg-warning');
        }

        $('#btnBulkEmailClose').text('Close');
        $('#btnStartBulkEmail').show().prop('disabled', true);

        // Allow modal close
        $('#bulkEmailModal').data('bs.modal')._config.backdrop = true;
        $('#bulkEmailModal').data('bs.modal')._config.keyboard = true;

        Swal.fire({
            icon: bulkEmailFailed === 0 ? 'success' : 'warning',
            title: 'Bulk Email Complete',
            html: '<strong>' + bulkEmailSent + '</strong> emails sent<br><strong>' + bulkEmailFailed + '</strong> failed'
        });
    }

    // Cancel bulk email
    $('#btnBulkEmailClose').on('click', function() {
        if (bulkEmailProcessing) {
            Swal.fire({
                title: 'Cancel Sending?',
                text: 'Emails that have already been sent cannot be undone.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Cancel',
                cancelButtonText: 'Continue Sending'
            }).then((result) => {
                if (result.isConfirmed) {
                    bulkEmailProcessing = false;
                }
            });
            return false;
        }
    });
});
</script>

<?php include '../../components/footer.php'; ?>
