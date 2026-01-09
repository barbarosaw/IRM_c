<?php
/**
 * Inventory Module - Edit Item
 * 
 * @author System Generated
 */


// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory permissions
if (!has_permission('edit_inventory')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->
prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$page_title = "Edit Inventory Item";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/InventoryItem.php';
require_once $root_dir . '/modules/inventory/models/SubscriptionType.php';

// Initialize models
$inventoryModel = new InventoryItem($db);
$subscriptionTypeModel = new InventorySubscriptionType($db);

// Get item ID
$itemId = (int)($_GET['id'] ?? 0);
if (!$itemId) {
    header('Location: items.php?error=invalid_item');
    exit;
}

// Get item data
$canViewLicenseKeys = has_permission('view_license_keys');
$itemData = $inventoryModel->getById($itemId, $canViewLicenseKeys);
if (!$itemData) {
    header('Location: items.php?error=item_not_found');
    exit;
}

// Get subscription types
$subscriptionTypes = $subscriptionTypeModel->getAll();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'name' => trim($_POST['name']),
            'code' => trim($_POST['code']),
            'subscription_type_id' => (int)$_POST['subscription_type_id'],
            'description' => trim($_POST['description'] ?? ''),
            'license_key' => trim($_POST['license_key'] ?? ''),
            'purchase_date' => $_POST['purchase_date'] ?: null,
            'expiry_date' => $_POST['expiry_date'] ?: null,
            'monthly_cost' => !empty($_POST['monthly_cost']) ? (float)$_POST['monthly_cost'] : null,
            'annual_cost' => !empty($_POST['annual_cost']) ? (float)$_POST['annual_cost'] : null,
            'currency' => $_POST['currency'] ?? 'USD',
            'vendor_name' => trim($_POST['vendor_name'] ?? ''),
            'vendor_contact' => trim($_POST['vendor_contact'] ?? ''),
            'max_users' => (int)($_POST['max_users'] ?? 1),
            'max_teams' => (int)($_POST['max_teams'] ?? 1),
            'assignment_type' => $_POST['assignment_type'] ?? 'individual',
            'status' => $_POST['status'] ?? 'active',
            'renewal_notification_days' => (int)($_POST['renewal_notification_days'] ?? 30),
            'auto_renewal' => isset($_POST['auto_renewal']) ? 1 : 0,
            'notes' => trim($_POST['notes'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Validation
        if (empty($data['name'])) {
            $errors[] = 'Item name is required.';
        }
        
        if (empty($data['code'])) {
            $errors[] = 'Item code is required.';
        }
        
        if (!$data['subscription_type_id']) {
            $errors[] = 'Subscription type is required.';
        }
        
        // Check if code already exists (excluding current item)
        if ($inventoryModel->codeExists($data['code'], $itemId)) {
            $errors[] = 'Item code already exists.';
        }
        
        // Validate dates
        if ($data['purchase_date'] && $data['expiry_date']) {
            if (strtotime($data['expiry_date']) <= strtotime($data['purchase_date'])) {
                $errors[] = 'Expiry date must be after purchase date.';
            }
        }
        
        if (empty($errors)) {
            if ($inventoryModel->update($itemId, $data)) {
                $success = true;
                $_SESSION['success_message'] = 'Item updated successfully.';
                header('Location: item-view.php?id=' . $itemId);
                exit;
            } else {
                $errors[] = 'Failed to update item.';
            }
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
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
                        <i class="fas fa-edit"></i> Edit Inventory Item
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item"><a href="items.php">Items</a></li>
                        <li class="breadcrumb-item"><a href="item-view.php?id=<?php echo $itemId; ?>
">Item Details</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            
            <?php if (!empty($errors)): ?>            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>                    <li><?php echo htmlspecialchars($error); ?>
</li>
                    <?php endforeach; ?>                </ul>
            </div>
            <?php endif; ?>
            <form method="POST" class="row">
                <!-- Basic Information -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-info-circle"></i> Basic Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name" class="form-label">Item Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($itemData['name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="code" class="form-label">Item Code <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="code" name="code" 
                                               value="<?php echo htmlspecialchars($itemData['code']); ?>
" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="subscription_type_id" class="form-label">Subscription Type <span class="text-danger">*</span></label>
                                        <select class="form-control select2" id="subscription_type_id" name="subscription_type_id" required>
                                            <option value="">Select Subscription Type</option>
                                            <?php foreach ($subscriptionTypes as $type): ?>                                            <option value="<?php echo $type['id']; ?>
" 
                                                    <?php echo $itemData['subscription_type_id'] == $type['id'] ? 'selected' : ''; ?>
>
                                                <?php echo htmlspecialchars($type['name']); ?>                                            </option>
                                            <?php endforeach; ?>                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="active" <?php echo $itemData['status'] === 'active' ? 'selected' : ''; ?>
>Active</option>
                                            <option value="inactive" <?php echo $itemData['status'] === 'inactive' ? 'selected' : ''; ?>
>Inactive</option>
                                            <option value="expired" <?php echo $itemData['status'] === 'expired' ? 'selected' : ''; ?>
>Expired</option>
                                            <option value="pending" <?php echo $itemData['status'] === 'pending' ? 'selected' : ''; ?>
>Pending</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($itemData['description'] ?? ''); ?>
</textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- License & Dates -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-key"></i> License & Dates
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($canViewLicenseKeys): ?>
                            <div class="form-group">
                                <label for="license_key" class="form-label">License Key</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="license_key" name="license_key" 
                                           value="<?php echo htmlspecialchars($itemData['license_key'] ?? ''); ?>">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('license_key')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-lock"></i> You don't have permission to view or edit license keys.
                            </div>
                            <?php endif; ?>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="purchase_date" class="form-label">Purchase Date</label>
                                        <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                               value="<?php echo $itemData['purchase_date']; ?>
">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="expiry_date" class="form-label">Expiry Date</label>
                                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                               value="<?php echo $itemData['expiry_date']; ?>
">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cost Information -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-dollar-sign"></i> Cost Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="monthly_cost" class="form-label">Monthly Cost</label>
                                        <input type="number" step="0.01" class="form-control" id="monthly_cost" name="monthly_cost" 
                                               value="<?php echo $itemData['monthly_cost']; ?>
">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="annual_cost" class="form-label">Annual Cost</label>
                                        <input type="number" step="0.01" class="form-control" id="annual_cost" name="annual_cost" 
                                               value="<?php echo $itemData['annual_cost']; ?>
">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="currency" class="form-label">Currency</label>
                                        <select class="form-control" id="currency" name="currency">
                                            <option value="USD" <?php echo $itemData['currency'] === 'USD' ? 'selected' : ''; ?>
>USD</option>
                                            <option value="EUR" <?php echo $itemData['currency'] === 'EUR' ? 'selected' : ''; ?>
>EUR</option>
                                            <option value="TRY" <?php echo $itemData['currency'] === 'TRY' ? 'selected' : ''; ?>
>TRY</option>
                                            <option value="GBP" <?php echo $itemData['currency'] === 'GBP' ? 'selected' : ''; ?>
>GBP</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Settings & Actions -->
                <div class="col-md-4">
                    <!-- Vendor Information -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-building"></i> Vendor Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="vendor_name" class="form-label">Vendor Name</label>
                                <input type="text" class="form-control" id="vendor_name" name="vendor_name" 
                                       value="<?php echo htmlspecialchars($itemData['vendor_name'] ?? ''); ?>
">
                            </div>
                            <div class="form-group">
                                <label for="vendor_contact" class="form-label">Vendor Contact</label>
                                <textarea class="form-control" id="vendor_contact" name="vendor_contact" rows="3"><?php echo htmlspecialchars($itemData['vendor_contact'] ?? ''); ?>
</textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assignment Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users"></i> Assignment Settings
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="assignment_type" class="form-label">Assignment Type</label>
                                <select class="form-control" id="assignment_type" name="assignment_type">
                                    <option value="individual" <?php echo $itemData['assignment_type'] === 'individual' ? 'selected' : ''; ?>>Individual</option>
                                    <option value="team" <?php echo $itemData['assignment_type'] === 'team' ? 'selected' : ''; ?>>Team</option>
                                    <option value="both" <?php echo $itemData['assignment_type'] === 'both' ? 'selected' : ''; ?>>Both</option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="max_users" class="form-label">Max Users</label>
                                        <input type="number" min="1" class="form-control" id="max_users" name="max_users" 
                                               value="<?php echo $itemData['max_users']; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="max_teams" class="form-label">Max Teams</label>
                                        <input type="number" min="1" class="form-control" id="max_teams" name="max_teams" 
                                               value="<?php echo $itemData['max_teams']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Renewal Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-sync-alt"></i> Renewal Settings
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="renewal_notification_days" class="form-label">Notification Days</label>
                                <input type="number" min="1" max="365" class="form-control" id="renewal_notification_days" 
                                       name="renewal_notification_days" value="<?php echo $itemData['renewal_notification_days']; ?>">
                                <small class="form-text text-muted">Days before expiry to send notification</small>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="auto_renewal" name="auto_renewal" 
                                       <?php echo $itemData['auto_renewal'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="auto_renewal">Auto Renewal</label>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       <?php echo $itemData['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-sticky-note"></i> Notes
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <textarea class="form-control" name="notes" rows="4" placeholder="Additional notes..."><?php echo htmlspecialchars($itemData['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="card">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-save"></i> Update Item
                            </button>
                            <a href="item-view.php?id=<?php echo $itemId; ?>" class="btn btn-secondary btn-block">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling.querySelector('button i');
    
    if (field.type === 'password') {
        field.type = 'text';
        button.className = 'fas fa-eye-slash';
    } else {
        field.type = 'password';
        button.className = 'fas fa-eye';
    }
}

// Initialize Select2
$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap4'
    });
    
    let manualAnnualEdit = false;
    let manualMonthlyEdit = false;
    
    // Calculate annual cost from monthly (always update unless manually edited)
    $('#monthly_cost').on('input', function() {
        const monthly = parseFloat($(this).val()) || 0;
        
        if (!manualAnnualEdit && monthly > 0) {
            $('#annual_cost').val((monthly * 12).toFixed(2));
        } else if (monthly === 0) {
            $('#annual_cost').val('');
            manualAnnualEdit = false;
        }
    });
    
    // Calculate monthly cost from annual (always update unless manually edited)
    $('#annual_cost').on('input', function() {
        const annual = parseFloat($(this).val()) || 0;
        
        if (!manualMonthlyEdit && annual > 0) {
            $('#monthly_cost').val((annual / 12).toFixed(2));
        } else if (annual === 0) {
            $('#monthly_cost').val('');
            manualMonthlyEdit = false;
        }
    });
    
    // Detect manual editing of annual cost
    $('#annual_cost').on('focus', function() {
        manualAnnualEdit = true;
    });
    
    // Detect manual editing of monthly cost
    $('#monthly_cost').on('focus', function() {
        manualMonthlyEdit = true;
    });
    
    // Reset manual edit flags when fields are cleared
    $('#annual_cost').on('blur', function() {
        if ($(this).val() === '') {
            manualAnnualEdit = false;
        }
    });
    
    $('#monthly_cost').on('blur', function() {
        if ($(this).val() === '') {
            manualMonthlyEdit = false;
        }
    });

    // Assignment Type change handler
    function toggleAssignmentFields() {
        var assignmentType = $('#assignment_type').val();
        
        if (assignmentType === 'individual') {
            // Individual: Enable users, disable teams and set to 0
            $('#max_users').prop('disabled', false);
            $('#max_teams').prop('disabled', true).val(0);
        } else if (assignmentType === 'team') {
            // Team: Enable teams, disable users and set to 0
            $('#max_users').prop('disabled', true).val(0);
            $('#max_teams').prop('disabled', false);
        } else if (assignmentType === 'both') {
            // Both: Enable both fields
            $('#max_users').prop('disabled', false);
            $('#max_teams').prop('disabled', false);
        }
    }
    
    // Assignment type change event
    $('#assignment_type').on('change', toggleAssignmentFields);
    
    // Initialize assignment fields on page load
    toggleAssignmentFields();

    // Auto-generate item code from name
    var manualCodeEdit = false;
    
    $('#name').on('input', function() {
        if (!manualCodeEdit) {
            var itemName = $(this).val();
            var itemCode = generateItemCode(itemName);
            $('#code').val(itemCode);
        }
    });
    
    // Detect manual editing of code
    $('#code').on('focus', function() {
        manualCodeEdit = true;
    });
    
    // Function to generate item code from name
    function generateItemCode(name) {
        if (!name) return '';
        
        // Remove special characters and convert to uppercase
        var code = name.replace(/[^a-zA-Z0-9\s]/g, '')
                      .toUpperCase()
                      .replace(/\s+/g, '_');
        
        // Limit to reasonable length
        if (code.length > 20) {
            code = code.substring(0, 20);
        }
        
        return code;
    }
});
</script>

<?php include '../../components/footer.php'; ?>
