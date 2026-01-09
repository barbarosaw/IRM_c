<?php
/**
 * Module Management Module - Main View
 */
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
                        <li class="breadcrumb-item"><a href="../../index.php">Home</a></li>
                        <li class="breadcrumb-item active">Module Management</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="content">
        <div class="container-fluid">
            <?php 
            // Process result messages
            if (isset($_GET['result'])) {
                $resultType = $_GET['result'];
                $alertClass = 'alert-success';
                $message = '';
                
                switch ($resultType) {
                    case 'created':
                        $name = htmlspecialchars($_GET['name'] ?? 'Module');
                        $message = "Module '{$name}' has been successfully created.";
                        break;
                    case 'deleted':
                        $name = htmlspecialchars($_GET['name'] ?? 'Module');
                        $message = "Module '{$name}' has been successfully deleted.";
                        break;
                    case 'activated':
                        $message = "Module has been successfully activated.";
                        break;
                    case 'deactivated':
                        $message = "Module has been successfully deactivated.";
                        break;
                    default:
                        $alertClass = 'alert-warning';
                        $message = "Operation completed.";
                }
                
                echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
                echo $message;
                echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                echo '</div>';
            }
            ?>

            <?php if (isset($success_message) && $success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($error_message) && $error_message): ?>
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
                                <?php if (!empty($modules)): ?>
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
                                                <?php if (file_exists("../{$module['code']}/index.php")): ?>
                                                    <a href="../<?php echo $module['code']; ?>/" class="btn btn-sm btn-info ms-1">
                                                        <i class="fas fa-external-link-alt me-1"></i> Open
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-sm btn-danger ms-1 delete-module-btn" data-module-id="<?php echo $module['id']; ?>" data-module-name="<?php echo htmlspecialchars($module['name']); ?>">
                                                    <i class="fas fa-trash me-1"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
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
                        <input type="text" class="form-control" id="module_icon" name="module_icon" placeholder="fa-users" data-icon-picker>
                        <div class="form-text">Choose a FontAwesome icon for the module</div>
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
<!-- Delete Module Modal -->
<div class="modal fade" id="deleteModuleModal" tabindex="-1" aria-labelledby="deleteModuleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="deleteModuleForm">
                <input type="hidden" name="delete_module_id" id="delete_module_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModuleModalLabel">Delete Module</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="delete-step-1">
                        <p class="text-danger fw-bold">Are you sure you want to delete this module? This action cannot be undone.</p>
                        <button type="button" class="btn btn-danger w-100" id="delete-step-1-next">Yes, continue</button>
                    </div>
                    <div id="delete-step-2" style="display:none;">
                        <p class="text-danger">Please type the module name to confirm deletion:</p>
                        <input type="text" class="form-control mb-2" id="delete_module_name_input" name="delete_module_name_input" autocomplete="off">
                        <div class="alert alert-warning" id="delete_module_name_warning" style="display:none;"></div>
                        <button type="submit" class="btn btn-danger w-100">Delete Module</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const moduleNameInput = document.getElementById('module_name');
    const moduleCodeInput = document.getElementById('module_code');
    
    // Auto-generate module code from name
    moduleNameInput.addEventListener('input', function() {
        const moduleName = this.value;
        const moduleCode = moduleName.toLowerCase()
            .replace(/\s+/g, '_')
            .replace(/[^a-z0-9_]/g, '');
        moduleCodeInput.value = moduleCode;
    });

    // Delete module functionality
    let deleteModuleId = null;
    let deleteModuleName = '';
    document.querySelectorAll('.delete-module-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            deleteModuleId = this.getAttribute('data-module-id');
            deleteModuleName = this.getAttribute('data-module-name');
            document.getElementById('delete_module_id').value = deleteModuleId;
            document.getElementById('delete-step-1').style.display = '';
            document.getElementById('delete-step-2').style.display = 'none';
            document.getElementById('delete_module_name_input').value = '';
            document.getElementById('delete_module_name_warning').style.display = 'none';
            var modal = new bootstrap.Modal(document.getElementById('deleteModuleModal'));
            modal.show();
        });
    });
    
    document.getElementById('delete-step-1-next').addEventListener('click', function() {
        document.getElementById('delete-step-1').style.display = 'none';
        document.getElementById('delete-step-2').style.display = '';
    });
    
    document.getElementById('deleteModuleForm').addEventListener('submit', function(e) {
        if (document.getElementById('delete-step-2').style.display !== 'none') {
            const inputName = document.getElementById('delete_module_name_input').value.trim();
            if (inputName !== deleteModuleName) {
                e.preventDefault();
                document.getElementById('delete_module_name_warning').textContent = 'Module name must match!';
                document.getElementById('delete_module_name_warning').style.display = '';
                return false;
            }
        }
    });
});
</script>