<?php
/**
 * Inventory Module - Assignment View
 * 
 * @author System Generated
 */


// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory permissions
if (!has_permission('view_inventory')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$page_title = "Assignment Details";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/Assignment.php';
require_once $root_dir . '/modules/inventory/models/InventoryItem.php';
require_once $root_dir . '/modules/inventory/models/SubscriptionType.php';
require_once $root_dir . '/modules/inventory/models/Team.php';
require_once $root_dir . '/modules/inventory/models/UsageLog.php';

// Initialize models
$assignmentModel = new InventoryAssignment($db);
$inventoryModel = new InventoryItem($db);
$subscriptionTypeModel = new InventorySubscriptionType($db);
$teamModel = new InventoryTeam($db);
$usageLogModel = new InventoryUsageLog($db);

// Get assignment ID
$assignment_id = (int)($_GET['id'] ?? 0);
if (!$assignment_id) {
    header('Location: assignments.php');
    exit;
}

// Get assignment data
$assignment = $assignmentModel->getById($assignment_id);
if (!$assignment) {
    $_SESSION['error_message'] = 'Assignment not found.';
    header('Location: assignments.php');
    exit;
}

// Get additional item details from assignment data (already included)
$item = $assignment; // assignment modelinden tüm item bilgileri geliyor
$subscriptionType = $assignment; // subscription type bilgileri de dahil

// Get usage logs for this assignment
$usageLogs = [];
try {
    $stmt = $db->prepare("
        SELECT ul.*, u.name as user_name
        FROM inventory_usage_logs ul
        LEFT JOIN users u ON ul.user_id = u.id
        WHERE ul.item_id = ? 
        AND (
            (? = 'user' AND ul.user_id = ?) OR
            (? = 'team' AND ul.user_id IN (
                SELECT user_id FROM inventory_team_members WHERE team_id = ?
            ))
        )
        ORDER BY ul.logged_at DESC 
        LIMIT 20
    ");
    $stmt->execute([
        $assignment['item_id'], 
        $assignment['assignee_type'], $assignment['assignee_id'],
        $assignment['assignee_type'], $assignment['assignee_id']
    ]);
    $usageLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If usage logs fail, continue without them
    $usageLogs = [];
}

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
                        <i class="fas fa-eye"></i> Assignment Details
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item"><a href="assignments.php">Assignments</a></li>
                        <li class="breadcrumb-item active">View</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            
            <!-- Action Buttons -->
            <div class="row mb-3">
                <div class="col-md-12">
                    <a href="assignments.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Assignments
                    </a>
                    
                    <?php if ($canEdit): ?>
                    <a href="assignment-edit.php?id=<?php echo $assignment['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Assignment
                    </a>
                    <?php endif; ?>
                    
                    <a href="item-view.php?id=<?php echo $assignment['item_id']; ?>" class="btn btn-info">
                        <i class="fas fa-box"></i> View Item Details
                    </a>
                    
                    <?php if ($assignment['assignee_type'] === 'team'): ?>
                    <a href="team-view.php?id=<?php echo $assignment['assignee_id']; ?>" class="btn btn-success">
                        <i class="fas fa-users"></i> View Team Details
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <!-- Assignment Details -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-info-circle"></i> Assignment Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="font-weight-bold">Assignment ID:</td>
                                            <td><?php echo $assignment['id']; ?></td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">Item:</td>
                                            <td>
                                                <a href="item-view.php?id=<?php echo $assignment['item_id']; ?>">
                                                    <?php echo htmlspecialchars($assignment['item_name']); ?>
                                                </a>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($assignment['item_code']); ?></small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">Subscription Type:</td>
                                            <td>
                                                <span class="badge text-white" 
                                                      style="background-color: <?php echo $assignment['subscription_type_color']; ?>;">
                                                    <i class="<?php echo $assignment['subscription_type_icon']; ?>"></i>
                                                    <?php echo htmlspecialchars($assignment['subscription_type_name']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">Assignee Type:</td>
                                            <td>
                                                <?php if ($assignment['assignee_type'] === 'user'): ?>
                                                    <span class="badge badge-primary">
                                                        <i class="fas fa-user"></i> User
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-success">
                                                        <i class="fas fa-users"></i> Team
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">Assignee:</td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($assignment['assignee_name']); ?></strong>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="font-weight-bold">Status:</td>
                                            <td>
                                                <?php
                                                $statusLabels = [
                                                    'active' => '<span class="badge badge-success">Active</span>',
                                                    'inactive' => '<span class="badge badge-secondary">Inactive</span>',
                                                    'suspended' => '<span class="badge badge-warning">Suspended</span>',
                                                    'expired' => '<span class="badge badge-danger">Expired</span>'
                                                ];
                                                echo $statusLabels[$assignment['status']] ?? '<span class="badge badge-light">' . ucfirst($assignment['status']) . '</span>';
                                                ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">Assigned Date:</td>
                                            <td><?php echo date('M j, Y H:i', strtotime($assignment['assigned_at'])); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">Assigned By:</td>
                                            <td><?php echo htmlspecialchars($assignment['assigned_by_name']); ?></td>
                                        </tr>
                                        <?php if ($assignment['unassigned_at']): ?>
                                        <tr>
                                            <td class="font-weight-bold">Unassigned Date:</td>
                                            <td><?php echo date('M j, Y H:i', strtotime($assignment['unassigned_at'])); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="font-weight-bold">Unassigned By:</td>
                                            <td><?php echo htmlspecialchars($assignment['unassigned_by_name']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td class="font-weight-bold">License Info:</td>
                                            <td>
                                                <?php if (!empty($assignment['license_key'])): ?>
                                                    <span class="badge badge-info">
                                                        <i class="fas fa-key"></i> License Provided
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-light">No License</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <?php if (!empty($assignment['notes'])): ?>
                            <div class="mt-3">
                                <h6 class="font-weight-bold">Notes:</h6>
                                <div class="bg-light p-3 rounded">
                                    <?php echo nl2br(htmlspecialchars($assignment['notes'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Usage Logs -->
                    <?php if (!empty($usageLogs)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-history"></i> Recent Usage Activity
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Duration</th>
                                            <th>Date & Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usageLogs as $log): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                                            <td>
                                                <?php
                                                $actionLabels = [
                                                    'login' => '<span class="badge badge-success">Login</span>',
                                                    'logout' => '<span class="badge badge-secondary">Logout</span>',
                                                    'access' => '<span class="badge badge-info">Access</span>',
                                                    'usage_start' => '<span class="badge badge-primary">Started</span>',
                                                    'usage_end' => '<span class="badge badge-warning">Ended</span>',
                                                    'license_activated' => '<span class="badge badge-success">Activated</span>',
                                                    'license_deactivated' => '<span class="badge badge-danger">Deactivated</span>'
                                                ];
                                                echo $actionLabels[$log['action']] ?? '<span class="badge badge-light">' . ucfirst($log['action']) . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php echo $log['usage_duration_minutes'] ? $log['usage_duration_minutes'] . ' min' : '-'; ?>
                                            </td>
                                            <td>
                                                <small><?php echo date('M j, Y H:i', strtotime($log['logged_at'])); ?></small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="usage-logs.php?item_id=<?php echo $assignment['item_id']; ?>" class="btn btn-sm btn-outline-primary">
                                    View All Usage Logs <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Item Details Card -->
                <div class="col-md-4">
                    

                    <!-- Quick Stats -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-bar"></i> Quick Stats
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get assignment stats
                            $stats = [];
                            try {
                                // Total usage logs count
                                $stmt = $db->prepare("SELECT COUNT(*) FROM inventory_usage_logs WHERE item_id = ?");
                                $stmt->execute([$assignment['item_id']]);
                                $stats['total_usage'] = $stmt->fetchColumn();

                                // Days since assignment
                                $stats['days_assigned'] = ceil((time() - strtotime($assignment['assigned_at'])) / (60 * 60 * 24));

                                // Active users (for team assignments)
                                if ($assignment['assignee_type'] === 'team') {
                                    $stmt = $db->prepare("SELECT COUNT(*) FROM inventory_team_members WHERE team_id = ?");
                                    $stmt->execute([$assignment['assignee_id']]);
                                    $stats['team_members'] = $stmt->fetchColumn();
                                }
                            } catch (Exception $e) {
                                // If stats fail, continue without them
                            }
                            ?>
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Days Assigned</span>
                                        <span class="info-box-number"><?php echo $stats['days_assigned'] ?? 0; ?></span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Usage</span>
                                        <span class="info-box-number"><?php echo $stats['total_usage'] ?? 0; ?></span>
                                    </div>
                                </div>
                                <?php if (isset($stats['team_members'])): ?>
                                <div class="col-12 mt-2">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Team Members</span>
                                        <span class="info-box-number"><?php echo $stats['team_members']; ?></span>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include '../../components/footer.php'; ?>
