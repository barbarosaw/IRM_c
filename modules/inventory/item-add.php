<?php
/**
 * Inventory Module - Add New Item
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

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$page_title = "Add Inventory Item";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/InventoryItem.php';
require_once $root_dir . '/modules/inventory/models/SubscriptionType.php';

// Initialize models
$inventoryModel = new InventoryItem($db);
$subscriptionTypeModel = new InventorySubscriptionType($db);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name' => trim($_POST['name'] ?? ''),
        'code' => trim($_POST['code'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'subscription_type_id' => (int)($_POST['subscription_type_id'] ?? 0),
        'vendor_name' => trim($_POST['vendor_name'] ?? ''),
        'vendor_contact' => trim($_POST['vendor_contact'] ?? ''),
        'max_users' => (int)($_POST['max_users'] ?? 0),
        'max_teams' => (int)($_POST['max_teams'] ?? 0),
        'monthly_cost' => floatval($_POST['monthly_cost'] ?? 0),
        'annual_cost' => floatval($_POST['annual_cost'] ?? 0),
        'currency' => trim($_POST['currency'] ?? 'USD'),
        'license_key' => trim($_POST['license_key'] ?? ''),
        'purchase_date' => $_POST['purchase_date'] ?: null,
        'expiry_date' => $_POST['expiry_date'] ?: null,
        'renewal_date' => $_POST['renewal_date'] ?: null,
        'status' => $_POST['status'] ?? 'active',
        'notes' => trim($_POST['notes'] ?? ''),
        // Assignment Settings
        'assignment_type' => $_POST['assignment_type'] ?? 'individual',
        // Renewal Settings
        'renewal_notification_days' => (int)($_POST['renewal_notification_days'] ?? 30),
        'auto_renewal' => !empty($_POST['auto_renewal'])
    ];
    
    // Validation
    if (empty($data['name'])) {
        $errors[] = "Item name is required.";
    }
    
    if (empty($data['code'])) {
        $errors[] = "Item code is required.";
    } else {
        // Check if code already exists
        if ($inventoryModel->codeExists($data['code'])) {
            $errors[] = "Item code already exists.";
        }
    }
    
    if ($data['subscription_type_id'] <= 0) {
        $errors[] = "Subscription type is required.";
    }
    
    if ($data['max_users'] < 0) {
        $errors[] = "Maximum users cannot be negative.";
    }
    
    if ($data['max_teams'] < 0) {
        $errors[] = "Maximum teams cannot be negative.";
    }
    
    if ($data['monthly_cost'] < 0) {
        $errors[] = "Monthly cost cannot be negative.";
    }
    
    if ($data['annual_cost'] < 0) {
        $errors[] = "Annual cost cannot be negative.";
    }
    
    // Date validations
    if ($data['purchase_date'] && $data['expiry_date']) {
        if (strtotime($data['purchase_date']) > strtotime($data['expiry_date'])) {
            $errors[] = "Purchase date cannot be after expiry date.";
        }
    }
    
    if ($data['expiry_date'] && $data['renewal_date']) {
        if (strtotime($data['expiry_date']) > strtotime($data['renewal_date'])) {
            $errors[] = "Expiry date cannot be after renewal date.";
        }
    }
    
    if (empty($errors)) {
        $result = $inventoryModel->create($data);
        if ($result) {
            $_SESSION['success_message'] = "Inventory item '{$data['name']}' has been created successfully.";
            header('Location: items.php');
            exit;
        } else {
            $errors[] = "Failed to create inventory item. Please try again.";
        }
    }
}

// Get subscription types for dropdown
$subscriptionTypes = $subscriptionTypeModel->getAllActive();

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-plus-circle"></i> Add New Inventory Item
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item"><a href="items.php">Items</a></li>
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
                                    <i class="fas fa-info-circle"></i> Basic Information
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="name">Item Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>
" 
                                                   placeholder="e.g., Microsoft Office 365" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="code">Item Code <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="code" name="code" 
                                                   value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>
" 
                                                   placeholder="e.g., MS-O365-E3" required>
                                            <small class="form-text text-muted">Unique identifier for this item</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3" 
                                              placeholder="Brief description of the item"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?>
</textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="subscription_type_id">Subscription Type <span class="text-danger">*</span></label>
                                            <select class="form-control" id="subscription_type_id" name="subscription_type_id" required>
                                                <option value="">Select subscription type</option>
                                                <?php foreach ($subscriptionTypes as $type): ?>                                                <option value="<?php echo $type['id']; ?>
" 
                                                        <?php echo (($_POST['subscription_type_id'] ?? '') == $type['id']) ? 'selected' : ''; ?>
>
                                                    <?php echo htmlspecialchars($type['name']); ?>                                                </option>
                                                <?php endforeach; ?>                                            </select>
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
                                                <option value="expired" <?php echo (($_POST['status'] ?? '') == 'expired') ? 'selected' : ''; ?>
>Expired</option>
                                                <option value="cancelled" <?php echo (($_POST['status'] ?? '') == 'cancelled') ? 'selected' : ''; ?>
>Cancelled</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Vendor Information -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-building"></i> Vendor Information
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="vendor_name">Vendor Name</label>
                                            <input type="text" class="form-control" id="vendor_name" name="vendor_name" 
                                                   value="<?php echo htmlspecialchars($_POST['vendor_name'] ?? ''); ?>
" 
                                                   placeholder="e.g., Microsoft Corporation">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="vendor_contact">Vendor Contact</label>
                                            <input type="text" class="form-control" id="vendor_contact" name="vendor_contact" 
                                                   value="<?php echo htmlspecialchars($_POST['vendor_contact'] ?? ''); ?>
" 
                                                   placeholder="Email or phone number">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Capacity & Cost -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-chart-bar"></i> Capacity & Cost
                                </h3>
                            </div>
                            <div class="card-body">
                                <!-- Assignment Type & Capacity -->
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="assignment_type" class="form-label">Assignment Type</label>
                                            <select class="form-control" id="assignment_type" name="assignment_type">
                                                <option value="individual" <?php echo (($_POST['assignment_type'] ?? 'individual') === 'individual') ? 'selected' : ''; ?>>Individual</option>
                                                <option value="team" <?php echo (($_POST['assignment_type'] ?? '') === 'team') ? 'selected' : ''; ?>>Team</option>
                                                <option value="both" <?php echo (($_POST['assignment_type'] ?? '') === 'both') ? 'selected' : ''; ?>>Both</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="max_users">Max Users</label>
                                            <input type="number" class="form-control" id="max_users" name="max_users" 
                                                   value="<?php echo htmlspecialchars($_POST['max_users'] ?? '1'); ?>" 
                                                   min="0" placeholder="0 = unlimited">
                                            <small class="form-text text-muted">0 means unlimited</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="max_teams">Max Teams</label>
                                            <input type="number" class="form-control" id="max_teams" name="max_teams" 
                                                   value="<?php echo htmlspecialchars($_POST['max_teams'] ?? '1'); ?>" 
                                                   min="0" placeholder="0 = unlimited">
                                            <small class="form-text text-muted">0 means unlimited</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Currency & Cost -->
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="currency">Currency</label>
                                            <select class="form-control" id="currency" name="currency">
                                                <option value="USD" <?php echo (($_POST['currency'] ?? 'USD') == 'USD') ? 'selected' : ''; ?>>USD ($)</option>
                                                <option value="EUR" <?php echo (($_POST['currency'] ?? '') == 'EUR') ? 'selected' : ''; ?>>EUR (€)</option>
                                                <option value="TRY" <?php echo (($_POST['currency'] ?? '') == 'TRY') ? 'selected' : ''; ?>>TRY (₺)</option>
                                                <option value="GBP" <?php echo (($_POST['currency'] ?? '') == 'GBP') ? 'selected' : ''; ?>>GBP (£)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="monthly_cost">Monthly Cost</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="monthly_cost" name="monthly_cost" 
                                                       value="<?php echo htmlspecialchars($_POST['monthly_cost'] ?? ''); ?>" 
                                                       step="0.01" min="0" placeholder="0.00">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">/month</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="annual_cost">Annual Cost</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="annual_cost" name="annual_cost" 
                                                       value="<?php echo htmlspecialchars($_POST['annual_cost'] ?? ''); ?>" 
                                                       step="0.01" min="0" placeholder="0.00">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">/year</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>
                    
                    <!-- Additional Information -->
                    <div class="col-md-4">
                        
                        <!-- Security -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-key"></i> Security
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php if (has_permission('view_license_keys')): ?>                                <div class="form-group">
                                    <label for="license_key">License Key</label>
                                    <textarea class="form-control" id="license_key" name="license_key" rows="3" 
                                              placeholder="Enter license key or activation code"><?php echo htmlspecialchars($_POST['license_key'] ?? ''); ?>
</textarea>
                                    <small class="form-text text-warning">
                                        <i class="fas fa-shield-alt"></i> Will be encrypted when saved
                                    </small>
                                </div>
                                <?php else: ?>                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    You don't have permission to view/edit license keys.
                                </div>
                                <?php endif; ?>                            </div>
                        </div>

                        <!-- Important Dates -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <i class="fas fa-calendar"></i> Important Dates
                                </h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="purchase_date">Purchase Date</label>
                                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                           value="<?php echo htmlspecialchars($_POST['purchase_date'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="expiry_date">Expiry Date</label>
                                    <input type="date" class="form-control" id="expiry_date" name="expiry_date" 
                                           value="<?php echo htmlspecialchars($_POST['expiry_date'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="renewal_date">Renewal Date</label>
                                    <input type="date" class="form-control" id="renewal_date" name="renewal_date" 
                                           value="<?php echo htmlspecialchars($_POST['renewal_date'] ?? ''); ?>">
                                </div>
                                
                                <!-- Renewal Settings -->
                                <hr>
                                <h6><i class="fas fa-sync-alt"></i> Renewal Settings</h6>
                                
                                <div class="form-group">
                                    <label for="renewal_notification_days" class="form-label">Notification Days</label>
                                    <input type="number" min="1" max="365" class="form-control" id="renewal_notification_days" 
                                           name="renewal_notification_days" value="<?php echo htmlspecialchars($_POST['renewal_notification_days'] ?? '30'); ?>">
                                    <small class="form-text text-muted">Days before expiry to send notification</small>
                                </div>
                                
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="auto_renewal" name="auto_renewal" 
                                           <?php echo (!empty($_POST['auto_renewal'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="auto_renewal">Auto Renewal</label>
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
                                    <label for="notes">Additional Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="4" 
                                              placeholder="Any additional information about this item"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?>
</textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <a href="items.php" class="btn btn-secondary btn-block">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    </div>
                                    <div class="col-md-6">
                                        <button type="submit" class="btn btn-success btn-block">
                                            <i class="fas fa-save"></i> Save Item
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