<?php
/**
 * PH Communications Module - SMS Inbox (Received Messages)
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../../login.php');
    exit;
}

// Check permission
if (!has_permission('ph_communications-view-inbox') && !has_permission('ph_communications-view-all')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$canViewAll = has_permission('ph_communications-view-all');

$page_title = "SMS Inbox";
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
                        <i class="fas fa-inbox me-2"></i>SMS Inbox
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="../index.php">PH Communications</a></li>
                        <li class="breadcrumb-item active">Inbox</li>
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
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" id="search"
                                   placeholder="Search phone number or message...">
                        </div>
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

            <!-- Received Messages Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        Received Messages
                    </h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnRefresh">
                            <i class="fas fa-sync-alt me-1"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table id="inboxTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>From</th>
                                <th>To (Shortcode)</th>
                                <th>Message</th>
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
    // Initialize DataTable
    const table = $('#inboxTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '../api/m360-sms/get-messages.php',
            data: function(d) {
                d.direction = 'inbound';
                d.date_from = $('#dateFrom').val();
                d.date_to = $('#dateTo').val();
                d.search = $('#search').val();
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
            {
                data: 'from_number',
                render: function(data) {
                    return '+' + data;
                }
            },
            {
                data: 'to_number',
                render: function(data) {
                    return data || '-';
                }
            },
            {
                data: 'message',
                render: function(data) {
                    if (data.length > 80) {
                        return '<div>' + data.substring(0, 80) + '...</div>' +
                               '<a href="#" class="view-full small" data-message="' +
                               data.replace(/"/g, '&quot;') + '">View Full</a>';
                    }
                    return data;
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
            emptyTable: "No received messages found"
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

    // Refresh button
    $('#btnRefresh').on('click', function() {
        table.ajax.reload();
    });

    // View full message modal
    $(document).on('click', '.view-full', function(e) {
        e.preventDefault();
        const message = $(this).data('message');

        // Create modal
        const modalHtml = `
            <div class="modal fade" id="messageModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Full Message</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p style="white-space: pre-wrap;">${message}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remove old modal if exists
        $('#messageModal').remove();

        // Append and show new modal
        $('body').append(modalHtml);
        new bootstrap.Modal('#messageModal').show();
    });

    // Auto-refresh every 30 seconds
    setInterval(function() {
        table.ajax.reload(null, false); // false = keep current page
    }, 30000);
});
</script>

<?php include '../../../components/footer.php'; ?>
