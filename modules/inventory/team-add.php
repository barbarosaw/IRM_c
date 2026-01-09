<?php
/**
 * Inventory Module - Add New Team
 * 
 * @author System Generated
 */


// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory add permission
if (!has_permission('add_inventory')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->
prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$page_title = "Add Inventory Team";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/Team.php';

// Initialize models
$teamModel = new InventoryTeam($db);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'code' => trim($_POST['code'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'department' => trim($_POST['department'] ?? ''),
        'manager_id' => (int)($_POST['manager_id'] ?? 0) ?: null,
        'status' => $_POST['status'] ?? 'active'
    ];
    
    // Validation
    if (empty($data['name'])) {
        $errors[] = "Team name is required.";
    }
    
    if (empty($data['code'])) {
        $errors[] = "Team code is required.";
    } else {
        // Check if code already exists
        if ($teamModel->codeExists($data['code'])) {
            $errors[] = "Team code already exists.";
        }
    }
    
    if (empty($errors)) {
        $result = $teamModel->create($data);
        if ($result) {
            $_SESSION['success_message'] = "Team '{$data['name']}' has been created successfully.";
            header('Location: teams.php');
            exit;
        } else {
            $errors[] = "Failed to create team. Please try again.";
        }
    }
}

// Get users for manager dropdown
$stmt = $db->prepare("SELECT id, name, email FROM users WHERE is_active = 1 ORDER BY name ASC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-plus-circle"></i> Add New Team
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item"><a href="teams.php">Teams</a></li>
                        <li class="breadcrumb-item active">Add New</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <h5><i class="icon fas fa-ban"></i> Error!</h5>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>                    <li><?php echo htmlspecialchars($error); ?>
</li>
                    <?php endforeach; ?>                </ul>
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
                                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>
" 
                                                   placeholder="e.g., IT Support Team" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="code">Team Code <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="code" name="code" 
                                                   value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>
" 
                                                   placeholder="e.g., IT-SUPPORT" required>
                                            <small class="form-text text-muted">Unique identifier for this team</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" 
                                              placeholder="Brief description of the team's role and responsibilities"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?>
</textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="department">Department</label>
                                            <input type="text" class="form-control" id="department" name="department" 
                                                   value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>
" 
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
                                                <option value="active" <?php echo (($_POST['status'] ?? 'active') == 'active') ? 'selected' : ''; ?>
>Active</option>
                                                <option value="inactive" <?php echo (($_POST['status'] ?? '') == 'inactive') ? 'selected' : ''; ?>
>Inactive</option>
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
                                <div class="form-group">
                                    <label for="manager_id">Team Manager</label>
                                    <select class="form-control select2" id="manager_id" name="manager_id">
                                        <option value="">Select team manager (optional)</option>
                                        <?php foreach ($users as $user): ?>                                        <option value="<?php echo $user['id']; ?>
" 
                                                <?php echo (($_POST['manager_id'] ?? '') == $user['id']) ? 'selected' : ''; ?>
>
                                            <?php echo htmlspecialchars($user['name']); ?>
 (<?php echo htmlspecialchars($user['email']); ?>
)
                                        </option>
                                        <?php endforeach; ?>                                    </select>
                                    <small class="form-text text-muted">
                                        The team manager will have administrative rights for this team's inventory assignments.
                                    </small>
                                </div>
                                
                                <div class="callout callout-info">
                                    <h5><i class="fas fa-info-circle"></i> Note:</h5>
                                    <p>After creating the team, you can add team members on the team details page.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar Information -->
                    <div class="col-md-4">
                        
                        <!-- Help Information -->
                        <div class="card card-info">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-question-circle"></i> Help
                                </h3>
                            </div>
                            <div class="card-body">
                                <h6>Team Code Guidelines:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success"></i> Use uppercase letters</li>
                                    <li><i class="fas fa-check text-success"></i> Use hyphens for spaces</li>
                                    <li><i class="fas fa-check text-success"></i> Keep it short and meaningful</li>
                                    <li><i class="fas fa-check text-success"></i> Must be unique</li>
                                </ul>
                                
                                <h6 class="mt-3">Team Benefits:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-users text-primary"></i> Group inventory assignments</li>
                                    <li><i class="fas fa-chart-line text-primary"></i> Team usage tracking</li>
                                    <li><i class="fas fa-shield-alt text-primary"></i> Centralized access control</li>
                                    <li><i class="fas fa-bell text-primary"></i> Team notifications</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Quick Stats -->
                        <div class="card card-success">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chart-pie"></i> Current Stats
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php
                                // Get current team stats
                                $stmt = $db->
prepare("SELECT COUNT(*) FROM inventory_teams WHERE is_active = 1");
                                $stmt->execute();
                                $totalTeams = $stmt->fetchColumn();
                                
                                $stmt = $db->prepare("SELECT COUNT(DISTINCT team_id) FROM inventory_team_members WHERE is_active = 1");
                                $stmt->execute();
                                $teamsWithMembers = $stmt->fetchColumn();
                                
                                $stmt = $db->prepare("SELECT COUNT(DISTINCT assignee_id) FROM inventory_assignments WHERE assignee_type = 'team' AND status = 'active'");
                                $stmt->execute();
                                $teamsWithAssignments = $stmt->fetchColumn();
                                ?>
                                <div class="row">
                                    <div class="col-6 text-center">
                                        <div class="info-box-content">
                                            <span class="info-box-text">Total Teams</span>
                                            <span class="info-box-number text-success"><?php echo $totalTeams; ?>
</span>
                                        </div>
                                    </div>
                                    <div class="col-6 text-center">
                                        <div class="info-box-content">
                                            <span class="info-box-text">With Members</span>
                                            <span class="info-box-number text-info"><?php echo $teamsWithMembers; ?>
</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-12 text-center">
                                        <div class="info-box-content">
                                            <span class="info-box-text">With Assignments</span>
                                            <span class="info-box-number text-primary"><?php echo $teamsWithAssignments; ?>
</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <a href="teams.php" class="btn btn-secondary btn-block">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <button type="submit" class="btn btn-success btn-block">
                                            <i class="fas fa-save"></i> Create Team
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

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        placeholder: 'Select team manager',
        allowClear: true
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
});
</script>

<?php include '../../components/footer.php'; ?>