<?php
/**
 * TimeWorks Module - Category-Based Reports
 *
 * Generate attendance reports filtered by user categories.
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../login.php');
    exit;
}

// Check permission
if (!has_permission('timeworks_reports_view')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$page_title = "TimeWorks - Category Reports";
$root_path = "../../../";
$root_dir = dirname(__DIR__, 3);

// Get categories
$stmt = $db->query("SELECT * FROM twr_category_definitions WHERE is_active = 1 ORDER BY sort_order");
$categories = $stmt->fetchAll();

// Default date range (current month)
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$categoryId = $_GET['category_id'] ?? '';

include '../../../components/header.php';
include '../../../components/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-chart-bar mr-2"></i><?= $page_title ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/modules/timeworks/">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Category Reports</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Filter Card -->
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Report Filters</h3>
                </div>
                <div class="card-body">
                    <form id="reportForm" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="categoryId" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="startDate" class="form-control" value="<?= $startDate ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" id="endDate" class="form-control" value="<?= $endDate ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Quick Select</label>
                            <select id="quickDate" class="form-select">
                                <option value="">Custom</option>
                                <option value="this_week">This Week</option>
                                <option value="last_week">Last Week</option>
                                <option value="this_month">This Month</option>
                                <option value="last_month">Last Month</option>
                                <option value="this_quarter">This Quarter</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-chart-bar mr-1"></i> Generate Report
                                </button>
                                <button type="button" id="btnExport" class="btn btn-success">
                                    <i class="fas fa-file-excel mr-1"></i> Export
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="row" id="summaryCards" style="display: none;">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3 id="statTotalUsers">0</h3>
                            <p>Total Users</p>
                        </div>
                        <div class="icon"><i class="fas fa-users"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3 id="statTotalHours">0</h3>
                            <p>Total Hours</p>
                        </div>
                        <div class="icon"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3 id="statLateCount">0</h3>
                            <p>Late Instances</p>
                        </div>
                        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3 id="statAvgHours">0</h3>
                            <p>Avg Hours/Day</p>
                        </div>
                        <div class="icon"><i class="fas fa-calculator"></i></div>
                    </div>
                </div>
            </div>

            <!-- Results Table -->
            <div class="card" id="resultsCard" style="display: none;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-table mr-2"></i>Report Results</h3>
                </div>
                <div class="card-body">
                    <table id="reportTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Category</th>
                                <th>Days Worked</th>
                                <th>Total Hours</th>
                                <th>Scheduled Hours</th>
                                <th>On Time</th>
                                <th>Late</th>
                                <th>Absent</th>
                                <th>Attendance %</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr class="table-info">
                                <th colspan="2">Totals</th>
                                <th id="totalDays">0</th>
                                <th id="totalHours">0</th>
                                <th id="totalScheduled">0</th>
                                <th id="totalOnTime">0</th>
                                <th id="totalLate">0</th>
                                <th id="totalAbsent">0</th>
                                <th id="totalAttendance">0%</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Category Breakdown Chart -->
            <div class="row" id="chartsRow" style="display: none;">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Hours by Category</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryPieChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-line mr-2"></i>Attendance Trend</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="attendanceChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let pieChart = null;
let lineChart = null;

$(document).ready(function() {
    // Quick date selection
    $('#quickDate').on('change', function() {
        const val = $(this).val();
        const today = new Date();
        let start, end;

        switch(val) {
            case 'this_week':
                start = new Date(today);
                start.setDate(today.getDate() - today.getDay());
                end = today;
                break;
            case 'last_week':
                start = new Date(today);
                start.setDate(today.getDate() - today.getDay() - 7);
                end = new Date(start);
                end.setDate(start.getDate() + 6);
                break;
            case 'this_month':
                start = new Date(today.getFullYear(), today.getMonth(), 1);
                end = today;
                break;
            case 'last_month':
                start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                end = new Date(today.getFullYear(), today.getMonth(), 0);
                break;
            case 'this_quarter':
                const quarter = Math.floor(today.getMonth() / 3);
                start = new Date(today.getFullYear(), quarter * 3, 1);
                end = today;
                break;
            default:
                return;
        }

        $('#startDate').val(start.toISOString().split('T')[0]);
        $('#endDate').val(end.toISOString().split('T')[0]);
    });

    // Form submit
    $('#reportForm').on('submit', function(e) {
        e.preventDefault();
        generateReport();
    });

    // Export
    $('#btnExport').on('click', function() {
        const params = new URLSearchParams({
            action: 'export',
            category_id: $('#categoryId').val(),
            start_date: $('#startDate').val(),
            end_date: $('#endDate').val()
        });
        window.location.href = '../api/reports.php?' + params.toString();
    });

    function generateReport() {
        const btn = $('#reportForm button[type="submit"]');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Loading...');

        $.ajax({
            url: '../api/reports.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'category_report',
                category_id: $('#categoryId').val(),
                start_date: $('#startDate').val(),
                end_date: $('#endDate').val()
            }),
            success: function(response) {
                btn.prop('disabled', false).html('<i class="fas fa-chart-bar mr-1"></i> Generate Report');

                if (response.success) {
                    displayResults(response);
                } else {
                    Swal.fire('Error', response.message || 'Failed to generate report', 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="fas fa-chart-bar mr-1"></i> Generate Report');
                Swal.fire('Error', 'Server error occurred', 'error');
            }
        });
    }

    function displayResults(response) {
        const data = response.data || [];
        const summary = response.summary || {};
        const chartData = response.chart_data || {};

        // Show cards
        $('#summaryCards, #resultsCard, #chartsRow').show();

        // Update summary
        $('#statTotalUsers').text(summary.total_users || 0);
        $('#statTotalHours').text((summary.total_hours || 0).toFixed(1));
        $('#statLateCount').text(summary.late_count || 0);
        $('#statAvgHours').text((summary.avg_hours_per_day || 0).toFixed(1));

        // Update table
        const tbody = $('#reportTable tbody');
        tbody.empty();

        let totals = {
            days: 0,
            hours: 0,
            scheduled: 0,
            onTime: 0,
            late: 0,
            absent: 0
        };

        data.forEach(function(row) {
            const attendance = row.scheduled_days > 0 ?
                ((row.on_time_count + row.late_count) / row.scheduled_days * 100).toFixed(1) : 0;

            tbody.append(`
                <tr>
                    <td>${row.full_name}</td>
                    <td>${row.categories || '<span class="text-muted">Uncategorized</span>'}</td>
                    <td>${row.days_worked}</td>
                    <td>${parseFloat(row.total_hours || 0).toFixed(1)}</td>
                    <td>${parseFloat(row.scheduled_hours || 0).toFixed(1)}</td>
                    <td><span class="badge bg-success">${row.on_time_count}</span></td>
                    <td><span class="badge bg-warning">${row.late_count}</span></td>
                    <td><span class="badge bg-danger">${row.absent_count}</span></td>
                    <td>${attendance}%</td>
                </tr>
            `);

            totals.days += parseInt(row.days_worked) || 0;
            totals.hours += parseFloat(row.total_hours) || 0;
            totals.scheduled += parseFloat(row.scheduled_hours) || 0;
            totals.onTime += parseInt(row.on_time_count) || 0;
            totals.late += parseInt(row.late_count) || 0;
            totals.absent += parseInt(row.absent_count) || 0;
        });

        const totalAttendance = (totals.onTime + totals.late + totals.absent) > 0 ?
            ((totals.onTime + totals.late) / (totals.onTime + totals.late + totals.absent) * 100).toFixed(1) : 0;

        $('#totalDays').text(totals.days);
        $('#totalHours').text(totals.hours.toFixed(1));
        $('#totalScheduled').text(totals.scheduled.toFixed(1));
        $('#totalOnTime').text(totals.onTime);
        $('#totalLate').text(totals.late);
        $('#totalAbsent').text(totals.absent);
        $('#totalAttendance').text(totalAttendance + '%');

        // Update charts
        updateCharts(chartData);
    }

    function updateCharts(chartData) {
        // Pie chart
        if (pieChart) pieChart.destroy();

        const pieCtx = document.getElementById('categoryPieChart').getContext('2d');
        pieChart = new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: chartData.category_labels || [],
                datasets: [{
                    data: chartData.category_hours || [],
                    backgroundColor: chartData.category_colors || ['#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8']
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

        // Line chart
        if (lineChart) lineChart.destroy();

        const lineCtx = document.getElementById('attendanceChart').getContext('2d');
        lineChart = new Chart(lineCtx, {
            type: 'line',
            data: {
                labels: chartData.date_labels || [],
                datasets: [{
                    label: 'On Time',
                    data: chartData.on_time_trend || [],
                    borderColor: '#28a745',
                    fill: false
                }, {
                    label: 'Late',
                    data: chartData.late_trend || [],
                    borderColor: '#ffc107',
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
</script>

<?php include '../../../components/footer.php'; ?>
