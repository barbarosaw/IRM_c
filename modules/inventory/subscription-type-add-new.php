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

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
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

<style>
.color-option-label {
    cursor: pointer;
    padding: 8px;
    border: 2px solid transparent;
    border-radius: 8px;
    display: flex;
    align-items: center;
    transition: all 0.2s ease;
}

.color-option-label:hover {
    background-color: #f8f9fa;
}

.color-option-label .color-circle {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    margin-right: 8px;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px rgba(0,0,0,0.1);
}

input[type="radio"]:checked + .color-option-label {
    border-color: #007bff;
    background-color: #f0f8ff;
}

.icon-selection-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
    margin-top: 8px;
}

.icon-option {
    position: relative;
}

.icon-badge {
    display: block;
    width: 40px;
    height: 40px;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    text-align: center;
    line-height: 36px;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #fff;
}

.icon-badge:hover {
    border-color: #007bff;
    background-color: #f0f8ff;
}

input[type="radio"]:checked + .icon-badge {
    border-color: #007bff;
    background-color: #007bff;
    color: white;
}

#preview-badge {
    background-color: #007bff !important;
    color: white;
    padding: 8px 16px;
    border-radius: 6px;
    display: inline-block;
    font-size: 14px;
    font-weight: 500;
}
</style>

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
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
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
                                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category" class="form-label">Category</label>
                                        <select class="form-control" id="category" name="category">
                                            <option value="general" <?php echo ($_POST['category'] ?? '') === 'general' ? 'selected' : ''; ?>>General</option>
                                            <option value="software" <?php echo ($_POST['category'] ?? '') === 'software' ? 'selected' : ''; ?>>Software</option>
                                            <option value="saas" <?php echo ($_POST['category'] ?? '') === 'saas' ? 'selected' : ''; ?>>SaaS</option>
                                            <option value="cloud" <?php echo ($_POST['category'] ?? '') === 'cloud' ? 'selected' : ''; ?>>Cloud</option>
                                            <option value="hardware" <?php echo ($_POST['category'] ?? '') === 'hardware' ? 'selected' : ''; ?>>Hardware</option>
                                            <option value="license" <?php echo ($_POST['category'] ?? '') === 'license' ? 'selected' : ''; ?>>License</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="features" class="form-label">Features</label>
                                <textarea class="form-control" id="features" name="features" rows="4" 
                                          placeholder="Enter features separated by new lines"><?php echo htmlspecialchars($_POST['features'] ?? ''); ?></textarea>
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
                            <!-- Color Selection -->
                            <div class="form-group">
                                <label class="form-label">Color</label>
                                <div class="row">
                                    <?php foreach ($colorOptions as $colorValue => $colorName): ?>
                                    <div class="col-6 mb-2">
                                        <div class="form-check">
                                            <input type="radio" class="form-check-input d-none" name="color" 
                                                   value="<?php echo $colorValue; ?>" id="color_<?php echo substr($colorValue, 1); ?>"
                                                   <?php echo ($_POST['color'] ?? '#007bff') === $colorValue ? 'checked' : ''; ?>>
                                            <label class="color-option-label" for="color_<?php echo substr($colorValue, 1); ?>">
                                                <div class="color-circle" style="background-color: <?php echo $colorValue; ?>"></div>
                                                <small class="ms-2"><?php echo $colorName; ?></small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Icon Selection -->
                            <div class="form-group">
                                <label class="form-label">Icon</label>
                                <div class="icon-selection-grid">
                                    <?php foreach ($iconOptions as $iconClass => $iconName): ?>
                                    <div class="icon-option">
                                        <input type="radio" class="d-none" name="icon" value="<?php echo $iconClass; ?>" 
                                               id="icon_<?php echo str_replace(['fas fa-', ' '], ['', '_'], $iconClass); ?>"
                                               <?php echo ($_POST['icon'] ?? 'fas fa-cube') === $iconClass ? 'checked' : ''; ?>>
                                        <label class="icon-badge" for="icon_<?php echo str_replace(['fas fa-', ' '], ['', '_'], $iconClass); ?>" 
                                               title="<?php echo $iconName; ?>">
                                            <i class="<?php echo $iconClass; ?>"></i>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Preview -->
                            <div class="form-group">
                                <label class="form-label">Preview</label>
                                <div class="text-center p-3 bg-light rounded">
                                    <div id="preview-badge">
                                        <i id="preview-icon" class="fas fa-cube me-2"></i>
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
                                    <option value="monthly" <?php echo ($_POST['billing_cycle'] ?? 'monthly') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="quarterly" <?php echo ($_POST['billing_cycle'] ?? '') === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                    <option value="annually" <?php echo ($_POST['billing_cycle'] ?? '') === 'annually' ? 'selected' : ''; ?>>Annually</option>
                                    <option value="one-time" <?php echo ($_POST['billing_cycle'] ?? '') === 'one-time' ? 'selected' : ''; ?>>One-time</option>
                                    <option value="perpetual" <?php echo ($_POST['billing_cycle'] ?? '') === 'perpetual' ? 'selected' : ''; ?>>Perpetual</option>
                                </select>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       <?php echo isset($_POST['is_active']) || !isset($_POST['name']) ? 'checked' : ''; ?>>
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
    const colorRadio = document.querySelector('input[name="color"]:checked');
    const iconRadio = document.querySelector('input[name="icon"]:checked');
    
    if (!colorRadio || !iconRadio) return;
    
    const color = colorRadio.value;
    const icon = iconRadio.value;
    
    const previewBadge = document.getElementById('preview-badge');
    const previewIcon = document.getElementById('preview-icon');
    const previewName = document.getElementById('preview-name');
    
    previewBadge.style.backgroundColor = color;
    previewIcon.className = icon + ' me-2';
    previewName.textContent = name;
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Name input listener
    document.getElementById('name').addEventListener('input', updatePreview);
    
    // Color radio listeners
    document.querySelectorAll('input[name="color"]').forEach(radio => {
        radio.addEventListener('change', updatePreview);
    });
    
    // Icon radio listeners
    document.querySelectorAll('input[name="icon"]').forEach(radio => {
        radio.addEventListener('change', updatePreview);
    });
    
    // Initial preview update
    updatePreview();
});
</script>

<?php include '../../components/footer.php'; ?>
