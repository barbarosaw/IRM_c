<?php
/**
 * Phone Calls Module - Call History Page
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../includes/init.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission
if (!has_permission('phone_calls-history') && !has_permission('phone_calls-view-all')) {
    header('Location: ../../access-denied.php');
    exit;
}

$canViewAll = has_permission('phone_calls-view-all');

$page_title = "Call History";
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
                        <i class="fas fa-history me-2"></i>Call History
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Phone Calls</a></li>
                        <li class="breadcrumb-item active">History</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Date From</label>
                            <input type="date" class="form-control" name="date_from" id="dateFrom">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date To</label>
                            <input type="date" class="form-control" name="date_to" id="dateTo">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="filterStatus">
                                <option value="">All</option>
                                <option value="completed">Completed</option>
                                <option value="busy">Busy</option>
                                <option value="no-answer">No Answer</option>
                                <option value="failed">Failed</option>
                                <option value="canceled">Canceled</option>
                            </select>
                        </div>
                        <?php if ($canViewAll): ?>
                        <div class="col-md-2">
                            <label class="form-label">User</label>
                            <select class="form-select" name="user_id" id="filterUser">
                                <option value="">All Users</option>
                                <?php
                                $users = $db->query("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($users as $user) {
                                    echo '<option value="' . $user['id'] . '">' . htmlspecialchars($user['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="btnClear">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Call History Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <?php echo $canViewAll ? 'All Calls' : 'Your Calls'; ?>
                    </h3>
                    <div class="card-tools">
                        <button class="btn btn-sm btn-outline-success" id="btnExport">
                            <i class="fas fa-download me-1"></i> Export CSV
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table id="historyTable" class="table table-bordered table-striped call-history-table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <?php if ($canViewAll): ?>
                                <th>User</th>
                                <?php endif; ?>
                                <th>Direction</th>
                                <th>Number</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Cost</th>
                                <th>Recording</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Audio Player Modal -->
<div class="modal fade" id="audioModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Call Recording</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="audio-player">
                    <audio id="audioPlayer" controls class="w-100"></audio>
                </div>
                <div class="mt-3 text-center">
                    <a id="audioDownload" href="#" class="btn btn-outline-primary" download>
                        <i class="fas fa-download me-1"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const canViewAll = <?php echo $canViewAll ? 'true' : 'false'; ?>;

    // Initialize DataTable
    const table = $('#historyTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: 'api/get-calls.php',
            data: function(d) {
                d.date_from = $('#dateFrom').val();
                d.date_to = $('#dateTo').val();
                d.status = $('#filterStatus').val();
                <?php if ($canViewAll): ?>
                d.user_id = $('#filterUser').val();
                <?php endif; ?>
            },
            dataSrc: 'data'
        },
        columns: [
            {
                data: 'created_at',
                render: function(data) {
                    return new Date(data).toLocaleString();
                }
            },
            <?php if ($canViewAll): ?>
            { data: 'user_name' },
            <?php endif; ?>
            {
                data: 'direction',
                render: function(data) {
                    const icon = data === 'outbound' ? 'fa-phone-alt text-success' : 'fa-phone-volume text-primary';
                    return '<i class="fas ' + icon + ' me-1"></i>' + data.charAt(0).toUpperCase() + data.slice(1);
                }
            },
            {
                data: null,
                render: function(data) {
                    return data.direction === 'outbound' ? data.to_number : data.from_number;
                }
            },
            {
                data: 'duration',
                render: function(data) {
                    if (!data) return '0:00';
                    const min = Math.floor(data / 60);
                    const sec = data % 60;
                    return min + ':' + String(sec).padStart(2, '0');
                }
            },
            {
                data: 'status',
                render: function(data) {
                    const badges = {
                        'completed': 'success',
                        'busy': 'warning',
                        'no-answer': 'warning',
                        'failed': 'danger',
                        'canceled': 'secondary',
                        'in-progress': 'primary',
                        'ringing': 'info',
                        'initiated': 'secondary'
                    };
                    const badge = badges[data] || 'secondary';
                    return '<span class="badge bg-' + badge + '">' + data.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</span>';
                }
            },
            {
                data: 'cost',
                render: function(data) {
                    return '$' + parseFloat(data || 0).toFixed(4);
                }
            },
            {
                data: null,
                render: function(data, type, row) {
                    if (!row.recording_url) return '<span class="text-muted">-</span>';
                    // Use proxy endpoint instead of direct Twilio URL
                    return '<button class="btn btn-sm btn-outline-info recording-btn" data-id="' + row.id + '"><i class="fas fa-play"></i></button>';
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: "No calls found"
        }
    });

    // Filter form
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        table.ajax.reload();
    });

    // Clear filters
    $('#btnClear').on('click', function() {
        $('#filterForm')[0].reset();
        table.ajax.reload();
    });

    // Play recording
    $(document).on('click', '.recording-btn', function() {
        const callId = $(this).data('id');
        const proxyUrl = 'api/stream-recording.php?id=' + callId;
        $('#audioPlayer').attr('src', proxyUrl);
        $('#audioDownload').attr('href', proxyUrl).attr('download', 'recording_' + callId + '.mp3');
        new bootstrap.Modal('#audioModal').show();
    });

    // Stop audio on modal close
    $('#audioModal').on('hidden.bs.modal', function() {
        $('#audioPlayer')[0].pause();
    });

    // Export CSV
    $('#btnExport').on('click', function() {
        let params = new URLSearchParams({
            date_from: $('#dateFrom').val(),
            date_to: $('#dateTo').val(),
            status: $('#filterStatus').val(),
            format: 'csv'
        });
        <?php if ($canViewAll): ?>
        params.append('user_id', $('#filterUser').val());
        <?php endif; ?>
        window.location.href = 'api/get-calls.php?' + params.toString();
    });
});
</script>

<?php include '../../components/footer.php'; ?>
