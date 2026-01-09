<?php
/**
 * TimeWorks Module - User Shifts
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Permission check
if (!has_permission('timeworks_shifts_view')) {
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

$page_title = "TimeWorks - User Shifts";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

include '../../components/header.php';
include '../../components/sidebar.php';

// Get all active users with their shifts (pivoted by day)
$stmt = $db->query("
    SELECT DISTINCT u.user_id, u.full_name, u.status
    FROM twr_users u
    INNER JOIN twr_user_shifts s ON u.user_id = s.user_id
    WHERE u.status = 'active'
    ORDER BY u.full_name ASC
");
$users = $stmt->fetchAll();

// Get users without schedules
$stmt = $db->query("
    SELECT u.user_id, u.full_name, u.email, u.status
    FROM twr_users u
    LEFT JOIN twr_user_shifts s ON u.user_id = s.user_id
    WHERE u.status = 'active' AND s.id IS NULL
    ORDER BY u.full_name ASC
");
$usersWithoutSchedule = $stmt->fetchAll();

// Get all shifts for each user
$shiftsData = [];
foreach ($users as $user) {
    $stmt = $db->prepare("
        SELECT day_of_week, start_time, end_time, is_off
        FROM twr_user_shifts
        WHERE user_id = ?
        ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
    ");
    $stmt->execute([$user['user_id']]);
    $shifts = $stmt->fetchAll();

    $userShifts = [
        'Monday' => null,
        'Tuesday' => null,
        'Wednesday' => null,
        'Thursday' => null,
        'Friday' => null,
        'Saturday' => null,
        'Sunday' => null
    ];

    foreach ($shifts as $shift) {
        $userShifts[$shift['day_of_week']] = $shift;
    }

    $shiftsData[$user['user_id']] = [
        'name' => $user['full_name'],
        'status' => $user['status'],
        'shifts' => $userShifts
    ];
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-calendar-alt"></i> User Shifts
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Shifts</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-calendar-week"></i> Weekly Schedules</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-warning me-2" id="bulkEditBtn" style="display: none;">
                            <i class="fas fa-edit"></i> Bulk Edit (<span id="selectedCount">0</span>)
                        </button>
                        <a href="index.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="shiftsTable" class="table table-bordered table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th style="vertical-align: middle; width: 40px;">
                                        <input type="checkbox" id="selectAll" class="form-check-input" title="Select All">
                                    </th>
                                    <th style="vertical-align: middle;">User</th>
                                    <th class="text-center">Monday</th>
                                    <th class="text-center">Tuesday</th>
                                    <th class="text-center">Wednesday</th>
                                    <th class="text-center">Thursday</th>
                                    <th class="text-center">Friday</th>
                                    <th class="text-center">Saturday</th>
                                    <th class="text-center">Sunday</th>
                                    <th class="text-center">Total Hrs/Week</th>
                                    <th class="text-center" style="width: 80px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shiftsData as $userId => $userData):
                                    $totalWeeklyHours = 0;

                                    // Calculate total weekly hours
                                    foreach ($userData['shifts'] as $day => $shift) {
                                        if ($shift && !$shift['is_off']) {
                                            $start = strtotime($shift['start_time']);
                                            $end = strtotime($shift['end_time']);

                                            // Handle overnight shifts (crossing midnight)
                                            if ($end <= $start) {
                                                $end += 86400; // Add 24 hours (86400 seconds)
                                            }

                                            $hours = ($end - $start) / 3600;
                                            $totalWeeklyHours += $hours;
                                        }
                                    }
                                ?>
                                    <tr data-user-id="<?php echo htmlspecialchars($userId); ?>">
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input user-checkbox" value="<?php echo htmlspecialchars($userId); ?>">
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($userData['name']); ?></strong>
                                        </td>
                                        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day):
                                            $shift = $userData['shifts'][$day];
                                        ?>
                                            <td class="text-center" style="padding: 8px;">
                                                <?php if ($shift): ?>
                                                    <?php if ($shift['is_off']): ?>
                                                        <span class="badge badge-secondary" style="font-size: 11px; padding: 5px 10px;">
                                                            <i class="fas fa-times"></i> OFF
                                                        </span>
                                                    <?php else: ?>
                                                        <?php
                                                        $start = strtotime($shift['start_time']);
                                                        $end = strtotime($shift['end_time']);

                                                        // Handle overnight shifts (crossing midnight)
                                                        if ($end <= $start) {
                                                            $end += 86400; // Add 24 hours (86400 seconds)
                                                        }

                                                        $hours = ($end - $start) / 3600;
                                                        ?>
                                                        <div style="white-space: nowrap;">
                                                            <strong><?php echo date('H:i', strtotime($shift['start_time'])); ?>-<?php echo date('H:i', strtotime($shift['end_time'])); ?></strong>
                                                            <span style="font-size: 11px; color: #666;">
                                                                (<?php echo number_format($hours, 1); ?>h)
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="text-center" style="vertical-align: middle;">
                                            <strong class="text-primary" style="font-size: 16px;">
                                                <?php echo number_format($totalWeeklyHours, 1); ?>h
                                            </strong>
                                        </td>
                                        <td class="text-center" style="vertical-align: middle;">
                                            <button type="button" class="btn btn-sm btn-outline-primary edit-shift-btn"
                                                    data-user-id="<?php echo htmlspecialchars($userId); ?>"
                                                    data-user-name="<?php echo htmlspecialchars($userData['name']); ?>"
                                                    title="Edit Schedule">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-bar"></i> Schedule Statistics</h3>
                        </div>
                        <div class="card-body">
                            <?php
                            $totalUsers = count($shiftsData);
                            $avgWeeklyHours = 0;
                            $maxWeeklyHours = 0;
                            $minWeeklyHours = PHP_FLOAT_MAX;

                            foreach ($shiftsData as $userData) {
                                $weeklyHours = 0;
                                foreach ($userData['shifts'] as $shift) {
                                    if ($shift && !$shift['is_off']) {
                                        $start = strtotime($shift['start_time']);
                                        $end = strtotime($shift['end_time']);

                                        // Handle overnight shifts (crossing midnight)
                                        if ($end <= $start) {
                                            $end += 86400; // Add 24 hours (86400 seconds)
                                        }

                                        $weeklyHours += ($end - $start) / 3600;
                                    }
                                }

                                $avgWeeklyHours += $weeklyHours;
                                $maxWeeklyHours = max($maxWeeklyHours, $weeklyHours);
                                $minWeeklyHours = min($minWeeklyHours, $weeklyHours);
                            }

                            $avgWeeklyHours = $totalUsers > 0 ? $avgWeeklyHours / $totalUsers : 0;
                            ?>
                            <table class="table table-sm">
                                <tr>
                                    <th>Total Users with Schedules:</th>
                                    <td class="text-right"><strong><?php echo $totalUsers; ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Average Weekly Hours:</th>
                                    <td class="text-right"><strong><?php echo number_format($avgWeeklyHours, 1); ?>h</strong></td>
                                </tr>
                                <tr>
                                    <th>Maximum Weekly Hours:</th>
                                    <td class="text-right"><strong><?php echo number_format($maxWeeklyHours, 1); ?>h</strong></td>
                                </tr>
                                <tr>
                                    <th>Minimum Weekly Hours:</th>
                                    <td class="text-right"><strong><?php echo ($minWeeklyHours < PHP_FLOAT_MAX && $minWeeklyHours >= 0) ? number_format($minWeeklyHours, 1) . 'h' : 'N/A'; ?></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <?php if (!empty($usersWithoutSchedule)): ?>
            <!-- Users Without Schedule -->
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-exclamation-triangle"></i> Users Without Schedule (<?= count($usersWithoutSchedule) ?>)
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted">These users do not have a work schedule defined. Click the button to assign a schedule.</p>
                    <div class="table-responsive">
                        <table id="noScheduleTable" class="table table-bordered table-hover">
                            <thead class="table-warning">
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAllNoSchedule" class="form-check-input" title="Select All">
                                    </th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersWithoutSchedule as $user): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input no-schedule-checkbox" value="<?= htmlspecialchars($user['user_id']) ?>">
                                    </td>
                                    <td><strong><?= htmlspecialchars($user['full_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-primary assign-schedule-btn"
                                                data-user-id="<?= htmlspecialchars($user['user_id']) ?>"
                                                data-user-name="<?= htmlspecialchars($user['full_name']) ?>"
                                                title="Assign Schedule">
                                            <i class="fas fa-calendar-plus"></i> Assign
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button type="button" class="btn btn-warning" id="bulkAssignBtn" style="display: none;">
                            <i class="fas fa-calendar-plus"></i> Bulk Assign Schedule (<span id="noScheduleSelectedCount">0</span>)
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#shiftsTable').DataTable({
        responsive: false,
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
        order: [[1, 'asc']], // Sort by name column (now index 1)
        scrollX: true,
        autoWidth: false,
        columnDefs: [
            { targets: [0], width: '40px', orderable: false }, // Checkbox
            { targets: [1], width: '150px' }, // Name
            { targets: [2, 3, 4, 5, 6, 7, 8], width: '110px', orderable: false }, // Days
            { targets: [9], width: '100px', orderable: true }, // Total hours
            { targets: [10], width: '80px', orderable: false } // Actions
        ],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search users..."
        }
    });

    // Selected user IDs for bulk edit
    var selectedUserIds = [];

    // Update bulk edit button visibility
    function updateBulkEditButton() {
        selectedUserIds = [];
        $('.user-checkbox:checked').each(function() {
            selectedUserIds.push($(this).val());
        });
        $('#selectedCount').text(selectedUserIds.length);
        if (selectedUserIds.length > 0) {
            $('#bulkEditBtn').show();
        } else {
            $('#bulkEditBtn').hide();
        }
    }

    // Select all checkbox
    $('#selectAll').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.user-checkbox').prop('checked', isChecked);
        updateBulkEditButton();
    });

    // Individual checkbox change
    $(document).on('change', '.user-checkbox', function() {
        updateBulkEditButton();
        // Update select all checkbox
        var allChecked = $('.user-checkbox').length === $('.user-checkbox:checked').length;
        $('#selectAll').prop('checked', allChecked);
    });

    // Calculate hours for a day row
    function calculateDayHours(row) {
        var isOff = row.find('.day-off-check').prop('checked');
        var startTime = row.find('.start-time').val();
        var endTime = row.find('.end-time').val();
        var hoursSpan = row.find('.day-hours');

        if (isOff) {
            hoursSpan.text('OFF').removeClass('bg-secondary bg-primary').addClass('bg-warning');
            row.find('.start-time, .end-time').prop('disabled', true);
            return 0;
        } else {
            row.find('.start-time, .end-time').prop('disabled', false);

            if (startTime && endTime) {
                var start = new Date('2000-01-01 ' + startTime);
                var end = new Date('2000-01-01 ' + endTime);

                // Handle overnight shifts
                if (end <= start) {
                    end = new Date('2000-01-02 ' + endTime);
                }

                var hours = (end - start) / (1000 * 60 * 60);
                hoursSpan.text(hours.toFixed(1) + 'h').removeClass('bg-warning bg-primary').addClass('bg-secondary');
                return hours;
            }
            return 0;
        }
    }

    // Calculate total weekly hours
    function calculateTotalHours() {
        var total = 0;
        $('#editScheduleModal tbody tr').each(function() {
            total += calculateDayHours($(this));
        });
        $('#totalWeeklyHours').text(total.toFixed(1) + 'h');
    }

    // Time/checkbox change handlers
    $(document).on('change', '.start-time, .end-time, .day-off-check', function() {
        calculateTotalHours();
    });

    // Load user shifts into modal
    function loadUserShifts(userId, callback) {
        $.ajax({
            url: 'api/update-shifts.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'get_shifts',
                user_id: userId
            }),
            success: function(response) {
                if (response.success) {
                    // Reset all days first
                    var days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                    days.forEach(function(day) {
                        $('#off_' + day).prop('checked', false);
                        $('#start_' + day).val('09:00').prop('disabled', false);
                        $('#end_' + day).val('18:00').prop('disabled', false);
                    });

                    // Apply loaded shifts
                    response.shifts.forEach(function(shift) {
                        var day = shift.day_of_week;
                        $('#off_' + day).prop('checked', shift.is_off == 1);
                        $('#start_' + day).val(shift.start_time.substring(0, 5));
                        $('#end_' + day).val(shift.end_time.substring(0, 5));
                    });

                    calculateTotalHours();

                    if (callback) callback(response);
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to load schedule', 'error');
            }
        });
    }

    // Individual edit button click
    $(document).on('click', '.edit-shift-btn', function() {
        var userId = $(this).data('user-id');
        var userName = $(this).data('user-name');

        $('#editUserId').val(userId);
        $('#editMode').val('single');
        $('#editScheduleModalTitle').html('<i class="fas fa-calendar-edit"></i> Edit Schedule - ' + userName);
        $('#bulkEditInfo').hide();

        loadUserShifts(userId, function() {
            var modal = new bootstrap.Modal(document.getElementById('editScheduleModal'));
            modal.show();
        });
    });

    // Bulk edit button click
    $('#bulkEditBtn').on('click', function() {
        if (selectedUserIds.length === 0) {
            Swal.fire('Warning', 'Please select at least one user', 'warning');
            return;
        }

        $('#editUserId').val(selectedUserIds.join(','));
        $('#editMode').val('bulk');
        $('#editScheduleModalTitle').html('<i class="fas fa-calendar-edit"></i> Bulk Edit Schedule');
        $('#bulkUserCount').text(selectedUserIds.length);
        $('#bulkEditInfo').show();

        // Reset to default schedule
        var days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        days.forEach(function(day) {
            var isWeekend = (day === 'Saturday' || day === 'Sunday');
            $('#off_' + day).prop('checked', isWeekend);
            $('#start_' + day).val('09:00').prop('disabled', isWeekend);
            $('#end_' + day).val('18:00').prop('disabled', isWeekend);
        });

        calculateTotalHours();

        var modal = new bootstrap.Modal(document.getElementById('editScheduleModal'));
        modal.show();
    });

    // Preset buttons
    $('.preset-btn').on('click', function() {
        var preset = $(this).data('preset');
        var days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        switch (preset) {
            case '9to6':
                days.forEach(function(day) {
                    var isWeekend = (day === 'Saturday' || day === 'Sunday');
                    $('#off_' + day).prop('checked', isWeekend);
                    $('#start_' + day).val('09:00');
                    $('#end_' + day).val('18:00');
                });
                break;
            case '9to5':
                days.forEach(function(day) {
                    var isWeekend = (day === 'Saturday' || day === 'Sunday');
                    $('#off_' + day).prop('checked', isWeekend);
                    $('#start_' + day).val('09:00');
                    $('#end_' + day).val('17:00');
                });
                break;
            case '8to5':
                days.forEach(function(day) {
                    var isWeekend = (day === 'Saturday' || day === 'Sunday');
                    $('#off_' + day).prop('checked', isWeekend);
                    $('#start_' + day).val('08:00');
                    $('#end_' + day).val('17:00');
                });
                break;
            case 'night':
                days.forEach(function(day) {
                    var isWeekend = (day === 'Saturday' || day === 'Sunday');
                    $('#off_' + day).prop('checked', isWeekend);
                    $('#start_' + day).val('19:00');
                    $('#end_' + day).val('04:00');
                });
                break;
            case 'alloff':
                days.forEach(function(day) {
                    $('#off_' + day).prop('checked', true);
                });
                break;
        }

        calculateTotalHours();
    });

    // Save schedule
    $('#saveScheduleBtn').on('click', function() {
        var mode = $('#editMode').val();
        var shifts = {};

        // Collect shift data
        var days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        days.forEach(function(day) {
            shifts[day] = {
                is_off: $('#off_' + day).prop('checked') ? 1 : 0,
                start_time: $('#start_' + day).val() + ':00',
                end_time: $('#end_' + day).val() + ':00'
            };
        });

        var requestData = {
            action: mode === 'bulk' ? 'update_bulk' : 'update_single',
            shifts: shifts
        };

        if (mode === 'bulk') {
            requestData.user_ids = selectedUserIds;
        } else {
            requestData.user_id = $('#editUserId').val();
        }

        // Show loading
        Swal.fire({
            title: 'Saving...',
            text: 'Updating schedule',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'api/update-shifts.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(requestData),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to save schedule', 'error');
            }
        });
    });

    // ========== USERS WITHOUT SCHEDULE HANDLERS ==========

    // Selected user IDs for bulk assign (no schedule)
    var selectedNoScheduleIds = [];

    // Update bulk assign button visibility
    function updateBulkAssignButton() {
        selectedNoScheduleIds = [];
        $('.no-schedule-checkbox:checked').each(function() {
            selectedNoScheduleIds.push($(this).val());
        });
        $('#noScheduleSelectedCount').text(selectedNoScheduleIds.length);
        if (selectedNoScheduleIds.length > 0) {
            $('#bulkAssignBtn').show();
        } else {
            $('#bulkAssignBtn').hide();
        }
    }

    // Select all checkbox (no schedule)
    $('#selectAllNoSchedule').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.no-schedule-checkbox').prop('checked', isChecked);
        updateBulkAssignButton();
    });

    // Individual checkbox change (no schedule)
    $(document).on('change', '.no-schedule-checkbox', function() {
        updateBulkAssignButton();
        var allChecked = $('.no-schedule-checkbox').length === $('.no-schedule-checkbox:checked').length;
        $('#selectAllNoSchedule').prop('checked', allChecked);
    });

    // Individual assign schedule button
    $(document).on('click', '.assign-schedule-btn', function() {
        var userId = $(this).data('user-id');
        var userName = $(this).data('user-name');

        $('#editUserId').val(userId);
        $('#editMode').val('single');
        $('#editScheduleModalTitle').html('<i class="fas fa-calendar-plus"></i> Assign Schedule - ' + userName);
        $('#bulkEditInfo').hide();

        // Reset to default schedule (weekdays 9-6)
        var days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        days.forEach(function(day) {
            var isWeekend = (day === 'Saturday' || day === 'Sunday');
            $('#off_' + day).prop('checked', isWeekend);
            $('#start_' + day).val('09:00').prop('disabled', isWeekend);
            $('#end_' + day).val('18:00').prop('disabled', isWeekend);
        });

        calculateTotalHours();

        var modal = new bootstrap.Modal(document.getElementById('editScheduleModal'));
        modal.show();
    });

    // Bulk assign button click
    $('#bulkAssignBtn').on('click', function() {
        if (selectedNoScheduleIds.length === 0) {
            Swal.fire('Warning', 'Please select at least one user', 'warning');
            return;
        }

        $('#editUserId').val(selectedNoScheduleIds.join(','));
        $('#editMode').val('bulk');
        $('#editScheduleModalTitle').html('<i class="fas fa-calendar-plus"></i> Bulk Assign Schedule');
        $('#bulkUserCount').text(selectedNoScheduleIds.length);
        $('#bulkEditInfo').show();

        // Reset to default schedule
        var days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        days.forEach(function(day) {
            var isWeekend = (day === 'Saturday' || day === 'Sunday');
            $('#off_' + day).prop('checked', isWeekend);
            $('#start_' + day).val('09:00').prop('disabled', isWeekend);
            $('#end_' + day).val('18:00').prop('disabled', isWeekend);
        });

        calculateTotalHours();

        var modal = new bootstrap.Modal(document.getElementById('editScheduleModal'));
        modal.show();
    });
});
</script>

<style>
/* Custom styling for better readability */
#shiftsTable {
    width: 100% !important;
}

#shiftsTable td {
    white-space: nowrap;
}

#shiftsTable tbody tr:hover {
    background-color: #f8f9fa;
}

.table-primary th {
    background-color: #007bff !important;
    color: white !important;
    font-weight: 600;
}

.table-responsive {
    overflow-x: auto;
}
</style>

<!-- Edit Schedule Modal -->
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editScheduleModalTitle">
                    <i class="fas fa-calendar-edit"></i> Edit Schedule
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editUserId">
                <input type="hidden" id="editMode" value="single">

                <!-- Bulk Edit Info -->
                <div id="bulkEditInfo" class="alert alert-info" style="display: none;">
                    <i class="fas fa-users"></i> Editing schedule for <strong><span id="bulkUserCount">0</span> users</strong>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 120px;">Day</th>
                                <th class="text-center" style="width: 80px;">Off Day</th>
                                <th class="text-center">Start Time</th>
                                <th class="text-center">End Time</th>
                                <th class="text-center" style="width: 100px;">Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                            <tr data-day="<?php echo $day; ?>">
                                <td><strong><?php echo $day; ?></strong></td>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input day-off-check" id="off_<?php echo $day; ?>">
                                </td>
                                <td>
                                    <input type="time" class="form-control form-control-sm start-time" id="start_<?php echo $day; ?>" value="09:00">
                                </td>
                                <td>
                                    <input type="time" class="form-control form-control-sm end-time" id="end_<?php echo $day; ?>" value="18:00">
                                </td>
                                <td class="text-center">
                                    <span class="day-hours badge bg-secondary">9.0h</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end"><strong>Total Weekly Hours:</strong></td>
                                <td class="text-center"><strong class="text-primary" id="totalWeeklyHours">45.0h</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Quick Presets -->
                <div class="card card-outline card-info mt-3">
                    <div class="card-header py-2">
                        <h6 class="card-title mb-0"><i class="fas fa-magic"></i> Quick Presets</h6>
                    </div>
                    <div class="card-body py-2">
                        <div class="btn-group flex-wrap" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-btn" data-preset="9to6">
                                9AM-6PM (Mon-Fri)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-btn" data-preset="9to5">
                                9AM-5PM (Mon-Fri)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-btn" data-preset="8to5">
                                8AM-5PM (Mon-Fri)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-btn" data-preset="night">
                                7PM-4AM (Mon-Fri)
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary preset-btn" data-preset="alloff">
                                All Days Off
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveScheduleBtn">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<?php include '../../components/footer.php'; ?>
