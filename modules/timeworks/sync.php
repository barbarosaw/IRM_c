<?php
/**
 * TimeWorks Module - Sync Page
 *
 * Synchronizes data from TimeWorks API to local database
 * - Users sync (only active users)
 * - Projects sync
 * - Project members sync
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Permission check
if (!has_permission('timeworks_sync_manage')) {
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

$page_title = "TimeWorks - Sync";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Define system constant for TimeWorksAPI
if (!defined('AW_SYSTEM')) {
    define('AW_SYSTEM', true);
}

// Load TimeWorks API
require_once $root_dir . '/modules/timeworks/models/TimeWorksAPI.php';
$api = new TimeWorksAPI($db);

// Handle AJAX sync requests
if (isset($_POST['action']) && $_POST['action'] === 'sync') {
    header('Content-Type: application/json');

    $syncType = $_POST['sync_type'] ?? 'users';
    $result = [
        'success' => false,
        'message' => '',
        'data' => []
    ];

    $syncStartTime = microtime(true);

    try {
        if ($syncType === 'users') {
            // Sync users
            $apiUsers = $api->getUsers();

            if ($apiUsers && isset($apiUsers['users'])) {
                $added = 0;
                $updated = 0;
                $skipped = 0;

                foreach ($apiUsers['users'] as $apiUser) {
                    // Check if user is already marked as inactive in our system
                    $checkStmt = $db->prepare("SELECT id, status FROM twr_users WHERE user_id = ?");
                    $checkStmt->execute([$apiUser['user_id']]);
                    $existingUser = $checkStmt->fetch();

                    // Skip if user is marked inactive in our system
                    if ($existingUser && $existingUser['status'] === 'inactive') {
                        $skipped++;
                        continue;
                    }

                    $stmt = $db->prepare("
                        INSERT INTO twr_users
                        (user_id, full_name, email, timezone, status, user_status, last_login_local, roles, created_at, synced_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                            full_name = VALUES(full_name),
                            email = VALUES(email),
                            timezone = VALUES(timezone),
                            user_status = VALUES(user_status),
                            last_login_local = VALUES(last_login_local),
                            roles = VALUES(roles),
                            synced_at = NOW(),
                            updated_at = NOW()
                    ");

                    $stmt->execute([
                        $apiUser['user_id'],
                        $apiUser['full_name'],
                        $apiUser['email'],
                        $apiUser['timezone'] ?? 'America/New_York',
                        'active', // Always set as active when syncing
                        $apiUser['user_status'] ?? 'normal',
                        $apiUser['last_login_local'] ?? null,
                        $apiUser['roles'] ?? 'User',
                        $apiUser['created_at'] ?? date('Y-m-d H:i:s')
                    ]);

                    if ($existingUser) {
                        $updated++;
                    } else {
                        $added++;
                    }
                }

                $result['success'] = true;
                $result['message'] = "Users synced: {$added} added, {$updated} updated, {$skipped} skipped (inactive)";
                $result['data'] = [
                    'added' => $added,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'total' => count($apiUsers['users'])
                ];
            } else {
                $result['message'] = 'Failed to fetch users from API';
            }

        } elseif ($syncType === 'projects') {
            // Sync projects with pagination
            $added = 0;
            $updated = 0;
            $offset = 0;
            $limit = 100;
            $totalFetched = 0;

            do {
                $apiProjects = $api->getProjects($limit, $offset);

                // Handle both response formats
                $projects = [];
                if ($apiProjects && isset($apiProjects['projects'])) {
                    $projects = $apiProjects['projects'];
                } elseif ($apiProjects && isset($apiProjects['items'])) {
                    $projects = $apiProjects['items'];
                }

                if (empty($projects)) {
                    break;
                }

                $totalFromAPI = $apiProjects['total'] ?? count($projects);
                $batchCount = count($projects);
                $totalFetched += $batchCount;

                foreach ($projects as $apiProject) {
                    // API returns 'id' not 'project_id'
                    $projectId = $apiProject['id'] ?? $apiProject['project_id'] ?? null;
                    if (!$projectId) continue;

                    $checkStmt = $db->prepare("SELECT id FROM twr_projects WHERE project_id = ?");
                    $checkStmt->execute([$projectId]);
                    $exists = $checkStmt->fetch();

                    $stmt = $db->prepare("
                        INSERT INTO twr_projects
                        (project_id, client_id, name, description, status, progress, is_billable, task_count, member_count, created_at, updated_at, synced_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                            client_id = VALUES(client_id),
                            name = VALUES(name),
                            description = VALUES(description),
                            status = VALUES(status),
                            progress = VALUES(progress),
                            is_billable = VALUES(is_billable),
                            task_count = VALUES(task_count),
                            member_count = VALUES(member_count),
                            updated_at = VALUES(updated_at),
                            synced_at = NOW()
                    ");

                    $stmt->execute([
                        $projectId,
                        $apiProject['client_id'] ?? null,
                        $apiProject['name'] ?? 'Unknown Project',
                        $apiProject['description'] ?? '',
                        $apiProject['status'] ?? 'active',
                        $apiProject['progress'] ?? 'Pending',
                        $apiProject['is_billable'] ?? 0,
                        $apiProject['task_count'] ?? 0,
                        $apiProject['member_count'] ?? count($apiProject['users'] ?? []),
                        $apiProject['created_at'] ?? date('Y-m-d H:i:s'),
                        $apiProject['updated_at'] ?? date('Y-m-d H:i:s')
                    ]);

                    if ($exists) {
                        $updated++;
                    } else {
                        $added++;
                    }
                }

                $offset += $limit;
            } while ($batchCount >= $limit && $totalFetched < $totalFromAPI);

            $result['success'] = true;
            $result['message'] = "Projects synced: {$added} added, {$updated} updated";
            $result['data'] = [
                'added' => $added,
                'updated' => $updated,
                'total' => $totalFetched
            ];

        } elseif ($syncType === 'user_clients') {
            // Derive user-client assignments from user-project-client relationships
            $added = 0;
            $skipped = 0;

            // Get user-project-client relationships
            $stmt = $db->query("
                SELECT DISTINCT
                    up.user_id,
                    p.client_id,
                    p.project_id
                FROM twr_user_projects up
                JOIN twr_projects p ON up.project_id = p.project_id
                JOIN twr_users u ON up.user_id = u.user_id
                WHERE p.client_id IS NOT NULL
                  AND u.status = 'active'
                ORDER BY up.user_id, p.client_id
            ");
            $relationships = $stmt->fetchAll();

            foreach ($relationships as $rel) {
                // Check if assignment already exists
                $checkStmt = $db->prepare("
                    SELECT id FROM twr_user_clients
                    WHERE user_id = ? AND client_id = ?
                ");
                $checkStmt->execute([$rel['user_id'], $rel['client_id']]);

                if (!$checkStmt->fetch()) {
                    // Insert new assignment
                    $insertStmt = $db->prepare("
                        INSERT INTO twr_user_clients
                        (user_id, client_id, assignment_type, project_id, is_primary, assigned_at, assigned_by, notes, created_at, updated_at)
                        VALUES (?, ?, 'via_project', ?, 0, NOW(), ?, 'Auto-assigned from project sync', NOW(), NOW())
                    ");
                    $insertStmt->execute([
                        $rel['user_id'],
                        $rel['client_id'],
                        $rel['project_id'],
                        $_SESSION['user_id']
                    ]);
                    $added++;
                } else {
                    $skipped++;
                }
            }

            $result['success'] = true;
            $result['message'] = "User-Client assignments: {$added} created, {$skipped} already exist";
            $result['data'] = [
                'added' => $added,
                'updated' => 0,
                'skipped' => $skipped,
                'total' => count($relationships)
            ];

        } elseif ($syncType === 'timeoff') {
            // Sync time off requests from TimeWorks API
            $added = 0;
            $updated = 0;
            $skipped = 0;

            // Get all time off requests with pagination
            $allRequests = $api->getAllTimeOffRequests(100, 'America/New_York');

            if (empty($allRequests)) {
                // Try to get response format for debugging
                $testResponse = $api->getTimeOffRequests(10, 0, 'America/New_York');
                if ($testResponse) {
                    error_log("TimeOff Sync: Response structure - " . json_encode(array_keys($testResponse)));
                    if (isset($testResponse['total']) && $testResponse['total'] == 0) {
                        $result['success'] = true;
                        $result['message'] = "No time off requests found in TimeWorks";
                        $result['data'] = ['added' => 0, 'updated' => 0, 'skipped' => 0, 'total' => 0];
                        // Skip to log section
                        goto logSync;
                    }
                }
                throw new Exception('Failed to fetch time off requests from TimeWorks API');
            }

            foreach ($allRequests as $request) {
                // Get the request ID from API
                $requestId = $request['id'] ?? null;
                if (!$requestId) continue;

                // Get user_id
                $userId = $request['user_id'] ?? null;
                if (!$userId) continue;

                // Check if user exists in our system
                $userCheck = $db->prepare("SELECT user_id FROM twr_users WHERE user_id = ?");
                $userCheck->execute([$userId]);
                if (!$userCheck->fetch()) {
                    $skipped++;
                    continue;
                }

                // Map API fields to database fields
                // API uses: started_at_date, stopped_at_date
                $startDate = $request['started_at_date'] ?? null;
                $endDate = $request['stopped_at_date'] ?? null;

                // Extract times from details if available
                $startTime = null;
                $endTime = null;
                if (!empty($request['details']) && is_array($request['details'])) {
                    $firstDetail = $request['details'][0] ?? null;
                    if ($firstDetail) {
                        // Parse datetime strings to extract time
                        if (!empty($firstDetail['started_at_datetime'])) {
                            $startTime = date('H:i:s', strtotime($firstDetail['started_at_datetime']));
                        }
                        if (!empty($firstDetail['stopped_at_datetime'])) {
                            $endTime = date('H:i:s', strtotime($firstDetail['stopped_at_datetime']));
                        }
                    }
                }

                // Calculate hours based on leave_type
                $leaveTypeFromApi = $request['leave_type'] ?? 'full_day';
                $hoursRequested = 8; // Default to 8 hours
                if ($leaveTypeFromApi === 'full_day') {
                    // Calculate days and multiply by 8
                    $days = 1;
                    if ($startDate && $endDate) {
                        $days = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
                    }
                    $hoursRequested = $days * 8;
                } elseif ($leaveTypeFromApi === 'half_day') {
                    $hoursRequested = 4;
                } elseif ($leaveTypeFromApi === 'partial') {
                    // For partial, try to calculate from details
                    if (!empty($request['details']) && is_array($request['details'])) {
                        $totalHours = 0;
                        foreach ($request['details'] as $detail) {
                            if (!empty($detail['started_at_datetime']) && !empty($detail['stopped_at_datetime'])) {
                                $start = strtotime($detail['started_at_datetime']);
                                $stop = strtotime($detail['stopped_at_datetime']);
                                $totalHours += ($stop - $start) / 3600;
                            }
                        }
                        if ($totalHours > 0) {
                            $hoursRequested = round($totalHours, 2);
                        }
                    }
                }

                // Combine notes and reason_notes
                $reason = trim(($request['notes'] ?? '') . ' ' . ($request['reason_notes'] ?? ''));

                // Map status values
                $apiStatus = strtolower($request['status'] ?? 'submitted');
                $statusMap = [
                    'approved' => 'approved',
                    'rejected' => 'rejected',
                    'submitted' => 'pending',
                    'pending' => 'pending',
                    'cancelled' => 'cancelled',
                    'denied' => 'rejected'
                ];
                $status = $statusMap[$apiStatus] ?? 'pending';

                // Parse created_at
                $createdAt = $request['created_at'] ?? null;
                if ($createdAt) {
                    $requestedAt = date('Y-m-d H:i:s', strtotime($createdAt));
                } else {
                    $requestedAt = date('Y-m-d H:i:s');
                }

                // approved_at - set if status is approved
                $approvedAt = ($status === 'approved' && !empty($request['updated_at']))
                    ? date('Y-m-d H:i:s', strtotime($request['updated_at']))
                    : null;

                // Map leave_type to our leave types
                // API returns: full_day, half_day, etc.
                $leaveTypeNames = [
                    'full_day' => 'Full Day Leave',
                    'half_day' => 'Half Day Leave',
                    'pto' => 'PTO',
                    'upto' => 'UPTO',
                    'sick' => 'Sick Leave',
                    'vacation' => 'Vacation'
                ];
                $leaveTypeName = $leaveTypeNames[$leaveTypeFromApi] ?? ucwords(str_replace('_', ' ', $leaveTypeFromApi));
                $leaveTypeCode = strtoupper(str_replace(' ', '_', $leaveTypeFromApi));

                // Check if leave type exists
                $typeCheck = $db->prepare("SELECT id FROM twr_leave_types WHERE code = ?");
                $typeCheck->execute([$leaveTypeCode]);
                $leaveType = $typeCheck->fetch();

                if (!$leaveType) {
                    // Create leave type
                    $insertType = $db->prepare("
                        INSERT INTO twr_leave_types (code, name, is_paid, is_active, sort_order, color_code)
                        VALUES (?, ?, 1, 1, 99, '#6c757d')
                    ");
                    $insertType->execute([$leaveTypeCode, $leaveTypeName]);
                    $leaveTypeId = $db->lastInsertId();
                } else {
                    $leaveTypeId = $leaveType['id'];
                }

                // Check if request already exists (by external_id or by user+dates)
                $existCheck = $db->prepare("
                    SELECT id FROM twr_leave_requests
                    WHERE external_id = ? OR (user_id = ? AND start_date = ? AND end_date = ?)
                ");
                $existCheck->execute([$requestId, $userId, $startDate, $endDate]);
                $existing = $existCheck->fetch();

                if ($existing) {
                    // Update existing
                    $updateStmt = $db->prepare("
                        UPDATE twr_leave_requests
                        SET leave_type_id = ?, start_time = ?, end_time = ?, hours_requested = ?,
                            reason = ?, status = ?, approved_at = ?, updated_at = NOW(), synced_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([
                        $leaveTypeId, $startTime, $endTime, $hoursRequested,
                        $reason, $status, $approvedAt, $existing['id']
                    ]);
                    $updated++;
                } else {
                    // Insert new
                    $insertStmt = $db->prepare("
                        INSERT INTO twr_leave_requests
                        (external_id, user_id, leave_type_id, start_date, end_date, start_time, end_time,
                         hours_requested, reason, status, requested_at, approved_at, created_at, updated_at, synced_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
                    ");
                    $insertStmt->execute([
                        $requestId, $userId, $leaveTypeId, $startDate, $endDate, $startTime, $endTime,
                        $hoursRequested, $reason, $status, $requestedAt, $approvedAt
                    ]);
                    $added++;
                }
            }

            $result['success'] = true;
            $result['message'] = "Time Off requests synced: {$added} added, {$updated} updated, {$skipped} skipped";
            $result['data'] = [
                'added' => $added,
                'updated' => $updated,
                'skipped' => $skipped,
                'total' => count($allRequests)
            ];
        }

        logSync:

        // Log sync activity
        $duration = round(microtime(true) - $syncStartTime, 2);
        $logStmt = $db->prepare("
            INSERT INTO twr_sync_log
            (sync_type, status, records_processed, records_added, records_updated, records_failed, started_at, completed_at, duration_seconds)
            VALUES (?, ?, ?, ?, ?, 0, NOW(), NOW(), ?)
        ");
        $logStmt->execute([
            $syncType,
            $result['success'] ? 'success' : 'failed',
            $result['data']['total'] ?? 0,
            $result['data']['added'] ?? 0,
            $result['data']['updated'] ?? 0,
            $duration
        ]);

    } catch (Exception $e) {
        $result['message'] = 'Error: ' . $e->getMessage();
    }

    echo json_encode($result);
    exit;
}

include '../../components/header.php';
include '../../components/sidebar.php';

// Get sync history
$stmt = $db->query("
    SELECT * FROM twr_sync_log
    ORDER BY completed_at DESC
    LIMIT 20
");
$syncHistory = $stmt->fetchAll();

// Get last sync times
$stmt = $db->query("
    SELECT sync_type, MAX(completed_at) as last_sync
    FROM twr_sync_log
    WHERE status = 'success'
    GROUP BY sync_type
");
$lastSyncs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-sync-alt"></i> TimeWorks Sync
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Sync</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- Sync Actions Card -->
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-play"></i> Sync Actions</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card bg-info">
                                <div class="card-body text-center">
                                    <h4><i class="fas fa-users"></i> Sync Users</h4>
                                    <p>Fetch latest users from TimeWorks API</p>
                                    <p class="small">
                                        Last sync:
                                        <?php
                                        if (isset($lastSyncs['users'])) {
                                            echo date('M j, Y H:i', strtotime($lastSyncs['users']));
                                        } else {
                                            echo 'Never';
                                        }
                                        ?>
                                    </p>
                                    <button class="btn btn-light btn-sync" data-sync-type="users">
                                        <i class="fas fa-sync"></i> Sync Users Now
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card bg-success">
                                <div class="card-body text-center">
                                    <h4><i class="fas fa-project-diagram"></i> Sync Projects</h4>
                                    <p>Fetch latest projects from TimeWorks API</p>
                                    <p class="small">
                                        Last sync:
                                        <?php
                                        if (isset($lastSyncs['projects'])) {
                                            echo date('M j, Y H:i', strtotime($lastSyncs['projects']));
                                        } else {
                                            echo 'Never';
                                        }
                                        ?>
                                    </p>
                                    <button class="btn btn-light btn-sync" data-sync-type="projects">
                                        <i class="fas fa-sync"></i> Sync Projects Now
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card bg-warning">
                                <div class="card-body text-center">
                                    <h4><i class="fas fa-user-tag"></i> Sync User-Clients</h4>
                                    <p>Derive user-client assignments from projects</p>
                                    <p class="small">
                                        Last sync:
                                        <?php
                                        if (isset($lastSyncs['user_clients'])) {
                                            echo date('M j, Y H:i', strtotime($lastSyncs['user_clients']));
                                        } else {
                                            echo 'Never';
                                        }
                                        ?>
                                    </p>
                                    <button class="btn btn-light btn-sync" data-sync-type="user_clients">
                                        <i class="fas fa-sync"></i> Sync User-Clients
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="card bg-purple">
                                <div class="card-body text-center text-white">
                                    <h4><i class="fas fa-calendar-alt"></i> Sync Time Off</h4>
                                    <p>Fetch leave/PTO requests from TimeWorks</p>
                                    <p class="small">
                                        Last sync:
                                        <?php
                                        if (isset($lastSyncs['timeoff'])) {
                                            echo date('M j, Y H:i', strtotime($lastSyncs['timeoff']));
                                        } else {
                                            echo 'Never';
                                        }
                                        ?>
                                    </p>
                                    <button class="btn btn-light btn-sync" data-sync-type="timeoff">
                                        <i class="fas fa-sync"></i> Sync Time Off
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Only active users will be synced. Users marked as inactive in the system will be skipped during sync.
                    </div>
                </div>
            </div>

            <!-- Sync Progress -->
            <div id="syncProgress" class="card card-warning" style="display: none;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-spinner fa-spin"></i> Sync in Progress</h3>
                </div>
                <div class="card-body">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                    </div>
                    <p class="mt-2 text-center" id="syncMessage">Please wait...</p>
                </div>
            </div>

            <!-- Sync History -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-history"></i> Sync History</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Records Processed</th>
                                    <th>Added</th>
                                    <th>Updated</th>
                                    <th>Failed</th>
                                    <th>Duration</th>
                                    <th>Completed At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($syncHistory)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No sync history found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($syncHistory as $log): ?>
                                        <tr>
                                            <td><?php echo $log['id']; ?></td>
                                            <td>
                                                <span class="badge badge-secondary">
                                                    <?php echo ucfirst($log['sync_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $statusBadge = $log['status'] === 'success' ? 'success' : 'danger';
                                                ?>
                                                <span class="badge badge-<?php echo $statusBadge; ?>">
                                                    <?php echo ucfirst($log['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($log['records_processed']); ?></td>
                                            <td><span class="badge badge-success"><?php echo number_format($log['records_added']); ?></span></td>
                                            <td><span class="badge badge-info"><?php echo number_format($log['records_updated']); ?></span></td>
                                            <td><span class="badge badge-danger"><?php echo number_format($log['records_failed']); ?></span></td>
                                            <td><?php echo $log['duration_seconds']; ?>s</td>
                                            <td>
                                                <small><?php echo date('M j, Y H:i:s', strtotime($log['completed_at'])); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.btn-sync').on('click', function() {
        var $btn = $(this);
        var syncType = $btn.data('sync-type');

        // Disable all buttons
        $('.btn-sync').prop('disabled', true);

        // Show progress
        $('#syncProgress').show();
        $('#syncMessage').text('Syncing ' + syncType + '...');

        // Make AJAX request
        $.ajax({
            url: 'sync.php',
            method: 'POST',
            data: {
                action: 'sync',
                sync_type: syncType
            },
            dataType: 'json',
            success: function(response) {
                $('#syncProgress').hide();

                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sync Completed',
                        html: response.message + '<br><br>' +
                              '<strong>Total:</strong> ' + response.data.total + '<br>' +
                              '<strong>Added:</strong> ' + response.data.added + '<br>' +
                              '<strong>Updated:</strong> ' + response.data.updated +
                              (response.data.skipped ? '<br><strong>Skipped:</strong> ' + response.data.skipped : ''),
                        confirmButtonText: 'OK'
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Sync Failed',
                        text: response.message,
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function(xhr, status, error) {
                $('#syncProgress').hide();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred during sync: ' + error,
                    confirmButtonText: 'OK'
                });
            },
            complete: function() {
                // Re-enable buttons
                $('.btn-sync').prop('disabled', false);
            }
        });
    });
});
</script>

<?php include '../../components/footer.php'; ?>
