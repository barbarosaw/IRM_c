<?php
/**
 * TimeWorks Module - Period Report
 *
 * Generate attendance reports for custom date ranges with aggregated metrics.
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

$page_title = "TimeWorks - Period Report";
$root_path = "../../../";
$root_dir = dirname(__DIR__, 3);

// Get users for filter
$stmt = $db->query("SELECT user_id, full_name FROM twr_users WHERE status = 'active' ORDER BY full_name");
$users = $stmt->fetchAll();

// Default date range
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

include '../../../components/header.php';
include '../../../components/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-calendar-alt mr-2"></i><?= $page_title ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/modules/timeworks/">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Period Report</li>
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
                    <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Report Parameters</h3>
                </div>
                <div class="card-body">
                    <form id="reportForm" class="row g-3">
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
                                <option value="pay_period">Current Pay Period (1-15 / 16-End)</option>
                                <option value="last_pay_period">Last Pay Period</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">User</label>
                            <select name="user_id" id="userId" class="form-select select2">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= htmlspecialchars($user['user_id']) ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sync mr-1"></i> Generate
                                </button>
                                <button type="button" id="btnExportExcel" class="btn btn-success">
                                    <i class="fas fa-file-excel mr-1"></i> Excel
                                </button>
                                <button type="button" id="btnExportPdf" class="btn btn-danger">
                                    <i class="fas fa-file-pdf mr-1"></i> PDF
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Row -->
            <div class="row" id="summaryRow" style="display: none;">
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="info-box bg-info">
                        <span class="info-box-icon"><i class="fas fa-users"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Users</span>
                            <span class="info-box-number" id="sumUsers">0</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="info-box bg-success">
                        <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Hours</span>
                            <span class="info-box-number" id="sumHours">0</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="info-box bg-primary">
                        <span class="info-box-icon"><i class="fas fa-calendar-check"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Working Days</span>
                            <span class="info-box-number" id="sumDays">0</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="info-box bg-success">
                        <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">On Time</span>
                            <span class="info-box-number" id="sumOnTime">0</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="info-box bg-warning">
                        <span class="info-box-icon"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Late</span>
                            <span class="info-box-number" id="sumLate">0</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <div class="info-box bg-danger">
                        <span class="info-box-icon"><i class="fas fa-times-circle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Absent</span>
                            <span class="info-box-number" id="sumAbsent">0</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Breakdown -->
            <div class="card" id="dailyCard" style="display: none;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-calendar-day mr-2"></i>Daily Breakdown</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body table-responsive">
                    <table id="dailyTable" class="table table-bordered table-sm">
                        <thead class="table-dark">
                            <tr id="dailyHeader"></tr>
                        </thead>
                        <tbody id="dailyBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- User Details -->
            <div class="card" id="detailsCard" style="display: none;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-clock mr-2"></i>User Details</h3>
                </div>
                <div class="card-body">
                    <table id="detailsTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Days Worked</th>
                                <th>Total Hours</th>
                                <th>Avg Hours/Day</th>
                                <th>On Time</th>
                                <th>Late</th>
                                <th>Absent</th>
                                <th>Attendance %</th>
                                <th>Late Minutes</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

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
            case 'pay_period':
                const day = today.getDate();
                if (day <= 15) {
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                    end = new Date(today.getFullYear(), today.getMonth(), 15);
                } else {
                    start = new Date(today.getFullYear(), today.getMonth(), 16);
                    end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                }
                break;
            case 'last_pay_period':
                const d = today.getDate();
                if (d <= 15) {
                    start = new Date(today.getFullYear(), today.getMonth() - 1, 16);
                    end = new Date(today.getFullYear(), today.getMonth(), 0);
                } else {
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                    end = new Date(today.getFullYear(), today.getMonth(), 15);
                }
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

    // Export handlers
    $('#btnExportExcel').on('click', function() {
        exportReport('excel');
    });

    $('#btnExportPdf').on('click', function() {
        exportReport('pdf');
    });

    function generateReport() {
        const btn = $('#reportForm button[type="submit"]');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Loading...');

        $.ajax({
            url: '../api/reports.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'period_report',
                start_date: $('#startDate').val(),
                end_date: $('#endDate').val(),
                user_id: $('#userId').val()
            }),
            success: function(response) {
                btn.prop('disabled', false).html('<i class="fas fa-sync mr-1"></i> Generate');

                if (response.success) {
                    displayResults(response);
                } else {
                    Swal.fire('Error', response.message || 'Failed to generate report', 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="fas fa-sync mr-1"></i> Generate');
                Swal.fire('Error', 'Server error occurred', 'error');
            }
        });
    }

    function displayResults(response) {
        const summary = response.summary || {};
        const daily = response.daily || [];
        const users = response.users || [];
        const dates = response.dates || [];

        // Show sections
        $('#summaryRow, #dailyCard, #detailsCard').show();

        // Update summary
        $('#sumUsers').text(summary.total_users || 0);
        $('#sumHours').text((summary.total_hours || 0).toFixed(1));
        $('#sumDays').text(summary.working_days || 0);
        $('#sumOnTime').text(summary.on_time_count || 0);
        $('#sumLate').text(summary.late_count || 0);
        $('#sumAbsent').text(summary.absent_count || 0);

        // Build daily header
        let headerHtml = '<th>User</th>';
        dates.forEach(function(date) {
            const d = new Date(date);
            const dayName = d.toLocaleDateString('en-US', { weekday: 'short' });
            const dayNum = d.getDate();
            headerHtml += `<th class="text-center">${dayName}<br>${dayNum}</th>`;
        });
        headerHtml += '<th>Total</th>';
        $('#dailyHeader').html(headerHtml);

        // Build daily body
        let bodyHtml = '';
        users.forEach(function(user) {
            bodyHtml += `<tr><td><strong>${user.full_name}</strong></td>`;

            let userTotal = 0;
            dates.forEach(function(date) {
                const dayData = daily.find(d => d.user_id === user.user_id && d.date === date);
                if (dayData) {
                    const hours = parseFloat(dayData.hours) || 0;
                    userTotal += hours;
                    let statusClass = 'bg-light';
                    if (dayData.status === 'on_time' || dayData.status === 'completed') statusClass = 'bg-success text-white';
                    else if (dayData.status === 'late') statusClass = 'bg-warning';
                    else if (dayData.status === 'absent') statusClass = 'bg-danger text-white';
                    else if (dayData.status === 'pto' || dayData.status === 'upto') statusClass = 'bg-info text-white';

                    bodyHtml += `<td class="text-center ${statusClass}">${hours.toFixed(1)}</td>`;
                } else {
                    const isWeekend = [0, 6].includes(new Date(date).getDay());
                    bodyHtml += `<td class="text-center ${isWeekend ? 'bg-secondary text-white' : ''}">-</td>`;
                }
            });

            bodyHtml += `<td class="text-center"><strong>${userTotal.toFixed(1)}</strong></td></tr>`;
        });
        $('#dailyBody').html(bodyHtml);

        // Update details table
        const tbody = $('#detailsTable tbody');
        tbody.empty();

        users.forEach(function(user) {
            const attendance = (user.on_time_count + user.late_count + user.absent_count) > 0 ?
                ((user.on_time_count + user.late_count) / (user.on_time_count + user.late_count + user.absent_count) * 100).toFixed(1) : 100;
            const avgHours = user.days_worked > 0 ? (user.total_hours / user.days_worked).toFixed(1) : 0;

            tbody.append(`
                <tr>
                    <td>${user.full_name}</td>
                    <td>${user.days_worked}</td>
                    <td>${parseFloat(user.total_hours || 0).toFixed(1)}</td>
                    <td>${avgHours}</td>
                    <td><span class="badge bg-success">${user.on_time_count}</span></td>
                    <td><span class="badge bg-warning">${user.late_count}</span></td>
                    <td><span class="badge bg-danger">${user.absent_count}</span></td>
                    <td>${attendance}%</td>
                    <td>${user.total_late_minutes || 0} min</td>
                </tr>
            `);
        });
    }

    function exportReport(format) {
        const params = new URLSearchParams({
            action: 'export_period',
            format: format,
            start_date: $('#startDate').val(),
            end_date: $('#endDate').val(),
            user_id: $('#userId').val()
        });
        window.location.href = '../api/reports.php?' + params.toString();
    }
});
</script>

<?php include '../../../components/footer.php'; ?>
