<?php
/**
 * Inventory Module - Teams List
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

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$page_title = "Inventory Teams";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/Team.php';

// Initialize models
$teamModel = new InventoryTeam($db);

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

// Get data
$teams = $teamModel->getAll();

// Apply filters
if ($search) {
    $teams = array_filter($teams, function($team) use ($search) {
        return stripos($team['name'], $search) !== false || 
               stripos($team['description'], $search) !== false ||
               stripos($team['department'], $search) !== false;
    });
}

if ($status) {
    $teams = array_filter($teams, function($team) use ($status) {
        if ($status === 'active') {
            return $team['is_active'] == 1;
        } elseif ($status === 'inactive') {
            return $team['is_active'] == 0;
        }
        return true;
    });
}

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
                        <i class="fas fa-users"></i> Inventory Teams
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item active">Teams</li>
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
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Search teams..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="teams.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                                <div class="col-md-2 text-right">
                                    <?php if ($canAdd): ?>
                                    <a href="team-add.php" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Add New Team
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Teams Table -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users"></i> Teams Management
                                <span class="badge badge-primary"><?php echo count($teams); ?> teams</span>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($teams)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No teams found.</p>
                                    <?php if ($canAdd): ?>
                                    <a href="team-add.php" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Add First Team
                                    </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="teamsTable">
                                        <thead>
                                            <tr>
                                                <th>Team</th>
                                                <th>Department</th>
                                                <th>Manager</th>
                                                <th>Members</th>
                                                <th>Assignments</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($teams as $team): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($team['name']); ?></strong>
                                                    </div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($team['code']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($team['department'] ?: '-'); ?></td>
                                                <td><?php echo htmlspecialchars($team['manager_name'] ?: '-'); ?></td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <?php echo $team['member_count']; ?> members
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-primary">
                                                        <?php echo $team['assignment_count']; ?> items
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $team['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $team['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($team['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="team-view.php?id=<?php echo $team['id']; ?>" 
                                                           class="btn btn-outline-info" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($canEdit): ?>
                                                        <a href="team-edit.php?id=<?php echo $team['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                        <?php if ($canDelete): ?>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="deleteTeam(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars($team['name']); ?>')" 
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <?php endif; ?>
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
// Initialize DataTable
$(document).ready(function() {
    $('#teamsTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [-1] } // Disable sorting on actions column
        ]
    });
});

// Delete team function
function deleteTeam(id, name) {
    $('#deleteTeamId').val(id);
    $('#deleteTeamName').text(name);
    $('#deleteModal').modal('show');
}
</script>

<?php include '../../components/footer.php'; ?>
