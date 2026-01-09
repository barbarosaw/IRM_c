<?php
/**
 * Inventory Module - Edit Subscription Type
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

$page_title = "Edit Subscription Type";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/SubscriptionType.php';

// Initialize models
$subscriptionTypeModel = new InventorySubscriptionType($db);

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
        
        // Check if name already exists (excluding current type)
        if ($subscriptionTypeModel->nameExists($data['name'], $typeId)) {
            $errors[] = 'Subscription type name already exists.';
        }
        
        // Validate color format
        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $data['color'])) {
            $errors[] = 'Invalid color format.';
        }
        
        if (empty($errors)) {
            if ($subscriptionTypeModel->update($typeId, $data)) {
                $success = true;
                $_SESSION['success_message'] = 'Subscription type updated successfully.';
                header('Location: subscription-types.php');
                exit;
            } else {
                $errors[] = 'Failed to update subscription type.';
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
    '#17a2b8' => 'Cyan',
    '#6f42c1' => 'Purple',
    '#fd7e14' => 'Orange',
    '#20c997' => 'Teal',
    '#e83e8c' => 'Pink',
    '#6c757d' => 'Gray',
    '#343a40' => 'Dark',
    '#17c671' => 'Emerald',
    '#f39c12' => 'Amber'
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
    'fas fa-globe' => 'Web',
    'fas fa-laptop' => 'Laptop',
    'fas fa-tablet-alt' => 'Tablet',
    'fas fa-code' => 'Code',
    'fas fa-key' => 'License Key',
    'fas fa-robot' => 'AI/Bot',
    'fas fa-palette' => 'Design',
    'fas fa-comments' => 'Communication',
    'fas fa-envelope' => 'Email',
    'fas fa-video' => 'Video',
    'fas fa-music' => 'Audio/Music',
    'fas fa-camera' => 'Camera',
    'fas fa-images' => 'Images',
    'fas fa-file-alt' => 'Documents',
    'fas fa-folder' => 'Folders',
    'fas fa-users' => 'Team',
    'fas fa-user-shield' => 'Admin',
    'fas fa-lock' => 'Security',
    'fas fa-wifi' => 'Network',
    'fas fa-network-wired' => 'Infrastructure',
    'fas fa-rocket' => 'Launch/Deploy',
    'fas fa-bolt' => 'Fast/Power',
    'fas fa-star' => 'Premium',
    'fas fa-gem' => 'Valuable',
    'fas fa-fire' => 'Hot/Trending',
    'fas fa-crown' => 'Enterprise',
    'fas fa-graduation-cap' => 'Education/Training'
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
                        <i class="fas fa-edit"></i> Edit Subscription Type
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
                        <li class="breadcrumb-item"><a href="subscription-types.php">Subscription Types</a></li>
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
                                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($type['name']); ?>
" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="category" class="form-label">Category</label>
                                        <select class="form-control" id="category" name="category">
                                            <option value="general" <?php echo $type['category'] === 'general' ? 'selected' : ''; ?>
>General</option>
                                            <option value="software" <?php echo $type['category'] === 'software' ? 'selected' : ''; ?>
>Software</option>
                                            <option value="saas" <?php echo $type['category'] === 'saas' ? 'selected' : ''; ?>
>SaaS</option>
                                            <option value="cloud" <?php echo $type['category'] === 'cloud' ? 'selected' : ''; ?>
>Cloud</option>
                                            <option value="hardware" <?php echo $type['category'] === 'hardware' ? 'selected' : ''; ?>
>Hardware</option>
                                            <option value="license" <?php echo $type['category'] === 'license' ? 'selected' : ''; ?>
>License</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($type['description'] ?? ''); ?>
</textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="features" class="form-label">Features</label>
                                <textarea class="form-control" id="features" name="features" rows="4" 
                                          placeholder="Enter features separated by new lines"><?php echo htmlspecialchars($type['features'] ?? ''); ?>
</textarea>
                                <small class="form-text text-muted">Enter each feature on a new line</small>
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
                                    <option value="monthly" <?php echo ($type['billing_cycle'] ?? 'monthly') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                    <option value="quarterly" <?php echo ($type['billing_cycle'] ?? '') === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                                    <option value="annually" <?php echo ($type['billing_cycle'] ?? '') === 'annually' ? 'selected' : ''; ?>>Annually</option>
                                    <option value="one-time" <?php echo ($type['billing_cycle'] ?? '') === 'one-time' ? 'selected' : ''; ?>>One-time</option>
                                    <option value="perpetual" <?php echo ($type['billing_cycle'] ?? '') === 'perpetual' ? 'selected' : ''; ?>>Perpetual</option>
                                </select>
                            </div>
                            
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                                       <?php echo $type['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">Active</label>
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
                                    <?php foreach ($colorOptions as $colorValue => $colorName): ?>
                                    <div class="col-4 mb-2">
                                        <div class="form-check">
                                            <input type="radio" class="form-check-input" name="color" 
                                                   value="<?php echo $colorValue; ?>" id="color_<?php echo substr($colorValue, 1); ?>"
                                                   <?php echo ($type['color'] ?? '#007bff') === $colorValue ? 'checked' : ''; ?>>
                                            <label class="form-check-label d-flex align-items-center" for="color_<?php echo substr($colorValue, 1); ?>">
                                                <span class="color-preview" style="background-color: <?php echo $colorValue; ?>; width: 20px; height: 20px; display: inline-block; border-radius: 3px; margin-right: 5px;"></span>
                                                <small><?php echo $colorName; ?></small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="icon" class="form-label">Icon</label>
                                <div class="d-flex flex-wrap">
                                    <?php foreach ($iconOptions as $iconClass => $iconName): ?>
                                    <div class="mr-2 mb-2">
                                        <div class="form-check">
                                            <input type="radio" class="form-check-input" name="icon" 
                                                   value="<?php echo $iconClass; ?>" id="icon_<?php echo str_replace(['fas fa-', ' '], ['', '_'], $iconClass); ?>"
                                                   <?php echo ($type['icon'] ?? 'fas fa-cube') === $iconClass ? 'checked' : ''; ?>>
                                            <label class="form-check-label badge badge-light border d-flex align-items-center px-2 py-1" 
                                                   for="icon_<?php echo str_replace(['fas fa-', ' '], ['', '_'], $iconClass); ?>"
                                                   style="cursor: pointer; font-size: 12px;">
                                                <i class="<?php echo $iconClass; ?> mr-1"></i>
                                                <?php echo $iconName; ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Preview -->
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h6>Preview</h6>
                                    <div id="preview-badge" class="badge ">
                                        <i id="preview-icon" class="<?php echo htmlspecialchars($type['icon'] ?? 'fas fa-cube'); ?>
"></i>
                                        <span id="preview-name"><?php echo htmlspecialchars($type['name']); ?>
</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="card">
                        <div class="card-body">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-save"></i> Update Subscription Type
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
    const name = document.getElementById('name').value || '<?php echo htmlspecialchars($type['name']); ?>';
    
    // Get selected color
    const colorRadio = document.querySelector('input[name="color"]:checked');
    const color = colorRadio ? colorRadio.value : '<?php echo htmlspecialchars($type['color'] ?? '#007bff'); ?>';
    
    // Get selected icon
    const iconRadio = document.querySelector('input[name="icon"]:checked');
    const icon = iconRadio ? iconRadio.value : '<?php echo htmlspecialchars($type['icon'] ?? 'fas fa-cube'); ?>';
    
    console.log('Preview update:', {name, color, icon}); // Debug log
    
    const previewBadge = document.getElementById('preview-badge');
    const previewIcon = document.getElementById('preview-icon');
    const previewName = document.getElementById('preview-name');
    
    if (previewBadge) {
        previewBadge.style.backgroundColor = color;
        previewBadge.style.borderColor = color;
    }
    
    if (previewIcon) {
        previewIcon.className = icon;
    }
    
    if (previewName) {
        previewName.textContent = name;
    }
    
    // Update icon selection highlights
    document.querySelectorAll('input[name="icon"]').forEach(radio => {
        const label = radio.nextElementSibling;
        if (radio.checked) {
            label.classList.add('badge-primary');
            label.classList.remove('badge-light');
        } else {
            label.classList.add('badge-light');
            label.classList.remove('badge-primary');
        }
    });
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners for preview updates
    document.getElementById('name').addEventListener('input', updatePreview);
    
    // Color radio buttons
    document.querySelectorAll('input[name="color"]').forEach(radio => {
        radio.addEventListener('change', function() {
            console.log('Color changed to:', this.value); // Debug
            updatePreview();
        });
    });
    
    // Icon radio buttons
    document.querySelectorAll('input[name="icon"]').forEach(radio => {
        radio.addEventListener('change', function() {
            console.log('Icon changed to:', this.value); // Debug
            updatePreview();
        });
    });
    
    // Initial preview update
    setTimeout(updatePreview, 100); // Small delay to ensure DOM is ready
});
</script>

<style>
/* Hide radio buttons for visual icons and colors */
input[name="icon"], input[name="color"] {
    display: none;
}

/* Icon selection styling */
input[name="icon"]:checked + label {
    background-color: #007bff !important;
    color: white !important;
    border-color: #007bff !important;
}

/* Color selection styling */
input[name="color"]:checked + label {
    font-weight: bold;
    background-color: #f8f9fa;
}

/* Hover effects */
label[for^="icon_"]:hover {
    background-color: #e9ecef !important;
    border-color: #6c757d !important;
}

label[for^="color_"]:hover {
    background-color: #f8f9fa;
}
</style>

<?php include '../../components/footer.php'; ?>