<?php
/**
 * Inventory Module - Usage Logs
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

$page_title = "Usage Logs";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/UsageLog.php';
require_once $root_dir . '/modules/inventory/models/InventoryItem.php';

// Initialize models
$usageLogModel = new InventoryUsageLog($db);
$inventoryModel = new InventoryItem($db);

// Get filter parameters
$itemFilter = $_GET['item_id'] ?? '';
$userFilter = $_GET['user_id'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$dateFilter = $_GET['date_range'] ?? '7'; // Default to 7 days

// Get usage logs with filters
$logs = $usageLogModel->getAll([
    'item_id' => $itemFilter,
    'user_id' => $userFilter,
    'action' => $actionFilter,
    'days' => $dateFilter
]);

// Get filter options
$items = $inventoryModel->getAll();
$stmt = $db->prepare("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique actions
$stmt = $db->prepare("SELECT DISTINCT action FROM inventory_usage_logs ORDER BY action");
$stmt->execute();
$actions = $stmt->fetchAll(PDO::FETCH_COLUMN);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-history"></i> Usage Logs
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item active">Usage Logs</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            
            <!-- Filters -->
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-filter"></i> Filters
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row">
                                <div class="col-md-3">
                                    <label for="item_id" class="form-label">Item</label>
                                    <select class="form-control select2" id="item_id" name="item_id">
                                        <option value="">All Items</option>
                                        <?php foreach ($items as $item): ?>
                                        <option value="<?php echo $item['id']; ?>" 
                                                <?php echo $itemFilter == $item['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="user_id" class="form-label">User</label>
                                    <select class="form-control select2" id="user_id" name="user_id">
                                        <option value="">All Users</option>
                                        <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" 
                                                <?php echo $userFilter == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="action" class="form-label">Action</label>
                                    <select class="form-control" id="action" name="action">
                                        <option value="">All Actions</option>
                                        <?php foreach ($actions as $action): ?>
                                        <option value="<?php echo htmlspecialchars($action); ?>" 
                                                <?php echo $actionFilter === $action ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $action))); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="date_range" class="form-label">Date Range</label>
                                    <select class="form-control" id="date_range" name="date_range">
                                        <option value="1" <?php echo $dateFilter === '1' ? 'selected' : ''; ?>>Last 24 hours</option>
                                        <option value="7" <?php echo $dateFilter === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                                        <option value="30" <?php echo $dateFilter === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                                        <option value="90" <?php echo $dateFilter === '90' ? 'selected' : ''; ?>>Last 90 days</option>
                                        <option value="365" <?php echo $dateFilter === '365' ? 'selected' : ''; ?>>Last year</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search"></i> Filter
                                    </button>
                                    <a href="usage-logs.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Usage Logs Table -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-list"></i> Usage Logs 
                                <span class="badge badge-info"><?php echo count($logs); ?> records</span>
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-sm btn-success" onclick="exportLogs()">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="usage-logs-table" class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date/Time</th>
                                            <th>User</th>
                                            <th>Item</th>
                                            <th>Action</th>
                                            <th>Description</th>
                                            <th>IP Address</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <span class="text-muted small">
                                                    <?php echo date('M j, Y', strtotime($log['logged_at'])); ?>
                                                </span><br>
                                                <span class="text-sm">
                                                    <?php echo date('H:i:s', strtotime($log['logged_at'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-2">
                                                        <i class="fas fa-user text-white"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($log['user_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($log['user_email'] ?? ''); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <span class="fw-bold"><?php echo htmlspecialchars($log['item_name']); ?></span>
                                                    <?php if (!empty($log['item_code'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($log['item_code']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $actionClass = '';
                                                $actionIcon = 'fas fa-circle';
                                                
                                                switch ($log['action']) {
                                                    case 'view':
                                                        $actionClass = 'bg-info';
                                                        $actionIcon = 'fas fa-eye';
                                                        break;
                                                    case 'license_key_viewed':
                                                        $actionClass = 'bg-warning';
                                                        $actionIcon = 'fas fa-key';
                                                        break;
                                                    case 'download':
                                                        $actionClass = 'bg-success';
                                                        $actionIcon = 'fas fa-download';
                                                        break;
                                                    case 'access':
                                                        $actionClass = 'bg-primary';
                                                        $actionIcon = 'fas fa-sign-in-alt';
                                                        break;
                                                    case 'error':
                                                        $actionClass = 'bg-danger';
                                                        $actionIcon = 'fas fa-exclamation-triangle';
                                                        break;
                                                    default:
                                                        $actionClass = 'bg-secondary';
                                                        $actionIcon = 'fas fa-circle';
                                                }
                                                ?>
                                                <span class="badge <?php echo $actionClass; ?>">
                                                    <i class="<?php echo $actionIcon; ?>"></i>
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action']))); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($log['description'] ?? ''); ?>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if (!empty($log['metadata'])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        onclick="showLogDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle"></i> Log Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="log-details-content">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#usage-logs-table').DataTable({
        "responsive": true,
        "lengthChange": false,
        "autoWidth": false,
        "order": [[0, "desc"]],
        "pageLength": 25,
        "columnDefs": [
            { "orderable": false, "targets": [6] }
        ]
    });
    
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap4',
        placeholder: function() {
            return $(this).data('placeholder');
        }
    });
});

// Show log details in modal
function showLogDetails(log) {
    let content = `
        <div class="row">
            <div class="col-md-6">
                <h6>Basic Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Date/Time:</strong></td><td>${log.logged_at}</td></tr>
                    <tr><td><strong>User:</strong></td><td>${log.user_name}</td></tr>
                    <tr><td><strong>Item:</strong></td><td>${log.item_name}</td></tr>
                    <tr><td><strong>Action:</strong></td><td>${log.action}</td></tr>
                    <tr><td><strong>IP Address:</strong></td><td>${log.ip_address || 'N/A'}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Additional Details</h6>
                <table class="table table-sm">
                    <tr><td><strong>User Agent:</strong></td><td><small>${log.user_agent || 'N/A'}</small></td></tr>
                    <tr><td><strong>Description:</strong></td><td>${log.description || 'N/A'}</td></tr>
                </table>
            </div>
        </div>
    `;
    
    if (log.metadata) {
        let metadata = typeof log.metadata === 'string' ? JSON.parse(log.metadata) : log.metadata;
        content += `
            <div class="row mt-3">
                <div class="col-md-12">
                    <h6>Metadata</h6>
                    <pre class="bg-light p-2 rounded"><code>${JSON.stringify(metadata, null, 2)}</code></pre>
                </div>
            </div>
        `;
    }
    
    document.getElementById('log-details-content').innerHTML = content;
    $('#logDetailsModal').modal('show');
}

// Export logs function
function exportLogs() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.open('export-usage-logs.php?' + params.toString(), '_blank');
}
</script>

<?php include '../../components/footer.php'; ?>
