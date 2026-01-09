<?php
/**
 * Inventory Module - View Subscription Type
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

$page_title = "View Subscription Type";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/SubscriptionType.php';
require_once $root_dir . '/modules/inventory/models/Item.php';

// Initialize models
$subscriptionTypeModel = new InventorySubscriptionType($db);
$itemModel = new InventoryItem($db);

// Get subscription type ID
$typeId = (int)($_GET['id'] ?? 0);
if (!$typeId) {
    header('Location: subscription-types.php?error=invalid_type');
    exit;
}

// Get subscription type data
$type = $subscriptionTypeModel->getById($typeId);
if (!$type) {
    header('Location: subscription-types.php?error=type_not_found');
    exit;
}

// Get items of this subscription type
$items = $itemModel->getBySubscriptionType($typeId);

// Get statistics
$stats = [
    'total_items' => count($items),
    'active_items' => count(array_filter($items, function($item) { return $item['is_active']; })),
    'total_value' => array_sum(array_column($items, 'monthly_cost')),
    'annual_value' => array_sum(array_column($items, 'annual_cost'))
];

// Check permissions
$canEdit = has_permission('edit_inventory');
$canDelete = has_permission('delete_inventory');
$canRemoveAssignments = has_permission('edit_inventory');

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-eye"></i> View Subscription Type
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item"><a href="subscription-types.php">Subscription Types</a></li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($type['name']); ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            
            <div class="row">
                <!-- Type Details -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header" style="background-color: <?php echo htmlspecialchars($type['color'] ?? '#007bff'); ?>; color: white;">
                            <h3 class="card-title">
                                <i class="<?php echo htmlspecialchars($type['icon'] ?? 'fas fa-cube'); ?>"></i>
                                <?php echo htmlspecialchars($type['name']); ?>
                            </h3>
                            <div class="card-tools">
                                <span class="badge badge-<?php echo $type['is_active'] ? 'light' : 'dark'; ?>">
                                    <?php echo $type['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if ($type['description']): ?>
                            <p class="text-muted mb-3">
                                <?php echo htmlspecialchars($type['description']); ?>
                            </p>
                            <?php endif; ?>
                            
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Items</span>
                                        <span class="info-box-number text-primary">
                                            <?php echo $stats['total_items']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Active Items</span>
                                        <span class="info-box-number text-success">
                                            <?php echo $stats['active_items']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Monthly Value</span>
                                        <span class="info-box-number text-info">
                                            $<?php echo number_format($stats['total_value'], 2); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Annual Value</span>
                                        <span class="info-box-number text-warning">
                                            $<?php echo number_format($stats['annual_value'], 2); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-3">
                                <strong>Category:</strong> 
                                <span class="badge badge-secondary"><?php echo ucfirst($type['category'] ?? 'General'); ?></span>
                            </div>
                            
                            <?php if ($type['billing_cycle']): ?>
                            <div class="mb-3">
                                <strong>Default Billing:</strong> 
                                <span class="badge badge-info"><?php echo ucfirst($type['billing_cycle']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($type['features']): ?>
                            <div class="mb-3">
                                <strong>Features:</strong>
                                <ul class="list-unstyled mt-2">
                                    <?php 
                                    $features = explode("\n", $type['features']);
                                    foreach ($features as $feature): 
                                        $feature = trim($feature);
                                        if ($feature):
                                    ?>
                                    <li><i class="fas fa-check text-success mr-2"></i><?php echo htmlspecialchars($feature); ?></li>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-calendar"></i> 
                                    Created <?php echo date('M j, Y', strtotime($type['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="row">
                                <?php if ($canEdit): ?>
                                <div class="col-sm-6">
                                    <a href="subscription-type-edit.php?id=<?php echo $type['id']; ?>" 
                                       class="btn btn-primary btn-sm btn-block">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </div>
                                <?php endif; ?>
                                <div class="col-sm-6">
                                    <a href="subscription-types.php" class="btn btn-secondary btn-sm btn-block">
                                        <i class="fas fa-arrow-left"></i> Back
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Items List -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-list"></i> Items in this Subscription Type
                                <span class="badge badge-primary"><?php echo $stats['total_items']; ?> items</span>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($items)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No items found</h4>
                                <p class="text-muted">This subscription type doesn't have any items yet.</p>
                                <a href="item-add.php?subscription_type=<?php echo $type['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add First Item
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Code</th>
                                            <th>Monthly Cost</th>
                                            <th>Status</th>
                                            <th>Assignments</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                    <?php if ($item['description']): ?>
                                                    <small class="text-muted ml-2">
                                                        - <?php echo htmlspecialchars(substr($item['description'], 0, 50)); ?>
                                                        <?php echo strlen($item['description']) > 50 ? '...' : ''; ?>
                                                    </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($item['code']); ?></code>
                                            </td>
                                            <td>
                                                <span class="font-weight-bold text-success">
                                                    $<?php echo number_format($item['monthly_cost'], 2); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $item['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                // Get assignment count for this item
                                                $assignmentCount = $itemModel->getAssignmentCount($item['id']);
                                                ?>
                                                <?php if ($assignmentCount > 0): ?>
                                                <span class="badge badge-info">
                                                    <?php echo $assignmentCount; ?> assigned
                                                </span>
                                                <?php else: ?>
                                                <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="item-view.php?id=<?php echo $item['id']; ?>" 
                                                       class="btn btn-outline-info" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($canEdit): ?>
                                                    <a href="item-edit.php?id=<?php echo $item['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <?php if ($canRemoveAssignments && $assignmentCount > 0): ?>
                                                    <button type="button" class="btn btn-outline-warning" 
                                                            onclick="removeAllAssignments(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>')" 
                                                            title="Remove All Assignments">
                                                        <i class="fas fa-unlink"></i>
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

<!-- Remove All Assignments Modal -->
<div class="modal fade" id="removeAssignmentsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Remove All Assignments</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove all assignments for this item?</p>
                <p><strong id="removeItemName"></strong></p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    This action will unassign the item from all users and teams. This cannot be undone.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="removeAssignmentsForm" method="POST" action="remove-all-assignments.php" style="display: inline;">
                    <input type="hidden" id="removeItemId" name="item_id">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-unlink"></i> Remove All Assignments
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize DataTable
$(document).ready(function() {
    if ($('#itemsTable').length) {
        $('#itemsTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'asc']],
            columnDefs: [
                { orderable: false, targets: [-1] }
            ]
        });
    }
});

// Remove all assignments function
function removeAllAssignments(itemId, itemName) {
    $('#removeItemId').val(itemId);
    $('#removeItemName').text(itemName);
    $('#removeAssignmentsModal').modal('show');
}
</script>

<?php include '../../components/footer.php'; ?>
