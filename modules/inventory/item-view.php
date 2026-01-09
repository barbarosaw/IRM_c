<?php
/**
 * Inventory Module - View Item Details
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

$page_title = "View Inventory Item";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/InventoryItem.php';
require_once $root_dir . '/modules/inventory/models/Assignment.php';
require_once $root_dir . '/modules/inventory/models/UsageLog.php';
require_once $root_dir . '/modules/inventory/models/Team.php';

// Initialize models
$inventoryModel = new InventoryItem($db);
$assignmentModel = new InventoryAssignment($db);
$usageLogModel = new InventoryUsageLog($db);
$teamModel = new InventoryTeam($db);

// Get item ID from URL
$itemId = (int)($_GET['id'] ?? 0);

if (!$itemId) {
    $_SESSION['error_message'] = "Invalid item ID.";
    header('Location: items.php');
    exit;
}

// Get item details
$canViewLicenseKeys = has_permission('view_license_keys');
$itemData = $inventoryModel->getById($itemId, $canViewLicenseKeys);

if (!$itemData) {
    $_SESSION['error_message'] = "Inventory item not found.";
    header('Location: items.php');
    exit;
}

// Get assignments and usage logs
$assignments = $assignmentModel->getByItemId($itemId);
$usageLogs = $usageLogModel->getByItemId($itemId, 20); // Last 20 logs

// Check assignment capacity
$userAssignments = array_filter($assignments, function($a) {
    return $a['assignee_type'] === 'user';
});
$teamAssignments = array_filter($assignments, function($a) {
    return $a['assignee_type'] === 'team';
});

$userCapacityFull = ($itemData['max_users'] > 0 && count($userAssignments) >= $itemData['max_users']);
$teamCapacityFull = ($itemData['max_teams'] > 0 && count($teamAssignments) >= $itemData['max_teams']);
$allCapacityFull = $userCapacityFull && $teamCapacityFull;

// Check permissions
$canEdit = has_permission('edit_inventory');
$canDelete = has_permission('delete_inventory');
$canAssign = has_permission('create_inventory_assignment');

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-eye"></i> View Inventory Item
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item"><a href="items.php">Items</a></li>
                        <li class="breadcrumb-item active">View</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            
            <div class="row">
                
                <!-- Item Details -->
                <div class="col-md-8">
                    
                    <!-- Basic Information -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-info-circle"></i> Basic Information
                            </h3>
                            <div class="card-tools">
                                <?php if ($canEdit): ?>
                                <a href="item-edit.php?id=<?php echo $itemData['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deleteItem(<?php echo $itemData['id']; ?>, '<?php echo htmlspecialchars($itemData['name']); ?>')">
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
                                            <td>
                                                <strong><?php echo htmlspecialchars($itemData['name']); ?></strong>
                                                <?php if ($itemData['expiry_warning']): ?>
                                                    <i class="fas fa-exclamation-triangle text-warning ml-1" 
                                                       title="Expiring soon"></i>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Code:</th>
                                            <td><code><?php echo htmlspecialchars($itemData['code']); ?></code></td>
                                        </tr>
                                        <tr>
                                            <th>Type:</th>
                                            <td>
                                                <span class="badge" style="background-color: <?php echo $itemData['subscription_type_color']; ?>;">
                                                    <i class="fas <?php echo $itemData['subscription_type_icon']; ?>"></i>
                                                    <?php echo htmlspecialchars($itemData['subscription_type_name']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Status:</th>
                                            <td>
                                                <?php 
                                                $statusClass = [
                                                    'active' => 'success',
                                                    'inactive' => 'secondary',
                                                    'expired' => 'danger',
                                                    'cancelled' => 'warning'
                                                ][$itemData['status']] ?? 'light';
                                                ?>
                                                <span class="badge badge-<?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($itemData['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="30%">Vendor:</th>
                                            <td><?php echo htmlspecialchars($itemData['vendor_name'] ?: 'Not specified'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Contact:</th>
                                            <td><?php echo htmlspecialchars($itemData['vendor_contact'] ?: 'Not specified'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Created:</th>
                                            <td><?php echo date('M j, Y g:i A', strtotime($itemData['created_at'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Created By:</th>
                                            <td><?php echo htmlspecialchars($itemData['created_by_name'] ?? 'Unknown'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <?php if ($itemData['description']): ?>
                            <div class="mt-3">
                                <h6>Description:</h6>
                                <p><?php echo nl2br(htmlspecialchars($itemData['description'])); ?></p>
                            </div>
                            <?php endif; ?>                        </div>
                    </div>

                    <!-- Capacity Information -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-bar"></i> Capacity & Utilization
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>User Capacity</h6>
                                    <?php 
                                    $userUtilization = $itemData['max_users'] > 0 ? ($itemData['current_users'] / $itemData['max_users'] * 100) : 0;
                                    $userUtilizationClass = $userUtilization >= 100 ? 'danger' : ($userUtilization >= 80 ? 'warning' : 'success');
                                    ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span><?php echo $itemData['current_users']; ?> / <?php echo $itemData['max_users'] > 0 ? $itemData['max_users'] : '∞'; ?> users</span>
                                            <span><?php echo $itemData['max_users'] > 0 ? number_format($userUtilization, 1) . '%' : 'Unlimited'; ?></span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-<?php echo $userUtilizationClass; ?>" 
                                                 style="width: <?php echo $itemData['max_users'] > 0 ? min($userUtilization, 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Team Capacity</h6>
                                    <?php 
                                    $teamUtilization = $itemData['max_teams'] > 0 ? ($itemData['current_teams'] / $itemData['max_teams'] * 100) : 0;
                                    $teamUtilizationClass = $teamUtilization >= 100 ? 'danger' : ($teamUtilization >= 80 ? 'warning' : 'success');
                                    ?>
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span><?php echo $itemData['current_teams']; ?> / <?php echo $itemData['max_teams'] > 0 ? $itemData['max_teams'] : '∞'; ?> teams</span>
                                            <span><?php echo $itemData['max_teams'] > 0 ? number_format($teamUtilization, 1) . '%' : 'Unlimited'; ?></span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar bg-<?php echo $teamUtilizationClass; ?>" 
                                                 style="width: <?php echo $itemData['max_teams'] > 0 ? min($teamUtilization, 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Assignments -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users"></i> Current Assignments
                                <span class="badge badge-primary"><?php echo count($assignments); ?>
</span>
                            </h3>
                            <div class="card-tools">
                                <?php if ($canAssign && !$allCapacityFull): ?>
                                <button type="button" class="btn btn-sm btn-success mr-2" data-bs-toggle="modal" data-bs-target="#assignmentModal">
                                    <i class="fas fa-plus"></i> New Assignment
                                </button>
                                <?php elseif ($allCapacityFull): ?>
                                <button type="button" class="btn btn-sm btn-secondary mr-2" disabled title="All capacity is full">
                                    <i class="fas fa-ban"></i> Capacity Full
                                </button>
                                <?php endif; ?>
                                <a href="assignments.php?item_id=<?php echo $itemData['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> View All
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($assignments)): ?>
                                <p class="text-muted text-center py-3">No active assignments</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Assigned To</th>
                                                <th>Type</th>
                                                <th>Assigned Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($assignments, 0, 10) as $assignment): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($assignment['assignee_type'] === 'user'): ?>
                                                        <i class="fas fa-user text-primary"></i>
                                                        <?php echo htmlspecialchars($assignment['assignee_name'] ?? 'Unknown User'); ?>
                                                    <?php else: ?>
                                                        <i class="fas fa-users text-info"></i>
                                                        <?php echo htmlspecialchars($assignment['assignee_name'] ?? 'Unknown Team'); ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?php echo $assignment['assignee_type'] === 'user' ? 'primary' : 'info'; ?>">
                                                        <?php echo ucfirst($assignment['assignee_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($assignment['assigned_date'])); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $assignment['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($assignment['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($canDelete): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="removeAssignment(<?php echo $assignment['id']; ?>, '<?php echo htmlspecialchars($assignment['assignee_name'] ?? 'Unknown'); ?>')"
                                                            title="Remove Assignment">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (count($assignments) > 10): ?>
                                <div class="text-center mt-2">
                                    <a href="assignments.php?item_id=<?php echo $itemData['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        View all <?php echo count($assignments); ?> assignments
                                    </a>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-history"></i> Recent Activity
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($usageLogs)): ?>
                                <p class="text-muted text-center py-3">No recent activity</p>
                            <?php else: ?>
                                <style>
                                .activity-item {
                                    padding: 12px;
                                    border-radius: 8px;
                                    transition: background-color 0.3s ease;
                                }
                                .activity-item:hover {
                                    background-color: #f8f9fa;
                                }
                                .activity-icon i {
                                    font-size: 1.2em;
                                    width: 20px;
                                    text-align: center;
                                }
                                .activity-content h6 {
                                    font-size: 0.95em;
                                    margin-bottom: 4px;
                                }
                                .activity-content p {
                                    font-size: 0.875em;
                                    line-height: 1.4;
                                }
                                .badge {
                                    font-size: 0.7em;
                                    padding: 3px 8px;
                                }
                                </style>
                                <div class="activity-list">
                                    <?php foreach ($usageLogs as $index => $log): ?>
                                    <div class="activity-item d-flex align-items-start mb-3">
                                        <div class="activity-icon mr-3">
                                            <?php
                                            $iconClass = 'fas fa-info-circle text-info';
                                            $badgeClass = 'badge-info';
                                            
                                            if (strpos($log['action'], 'assignment_created') !== false) {
                                                $iconClass = 'fas fa-plus-circle text-success';
                                                $badgeClass = 'badge-success';
                                            } elseif (strpos($log['action'], 'assignment_removed') !== false) {
                                                $iconClass = 'fas fa-minus-circle text-danger';
                                                $badgeClass = 'badge-danger';
                                            } elseif (strpos($log['action'], 'access') !== false) {
                                                $iconClass = 'fas fa-sign-in-alt text-primary';
                                                $badgeClass = 'badge-primary';
                                            } elseif (strpos($log['action'], 'usage') !== false) {
                                                $iconClass = 'fas fa-play-circle text-warning';
                                                $badgeClass = 'badge-warning';
                                            }
                                            ?>
                                            <i class="<?php echo $iconClass; ?>"></i>
                                        </div>
                                        <div class="activity-content flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <strong><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></strong>
                                                        <span class="badge <?php echo $badgeClass; ?> ml-2">
                                                            <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                                        </span>
                                                    </h6>
                                                    <p class="mb-1 text-muted">
                                                        <?php echo htmlspecialchars($log['description']); ?>
                                                    </p>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y g:i A', strtotime($log['logged_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($index < count($usageLogs) - 1): ?>
                                    <hr class="my-2">
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                
                <!-- Sidebar Information -->
                <div class="col-md-4">
                    
                    <!-- Security Info -->
                    <?php if ($canViewLicenseKeys): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-key"></i> Security Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($itemData['license_key']): ?>
                                <div class="form-group">
                                    <label>License Key:</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="licenseKey" 
                                               value="<?php echo htmlspecialchars($itemData['license_key']); ?>" readonly>
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary" type="button" onclick="toggleLicenseKey()">
                                                <i class="fas fa-eye" id="licenseKeyIcon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No license key stored</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Cost Information -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-dollar-sign"></i> Cost Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($itemData['monthly_cost'] || $itemData['annual_cost']): ?>
                                <table class="table table-borderless table-sm">
                                    <?php if ($itemData['monthly_cost']): ?>
                                    <tr>
                                        <th>Monthly:</th>
                                        <td class="text-right">
                                            <?php echo $itemData['currency']; ?> <?php echo number_format($itemData['monthly_cost'], 2); ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($itemData['annual_cost']): ?>
                                    <tr>
                                        <th>Annual:</th>
                                        <td class="text-right">
                                            <?php echo $itemData['currency']; ?> <?php echo number_format($itemData['annual_cost'], 2); ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ($itemData['monthly_cost'] && $itemData['annual_cost']): ?>
                                    <tr class="border-top">
                                        <th>Savings:</th>
                                        <td class="text-right text-success">
                                            <?php 
                                            $monthlyCostAnnualized = $itemData['monthly_cost'] * 12;
                                            $savings = $monthlyCostAnnualized - $itemData['annual_cost'];
                                            echo $itemData['currency']; ?> <?php echo number_format($savings, 2); ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            <?php else: ?>
                                <p class="text-muted">No cost information available</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Important Dates -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-calendar"></i> Important Dates
                            </h3>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless table-sm">
                                <?php if ($itemData['purchase_date']): ?>
                                <tr>
                                    <th>Purchase:</th>
                                    <td><?php echo date('M j, Y', strtotime($itemData['purchase_date'])); ?></td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($itemData['expiry_date']): ?>
                                <tr>
                                    <th>Expiry:</th>
                                    <td>
                                        <?php 
                                        $daysUntilExpiry = ceil((strtotime($itemData['expiry_date']) - time()) / (60 * 60 * 24));
                                        $expiryClass = $daysUntilExpiry <= 7 ? 'text-danger' : ($daysUntilExpiry <= 30 ? 'text-warning' : '');
                                        ?>
                                        <span class="<?php echo $expiryClass; ?>">
                                            <?php echo date('M j, Y', strtotime($itemData['expiry_date'])); ?>
                                            <br><small>(<?php echo $daysUntilExpiry; ?> days)</small>
                                        </span>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                
                                <?php if ($itemData['renewal_date']): ?>
                                <tr>
                                    <th>Renewal:</th>
                                    <td><?php echo date('M j, Y', strtotime($itemData['renewal_date'])); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>

                    <!-- Notes -->
                    <?php if ($itemData['notes']): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-sticky-note"></i> Notes
                            </h3>
                        </div>
                        <div class="card-body">
                            <p><?php echo nl2br(htmlspecialchars($itemData['notes'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
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
                <p>Are you sure you want to delete this inventory item?</p>
                <p><strong id="deleteItemName"></strong></p>
                <p class="text-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    This will also unassign all users and teams from this item.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="item-delete.php" style="display: inline;">
                    <input type="hidden" id="deleteItemId" name="item_id">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle license key visibility
function toggleLicenseKey() {
    const input = document.getElementById('licenseKey');
    const icon = document.getElementById('licenseKeyIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Delete item function
function deleteItem(id, name) {
    $('#deleteItemId').val(id);
    $('#deleteItemName').text(name);
    $('#deleteModal').modal('show');
}
</script>

<!-- Assignment Modal -->
<div class="modal fade" id="assignmentModal" tabindex="-1" aria-labelledby="assignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="assignmentModalLabel">
                    <i class="fas fa-plus"></i> New Assignment
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignmentForm">
                <div class="modal-body">
                    <!-- İlk satır: Inventory Item ve Assignment Date -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="item_id">Inventory Item *</label>
                                <select class="form-control" id="item_id" name="item_id" required disabled>
                                    <option value="<?php echo $itemData['id']; ?>" selected>
                                        <?php echo htmlspecialchars($itemData['name']); ?>
                                        (<?php echo htmlspecialchars($itemData['type'] ?? 'No Type'); ?>)
                                    </option>
                                </select>
                                <!-- Hidden input to send item_id value -->
                                <input type="hidden" name="item_id" value="<?php echo $itemData['id']; ?>">
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
                                    <?php 
                                    $stmt = $db->prepare("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name ASC");
                                    $stmt->execute();
                                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($users as $user): ?>
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
                                    <?php 
                                    $teams = $teamModel->getAllActive();
                                    foreach ($teams as $team): ?>
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
                        <label for="assignment_notes">Notes</label>
                        <textarea class="form-control" id="assignment_notes" name="notes" rows="3" 
                                  placeholder="Optional notes about this assignment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Remove Assignment Modal -->
<div class="modal fade" id="removeAssignmentModal" tabindex="-1" aria-labelledby="removeAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="removeAssignmentModalLabel">
                    <i class="fas fa-exclamation-triangle text-warning"></i> Remove Assignment
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove this assignment?</p>
                <div class="alert alert-warning">
                    <strong>Assignee:</strong> <span id="removeAssignmentName"></span><br>
                    <strong>Item:</strong> <?php echo htmlspecialchars($itemData['name']); ?>
                </div>
                <p class="text-muted">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmRemoveAssignment">
                    <i class="fas fa-trash"></i> Remove Assignment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Modal assignee type change handler
    $('#modal_assignee_type').on('change', function() {
        const assigneeType = $(this).val();
        
        if (assigneeType === 'user') {
            $('#modal_user_select_group').show();
            $('#modal_team_select_group').hide();
            $('#modal_user_id').prop('required', true);
            $('#modal_team_id').prop('required', false);
        } else if (assigneeType === 'team') {
            $('#modal_user_select_group').hide();
            $('#modal_team_select_group').show();
            $('#modal_user_id').prop('required', false);
            $('#modal_team_id').prop('required', true);
        } else {
            $('#modal_user_select_group').hide();
            $('#modal_team_select_group').hide();
            $('#modal_user_id').prop('required', false);
            $('#modal_team_id').prop('required', false);
        }
    });
    
    // Initialize modal on first load
    $('#modal_assignee_type').trigger('change');
    
    // Assignment form submit handler
    $('#assignmentForm').on('submit', function(e) {
        e.preventDefault();
        
        // Create custom form data to only send relevant fields
        const formData = new FormData();
        
        // Always send these fields
        formData.append('item_id', $('#assignmentForm input[name="item_id"]').val());
        formData.append('assigned_date', $('#assigned_date').val());
        formData.append('assignee_type', $('#modal_assignee_type').val());
        formData.append('notes', $('#assignment_notes').val());
        
        // Send only the relevant assignee ID based on type
        const assigneeType = $('#modal_assignee_type').val();
        if (assigneeType === 'user') {
            formData.append('user_id', $('#modal_user_id').val());
        } else if (assigneeType === 'team') {
            formData.append('team_id', $('#modal_team_id').val());
        }
        
        $.ajax({
            url: 'assignment-add.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json', // Explicitly expect JSON
            success: function(data) {
                console.log('Parsed data:', data); // Debug
                
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('assignmentModal'));
                    modal.hide();
                    
                    // Show success message
                    if (data.message) {
                        toastr.success(data.message);
                    } else {
                        toastr.success('Assignment created successfully!');
                    }
                    
                    // Reload page to show new assignment
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    toastr.error(data.message || 'Failed to create assignment');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr, status, error);
                
                // Try to parse error response
                try {
                    const errorData = JSON.parse(xhr.responseText);
                    toastr.error(errorData.message || 'Failed to create assignment');
                } catch (e) {
                    toastr.error('Failed to create assignment: ' + error);
                }
            }
        });
    });
    
    // Reset form when modal is hidden
    document.getElementById('assignmentModal').addEventListener('hidden.bs.modal', function() {
        document.getElementById('assignmentForm').reset();
        $('#modal_assignee_type').trigger('change');
    });
});

// Remove assignment functions
let assignmentToRemove = null;

function removeAssignment(assignmentId, assigneeName) {
    assignmentToRemove = assignmentId;
    $('#removeAssignmentName').text(assigneeName);
    const modal = new bootstrap.Modal(document.getElementById('removeAssignmentModal'));
    modal.show();
}

$('#confirmRemoveAssignment').on('click', function() {
    if (assignmentToRemove) {
        $.ajax({
            url: 'assignment-remove.php',
            type: 'POST',
            data: {
                assignment_id: assignmentToRemove
            },
            dataType: 'json',
            success: function(data) {
                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('removeAssignmentModal'));
                    modal.hide();
                    toastr.success(data.message || 'Assignment removed successfully');
                    
                    // Reload page to update assignments
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    toastr.error(data.message || 'Failed to remove assignment');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', xhr, status, error);
                toastr.error('Failed to remove assignment: ' + error);
            }
        });
    }
});

// Toggle license key visibility
function toggleLicenseKey() {
    const input = document.getElementById('licenseKey');
    const icon = document.getElementById('licenseKeyIcon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Delete item function
function deleteItem(id, name) {
    $('#deleteItemId').val(id);
    $('#deleteItemName').text(name);
    $('#deleteModal').modal('show');
}
</script>

<?php include '../../components/footer.php'; ?>
