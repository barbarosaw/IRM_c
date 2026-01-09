<?php
/**
 * Inventory Module - Items Management
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

$page_title = "Inventory Items";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/InventoryItem.php';
require_once $root_dir . '/modules/inventory/models/SubscriptionType.php';

// Initialize models
$inventoryModel = new InventoryItem($db);
$subscriptionTypeModel = new InventorySubscriptionType($db);

// Get filter parameters
$filter_type = $_GET['type'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Get data
$canViewLicenseKeys = has_permission('view_license_keys');
$items = $inventoryModel->getAll($canViewLicenseKeys);
$subscriptionTypes = $subscriptionTypeModel->getAllActive();

// Apply filters
if ($filter_type) {
    $items = array_filter($items, function($item) use ($filter_type) {
        return $item['subscription_type_id'] == $filter_type;
    });
}

if ($filter_status) {
    $items = array_filter($items, function($item) use ($filter_status) {
        return $item['status'] == $filter_status;
    });
}

if ($search) {
    $items = array_filter($items, function($item) use ($search) {
        return stripos($item['name'], $search) !== false || 
               stripos($item['vendor_name'], $search) !== false ||
               stripos($item['description'], $search) !== false;
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
                        <i class="fas fa-boxes"></i> Inventory Items
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item active">Items</li>
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
                                <div class="col-md-3">
                                    <label for="search" class="form-label">Search</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Search items..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="type" class="form-label">Subscription Type</label>
                                    <select class="form-control" id="type" name="type">
                                        <option value="">All Types</option>
                                        <?php foreach ($subscriptionTypes as $type): ?>
                                        <option value="<?php echo $type['id']; ?>" <?php echo $filter_type == $type['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $filter_status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="expired" <?php echo $filter_status == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                        <option value="cancelled" <?php echo $filter_status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="items.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                                <div class="col-md-2 text-right">
                                    <?php if ($canAdd): ?>
                                    <a href="item-add.php" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Add New Item
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Table -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-list"></i> Items List 
                                <span class="badge badge-primary"><?php echo count($items); ?> items</span>
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($items)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No inventory items found.</p>
                                    <?php if ($canAdd): ?>
                                    <a href="item-add.php" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Add First Item
                                    </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover" id="itemsTable">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Type</th>
                                                <th>Vendor</th>
                                                <th>Capacity</th>
                                                <th>Cost</th>
                                                <th>Status</th>
                                                <th>Expiry</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item): 
                                                $userUtilization = $item['max_users'] > 0 ? ($item['current_users'] / $item['max_users'] * 100) : 0;
                                                $teamUtilization = $item['max_teams'] > 0 ? ($item['current_teams'] / $item['max_teams'] * 100) : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                        <?php if ($item['expiry_warning']): ?>
                                                            <i class="fas fa-exclamation-triangle text-warning ml-1" 
                                                               title="Expiring soon"></i>
                                                        <?php endif; ?>
                                                        <?php if ($item['license_key'] && $canViewLicenseKeys): ?>
                                                            <i class="fas fa-key text-info ml-1" title="Has license key"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($item['code']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background-color: <?php echo $item['subscription_type_color']; ?>;">
                                                        <i class="fas <?php echo $item['subscription_type_icon']; ?>"></i>
                                                        <?php echo htmlspecialchars($item['subscription_type_name']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['vendor_name'] ?: '-'); ?></td>
                                                <td>
                                                    <div class="mb-1">
                                                        <small>Users: <?php echo $item['current_users']; ?>/<?php echo $item['max_users']; ?></small>
                                                        <div class="progress" style="height: 3px;">
                                                            <div class="progress-bar <?php echo $userUtilization >= 100 ? 'bg-danger' : ($userUtilization >= 80 ? 'bg-warning' : 'bg-success'); ?>" 
                                                                 style="width: <?php echo min($userUtilization, 100); ?>%"></div>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <small>Teams: <?php echo $item['current_teams']; ?>/<?php echo $item['max_teams']; ?></small>
                                                        <div class="progress" style="height: 3px;">
                                                            <div class="progress-bar <?php echo $teamUtilization >= 100 ? 'bg-danger' : ($teamUtilization >= 80 ? 'bg-warning' : 'bg-success'); ?>" 
                                                                 style="width: <?php echo min($teamUtilization, 100); ?>%"></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($item['monthly_cost']): ?>
                                                        <div><?php echo $item['currency']; ?> <?php echo number_format($item['monthly_cost'], 2); ?>/mo</div>
                                                    <?php endif; ?>
                                                    <?php if ($item['annual_cost']): ?>
                                                        <small class="text-muted"><?php echo $item['currency']; ?> <?php echo number_format($item['annual_cost'], 2); ?>/yr</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $statusBadge = [
                                                        'active' => 'badge-success',
                                                        'inactive' => 'badge-secondary', 
                                                        'expired' => 'badge-danger',
                                                        'cancelled' => 'badge-warning',
                                                        'pending' => 'badge-info'
                                                    ][$item['status']] ?? 'badge-light';
                                                    ?>
                                                    <span class="badge <?php echo $statusBadge; ?>">
                                                        <?php echo ucfirst($item['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($item['expiry_date']): ?>
                                                        <?php 
                                                        $daysUntilExpiry = ceil((strtotime($item['expiry_date']) - time()) / (60 * 60 * 24));
                                                        $expiryClass = $daysUntilExpiry <= 7 ? 'text-danger' : ($daysUntilExpiry <= 30 ? 'text-warning' : 'text-muted');
                                                        ?>
                                                        <span class="<?php echo $expiryClass; ?>">
                                                            <?php echo date('M j, Y', strtotime($item['expiry_date'])); ?>
                                                        </span>
                                                        <br><small class="<?php echo $expiryClass; ?>">
                                                            (<?php echo $daysUntilExpiry; ?> days)
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="text-muted">No expiry</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="item-view.php?id=<?php echo $item['id']; ?>" 
                                                           class="btn btn-outline-info" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($canEdit): ?>
                                                        <a href="item-edit.php?id=<?php echo $item['id']; ?>" 
                                                           class="btn btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                        <?php if ($canDelete): ?>
                                                        <button type="button" class="btn btn-outline-danger" 
                                                                onclick="deleteItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>')" 
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
                <button type="button" class="close" data-bs-dismiss="modal">&times;</button>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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
// Initialize DataTable
$(document).ready(function() {
    $('#itemsTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [-1] } // Disable sorting on actions column
        ]
    });
});

// Delete item function
function deleteItem(id, name) {
    $('#deleteItemId').val(id);
    $('#deleteItemName').text(name);
    $('#deleteModal').modal('show');
}
</script>

<?php include '../../components/footer.php'; ?>
