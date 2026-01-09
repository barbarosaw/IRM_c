<?php
/**
 * TimeWorks Module - Daily Report
 *
 * Comprehensive daily attendance report with chunked processing
 * - On Time, Late (with/without notice), Absent (with/without notice)
 * - PTO, Unpaid TO tracking
 * - Absenteeism %, Tardiness %, Shrinkage %, Lost Hours
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Permission check
if (!has_permission('timeworks_daily_report_view')) {
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

$page_title = "TimeWorks - Daily Report";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Get selected date (default today)
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$dayOfWeek = date('l', strtotime($selectedDate));

// Check if report exists for this date
$stmt = $db->prepare("SELECT id, generated_at FROM twr_daily_reports WHERE report_date = ?");
$stmt->execute([$selectedDate]);
$existingReport = $stmt->fetch();

// Get all active users for notices modal
$activeUsers = $db->query("
    SELECT user_id, full_name FROM twr_users
    WHERE status = 'active'
    ORDER BY full_name ASC
")->fetchAll();

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-calendar-day"></i> Daily Report
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Daily Report</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- Date Selection Card -->
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-calendar"></i> Report Settings</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-bs-toggle="modal" data-bs-target="#noticesModal">
                            <i class="fas fa-clipboard-list"></i> Manage Notices
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-3">
                            <label for="reportDate">Select Date:</label>
                            <input type="date" id="reportDate" class="form-control" value="<?php echo $selectedDate; ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="button" id="btnGenerateReport" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Generate Report
                            </button>
                            <button type="button" id="btnLoadSaved" class="btn btn-info ml-2" <?php echo !$existingReport ? 'style="display:none;"' : ''; ?>>
                                <i class="fas fa-database"></i> Load Saved Report
                            </button>
                        </div>
                        <div class="col-md-5 text-right">
                            <div class="btn-group">
                                <a href="?date=<?php echo date('Y-m-d', strtotime('-1 day', strtotime($selectedDate))); ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-chevron-left"></i> Previous Day
                                </a>
                                <a href="?date=<?php echo date('Y-m-d'); ?>" class="btn btn-outline-primary">Today</a>
                                <a href="?date=<?php echo date('Y-m-d', strtotime('+1 day', strtotime($selectedDate))); ?>" class="btn btn-outline-secondary" <?php echo $selectedDate >= date('Y-m-d') ? 'disabled' : ''; ?>>
                                    Next Day <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Selected Date:</strong> <?php echo date('l, F j, Y', strtotime($selectedDate)); ?>
                            <span class="badge badge-info"><?php echo $dayOfWeek; ?></span>
                            <?php if ($existingReport): ?>
                                <span class="badge badge-success ml-2">
                                    <i class="fas fa-check"></i> Report saved at <?php echo date('H:i', strtotime($existingReport['generated_at'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-right text-muted">
                            <i class="fas fa-server"></i> <strong>Server Time:</strong>
                            <span id="serverTime"><?php echo date('l, F j, Y - H:i:s'); ?></span>
                            <span class="badge badge-secondary"><?php echo date('T'); ?> (<?php echo date('P'); ?>)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Card (hidden initially) -->
            <div class="card card-warning" id="progressCard" style="display: none;">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-spinner fa-spin"></i> Generating Report...</h3>
                </div>
                <div class="card-body">
                    <div class="progress" style="height: 25px;">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%">0%</div>
                    </div>
                    <div id="progressStatus" class="mt-2 text-center">Initializing...</div>
                </div>
            </div>

            <!-- Report Content (hidden initially) -->
            <div id="reportContent" style="display: none;">

                <!-- Summary Statistics -->
                <div class="row">
                    <div class="col-lg-2 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3 id="statOnTime">0</h3>
                                <p>On Time</p>
                            </div>
                            <div class="icon"><i class="fas fa-check-circle"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3 id="statLateWithNotice">0</h3>
                                <p>Late (Notice)</p>
                            </div>
                            <div class="icon"><i class="fas fa-clock"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-6">
                        <div class="small-box bg-orange" style="background-color: #fd7e14 !important;">
                            <div class="inner">
                                <h3 id="statLateWithoutNotice">0</h3>
                                <p>Late (No Notice)</p>
                            </div>
                            <div class="icon"><i class="fas fa-exclamation-clock"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3 id="statAbsentWithoutNotice">0</h3>
                                <p>Absent (No Notice)</p>
                            </div>
                            <div class="icon"><i class="fas fa-user-times"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-6">
                        <div class="small-box bg-purple" style="background-color: #6f42c1 !important;">
                            <div class="inner">
                                <h3 id="statAbsentWithNotice">0</h3>
                                <p>Absent (Notice)</p>
                            </div>
                            <div class="icon"><i class="fas fa-user-clock"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3 id="statPTO">0</h3>
                                <p>PTO / Unpaid</p>
                            </div>
                            <div class="icon"><i class="fas fa-umbrella-beach"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Flexible Schedule Row -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box" style="background-color: #20c997 !important; color: white;">
                            <div class="inner">
                                <h3 id="statFlexible">0</h3>
                                <p>Flexible Schedule</p>
                            </div>
                            <div class="icon"><i class="fas fa-user-clock"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Metrics Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="info-box bg-gradient-danger">
                            <span class="info-box-icon"><i class="fas fa-percentage"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Absenteeism Rate</span>
                                <span class="info-box-number" id="metricAbsenteeism">0%</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box bg-gradient-warning">
                            <span class="info-box-icon"><i class="fas fa-percentage"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Tardiness Rate</span>
                                <span class="info-box-number" id="metricTardiness">0%</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box bg-gradient-info">
                            <span class="info-box-icon"><i class="fas fa-percentage"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Shrinkage Rate</span>
                                <span class="info-box-number" id="metricShrinkage">0%</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-box bg-gradient-dark">
                            <span class="info-box-icon"><i class="fas fa-hourglass-half"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Lost Hours</span>
                                <span class="info-box-number" id="metricLostHours">0 hrs</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Hours Summary -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card card-outline card-info">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-clock"></i> Hours Summary</h3>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th>Total Scheduled Hours:</th>
                                        <td class="text-right"><strong id="hoursScheduled">0</strong> hrs</td>
                                    </tr>
                                    <tr>
                                        <th>Total Worked Hours:</th>
                                        <td class="text-right"><strong id="hoursWorked">0</strong> hrs</td>
                                    </tr>
                                    <tr>
                                        <th>Lost Hours:</th>
                                        <td class="text-right text-danger"><strong id="hoursLost">0</strong> hrs</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card card-outline card-success">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-users"></i> Workforce Summary</h3>
                            </div>
                            <div class="card-body">
                                <table class="table table-sm">
                                    <tr>
                                        <th>Total Users:</th>
                                        <td class="text-right"><strong id="totalUsers">0</strong></td>
                                    </tr>
                                    <tr>
                                        <th>Scheduled to Work:</th>
                                        <td class="text-right"><strong id="totalScheduled">0</strong></td>
                                    </tr>
                                    <tr>
                                        <th>Day Off / No Schedule:</th>
                                        <td class="text-right"><span id="totalOff">0</span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Tables -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-list"></i> Detailed Report</h3>
                        <div class="card-tools">
                            <button class="btn btn-sm btn-success" id="btnCreateSummary">
                                <i class="fas fa-file-alt"></i> Create Summary of Day
                            </button>
                            <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="reportTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="all-tab" data-bs-toggle="tab" href="#tabAll" role="tab">
                                    All <span class="badge bg-secondary" id="countAll">0</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="ontime-tab" data-bs-toggle="tab" href="#tabOnTime" role="tab">
                                    <i class="fas fa-check text-success"></i> On Time <span class="badge bg-success" id="countOnTime">0</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="late-tab" data-bs-toggle="tab" href="#tabLate" role="tab">
                                    <i class="fas fa-clock text-warning"></i> Late <span class="badge bg-warning" id="countLate">0</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="absent-tab" data-bs-toggle="tab" href="#tabAbsent" role="tab">
                                    <i class="fas fa-times text-danger"></i> Absent <span class="badge bg-danger" id="countAbsent">0</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="pto-tab" data-bs-toggle="tab" href="#tabPTO" role="tab">
                                    <i class="fas fa-umbrella-beach text-info"></i> PTO/Leave <span class="badge bg-info" id="countPTO">0</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="flexible-tab" data-bs-toggle="tab" href="#tabFlexible" role="tab">
                                    <i class="fas fa-user-clock" style="color: #20c997;"></i> Flexible <span class="badge" style="background-color: #20c997;" id="countFlexible">0</span>
                                </a>
                            </li>
                        </ul>
                        <div class="tab-content mt-3" id="reportTabsContent">
                            <div class="tab-pane fade show active" id="tabAll" role="tabpanel">
                                <div class="table-responsive">
                                    <table id="tableAll" class="table table-bordered table-striped table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Status</th>
                                                <th>Scheduled</th>
                                                <th>Check In</th>
                                                <th>Check Out</th>
                                                <th>Sched. Hrs</th>
                                                <th>Worked Hrs</th>
                                                <th>Late (min)</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tabOnTime" role="tabpanel">
                                <div class="table-responsive">
                                    <table id="tableOnTime" class="table table-bordered table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Scheduled</th>
                                                <th>Check In</th>
                                                <th>Check Out</th>
                                                <th>Worked Hrs</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tabLate" role="tabpanel">
                                <div class="table-responsive">
                                    <table id="tableLate" class="table table-bordered table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Notice</th>
                                                <th>Scheduled</th>
                                                <th>Check In</th>
                                                <th>Late (min)</th>
                                                <th>Worked Hrs</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tabAbsent" role="tabpanel">
                                <div class="table-responsive">
                                    <table id="tableAbsent" class="table table-bordered table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Notice</th>
                                                <th>Scheduled</th>
                                                <th>Sched. Hrs</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tabPTO" role="tabpanel">
                                <div class="table-responsive">
                                    <table id="tablePTO" class="table table-bordered table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Type</th>
                                                <th>Sched. Hrs</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tabFlexible" role="tabpanel">
                                <div class="table-responsive">
                                    <table id="tableFlexible" class="table table-bordered table-hover table-sm">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Check In</th>
                                                <th>Check Out</th>
                                                <th>Worked Hrs</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<!-- Notices Modal -->
<div class="modal fade" id="noticesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-clipboard-list"></i> Manage Notices for <span id="noticesDate"><?php echo date('M j, Y', strtotime($selectedDate)); ?></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Add Notice Form -->
                <form id="addNoticeForm" class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>User</label>
                                <select id="noticeUserId" class="form-control select2" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($activeUsers as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Notice Type</label>
                                <select id="noticeType" class="form-control" required>
                                    <option value="">Select Type</option>
                                    <option value="pto">PTO (Paid Time Off)</option>
                                    <option value="unpaid_to">Unpaid Time Off</option>
                                    <option value="sick_leave">Sick Leave</option>
                                    <option value="late_notice">Late Notice</option>
                                    <option value="absent_notice">Absent Notice</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Reason (Optional)</label>
                                <input type="text" id="noticeReason" class="form-control" placeholder="Enter reason">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-success btn-block">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Existing Notices -->
                <table id="noticesTable" class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Type</th>
                            <th>Reason</th>
                            <th width="50">Action</th>
                        </tr>
                    </thead>
                    <tbody id="noticesList">
                        <tr><td colspan="4" class="text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Day Summary Modal -->
<div class="modal fade" id="daySummaryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-file-alt"></i> Daily Summary - <span id="summaryDate"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="summaryContent">
                <!-- Summary content will be dynamically generated -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-info" id="btnCopySummary">
                    <i class="fas fa-copy"></i> Copy to Clipboard
                </button>
                <button type="button" class="btn btn-primary" id="btnPrintSummary">
                    <i class="fas fa-print"></i> Print Summary
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Print styles for summary -->
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #summaryPrintArea, #summaryPrintArea * {
        visibility: visible;
    }
    #summaryPrintArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .no-print {
        display: none !important;
    }
}

#summaryContent table {
    border-collapse: collapse;
    width: 100%;
    margin-bottom: 15px;
}

#summaryContent table th,
#summaryContent table td {
    border: 1px solid #dee2e6;
    padding: 8px;
    text-align: left;
}

#summaryContent table th {
    background-color: #f8f9fa;
    font-weight: bold;
}

#summaryContent .summary-section {
    margin-bottom: 20px;
}

#summaryContent .summary-section h5 {
    background-color: #007bff;
    color: white;
    padding: 8px 12px;
    margin-bottom: 0;
    font-size: 14px;
}

#summaryContent .summary-section.late h5 {
    background-color: #fd7e14;
}

#summaryContent .summary-section.absent h5 {
    background-color: #dc3545;
}

#summaryContent .summary-section.hours h5 {
    background-color: #17a2b8;
}

#summaryContent .summary-section.workforce h5 {
    background-color: #28a745;
}
</style>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        dropdownParent: $('#noticesModal'),
        width: '100%'
    });

    // Live server time clock
    function updateServerTime() {
        const now = new Date();
        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
            timeZone: 'America/New_York'
        };
        const formatted = now.toLocaleString('en-US', options).replace(',', ' -');
        $('#serverTime').text(formatted);
    }
    setInterval(updateServerTime, 1000);

    const CHUNK_SIZE = 50;
    let currentDate = '<?php echo $selectedDate; ?>';
    let allDetails = [];
    let accumulatedStats = {};

    // Date change handler
    $('#reportDate').on('change', function() {
        currentDate = $(this).val();
        window.location.href = '?date=' + currentDate;
    });

    // Generate Report button
    $('#btnGenerateReport').on('click', function() {
        generateReport();
    });

    // Load Saved Report button
    $('#btnLoadSaved').on('click', function() {
        loadSavedReport();
    });

    // Notices Modal
    $('#noticesModal').on('show.bs.modal', function() {
        $('#noticesDate').text(new Date(currentDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }));
        loadNotices();
    });

    // Add Notice Form
    $('#addNoticeForm').on('submit', function(e) {
        e.preventDefault();
        saveNotice();
    });

    function generateReport() {
        allDetails = [];
        accumulatedStats = {
            on_time: 0,
            late_with_notice: 0,
            late_without_notice: 0,
            absent_with_notice: 0,
            absent_without_notice: 0,
            pto: 0,
            unpaid_to: 0,
            flexible: 0,
            off: 0,
            no_schedule: 0,
            scheduled_hours: 0,
            worked_hours: 0
        };

        $('#reportContent').hide();
        $('#progressCard').show();
        $('#progressBar').css('width', '0%').text('0%');
        $('#progressStatus').text('Initializing...');
        $('#btnGenerateReport').prop('disabled', true);

        // Get user count first
        $.post('api/daily-report.php', {
            action: 'get_users_count',
            date: currentDate
        }, function(response) {
            if (response.success) {
                const totalUsers = response.total;
                $('#progressStatus').text('Processing ' + totalUsers + ' users...');
                processChunks(0, totalUsers);
            } else {
                showError(response.error || 'Failed to get user count');
            }
        }).fail(function() {
            showError('API request failed');
        });
    }

    function processChunks(offset, totalUsers) {
        $.post('api/daily-report.php', {
            action: 'process_chunk',
            date: currentDate,
            offset: offset,
            limit: CHUNK_SIZE
        }, function(response) {
            if (response.success) {
                console.log('Chunk response stats:', response.stats);

                // Accumulate results
                allDetails = allDetails.concat(response.results);

                // Accumulate stats
                for (let key in response.stats) {
                    if (typeof response.stats[key] === 'number') {
                        accumulatedStats[key] = (accumulatedStats[key] || 0) + response.stats[key];
                    }
                }
                console.log('Accumulated stats:', accumulatedStats);

                // Update progress
                const processed = offset + response.processed;
                const percent = Math.round((processed / totalUsers) * 100);
                $('#progressBar').css('width', percent + '%').text(percent + '%');
                $('#progressStatus').text('Processed ' + processed + ' of ' + totalUsers + ' users...');

                // Continue or finalize
                if (processed < totalUsers) {
                    processChunks(processed, totalUsers);
                } else {
                    finalizeReport();
                }
            } else {
                showError(response.error || 'Chunk processing failed');
            }
        }).fail(function() {
            showError('API request failed');
        });
    }

    function finalizeReport() {
        $('#progressStatus').text('Saving report...');

        $.post('api/daily-report.php', {
            action: 'finalize_report',
            date: currentDate,
            stats: JSON.stringify(accumulatedStats),
            details: JSON.stringify(allDetails)
        }, function(response) {
            if (response.success) {
                $('#progressCard').hide();
                $('#btnGenerateReport').prop('disabled', false);
                displayReport(response.summary, allDetails);
            } else {
                showError(response.error || 'Failed to save report');
            }
        }).fail(function() {
            showError('API request failed');
        });
    }

    function loadSavedReport() {
        $('#reportContent').hide();
        $('#progressCard').show();
        $('#progressBar').css('width', '50%').text('Loading...');
        $('#progressStatus').text('Loading saved report...');

        $.get('api/daily-report.php', {
            action: 'get_saved_report',
            date: currentDate
        }, function(response) {
            $('#progressCard').hide();
            if (response.success) {
                displayReport(response.report, response.details);
            } else {
                alert(response.error || 'No saved report found');
            }
        }).fail(function() {
            $('#progressCard').hide();
            alert('Failed to load report');
        });
    }

    function displayReport(summary, details) {
        console.log('=== displayReport called ===');
        console.log('Summary object:', JSON.stringify(summary, null, 2));
        console.log('on_time value:', summary.on_time, 'type:', typeof summary.on_time);
        console.log('late_without_notice value:', summary.late_without_notice, 'type:', typeof summary.late_without_notice);
        console.log('Details count:', details.length);

        // Update allDetails for summary functionality
        allDetails = details;

        // Update statistics - ensure numeric values
        var onTime = parseInt(summary.on_time) || 0;
        var lateWithNotice = parseInt(summary.late_with_notice) || 0;
        var lateWithoutNotice = parseInt(summary.late_without_notice) || 0;
        var absentWithNotice = parseInt(summary.absent_with_notice) || 0;
        var absentWithoutNotice = parseInt(summary.absent_without_notice) || 0;
        var pto = parseInt(summary.pto) || 0;
        var unpaidTo = parseInt(summary.unpaid_to) || 0;
        var flexible = parseInt(summary.flexible) || 0;

        console.log('Parsed values - OnTime:', onTime, 'LateWithout:', lateWithoutNotice, 'AbsentWithout:', absentWithoutNotice, 'Flexible:', flexible);

        $('#statOnTime').text(onTime);
        $('#statLateWithNotice').text(lateWithNotice);
        $('#statLateWithoutNotice').text(lateWithoutNotice);
        $('#statAbsentWithNotice').text(absentWithNotice);
        $('#statAbsentWithoutNotice').text(absentWithoutNotice);
        $('#statPTO').text(pto + unpaidTo);
        $('#statFlexible').text(flexible);

        // Update metrics
        $('#metricAbsenteeism').text(parseFloat(summary.absenteeism_rate || 0).toFixed(1) + '%');
        $('#metricTardiness').text(parseFloat(summary.tardiness_rate || 0).toFixed(1) + '%');
        $('#metricShrinkage').text(parseFloat(summary.shrinkage_rate || 0).toFixed(1) + '%');
        $('#metricLostHours').text(parseFloat(summary.lost_hours || 0).toFixed(1) + ' hrs');

        // Update hours
        $('#hoursScheduled').text(parseFloat(summary.scheduled_hours || 0).toFixed(1));
        $('#hoursWorked').text(parseFloat(summary.worked_hours || 0).toFixed(1));
        $('#hoursLost').text(parseFloat(summary.lost_hours || 0).toFixed(1));

        // Update workforce
        $('#totalUsers').text(summary.total_users || details.length);
        $('#totalScheduled').text(summary.total_scheduled || 0);
        $('#totalOff').text(summary.total_off || 0);

        // Update tab counts
        const onTimeCount = parseInt(summary.on_time) || 0;
        const lateCount = (parseInt(summary.late_with_notice) || 0) + (parseInt(summary.late_without_notice) || 0);
        const absentCount = (parseInt(summary.absent_with_notice) || 0) + (parseInt(summary.absent_without_notice) || 0);
        const ptoCount = (parseInt(summary.pto) || 0) + (parseInt(summary.unpaid_to) || 0);
        const flexibleCount = parseInt(summary.flexible) || 0;

        $('#countAll').text(details.length);
        $('#countOnTime').text(onTimeCount);
        $('#countLate').text(lateCount);
        $('#countAbsent').text(absentCount);
        $('#countPTO').text(ptoCount);
        $('#countFlexible').text(flexibleCount);

        // Populate tables
        populateTables(details);

        // Show content
        $('#reportContent').show();
    }

    function populateTables(details) {
        // Clear existing tables
        $('#tableAll tbody, #tableOnTime tbody, #tableLate tbody, #tableAbsent tbody, #tablePTO tbody, #tableFlexible tbody').empty();

        // Destroy existing DataTables
        if ($.fn.DataTable.isDataTable('#tableAll')) {
            $('#tableAll').DataTable().destroy();
        }

        details.forEach(function(d) {
            const statusBadge = getStatusBadge(d.status);
            const schedTime = d.scheduled_start && d.scheduled_end ?
                formatTime(d.scheduled_start) + ' - ' + formatTime(d.scheduled_end) : '-';

            // All table
            const allRow = `<tr>
                <td><strong>${escapeHtml(d.full_name)}</strong></td>
                <td>${statusBadge}</td>
                <td>${schedTime}</td>
                <td>${d.actual_start ? formatTime(d.actual_start) : '-'}</td>
                <td>${d.actual_end ? formatTime(d.actual_end) : '-'}</td>
                <td>${d.scheduled_hours ? parseFloat(d.scheduled_hours).toFixed(1) : '-'}</td>
                <td>${d.worked_hours ? parseFloat(d.worked_hours).toFixed(2) : '-'}</td>
                <td>${d.late_minutes > 0 ? '<span class="text-danger">+' + d.late_minutes + '</span>' : '-'}</td>
            </tr>`;
            $('#tableAll tbody').append(allRow);

            // Category tables
            if (d.status === 'on_time') {
                $('#tableOnTime tbody').append(`<tr>
                    <td><strong>${escapeHtml(d.full_name)}</strong></td>
                    <td>${schedTime}</td>
                    <td>${formatTime(d.actual_start)}</td>
                    <td>${d.actual_end ? formatTime(d.actual_end) : '-'}</td>
                    <td>${parseFloat(d.worked_hours).toFixed(2)}</td>
                </tr>`);
            } else if (d.status.startsWith('late_')) {
                const hasNotice = d.status === 'late_with_notice';
                $('#tableLate tbody').append(`<tr>
                    <td><strong>${escapeHtml(d.full_name)}</strong></td>
                    <td>${hasNotice ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-danger">No</span>'}</td>
                    <td>${schedTime}</td>
                    <td>${formatTime(d.actual_start)}</td>
                    <td><span class="text-danger">+${d.late_minutes} min</span></td>
                    <td>${parseFloat(d.worked_hours).toFixed(2)}</td>
                </tr>`);
            } else if (d.status.startsWith('absent_')) {
                const hasNotice = d.status === 'absent_with_notice';
                $('#tableAbsent tbody').append(`<tr>
                    <td><strong>${escapeHtml(d.full_name)}</strong></td>
                    <td>${hasNotice ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-danger">No</span>'}</td>
                    <td>${schedTime}</td>
                    <td>${d.scheduled_hours ? parseFloat(d.scheduled_hours).toFixed(1) : '-'}</td>
                    <td>${d.notes ? escapeHtml(d.notes) : '-'}</td>
                </tr>`);
            } else if (d.status === 'pto' || d.status === 'unpaid_to') {
                const typeLabel = d.status === 'pto' ? 'PTO' : 'Unpaid TO';
                $('#tablePTO tbody').append(`<tr>
                    <td><strong>${escapeHtml(d.full_name)}</strong></td>
                    <td><span class="badge badge-info">${typeLabel}</span></td>
                    <td>${d.scheduled_hours ? parseFloat(d.scheduled_hours).toFixed(1) : '-'}</td>
                    <td>${d.notes ? escapeHtml(d.notes) : '-'}</td>
                </tr>`);
            } else if (d.status === 'flexible') {
                $('#tableFlexible tbody').append(`<tr>
                    <td><strong>${escapeHtml(d.full_name)}</strong></td>
                    <td>${d.actual_start ? formatTime(d.actual_start) : '-'}</td>
                    <td>${d.actual_end ? formatTime(d.actual_end) : '-'}</td>
                    <td>${d.worked_hours ? parseFloat(d.worked_hours).toFixed(2) : '-'}</td>
                </tr>`);
            }
        });

        // Initialize DataTable for All
        $('#tableAll').DataTable({
            pageLength: 50,
            order: [[0, 'asc']],
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
        });
    }

    function getStatusBadge(status) {
        const badges = {
            'on_time': '<span class="badge badge-success">On Time</span>',
            'late_with_notice': '<span class="badge badge-warning">Late (Notice)</span>',
            'late_without_notice': '<span class="badge" style="background-color:#fd7e14;color:#fff;">Late (No Notice)</span>',
            'absent_with_notice': '<span class="badge" style="background-color:#6f42c1;color:#fff;">Absent (Notice)</span>',
            'absent_without_notice': '<span class="badge badge-danger">Absent (No Notice)</span>',
            'pto': '<span class="badge badge-info">PTO</span>',
            'unpaid_to': '<span class="badge badge-secondary">Unpaid TO</span>',
            'flexible': '<span class="badge" style="background-color:#20c997;color:#fff;">Flexible</span>',
            'off': '<span class="badge badge-light">Day Off</span>',
            'no_schedule': '<span class="badge badge-light">No Schedule</span>'
        };
        return badges[status] || '<span class="badge badge-secondary">' + status + '</span>';
    }

    function formatTime(time) {
        if (!time) return '-';
        const parts = time.split(':');
        if (parts.length >= 2) {
            let hour = parseInt(parts[0]);
            const min = parts[1];
            const ampm = hour >= 12 ? 'PM' : 'AM';
            hour = hour % 12 || 12;
            return hour + ':' + min + ' ' + ampm;
        }
        return time;
    }

    function escapeHtml(text) {
        if (!text) return '';
        return $('<div>').text(text).html();
    }

    function showError(message) {
        $('#progressCard').hide();
        $('#btnGenerateReport').prop('disabled', false);
        alert('Error: ' + message);
    }

    // Notices functions
    function loadNotices() {
        $.get('api/daily-report.php', {
            action: 'get_notices',
            date: currentDate
        }, function(response) {
            if (response.success) {
                renderNotices(response.notices);
            }
        });
    }

    function renderNotices(notices) {
        const tbody = $('#noticesList');
        tbody.empty();

        if (notices.length === 0) {
            tbody.append('<tr><td colspan="4" class="text-center text-muted">No notices for this date</td></tr>');
            return;
        }

        notices.forEach(function(n) {
            const typeLabels = {
                'pto': 'PTO',
                'unpaid_to': 'Unpaid TO',
                'sick_leave': 'Sick Leave',
                'late_notice': 'Late Notice',
                'absent_notice': 'Absent Notice'
            };
            tbody.append(`<tr>
                <td>${escapeHtml(n.full_name)}</td>
                <td><span class="badge badge-info">${typeLabels[n.notice_type] || n.notice_type}</span></td>
                <td>${n.reason ? escapeHtml(n.reason) : '-'}</td>
                <td>
                    <button class="btn btn-xs btn-danger" onclick="deleteNotice(${n.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`);
        });
    }

    function saveNotice() {
        const userId = $('#noticeUserId').val();
        const noticeType = $('#noticeType').val();
        const reason = $('#noticeReason').val();

        if (!userId || !noticeType) {
            alert('Please select a user and notice type');
            return;
        }

        $.post('api/daily-report.php', {
            action: 'save_notice',
            user_id: userId,
            date: currentDate,
            notice_type: noticeType,
            reason: reason
        }, function(response) {
            if (response.success) {
                $('#addNoticeForm')[0].reset();
                $('#noticeUserId').val('').trigger('change');
                loadNotices();
            } else {
                alert(response.error || 'Failed to save notice');
            }
        });
    }

    window.deleteNotice = function(noticeId) {
        if (!confirm('Delete this notice?')) return;

        $.post('api/daily-report.php', {
            action: 'delete_notice',
            notice_id: noticeId
        }, function(response) {
            if (response.success) {
                loadNotices();
            } else {
                alert(response.error || 'Failed to delete notice');
            }
        });
    };

    // ========== DAY SUMMARY FUNCTIONALITY ==========

    let summaryData = {
        date: '',
        late: [],
        absent: [],
        hours: {},
        workforce: {}
    };

    // Create Summary button click handler
    $('#btnCreateSummary').on('click', function() {
        if (allDetails.length === 0) {
            alert('Please generate or load a report first.');
            return;
        }
        createDaySummary();
    });

    function createDaySummary() {
        // Build summary data from current report
        summaryData.date = currentDate;
        summaryData.late = [];
        summaryData.absent = [];

        // Collect late and absent users
        allDetails.forEach(function(d) {
            if (d.status === 'late_with_notice' || d.status === 'late_without_notice') {
                summaryData.late.push({
                    name: d.full_name,
                    scheduled: d.scheduled_start && d.scheduled_end ?
                        formatTime(d.scheduled_start) + ' - ' + formatTime(d.scheduled_end) : '-',
                    checkIn: d.actual_start ? formatTime(d.actual_start) : '-',
                    lateMinutes: d.late_minutes || 0,
                    hasNotice: d.status === 'late_with_notice',
                    workedHours: d.worked_hours ? parseFloat(d.worked_hours).toFixed(2) : '0'
                });
            } else if (d.status === 'absent_with_notice' || d.status === 'absent_without_notice') {
                summaryData.absent.push({
                    name: d.full_name,
                    scheduled: d.scheduled_start && d.scheduled_end ?
                        formatTime(d.scheduled_start) + ' - ' + formatTime(d.scheduled_end) : '-',
                    scheduledHours: d.scheduled_hours ? parseFloat(d.scheduled_hours).toFixed(1) : '0',
                    hasNotice: d.status === 'absent_with_notice',
                    notes: d.notes || '-'
                });
            }
        });

        // Get hours data from the UI
        summaryData.hours = {
            scheduled: $('#hoursScheduled').text(),
            worked: $('#hoursWorked').text(),
            lost: $('#hoursLost').text()
        };

        // Get workforce data from the UI
        summaryData.workforce = {
            total: $('#totalUsers').text(),
            scheduled: $('#totalScheduled').text(),
            off: $('#totalOff').text(),
            onTime: $('#statOnTime').text(),
            lateWithNotice: $('#statLateWithNotice').text(),
            lateWithoutNotice: $('#statLateWithoutNotice').text(),
            absentWithNotice: $('#statAbsentWithNotice').text(),
            absentWithoutNotice: $('#statAbsentWithoutNotice').text(),
            pto: $('#statPTO').text()
        };

        // Build the summary HTML
        buildSummaryHTML();

        // Show modal
        $('#daySummaryModal').modal('show');
    }

    function buildSummaryHTML() {
        const dateFormatted = new Date(summaryData.date).toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });

        $('#summaryDate').text(dateFormatted);

        let html = '<div id="summaryPrintArea">';

        // Header
        html += `<div style="text-align: center; margin-bottom: 20px;">
            <h4 style="margin-bottom: 5px;">Daily Attendance Summary</h4>
            <p style="margin: 0; color: #666;">${dateFormatted}</p>
            <p style="margin: 0; font-size: 12px; color: #999;">Generated: ${new Date().toLocaleString('en-US', { timeZone: 'America/New_York' })} EST</p>
        </div>`;

        // Workforce Summary Section
        html += `<div class="summary-section workforce">
            <h5><i class="fas fa-users"></i> Workforce Summary</h5>
            <table>
                <tr>
                    <th style="width: 50%;">Metric</th>
                    <th style="width: 50%;">Count</th>
                </tr>
                <tr><td>Total Users</td><td>${summaryData.workforce.total}</td></tr>
                <tr><td>Scheduled to Work</td><td>${summaryData.workforce.scheduled}</td></tr>
                <tr><td>Day Off / No Schedule</td><td>${summaryData.workforce.off}</td></tr>
                <tr><td style="color: green;">On Time</td><td style="color: green;"><strong>${summaryData.workforce.onTime}</strong></td></tr>
                <tr><td style="color: #fd7e14;">Late (with Notice)</td><td style="color: #fd7e14;"><strong>${summaryData.workforce.lateWithNotice}</strong></td></tr>
                <tr><td style="color: #fd7e14;">Late (without Notice)</td><td style="color: #fd7e14;"><strong>${summaryData.workforce.lateWithoutNotice}</strong></td></tr>
                <tr><td style="color: red;">Absent (with Notice)</td><td style="color: red;"><strong>${summaryData.workforce.absentWithNotice}</strong></td></tr>
                <tr><td style="color: red;">Absent (without Notice)</td><td style="color: red;"><strong>${summaryData.workforce.absentWithoutNotice}</strong></td></tr>
                <tr><td style="color: #17a2b8;">PTO / Unpaid</td><td style="color: #17a2b8;"><strong>${summaryData.workforce.pto}</strong></td></tr>
            </table>
        </div>`;

        // Hours Summary Section
        html += `<div class="summary-section hours">
            <h5><i class="fas fa-clock"></i> Hours Summary</h5>
            <table>
                <tr>
                    <th style="width: 50%;">Metric</th>
                    <th style="width: 50%;">Hours</th>
                </tr>
                <tr><td>Total Scheduled Hours</td><td>${summaryData.hours.scheduled} hrs</td></tr>
                <tr><td>Total Worked Hours</td><td>${summaryData.hours.worked} hrs</td></tr>
                <tr><td style="color: red;">Lost Hours</td><td style="color: red;"><strong>${summaryData.hours.lost} hrs</strong></td></tr>
            </table>
        </div>`;

        // Late Users Section
        html += `<div class="summary-section late">
            <h5><i class="fas fa-clock"></i> Late Users (${summaryData.late.length})</h5>`;

        if (summaryData.late.length > 0) {
            html += `<table>
                <tr>
                    <th>Name</th>
                    <th>Scheduled</th>
                    <th>Check In</th>
                    <th>Late (min)</th>
                    <th>Notice</th>
                    <th>Worked Hrs</th>
                </tr>`;

            summaryData.late.forEach(function(user) {
                html += `<tr>
                    <td>${escapeHtml(user.name)}</td>
                    <td>${user.scheduled}</td>
                    <td>${user.checkIn}</td>
                    <td style="color: red;"><strong>+${user.lateMinutes}</strong></td>
                    <td>${user.hasNotice ? '<span style="color:green;">Yes</span>' : '<span style="color:red;">No</span>'}</td>
                    <td>${user.workedHours}</td>
                </tr>`;
            });

            html += '</table>';
        } else {
            html += '<p style="padding: 10px; text-align: center; color: #666;">No late users</p>';
        }
        html += '</div>';

        // Absent Users Section
        html += `<div class="summary-section absent">
            <h5><i class="fas fa-user-times"></i> Absent Users (${summaryData.absent.length})</h5>`;

        if (summaryData.absent.length > 0) {
            html += `<table>
                <tr>
                    <th>Name</th>
                    <th>Scheduled</th>
                    <th>Sched. Hrs</th>
                    <th>Notice</th>
                    <th>Notes</th>
                </tr>`;

            summaryData.absent.forEach(function(user) {
                html += `<tr>
                    <td>${escapeHtml(user.name)}</td>
                    <td>${user.scheduled}</td>
                    <td>${user.scheduledHours}</td>
                    <td>${user.hasNotice ? '<span style="color:green;">Yes</span>' : '<span style="color:red;">No</span>'}</td>
                    <td>${escapeHtml(user.notes)}</td>
                </tr>`;
            });

            html += '</table>';
        } else {
            html += '<p style="padding: 10px; text-align: center; color: #666;">No absent users</p>';
        }
        html += '</div>';

        html += '</div>'; // Close summaryPrintArea

        $('#summaryContent').html(html);
    }

    // Print summary
    $('#btnPrintSummary').on('click', function() {
        const printContents = document.getElementById('summaryPrintArea').innerHTML;
        const originalContents = document.body.innerHTML;

        // Create print window
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Daily Summary - ${summaryData.date}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    table { border-collapse: collapse; width: 100%; margin-bottom: 15px; }
                    th, td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
                    th { background-color: #f8f9fa; font-weight: bold; }
                    h4 { margin-bottom: 5px; }
                    h5 { background-color: #007bff; color: white; padding: 8px 12px; margin: 0; font-size: 14px; }
                    .summary-section { margin-bottom: 20px; }
                    .summary-section.late h5 { background-color: #fd7e14; }
                    .summary-section.absent h5 { background-color: #dc3545; }
                    .summary-section.hours h5 { background-color: #17a2b8; }
                    .summary-section.workforce h5 { background-color: #28a745; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>
                ${printContents}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();

        setTimeout(function() {
            printWindow.print();
            printWindow.close();
        }, 250);
    });

    // Copy to clipboard (for Excel)
    $('#btnCopySummary').on('click', function() {
        // Build tab-separated text for Excel
        let text = '';

        // Header
        const dateFormatted = new Date(summaryData.date).toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
        text += 'Daily Attendance Summary\n';
        text += dateFormatted + '\n\n';

        // Workforce Summary
        text += 'WORKFORCE SUMMARY\n';
        text += 'Metric\tCount\n';
        text += `Total Users\t${summaryData.workforce.total}\n`;
        text += `Scheduled to Work\t${summaryData.workforce.scheduled}\n`;
        text += `Day Off / No Schedule\t${summaryData.workforce.off}\n`;
        text += `On Time\t${summaryData.workforce.onTime}\n`;
        text += `Late (with Notice)\t${summaryData.workforce.lateWithNotice}\n`;
        text += `Late (without Notice)\t${summaryData.workforce.lateWithoutNotice}\n`;
        text += `Absent (with Notice)\t${summaryData.workforce.absentWithNotice}\n`;
        text += `Absent (without Notice)\t${summaryData.workforce.absentWithoutNotice}\n`;
        text += `PTO / Unpaid\t${summaryData.workforce.pto}\n\n`;

        // Hours Summary
        text += 'HOURS SUMMARY\n';
        text += 'Metric\tHours\n';
        text += `Total Scheduled Hours\t${summaryData.hours.scheduled}\n`;
        text += `Total Worked Hours\t${summaryData.hours.worked}\n`;
        text += `Lost Hours\t${summaryData.hours.lost}\n\n`;

        // Late Users
        text += `LATE USERS (${summaryData.late.length})\n`;
        if (summaryData.late.length > 0) {
            text += 'Name\tScheduled\tCheck In\tLate (min)\tNotice\tWorked Hrs\n';
            summaryData.late.forEach(function(user) {
                text += `${user.name}\t${user.scheduled}\t${user.checkIn}\t+${user.lateMinutes}\t${user.hasNotice ? 'Yes' : 'No'}\t${user.workedHours}\n`;
            });
        } else {
            text += 'No late users\n';
        }
        text += '\n';

        // Absent Users
        text += `ABSENT USERS (${summaryData.absent.length})\n`;
        if (summaryData.absent.length > 0) {
            text += 'Name\tScheduled\tSched. Hrs\tNotice\tNotes\n';
            summaryData.absent.forEach(function(user) {
                text += `${user.name}\t${user.scheduled}\t${user.scheduledHours}\t${user.hasNotice ? 'Yes' : 'No'}\t${user.notes}\n`;
            });
        } else {
            text += 'No absent users\n';
        }

        // Copy to clipboard
        navigator.clipboard.writeText(text).then(function() {
            // Show success message
            const btn = $('#btnCopySummary');
            const originalText = btn.html();
            btn.html('<i class="fas fa-check"></i> Copied!');
            btn.removeClass('btn-info').addClass('btn-success');

            setTimeout(function() {
                btn.html(originalText);
                btn.removeClass('btn-success').addClass('btn-info');
            }, 2000);
        }).catch(function(err) {
            alert('Failed to copy. Please select the content manually.');
            console.error('Copy failed:', err);
        });
    });

    // ========== END DAY SUMMARY FUNCTIONALITY ==========

    <?php if ($existingReport): ?>
    // Auto-load saved report if exists
    loadSavedReport();
    <?php endif; ?>
});
</script>

<?php include '../../components/footer.php'; ?>
