<?php
/**
 * AbroadWorks Management System - Module Management
 * 
 * This page allows administrators to manage system modules.
 * 
 * @author ikinciadam@gmail.com
 */

// Include required files
require_once 'includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if user is an admin or owner
if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && 
    (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner'])) {
    header('Location: access-denied.php');
    exit;
}

// Set page title
$page_title = "Module Management";

// Handle module activation/deactivation and creation
$success_message = '';
$error_message = '';

// Handle module creation
if (isset($_POST['create_module'])) {
    $module_name = trim($_POST['module_name']);
    $module_code = trim($_POST['module_code']);
    $module_description = trim($_POST['module_description']);
    $module_icon = trim($_POST['module_icon']);
    
    if (empty($module_name) || empty($module_code)) {
        $error_message = "Module name and code are required.";
    } else {
        // Check if module code already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM modules WHERE code = ?");
        $stmt->execute([$module_code]);
        $exists = $stmt->fetchColumn();
        
        if ($exists) {
            $error_message = "A module with this code already exists.";
        } else {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // 1. Create module record in database
                $stmt = $db->prepare("INSERT INTO modules (name, code, description, icon, is_active, created_at, updated_at) 
                                     VALUES (?, ?, ?, ?, 1, NOW(), NOW())");
                $result = $stmt->execute([$module_name, $module_code, $module_description, $module_icon]);
                
                if (!$result) {
                    throw new Exception("Failed to create module record.");
                }
                
                $module_id = $db->lastInsertId();
                
                // 2. Create module directory structure
                $module_dir = "modules/{$module_code}";
                if (!file_exists($module_dir)) {
                    mkdir($module_dir, 0755, true);
                    mkdir("{$module_dir}/models", 0755, true);
                    mkdir("{$module_dir}/views", 0755, true);
                    mkdir("{$module_dir}/widgets", 0755, true);
                } else {
                    throw new Exception("Module directory already exists.");
                }
                
                // 3. Create basic files
                
                // Main index.php with fixed paths for assets
                $index_content = "<?php\n/**\n * {$module_name} Module\n * \n * @author System Generated\n */\n\n// Include required files\nrequire_once '../../includes/init.php';\n\n// Check if user is logged in\nif (!isset(\$_SESSION['user_id'])) {\n    header('Location: ../../login.php');\n    exit;\n}\n\n// Check if module is active\n\$stmt = \$db->prepare(\"SELECT is_active FROM modules WHERE code = ?\");\n\$stmt->execute(['{$module_code}']);\n\$is_active = \$stmt->fetchColumn();\n\nif (!\$is_active) {\n    header('Location: ../../module-inactive.php');\n    exit;\n}\n\n// Set page title and root path for assets\n\$page_title = \"{$module_name}\";\n\$root_path = \"../../\";\n\$root_dir = dirname(__DIR__, 2);\n\n// Include header and sidebar\ninclude '../../components/header.php';\ninclude '../../components/sidebar.php';\n\n// Include the view\ninclude 'views/index.php';\n\n// Include footer\ninclude '../../components/footer.php';\n";
                file_put_contents("{$module_dir}/index.php", $index_content);
                
                // Models index.php
                $models_index = "<?php\n// This file is intentionally left empty to prevent directory listing\n";
                file_put_contents("{$module_dir}/models/index.php", $models_index);
                
                // Views index.php - Simplified structure to match existing modules
                $views_index = "<?php\n/**\n * {$module_name} Module - Main View\n */\n?>\n<div class=\"content-wrapper\">\n    <div class=\"content-header\">\n        <div class=\"container-fluid\">\n            <div class=\"row mb-2\">\n                <div class=\"col-sm-6\">\n                    <h1 class=\"m-0 text-primary\">{$module_name}</h1>\n                </div>\n                <div class=\"col-sm-6\">\n                    <ol class=\"breadcrumb float-sm-end\">\n                        <li class=\"breadcrumb-item\"><a href=\"../../index.php\">Home</a></li>\n                        <li class=\"breadcrumb-item active\">{$module_name}</li>\n                    </ol>\n                </div>\n            </div>\n        </div>\n    </div>\n\n    <div class=\"content\">\n        <div class=\"container-fluid\">\n            <div class=\"card\">\n                <div class=\"card-header\">\n                    <h3 class=\"card-title\">{$module_name} Module</h3>\n                </div>\n                <div class=\"card-body\">\n                    <p>Welcome to the {$module_name} module. This is a system-generated template.</p>\n                </div>\n            </div>\n        </div>\n    </div>\n</div>";
                file_put_contents("{$module_dir}/views/index.php", $views_index);
                
                // Widgets index.php
                $widgets_index = "<?php\n// This file is intentionally left empty to prevent directory listing\n";
                file_put_contents("{$module_dir}/widgets/index.php", $widgets_index);
                
                // 4. Create permissions
                $view_permission_name = "View {$module_name}";
                $view_permission_code = "view_{$module_code}";
                $view_permission_desc = "Permission to view the {$module_name} module";
                
                // Check if permission code already exists
                $stmt = $db->prepare("SELECT COUNT(*) FROM permissions WHERE code = ?");
                $stmt->execute([$view_permission_code]);
                $permission_exists = $stmt->fetchColumn();
                
                if ($permission_exists) {
                    // If permission code exists, add a unique suffix
                    $counter = 1;
                    $original_code = $view_permission_code;
                    do {
                        $view_permission_code = $original_code . '_' . $counter;
                        $stmt->execute([$view_permission_code]);
                        $permission_exists = $stmt->fetchColumn();
                        $counter++;
                    } while ($permission_exists);
                }
                
                $stmt = $db->prepare("INSERT INTO permissions (name, code, description, module, is_module, created_at, updated_at) 
                                     VALUES (?, ?, ?, ?, 1, NOW(), NOW())");
                $result = $stmt->execute([$view_permission_name, $view_permission_code, $view_permission_desc, $module_code]);
                
                if (!$result) {
                    throw new Exception("Failed to create permission records.");
                }
                
                // 5. Create menu item
                $stmt = $db->prepare("INSERT INTO menu_items (name, url, icon, parent_id, permission, display_order, is_active, created_at, updated_at) 
                                     VALUES (?, ?, ?, NULL, ?, ?, 1, NOW(), NOW())");
                $menu_url = "modules/{$module_code}/";
                $result = $stmt->execute([$module_name, $menu_url, $module_icon, $view_permission_code, 999]);
                
                if (!$result) {
                    throw new Exception("Failed to create menu item.");
                }
                
                // Commit transaction
                $db->commit();
                
                $success_message = "Module '{$module_name}' has been successfully created.";
                
                // Log the action
                log_activity($_SESSION['user_id'], 'create', 'modules', "Created module: {$module_name}");
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $db->rollBack();
                $error_message = "Error creating module: " . $e->getMessage();
            }
        }
    }
}

if (isset($_POST['toggle_module'])) {
    $module_id = (int)$_POST['module_id'];
    $new_status = (int)$_POST['new_status'];
    
    try {
        $stmt = $db->prepare("UPDATE modules SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$new_status, $module_id]);
        
        if ($result) {
            $status_text = $new_status ? 'activated' : 'deactivated';
            $success_message = "Module successfully $status_text.";
            
            // Log the action
            $action = $new_status ? 'activate' : 'deactivate';
            log_activity($_SESSION['user_id'], $action, 'modules', "Module ID: $module_id");
        } else {
            $error_message = "Failed to update module status.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Get all modules
$stmt = $db->prepare("SELECT * FROM modules ORDER BY name");
$stmt->execute();
$modules = $stmt->fetchAll();

// Include header and sidebar
include 'components/header.php';
include 'components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Module Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item active">Module Management</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title">System Modules</h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModuleModal">
                        <i class="fas fa-plus me-1"></i> Create Module
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modules as $module): ?>
                                    <tr>
                                        <td><?php echo $module['id']; ?></td>
                                        <td><?php echo htmlspecialchars($module['name']); ?></td>
                                        <td><code><?php echo htmlspecialchars($module['code']); ?></code></td>
                                        <td>
                                            <?php if (!empty($module['description'])): ?>
                                                <?php echo htmlspecialchars($module['description']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No description</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($module['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($module['updated_at'])); ?></td>
                                        <td>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="module_id" value="<?php echo $module['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $module['is_active'] ? 0 : 1; ?>">
                                                <button type="submit" name="toggle_module" class="btn btn-sm <?php echo $module['is_active'] ? 'btn-danger' : 'btn-success'; ?>">
                                                    <?php echo $module['is_active'] ? '<i class="fas fa-ban me-1"></i> Deactivate' : '<i class="fas fa-check me-1"></i> Activate'; ?>
                                                </button>
                                            </form>
                                            
                                            <?php if (file_exists("modules/{$module['code']}/index.php")): ?>
                                                <a href="modules/<?php echo $module['code']; ?>/" class="btn btn-sm btn-info ms-1">
                                                    <i class="fas fa-external-link-alt me-1"></i> Open
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($modules)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No modules found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Deactivating a module will prevent non-administrator users from accessing it. 
                        The module will remain installed but will be inaccessible until reactivated.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Module Modal -->
<div class="modal fade" id="createModuleModal" tabindex="-1" aria-labelledby="createModuleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="createModuleModalLabel">Create New Module</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="module_name" class="form-label">Module Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="module_name" name="module_name" required>
                        <div class="form-text">Display name of the module (e.g. "Customer Management")</div>
                    </div>
                    <div class="mb-3">
                        <label for="module_code" class="form-label">Module Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="module_code" name="module_code" required>
                        <div class="form-text">Unique identifier for the module (e.g. "customers"). Use lowercase letters and underscores only.</div>
                    </div>
                    <div class="mb-3">
                        <label for="module_description" class="form-label">Description</label>
                        <textarea class="form-control" id="module_description" name="module_description" rows="2"></textarea>
                        <div class="form-text">Brief description of the module's purpose</div>
                    </div>
                    <div class="mb-3">
                        <label for="module_icon" class="form-label">Icon</label>
                        <input type="text" class="form-control" id="module_icon" name="module_icon" placeholder="fa-users">
                        <div class="form-text">FontAwesome icon code (e.g. "fa-users"). See <a href="https://fontawesome.com/icons" target="_blank">FontAwesome</a> for available icons.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_module" class="btn btn-primary">Create Module</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript to auto-generate module code from name -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const moduleNameInput = document.getElementById('module_name');
    const moduleCodeInput = document.getElementById('module_code');
    
    moduleNameInput.addEventListener('input', function() {
        // Convert module name to lowercase, replace spaces with underscores, and remove special characters
        const moduleName = this.value;
        const moduleCode = moduleName.toLowerCase()
            .replace(/\s+/g, '_')
            .replace(/[^a-z0-9_]/g, '');
        
        moduleCodeInput.value = moduleCode;
    });
});
</script>

<?php include 'components/footer.php'; ?>
