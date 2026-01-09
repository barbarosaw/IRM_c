<?php
/**
 * TimeWorks Module - Leave Requests Management
 *
 * Manages PTO, UPTO, and other leave requests with approval workflow.
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission
if (!has_permission('timeworks_leave_view')) {
    header('Location: ../../access-denied.php');
    exit;
}

$canManage = has_permission('timeworks_leave_manage');
$page_title = "TimeWorks - Leave Requests";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Get leave types for filter
$stmt = $db->query("SELECT * FROM twr_leave_types WHERE is_active = 1 ORDER BY sort_order");
$leaveTypes = $stmt->fetchAll();

// Get users for filter
$stmt = $db->query("SELECT user_id, full_name FROM twr_users WHERE status = 'active' ORDER BY full_name");
$users = $stmt->fetchAll();

// Get statistics
$stats = [
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'this_month' => 0
];

$stmt = $db->query("
    SELECT
        status,
        COUNT(*) as count
    FROM twr_leave_requests
    GROUP BY status
");
while ($row = $stmt->fetch()) {
    if (isset($stats[$row['status']])) {
        $stats[$row['status']] = $row['count'];
    }
}

$stmt = $db->prepare("
    SELECT COUNT(*) FROM twr_leave_requests
    WHERE MONTH(start_date) = MONTH(CURRENT_DATE())
    AND YEAR(start_date) = YEAR(CURRENT_DATE())
    AND status = 'approved'
");
$stmt->execute();
$stats['this_month'] = $stmt->fetchColumn();

include '../../components/header.php';
include '../../components/sidebar.php';
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
                        <li class="breadcrumb-item active">Leave Requests</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?= $stats['pending'] ?></h3>
                            <p>Pending Requests</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?= $stats['approved'] ?></h3>
                            <p>Approved</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?= $stats['rejected'] ?></h3>
                            <p>Rejected</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?= $stats['this_month'] ?></h3>
                            <p>This Month</p>
                        </div>
                        <div class="icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filters</h3>
                    <div class="card-tools">
                        <?php if ($canManage): ?>
                        <button type="button" class="btn btn-info btn-sm" id="btnSyncTimeOff">
                            <i class="fas fa-sync mr-1"></i> Sync from TimeWorks
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newRequestModal">
                            <i class="fas fa-plus mr-1"></i> New Request
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" id="filterStatus" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Leave Type</label>
                            <select name="leave_type" id="filterLeaveType" class="form-select">
                                <option value="">All Types</option>
                                <?php foreach ($leaveTypes as $type): ?>
                                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">User</label>
                            <select name="user_id" id="filterUser" class="form-select select2">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?= htmlspecialchars($user['user_id']) ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="filterStartDate" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" id="filterEndDate" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div>
                                <button type="button" id="btnApplyFilter" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <button type="button" id="btnResetFilter" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Leave Requests Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list mr-2"></i>Leave Requests</h3>
                </div>
                <div class="card-body">
                    <table id="leaveRequestsTable" class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Leave Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- New Request Modal -->
<div class="modal fade" id="newRequestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>New Leave Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="newRequestForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">User <span class="text-danger">*</span></label>
                                <select name="user_id" id="newUserId" class="form-select select2" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?= htmlspecialchars($user['user_id']) ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Leave Type <span class="text-danger">*</span></label>
                                <select name="leave_type_id" id="newLeaveType" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <?php foreach ($leaveTypes as $type): ?>
                                    <option value="<?= $type['id'] ?>" data-paid="<?= $type['is_paid'] ?>">
                                        <?= htmlspecialchars($type['name']) ?>
                                        (<?= $type['is_paid'] ? 'Paid' : 'Unpaid' ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Start Date <span class="text-danger">*</span></label>
                                <input type="date" name="start_date" id="newStartDate" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">End Date <span class="text-danger">*</span></label>
                                <input type="date" name="end_date" id="newEndDate" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Total Days</label>
                                <input type="text" id="newTotalDays" class="form-control" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" id="newReason" class="form-control" rows="3" placeholder="Enter reason for leave request..."></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_client_paid" id="newClientPaid" class="form-check-input" value="1">
                        <label class="form-check-label" for="newClientPaid">
                            Client-Paid Leave (does not count as shrinkage)
                        </label>
                    </div>
                    <?php if ($canManage): ?>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="auto_approve" id="newAutoApprove" class="form-check-input" value="1">
                        <label class="form-check-label" for="newAutoApprove">
                            Auto-approve this request
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-1"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-clipboard-check mr-2"></i>Review Leave Request</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="reviewForm">
                <input type="hidden" name="id" id="reviewId">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>User:</strong>
                            <p id="reviewUser" class="mb-0"></p>
                        </div>
                        <div class="col-md-6">
                            <strong>Leave Type:</strong>
                            <p id="reviewLeaveType" class="mb-0"></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Start Date:</strong>
                            <p id="reviewStartDate" class="mb-0"></p>
                        </div>
                        <div class="col-md-4">
                            <strong>End Date:</strong>
                            <p id="reviewEndDate" class="mb-0"></p>
                        </div>
                        <div class="col-md-4">
                            <strong>Days Requested:</strong>
                            <p id="reviewDays" class="mb-0"></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <strong>Reason:</strong>
                        <p id="reviewReason" class="mb-0 text-muted"></p>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="form-label">Decision <span class="text-danger">*</span></label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="status" id="statusApproved" value="approved">
                            <label class="btn btn-outline-success" for="statusApproved">
                                <i class="fas fa-check mr-1"></i> Approve
                            </label>
                            <input type="radio" class="btn-check" name="status" id="statusRejected" value="rejected">
                            <label class="btn btn-outline-danger" for="statusRejected">
                                <i class="fas fa-times mr-1"></i> Reject
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="reviewNotes" class="form-control" rows="3" placeholder="Add notes about this decision..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Save Decision
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="fas fa-eye mr-2"></i>Leave Request Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Employee:</strong>
                        <p id="viewUser" class="mb-0 fs-5"></p>
                        <small id="viewEmail" class="text-muted"></small>
                    </div>
                    <div class="col-md-6">
                        <strong>Status:</strong>
                        <p id="viewStatus" class="mb-0"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Leave Type:</strong>
                        <p id="viewLeaveType" class="mb-0"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Hours Requested:</strong>
                        <p id="viewHours" class="mb-0"></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Start Date:</strong>
                        <p id="viewStartDate" class="mb-0"></p>
                    </div>
                    <div class="col-md-4">
                        <strong>End Date:</strong>
                        <p id="viewEndDate" class="mb-0"></p>
                    </div>
                    <div class="col-md-4">
                        <strong>Total Days:</strong>
                        <p id="viewDays" class="mb-0"></p>
                    </div>
                </div>
                <div class="mb-3">
                    <strong>Reason:</strong>
                    <p id="viewReason" class="mb-0 text-muted"></p>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Requested At:</strong>
                        <p id="viewRequestedAt" class="mb-0 text-muted"></p>
                    </div>
                    <div class="col-md-6">
                        <strong>Reviewed By:</strong>
                        <p id="viewApprovedBy" class="mb-0 text-muted"></p>
                    </div>
                </div>
                <div id="viewNotesSection" class="mt-3" style="display: none;">
                    <strong>Review Notes:</strong>
                    <p id="viewNotes" class="mb-0 text-muted"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Fix z-index for modals */
.modal-backdrop { z-index: 1050 !important; }
.modal { z-index: 1055 !important; }
#viewDetailsModal { z-index: 1060 !important; }
#reviewModal { z-index: 1060 !important; }
#newRequestModal { z-index: 1060 !important; }

/* Override global Select2 z-index when modal is open */
body.modal-open .select2-container {
    z-index: 1040 !important;
}
/* Select2 inside modals should be above modal */
.modal .select2-container {
    z-index: 1070 !important;
}
</style>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

    // Close any open Select2 dropdowns when modal opens
    $('.modal').on('show.bs.modal', function() {
        $('.select2').each(function() {
            if ($(this).data('select2')) {
                $(this).select2('close');
            }
        });
    });

    // Initialize DataTable
    const table = $('#leaveRequestsTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: 'api/leave-requests.php',
            type: 'POST',
            xhrFields: { withCredentials: true },
            data: function(d) {
                return {
                    action: 'list',
                    status: $('#filterStatus').val(),
                    leave_type: $('#filterLeaveType').val(),
                    user_id: $('#filterUser').val(),
                    start_date: $('#filterStartDate').val(),
                    end_date: $('#filterEndDate').val()
                };
            },
            dataSrc: function(json) {
                return json.data || [];
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables AJAX error:', xhr.status, xhr.responseText);
                if (xhr.status === 401) {
                    Swal.fire('Session Expired', 'Please refresh the page and try again.', 'warning');
                }
            }
        },
        columns: [
            { data: 'full_name' },
            {
                data: 'leave_type_name',
                render: function(data, type, row) {
                    const badge = row.is_paid ? 'Paid' : 'Unpaid';
                    const badgeClass = row.is_paid ? 'success' : 'secondary';
                    const leaveColor = row.leave_color || '#6c757d';
                    return `<span class="badge" style="background-color: ${leaveColor};">${data}</span>
                            <small class="text-muted ms-1">(${badge})</small>`;
                }
            },
            {
                data: 'start_date',
                render: function(data) {
                    return data ? new Date(data).toLocaleDateString() : '-';
                }
            },
            {
                data: 'end_date',
                render: function(data) {
                    return data ? new Date(data).toLocaleDateString() : '-';
                }
            },
            {
                data: 'days_count',
                render: function(data) {
                    return `<span class="badge bg-info">${data} day${data > 1 ? 's' : ''}</span>`;
                }
            },
            {
                data: 'reason',
                render: function(data) {
                    if (!data) return '<span class="text-muted">-</span>';
                    return data.length > 50 ? data.substring(0, 50) + '...' : data;
                }
            },
            {
                data: 'status',
                render: function(data, type, row) {
                    const colors = {
                        'pending': 'warning',
                        'approved': 'success',
                        'rejected': 'danger',
                        'cancelled': 'secondary'
                    };
                    const icons = {
                        'pending': 'clock',
                        'approved': 'check-circle',
                        'rejected': 'times-circle',
                        'cancelled': 'ban'
                    };
                    return `<span class="badge bg-${colors[data] || 'secondary'}">
                        <i class="fas fa-${icons[data] || 'question'} mr-1"></i>
                        ${data.charAt(0).toUpperCase() + data.slice(1)}
                    </span>`;
                }
            },
            {
                data: 'requested_at',
                render: function(data) {
                    return data ? new Date(data).toLocaleDateString() : '-';
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    let buttons = '';

                    if (row.status === 'pending' && <?= $canManage ? 'true' : 'false' ?>) {
                        buttons += `<button class="btn btn-sm btn-info btn-review" data-id="${row.id}" title="Review">
                            <i class="fas fa-clipboard-check"></i>
                        </button> `;
                        buttons += `<button class="btn btn-sm btn-success btn-quick-approve" data-id="${row.id}" title="Quick Approve">
                            <i class="fas fa-check"></i>
                        </button> `;
                        buttons += `<button class="btn btn-sm btn-danger btn-quick-reject" data-id="${row.id}" title="Quick Reject">
                            <i class="fas fa-times"></i>
                        </button> `;
                    }

                    buttons += `<button class="btn btn-sm btn-secondary btn-view" data-id="${row.id}" title="View Details">
                        <i class="fas fa-eye"></i>
                    </button>`;

                    return buttons;
                }
            }
        ],
        order: [[7, 'desc']],
        pageLength: 25,
        responsive: true,
        language: {
            emptyTable: "No leave requests found"
        }
    });

    // Filter buttons
    $('#btnApplyFilter').on('click', function() {
        table.ajax.reload();
    });

    $('#btnResetFilter').on('click', function() {
        $('#filterForm')[0].reset();
        $('#filterUser').val('').trigger('change');
        table.ajax.reload();
    });

    // Calculate total days
    function calculateDays() {
        const start = new Date($('#newStartDate').val());
        const end = new Date($('#newEndDate').val());

        if (start && end && end >= start) {
            const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
            $('#newTotalDays').val(days + ' day' + (days > 1 ? 's' : ''));
        } else {
            $('#newTotalDays').val('');
        }
    }

    $('#newStartDate, #newEndDate').on('change', calculateDays);

    // New request form
    $('#newRequestForm').on('submit', function(e) {
        e.preventDefault();

        const formData = {
            action: 'create',
            user_id: $('#newUserId').val(),
            leave_type_id: $('#newLeaveType').val(),
            start_date: $('#newStartDate').val(),
            end_date: $('#newEndDate').val(),
            reason: $('#newReason').val(),
            is_client_paid: $('#newClientPaid').is(':checked') ? 1 : 0,
            auto_approve: $('#newAutoApprove').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: 'api/leave-requests.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success', 'Leave request created successfully', 'success');
                    $('#newRequestModal').modal('hide');
                    $('#newRequestForm')[0].reset();
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', response.message || 'Failed to create request', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server error occurred', 'error');
            }
        });
    });

    // Review button
    $(document).on('click', '.btn-review', function() {
        const id = $(this).data('id');

        $.ajax({
            url: 'api/leave-requests.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'get', id: id }),
            success: function(response) {
                if (response.success) {
                    const r = response.request;
                    $('#reviewId').val(r.id);
                    $('#reviewUser').text(r.full_name);
                    $('#reviewLeaveType').text(r.leave_type_name + (r.is_paid ? ' (Paid)' : ' (Unpaid)'));
                    $('#reviewStartDate').text(new Date(r.start_date).toLocaleDateString());
                    $('#reviewEndDate').text(new Date(r.end_date).toLocaleDateString());
                    $('#reviewDays').text(r.days_count);
                    $('#reviewReason').text(r.reason || 'No reason provided');
                    $('#reviewNotes').val('');
                    $('input[name="status"]').prop('checked', false);
                    $('#reviewModal').modal('show');
                } else {
                    Swal.fire('Error', response.message || 'Failed to load request', 'error');
                }
            }
        });
    });

    // Review form submit
    $('#reviewForm').on('submit', function(e) {
        e.preventDefault();

        const status = $('input[name="status"]:checked').val();
        if (!status) {
            Swal.fire('Error', 'Please select a decision', 'error');
            return;
        }

        $.ajax({
            url: 'api/leave-requests.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'review',
                id: $('#reviewId').val(),
                status: status,
                notes: $('#reviewNotes').val()
            }),
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success', 'Decision saved successfully', 'success');
                    $('#reviewModal').modal('hide');
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', response.message || 'Failed to save decision', 'error');
                }
            }
        });
    });

    // Quick approve
    $(document).on('click', '.btn-quick-approve', function() {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Quick Approve',
            text: 'Are you sure you want to approve this leave request?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Yes, approve'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/leave-requests.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'review',
                        id: id,
                        status: 'approved',
                        notes: 'Quick approved'
                    }),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Approved', 'Leave request approved', 'success');
                            table.ajax.reload();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Quick reject
    $(document).on('click', '.btn-quick-reject', function() {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Quick Reject',
            text: 'Are you sure you want to reject this leave request?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, reject'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/leave-requests.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'review',
                        id: id,
                        status: 'rejected',
                        notes: 'Quick rejected'
                    }),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Rejected', 'Leave request rejected', 'success');
                            table.ajax.reload();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // View Details button
    $(document).on('click', '.btn-view', function() {
        const id = $(this).data('id');

        $.ajax({
            url: 'api/leave-requests.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'get', id: id }),
            success: function(response) {
                if (response.success) {
                    const r = response.request;

                    // Status badge
                    const statusColors = {
                        'pending': 'warning',
                        'approved': 'success',
                        'rejected': 'danger',
                        'cancelled': 'secondary'
                    };
                    const statusBadge = `<span class="badge bg-${statusColors[r.status] || 'secondary'}">${r.status.charAt(0).toUpperCase() + r.status.slice(1)}</span>`;

                    // Leave type with color
                    const leaveTypeColor = r.leave_color || '#6c757d';
                    const leaveTypeBadge = `<span class="badge" style="background-color: ${leaveTypeColor};">${r.leave_type_name}</span> ${r.is_paid ? '<small class="text-success">(Paid)</small>' : '<small class="text-secondary">(Unpaid)</small>'}`;

                    $('#viewUser').text(r.full_name);
                    $('#viewEmail').text(r.email || '');
                    $('#viewStatus').html(statusBadge);
                    $('#viewLeaveType').html(leaveTypeBadge);
                    $('#viewHours').text((r.hours_requested || 0) + ' hours');
                    $('#viewStartDate').text(r.start_date ? new Date(r.start_date).toLocaleDateString() : '-');
                    $('#viewEndDate').text(r.end_date ? new Date(r.end_date).toLocaleDateString() : '-');
                    $('#viewDays').text(r.days_count + ' day' + (r.days_count > 1 ? 's' : ''));
                    $('#viewReason').text(r.reason || 'No reason provided');
                    $('#viewRequestedAt').text(r.requested_at ? new Date(r.requested_at).toLocaleString() : '-');
                    $('#viewApprovedBy').text(r.approved_by_name || (r.status === 'pending' ? 'Pending review' : '-'));

                    // Show notes if available
                    if (r.notes) {
                        $('#viewNotesSection').show();
                        $('#viewNotes').text(r.notes);
                    } else {
                        $('#viewNotesSection').hide();
                    }

                    $('#viewDetailsModal').modal('show');
                } else {
                    Swal.fire('Error', response.message || 'Failed to load request details', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Server error occurred', 'error');
            }
        });
    });

    // Sync Time Off from TimeWorks
    $('#btnSyncTimeOff').on('click', function() {
        const $btn = $(this);
        const originalHtml = $btn.html();

        Swal.fire({
            title: 'Sync Time Off Requests',
            text: 'This will sync all leave/PTO requests from TimeWorks. Continue?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#17a2b8',
            confirmButtonText: 'Yes, sync now'
        }).then((result) => {
            if (result.isConfirmed) {
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Syncing...');

                $.ajax({
                    url: 'sync.php',
                    type: 'POST',
                    data: {
                        action: 'sync',
                        sync_type: 'timeoff'
                    },
                    dataType: 'json',
                    success: function(response) {
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
                                table.ajax.reload();
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', response.message || 'Sync failed', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire('Error', 'An error occurred during sync: ' + error, 'error');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                });
            }
        });
    });
});
</script>

<?php include '../../components/footer.php'; ?>
