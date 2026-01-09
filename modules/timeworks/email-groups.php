<?php
/**
 * TimeWorks Module - Email Groups Management
 *
 * Manage email recipient groups for bulk email campaigns
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission
if (!has_permission('timeworks_email_manage')) {
    header('Location: ../../access-denied.php');
    exit;
}

// Check if module is active
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['timeworks']);
$is_active = $stmt->fetchColumn();

if (!$is_active) {
    header('Location: ../../module-inactive.php');
    exit;
}

// Get groups
$groups = [];
try {
    $stmt = $db->query("
        SELECT g.*,
               u.name as created_by_name,
               (SELECT COUNT(*) FROM email_group_members WHERE group_id = g.id) as member_count
        FROM email_groups g
        LEFT JOIN users u ON g.created_by = u.id
        ORDER BY g.name ASC
    ");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error loading groups: " . $e->getMessage());
}

$page_title = "TimeWorks - Email Groups";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<!-- Content Wrapper -->
<div class="content-wrapper">
    <!-- Content Header -->
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-users-cog text-primary"></i> Email Groups
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Email Groups</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="content">
        <div class="container-fluid">
            <!-- Alert Container -->
            <div id="alertContainer"></div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> Recipient Groups</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#groupModal" onclick="openCreateModal()">
                            <i class="fas fa-plus"></i> New Group
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table id="groupsTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Members</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groups as $group): ?>
                            <tr data-group-id="<?= $group['id'] ?>">
                                <td>
                                    <strong><?= htmlspecialchars($group['name']) ?></strong>
                                </td>
                                <td>
                                    <?= htmlspecialchars(substr($group['description'] ?? '', 0, 50)) ?>
                                    <?= strlen($group['description'] ?? '') > 50 ? '...' : '' ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= $group['member_count'] ?></span> members
                                </td>
                                <td>
                                    <?php if ($group['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= date('M j, Y', strtotime($group['created_at'])) ?>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars($group['created_by_name'] ?? 'System') ?></small>
                                </td>
                                <td>
                                    <a href="email-group-edit.php?id=<?= $group['id'] ?>" class="btn btn-sm btn-primary" title="Edit & Manage Members">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteGroup(<?= $group['id'] ?>, '<?= htmlspecialchars($group['name'], ENT_QUOTES) ?>')" title="Delete Group">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($groups)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p>No email groups yet. Create your first group!</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Info Card -->
            <div class="card card-info card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle"></i> How to Use Email Groups</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h5><i class="fas fa-plus-circle text-success"></i> Create Group</h5>
                            <p>Create a new recipient group with a name and optional description.</p>
                        </div>
                        <div class="col-md-4">
                            <h5><i class="fas fa-users text-primary"></i> Add Members</h5>
                            <p>Add members by selecting TimeWorks users, importing from filters, or manually adding email addresses.</p>
                        </div>
                        <div class="col-md-4">
                            <h5><i class="fas fa-mail-bulk text-warning"></i> Send Emails</h5>
                            <p>Go to <a href="bulk-email.php">Bulk Email</a> and select your group from the recipients dropdown.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Group Modal -->
<div class="modal fade" id="groupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="groupModalTitle"><i class="fas fa-plus"></i> New Email Group</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="groupForm">
                <div class="modal-body">
                    <input type="hidden" id="groupId" name="group_id" value="">

                    <div class="mb-3">
                        <label for="groupName" class="form-label">Group Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="groupName" name="name" required placeholder="e.g., Marketing Team, All Managers">
                    </div>

                    <div class="mb-3">
                        <label for="groupDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="groupDescription" name="description" rows="3" placeholder="Optional description for this group"></textarea>
                    </div>

                    <div class="mb-3" id="statusField" style="display: none;">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="groupActive" name="is_active" value="1" checked>
                            <label class="form-check-label" for="groupActive">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="groupSubmitBtn">
                        <i class="fas fa-save"></i> Create Group
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable if there are records
    <?php if (!empty($groups)): ?>
    $('#groupsTable').DataTable({
        "order": [[0, "asc"]],
        "pageLength": 25
    });
    <?php endif; ?>

    // Form submit
    $('#groupForm').on('submit', function(e) {
        e.preventDefault();
        saveGroup();
    });
});

function openCreateModal() {
    $('#groupId').val('');
    $('#groupName').val('');
    $('#groupDescription').val('');
    $('#groupActive').prop('checked', true);
    $('#statusField').hide();
    $('#groupModalTitle').html('<i class="fas fa-plus"></i> New Email Group');
    $('#groupSubmitBtn').html('<i class="fas fa-save"></i> Create Group');
}

function saveGroup() {
    var groupId = $('#groupId').val();
    var action = groupId ? 'update_group' : 'create_group';

    var data = {
        action: action,
        group_id: groupId,
        name: $('#groupName').val(),
        description: $('#groupDescription').val(),
        is_active: $('#groupActive').is(':checked') ? 1 : 0
    };

    $.ajax({
        url: 'api/email-groups.php',
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#groupModal').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: response.message,
                    timer: 1500
                }).then(function() {
                    if (response.group_id) {
                        // Redirect to edit page to add members
                        window.location.href = 'email-group-edit.php?id=' + response.group_id;
                    } else {
                        location.reload();
                    }
                });
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to save group', 'error');
        }
    });
}

function deleteGroup(groupId, groupName) {
    Swal.fire({
        title: 'Delete Group?',
        html: `Are you sure you want to delete <strong>${groupName}</strong>?<br>All members will be removed.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api/email-groups.php',
                type: 'POST',
                data: { action: 'delete_group', group_id: groupId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted',
                            text: response.message,
                            timer: 1500
                        }).then(function() {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to delete group', 'error');
                }
            });
        }
    });
}
</script>

<?php include '../../components/footer.php'; ?>
