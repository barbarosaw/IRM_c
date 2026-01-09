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

// Get date range from request (default to last 1 year)
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-1 year'));
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
    // Get basic counts with date filter
    $stmt = $db->prepare("SELECT COUNT(*) as total, 
                                 COUNT(CASE WHEN is_active = 1 THEN 1 END) as active,
                                 COALESCE(SUM(CASE WHEN is_active = 1 THEN monthly_cost ELSE 0 END), 0) as monthly_total,
                                 COALESCE(SUM(CASE WHEN is_active = 1 THEN annual_cost ELSE 0 END), 0) as annual_total
                          FROM inventory_items 
                          WHERE (created_at BETWEEN ? AND ? OR created_at IS NULL)");
    $stmt->execute([$dateFrom, $dateTo . ' 23:59:59']);
    $itemStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($itemStats) {
        $overviewStats['total_items'] = $itemStats['total'];
        $overviewStats['active_items'] = $itemStats['active'];
        $overviewStats['inactive_items'] = $itemStats['total'] - $itemStats['active'];
        $overviewStats['total_monthly_cost'] = $itemStats['monthly_total'];
        $overviewStats['total_annual_cost'] = $itemStats['annual_total'];
    }
    
    // Get subscription types count (this doesn't need date filter as it's just types)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM inventory_subscription_types WHERE is_active = 1");
    $stmt->execute();
    $typeStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $overviewStats['subscription_types'] = $typeStats['total'] ?? 0;
    
} catch (Exception $e) {
    // Handle gracefully
}

// Get category distribution with date filter
$categoryData = [];
try {
    $stmt = $db->prepare("
        SELECT st.name as category_name, st.color, 
               COUNT(i.id) as item_count,
               COALESCE(SUM(i.monthly_cost), 0) as monthly_cost
        FROM inventory_subscription_types st
        LEFT JOIN inventory_items i ON st.id = i.subscription_type_id 
                                    AND i.is_active = 1
                                    AND (i.created_at BETWEEN ? AND ? OR i.created_at IS NULL)
        WHERE st.is_active = 1
        GROUP BY st.id, st.name, st.color
        ORDER BY item_count DESC
    ");
    $stmt->execute([$dateFrom, $dateTo . ' 23:59:59']);
    $categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle gracefully
}

// Get monthly cost trend based on selected date range
$monthlyCostData = [];
try {
    // Calculate number of months in the selected range
    $startDate = new DateTime($dateFrom);
    $endDate = new DateTime($dateTo);
    $interval = $startDate->diff($endDate);
    $monthsDiff = ($interval->y * 12) + $interval->m;
    
    // Limit to maximum 12 months for chart readability
    $monthsToShow = min($monthsDiff + 1, 12);
    
    for ($i = $monthsToShow - 1; $i >= 0; $i--) {
        $currentMonth = new DateTime($dateFrom);
        $currentMonth->modify("+$i months");
        $monthStart = $currentMonth->format('Y-m-01');
        $monthEnd = $currentMonth->format('Y-m-t');
        $monthName = $currentMonth->format('M Y');
        
        // Don't go beyond the end date
        if ($monthStart > $dateTo) continue;
        if ($monthEnd > $dateTo) $monthEnd = $dateTo;
        
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(monthly_cost), 0) as total_cost
            FROM inventory_items 
            WHERE is_active = 1 
            AND (created_at BETWEEN ? AND ? OR created_at IS NULL)
        ");
        $stmt->execute([$monthStart, $monthEnd . ' 23:59:59']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $monthlyCostData[] = [
            'month' => $monthName,
            'cost' => $result['total_cost'] ?? 0
        ];
    }
    
    // Reverse array to show chronological order
    $monthlyCostData = array_reverse($monthlyCostData);
} catch (Exception $e) {
    // Handle gracefully
}

// Get top items by cost with date filter
$topItemsData = [];
try {
    $stmt = $db->prepare("
        SELECT i.name, i.code, i.monthly_cost, i.annual_cost, st.name as type_name, st.color
        FROM inventory_items i
        LEFT JOIN inventory_subscription_types st ON i.subscription_type_id = st.id
        WHERE i.is_active = 1 
        AND (i.created_at BETWEEN ? AND ? OR i.created_at IS NULL)
        ORDER BY i.monthly_cost DESC
        LIMIT 10
    ");
    $stmt->execute([$dateFrom, $dateTo . ' 23:59:59']);
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
                            <?php if (isset($_GET['date_from']) || isset($_GET['date_to']) || isset($_GET['report_type'])): ?>
                                <span class="badge badge-info ml-2">
                                    <i class="fas fa-filter"></i> Active: <?= date('M d, Y', strtotime($dateFrom)) ?> - <?= date('M d, Y', strtotime($dateTo)) ?>
                                </span>
                            <?php endif; ?>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="GET">
                                <div class="row g-3 align-items-center">
                                    <div class="col-md-2">
                                        <label for="date_from" class="form-label">From:</label>
                                        <input type="date" id="date_from" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="date_to" class="form-label">To:</label>
                                        <input type="date" id="date_to" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="report_type" class="form-label">Report Type:</label>
                                        <select id="report_type" name="report_type" class="form-select form-select-sm">
                                            <option value="overview" <?= $reportType === 'overview' ? 'selected' : '' ?>>Overview</option>
                                            <option value="financial" <?= $reportType === 'financial' ? 'selected' : '' ?>>Financial</option>
                                            <option value="categories" <?= $reportType === 'categories' ? 'selected' : '' ?>>Categories</option>
                                            <option value="trends" <?= $reportType === 'trends' ? 'selected' : '' ?>>Trends</option>
                                        </select>
                                    </div>
                                    <div class="col-md-auto">
                                        <label class="form-label d-block">&nbsp;</label>
                                        <div class="btn-group">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="fas fa-filter"></i> Apply Filter
                                            </button>
                                            <a href="?" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-undo"></i> Reset
                                            </a>
                                        </div>
                                    </div>
                                </div>
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

            <!-- Top Items by Cost & Category Summary -->
            <div class="row">
                <!-- Top Items by Cost -->
                <div class="col-md-6">
                    <div class="card" id="financial-section">
                        <div class="card-header">
                            <h3 class="card-title">Top Items by Monthly Cost</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-primary" onclick="exportTableToCSV('topItemsTable', 'top-items-by-cost')">
                                    <i class="fas fa-file-csv"></i> Export CSV
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
                                    <table id="topItemsTable" class="table table-bordered table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Code</th>
                                                <th>Category</th>
                                                <th>Monthly Cost</th>
                                                <th>Annual Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $totalMonthly = 0;
                                            $totalAnnual = 0;
                                            foreach ($topItemsData as $item): 
                                                $totalMonthly += $item['monthly_cost'];
                                                $totalAnnual += $item['annual_cost'];
                                            ?>
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
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-info">
                                                <th colspan="3">Total (Top 10)</th>
                                                <th class="text-right">$<?= number_format($totalMonthly, 2) ?></th>
                                                <th class="text-right">$<?= number_format($totalAnnual, 2) ?></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Category Summary -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Category Summary</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-primary" onclick="exportTableToCSV('categoryTable', 'category-summary')">
                                    <i class="fas fa-file-csv"></i> Export CSV
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
                                    <table id="categoryTable" class="table table-bordered table-striped table-sm">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Item Count</th>
                                                <th>Monthly Cost</th>
                                                <th>Avg Cost/Item</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $totalItems = 0;
                                            $totalMonthlyCost = 0;
                                            foreach ($categoryData as $category): 
                                                $totalItems += $category['item_count'];
                                                $totalMonthlyCost += $category['monthly_cost'];
                                            ?>
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
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-info">
                                                <th>Total</th>
                                                <th class="text-center"><?= number_format($totalItems) ?></th>
                                                <th class="text-right">$<?= number_format($totalMonthlyCost, 2) ?></th>
                                                <th class="text-right">
                                                    <?php $overallAvg = $totalItems > 0 ? $totalMonthlyCost / $totalItems : 0; ?>
                                                    $<?= number_format($overallAvg, 2) ?>
                                                </th>
                                            </tr>
                                        </tfoot>
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
        order: [[3, 'desc']], // Order by monthly cost desc
        searching: false,
        paging: false,
        info: false
    });

    // Add form submission handler for better UX
    $('form[method="GET"]').on('submit', function() {
        // Show loading state
        $(this).find('button[type="submit"]').html('<i class="fas fa-spinner fa-spin"></i> Loading...');
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

// Export to CSV function
function exportTableToCSV(tableId, filename = '') {
    // Get table element
    var table = document.getElementById(tableId);
    if (!table) {
        alert('Table not found!');
        return;
    }
    
    var csv = [];
    var rows = table.querySelectorAll('tr');
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (var j = 0; j < cols.length; j++) {
            // Clean text content and handle commas
            var cellText = cols[j].innerText.replace(/"/g, '""');
            row.push('"' + cellText + '"');
        }
        
        csv.push(row.join(','));
    }
    
    // Create CSV file and download
    var csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
    var downloadLink = document.createElement('a');
    
    // Set filename
    filename = filename ? filename + '.csv' : 'table_data.csv';
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    
    // Trigger download
    downloadLink.style.display = 'none';
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}
</script>
