<?php
/**
 * Inventory Module - Assignment Edit Page
 * 
 * @author System Generated
 */

// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory edit permission
if (!has_permission('edit_inventory')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$page_title = "Edit Assignment";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/Assignment.php';
require_once $root_dir . '/modules/inventory/models/Item.php';
require_once $root_dir . '/modules/inventory/models/Team.php';
require_once $root_dir . '/modules/inventory/models/UsageLog.php';

// Initialize models
$assignmentModel = new InventoryAssignment($db);
$itemModel = new InventoryItem($db);
$teamModel = new InventoryTeam($db);
$usageLogModel = new InventoryUsageLog($db);

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: assignments.php');
    exit;
}

$assignment_id = (int)$_GET['id'];

// Get assignment details
try {
    $assignment = $assignmentModel->getById($assignment_id);
    
    if (!$assignment) {
        $_SESSION['error'] = 'Assignment not found.';
        header('Location: assignments.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching assignment: " . $e->getMessage());
    $_SESSION['error'] = 'Error fetching assignment details.';
    header('Location: assignments.php');
    exit;
}

// Get all active items for dropdown
try {
    $items = $itemModel->getActive();
} catch (Exception $e) {
    error_log("Error fetching items: " . $e->getMessage());
    $items = [];
}

// Get all users for dropdown
try {
    $users = [];
    $stmt = $db->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE is_active = 1 ORDER BY first_name");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
}

// Get all teams for dropdown  
try {
    $teams = $teamModel->getActive();
} catch (Exception $e) {
    error_log("Error fetching teams: " . $e->getMessage());
    $teams = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'item_id' => (int)$_POST['item_id'],
            'assignee_type' => clean_input($_POST['assignee_type']),
            'assignee_id' => (int)$_POST['assignee_id'],
            'status' => clean_input($_POST['status']),
            'usage_start_date' => !empty($_POST['usage_start_date']) ? $_POST['usage_start_date'] : null,
            'usage_end_date' => !empty($_POST['usage_end_date']) ? $_POST['usage_end_date'] : null,
            'notes' => clean_input($_POST['notes']),
            'updated_by' => $_SESSION['user_id']
        ];

        // Validate required fields
        $errors = [];
        
        if (empty($data['item_id'])) {
            $errors[] = 'Item is required.';
        }
        
        if (empty($data['assignee_type'])) {
            $errors[] = 'Assignee type is required.';
        }
        
        if (empty($data['assignee_id'])) {
            $errors[] = 'Assignee is required.';
        }
        
        if (empty($data['status'])) {
            $errors[] = 'Status is required.';
        }

        // Validate dates
        if (!empty($data['usage_start_date']) && !empty($data['usage_end_date'])) {
            if (strtotime($data['usage_end_date']) < strtotime($data['usage_start_date'])) {
                $errors[] = 'End date cannot be earlier than start date.';
            }
        }

        if (empty($errors)) {
            // Update assignment
            if ($assignmentModel->update($assignment_id, $data)) {
                // Log the activity
                $usageLogModel->logUsage([
                    'item_id' => $data['item_id'],
                    'assignment_id' => $assignment_id,
                    'user_id' => $_SESSION['user_id'],
                    'action' => 'assignment_updated',
                    'description' => 'Assignment updated for ' . $data['assignee_type'] . ' ID: ' . $data['assignee_id']
                ]);

                $_SESSION['success'] = 'Assignment updated successfully.';
                header('Location: assignment-view.php?id=' . $assignment_id);
                exit;
            } else {
                $errors[] = 'Failed to update assignment.';
            }
        }

    } catch (Exception $e) {
        error_log("Error updating assignment: " . $e->getMessage());
        $errors[] = 'An error occurred while updating the assignment.';
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
                        <i class="fas fa-edit"></i> Edit Assignment
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item"><a href="assignments.php">Assignments</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- Action Buttons -->
            <div class="mb-3">
                <a href="assignments.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Assignments
                </a>
                <a href="assignment-view.php?id=<?php echo $assignment['id']; ?>" class="btn btn-info">
                    <i class="fas fa-eye"></i> View Assignment
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle"></i> Error!</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-edit"></i> Assignment Details
                            </h3>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="item_id">Item <span class="text-danger">*</span></label>
                                            <select name="item_id" id="item_id" class="form-control" required>
                                                <option value="">Select Item</option>
                                                <?php foreach ($items as $item): ?>
                                                    <option value="<?php echo $item['id']; ?>" 
                                                            <?php echo ($item['id'] == $assignment['item_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($item['name']) . ' (' . htmlspecialchars($item['code']) . ')'; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="assignee_type">Assignee Type <span class="text-danger">*</span></label>
                                            <select name="assignee_type" id="assignee_type" class="form-control" required>
                                                <option value="">Select Type</option>
                                                <option value="user" <?php echo ($assignment['assignee_type'] === 'user') ? 'selected' : ''; ?>>User</option>
                                                <option value="team" <?php echo ($assignment['assignee_type'] === 'team') ? 'selected' : ''; ?>>Team</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="assignee_id">Assignee <span class="text-danger">*</span></label>
                                            <select name="assignee_id" id="assignee_id" class="form-control" required>
                                                <option value="">Select Assignee</option>
                                                <!-- Will be populated by JavaScript based on assignee_type -->
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="status">Status <span class="text-danger">*</span></label>
                                            <select name="status" id="status" class="form-control" required>
                                                <option value="active" <?php echo ($assignment['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo ($assignment['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                <option value="suspended" <?php echo ($assignment['status'] === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="usage_start_date">Usage Start Date</label>
                                            <input type="date" name="usage_start_date" id="usage_start_date" 
                                                   class="form-control" 
                                                   value="<?php echo htmlspecialchars($assignment['usage_start_date'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="usage_end_date">Usage End Date</label>
                                            <input type="date" name="usage_end_date" id="usage_end_date" 
                                                   class="form-control"
                                                   value="<?php echo htmlspecialchars($assignment['usage_end_date'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="notes">Notes</label>
                                    <textarea name="notes" id="notes" class="form-control" rows="4" 
                                              placeholder="Enter any additional notes about this assignment..."><?php echo htmlspecialchars($assignment['notes'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Update Assignment
                                    </button>
                                    <a href="assignments.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Assignment Info Sidebar -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-info-circle"></i> Current Assignment Info
                            </h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td><strong>Assignment ID:</strong></td>
                                        <td><?php echo $assignment['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Current Item:</strong></td>
                                        <td>
                                            <span class="badge badge-primary">
                                                <?php echo htmlspecialchars($assignment['item_name']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Current Assignee:</strong></td>
                                        <td>
                                            <?php if ($assignment['assignee_type'] === 'user'): ?>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($assignment['assignee_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-info">
                                                    <i class="fas fa-users"></i> <?php echo htmlspecialchars($assignment['assignee_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <?php
                                            $statusColor = [
                                                'active' => 'success',
                                                'inactive' => 'secondary', 
                                                'suspended' => 'warning'
                                            ][$assignment['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge badge-<?php echo $statusColor; ?>">
                                                <?php echo ucfirst($assignment['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Assigned Date:</strong></td>
                                        <td><?php echo date('M j, Y H:i', strtotime($assignment['assigned_at'])); ?></td>
                                    </tr>
                                    <?php if ($assignment['usage_start_date']): ?>
                                    <tr>
                                        <td><strong>Usage Period:</strong></td>
                                        <td>
                                            <?php echo date('M j, Y', strtotime($assignment['usage_start_date'])); ?>
                                            <?php if ($assignment['usage_end_date']): ?>
                                                - <?php echo date('M j, Y', strtotime($assignment['usage_end_date'])); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-history"></i> Recent Activity
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get recent usage logs for this assignment
                            try {
                                $recentActivity = $usageLogModel->getRecentByAssignment($assignment['id'], 5);
                            } catch (Exception $e) {
                                $recentActivity = [];
                            }
                            ?>
                            
                            <?php if (empty($recentActivity)): ?>
                                <p class="text-muted">No recent activity.</p>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="mb-2 pb-2 border-bottom">
                                        <small class="text-muted">
                                            <?php echo date('M j, Y H:i', strtotime($activity['logged_at'])); ?>
                                        </small>
                                        <div>
                                            <span class="badge badge-secondary badge-sm">
                                                <?php echo ucfirst(str_replace('_', ' ', $activity['action'])); ?>
                                            </span>
                                        </div>
                                        <?php if ($activity['description']): ?>
                                            <small class="text-muted d-block">
                                                <?php echo htmlspecialchars($activity['description']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const assigneeTypeSelect = document.getElementById('assignee_type');
    const assigneeSelect = document.getElementById('assignee_id');
    
    // User and team data from PHP
    const users = <?php echo json_encode($users); ?>;
    const teams = <?php echo json_encode($teams); ?>;
    const currentAssigneeId = <?php echo $assignment['assignee_id']; ?>;
    const currentAssigneeType = '<?php echo $assignment['assignee_type']; ?>';
    
    function updateAssigneeOptions() {
        const selectedType = assigneeTypeSelect.value;
        
        // Clear existing options
        assigneeSelect.innerHTML = '<option value="">Select Assignee</option>';
        
        if (selectedType === 'user') {
            users.forEach(function(user) {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = user.name;
                if (currentAssigneeType === 'user' && user.id == currentAssigneeId) {
                    option.selected = true;
                }
                assigneeSelect.appendChild(option);
            });
        } else if (selectedType === 'team') {
            teams.forEach(function(team) {
                const option = document.createElement('option');
                option.value = team.id;
                option.textContent = team.name;
                if (currentAssigneeType === 'team' && team.id == currentAssigneeId) {
                    option.selected = true;
                }
                assigneeSelect.appendChild(option);
            });
        }
    }
    
    // Initialize assignee options on page load
    updateAssigneeOptions();
    
    // Update assignee options when type changes
    assigneeTypeSelect.addEventListener('change', updateAssigneeOptions);
    
    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const startDate = document.getElementById('usage_start_date').value;
        const endDate = document.getElementById('usage_end_date').value;
        
        if (startDate && endDate && new Date(endDate) < new Date(startDate)) {
            e.preventDefault();
            alert('End date cannot be earlier than start date.');
            return false;
        }
    });
});
</script>

<?php include '../../components/footer.php'; ?>
