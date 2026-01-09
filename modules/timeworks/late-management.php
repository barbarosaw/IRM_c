<?php
/**
 * TimeWorks Module - Late Records Management
 *
 * HR interface for reviewing and managing late attendance records.
 * Features:
 * - View all late records with filters
 * - Review evidence submissions
 * - Approve/reject late notices
 * - Add HR notes
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission
if (!has_permission('timeworks_late_manage')) {
    header('Location: ../../access-denied.php');
    exit;
}

$page_title = "TimeWorks - Late Management";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Set timezone
date_default_timezone_set('America/New_York');

// Get filter parameters
$filterStatus = $_GET['status'] ?? '';
$filterDate = $_GET['date'] ?? '';
$filterDateEnd = $_GET['date_end'] ?? '';
$filterUser = $_GET['user'] ?? '';

// Build query
$where = ["1=1"];
$params = [];

if ($filterStatus) {
    $where[] = "lr.status = ?";
    $params[] = $filterStatus;
}

if ($filterDate) {
    $where[] = "lr.shift_date >= ?";
    $params[] = $filterDate;
}

if ($filterDateEnd) {
    $where[] = "lr.shift_date <= ?";
    $params[] = $filterDateEnd;
}

if ($filterUser) {
    $where[] = "(u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%{$filterUser}%";
    $params[] = "%{$filterUser}%";
}

$whereClause = implode(" AND ", $where);

// Get late records
$sql = "
    SELECT
        lr.*,
        u.full_name,
        u.email,
        reviewer.name as reviewer_name
    FROM twr_late_records lr
    LEFT JOIN twr_users u ON lr.user_id = u.user_id
    LEFT JOIN users reviewer ON lr.reviewed_by = reviewer.id
    WHERE {$whereClause}
    ORDER BY lr.shift_date DESC, lr.created_at DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$lateRecords = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => 0,
    'pending' => 0,
    'late_without_notice' => 0,
    'late_with_notice' => 0,
    'excused' => 0,
    'rejected' => 0
];

$stmt = $db->query("
    SELECT status, COUNT(*) as count
    FROM twr_late_records
    GROUP BY status
");
while ($row = $stmt->fetch()) {
    $stats[$row['status']] = $row['count'];
    $stats['total'] += $row['count'];
}

// Page title
$page_title = "TimeWorks - Late Records Management";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0"><i class="fas fa-clock text-warning"></i> Late Records Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Late Records</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3><?php echo $stats['total']; ?></h3>
                            <p>Total Records</p>
                        </div>
                        <div class="icon"><i class="fas fa-list"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <h3><?php echo $stats['pending']; ?></h3>
                            <p>Pending Review</p>
                        </div>
                        <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3><?php echo $stats['late_without_notice']; ?></h3>
                            <p>Without Notice</p>
                        </div>
                        <div class="icon"><i class="fas fa-times-circle"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3><?php echo $stats['late_with_notice']; ?></h3>
                            <p>With Notice</p>
                        </div>
                        <div class="icon"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-primary">
                        <div class="inner">
                            <h3><?php echo $stats['excused']; ?></h3>
                            <p>Excused</p>
                        </div>
                        <div class="icon"><i class="fas fa-user-check"></i></div>
                    </div>
                </div>
                <div class="col-lg-2 col-6">
                    <div class="small-box bg-secondary">
                        <div class="inner">
                            <h3><?php echo $stats['rejected']; ?></h3>
                            <p>Rejected</p>
                        </div>
                        <div class="icon"><i class="fas fa-ban"></i></div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter"></i> Filters</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="late_without_notice" <?php echo $filterStatus === 'late_without_notice' ? 'selected' : ''; ?>>Without Notice</option>
                                <option value="late_with_notice" <?php echo $filterStatus === 'late_with_notice' ? 'selected' : ''; ?>>With Notice</option>
                                <option value="excused" <?php echo $filterStatus === 'excused' ? 'selected' : ''; ?>>Excused</option>
                                <option value="rejected" <?php echo $filterStatus === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filterDate); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_end" class="form-control" value="<?php echo htmlspecialchars($filterDateEnd); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">User</label>
                            <input type="text" name="user" class="form-control" placeholder="Name or email" value="<?php echo htmlspecialchars($filterUser); ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                            <a href="late-management.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Late Records Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-table"></i> Late Records</h3>
                    <div class="card-tools">
                        <a href="index.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="lateRecordsTable" class="table table-bordered table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Scheduled</th>
                                    <th>Actual</th>
                                    <th>Late By</th>
                                    <th>Status</th>
                                    <th>Evidence</th>
                                    <th>Reviewed By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lateRecords as $record): ?>
                                    <?php
                                    $statusBadge = match($record['status']) {
                                        'pending' => '<span class="badge bg-warning">Pending</span>',
                                        'late_without_notice' => '<span class="badge bg-danger">Without Notice</span>',
                                        'late_with_notice' => '<span class="badge bg-success">With Notice</span>',
                                        'excused' => '<span class="badge bg-primary">Excused</span>',
                                        'rejected' => '<span class="badge bg-secondary">Rejected</span>',
                                        'absent' => '<span class="badge bg-dark">Absent</span>',
                                        default => '<span class="badge bg-secondary">' . ucfirst($record['status']) . '</span>'
                                    };

                                    $hasEvidence = !empty($record['evidence_file']) || !empty($record['evidence_notes']);
                                    $evidenceExpired = $record['evidence_deadline'] && strtotime($record['evidence_deadline']) < time();
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('M j, Y', strtotime($record['shift_date'])); ?></strong>
                                            <br><small class="text-muted"><?php echo date('l', strtotime($record['shift_date'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($record['full_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($record['email']); ?></small>
                                        </td>
                                        <td><?php echo date('h:i A', strtotime($record['scheduled_start'])); ?></td>
                                        <td>
                                            <?php if ($record['actual_start']): ?>
                                                <?php echo date('h:i A', strtotime($record['actual_start'])); ?>
                                            <?php else: ?>
                                                <span class="text-danger">No clock-in</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong class="text-danger"><?php echo $record['late_minutes']; ?> min</strong>
                                        </td>
                                        <td><?php echo $statusBadge; ?></td>
                                        <td>
                                            <?php if ($hasEvidence): ?>
                                                <button type="button" class="btn btn-sm btn-info view-evidence-btn"
                                                        data-id="<?php echo $record['id']; ?>"
                                                        data-file="<?php echo htmlspecialchars($record['evidence_file'] ?? ''); ?>"
                                                        data-notes="<?php echo htmlspecialchars($record['evidence_notes'] ?? ''); ?>"
                                                        data-notice="<?php echo htmlspecialchars($record['notice_type']); ?>">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            <?php elseif ($evidenceExpired): ?>
                                                <span class="badge bg-secondary">Expired</span>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($record['reviewed_by']): ?>
                                                <?php echo htmlspecialchars($record['reviewer_name']); ?>
                                                <br><small class="text-muted"><?php echo date('M j, g:i A', strtotime($record['reviewed_at'])); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-primary review-btn"
                                                        data-id="<?php echo $record['id']; ?>"
                                                        data-user="<?php echo htmlspecialchars($record['full_name']); ?>"
                                                        data-date="<?php echo $record['shift_date']; ?>"
                                                        data-status="<?php echo $record['status']; ?>"
                                                        data-notes="<?php echo htmlspecialchars($record['hr_notes'] ?? ''); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($record['status'] === 'pending' && $hasEvidence): ?>
                                                    <button type="button" class="btn btn-sm btn-success quick-approve-btn"
                                                            data-id="<?php echo $record['id']; ?>" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger quick-reject-btn"
                                                            data-id="<?php echo $record['id']; ?>" title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Review Late Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reviewRecordId">
                <div class="mb-3">
                    <strong>User:</strong> <span id="reviewUser"></span><br>
                    <strong>Date:</strong> <span id="reviewDate"></span>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select id="reviewStatus" class="form-select">
                        <option value="pending">Pending</option>
                        <option value="late_without_notice">Late Without Notice</option>
                        <option value="late_with_notice">Late With Notice</option>
                        <option value="excused">Excused</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">HR Notes</label>
                    <textarea id="reviewNotes" class="form-control" rows="3" placeholder="Add notes about this decision..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveReviewBtn">
                    <i class="fas fa-save"></i> Save Review
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Evidence Modal -->
<div class="modal fade" id="evidenceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-file-alt"></i> Evidence Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Notice Type</h6>
                        <p id="evidenceNoticeType"></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Uploaded File</h6>
                        <div id="evidenceFileContainer"></div>
                    </div>
                </div>
                <div class="mt-3">
                    <h6>User's Explanation</h6>
                    <div id="evidenceNotes" class="border rounded p-3 bg-light"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#lateRecordsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 25,
        responsive: true,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search..."
        }
    });

    // Review button click
    $('.review-btn').on('click', function() {
        var id = $(this).data('id');
        var user = $(this).data('user');
        var date = $(this).data('date');
        var status = $(this).data('status');
        var notes = $(this).data('notes');

        $('#reviewRecordId').val(id);
        $('#reviewUser').text(user);
        $('#reviewDate').text(date);
        $('#reviewStatus').val(status);
        $('#reviewNotes').val(notes);

        new bootstrap.Modal(document.getElementById('reviewModal')).show();
    });

    // Save review
    $('#saveReviewBtn').on('click', function() {
        var id = $('#reviewRecordId').val();
        var status = $('#reviewStatus').val();
        var notes = $('#reviewNotes').val();

        $.ajax({
            url: 'api/late-records.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'update_status',
                id: id,
                status: status,
                hr_notes: notes
            }),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated',
                        text: 'Late record has been updated.',
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
                Swal.fire('Error', 'Failed to update record', 'error');
            }
        });
    });

    // Quick approve
    $('.quick-approve-btn').on('click', function() {
        var id = $(this).data('id');

        Swal.fire({
            title: 'Approve Late Notice?',
            text: 'This will mark the record as "Late With Notice"',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Yes, Approve'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/late-records.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'update_status',
                        id: id,
                        status: 'late_with_notice'
                    }),
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Quick reject
    $('.quick-reject-btn').on('click', function() {
        var id = $(this).data('id');

        Swal.fire({
            title: 'Reject Evidence?',
            text: 'This will mark the record as "Late Without Notice"',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, Reject'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/late-records.php',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'update_status',
                        id: id,
                        status: 'late_without_notice'
                    }),
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // View evidence
    $('.view-evidence-btn').on('click', function() {
        var file = $(this).data('file');
        var notes = $(this).data('notes');
        var notice = $(this).data('notice');

        var noticeLabels = {
            'none': 'None',
            'email': 'Email',
            'chat': 'Chat/Slack',
            'sms': 'SMS',
            'phone': 'Phone Call',
            'prior_approval': 'Prior Approval',
            'system': 'System'
        };

        $('#evidenceNoticeType').text(noticeLabels[notice] || notice);
        $('#evidenceNotes').text(notes || 'No explanation provided.');

        if (file) {
            var fileUrl = '/assets/uploads/late_evidence/' + file;
            var ext = file.split('.').pop().toLowerCase();

            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                $('#evidenceFileContainer').html('<a href="' + fileUrl + '" target="_blank"><img src="' + fileUrl + '" class="img-fluid" style="max-height: 300px;"></a>');
            } else if (ext === 'pdf') {
                $('#evidenceFileContainer').html('<a href="' + fileUrl + '" target="_blank" class="btn btn-outline-primary"><i class="fas fa-file-pdf"></i> View PDF</a>');
            } else {
                $('#evidenceFileContainer').html('<a href="' + fileUrl + '" target="_blank" class="btn btn-outline-primary"><i class="fas fa-download"></i> Download File</a>');
            }
        } else {
            $('#evidenceFileContainer').html('<span class="text-muted">No file uploaded</span>');
        }

        new bootstrap.Modal(document.getElementById('evidenceModal')).show();
    });
});
</script>

<?php include '../../components/footer.php'; ?>
