<?php
/**
 * Inventory Module - Assignments List
 * 
 * @author System Generated
 */


// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory view permission
if (!has_permission('view_inventory')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->
prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$page_title = "Inventory Assignments";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/Assignment.php';
require_once $root_dir . '/modules/inventory/models/InventoryItem.php';
require_once $root_dir . '/modules/inventory/models/Team.php';

// Initialize models
$assignmentModel = new InventoryAssignment($db);
$inventoryModel = new InventoryItem($db);
$teamModel = new InventoryTeam($db);

// Get filter parameters
$item_id = (int)($_GET['item_id'] ?? 0);
$team_id = (int)($_GET['team_id'] ?? 0);
$user_id = (int)($_GET['user_id'] ?? 0);
$status = $_GET['status'] ?? '';
$assignee_type = $_GET['assignee_type'] ?? '';

// Get data
$assignments = $assignmentModel->getAll();

// Apply filters
if ($item_id) {
    $assignments = array_filter($assignments, function($assignment) use ($item_id) {
        return $assignment['item_id'] == $item_id;
    });
}

if ($team_id) {
    $assignments = array_filter($assignments, function($assignment) use ($team_id) {
        return $assignment['assignee_type'] === 'team' && $assignment['assignee_id'] == $team_id;
    });
}

if ($user_id) {
    $assignments = array_filter($assignments, function($assignment) use ($user_id) {
        return $assignment['assignee_type'] === 'user' && $assignment['assignee_id'] == $user_id;
    });
}

if ($status) {
    $assignments = array_filter($assignments, function($assignment) use ($status) {
        return $assignment['status'] == $status;
    });
}

if ($assignee_type) {
    $assignments = array_filter($assignments, function($assignment) use ($assignee_type) {
        return $assignment['assignee_type'] == $assignee_type;
    });
}

// Get data for filters
$items = $inventoryModel->getAll(false);
$teams = $teamModel->getAllActive();
$stmt = $db->prepare("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name ASC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check permissions
$canAdd = has_permission('add_inventory');
$canEdit = has_permission('edit_inventory');
$canDelete = has_permission('delete_inventory');

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-user-check"></i> Inventory Assignments
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item active">Assignments</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            
            <!-- Filters and Search -->
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="row align-items-end">
                                <div class="col-md-2">
                                    <label for="item_id" class="form-label">Item</label>
                                    <select class="form-control" id="item_id" name="item_id">
                                        <option value="">All Items</option>
                                        <?php foreach ($items as $item): ?>                                        <option value="<?php echo $item['id']; ?>
" <?php echo $item_id == $item['id'] ? 'selected' : ''; ?>
>
                                            <?php echo htmlspecialchars($item['name']); ?>                                        </option>
                                        <?php endforeach; ?>                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="assignee_type" class="form-label">Type</label>
                                    <select class="form-control" id="assignee_type" name="assignee_type">
                                        <option value="">All Types</option>
                                        <option value="user" <?php echo $assignee_type == 'user' ? 'selected' : ''; ?>
>User</option>
                                        <option value="team" <?php echo $assignee_type == 'team' ? 'selected' : ''; ?>
>Team</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="team_id" class="form-label">Team</label>
                                    <select class="form-control" id="team_id" name="team_id">
                                        <option value="">All Teams</option>
                                        <?php foreach ($teams as $team): ?>                                        <option value="<?php echo $team['id']; ?>
" <?php echo $team_id == $team['id'] ? 'selected' : ''; ?>
>
                                            <?php echo htmlspecialchars($team['name']); ?>                                        </option>
                                        <?php endforeach; ?>                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>
>Active</option>
                                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>
>Inactive</option>
                                        <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>
>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="assignments.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                                <div class="col-md-2 text-right">
                                    <?php if ($canAdd): ?>
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                                        <i class="fas fa-plus"></i> New Assignment
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assignments Table -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-list"></i> Assignments List
                                <span class="badge badge-primary"><?php echo count($assignments); ?>
 assignments</span>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($assignments)): ?>                                <div class="text-center py-4">
                                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No assignments found.</p>
                                    <?php if ($canAdd): ?>                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                                        <i class="fas fa-plus"></i> Create First Assignment
                                    </button>
                                    <?php endif; ?>                                </div>
                            <?php else: ?>                                <div class="table-responsive">
                                    <table class="table table-hover" id="assignmentsTable">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Assigned To</th>
                                                <th>Type</th>
                                                <th>Assigned By</th>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($assignments as $assignment): ?>                                            <tr data-assignment-id="<?php echo $assignment['id']; ?>">
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($assignment['item_name']); ?>
</strong>
                                                        <span class="badge ml-1" style="background-color: <?php echo $assignment['subscription_type_color'] ?? '#6c757d'; ?>
;">
                                                            <i class="fas <?php echo $assignment['subscription_type_icon'] ?? 'fa-tag'; ?>
"></i>
                                                        </span>
                                                    </div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($assignment['item_code']); ?>
</small>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($assignment['assignee_type'] === 'user'): ?>                                                            <i class="fas fa-user text-primary mr-2"></i>
                                                        <?php else: ?>                                                            <i class="fas fa-users text-info mr-2"></i>
                                                        <?php endif; ?>                                                        <div>
                                                            <strong><?php echo htmlspecialchars($assignment['assignee_name']); ?>
</strong>
                                                            <br><small class="text-muted"><?php echo ucfirst($assignment['assignee_type']); ?>
</small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $assignment['assignee_type'] === 'user' ? 'primary' : 'info'; ?>
">
                                                        <?php echo ucfirst($assignment['assignee_type']); ?>                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($assignment['assigned_by_name'] ?? 'System'); ?>
</td>
                                                <td><?php echo date('M j, Y', strtotime($assignment['assigned_at'])); ?>
</td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'active' =>
 'success',
                                                        'inactive' => 'secondary',
                                                        'cancelled' => 'warning'
                                                    ][$assignment['status']] ?? 'light';
                                                    ?>
                                                    <span class="badge badge-<?php echo $statusClass; ?>
">
                                                        <?php echo ucfirst($assignment['status']); ?>                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="assignment-view.php?id=<?php echo $assignment['id']; ?>
" 
                                                           class="btn btn-outline-info" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($canEdit && $assignment['status'] === 'active'): ?>                                                        <button type="button" class="btn btn-outline-warning" 
                                                                onclick="unassignItem(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['item_name']); ?>', '<?php echo htmlspecialchars($assignment['assignee_name']); ?>')"
                                                                title="Unassign">
                                                            <i class="fas fa-user-minus"></i>
                                                        </button>
                                                        <?php endif; ?>                                                        <?php if ($canDelete): ?>                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="deleteAssignment(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['item_name']); ?>', '<?php echo htmlspecialchars($assignment['assignee_name']); ?>')"
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <?php endif; ?>                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Unassign Confirmation Modal -->
<div class="modal fade" id="unassignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirm Unassign</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to unassign this item?</p>
                <p><strong id="unassignItemName"></strong> from <strong id="unassignAssigneeName"></strong></p>
                <p class="text-info">
                    <i class="fas fa-info-circle"></i>
                    This will change the assignment status to inactive.
                </p>
                
                <div class="form-group mt-3">
                    <label for="unassignReason">Reason (optional):</label>
                    <textarea class="form-control" id="unassignReason" name="reason" rows="3" 
                              placeholder="Enter reason for unassigning..."></textarea>
                </div>
                
                <input type="hidden" id="unassignAssignmentId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="performUnassign()">
                    <i class="fas fa-user-minus"></i> Unassign
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirm Delete</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this assignment?</p>
                <p><strong id="deleteItemName"></strong> from <strong id="deleteAssigneeName"></strong></p>
                <p class="text-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    This action cannot be undone.
                </p>
                <input type="hidden" id="deleteAssignmentId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="performDelete()">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Assignment Modal -->
<div class="modal fade" id="addAssignmentModal" tabindex="-1" aria-labelledby="addAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="addAssignmentModalLabel">
                    <i class="fas fa-plus"></i> New Assignment
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addAssignmentForm">
                <div class="modal-body">
                    <!-- İlk satır: Inventory Item ve Assignment Date -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="item_id">Inventory Item *</label>
                                <select class="form-control" id="item_id" name="item_id" required>
                                    <option value="">Select an item...</option>
                                    <?php foreach ($items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['name']); ?> 
                                        (<?php echo htmlspecialchars($item['type'] ?? 'No Type'); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="assigned_date">Assignment Date *</label>
                                <input type="date" class="form-control" id="assigned_date" name="assigned_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- İkinci satır: Assign To dropdown -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="modal_assignee_type">Assign To *</label>
                                <select class="form-control" id="modal_assignee_type" name="assignee_type" required>
                                    <option value="">Select assignee type...</option>
                                    <option value="user" selected>User</option>
                                    <option value="team">Team</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- User/Team selection area -->
                            <div class="form-group" id="modal_user_select_group" style="display: none;">
                                <label for="modal_user_id">Select User *</label>
                                <select class="form-control" id="modal_user_id" name="user_id">
                                    <option value="">Select user...</option>
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" id="modal_team_select_group" style="display: none;">
                                <label for="modal_team_id">Select Team *</label>
                                <select class="form-control" id="modal_team_id" name="team_id">
                                    <option value="">Select team...</option>
                                    <?php foreach ($teams as $team): ?>
                                    <option value="<?php echo $team['id']; ?>">
                                        <?php echo htmlspecialchars($team['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Üçüncü satır: Notes -->
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" 
                                  placeholder="Optional notes about this assignment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <div id="assignmentErrorMsg" style="display:none;" class="w-100 mb-2">
                        <div class="alert alert-danger p-2" role="alert" style="margin-bottom:0;"></div>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show alert function
function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="fas ${iconClass}"></i> ${message}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `;
    
    // Insert alert at the top of content
    $('.content-header').after(alertHtml);
    
    // Auto-hide after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}

// Initialize DataTable
$(document).ready(function() {
    $('#assignmentsTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[4, 'desc']], // Order by date descending
        columnDefs: [
            { orderable: false, targets: [-1] } // Disable sorting on actions column
        ]
    });

    // Handle assignee type change
    function toggleAssigneeSelection() {
        var type = $('#modal_assignee_type').val();
        console.log('Assignee type changed to:', type);
        
        // Element varlığını kontrol et
        console.log('User group element exists:', $('#modal_user_select_group').length > 0);
        console.log('Team group element exists:', $('#modal_team_select_group').length > 0);
        
        // Önce hepsini gizle
        $('#modal_user_select_group').css('display', 'none');
        $('#modal_team_select_group').css('display', 'none');
        $('#modal_user_id, #modal_team_id').prop('required', false);
        
        // Seçilen türe göre göster
        if (type === 'user') {
            console.log('Showing user select group');
            $('#modal_user_select_group').css('display', 'block');
            $('#modal_user_id').prop('required', true);
            console.log('User group display after show:', $('#modal_user_select_group').css('display'));
        } else if (type === 'team') {
            console.log('Showing team select group');
            $('#modal_team_select_group').css('display', 'block');
            $('#modal_team_id').prop('required', true);
            console.log('Team group display after show:', $('#modal_team_select_group').css('display'));
        }
    }
    
    // Event listener ekle
    $('#modal_assignee_type').on('change', toggleAssigneeSelection);

    // Trigger change event on page load to show default selection
    console.log('Triggering initial change event');
    $('#modal_assignee_type').trigger('change');

    // Handle add assignment form submission
    $('#addAssignmentForm').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        var submitBtn = $(this).find('button[type="submit"]');
        var originalText = submitBtn.html();
        
        // Manuel olarak assignee_id'yi ekle (gizli olan alanlardan)
        var assigneeType = $('#modal_assignee_type').val();
        var assigneeId = '';
        
        if (assigneeType === 'user') {
            assigneeId = $('#modal_user_id').val();
        } else if (assigneeType === 'team') {
            assigneeId = $('#modal_team_id').val();
        }
        
        // FormData'ya manuel ekle
        formData.set('assignee_id', assigneeId);
        
        // Debug için console'a yazdır
        console.log('Form submission data:');
        console.log('item_id:', formData.get('item_id'));
        console.log('assignee_type:', formData.get('assignee_type'));
        console.log('assignee_id:', formData.get('assignee_id'));
        console.log('assigned_date:', formData.get('assigned_date'));
        console.log('notes:', formData.get('notes'));
        
        // Show loading
        submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Creating...').prop('disabled', true);
        
        $.ajax({
            url: 'assignment-add.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    // Show success message
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message);
                    } else {
                        showAlert('success', response.message);
                    }
                    // Başarılıysa modalı kapat ve sayfayı yenile
                    $('#addAssignmentModal').modal('hide');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                    // Modal içi hata mesajını gizle
                    $('#assignmentErrorMsg').hide();
                } else {
                    // Hata varsa modal açık kalsın, mesajı hem sayfada hem modalda göster
                    showAlert('error', response.message);
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message);
                    }
                    // Modal footer'da göster
                    $('#assignmentErrorMsg .alert').text(response.message);
                    $('#assignmentErrorMsg').show();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                
                if (typeof toastr !== 'undefined') {
                    toastr.error('An error occurred while creating the assignment.');
                } else {
                    alert('An error occurred while creating the assignment.');
                }
            },
            complete: function() {
                // Reset button
                submitBtn.html(originalText).prop('disabled', false);
            }
        });
    });

    // Reset form when modal is hidden
    $('#addAssignmentModal').on('hidden.bs.modal', function() {
    $('#addAssignmentForm')[0].reset();
    $('#modal_user_select_group, #modal_team_select_group').hide();
    $('#modal_user_id, #modal_team_id').prop('required', false);
    // Modal hata mesajını gizle
    $('#assignmentErrorMsg').hide();
    // Set default selection to user and show user list
    $('#modal_assignee_type').val('user');
    toggleAssigneeSelection();
    });

    // Modal açıldığında da tetikle
    $('#addAssignmentModal').on('shown.bs.modal', function() {
        console.log('Modal opened, setting default user selection');
        $('#modal_assignee_type').val('user');
        toggleAssigneeSelection();
    });
    
    // Reset unassign modal when hidden
    $('#unassignModal').on('hidden.bs.modal', function() {
        $('#unassignReason').val('');
        $('#unassignAssignmentId').val('');
    });
});

// Unassign item function
function unassignItem(id, itemName, assigneeName) {
    $('#unassignAssignmentId').val(id);
    $('#unassignItemName').text(itemName);
    $('#unassignAssigneeName').text(assigneeName);
    $('#unassignReason').val(''); // Clear previous reason
    $('#unassignModal').modal('show');
}

// Perform unassign with AJAX
function performUnassign() {
    const assignmentId = $('#unassignAssignmentId').val();
    const reason = $('#unassignReason').val().trim();
    
    if (!assignmentId) {
        showAlert('error', 'Assignment ID is missing.');
        return;
    }
    
    // Show loading state
    const unassignButton = $('.btn-warning[onclick="performUnassign()"]');
    const originalText = unassignButton.html();
    unassignButton.html('<i class="fas fa-spinner fa-spin"></i> Unassigning...').prop('disabled', true);
    
    $.ajax({
        url: 'assignment-unassign.php',
        type: 'POST',
        data: {
            assignment_id: assignmentId,
            reason: reason
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#unassignModal').modal('hide');
                
                // Update the row to show unassigned status
                const row = $('tr[data-assignment-id="' + assignmentId + '"]');
                const statusCell = row.find('td:nth-child(4)'); // Status column
                statusCell.html('<span class="badge badge-secondary">Unassigned</span>');
                
                // Remove unassign button and update actions
                const actionsCell = row.find('td:last-child .btn-group');
                actionsCell.find('.btn-outline-warning').remove(); // Remove unassign button
                
            } else {
                showAlert('error', response.message || 'Unassign failed.');
            }
        },
        error: function(xhr, status, error) {
            console.error('Unassign error:', error);
            showAlert('error', 'An error occurred while unassigning the assignment.');
        },
        complete: function() {
            // Reset button state
            unassignButton.html(originalText).prop('disabled', false);
        }
    });
}

// Delete assignment function
function deleteAssignment(id, itemName, assigneeName) {
    $('#deleteAssignmentId').val(id);
    $('#deleteItemName').text(itemName);
    $('#deleteAssigneeName').text(assigneeName);
    $('#deleteModal').modal('show');
}

// Perform delete with AJAX
function performDelete() {
    const assignmentId = $('#deleteAssignmentId').val();
    
    if (!assignmentId) {
        showAlert('error', 'Assignment ID is missing.');
        return;
    }
    
    // Show loading state
    const deleteButton = $('.btn-danger[onclick="performDelete()"]');
    const originalText = deleteButton.html();
    deleteButton.html('<i class="fas fa-spinner fa-spin"></i> Deleting...').prop('disabled', true);
    
    $.ajax({
        url: 'assignment-delete.php',
        type: 'POST',
        data: {
            assignment_id: assignmentId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('success', response.message);
                $('#deleteModal').modal('hide');
                
                // Remove the row from table
                $('tr[data-assignment-id="' + assignmentId + '"]').fadeOut(500, function() {
                    $(this).remove();
                    
                    // Check if table is empty
                    if ($('#assignmentsTable tbody tr').length === 0) {
                        location.reload(); // Reload to show "no assignments" message
                    }
                });
            } else {
                showAlert('error', response.message || 'Delete failed.');
            }
        },
        error: function(xhr, status, error) {
            console.error('Delete error:', error);
            showAlert('error', 'An error occurred while deleting the assignment.');
        },
        complete: function() {
            // Reset button state
            deleteButton.html(originalText).prop('disabled', false);
        }
    });
}
</script>

<?php include '../../components/footer.php'; ?>