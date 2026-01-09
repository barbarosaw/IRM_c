<?php
/**
 * Inventory Module - Comprehensive Reports Dashboard
 * 
 * @author System Generated
 */

// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory view permission
if (!has_permission('view_inventory')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$page_title = "Inventory Reports";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/Item.php';
require_once $root_dir . '/modules/inventory/models/SubscriptionType.php';

// Initialize models
$itemModel = new InventoryItem($db);
$subscriptionTypeModel = new InventorySubscriptionType($db);

// Get date range from request (default to last 30 days)
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$reportType = $_GET['report_type'] ?? 'overview';

// Get overview statistics
$overviewStats = [
    'total_items' => 0,
    'active_items' => 0,
    'inactive_items' => 0,
    'total_monthly_cost' => 0,
    'total_annual_cost' => 0,
    'subscription_types' => 0
];

try {
    // Get basic counts
    $stmt = $db->prepare("SELECT COUNT(*) as total, 
                                 COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                                 COALESCE(SUM(CASE WHEN is_active = 1 THEN monthly_cost ELSE 0 END), 0) as monthly_total,
                                 COALESCE(SUM(CASE WHEN is_active = 1 THEN annual_cost ELSE 0 END), 0) as annual_total
                          FROM inventory_items");
    $stmt->execute();
    $itemStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($itemStats) {
        $overviewStats['total_items'] = $itemStats['total'];
        $overviewStats['active_items'] = $itemStats['active'];
        $overviewStats['inactive_items'] = $itemStats['total'] - $itemStats['active'];
        $overviewStats['total_monthly_cost'] = $itemStats['monthly_total'];
        $overviewStats['total_annual_cost'] = $itemStats['annual_total'];
    }
    
    // Get subscription types count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM inventory_subscription_types WHERE is_active = 1");
    $stmt->execute();
    $typeStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $overviewStats['subscription_types'] = $typeStats['total'] ?? 0;
    
} catch (Exception $e) {
    // Handle gracefully
}

// Get category distribution
$categoryData = [];
try {
    $stmt = $db->prepare("
        SELECT st.name as category_name, st.color, 
               COUNT(i.id) as item_count,
               COALESCE(SUM(i.monthly_cost), 0) as monthly_cost
        FROM inventory_subscription_types st
        LEFT JOIN inventory_items i ON st.id = i.subscription_type_id AND i.is_active = 1
        WHERE st.is_active = 1
        GROUP BY st.id, st.name, st.color
        ORDER BY item_count DESC
    ");
    $stmt->execute();
    $categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle gracefully
}

// Get monthly cost trend (last 6 months)
$monthlyCostData = [];
try {
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthName = date('M Y', strtotime("-$i months"));
        
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(monthly_cost), 0) as total_cost
            FROM inventory_items 
            WHERE is_active = 1 
            AND (created_at <= ? OR created_at IS NULL)
        ");
        $stmt->execute([$month . '-31']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $monthlyCostData[] = [
            'month' => $monthName,
            'cost' => $result['total_cost'] ?? 0
        ];
    }
} catch (Exception $e) {
    // Handle gracefully
}

// Get top items by cost
$topItemsData = [];
try {
    $stmt = $db->prepare("
        SELECT i.name, i.code, i.monthly_cost, i.annual_cost, st.name as type_name, st.color
        FROM inventory_items i
        LEFT JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
        WHERE i.is_active = 1
        ORDER BY i.monthly_cost DESC
        LIMIT 10
    ");
    $stmt->execute();
    $topItemsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle gracefully
}

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-chart-bar"></i> Inventory Reports</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item active">Reports</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            
            <!-- Date Range Filter -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Report Filters</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mr-3">
                                    <label for="date_from" class="mr-2">From:</label>
                                    <input type="date" id="date_from" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                                </div>
                                <div class="form-group mr-3">
                                    <label for="date_to" class="mr-2">To:</label>
                                    <input type="date" id="date_to" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                                </div>
                                <div class="form-group mr-3">
                                    <label for="report_type" class="mr-2">Report Type:</label>
                                    <select id="report_type" name="report_type" class="form-control">
                                        <option value="overview" <?= $reportType === 'overview' ? 'selected' : '' ?>>Overview</option>
                                        <option value="financial" <?= $reportType === 'financial' ? 'selected' : '' ?>>Financial</option>
                                        <option value="categories" <?= $reportType === 'categories' ? 'selected' : '' ?>>Categories</option>
                                        <option value="trends" <?= $reportType === 'trends' ? 'selected' : '' ?>>Trends</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Apply Filter
                                </button>
                                <a href="?" class="btn btn-secondary ml-2">
                                    <i class="fas fa-undo"></i> Reset
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overview Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= number_format($overviewStats['total_items']) ?></h3>
                            <p>Total Items</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <a href="items.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= number_format($overviewStats['active_items']) ?></h3>
                            <p>Active Items</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <a href="items.php?status=active" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3>$<?= number_format($overviewStats['total_monthly_cost'], 2) ?></h3>
                            <p>Monthly Cost</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <a href="#financial-section" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>$<?= number_format($overviewStats['total_annual_cost'], 2) ?></h3>
                            <p>Annual Cost</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <a href="#financial-section" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Category Distribution Chart -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Items by Category</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Monthly Cost Trend Chart -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Monthly Cost Trend</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="costTrendChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Items by Cost -->
            <div class="row">
                <div class="col-12">
                    <div class="card" id="financial-section">
                        <div class="card-header">
                            <h3 class="card-title">Top Items by Monthly Cost</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-primary" onclick="exportTableToExcel('topItemsTable', 'top-items-by-cost')">
                                    <i class="fas fa-file-excel"></i> Export
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($topItemsData)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No data available.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table id="topItemsTable" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Code</th>
                                                <th>Category</th>
                                                <th>Monthly Cost</th>
                                                <th>Annual Cost</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topItemsData as $item): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                                    <td><code><?= htmlspecialchars($item['code']) ?></code></td>
                                                    <td>
                                                        <?php if ($item['type_name']): ?>
                                                            <span class="badge" style="background-color: <?= htmlspecialchars($item['color'] ?? '#6c757d') ?>;">
                                                                <?= htmlspecialchars($item['type_name']) ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">No category</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-right">
                                                        <strong>$<?= number_format($item['monthly_cost'], 2) ?></strong>
                                                    </td>
                                                    <td class="text-right">
                                                        $<?= number_format($item['annual_cost'], 2) ?>
                                                    </td>
                                                    <td>
                                                        <a href="item-view.php?code=<?= urlencode($item['code']) ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
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

            <!-- Category Summary -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Category Summary</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-primary" onclick="exportTableToExcel('categoryTable', 'category-summary')">
                                    <i class="fas fa-file-excel"></i> Export
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($categoryData)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> No category data available.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table id="categoryTable" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Item Count</th>
                                                <th>Monthly Cost</th>
                                                <th>Average Cost per Item</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categoryData as $category): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge" style="background-color: <?= htmlspecialchars($category['color'] ?? '#6c757d') ?>;">
                                                            <?= htmlspecialchars($category['category_name']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <strong><?= number_format($category['item_count']) ?></strong>
                                                    </td>
                                                    <td class="text-right">
                                                        <strong>$<?= number_format($category['monthly_cost'], 2) ?></strong>
                                                    </td>
                                                    <td class="text-right">
                                                        <?php $avgCost = $category['item_count'] > 0 ? $category['monthly_cost'] / $category['item_count'] : 0; ?>
                                                        $<?= number_format($avgCost, 2) ?>
                                                    </td>
                                                    <td>
                                                        <a href="items.php?category=<?= urlencode($category['category_name']) ?>" class="btn btn-sm btn-info">
                                                            <i class="fas fa-list"></i> View Items
                                                        </a>
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
    </section>
</div>

<?php include '../../components/footer.php'; ?>

<!-- Chart.js -->
<script src="<?= $root_path ?>assets/js/chart.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTables
    $('#topItemsTable, #categoryTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[3, 'desc']] // Order by monthly cost desc
    });

    // Category Distribution Chart
    <?php if (!empty($categoryData)): ?>
    var categoryCtx = document.getElementById('categoryChart').getContext('2d');
    var categoryChart = new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: [<?php echo "'" . implode("', '", array_column($categoryData, 'category_name')) . "'"; ?>],
            datasets: [{
                data: [<?php echo implode(', ', array_column($categoryData, 'item_count')); ?>],
                backgroundColor: [<?php echo "'" . implode("', '", array_column($categoryData, 'color')) . "'"; ?>],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed + ' items';
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>

    // Monthly Cost Trend Chart
    <?php if (!empty($monthlyCostData)): ?>
    var costTrendCtx = document.getElementById('costTrendChart').getContext('2d');
    var costTrendChart = new Chart(costTrendCtx, {
        type: 'line',
        data: {
            labels: [<?php echo "'" . implode("', '", array_column($monthlyCostData, 'month')) . "'"; ?>],
            datasets: [{
                label: 'Monthly Cost ($)',
                data: [<?php echo implode(', ', array_column($monthlyCostData, 'cost')); ?>],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 3,
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
                        callback: function(value) {
                            return '$' + value.toFixed(2);
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});

// Export to Excel function
function exportTableToExcel(tableId, filename = '') {
    var downloadLink;
    var dataType = 'application/vnd.ms-excel';
    var tableSelect = document.getElementById(tableId);
    var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
    
    // Specify filename
    filename = filename ? filename + '.xls' : 'excel_data.xls';
    
    // Create download link element
    downloadLink = document.createElement("a");
    
    document.body.appendChild(downloadLink);
    
    if (navigator.msSaveOrOpenBlob) {
        var blob = new Blob(['\ufeff', tableHTML], {
            type: dataType
        });
        navigator.msSaveOrOpenBlob(blob, filename);
    } else {
        // Create a link to the file
        downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
    
        // Setting the file name
        downloadLink.download = filename;
        
        // Triggering the function
        downloadLink.click();
    }
}
</script>
