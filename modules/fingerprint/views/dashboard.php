<?php
// No AJAX handling here - moved to separate ajax_handler.php

// Set timezone to Eastern Time
date_default_timezone_set('America/New_York');

// Helper function to format dates in Eastern Time
function formatEasternTime($format, $timestamp = null)
{
    if ($timestamp === null) {
        $timestamp = time();
    } elseif (is_string($timestamp)) {
        $timestamp = strtotime($timestamp);
    }

    $eastern = new DateTime();
    $eastern->setTimestamp($timestamp);
    $eastern->setTimezone(new DateTimeZone('America/New_York'));

    return $eastern->format($format);
}


?>

<style>
    .security-tab-content {
        max-height: 350px;
        overflow-y: auto;
    }

    .security-tab-content .table-responsive {
        overflow-x: auto;
        overflow-y: visible;
    }

    .btn-group .btn.active {
        background-color: #007bff;
        color: white;
        border-color: #007bff;
    }

    .table-sm th,
    .table-sm td {
        padding: 0.3rem;
        font-size: 0.875rem;
        white-space: nowrap;
    }

    .list-group-item {
        padding: 0.5rem 1rem;
    }

    .badge {
        font-size: 0.75em;
    }

    .chart-container {
        position: relative;
        height: 300px;
        margin-bottom: 20px;
    }

    .security-stats {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .table-warning {
        --bs-table-accent-bg: #fff3cd;
    }

    /* Security Analysis Card Fixes */
    .security-analysis-card .card-body {
        max-height: 380px;
        overflow: hidden;
        padding: 1rem;
    }

    .security-tab-content {
        max-height: 320px;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .security-tab-content .card {
        margin-bottom: 0;
    }

    .security-tab-content .card-body {
        max-height: 280px;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .security-tab-content .table {
        margin-bottom: 0;
        font-size: 0.8rem;
    }

    .security-tab-content .table td,
    .security-tab-content .table th {
        padding: 0.25rem 0.5rem;
        vertical-align: middle;
    }

    /* Progress bar adjustments */
    .progress {
        height: 8px !important;
    }

    /* Badge size adjustments */
    .security-tab-content .badge {
        font-size: 0.7em;
        padding: 0.2em 0.4em;
    }

    /* Responsive table fixes */
    .security-tab-content .table-responsive {
        border: none;
        overflow-x: auto;
        overflow-y: visible;
        max-height: 250px;
    }

    /* Rapid IP Changes - Remove scroll from inner tables */
    .security-tab-content #rapidChangeContent .table-responsive {
        max-height: none !important;
        overflow: visible !important;
        border: none !important;
    }

    /* Additional fix for nested table-responsive divs */
    #rapidChangeContent td .table-responsive {
        max-height: none !important;
        overflow: visible !important;
        border: none !important;
    }

    /* Rapid IP Changes table styling */
    .security-tab-content .table-sm th {
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
    }

    .security-tab-content .table-sm td {
        text-align: center;
        vertical-align: middle;
    }

    /* IP Address column specific styling */
    .security-tab-content .table-sm td:first-child {
        text-align: left;
    }

    /* Time comparison styling */
    .time-comparison {
        font-size: 0.75rem;
        line-height: 1.2;
    }

    .time-comparison .mb-1 {
        margin-bottom: 0.25rem !important;
    }

    .time-comparison small {
        display: block;
        white-space: nowrap;
    }
</style>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-fingerprint text-primary"></i>
                        Fingerprint Analytics
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>">Home</a></li>
                        <li class="breadcrumb-item active">Fingerprint Analytics</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo number_format($stats['total_fingerprints'] ?? 0); ?></h3>
                            <p>Total Fingerprints</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-fingerprint"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo number_format($stats['today_fingerprints'] ?? 0); ?></h3>
                            <p>Today's Activity</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?php echo number_format($stats['unique_users'] ?? 0); ?></h3>
                            <p>Unique Users</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?php echo number_format($stats['unique_ips'] ?? 0); ?></h3>
                            <p>Unique IP Addresses</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-globe"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card security-analysis-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-shield-alt text-danger"></i> Security Analysis
                                <small class="text-muted ms-2">(Analysis results are indicative and may not be definitive)</small>
                            </h5>
                            <div class="btn-group mt-2" role="group">
                                <button type="button" class="btn btn-outline-success btn-sm active" id="chartsBtn">
                                    <i class="fas fa-table"></i> Analytics Tables
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" id="rapidChangeBtn">
                                    <i class="fas fa-route"></i> Rapid IP Changes
                                </button>
                                <button type="button" class="btn btn-outline-info btn-sm" id="vpnBtn">
                                    <i class="fas fa-mask"></i> VPN/Proxy Detection
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="multipleSessionBtn">
                                    <i class="fas fa-users-cog"></i> Multiple Sessions
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="suspiciousBtn">
                                    <i class="fas fa-exclamation-triangle"></i> Suspicious Activities
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Analytics Tables -->
                            <div id="chartsContent" class="security-tab-content">
                                <div class="row">
                                    <!-- Top Browsers -->
                                    <div class="col-md-6 mb-2">
                                        <div class="card h-100">
                                            <div class="card-header py-2">
                                                <h6 class="card-title mb-0">
                                                    <i class="fas fa-desktop mr-1"></i>
                                                    Top Browsers
                                                </h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped mb-0">
                                                        <thead class="table-dark">
                                                            <tr>
                                                                <th style="width: 10%">#</th>
                                                                <th style="width: 40%"><i class="fas fa-desktop"></i> Browser</th>
                                                                <th style="width: 25%"><i class="fas fa-chart-bar"></i> Count</th>
                                                                <th style="width: 25%"><i class="fas fa-percentage"></i> %</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $totalBrowsers = array_sum(array_column($topBrowsers, 'count'));
                                                            foreach ($topBrowsers as $index => $browser):
                                                                $percentage = $totalBrowsers > 0 ? round((($browser['count'] ?? 0) / $totalBrowsers) * 100, 1) : 0;
                                                            ?>
                                                                <tr>
                                                                    <td><span class="badge bg-primary"><?= $index + 1 ?></span></td>
                                                                    <td class="text-truncate" style="max-width: 80px;" title="<?= htmlspecialchars($browser['browser'] ?? 'Unknown') ?>"><?= htmlspecialchars($browser['browser'] ?? 'Unknown') ?></td>
                                                                    <td><span class="badge bg-success"><?= number_format($browser['count'] ?? 0) ?></span></td>
                                                                    <td>
                                                                        <div class="d-flex align-items-center">
                                                                            <div class="progress" style="width: 30px; height: 8px; margin-right: 5px;">
                                                                                <div class="progress-bar bg-info" style="width: <?= $percentage ?>%"></div>
                                                                            </div>
                                                                            <small><?= $percentage ?>%</small>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                            <?php if (empty($topBrowsers)): ?>
                                                                <tr>
                                                                    <td colspan="4" class="text-center text-muted py-2">No browser data available</td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Top Users -->
                                    <div class="col-md-6 mb-2">
                                        <div class="card h-100">
                                            <div class="card-header py-2">
                                                <h6 class="card-title mb-0">
                                                    <i class="fas fa-users mr-1"></i>
                                                    Top 10 Most Active Users
                                                </h6>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-striped mb-0">
                                                        <thead class="table-dark">
                                                            <tr>
                                                                <th style="width: 10%">#</th>
                                                                <th style="width: 30%"><i class="fas fa-user"></i> User ID</th>
                                                                <th style="width: 35%"><i class="fas fa-activity"></i> Activity</th>
                                                                <th style="width: 25%"><i class="fas fa-star"></i> Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $maxActivity = !empty($topUsers) ? max(array_column($topUsers, 'activity_count')) : 1;
                                                            foreach ($topUsers as $index => $user):
                                                                $activityPercentage = $maxActivity > 0 ? round((($user['activity_count'] ?? 0) / $maxActivity) * 100, 1) : 0;
                                                                $badgeClass = $index < 3 ? 'bg-warning' : ($index < 7 ? 'bg-info' : 'bg-secondary');
                                                            ?>
                                                                <tr>
                                                                    <td>
                                                                        <span class="badge <?= $badgeClass ?>"><?= $index + 1 ?></span>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge bg-primary text-truncate" style="max-width: 60px;" title="<?= htmlspecialchars($user['user_id'] ?? 'Unknown') ?>"><?= htmlspecialchars(substr($user['user_id'] ?? 'Unknown', 0, 8)) ?></span>
                                                                    </td>
                                                                    <td>
                                                                        <div class="d-flex align-items-center">
                                                                            <div class="progress" style="width: 25px; height: 8px; margin-right: 5px;">
                                                                                <div class="progress-bar bg-success" style="width: <?= $activityPercentage ?>%"></div>
                                                                            </div>
                                                                            <span class="badge bg-success"><?= number_format($user['activity_count'] ?? 0) ?></span>
                                                                        </div>
                                                                    </td>
                                                                    <td>
                                                                        <?php if ($index == 0): ?>
                                                                            <span class="badge bg-warning"><i class="fas fa-trophy"></i></span>
                                                                        <?php elseif ($index < 3): ?>
                                                                            <span class="badge bg-info"><i class="fas fa-medal"></i></span>
                                                                        <?php elseif ($index < 7): ?>
                                                                            <span class="badge bg-primary"><i class="fas fa-star"></i></span>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-secondary"><i class="fas fa-user"></i></span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                            <?php if (empty($topUsers)): ?>
                                                                <tr>
                                                                    <td colspan="4" class="text-center text-muted py-2">No user activity data available</td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Rapid Location Changes -->
                            <div id="rapidChangeContent" class="security-tab-content" style="display: none;">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th style="width: 15%;">User ID</th>
                                                <th style="width: 85%;">IP Change History</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Group rapid changes by user
                                            $groupedChanges = [];
                                            foreach ($rapidChanges as $change) {
                                                $groupedChanges[$change['user_id']][] = $change;
                                            }

                                            foreach ($groupedChanges as $userId => $userChanges):
                                            ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-danger"><?= htmlspecialchars($userId) ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="table-responsive">
                                                            <table class="table table-sm mb-0">
                                                                <thead>
                                                                    <tr>
                                                                        <th style="width: 40%;">IP Address</th>
                                                                        <th style="width: 20%;">Date</th>
                                                                        <th style="width: 15%;">Time Difference</th>
                                                                        <th style="width: 25%;">Risk Level</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($userChanges as $change): ?>
                                                                        <tr>
                                                                            <td>
                                                                                <code class="text-primary"><?= htmlspecialchars($change['first_ip'] ?? $change['ip_from'] ?? $change['ip_address_from'] ?? $change['prev_ip'] ?? $change['ip1'] ?? $change['ip_address_1'] ?? $change['ip_address'] ?? $change['ip'] ?? 'Unknown') ?></code>
                                                                                <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                                                                <code class="text-success"><?= htmlspecialchars($change['second_ip'] ?? $change['ip_to'] ?? $change['ip_address_to'] ?? $change['current_ip'] ?? $change['ip2'] ?? $change['ip_address_2'] ?? $change['new_ip'] ?? 'Unknown') ?></code>
                                                                            </td>
                                                                            <td>
                                                                                <small class="text-muted">
                                                                                    <?= formatEasternTime('d.m.Y H:i', $change['first_time'] ?? $change['timestamp'] ?? 'now') ?>
                                                                                    <br>
                                                                                    <i class="fas fa-arrow-down text-muted"></i>
                                                                                    <br>
                                                                                    <?= formatEasternTime('d.m.Y H:i', $change['second_time'] ?? $change['timestamp'] ?? 'now') ?>
                                                                                </small>
                                                                            </td>
                                                                            <td>
                                                                                <span class="badge bg-<?= ($change['time_diff_minutes'] ?? 0) < 30 ? 'danger' : (($change['time_diff_minutes'] ?? 0) < 60 ? 'warning' : 'info') ?>">
                                                                                    <?= $change['time_diff_minutes'] ?? '0' ?> min
                                                                                </span>
                                                                            </td>
                                                                            <td>
                                                                                <small class="text-<?= ($change['time_diff_minutes'] ?? 0) < 30 ? 'danger' : 'warning' ?>">
                                                                                    <?php
                                                                                    $timeDiff = $change['time_diff_minutes'] ?? 0;
                                                                                    if ($timeDiff < 30): ?>
                                                                                        High Risk - Very rapid change
                                                                                    <?php elseif ($timeDiff < 60): ?>
                                                                                        Medium Risk - Quick change
                                                                                    <?php else: ?>
                                                                                        Low Risk - Normal change
                                                                                    <?php endif; ?>
                                                                                </small>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <?php if (empty($rapidChanges)): ?>
                                                <tr>
                                                    <td colspan="2" class="text-center text-muted">No rapid location changes detected</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- VPN/Proxy Detection -->
                            <div id="vpnContent" class="security-tab-content" style="display: none;">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th style="width: 15%;">User ID</th>
                                                <th style="width: 20%;">IP Address</th>
                                                <th style="width: 12%;">Risk Score</th>
                                                <th style="width: 15%;">Reason</th>
                                                <th style="width: 38%;">Time Information</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($vpnDetection as $vpn): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-primary"><?= htmlspecialchars($vpn['user_id']) ?></span>
                                                    </td>
                                                    <td><code class="text-info"><?= htmlspecialchars($vpn['ip']) ?></code></td>
                                                    <td>
                                                        <span class="badge bg-<?= $vpn['activity_count'] > 10 ? 'danger' : ($vpn['activity_count'] > 5 ? 'warning' : 'success') ?>">
                                                            <?= min($vpn['activity_count'], 10) ?>/10
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-info">
                                                            <?= htmlspecialchars($vpn['ip_type'] ?? 'Unknown') ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <small class="text-primary">
                                                            <i class="fas fa-clock text-primary"></i>
                                                            <?= formatEasternTime('Y-m-d H:i:s T', $vpn['last_seen']) ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($vpnDetection)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">No VPN/proxy usage detected</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        All timestamps are displayed in Eastern Time Zone (EDT/EST)
                                    </small>
                                </div>
                            </div>

                            <!-- Multiple Sessions Detection -->
                            <div id="multipleSessionContent" class="security-tab-content" style="display: none;">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th style="width: 20%"><i class="fas fa-user"></i> User ID</th>
                                                <th style="width: 15%"><i class="fas fa-network-wired"></i> IP</th>
                                                <th style="width: 15%"><i class="fas fa-layer-group"></i> Sessions</th>
                                                <th style="width: 20%"><i class="fas fa-key"></i> Session ID</th>
                                                <th style="width: 30%"><i class="fas fa-clock"></i> Activity Times</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($concurrentSessions)): ?>
                                                <?php foreach ($concurrentSessions as $session): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-primary"><?= htmlspecialchars($session['user_id'] ?? 'Unknown') ?></span>
                                                        </td>
                                                        <td>
                                                            <code class="text-info"><?= htmlspecialchars($session['ip'] ?? 'Unknown') ?></code>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $sessionCount = intval($session['session_count'] ?? 0);
                                                            $badgeClass = $sessionCount > 5 ? 'bg-danger' : ($sessionCount > 3 ? 'bg-warning' : 'bg-success');
                                                            ?>
                                                            <span class="badge <?= $badgeClass ?>"><?= $sessionCount ?></span>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted font-monospace"><?= htmlspecialchars(substr($session['session_id'] ?? 'N/A', 0, 15)) ?>...</small>
                                                        </td>
                                                        <td>
                                                            <small class="text-muted">
                                                                <?php
                                                                $times = $session['session_times'] ?? 'N/A';
                                                                if ($times !== 'N/A' && strlen($times) > 50) {
                                                                    echo htmlspecialchars(substr($times, 0, 47)) . '...';
                                                                } else {
                                                                    echo htmlspecialchars($times);
                                                                }
                                                                ?>
                                                            </small>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-3">
                                                        <i class="fas fa-check-circle text-success me-2"></i>
                                                        No concurrent sessions detected in the last 2 hours
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Shows users with multiple sessions using the same session_id within 2 hours
                                    </small>
                                </div>
                            </div>

                            <!-- Suspicious Activities -->
                            <div id="suspiciousContent" class="security-tab-content" style="display: none;">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>IP Address</th>
                                                <th>Users Count</th>
                                                <th>Sessions</th>
                                                <th>Reason</th>
                                                <th>User Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($suspiciousIPs as $suspicious): ?>
                                                <tr>
                                                    <td><code class="text-danger"><?= htmlspecialchars($suspicious['ip'] ?? 'Unknown') ?></code></td>
                                                    <td>
                                                        <span class="badge bg-danger"><?= $suspicious['user_count'] ?? 0 ?> users</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning"><?= $suspicious['total_sessions'] ?? 0 ?> sessions</span>
                                                    </td>
                                                    <td>
                                                        <small class="text-danger">Multiple users sharing same IP - possible account sharing or security breach</small>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-info" onclick="showIpDetails('<?= htmlspecialchars($suspicious['ip'] ?? 'Unknown') ?>')">
                                                            <i class="fas fa-eye"></i> View Details
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <?php
                                            // Show concurrent sessions in suspicious activities
                                            foreach ($concurrentSessions as $concurrent):
                                            ?>
                                                <tr class="table-warning">
                                                    <td><code class="text-warning"><?= htmlspecialchars($concurrent['ip'] ?? 'Unknown') ?></code></td>
                                                    <td>
                                                        <span class="badge bg-warning">User <?= htmlspecialchars($concurrent['user_id'] ?? 'Unknown') ?></span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-danger"><?= intval($concurrent['session_count'] ?? 0) ?> concurrent</span>
                                                    </td>
                                                    <td>
                                                        <small class="text-warning">Multiple concurrent sessions from same user - possible session hijacking</small>
                                                    </td>
                                                    <td>
                                                        <small>Last: <?= formatEasternTime('H:i d.m.Y', $concurrent['last_activity'] ?? 'now') ?></small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>

                                            <?php if (empty($suspiciousIPs) && empty($concurrentSessions)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted">No suspicious activities detected</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Recent Data -->
            <div class="row">
                <!-- Filters -->
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-filter mr-1"></i>
                                Filters & Recent Activity
                            </h3>
                        </div>
                        <div class="card-body">
                            <!-- Filter Form -->
                            <form id="filterForm" class="row g-3 mb-4">
                                <!-- Date Range Presets -->
                                <div class="col-12 mb-3">
                                    <label class="form-label">Quick Date Selection:</label>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('today')">Today</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('yesterday')">Yesterday</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('week')">This Week</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('month')">This Month</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('3months')">3 Months</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setDateRange('clear')">Clear</button>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <label for="user_id" class="form-label">User ID</label>
                                    <input type="text" class="form-control" id="user_id" name="user_id" value="<?php echo htmlspecialchars($filters['user_id']); ?>" placeholder="Search by User ID">
                                </div>
                                <div class="col-md-3">
                                    <label for="ip" class="form-label">IP Address</label>
                                    <input type="text" class="form-control" id="ip" name="ip" value="<?php echo htmlspecialchars($filters['ip']); ?>" placeholder="Search by IP">
                                </div>
                                <div class="col-md-2">
                                    <label for="date_from" class="form-label">From Date</label>
                                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="date_to" class="form-label">To Date</label>
                                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i> Filter
                                        </button>
                                        <a href="?" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Clear
                                        </a>
                                    </div>
                                </div>
                            </form>

                            <!-- Recent Fingerprints Table -->
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-sm">
                                    <thead class="table-dark">
                                        <tr>
                                            <th style="width: 15%;">
                                                <a href="?order_by=user_id&order_dir=<?php echo ($orderBy == 'user_id' && $orderDir == 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($filters) ? '&' . http_build_query($filters) : ''; ?>" class="text-white text-decoration-none">
                                                    <i class="fas fa-user"></i> User ID
                                                    <?php if ($orderBy == 'user_id'): ?>
                                                        <i class="fas fa-sort-<?php echo $orderDir == 'ASC' ? 'up' : 'down'; ?>"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th style="width: 15%;">
                                                <a href="?order_by=ip&order_dir=<?php echo ($orderBy == 'ip' && $orderDir == 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($filters) ? '&' . http_build_query($filters) : ''; ?>" class="text-white text-decoration-none">
                                                    <i class="fas fa-network-wired"></i> IP
                                                    <?php if ($orderBy == 'ip'): ?>
                                                        <i class="fas fa-sort-<?php echo $orderDir == 'ASC' ? 'up' : 'down'; ?>"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th style="width: 15%;"><i class="fas fa-desktop"></i> Browser</th>
                                            <th style="width: 12%;">
                                                <a href="?order_by=total_sessions&order_dir=<?php echo ($orderBy == 'total_sessions' && $orderDir == 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($filters) ? '&' . http_build_query($filters) : ''; ?>" class="text-white text-decoration-none">
                                                    <i class="fas fa-chart-line"></i> Sessions
                                                    <?php if ($orderBy == 'total_sessions'): ?>
                                                        <i class="fas fa-sort-<?php echo $orderDir == 'ASC' ? 'up' : 'down'; ?>"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th style="width: 12%;">
                                                <a href="?order_by=total_ip_changes&order_dir=<?php echo ($orderBy == 'total_ip_changes' && $orderDir == 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($filters) ? '&' . http_build_query($filters) : ''; ?>" class="text-white text-decoration-none">
                                                    <i class="fas fa-exchange-alt"></i> IP Changes
                                                    <?php if ($orderBy == 'total_ip_changes'): ?>
                                                        <i class="fas fa-sort-<?php echo $orderDir == 'ASC' ? 'up' : 'down'; ?>"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th style="width: 18%;">
                                                <a href="?order_by=last_activity&order_dir=<?php echo ($orderBy == 'last_activity' && $orderDir == 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($filters) ? '&' . http_build_query($filters) : ''; ?>" class="text-white text-decoration-none">
                                                    <i class="fas fa-clock"></i> Last Activity
                                                    <?php if ($orderBy == 'last_activity'): ?>
                                                        <i class="fas fa-sort-<?php echo $orderDir == 'ASC' ? 'up' : 'down'; ?>"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th style="width: 13%;"><i class="fas fa-cog"></i> Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="fingerprintTableBody">
                                        <?php if (!empty($recentFingerprints)): ?>
                                            <?php foreach ($recentFingerprints as $index => $fingerprint): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-primary text-truncate d-inline-block" style="max-width: 100px;" title="<?php echo htmlspecialchars($fingerprint['user_id']); ?>">
                                                            <?php echo htmlspecialchars($fingerprint['user_id']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <code class="small"><?php echo htmlspecialchars($fingerprint['ip']); ?></code>
                                                    </td>
                                                    <td>
                                                        <span class="text-dark small">
                                                            <?php
                                                            $ua = $fingerprint['user_agent'];
                                                            if (strpos($ua, 'Chrome') !== false && strpos($ua, 'Edg') === false) {
                                                                echo '<i class="fab fa-chrome text-success"></i> Chrome';
                                                            } elseif (strpos($ua, 'Firefox') !== false) {
                                                                echo '<i class="fab fa-firefox text-warning"></i> Firefox';
                                                            } elseif (strpos($ua, 'Safari') !== false && strpos($ua, 'Chrome') === false) {
                                                                echo '<i class="fab fa-safari text-info"></i> Safari';
                                                            } elseif (strpos($ua, 'Edg') !== false) {
                                                                echo '<i class="fab fa-edge text-primary"></i> Edge';
                                                            } else {
                                                                echo '<i class="fas fa-globe text-secondary"></i> Other';
                                                            }
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success">
                                                            <?php echo number_format($fingerprint['total_sessions']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning">
                                                            <?php echo number_format($fingerprint['total_ip_changes']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-dark">
                                                            <?php echo formatEasternTime('M j, Y', $fingerprint['last_activity']); ?><br>
                                                            <span class="text-muted"><?php echo formatEasternTime('H:i', $fingerprint['last_activity']); ?></span>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-info" onclick="toggleUserDetails('<?php echo htmlspecialchars($fingerprint['user_id']); ?>', <?php echo $index; ?>)">
                                                            <i class="fas fa-eye"></i> Details
                                                        </button>
                                                    </td>
                                                </tr>
                                                <!-- Details Row -->
                                                <tr id="details-<?php echo $index; ?>" class="details-row" style="display: none;">
                                                    <td colspan="7" class="bg-light">
                                                        <div class="p-3">
                                                            <h6 class="mb-3">
                                                                <i class="fas fa-list"></i>
                                                                Detailed Activity for User: <?php echo htmlspecialchars($fingerprint['user_id']); ?>
                                                            </h6>
                                                            <div id="user-details-<?php echo $index; ?>" class="user-details-content">
                                                                <div class="text-center text-muted">
                                                                    <i class="fas fa-spinner fa-spin"></i> Loading details...
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">
                                                    <i class="fas fa-info-circle fs-4 text-info"></i>
                                                    <br><br>
                                                    <strong>No fingerprint data found</strong>
                                                    <br>
                                                    <small>Data will appear here when fingerprints are received</small>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>

                                <!-- Pagination -->
                                <?php if ($totalUsersCount > $limit): ?>
                                    <nav aria-label="Fingerprint pagination" class="mt-3">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <p class="text-muted mb-0">
                                                    Showing <?php echo (($page - 1) * $limit) + 1; ?> to
                                                    <?php echo min($page * $limit, $totalUsersCount); ?> of
                                                    <?php echo number_format($totalUsersCount); ?> users
                                                </p>
                                            </div>
                                            <div class="col-md-6">
                                                <ul class="pagination pagination-sm justify-content-end mb-0">
                                                    <?php
                                                    $totalPages = ceil($totalUsersCount / $limit);
                                                    $currentParams = $_GET;

                                                    // Previous button
                                                    if ($page > 1):
                                                        $currentParams['page'] = $page - 1;
                                                        $prevUrl = '?' . http_build_query($currentParams);
                                                    ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="<?php echo $prevUrl; ?>">
                                                                <i class="fas fa-chevron-left"></i> Previous
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>

                                                    <?php
                                                    // Page numbers
                                                    $startPage = max(1, $page - 2);
                                                    $endPage = min($totalPages, $page + 2);

                                                    for ($i = $startPage; $i <= $endPage; $i++):
                                                        $currentParams['page'] = $i;
                                                        $pageUrl = '?' . http_build_query($currentParams);
                                                    ?>
                                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                            <a class="page-link" href="<?php echo $pageUrl; ?>"><?php echo $i; ?></a>
                                                        </li>
                                                    <?php endfor; ?>

                                                    <?php
                                                    // Next button
                                                    if ($page < $totalPages):
                                                        $currentParams['page'] = $page + 1;
                                                        $nextUrl = '?' . http_build_query($currentParams);
                                                    ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="<?php echo $nextUrl; ?>">
                                                                Next <i class="fas fa-chevron-right"></i>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Security tabs functionality
        document.getElementById('chartsBtn').addEventListener('click', function() {
            showSecurityTab('charts');
            setActiveTab(this);
        });

        document.getElementById('rapidChangeBtn').addEventListener('click', function() {
            showSecurityTab('rapidChange');
            setActiveTab(this);
        });

        document.getElementById('vpnBtn').addEventListener('click', function() {
            showSecurityTab('vpn');
            setActiveTab(this);
        });

        document.getElementById('multipleSessionBtn').addEventListener('click', function() {
            showSecurityTab('multipleSession');
            setActiveTab(this);
        });

        document.getElementById('suspiciousBtn').addEventListener('click', function() {
            showSecurityTab('suspicious');
            setActiveTab(this);
        });

        function setActiveTab(activeButton) {
            document.querySelectorAll('.btn-group .btn').forEach(function(btn) {
                btn.classList.remove('active');
            });
            activeButton.classList.add('active');
        }

        function showSecurityTab(tab) {
            document.querySelectorAll('.security-tab-content').forEach(function(content) {
                content.style.display = 'none';
            });
            const tabContent = document.getElementById(tab + 'Content');
            if (tabContent) {
                tabContent.style.display = 'block';
            }
        }

        // IP Details Modal Function
        window.showIpDetails = function(ip) {
            fetch('ajax_handler.php?action=get_ip_details&ip=' + encodeURIComponent(ip))
                .then(response => response.json())
                .then(data => {
                    let details = `
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-network-wired mr-2"></i>
                                    IP Address Analysis: <code class="text-light">${ip}</code>
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th class="border-0">
                                                    <i class="fas fa-user text-primary"></i> User ID
                                                </th>
                                                <th class="border-0">
                                                    <i class="fas fa-clock text-success"></i> Sessions
                                                </th>
                                                <th class="border-0">
                                                    <i class="fas fa-calendar text-info"></i> Last Activity
                                                </th>
                                                <th class="border-0">
                                                    <i class="fas fa-desktop text-warning"></i> Browser
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                    `;

                    if (data && data.length > 0) {
                        data.forEach(function(item, index) {
                            const badgeClass = item.session_count > 10 ? 'bg-danger' : (item.session_count > 5 ? 'bg-warning' : 'bg-success');
                            details += `
                                <tr class="${index % 2 === 0 ? 'bg-light' : ''}">
                                    <td>
                                        <span class="badge bg-primary px-3 py-2">
                                            ${item.user_id}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge ${badgeClass} px-3 py-2">
                                            ${item.session_count} sessions
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <i class="fas fa-clock mr-1"></i>
                                        ${item.last_activity}
                                    </td>
                                    <td>
                                        <small class="text-secondary">
                                            <i class="fas fa-globe mr-1"></i>
                                            ${item.browser || 'Unknown Browser'}
                                        </small>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        details += `
                            <tr>
                                <td colspan="4" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                                        <br>
                                        <strong>No data found</strong>
                                        <br>
                                        <small>No user sessions found for this IP address</small>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }

                    details += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer text-muted bg-light">
                                <small>
                                    <i class="fas fa-shield-alt text-primary mr-1"></i>
                                    Security Analysis - This data shows all users who have accessed from IP: <strong>${ip}</strong>
                                </small>
                            </div>
                        </div>
                    `;

                    // Create and show modal
                    const modal = document.createElement('div');
                    modal.className = 'modal fade';
                    modal.innerHTML = `
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content border-0 shadow">
                                <div class="modal-header bg-gradient-primary text-white border-0">
                                    <h5 class="modal-title">
                                        <i class="fas fa-search-plus mr-2"></i>
                                        IP Security Details
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body p-4">
                                    ${details}
                                </div>
                                <div class="modal-footer border-0 bg-light">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times mr-1"></i> Close
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(modal);

                    // Show modal (Bootstrap 5)
                    const bsModal = new bootstrap.Modal(modal);
                    bsModal.show();

                    // Remove modal when hidden
                    modal.addEventListener('hidden.bs.modal', function() {
                        document.body.removeChild(modal);
                    });
                })
                .catch(error => {
                    console.error('Error fetching IP details:', error);

                    // Show error modal
                    const errorModal = document.createElement('div');
                    errorModal.className = 'modal fade';
                    errorModal.innerHTML = `
                        <div class="modal-dialog">
                            <div class="modal-content border-0 shadow">
                                <div class="modal-header bg-danger text-white border-0">
                                    <h5 class="modal-title">
                                        <i class="fas fa-exclamation-triangle mr-2"></i>
                                        Error
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-center py-4">
                                    <i class="fas fa-exclamation-circle fa-3x text-danger mb-3"></i>
                                    <h6>Unable to Load IP Details</h6>
                                    <p class="text-muted">There was an error loading the IP details. Please try again later.</p>
                                </div>
                                <div class="modal-footer border-0">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(errorModal);

                    const bsErrorModal = new bootstrap.Modal(errorModal);
                    bsErrorModal.show();

                    errorModal.addEventListener('hidden.bs.modal', function() {
                        document.body.removeChild(errorModal);
                    });
                });
        };

        // Set default active tab
        showSecurityTab('charts');

        // Filter form submission
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const params = new URLSearchParams();

            // Keep existing sort parameters
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('order_by')) {
                params.append('order_by', urlParams.get('order_by'));
            }
            if (urlParams.has('order_dir')) {
                params.append('order_dir', urlParams.get('order_dir'));
            }

            // Add form data
            for (let [key, value] of formData.entries()) {
                if (value) {
                    params.append(key, value);
                }
            }

            window.location.href = '?' + params.toString();
        });

        // Date range preset functions
        window.setDateRange = function(range) {
            const today = new Date();
            const dateFrom = document.getElementById('date_from');
            const dateTo = document.getElementById('date_to');

            switch (range) {
                case 'today':
                    const todayStr = today.toISOString().split('T')[0];
                    dateFrom.value = todayStr;
                    dateTo.value = todayStr;
                    break;
                case 'yesterday':
                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    const yesterdayStr = yesterday.toISOString().split('T')[0];
                    dateFrom.value = yesterdayStr;
                    dateTo.value = yesterdayStr;
                    break;
                case 'week':
                    const weekAgo = new Date(today);
                    weekAgo.setDate(weekAgo.getDate() - 7);
                    dateFrom.value = weekAgo.toISOString().split('T')[0];
                    dateTo.value = today.toISOString().split('T')[0];
                    break;
                case 'month':
                    const monthAgo = new Date(today);
                    monthAgo.setMonth(monthAgo.getMonth() - 1);
                    dateFrom.value = monthAgo.toISOString().split('T')[0];
                    dateTo.value = today.toISOString().split('T')[0];
                    break;
                case '3months':
                    const threeMonthsAgo = new Date(today);
                    threeMonthsAgo.setMonth(threeMonthsAgo.getMonth() - 3);
                    dateFrom.value = threeMonthsAgo.toISOString().split('T')[0];
                    dateTo.value = today.toISOString().split('T')[0];
                    break;
                case 'clear':
                    dateFrom.value = '';
                    dateTo.value = '';
                    break;
            }
        };
    });
</script>

<script>
    // Toggle user details
    function toggleUserDetails(userId, index) {
        const detailsRow = document.getElementById('details-' + index);
        const detailsContent = document.getElementById('user-details-' + index);

        if (detailsRow.style.display === 'none') {
            // Show details
            detailsRow.style.display = 'table-row';

            // Load user details via AJAX
            console.log('Fetching user details for:', userId); // Debug log

            fetch('ajax_handler.php?action=get_user_details&user_id=' + encodeURIComponent(userId))
                .then(response => {
                    console.log('Response status:', response.status); // Debug log
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Received data:', data); // Debug log

                    if (data.error) {
                        throw new Error(data.error);
                    }

                    let html = '<div class="table-responsive">';
                    html += '<table class="table table-sm table-bordered">';
                    html += '<thead class="table-secondary">';
                    html += '<tr>';
                    html += '<th><i class="fas fa-hashtag"></i> ID</th>';
                    html += '<th><i class="fas fa-network-wired"></i> IP</th>';
                    html += '<th><i class="fas fa-globe"></i> User Agent</th>';
                    html += '<th><i class="fas fa-key"></i> Session IDs</th>';
                    html += '<th><i class="fas fa-link"></i> URL</th>';
                    html += '<th><i class="fas fa-clock"></i> Timestamp</th>';
                    html += '</tr>';
                    html += '</thead>';
                    html += '<tbody>';

                    if (data && data.length > 0) {
                        data.forEach(function(record) {
                            html += '<tr>';
                            html += '<td><span class="badge bg-secondary">' + (record.id || 'N/A') + '</span></td>';
                            html += '<td><code>' + (record.ip_address || record.ip || 'N/A') + '</code></td>';
                            html += '<td><small class="text-muted">' + (record.browser || record.user_agent || 'N/A').substring(0, 60) + (record.user_agent && record.user_agent.length > 60 ? '...' : '') + '</small></td>';
                            html += '<td><small class="text-info">' + (record.session_id || 'N/A') + '</small></td>';
                            html += '<td><small class="text-primary">' + (record.url || 'N/A').substring(0, 50) + (record.url && record.url.length > 50 ? '...' : '') + '</small></td>';
                            html += '<td><small>' + (record.formatted_timestamp || record.timestamp || 'N/A') + '</small></td>';
                            html += '</tr>';
                        });
                    } else {
                        html += '<tr><td colspan="6" class="text-center text-muted">No detailed records found</td></tr>';
                    }

                    html += '</tbody>';
                    html += '</table>';
                    html += '</div>';

                    detailsContent.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading user details:', error);
                    detailsContent.innerHTML = '<div class="alert alert-danger">Error loading details: ' + error.message + '</div>';
                });
        } else {
            // Hide details
            detailsRow.style.display = 'none';
        }
    }
</script>