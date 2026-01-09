<?php
/**
 * Inventory Module - Edit Team
 * 
 * @author System Generated
 */


// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory permissions
if (!has_permission('edit_inventory')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$page_title = "Edit Team";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/Team.php';

// Initialize models
$teamModel = new InventoryTeam($db);

// Get team ID
$teamId = (int)($_GET['id'] ?? 0);
if (!$teamId) {
    header('Location: teams.php?error=invalid_team');
    exit;
}

// Get team data
$teamData = $teamModel->getById($teamId);
if (!$teamData) {
    header('Location: teams.php?error=team_not_found');
    exit;
}

// Get team members with details
$stmt = $db->prepare("
    SELECT tm.*, u.name, u.email, u.id as user_id,
           (CASE WHEN tm.is_lead = 1 THEN 'Leader' ELSE 'Member' END) as role_display
    FROM inventory_team_members tm 
    JOIN users u ON tm.user_id = u.id 
    WHERE tm.team_id = ? AND tm.is_active = 1 
    ORDER BY tm.is_lead DESC, u.name
");
$stmt->execute([$teamId]);
$teamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current member IDs for selections
$currentMemberIds = array_column($teamMembers, 'user_id');

// Get all users for member/manager assignment
$stmt = $db->prepare("SELECT id, name, email FROM users WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$availableUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get team assignment statistics
$stmt = $db->prepare("
    SELECT COUNT(*) as assignments_count,
           COALESCE(SUM(i.cost), 0) as total_cost
    FROM inventory_assignments ia
    LEFT JOIN inventory_items i ON ia.item_id = i.id
    WHERE ia.assignee_type = 'team' AND ia.assignee_id = ? AND ia.status = 'active'
");
$stmt->execute([$teamId]);
$teamStats = $stmt->fetch(PDO::FETCH_ASSOC);

$errors = [];
$success = false;

// Handle team member actions (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_member') {
        $userId = (int)$_POST['user_id'];
        $isLead = isset($_POST['is_lead']) ? 1 : 0;
        
        // Check if user is already a member
        $stmt = $db->prepare("SELECT id FROM inventory_team_members WHERE team_id = ? AND user_id = ? AND is_active = 1");
        $stmt->execute([$teamId, $userId]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'User is already a team member']);
            exit;
        }
        
        // If setting as lead, remove lead status from others
        if ($isLead) {
            $stmt = $db->prepare("UPDATE inventory_team_members SET is_lead = 0 WHERE team_id = ?");
            $stmt->execute([$teamId]);
        }
        
        // Add new member
        $stmt = $db->prepare("INSERT INTO inventory_team_members (team_id, user_id, is_lead, added_by, created_at) VALUES (?, ?, ?, ?, NOW())");
        
        if ($stmt->execute([$teamId, $userId, $isLead, $_SESSION['user_id']])) {
            echo json_encode(['success' => true, 'message' => 'Member added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add member']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'remove_member') {
        $userId = (int)$_POST['user_id'];
        
        $stmt = $db->prepare("UPDATE inventory_team_members SET is_active = 0, updated_at = NOW() WHERE team_id = ? AND user_id = ?");
        
        if ($stmt->execute([$teamId, $userId])) {
            echo json_encode(['success' => true, 'message' => 'Member removed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove member']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'change_role') {
        $userId = (int)$_POST['user_id'];
        $isLead = isset($_POST['is_lead']) ? 1 : 0;
        
        // If setting as lead, remove lead status from others
        if ($isLead) {
            $stmt = $db->prepare("UPDATE inventory_team_members SET is_lead = 0 WHERE team_id = ?");
            $stmt->execute([$teamId]);
        }
        
        $stmt = $db->prepare("UPDATE inventory_team_members SET is_lead = ?, updated_at = NOW() WHERE team_id = ? AND user_id = ?");
        
        if ($stmt->execute([$isLead, $teamId, $userId])) {
            echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update role']);
        }
        exit;
    }
}

// Handle team information update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    try {
        $data = [
            'name' => trim($_POST['name']),
            'code' => trim($_POST['code']),
            'description' => trim($_POST['description'] ?? ''),
            'department' => trim($_POST['department'] ?? ''),
            'manager_id' => !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null,
            'budget_limit' => !empty($_POST['budget_limit']) ? (float)$_POST['budget_limit'] : null,
            'status' => $_POST['status'] ?? 'active'
        ];
        
        // Validation
        if (empty($data['name'])) {
            $errors[] = 'Team name is required.';
        }
        
        if (empty($data['code'])) {
            $errors[] = 'Team code is required.';
        } else {
            // Check if code already exists (excluding current team)
            if ($teamModel->codeExists($data['code'], $teamId)) {
                $errors[] = 'Team code already exists.';
            }
        }
        
        if (empty($errors)) {
            if ($teamModel->update($teamId, $data)) {
                $success = true;
                $_SESSION['success_message'] = 'Team updated successfully.';
                header('Location: team-view.php?id=' . $teamId);
                exit;
            } else {
                $errors[] = 'Failed to update team.';
            }
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-edit"></i> Edit Team: <?php echo htmlspecialchars($teamData['name']); ?>
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item"><a href="teams.php">Teams</a></li>
                        <li class="breadcrumb-item"><a href="team-view.php?id=<?php echo $teamId; ?>">Team Details</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <h5><i class="icon fas fa-ban"></i> Error!</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row">
                    
                    <!-- Basic Information -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-info-circle"></i> Team Information
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="name">Team Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?php echo htmlspecialchars($teamData['name']); ?>" 
                                                   placeholder="e.g., IT Support Team" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="code">Team Code <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="code" name="code" 
                                                   value="<?php echo htmlspecialchars($teamData['code']); ?>" 
                                                   placeholder="e.g., IT-SUPPORT" required>
                                            <small class="form-text text-muted">Unique identifier for this team</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" 
                                              placeholder="Brief description of the team's role and responsibilities"><?php echo htmlspecialchars($teamData['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="department">Department</label>
                                            <input type="text" class="form-control" id="department" name="department" 
                                                   value="<?php echo htmlspecialchars($teamData['department'] ?? ''); ?>" 
                                                   placeholder="e.g., Information Technology"
                                                   list="departments">
                                            <datalist id="departments">
                                                <option value="Information Technology">
                                                <option value="Human Resources">
                                                <option value="Finance">
                                                <option value="Marketing">
                                                <option value="Sales">
                                                <option value="Operations">
                                                <option value="Customer Support">
                                                <option value="Research & Development">
                                            </datalist>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="status">Status</label>
                                            <select class="form-control" id="status" name="status">
                                                <option value="active" <?php echo ($teamData['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo ($teamData['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Team Management -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-user-tie"></i> Team Management
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="manager_id">Team Manager</label>
                                            <select class="form-control select2" id="manager_id" name="manager_id">
                                                <option value="">Select team manager (optional)</option>
                                                <?php foreach ($availableUsers as $user): ?>
                                                <option value="<?php echo $user['id']; ?>" 
                                                        <?php echo ($teamData['manager_id'] == $user['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">
                                                The team manager will have administrative rights for this team's inventory assignments.
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="budget_limit">Budget Limit</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                                <input type="number" step="0.01" class="form-control" id="budget_limit" name="budget_limit" 
                                                       value="<?php echo $teamData['budget_limit'] ?? ''; ?>" placeholder="0.00">
                                            </div>
                                            <small class="form-text text-muted">Maximum budget allocation for this team</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Team Members Management -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-users"></i> Team Members (<?php echo count($teamMembers); ?>)
                                </h3>
                                <div class="card-tools">
                                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addMemberModal">
                                        <i class="fas fa-plus"></i> Add Member
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($teamMembers)): ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No team members assigned yet.</p>
                                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addMemberModal">
                                        <i class="fas fa-plus"></i> Add First Member
                                    </button>
                                </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Member</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Joined</th>
                                                <th width="150">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="membersTable">
                                            <?php foreach ($teamMembers as $member): ?>
                                            <tr id="member-<?php echo $member['user_id']; ?>">
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm mr-2">
                                                            <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                                            <?php if ($member['is_lead']): ?>
                                                            <span class="badge badge-primary ml-1">Leader</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo $member['is_lead'] ? 'badge-primary' : 'badge-secondary'; ?>">
                                                        <?php echo $member['role_display']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($member['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if (!$member['is_lead']): ?>
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="changeRole(<?php echo $member['user_id']; ?>, 1)" 
                                                                title="Make Leader">
                                                            <i class="fas fa-crown"></i>
                                                        </button>
                                                        <?php else: ?>
                                                        <button type="button" class="btn btn-outline-secondary" 
                                                                onclick="changeRole(<?php echo $member['user_id']; ?>, 0)" 
                                                                title="Remove Leader">
                                                            <i class="fas fa-user"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="removeMember(<?php echo $member['user_id']; ?>, '<?php echo htmlspecialchars($member['name']); ?>')" 
                                                                title="Remove Member">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar Information -->
                    <div class="col-md-4">
                        
                        <!-- Current Statistics -->
                        <div class="card card-info">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chart-pie"></i> Current Statistics
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="border-right">
                                            <h4 class="text-info"><?php echo count($teamMembers); ?></h4>
                                            <small class="text-muted">Members</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-success"><?php echo $teamStats['assignments_count'] ?? 0; ?></h4>
                                        <small class="text-muted">Assignments</small>
                                    </div>
                                </div>
                                <?php if ($teamData['budget_limit']): ?>
                                <hr>
                                <div class="text-center">
                                    <h5 class="text-primary">$<?php echo number_format($teamStats['total_cost'] ?? 0, 2); ?></h5>
                                    <small class="text-muted">
                                        of $<?php echo number_format($teamData['budget_limit'], 2); ?> budget used
                                    </small>
                                    <?php
                                    $budgetUsed = ($teamStats['total_cost'] ?? 0) / $teamData['budget_limit'] * 100;
                                    $progressClass = $budgetUsed > 90 ? 'bg-danger' : ($budgetUsed > 70 ? 'bg-warning' : 'bg-success');
                                    ?>
                                    <div class="progress mt-2">
                                        <div class="progress-bar <?php echo $progressClass; ?>" 
                                             style="width: <?php echo min($budgetUsed, 100); ?>%"></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Help Information -->
                        <div class="card card-warning">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-question-circle"></i> Team Management
                                </h3>
                            </div>
                            <div class="card-body">
                                <h6>Member Roles:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-crown text-primary"></i> <strong>Leader:</strong> Can manage team assignments</li>
                                    <li><i class="fas fa-user text-secondary"></i> <strong>Member:</strong> Standard team member</li>
                                </ul>
                                
                                <h6 class="mt-3">Actions Available:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-plus text-success"></i> Add new members</li>
                                    <li><i class="fas fa-crown text-primary"></i> Promote to leader</li>
                                    <li><i class="fas fa-user text-info"></i> Demote to member</li>
                                    <li><i class="fas fa-times text-danger"></i> Remove from team</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <a href="team-view.php?id=<?php echo $teamId; ?>" class="btn btn-secondary btn-block">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <button type="submit" class="btn btn-primary btn-block">
                                            <i class="fas fa-save"></i> Update Team
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </form>

        </div>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">
                    <i class="fas fa-user-plus"></i> Add Team Member
                </h4>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form id="addMemberForm">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_member_id">Select User</label>
                        <select class="form-control select2" id="new_member_id" name="user_id" required>
                            <option value="">Choose a user...</option>
                            <?php foreach ($availableUsers as $user): ?>
                            <?php if (!in_array($user['id'], $currentMemberIds)): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                            </option>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="make_leader" name="is_lead" value="1">
                        <label class="form-check-label" for="make_leader">
                            Make this user the team leader
                        </label>
                        <small class="form-text text-muted">This will remove leader status from current leader if any.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        placeholder: 'Select team manager',
        allowClear: true,
        theme: 'bootstrap4'
    });
    
    // Auto-generate code from name if code is empty
    $('#name').on('input', function() {
        const name = $(this).val();
        const code = $('#code').val();
        
        if (!code && name) {
            const generatedCode = name
                .toUpperCase()
                .replace(/[^A-Z0-9\s]/g, '')
                .replace(/\s+/g, '-')
                .substring(0, 20);
            $('#code').val(generatedCode);
        }
    });
    
    // Add member form submission
    $('#addMemberForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'add_member');
        
        $.ajax({
            url: '',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#addMemberModal').modal('hide');
                    location.reload(); // Reload to update the members table
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('An error occurred while adding the member.');
            }
        });
    });
});

// Remove member function
function removeMember(userId, userName) {
    if (confirm('Are you sure you want to remove "' + userName + '" from this team?')) {
        $.ajax({
            url: '',
            method: 'POST',
            data: {
                action: 'remove_member',
                user_id: userId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    $('#member-' + userId).fadeOut(function() {
                        $(this).remove();
                        // Update member count
                        updateMemberCount();
                    });
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('An error occurred while removing the member.');
            }
        });
    }
}

// Change role function
function changeRole(userId, isLead) {
    const action = isLead ? 'promote to leader' : 'demote to member';
    
    if (confirm('Are you sure you want to ' + action + '?')) {
        $.ajax({
            url: '',
            method: 'POST',
            data: {
                action: 'change_role',
                user_id: userId,
                is_lead: isLead
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload(); // Reload to update all role displays
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error('An error occurred while changing the role.');
            }
        });
    }
}

// Update member count in the header
function updateMemberCount() {
    const memberCount = $('#membersTable tr').length;
    $('.card-title').first().html('<i class="fas fa-users"></i> Team Members (' + memberCount + ')');
    
    // Update statistics
    $('.text-info').first().text(memberCount);
}
</script>

<?php include '../../components/footer.php'; ?>
