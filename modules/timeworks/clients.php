<?php
/**
 * TimeWorks Module - Client Management
 *
 * Sync and manage clients from TimeWorks API.
 * Assign users to clients for reporting.
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission
if (!has_permission('timeworks_users_manage')) {
    header('Location: ../../access-denied.php');
    exit;
}

$page_title = "TimeWorks - Client Management";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Get clients
$stmt = $db->query("SELECT * FROM twr_clients WHERE status = 'active' ORDER BY name");
$clients = $stmt->fetchAll();

// Get users for assignment
$stmt = $db->query("SELECT user_id, full_name FROM twr_users WHERE status = 'active' ORDER BY full_name");
$users = $stmt->fetchAll();

// Get last sync time
$stmt = $db->query("SELECT MAX(synced_at) as last_sync FROM twr_clients");
$lastSync = $stmt->fetchColumn();

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-building mr-2"></i><?= $page_title ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/modules/timeworks/">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Clients</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Sync Card -->
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-sync mr-2"></i>Sync Clients from TimeWorks</h3>
                    <div class="card-tools">
                        <span class="text-muted mr-3">
                            Last sync: <?= $lastSync ? date('M j, Y g:i A', strtotime($lastSync)) : 'Never' ?>
                        </span>
                        <button type="button" id="btnSync" class="btn btn-primary btn-sm">
                            <i class="fas fa-sync mr-1"></i> Sync Now
                        </button>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Client List -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-list mr-2"></i>Clients (<?= count($clients) ?>)</h3>
                        </div>
                        <div class="card-body p-0">
                            <table id="clientsTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Assigned Users</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $client): ?>
                                    <?php
                                        $stmt = $db->prepare("SELECT COUNT(*) FROM twr_user_clients WHERE client_id = ?");
                                        $stmt->execute([$client['client_id']]);
                                        $userCount = $stmt->fetchColumn();
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($client['name']) ?></strong>
                                            <?php if (!empty($client['email'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($client['email']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $userCount ?> users</span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary btn-assign" data-client-id="<?= htmlspecialchars($client['client_id']) ?>" data-client-name="<?= htmlspecialchars($client['name']) ?>">
                                                <i class="fas fa-user-plus"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info btn-view" data-client-id="<?= htmlspecialchars($client['client_id']) ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- User-Client Assignments -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-user-tag mr-2"></i>User-Client Assignments</h3>
                            <div class="card-tools">
                                <div class="input-group input-group-sm" style="width: 200px;">
                                    <input type="text" id="searchAssignments" class="form-control" placeholder="Search...">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-default">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="assignmentsTable" class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Client</th>
                                        <th>Type</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Assign Users Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Assign Users to <span id="assignClientName"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignForm">
                <input type="hidden" name="client_id" id="assignClientId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Users</label>
                        <select name="user_ids[]" id="assignUsers" class="form-select select2" multiple required>
                            <?php foreach ($users as $user): ?>
                            <option value="<?= htmlspecialchars($user['user_id']) ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assignment Type</label>
                        <select name="assignment_type" id="assignType" class="form-select">
                            <option value="direct">Direct Assignment</option>
                            <option value="via_project">Via Project</option>
                            <option value="temporary">Temporary</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="assignNotes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Users</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Client Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-building mr-2"></i>Client Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="clientDetails"></div>
                <hr>
                <h6>Assigned Users</h6>
                <table id="clientUsersTable" class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Type</th>
                            <th>Assigned</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('#assignUsers').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#assignModal')
    });

    // Load assignments
    loadAssignments();

    // Sync button
    $('#btnSync').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Syncing...');

        $.ajax({
            url: 'api/sync-clients.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'sync' }),
            success: function(response) {
                btn.prop('disabled', false).html('<i class="fas fa-sync mr-1"></i> Sync Now');

                if (response.success) {
                    Swal.fire('Success', `Synced ${response.synced} clients`, 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message || 'Sync failed', 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="fas fa-sync mr-1"></i> Sync Now');
                Swal.fire('Error', 'Server error occurred', 'error');
            }
        });
    });

    // Assign button
    $('.btn-assign').on('click', function() {
        $('#assignClientId').val($(this).data('client-id'));
        $('#assignClientName').text($(this).data('client-name'));
        $('#assignUsers').val([]).trigger('change');
        $('#assignNotes').val('');
        $('#assignModal').modal('show');
    });

    // Assign form
    $('#assignForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: 'api/sync-clients.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'assign',
                client_id: $('#assignClientId').val(),
                user_ids: $('#assignUsers').val(),
                assignment_type: $('#assignType').val(),
                notes: $('#assignNotes').val()
            }),
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success', 'Users assigned successfully', 'success');
                    $('#assignModal').modal('hide');
                    loadAssignments();
                    location.reload();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }
        });
    });

    // View button
    $('.btn-view').on('click', function() {
        const clientId = $(this).data('client-id');

        $.ajax({
            url: 'api/sync-clients.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'get_client', client_id: clientId }),
            success: function(response) {
                if (response.success) {
                    const client = response.client;
                    let html = `
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> ${client.name}</p>
                                <p><strong>Email:</strong> ${client.email || '-'}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Phone:</strong> ${client.phone || '-'}</p>
                                <p><strong>Status:</strong> <span class="badge bg-${client.status === 'active' ? 'success' : 'secondary'}">${client.status}</span></p>
                            </div>
                        </div>
                    `;
                    $('#clientDetails').html(html);

                    // Load assigned users
                    const tbody = $('#clientUsersTable tbody');
                    tbody.empty();
                    (response.users || []).forEach(function(user) {
                        tbody.append(`
                            <tr>
                                <td>${user.full_name}</td>
                                <td><span class="badge bg-info">${user.assignment_type}</span></td>
                                <td>${user.assigned_at ? new Date(user.assigned_at).toLocaleDateString() : '-'}</td>
                                <td>
                                    <button class="btn btn-xs btn-danger btn-remove-assignment" data-id="${user.id}">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });

                    $('#viewModal').modal('show');
                }
            }
        });
    });

    // Remove assignment
    $(document).on('click', '.btn-remove-assignment', function() {
        const id = $(this).data('id');

        Swal.fire({
            title: 'Remove Assignment?',
            text: 'This will remove the user from this client.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/sync-clients.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ action: 'remove_assignment', id: id }),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Removed', 'Assignment removed', 'success');
                            $('#viewModal').modal('hide');
                            location.reload();
                        }
                    }
                });
            }
        });
    });

    function loadAssignments() {
        $.ajax({
            url: 'api/sync-clients.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'list_assignments' }),
            success: function(response) {
                if (response.success) {
                    const tbody = $('#assignmentsTable tbody');
                    tbody.empty();

                    (response.data || []).forEach(function(row) {
                        tbody.append(`
                            <tr>
                                <td>${row.full_name}</td>
                                <td>${row.client_name}</td>
                                <td><span class="badge bg-secondary">${row.assignment_type}</span></td>
                                <td>
                                    <button class="btn btn-xs btn-danger btn-remove" data-id="${row.id}">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });
                }
            }
        });
    }

    // Search assignments
    $('#searchAssignments').on('keyup', function() {
        const val = $(this).val().toLowerCase();
        $('#assignmentsTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(val) > -1);
        });
    });

    // Remove from list
    $(document).on('click', '.btn-remove', function() {
        const id = $(this).data('id');

        $.ajax({
            url: 'api/sync-clients.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'remove_assignment', id: id }),
            success: function(response) {
                if (response.success) {
                    loadAssignments();
                    location.reload();
                }
            }
        });
    });
});
</script>

<?php include '../../components/footer.php'; ?>
