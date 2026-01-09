<?php
/**
 * AbroadWorks Management System - Add New Role
 * 
 * @author ikinciadam@gmail.com
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user has permission to manage roles
check_page_access('roles-manage');

$page_title = 'Add Role';
$success_message = '';
$error_message = '';

// Get all permissions grouped by module (same as role-edit.php)
$permission_modules = get_permissions_by_module();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = clean_input($_POST['name']);
    $description = clean_input($_POST['description']);
    $selected_permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
    
    // Validate input
    if (empty($name)) {
        $error_message = "Role name is required.";
    } else {
        // Check if role name already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM roles WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
            $error_message = "Role name already exists.";
        } else {
            try {
                // Begin transaction
                $db->beginTransaction();
                
                // Insert role
                $stmt = $db->prepare("INSERT INTO roles (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                $stmt->execute([$name, $description]);
                
                // Get the new role ID
                $role_id = $db->lastInsertId();
                
                // Assign permissions to the role
                foreach ($selected_permissions as $permission_id) {
                    $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                    $stmt->execute([$role_id, $permission_id]);
                }
                
                // Commit transaction
                $db->commit();
                
                // Log the activity
                log_activity($_SESSION['user_id'], 'create', 'role', "Created new role: $name (ID: $role_id)");
                
                $success_message = "Role created successfully!";
                
                // Clear form data on success
                $name = $description = '';
                $selected_permissions = [];
            } catch (PDOException $e) {
                // Rollback transaction on error
                $db->rollBack();
                $error_message = "Error creating role: " . $e->getMessage();
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
                    <h1 class="m-0 text-primary">Add New Role</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="roles.php">Roles</a></li>
                        <li class="breadcrumb-item active">Add Role</li>
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
            
            <form method="post" class="needs-validation" novalidate>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Role Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Role Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required>
                                    <div class="invalid-feedback">Please enter a role name.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($description) ? htmlspecialchars($description) : ''; ?></textarea>
                                    <small class="form-text text-muted">Brief description of the role's purpose.</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mt-3">
                            <div class="card-header">
                                <h3 class="card-title">Actions</h3>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Role
                                    </button>
                                    <a href="roles.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-1"></i> Cancel
                                    </a>
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
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                                    data-bs-target="#collapse_<?php echo $module_code; ?>"
                                                    aria-expanded="false" aria-controls="collapse_<?php echo $module_code; ?>">
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
                                                                                    <?php echo in_array($permission['id'], $selected_permissions ?? []) ? 'checked' : ''; ?>>
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
});
</script>

<?php require_once 'components/footer.php'; ?>
