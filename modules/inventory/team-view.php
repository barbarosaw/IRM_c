<?php
/**
 * Inventory Module - View Team Details
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

$page_title = "View Team Details";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/Team.php';
require_once $root_dir . '/modules/inventory/models/Assignment.php';

// Initialize models
$teamModel = new InventoryTeam($db);
$assignmentModel = new InventoryAssignment($db);

// Get team ID from URL
$teamId = (int)($_GET['id'] ?? 0);

if (!$teamId) {
    $_SESSION['error_message'] = "Invalid team ID.";
    header('Location: teams.php');
    exit;
}

// Get team details
$team = $teamModel->getById($teamId);

if (!$team) {
    $_SESSION['error_message'] = "Team not found.";
    header('Location: teams.php');
    exit;
}

// Get actual team leader from members list
$teamLeader = null;
if (!empty($team['members'])) {
    foreach ($team['members'] as $member) {
        if ($member['role'] === 'manager') {
            $teamLeader = $member;
            break;
        }
    }
}

// Override manager_name with actual team leader
if ($teamLeader) {
    $team['manager_name'] = $teamLeader['user_name'];
}

// Get team assignments
$assignments = $assignmentModel->getByTeamId($teamId);

// Calculate budget usage
$totalAssignmentCost = 0;
foreach ($assignments as $assignment) {
    if ($assignment['status'] === 'active') {
        $totalAssignmentCost += (float)($assignment['monthly_cost'] ?? 0);
    }
}
$budgetLimit = (float)($team['budget_limit'] ?? 0);
$remainingBudget = $budgetLimit - $totalAssignmentCost;
$budgetUsagePercentage = $budgetLimit > 0 ? ($totalAssignmentCost / $budgetLimit) * 100 : 0;

// Check permissions
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
                        <i class="fas fa-users"></i> <?php echo htmlspecialchars($team['name']); ?>                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item"><a href="teams.php">Teams</a></li>
                        <li class="breadcrumb-item active">View</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            
            <div class="row">
                
                <!-- Team Details -->
                <div class="col-md-8">
                    
                    <!-- Basic Information -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-info-circle"></i> Team Information
                            </h3>
                            <div class="card-tools">
                                <?php if ($canEdit): ?>                                <a href="team-edit.php?id=<?php echo $team['id']; ?>
" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <?php endif; ?>                                <?php if ($canDelete): ?>                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deleteTeam(<?php echo $team['id']; ?>
, '<?php echo htmlspecialchars($team['name']); ?>
')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <?php endif; ?>                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="30%">Name:</th>
                                            <td><strong><?php echo htmlspecialchars($team['name']); ?>
</strong></td>
                                        </tr>
                                        <tr>
                                            <th>Code:</th>
                                            <td><code><?php echo htmlspecialchars($team['code']); ?>
</code></td>
                                        </tr>
                                        <tr>
                                            <th>Department:</th>
                                            <td><?php echo htmlspecialchars($team['department'] ?: 'Not specified'); ?>
</td>
                                        </tr>
                                        <tr>
                                            <th>Status:</th>
                                            <td>
                                                <span class="badge badge-<?php echo $team['is_active'] ? 'success' : 'secondary'; ?>
">
                                                    <?php echo $team['is_active'] ? 'Active' : 'Inactive'; ?>                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="30%">Manager:</th>
                                            <td><?php echo htmlspecialchars($team['manager_name'] ?: 'No manager assigned'); ?>
</td>
                                        </tr>
                                        <tr>
                                            <th>Members:</th>
                                            <td>
                                                <span class="badge badge-info">
                                                    <?php echo count($team['members']); ?>
 members
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Created:</th>
                                            <td><?php echo date('M j, Y g:i A', strtotime($team['created_at'])); ?>
</td>
                                        </tr>
                                        <tr>
                                            <th>Created By:</th>
                                            <td><?php echo htmlspecialchars($team['created_by_name'] ?? 'Unknown'); ?>
</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <?php if ($team['description']): ?>                            <div class="mt-3">
                                <h6>Description:</h6>
                                <p><?php echo nl2br(htmlspecialchars($team['description'])); ?>
</p>
                            </div>
                            <?php endif; ?>                        </div>
                    </div>

                    <!-- Team Members -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users"></i> Team Members
                                <span class="badge badge-primary"><?php echo count($team['members']); ?>
</span>
                            </h3>
                            <div class="card-tools">
                                <?php if ($canEdit): ?>
                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                                    <i class="fas fa-user-plus"></i> Add Member
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($team['members'])): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-user-friends fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No team members yet.</p>
                                    <?php if ($canEdit): ?>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                                        <i class="fas fa-user-plus"></i> Add First Member
                                    </button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Member</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Joined</th>
                                                <?php if ($canEdit): ?>
                                                <th width="120">Actions</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($team['members'] as $member): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm me-2">
                                                            <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($member['user_name']); ?></strong>
                                                            <?php if ($member['role'] === 'manager'): ?>
                                                            <span class="badge badge-warning ms-1">Manager</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($member['user_email']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $member['role'] === 'manager' ? 'warning' : 'info'; ?>">
                                                        <?php echo ucfirst($member['role']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($member['joined_at'])); ?>
                                                    </small>
                                                </td>
                                                <?php if ($canEdit): ?>
                                                <td>
                                                    <?php if ($member['role'] !== 'manager'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="removeMember(<?php echo $member['user_id']; ?>, '<?php echo htmlspecialchars($member['user_name']); ?>')" 
                                                            title="Remove Member">
                                                        <i class="fas fa-user-minus"></i>
                                                    </button>
                                                    <?php else: ?>
                                                    <span class="text-muted small">Manager</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Team Assignments -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-boxes"></i> Inventory Assignments
                                <span class="badge badge-primary"><?php echo count($assignments); ?>
</span>
                            </h3>
                            <div class="card-tools">
                                <a href="assignments.php?team_id=<?php echo $team['id']; ?>
" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> View All
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($assignments)): ?>                                <p class="text-muted text-center py-3">No inventory items assigned to this team</p>
                            <?php else: ?>                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Type</th>
                                                <th>Assigned Date</th>
                                                <th>Status</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($assignments, 0, 10) as $assignment): ?>                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($assignment['item_name']); ?>
</strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($assignment['item_code']); ?>
</small>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background-color: <?php echo $assignment['subscription_type_color']; ?>
;">
                                                        <i class="fas <?php echo $assignment['subscription_type_icon']; ?>
"></i>
                                                        <?php echo htmlspecialchars($assignment['subscription_type_name']); ?>                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($assignment['assigned_date'])); ?>
</td>
                                                <td>
                                                    <span class="badge badge-<?php echo $assignment['status'] === 'active' ? 'success' : 'secondary'; ?>
">
                                                        <?php echo ucfirst($assignment['status']); ?>                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($assignment['notes']): ?>                                                        <?php echo htmlspecialchars(substr($assignment['notes'], 0, 50)); ?>                                                        <?php echo strlen($assignment['notes']) >
 50 ? '...' : ''; ?>
                                                    <?php else: ?>                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>                                                </td>
                                            </tr>
                                            <?php endforeach; ?>                                        </tbody>
                                    </table>
                                </div>
                                <?php if (count($assignments) >
 10): ?>
                                <div class="text-center mt-2">
                                    <a href="assignments.php?team_id=<?php echo $team['id']; ?>
" class="btn btn-sm btn-outline-primary">
                                        View all <?php echo count($assignments); ?>
 assignments
                                    </a>
                                </div>
                                <?php endif; ?>                            <?php endif; ?>                        </div>
                    </div>

                </div>
                
                <!-- Sidebar -->
                <div class="col-md-4">
                    
                    <!-- Quick Stats -->
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-pie"></i> Team Statistics
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6 text-center">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Members</span>
                                        <span class="info-box-number text-info"><?php echo count($team['members']); ?>
</span>
                                    </div>
                                </div>
                                <div class="col-6 text-center">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Assignments</span>
                                        <span class="info-box-number text-primary"><?php echo count($assignments); ?>
</span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($assignments)): ?>                            <div class="row mt-3">
                                <div class="col-12">
                                    <h6>Assignment Status</h6>
                                    <?php
                                    $activeAssignments = array_filter($assignments, function($a) { return $a['status'] === 'active'; });
                                    $inactiveAssignments = array_filter($assignments, function($a) { return $a['status'] !== 'active'; });
                                    $activePercentage = count($assignments) >
 0 ? (count($activeAssignments) / count($assignments) * 100) : 0;
                                    ?>
                                    <div class="progress mb-2">
                                        <div class="progress-bar bg-success" style="width: <?php echo $activePercentage; ?>
%"></div>
                                    </div>
                                    <small><?php echo count($activeAssignments); ?>
 active, <?php echo count($inactiveAssignments); ?>
 inactive</small>
                                </div>
                            </div>
                            <?php endif; ?>                        </div>
                    </div>

                    <!-- Team Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-cogs"></i> Quick Actions
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <?php if ($canEdit): ?>
                                <button type="button" class="btn btn-success btn-block mb-2" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                                    <i class="fas fa-user-plus"></i> Add Member
                                </button>
                                </button>
                                <a href="team-edit.php?id=<?php echo $team['id']; ?>
" class="btn btn-primary btn-block mb-2">
                                    <i class="fas fa-edit"></i> Edit Team
                                </a>
                                <?php endif; ?>                                <a href="assignments.php?team_id=<?php echo $team['id']; ?>
" class="btn btn-info btn-block mb-2">
                                    <i class="fas fa-boxes"></i> View Assignments
                                </a>
                                <a href="teams.php" class="btn btn-secondary btn-block">
                                    <i class="fas fa-arrow-left"></i> Back to Teams
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
                
            </div>

        </div>
    </div>
</div>

<!-- Add Member Modal -->
<?php if ($canEdit): ?><div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Add Team Member</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="team-add-member.php">
                <div class="modal-body">
                    <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>
">
                    
                    <div class="form-group">
                        <label for="user_id">Select User</label>
                        <select class="form-control select2" id="user_id" name="user_id" required>
                            <option value="">Choose a user...</option>
                            <?php
                            // Get available users (not already in team)
                            $currentMemberIds = array_column($team['members'], 'user_id');
                            $placeholders = str_repeat('?,', count($currentMemberIds));
                            $placeholders = rtrim($placeholders, ',');
                            
                            $sql = "SELECT id, name, email FROM users WHERE is_active = 1";
                            if (!empty($currentMemberIds)) {
                                $sql .= " AND id NOT IN ($placeholders)";
                            }
                            $sql .= " ORDER BY name ASC";
                            
                            $stmt = $db->prepare($sql);
                            $stmt->execute($currentMemberIds);
                            $availableUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($availableUsers as $user): ?>
                            <option value="<?php echo $user['id']; ?>
">
                                <?php echo htmlspecialchars($user['name']); ?>
 (<?php echo htmlspecialchars($user['email']); ?>
)
                            </option>
                            <?php endforeach; ?>                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="member">Member</option>
                            <option value="lead">Team Lead</option>
                        </select>
                        <small class="form-text text-muted">Note: Team manager is set separately in team settings.</small>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_lead" name="is_lead" value="1">
                        <label class="form-check-label" for="is_lead">
                            Make this user the team leader
                        </label>
                        <small class="form-text text-muted">This will remove leader status from current leader if any.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-user-plus"></i> Add Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!-- Remove Member Modal -->
<div class="modal fade" id="removeMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Remove Team Member</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove this member from the team?</p>
                <p><strong id="removeMemberName"></strong></p>
                <p class="text-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    This will remove their access to team-assigned inventory items.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="removeMemberForm" method="POST" action="team-remove-member.php" style="display: inline;">
                    <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>
">
                    <input type="hidden" id="removeMemberUserId" name="user_id">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-user-minus"></i> Remove
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Team Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Confirm Delete</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this team?</p>
                <p><strong id="deleteTeamName"></strong></p>
                <p class="text-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    This will also remove all team members and unassign all inventory items.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="team-delete.php" style="display: inline;">
                    <input type="hidden" id="deleteTeamId" name="team_id">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        placeholder: 'Choose a user...'
    });
});

// Remove member function
function removeMember(userId, userName) {
    $('#removeMemberUserId').val(userId);
    $('#removeMemberName').text(userName);
    $('#removeMemberModal').modal('show');
}

// Delete team function
function deleteTeam(id, name) {
    $('#deleteTeamId').val(id);
    $('#deleteTeamName').text(name);
    $('#deleteModal').modal('show');
}
</script>

<?php include '../../components/footer.php'; ?>