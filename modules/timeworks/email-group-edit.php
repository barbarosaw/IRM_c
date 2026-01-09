<?php
/**
 * TimeWorks Module - Email Group Edit
 *
 * Edit group details and manage members
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

// Get group ID
$groupId = (int)($_GET['id'] ?? 0);

if (!$groupId) {
    header('Location: email-groups.php');
    exit;
}

// Get group data
$group = null;
$members = [];

try {
    $stmt = $db->prepare("
        SELECT g.*, u.name as created_by_name
        FROM email_groups g
        LEFT JOIN users u ON g.created_by = u.id
        WHERE g.id = ?
    ");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        header('Location: email-groups.php');
        exit;
    }

    // Get members
    $stmt = $db->prepare("
        SELECT m.*, u.full_name as user_name,
               CASE WHEN u.status = 'active' THEN 1 ELSE 0 END as user_active
        FROM email_group_members m
        LEFT JOIN twr_users u ON m.user_id = u.user_id
        WHERE m.group_id = ?
        ORDER BY m.name ASC, m.email ASC
    ");
    $stmt->execute([$groupId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error loading group: " . $e->getMessage());
    header('Location: email-groups.php');
    exit;
}

$page_title = "Edit Group: " . htmlspecialchars($group['name']);
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
                        <i class="fas fa-users-cog text-primary"></i> Edit Email Group
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?= $root_path ?>">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">TimeWorks</a></li>
                        <li class="breadcrumb-item"><a href="email-groups.php">Email Groups</a></li>
                        <li class="breadcrumb-item active">Edit</li>
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

            <div class="row">
                <!-- Group Info Card -->
                <div class="col-lg-4">
                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-info-circle"></i> Group Details</h3>
                        </div>
                        <form id="groupForm">
                            <input type="hidden" name="group_id" value="<?= $groupId ?>">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="groupName" class="form-label">Group Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="groupName" name="name" value="<?= htmlspecialchars($group['name']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="groupDescription" class="form-label">Description</label>
                                    <textarea class="form-control" id="groupDescription" name="description" rows="3"><?= htmlspecialchars($group['description'] ?? '') ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="groupActive" name="is_active" value="1" <?= $group['is_active'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="groupActive">Active</label>
                                    </div>
                                </div>

                                <div class="text-muted small">
                                    <p class="mb-1"><strong>Created:</strong> <?= date('M j, Y H:i', strtotime($group['created_at'])) ?></p>
                                    <p class="mb-1"><strong>By:</strong> <?= htmlspecialchars($group['created_by_name'] ?? 'System') ?></p>
                                    <p class="mb-0"><strong>Members:</strong> <span id="memberCount"><?= count($members) ?></span></p>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <a href="email-groups.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Quick Add Card -->
                    <div class="card card-success card-outline">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-user-plus"></i> Add Members</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <button type="button" class="btn btn-success btn-block w-100 mb-2" onclick="openAddUsersModal()">
                                    <i class="fas fa-users"></i> Add from TimeWorks Users
                                </button>
                            </div>

                            <div class="mb-3">
                                <button type="button" class="btn btn-info btn-block w-100 mb-2" onclick="openImportModal()">
                                    <i class="fas fa-file-import"></i> Import from Filter
                                </button>
                            </div>

                            <hr>
                            <h6>Manual Add</h6>
                            <form id="manualAddForm">
                                <div class="mb-2">
                                    <input type="email" class="form-control form-control-sm" id="manualEmail" placeholder="Email address" required>
                                </div>
                                <div class="mb-2">
                                    <input type="text" class="form-control form-control-sm" id="manualName" placeholder="Name (optional)">
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-success w-100">
                                    <i class="fas fa-plus"></i> Add Email
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Members Card -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-users"></i> Members (<span id="memberCountHeader"><?= count($members) ?></span>)</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-danger" onclick="removeSelectedMembers()" id="removeSelectedBtn" style="display: none;">
                                    <i class="fas fa-trash"></i> Remove Selected
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="membersTable" class="table table-bordered table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th style="width: 30px;">
                                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                                        </th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Source</th>
                                        <th style="width: 80px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="membersBody">
                                    <?php foreach ($members as $member): ?>
                                    <tr data-member-id="<?= $member['id'] ?>">
                                        <td>
                                            <input type="checkbox" class="member-checkbox" value="<?= $member['id'] ?>">
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($member['name'] ?? $member['user_name'] ?? '-') ?>
                                        </td>
                                        <td><?= htmlspecialchars($member['email']) ?></td>
                                        <td>
                                            <?php if ($member['user_id']): ?>
                                            <span class="badge bg-primary">TimeWorks User</span>
                                            <?php if (isset($member['user_active']) && $member['user_active'] == 0): ?>
                                            <span class="badge bg-warning">Inactive</span>
                                            <?php endif; ?>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">Manual</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-xs btn-danger" onclick="removeMember(<?= $member['id'] ?>)" title="Remove">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($members)): ?>
                                    <tr id="noMembersRow">
                                        <td colspan="5" class="text-center text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2"></i>
                                            <p>No members in this group yet.</p>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Users Modal -->
<div class="modal fade" id="addUsersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-users"></i> Add TimeWorks Users <span id="totalUsersCount" class="badge bg-light text-success ms-2"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 border-bottom">
                    <input type="text" class="form-control" id="userSearch" placeholder="Search users by name or email...">
                </div>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="sticky-top bg-light">
                            <tr>
                                <th style="width: 30px;">
                                    <input type="checkbox" id="selectAllUsers">
                                </th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="availableUsersBody">
                            <tr>
                                <td colspan="4" class="text-center py-4">
                                    <i class="fas fa-spinner fa-spin"></i> Loading users...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <span id="selectedUsersCount" class="me-auto text-muted">0 users selected</span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="addSelectedUsers()">
                    <i class="fas fa-plus"></i> Add Selected
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-file-import"></i> Import from Filter</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Import users based on a filter criteria:</p>
                <div class="mb-3">
                    <select class="form-control" id="importFilter">
                        <option value="all">All Users with Email</option>
                        <option value="active">Active Users Only</option>
                        <option value="inactive">Inactive Users Only</option>
                        <option value="without_activity">Users Without Activity</option>
                        <option value="with_activity">Users With Activity</option>
                    </select>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Users already in the group will be skipped.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" onclick="importFromFilter()">
                    <i class="fas fa-file-import"></i> Import
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var groupId = <?= $groupId ?>;
var membersTable = null;

$(document).ready(function() {
    // Initialize DataTable for members
    initMembersTable();

    function initMembersTable() {
        // Destroy existing DataTable if any
        if ($.fn.DataTable.isDataTable('#membersTable')) {
            $('#membersTable').DataTable().destroy();
        }

        // Only initialize if we have actual member rows (not just the "no members" row)
        var memberRows = $('#membersTable tbody tr[data-member-id]').length;
        if (memberRows > 0) {
            membersTable = $('#membersTable').DataTable({
                "order": [[1, "asc"]],
                "pageLength": 25,
                "columnDefs": [
                    { "orderable": false, "targets": [0, 4] }
                ]
            });
        }
    }

    // Group form submit
    $('#groupForm').on('submit', function(e) {
        e.preventDefault();
        saveGroup();
    });

    // Manual add form
    $('#manualAddForm').on('submit', function(e) {
        e.preventDefault();
        addManualMember();
    });

    // User search
    $('#userSearch').on('keyup', function() {
        filterUsers($(this).val());
    });

    // Select all users
    $('#selectAllUsers').on('change', function() {
        $('.user-checkbox:visible').prop('checked', $(this).is(':checked'));
        updateSelectedCount();
    });

    // User checkbox change
    $(document).on('change', '.user-checkbox', function() {
        updateSelectedCount();
    });

    // Member checkbox change
    $(document).on('change', '.member-checkbox', function() {
        var checked = $('.member-checkbox:checked').length;
        $('#removeSelectedBtn').toggle(checked > 0);
    });
});

function saveGroup() {
    var data = {
        action: 'update_group',
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
                showAlert('success', response.message);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to save group');
        }
    });
}

function openAddUsersModal() {
    $('#addUsersModal').modal('show');
    loadAvailableUsers();
}

function loadAvailableUsers() {
    $.ajax({
        url: 'api/email-groups.php',
        type: 'GET',
        data: { action: 'get_available_users', group_id: groupId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                renderAvailableUsers(response.users);
            }
        }
    });
}

function renderAvailableUsers(users) {
    var html = '';

    // Update total users count badge
    $('#totalUsersCount').text(users.length + ' users available');

    if (users.length === 0) {
        html = '<tr><td colspan="4" class="text-center text-muted">All users are already in this group</td></tr>';
    } else {
        users.forEach(function(user) {
            html += `
                <tr data-user-id="${user.id}">
                    <td><input type="checkbox" class="user-checkbox" value="${user.id}"></td>
                    <td>${escapeHtml(user.name || '-')}</td>
                    <td>${escapeHtml(user.email)}</td>
                    <td>${user.is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'}</td>
                </tr>
            `;
        });
    }

    $('#availableUsersBody').html(html);
    $('#selectAllUsers').prop('checked', false);
    updateSelectedCount();
}

function filterUsers(search) {
    search = search.toLowerCase();
    $('#availableUsersBody tr').each(function() {
        var name = $(this).find('td:eq(1)').text().toLowerCase();
        var email = $(this).find('td:eq(2)').text().toLowerCase();
        $(this).toggle(name.includes(search) || email.includes(search));
    });
}

function updateSelectedCount() {
    var count = $('.user-checkbox:checked').length;
    $('#selectedUsersCount').text(count + ' users selected');
}

function addSelectedUsers() {
    var userIds = [];
    $('.user-checkbox:checked').each(function() {
        userIds.push($(this).val());
    });

    if (userIds.length === 0) {
        Swal.fire('Warning', 'Please select at least one user', 'warning');
        return;
    }

    $.ajax({
        url: 'api/email-groups.php',
        type: 'POST',
        data: {
            action: 'add_members_bulk',
            group_id: groupId,
            user_ids: JSON.stringify(userIds)
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#addUsersModal').modal('hide');
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
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
            Swal.fire('Error', 'Failed to add users', 'error');
        }
    });
}

function openImportModal() {
    $('#importModal').modal('show');
}

function importFromFilter() {
    var filter = $('#importFilter').val();

    Swal.fire({
        title: 'Import Users',
        text: 'This will add all matching users to the group. Continue?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, import'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api/email-groups.php',
                type: 'POST',
                data: {
                    action: 'import_from_filter',
                    group_id: groupId,
                    filter: filter
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#importModal').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: 'Imported',
                            text: response.message,
                            timer: 2000
                        }).then(function() {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to import users', 'error');
                }
            });
        }
    });
}

function addManualMember() {
    var email = $('#manualEmail').val();
    var name = $('#manualName').val();

    $.ajax({
        url: 'api/email-groups.php',
        type: 'POST',
        data: {
            action: 'add_member',
            group_id: groupId,
            email: email,
            name: name
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#manualEmail').val('');
                $('#manualName').val('');
                showAlert('success', response.message);
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function() {
            showAlert('danger', 'Failed to add member');
        }
    });
}

function removeMember(memberId) {
    Swal.fire({
        title: 'Remove Member?',
        text: 'This member will be removed from the group.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, remove'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'api/email-groups.php',
                type: 'POST',
                data: { action: 'remove_member', member_id: memberId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $(`tr[data-member-id="${memberId}"]`).fadeOut(300, function() {
                            $(this).remove();
                            updateMemberCount();
                        });
                    } else {
                        Swal.fire('Error', response.message, 'error');
                    }
                }
            });
        }
    });
}

function toggleSelectAll() {
    var checked = $('#selectAll').is(':checked');
    $('.member-checkbox').prop('checked', checked);
    $('#removeSelectedBtn').toggle(checked && $('.member-checkbox').length > 0);
}

function removeSelectedMembers() {
    var memberIds = [];
    $('.member-checkbox:checked').each(function() {
        memberIds.push($(this).val());
    });

    if (memberIds.length === 0) return;

    Swal.fire({
        title: 'Remove Selected?',
        text: `Remove ${memberIds.length} members from the group?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, remove all'
    }).then((result) => {
        if (result.isConfirmed) {
            var promises = memberIds.map(function(memberId) {
                return $.ajax({
                    url: 'api/email-groups.php',
                    type: 'POST',
                    data: { action: 'remove_member', member_id: memberId }
                });
            });

            Promise.all(promises).then(function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Removed',
                    text: memberIds.length + ' members removed',
                    timer: 1500
                }).then(function() {
                    location.reload();
                });
            });
        }
    });
}

function updateMemberCount() {
    var count = $('#membersBody tr[data-member-id]').length;
    $('#memberCount').text(count);
    $('#memberCountHeader').text(count);
}

function showAlert(type, message) {
    var alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    $('#alertContainer').html(alertHtml);
    setTimeout(function() {
        $('#alertContainer .alert').alert('close');
    }, 3000);
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text || ''));
    return div.innerHTML;
}
</script>

<?php include '../../components/footer.php'; ?>
