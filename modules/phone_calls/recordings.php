<?php
/**
 * Phone Calls Module - Recordings Page
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../includes/init.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission
if (!has_permission('phone_calls-recordings')) {
    header('Location: ../../access-denied.php');
    exit;
}

$page_title = "Call Recordings";
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
                        <i class="fas fa-microphone me-2"></i>Call Recordings
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Phone Calls</a></li>
                        <li class="breadcrumb-item active">Recordings</li>
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
                        <div class="col-md-3">
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
                        <div class="col-md-3 d-flex align-items-end">
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

            <!-- Recordings Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">All Recordings</h3>
                </div>
                <div class="card-body">
                    <table id="recordingsTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>User</th>
                                <th>Number</th>
                                <th>Duration</th>
                                <th>Recording</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    const table = $('#recordingsTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: 'api/get-recordings.php',
            data: function(d) {
                d.date_from = $('#dateFrom').val();
                d.date_to = $('#dateTo').val();
                d.user_id = $('#filterUser').val();
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
            { data: 'user_name' },
            { data: 'to_number' },
            {
                data: 'recording_duration',
                render: function(data) {
                    if (!data) return '0:00';
                    const min = Math.floor(data / 60);
                    const sec = data % 60;
                    return min + ':' + String(sec).padStart(2, '0');
                }
            },
            {
                data: null,
                render: function(data, type, row) {
                    if (!row.recording_url) return '<span class="text-muted">Not available</span>';
                    // Use proxy endpoint instead of direct Twilio URL
                    const proxyUrl = 'api/stream-recording.php?id=' + row.id;
                    return '<audio controls class="w-100" style="height:30px;"><source src="' + proxyUrl + '" type="audio/mpeg"></audio>';
                }
            },
            {
                data: null,
                render: function(data, type, row) {
                    if (!row.recording_url) return '-';
                    // Use proxy endpoint for download
                    const proxyUrl = 'api/stream-recording.php?id=' + row.id;
                    return '<a href="' + proxyUrl + '" class="btn btn-sm btn-outline-primary" download="recording_' + row.id + '.mp3"><i class="fas fa-download"></i></a>';
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: "No recordings found"
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
});
</script>

<?php include '../../components/footer.php'; ?>
