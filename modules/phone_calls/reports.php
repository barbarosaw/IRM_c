<?php
/**
 * Phone Calls Module - Reports Page
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../includes/init.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission
if (!has_permission('phone_calls-reports')) {
    header('Location: ../../access-denied.php');
    exit;
}

require_once 'models/PhoneCall.php';
$phoneCallModel = new PhoneCall($db);

// Get period from query string
$period = $_GET['period'] ?? 'month';
$validPeriods = ['today', 'week', 'month', 'year', 'all'];
if (!in_array($period, $validPeriods)) {
    $period = 'month';
}

// Get stats
$overallStats = $phoneCallModel->getOverallStats($period);
$userStats = $phoneCallModel->getStatsByUser($period);
$dailyStats = $phoneCallModel->getStatsByDay($period === 'year' ? 365 : ($period === 'month' ? 30 : ($period === 'week' ? 7 : 1)));

$page_title = "Phone Calls Reports";
$root_path = "../../";

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<link rel="stylesheet" href="assets/css/phone-calls.css">

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-chart-bar me-2"></i>Reports & Analytics
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Phone Calls</a></li>
                        <li class="breadcrumb-item active">Reports</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Period Selector -->
            <div class="mb-4">
                <div class="btn-group" role="group">
                    <a href="?period=today" class="btn btn-<?php echo $period === 'today' ? 'primary' : 'outline-primary'; ?>">Today</a>
                    <a href="?period=week" class="btn btn-<?php echo $period === 'week' ? 'primary' : 'outline-primary'; ?>">This Week</a>
                    <a href="?period=month" class="btn btn-<?php echo $period === 'month' ? 'primary' : 'outline-primary'; ?>">This Month</a>
                    <a href="?period=year" class="btn btn-<?php echo $period === 'year' ? 'primary' : 'outline-primary'; ?>">This Year</a>
                    <a href="?period=all" class="btn btn-<?php echo $period === 'all' ? 'primary' : 'outline-primary'; ?>">All Time</a>
                </div>
            </div>

            <!-- Overview Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body d-flex align-items-center">
                            <div class="stats-icon bg-calls me-3">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div>
                                <div class="stats-value"><?php echo number_format($overallStats['total_calls'] ?? 0); ?></div>
                                <div class="stats-label">Total Calls</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body d-flex align-items-center">
                            <div class="stats-icon bg-duration me-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <?php
                                $totalSeconds = $overallStats['total_duration'] ?? 0;
                                $hours = floor($totalSeconds / 3600);
                                $minutes = floor(($totalSeconds % 3600) / 60);
                                $durationDisplay = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
                                ?>
                                <div class="stats-value"><?php echo $durationDisplay; ?></div>
                                <div class="stats-label">Total Duration</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body d-flex align-items-center">
                            <div class="stats-icon bg-cost me-3">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div>
                                <div class="stats-value">$<?php echo number_format($overallStats['total_cost'] ?? 0, 2); ?></div>
                                <div class="stats-label">Total Cost</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="card-body d-flex align-items-center">
                            <div class="stats-icon bg-users me-3">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <div class="stats-value"><?php echo number_format($overallStats['unique_users'] ?? 0); ?></div>
                                <div class="stats-label">Active Users</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Daily Chart -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-line me-2"></i>Call Volume
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="dailyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Users -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-trophy me-2"></i>Top Users
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($userStats)): ?>
                                <div class="text-center text-muted py-4">No data available</div>
                            <?php else: ?>
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th class="text-center">Calls</th>
                                            <th class="text-end">Cost</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($userStats, 0, 10) as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                                <td class="text-center"><?php echo number_format($user['total_calls']); ?></td>
                                                <td class="text-end">$<?php echo number_format($user['total_cost'] ?? 0, 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cost Breakdown Chart -->
            <div class="row mt-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-pie-chart me-2"></i>Cost by User
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="costChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-clock me-2"></i>Duration by User
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="durationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Daily Chart Data
    const dailyData = <?php echo json_encode($dailyStats); ?>;
    const dailyLabels = dailyData.map(d => new Date(d.date).toLocaleDateString());
    const dailyCalls = dailyData.map(d => d.total_calls);
    const dailyCost = dailyData.map(d => parseFloat(d.total_cost || 0));

    new Chart(document.getElementById('dailyChart'), {
        type: 'line',
        data: {
            labels: dailyLabels,
            datasets: [{
                label: 'Calls',
                data: dailyCalls,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // User Stats Data
    const userStats = <?php echo json_encode(array_slice($userStats, 0, 5)); ?>;
    const userLabels = userStats.map(u => u.name);
    const userCosts = userStats.map(u => parseFloat(u.total_cost || 0));
    const userDurations = userStats.map(u => Math.round((u.total_duration || 0) / 60)); // in minutes

    // Cost Chart
    new Chart(document.getElementById('costChart'), {
        type: 'doughnut',
        data: {
            labels: userLabels,
            datasets: [{
                data: userCosts,
                backgroundColor: [
                    '#667eea', '#764ba2', '#22c55e', '#f59e0b', '#ef4444'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Duration Chart
    new Chart(document.getElementById('durationChart'), {
        type: 'bar',
        data: {
            labels: userLabels,
            datasets: [{
                label: 'Minutes',
                data: userDurations,
                backgroundColor: '#22c55e'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>

<?php include '../../components/footer.php'; ?>
