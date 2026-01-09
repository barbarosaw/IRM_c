<?php
/**
 * PH Communications Module - SMS Outbox (Sent Messages)
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../login.php');
    exit;
}

// Check permission
if (!has_permission('ph_communications-view-outbox') && !has_permission('ph_communications-view-all')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$canViewAll = has_permission('ph_communications-view-all');

$page_title = "SMS Outbox";
$root_path = "../../../";

include '../../../components/header.php';
include '../../../components/sidebar.php';
?>

<link rel="stylesheet" href="../assets/css/ph-communications.css">

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-paper-plane me-2"></i>SMS Outbox
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="../index.php">PH Communications</a></li>
                        <li class="breadcrumb-item active">Outbox</li>
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
                                <option value="pending">Pending</option>
                                <option value="sent">Sent</option>
                                <option value="acknowledged">Acknowledged</option>
                                <option value="delivered">Delivered</option>
                                <option value="failed">Failed</option>
                                <option value="rejected">Rejected</option>
                                <option value="expired">Expired</option>
                                <option value="undelivered">Undelivered</option>
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

            <!-- Sent Messages Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Sent Messages
                    </h3>
                    <div class="card-tools">
                        <a href="compose.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-1"></i> Send New SMS
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <table id="outboxTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <?php if ($canViewAll): ?>
                                <th>User</th>
                                <?php endif; ?>
                                <th>To</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Telco</th>
                                <th>Parts</th>
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
    const canViewAll = <?php echo $canViewAll ? 'true' : 'false'; ?>;

    // Initialize DataTable
    const table = $('#outboxTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '../api/m360-sms/get-messages.php',
            data: function(d) {
                d.direction = 'outbound';
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
                data: 'to_number',
                render: function(data) {
                    return '+' + data;
                }
            },
            {
                data: 'message',
                render: function(data) {
                    if (data.length > 50) {
                        return data.substring(0, 50) + '... <a href="#" class="view-full" data-message="' +
                               data.replace(/"/g, '&quot;') + '">View Full</a>';
                    }
                    return data;
                }
            },
            {
                data: 'status',
                render: function(data) {
                    const badges = {
                        'pending': 'secondary',
                        'sent': 'info',
                        'acknowledged': 'success',
                        'delivered': 'success',
                        'failed': 'danger',
                        'rejected': 'danger',
                        'expired': 'warning',
                        'undelivered': 'danger'
                    };
                    const badge = badges[data] || 'secondary';
                    return '<span class="badge bg-' + badge + '">' +
                           data.charAt(0).toUpperCase() + data.slice(1) + '</span>';
                }
            },
            {
                data: 'telco_id',
                render: function(data) {
                    const telcos = {1: 'Globe', 2: 'Smart', 3: 'Sun', 4: 'DITO'};
                    return telcos[data] || '-';
                }
            },
            {
                data: 'msgcount',
                render: function(data) {
                    return data || 1;
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: "No sent messages found"
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

    // View full message
    $(document).on('click', '.view-full', function(e) {
        e.preventDefault();
        const message = $(this).data('message');
        alert(message);
    });
});
</script>

<?php include '../../../components/footer.php'; ?>
