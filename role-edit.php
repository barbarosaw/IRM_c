<?php
/**
 * AbroadWorks Management System - Edit Role
 * 
 * @author ikinciadam@gmail.com
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user has permission to manage roles
check_page_access('roles-manage');

$page_title = 'Edit Role';
$success_message = '';
$error_message = '';

// Check if role ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: roles.php');
    exit;
}

$role_id = (int)$_GET['id'];

// Get role details
$stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
$stmt->execute([$role_id]);
$role = $stmt->fetch();

if (!$role) {
    header('Location: roles.php');
    exit;
}

// Get all modules for the dashboard visibility
$modules = get_all_modules();

// Get role's module visibility settings
$stmt = $db->prepare("SELECT module_code, is_visible FROM module_visibility WHERE role_id = ?");
$stmt->execute([$role_id]);
$module_visibility = [];
while ($row = $stmt->fetch()) {
    $module_visibility[$row['module_code']] = $row['is_visible'];
}

// Get all permissions grouped by module
$permission_modules = get_permissions_by_module();

// Get selected permissions for this role
$stmt = $db->prepare("
    SELECT p.id FROM permissions p
    JOIN role_permissions rp ON p.id = rp.permission_id
    WHERE rp.role_id = ?
");
$stmt->execute([$role_id]);
$selected_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = clean_input($_POST['name']);
    $description = clean_input($_POST['description']);
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
    $module_visibility_data = isset($_POST['module_visibility']) ? $_POST['module_visibility'] : [];
    
    // Validate input
    if (empty($name)) {
        $error_message = "Role name is required.";
    } else {
        // Check if role name already exists (excluding current role)
        $stmt = $db->prepare("SELECT COUNT(*) FROM roles WHERE name = ? AND id != ?");
        $stmt->execute([$name, $role_id]);
        if ($stmt->fetchColumn() > 0) {
            $error_message = "Role name already exists.";
        } else {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // Update role
                $stmt = $db->prepare("UPDATE roles SET name = ?, description = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $description, $role_id]);
                
                // Delete existing permissions
                $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
                $stmt->execute([$role_id]);
                
                // Add new permissions
                foreach ($permissions as $permission_id) {
                    $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                    $stmt->execute([$role_id, $permission_id]);
                }
                
                // Update module visibility - process ALL modules, not just checked ones
                // (unchecked checkboxes don't get submitted in HTML forms)
                foreach ($modules as $module) {
                    $module_code = $module['code'];
                    // Check if this module was checked (exists in submitted data)
                    $is_visible = isset($module_visibility_data[$module_code]) ? 1 : 0;

                    $stmt = $db->prepare("
                        INSERT INTO module_visibility (role_id, module_code, is_visible, created_at, updated_at)
                        VALUES (?, ?, ?, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE is_visible = ?, updated_at = NOW()
                    ");
                    $stmt->execute([$role_id, $module_code, $is_visible, $is_visible]);
                }
                
                // Commit transaction
                $db->commit();
                
                // Log the activity
                log_activity($_SESSION['user_id'], 'update', 'role', "Updated role: $name (ID: $role_id)");
                
                $success_message = "Role updated successfully!";
                
                // Refresh role data
                $stmt = $db->prepare("SELECT * FROM roles WHERE id = ?");
                $stmt->execute([$role_id]);
                $role = $stmt->fetch();
                
                // Refresh selected permissions
                $stmt = $db->prepare("
                    SELECT p.id FROM permissions p
                    JOIN role_permissions rp ON p.id = rp.permission_id
                    WHERE rp.role_id = ?
                ");
                $stmt->execute([$role_id]);
                $selected_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Refresh module visibility
                $stmt = $db->prepare("SELECT module_code, is_visible FROM module_visibility WHERE role_id = ?");
                $stmt->execute([$role_id]);
                $module_visibility = [];
                while ($row = $stmt->fetch()) {
                    $module_visibility[$row['module_code']] = $row['is_visible'];
                }
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $db->rollBack();
                $error_message = "Error updating role: " . $e->getMessage();
            }
        }
    }
}

// Include header and sidebar
require_once 'components/header.php';
require_once 'components/sidebar.php';
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Edit Role</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="roles.php">Roles</a></li>
                        <li class="breadcrumb-item active">Edit Role</li>
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
            
            <form method="post">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <h3 class="card-title">Role Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Role Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($role['name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($role['description']); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dashboard Module Visibility Card -->
                        <div class="card card-info card-outline">
                            <div class="card-header">
                                <h3 class="card-title">Dashboard Module Visibility</h3>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Select which modules this role can see on the dashboard.</p>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="select-all-modules">
                                        <label class="form-check-label fw-bold" for="select-all-modules">
                                            Select All Modules
                                        </label>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Module</th>
                                                <th class="text-center">Visible</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($modules as $module): ?>
                                                <tr>
                                                    <td>
                                                        <i class="fas <?php echo $module['icon']; ?> me-2"></i>
                                                        <?php echo htmlspecialchars($module['name']); ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="form-check form-switch d-flex justify-content-center">
                                                            <input class="form-check-input module-visibility" type="checkbox" 
                                                                id="module_<?php echo $module['code']; ?>" 
                                                                name="module_visibility[<?php echo $module['code']; ?>]" 
                                                                value="1" 
                                                                <?php echo isset($module_visibility[$module['code']]) && $module_visibility[$module['code']] ? 'checked' : ''; ?>>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card card-primary card-outline">
                            <div class="card-header">
                                <h3 class="card-title">Role Permissions</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="select-all-permissions">
                                        <label class="form-check-label fw-bold" for="select-all-permissions">
                                            Select All Permissions
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="accordion" id="permissionsAccordion">
                                    <?php foreach ($permission_modules as $module_code => $module): ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading_<?php echo $module_code; ?>">
                                                <button class="accordion-button" type="button" data-bs-toggle="collapse" 
                                                    data-bs-target="#collapse_<?php echo $module_code; ?>" 
                                                    aria-expanded="true" aria-controls="collapse_<?php echo $module_code; ?>">
                                                    <i class="fas <?php echo $module['icon']; ?> me-2"></i>
                                                    <?php echo htmlspecialchars($module['name']); ?> Permissions
                                                    <span class="ms-2 badge bg-info select-group-count" 
                                                         id="count_<?php echo $module_code; ?>">0 / <?php echo count($module['permissions']); ?></span>
                                                </button>
                                            </h2>
                                            <div id="collapse_<?php echo $module_code; ?>" 
                                                class="accordion-collapse collapse" 
                                                aria-labelledby="heading_<?php echo $module_code; ?>" 
                                                data-bs-parent="#permissionsAccordion">
                                                <div class="accordion-body">
                                                    <div class="mb-2">
                                                        <div class="form-check">
                                                            <input class="form-check-input group-selector" 
                                                                type="checkbox" 
                                                                id="group_<?php echo $module_code; ?>" 
                                                                data-group="<?php echo $module_code; ?>">
                                                            <label class="form-check-label fw-bold" for="group_<?php echo $module_code; ?>">
                                                                Select All <?php echo htmlspecialchars($module['name']); ?> Permissions
                                                            </label>
                                                        </div>
                                                    </div>
                                                
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover">
                                                            <thead>
                                                                <tr>
                                                                    <th style="width: 30px;"></th>
                                                                    <th>Permission</th>
                                                                    <th>Code</th>
                                                                    <th>Description</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($module['permissions'] as $permission): ?>
                                                                    <tr>
                                                                        <td>
                                                                            <div class="form-check">
                                                                                <input class="form-check-input permission-checkbox" 
                                                                                    type="checkbox" 
                                                                                    name="permissions[]" 
                                                                                    value="<?php echo $permission['id']; ?>" 
                                                                                    id="permission_<?php echo $permission['id']; ?>" 
                                                                                    data-group="<?php echo $module_code; ?>"
                                                                                    <?php echo in_array($permission['id'], $selected_permissions) ? 'checked' : ''; ?>>
                                                                            </div>
                                                                        </td>
                                                                        <td>
                                                                            <label for="permission_<?php echo $permission['id']; ?>" class="form-check-label">
                                                                                <?php echo htmlspecialchars($permission['name']); ?>
                                                                                <?php if ($permission['is_module']): ?>
                                                                                    <span class="badge bg-success">Module Access</span>
                                                                                <?php endif; ?>
                                                                            </label>
                                                                        </td>
                                                                        <td><code><?php echo htmlspecialchars($permission['code']); ?></code></td>
                                                                        <td><?php echo htmlspecialchars($permission['description']); ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Update Role</button>
                        <a href="roles.php" class="btn btn-secondary ms-2">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Function to update selected count
    function updateSelectedCount(groupName) {
        const total = document.querySelectorAll(`.permission-checkbox[data-group="${groupName}"]`).length;
        const selected = document.querySelectorAll(`.permission-checkbox[data-group="${groupName}"]:checked`).length;
        document.getElementById(`count_${groupName}`).textContent = `${selected} / ${total}`;
        
        // Update group selector indeterminate state
        const groupSelector = document.getElementById(`group_${groupName}`);
        if (selected === 0) {
            groupSelector.checked = false;
            groupSelector.indeterminate = false;
        } else if (selected === total) {
            groupSelector.checked = true;
            groupSelector.indeterminate = false;
        } else {
            groupSelector.checked = false;
            groupSelector.indeterminate = true;
        }
    }
    
    // Initialize counts
    document.querySelectorAll('.select-group-count').forEach(function(countElement) {
        const groupName = countElement.id.replace('count_', '');
        updateSelectedCount(groupName);
    });
    
    // Select all permissions checkbox
    document.getElementById('select-all-permissions').addEventListener('change', function() {
        document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        
        document.querySelectorAll('.group-selector').forEach(checkbox => {
            checkbox.checked = this.checked;
            checkbox.indeterminate = false;
        });
        
        // Update all counts
        document.querySelectorAll('.select-group-count').forEach(function(countElement) {
            const groupName = countElement.id.replace('count_', '');
            updateSelectedCount(groupName);
        });
    });
    
    // Group selector checkboxes
    document.querySelectorAll('.group-selector').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const group = this.dataset.group;
            const isChecked = this.checked;
            
            document.querySelectorAll(`.permission-checkbox[data-group="${group}"]`).forEach(permCheckbox => {
                permCheckbox.checked = isChecked;
            });
            
            updateSelectedCount(group);
            updateSelectAllCheckbox();
        });
    });
    
    // Permission checkboxes
    document.querySelectorAll('.permission-checkbox').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const group = this.dataset.group;
            updateSelectedCount(group);
            updateSelectAllCheckbox();
        });
    });
    
    // Update select all checkbox state
    function updateSelectAllCheckbox() {
        const totalPermissions = document.querySelectorAll('.permission-checkbox').length;
        const selectedPermissions = document.querySelectorAll('.permission-checkbox:checked').length;
        
        const selectAllCheckbox = document.getElementById('select-all-permissions');
        
        if (selectedPermissions === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (selectedPermissions === totalPermissions) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }
    }
    
    // Initialize select all checkbox state
    updateSelectAllCheckbox();
    
    // Select all modules checkbox
    document.getElementById('select-all-modules').addEventListener('change', function() {
        document.querySelectorAll('.module-visibility').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
    
    // Update select all modules when individuals change
    document.querySelectorAll('.module-visibility').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const allModules = document.querySelectorAll('.module-visibility').length;
            const checkedModules = document.querySelectorAll('.module-visibility:checked').length;
            
            const selectAllModules = document.getElementById('select-all-modules');
            if (checkedModules === 0) {
                selectAllModules.checked = false;
                selectAllModules.indeterminate = false;
            } else if (checkedModules === allModules) {
                selectAllModules.checked = true;
                selectAllModules.indeterminate = false;
            } else {
                selectAllModules.checked = false;
                selectAllModules.indeterminate = true;
            }
        });
    });
    
    // Initialize select all modules state
    const totalModules = document.querySelectorAll('.module-visibility').length;
    const checkedModules = document.querySelectorAll('.module-visibility:checked').length;
    
    const selectAllModules = document.getElementById('select-all-modules');
    if (checkedModules === 0) {
        selectAllModules.checked = false;
        selectAllModules.indeterminate = false;
    } else if (checkedModules === totalModules) {
        selectAllModules.checked = true;
        selectAllModules.indeterminate = false;
    } else {
        selectAllModules.checked = false;
        selectAllModules.indeterminate = true;
    }
});
</script>

<?php require_once 'components/footer.php'; ?>
