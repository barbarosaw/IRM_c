<?php

/**
 * Inventory Module - Main Dashboard View
 */

// Load models
require_once $root_dir . '/modules/inventory/models/InventoryItem.php';
require_once $root_dir . '/modules/inventory/models/SubscriptionType.php';
require_once $root_dir . '/modules/inventory/models/Team.php';
require_once $root_dir . '/modules/inventory/models/Assignment.php';
require_once $root_dir . '/modules/inventory/models/UsageLog.php';

// Initialize models
$inventoryModel = new InventoryItem($db);
$subscriptionTypeModel = new InventorySubscriptionType($db);
$teamModel = new InventoryTeam($db);
$assignmentModel = new InventoryAssignment($db);
$usageLogModel = new InventoryUsageLog($db);

// Get dashboard data
$capacityStats = $inventoryModel->getCapacityStats();
$costSummary = $inventoryModel->getCostSummary();
$expiringSoon = $inventoryModel->getExpiringSoon(30);
$assignmentStats = $assignmentModel->getAssignmentStats();
$recentUsage = $usageLogModel->getRecent(10);
$mostUsedItems = $usageLogModel->getMostUsedItems('month', 5);

// Check permissions
$canAdd = has_permission('add_inventory');
$canEdit = has_permission('edit_inventory');
$canDelete = has_permission('delete_inventory');
$canViewLicenseKeys = has_permission('view_license_keys');
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-boxes-packing"></i> Inventory Dashboard
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item active">Inventory</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- Statistics Cards -->
            <div class="row">
                <!-- Total Items -->
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo number_format($capacityStats['total_items']); ?></h3>
                            <p>Items</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <a href="items.php" class="small-box-footer">
                            View Details <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <!-- User Capacity -->
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo number_format($capacityStats['total_current_users'] ?? 0); ?>/<?php echo number_format($capacityStats['total_max_users'] ?? 0); ?></h3>
                            <p>Users</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <a href="assignments.php" class="small-box-footer">
                            View Assignments <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Team Capacity -->
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?php echo number_format($capacityStats['total_current_teams'] ?? 0); ?>/<?php echo number_format($capacityStats['total_max_teams'] ?? 0); ?></h3>
                            <p>Teams</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <a href="teams.php" class="small-box-footer">
                            View Teams <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>

                <!-- Monthly Cost -->
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>
                                <?php
                                $totalMonthlyCost = 0;
                                foreach ($costSummary as $cost) {
                                    $totalMonthlyCost += $cost['total_monthly_cost'];
                                }
                                $totalAnnualCost = $totalMonthlyCost * 12;
                                echo '$' . number_format($totalMonthlyCost, 2);
                                ?>

                            </h3>
                            <p>Monthly Cost <small style="font-size: 11px; color: #c9c6c6;" >
                                    (Annual: $<?php echo number_format($totalAnnualCost, 2); ?>)
                                </small></p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <a href="reports.php" class="small-box-footer">
                            View Reports <i class="fas fa-arrow-circle-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Action Buttons -->
                <div class="col-md-12 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-tools"></i> Quick Actions</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($canAdd): ?>
                                <a href="item-add.php" class="btn btn-primary mr-2 mb-2">
                                    <i class="fas fa-plus"></i> Add New Item
                                </a>
                            <?php endif; ?>

                            <a href="items.php" class="btn btn-outline-primary mr-2 mb-2">
                                <i class="fas fa-list"></i> View All Items
                            </a>

                            <a href="assignments.php" class="btn btn-outline-success mr-2 mb-2">
                                <i class="fas fa-user-check"></i> Manage Assignments
                            </a>

                            <a href="teams.php" class="btn btn-outline-info mr-2 mb-2">
                                <i class="fas fa-users"></i> Manage Teams
                            </a>

                            <?php if ($canAdd): ?>
                                <a href="subscription-types.php" class="btn btn-outline-secondary mr-2 mb-2">
                                    <i class="fas fa-tags"></i> Subscription Types
                                </a>
                            <?php endif; ?>

                            <a href="reports.php" class="btn btn-outline-warning mr-2 mb-2">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>

                            <a href="usage-logs.php" class="btn btn-outline-dark mr-2 mb-2">
                                <i class="fas fa-history"></i> Usage Logs
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Expiring Items -->
                <div class="col-md-6">
                    <div class="card card-warning">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> Expiring Soon (30 days)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($expiringSoon)): ?>
                                <p class="text-muted">No items expiring in the next 30 days.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Type</th>
                                                <th>Expires</th>
                                                <th>Days Left</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($expiringSoon as $item):
                                                $daysLeft = ceil((strtotime($item['expiry_date']) - time()) / (60 * 60 * 24));
                                                $badgeClass = $daysLeft <= 7 ? 'danger' : ($daysLeft <= 14 ? 'warning' : 'info');
                                            ?>
                                                <tr>
                                                    <td>
                                                        <a href="item-view.php?id=<?php echo $item['id']; ?>">
                                                            <?php echo htmlspecialchars($item['name']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($item['subscription_type_name']); ?></td>
                                                    <td><?php echo date('M j, Y', strtotime($item['expiry_date'])); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $badgeClass; ?>">
                                                            <?php echo $daysLeft; ?> days
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

                <!-- Most Used Items -->
                <div class="col-md-6">
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-star"></i> Less Used Items (This Month)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($mostUsedItems)): ?>
                                <p class="text-muted">No usage data available for this month.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Type</th>
                                                <th>Usage Count</th>
                                                <th>Users</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($mostUsedItems as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($item['subscription_type_name']); ?></td>
                                                    <td>
                                                        <span class="badge badge-primary">
                                                            <?php echo number_format($item['usage_count']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-info">
                                                            <?php echo number_format($item['unique_users']); ?>
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
            </div>

            <!-- Recent Activity -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-history"></i> Recent Usage Activity</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentUsage)): ?>
                                <p class="text-muted">No recent usage activity.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Item</th>
                                                <th>Action</th>
                                                <th>Duration</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentUsage as $usage): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($usage['user_name']); ?></td>
                                                    <td>
                                                        <?php
                                                        // Generate a color based on subscription type
                                                        $colors = [
                                                            'Software' => '#007bff',
                                                            'Hardware' => '#28a745',
                                                            'Service' => '#ffc107',
                                                            'License' => '#dc3545',
                                                            'Subscription' => '#6f42c1'
                                                        ];
                                                        $defaultColor = '#6c757d';
                                                        $bgColor = $colors[$usage['subscription_type_name']] ?? $defaultColor;
                                                        ?>
                                                        <span class="badge text-white" style="background-color: <?php echo $bgColor; ?>;">
                                                            <?php echo htmlspecialchars($usage['item_name']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $actionLabels = [
                                                            'login' => '<span class="badge badge-success">Login</span>',
                                                            'logout' => '<span class="badge badge-secondary">Logout</span>',
                                                            'access' => '<span class="badge badge-info">Access</span>',
                                                            'usage_start' => '<span class="badge badge-primary">Started</span>',
                                                            'usage_end' => '<span class="badge badge-warning">Ended</span>',
                                                            'license_activated' => '<span class="badge badge-success">Activated</span>',
                                                            'license_deactivated' => '<span class="badge badge-danger">Deactivated</span>'
                                                        ];
                                                        echo $actionLabels[$usage['action']] ?? '<span class="badge badge-light">' . ucfirst($usage['action']) . '</span>';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $usage['usage_duration_minutes'] ? $usage['usage_duration_minutes'] . ' min' : '-'; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo date('M j, Y H:i', strtotime($usage['logged_at'])); ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="reports.php?tab=usage" class="btn btn-outline-primary">
                                        View All Usage Logs <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
</div>
</div>
</div>
</div>