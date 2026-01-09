<?php
/**
 * Inventory Module - Add Subscription Type
 * 
 * @author System Generated
 */


// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory permissions
if (!has_permission('create_inventory')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->
prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$page_title = "Add Subscription Type";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/SubscriptionType.php';

// Initialize models
$subscriptionTypeModel = new InventorySubscriptionType($db);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description'] ?? ''),
            'color' => $_POST['color'] ?? '#007bff',
            'icon' => $_POST['icon'] ?? 'fas fa-cube',
            'category' => $_POST['category'] ?? 'general',
            'billing_cycle' => $_POST['billing_cycle'] ?? 'monthly',
            'features' => trim($_POST['features'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        // Validation
        if (empty($data['name'])) {
            $errors[] = 'Subscription type name is required.';
        }
        
        // Check if name already exists
        if ($subscriptionTypeModel->nameExists($data['name'])) {
            $errors[] = 'Subscription type name already exists.';
        }
        
        // Validate color format
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $data['color'])) {
            $errors[] = 'Invalid color format.';
        }
        
        if (empty($errors)) {
            $id = $subscriptionTypeModel->create($data);
            if ($id) {
                $success = true;
                $_SESSION['success_message'] = 'Subscription type created successfully.';
                header('Location: subscription-types.php');
                exit;
            } else {
                $errors[] = 'Failed to create subscription type.';
            }
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Predefined color options
$colorOptions = [
    '#007bff' => 'Blue',
    '#28a745' => 'Green', 
    '#dc3545' => 'Red',
    '#ffc107' => 'Yellow',
    '#17a2b8' => 'Cyan',
    '#6f42c1' => 'Purple',
    '#fd7e14' => 'Orange',
    '#20c997' => 'Teal',
    '#e83e8c' => 'Pink',
    '#6c757d' => 'Gray'
];

// Predefined icon options
$iconOptions = [
    'fas fa-cube' => 'Cube',
    'fas fa-server' => 'Server',
    'fas fa-cloud' => 'Cloud',
    'fas fa-desktop' => 'Desktop',
    'fas fa-mobile-alt' => 'Mobile',
    'fas fa-shield-alt' => 'Security',
    'fas fa-chart-line' => 'Analytics',
    'fas fa-cogs' => 'Tools',
    'fas fa-database' => 'Database',
    'fas fa-globe' => 'Web'
];

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">
                        <i class="fas fa-plus"></i> Add Subscription Type
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item"><a href="subscription-types.php">Subscription Types</a></li>
                        <li class="breadcrumb-item active">Add</li>
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
                                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>
" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category" class="form-label">Category</label>
                                        <select class="form-control" id="category" name="category">
                                            <option value="general" <?php echo ($_POST['category'] ?? '') === 'general' ? 'selected' : ''; ?>
>General</option>
                                            <option value="software" <?php echo ($_POST['category'] ?? '') === 'software' ? 'selected' : ''; ?>
>Software</option>
                                            <option value="saas" <?php echo ($_POST['category'] ?? '') === 'saas' ? 'selected' : ''; ?>
>SaaS</option>
                                            <option value="cloud" <?php echo ($_POST['category'] ?? '') === 'cloud' ? 'selected' : ''; ?>
>Cloud</option>
                                            <option value="hardware" <?php echo ($_POST['category'] ?? '') === 'hardware' ? 'selected' : ''; ?>
>Hardware</option>
                                            <option value="license" <?php echo ($_POST['category'] ?? '') === 'license' ? 'selected' : ''; ?>
>License</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?>
</textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="features" class="form-label">Features</label>
                                <textarea class="form-control" id="features" name="features" rows="4" 
                                          placeholder="Enter features separated by new lines"><?php echo htmlspecialchars($_POST['features'] ?? ''); ?>
</textarea>
                                <small class="form-text text-muted">Enter each feature on a new line</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Visual & Settings -->
                <div class="col-md-4">
                    <!-- Visual Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-palette"></i> Visual Settings
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="color" class="form-label">Color</label>
                                <div class="row">
                                    <?php foreach ($colorOptions as $colorValue =>
 $colorName): ?>
                                    <div class="col-4 mb-2">
                                        <div class="form-check">
                                            <input type="radio" class="form-check-input" name="color" 
                                                   value="<?php echo $colorValue; ?>
" id="color_<?php echo substr($colorValue, 1); ?>
"
                                                   <?php echo ($_POST['color'] ?? '#007bff') === $colorValue ? 'checked' : ''; ?>
>
                                            <label class="form-check-label d-flex align-items-center" for="color_<?php echo substr($colorValue, 1); ?>
">
                                                <span class="color-preview" style="background-color: <?php echo $colorValue; ?>
; width: 20px; height: 20px; display: inline-block; border-radius: 3px; margin-right: 5px;"></span>
                                                <small><?php echo $colorName; ?>
</small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>                                </div>
                                
                                <div class="mt-2">
                                    <label for="custom_color" class="form-label">Custom Color</label>
                                    <input type="color" class="form-control" id="custom_color" 
                                           value="<?php echo htmlspecialchars($_POST['color'] ?? '#007bff'); ?>
"
                                           onchange="updateCustomColor(this.value)">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="icon" class="form-label">Icon</label>
                                <select class="form-control" id="icon" name="icon">
                                    <?php foreach ($iconOptions as $iconClass =>
 $iconName): ?>
                                    <option value="<?php echo $iconClass; ?>
" 
                                            <?php echo ($_POST['icon'] ?? 'fas fa-cube') === $iconClass ? 'selected' : ''; ?>
>
                                        <?php echo $iconName; ?>                                    </option>
                                    <?php endforeach; ?>                                </select>
                            </div>
                            
                            <!-- Preview -->
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6>Preview</h6>
                                    <div id="preview-badge" class="badge badge-primary">
                                        <i id="preview-icon" class="fas fa-cube"></i>
                                        <span id="preview-name">Sample Type</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Billing Settings -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-credit-card"></i> Billing Settings
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="billing_cycle" class="form-label">Default Billing Cycle</label>
                                <select class="form-control" id="billing_cycle" name="billing_cycle">
                                    <option value="monthly" <?php echo ($_POST['billing_cycle'] ?? 'monthly') === 'monthly' ? 'selected' : ''; ?>
>Monthly</option>
                                    <option value="quarterly" <?php echo ($_POST['billing_cycle'] ?? '') === 'quarterly' ? 'selected' : ''; ?>
>Quarterly</option>
                                    <option value="annually" <?php echo ($_POST['billing_cycle'] ?? '') === 'annually' ? 'selected' : ''; ?>
>Annually</option>
                                    <option value="one-time" <?php echo ($_POST['billing_cycle'] ?? '') === 'one-time' ? 'selected' : ''; ?>
>One-time</option>
                                    <option value="perpetual" <?php echo ($_POST['billing_cycle'] ?? '') === 'perpetual' ? 'selected' : ''; ?>
>Perpetual</option>
                                </select>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       <?php echo isset($_POST['is_active']) || !isset($_POST['name']) ? 'checked' : ''; ?>
>
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="card">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-save"></i> Create Subscription Type
                            </button>
                            <a href="subscription-types.php" class="btn btn-secondary btn-block">
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
// Update preview when inputs change
function updatePreview() {
    const name = document.getElementById('name').value || 'Sample Type';
    const color = document.querySelector('input[name="color"]:checked').value;
    const icon = document.getElementById('icon').value;
    
    const previewBadge = document.getElementById('preview-badge');
    const previewIcon = document.getElementById('preview-icon');
    const previewName = document.getElementById('preview-name');
    
    previewBadge.style.backgroundColor = color;
    previewIcon.className = icon;
    previewName.textContent = name;
}

// Update custom color selection
function updateCustomColor(color) {
    // Add custom color option if it doesn't exist
    let customRadio = document.querySelector('input[name="color"][value="' + color + '"]');
    if (!customRadio) {
        // Uncheck all existing color options
        document.querySelectorAll('input[name="color"]').forEach(radio => radio.checked = false);
        
        // Create hidden input for custom color
        customRadio = document.createElement('input');
        customRadio.type = 'radio';
        customRadio.name = 'color';
        customRadio.value = color;
        customRadio.checked = true;
        customRadio.style.display = 'none';
        document.body.appendChild(customRadio);
    } else {
        customRadio.checked = true;
    }
    
    updatePreview();
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('name').addEventListener('input', updatePreview);
    document.getElementById('icon').addEventListener('change', updatePreview);
    document.querySelectorAll('input[name="color"]').forEach(radio => {
        radio.addEventListener('change', updatePreview);
    });
    
    // Initial preview update
    updatePreview();
});
</script>

<?php include '../../components/footer.php'; ?>