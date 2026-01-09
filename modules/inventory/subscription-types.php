<?php
/**
 * Inventory Module - Subscription Types List
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

$page_title = "Subscription Types";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/SubscriptionType.php';

// Initialize models
$subscriptionTypeModel = new InventorySubscriptionType($db);

// Get filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

// Get data
$subscriptionTypes = $subscriptionTypeModel->getAllWithStats();

// Apply filters
if ($search) {
    $subscriptionTypes = array_filter($subscriptionTypes, function($type) use ($search) {
        return stripos($type['name'], $search) !== false || 
               stripos($type['description'], $search) !== false;
    });
}

if ($status) {
    $subscriptionTypes = array_filter($subscriptionTypes, function($type) use ($status) {
        return $type['is_active'] == ($status === 'active' ? 1 : 0);
    });
}

// Calculate totals for display
$totalItems = array_sum(array_column($subscriptionTypes, 'items_count'));
$totalActiveItems = array_sum(array_column($subscriptionTypes, 'active_items_count'));
$totalInactiveItems = $totalItems - $totalActiveItems;

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
                        <i class="fas fa-tags"></i> Subscription Types
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item active">Subscription Types</li>
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
                                           placeholder="Search subscription types..." value="<?php echo htmlspecialchars($search); ?>
">
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>
>Active</option>
                                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>
>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="subscription-types.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                                <div class="col-md-2 text-right">
                                    <?php if ($canAdd): ?>                                    <a href="subscription-type-add.php" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Add New Type
                                    </a>
                                    <?php endif; ?>                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Show message if no subscription types found -->
            <?php if (empty($subscriptionTypes)): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-tags fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No subscription types found</h4>
                                <p class="text-muted">Create your first subscription type to categorize inventory items.</p>
                                <?php if ($canAdd): ?>
                                <a href="subscription-type-add.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Create First Type
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Subscription Types Overview -->
            <?php if (!empty($subscriptionTypes)): ?>
            <div class="row mt-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-table"></i> Subscription Types Overview
                                <span class="badge badge-primary"><?php echo count($subscriptionTypes); ?> types</span>
                            </h3>
                            <div class="card-tools">
                                <span class="badge badge-info mr-2">Total Items: <?php echo $totalItems; ?></span>
                                <span class="badge badge-success mr-2">Active: <?php echo $totalActiveItems; ?></span>
                                <span class="badge badge-secondary">Inactive: <?php echo $totalInactiveItems; ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="typesTable">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Description</th>
                                            <th>Items</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subscriptionTypes as $type): ?>                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge mr-2" style="background-color: <?php echo $type['color']; ?>
;">
                                                        <i class="fas <?php echo $type['icon']; ?>
"></i>
                                                    </span>
                                                    <strong><?php echo htmlspecialchars($type['name']); ?>
</strong>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($type['description']): ?>                                                    <?php echo htmlspecialchars(substr($type['description'], 0, 80)); ?>                                                    <?php echo strlen($type['description']) >
 80 ? '...' : ''; ?>
                                                <?php else: ?>                                                    <span class="text-muted">No description</span>
                                                <?php endif; ?>                                            </td>
                                            <td>
                                                <span class="badge badge-primary">
                                                    <?php echo $type['items_count']; ?>
 total
                                                </span>
                                                <span class="badge badge-success">
                                                    <?php echo $type['active_items_count']; ?>
 active
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $type['is_active'] ? 'success' : 'secondary'; ?>
">
                                                    <?php echo $type['is_active'] ? 'Active' : 'Inactive'; ?>                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($type['created_at'])); ?>
</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="subscription-type-view.php?id=<?php echo $type['id']; ?>
" 
                                                       class="btn btn-outline-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($canEdit): ?>                                                    <a href="subscription-type-edit.php?id=<?php echo $type['id']; ?>
" 
                                                       class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php endif; ?>                                                    <?php if ($canDelete && $type['items_count'] == 0): ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteType(<?php echo $type['id']; ?>, '<?php echo htmlspecialchars($type['name'], ENT_QUOTES); ?>')" 
                                                            title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <?php elseif ($canDelete): ?>                                                    <button type="button" class="btn btn-outline-danger" 
                                                            title="Cannot delete - has items" disabled>
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                    <?php endif; ?>                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-info font-weight-bold">
                                            <td><strong>TOTALS</strong></td>
                                            <td>-</td>
                                            <td>
                                                <span class="badge badge-primary">
                                                    <?php echo $totalItems; ?> total
                                                </span>
                                                <span class="badge badge-success">
                                                    <?php echo $totalActiveItems; ?> active
                                                </span>
                                            </td>
                                            <td>-</td>
                                            <td>-</td>
                                            <td>-</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
                <p>Are you sure you want to delete this subscription type?</p>
                <p><strong id="deleteTypeName"></strong></p>
                <p class="text-info">
                    <i class="fas fa-info-circle"></i>
                    Only subscription types with no items can be deleted.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="subscription-type-delete.php" style="display: inline;">
                    <input type="hidden" id="deleteTypeId" name="type_id">
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
    $('#typesTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [-1] } // Disable sorting on actions column
        ]
    });
});

// Delete type function
function deleteType(id, name) {
    $('#deleteTypeId').val(id);
    $('#deleteTypeName').text(name);
    $('#deleteModal').modal('show');
}
</script>

<?php include '../../components/footer.php'; ?>