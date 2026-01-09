<?php
/**
 * AbroadWorks Management System - Communication Types Management
 * 
 * @author ikinciadam@gmail.com
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user has permission to manage vendors
check_page_access('vendors-manage');

$page_title = 'Communication Types';
$success_message = '';
$error_message = '';

// Handle type creation
if (isset($_POST['add_type'])) {
    $name = clean_input($_POST['name']);
    $icon = clean_input($_POST['icon']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name)) {
        $error_message = "Name is required.";
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO communication_types (name, icon, is_active, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$name, $icon, $is_active]);
            
            $success_message = "Communication type added successfully.";
            log_activity($_SESSION['user_id'], 'create', 'communication_type', "Added communication type: $name");
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error_message = "A type with this name already exists.";
            } else {
                $error_message = "Error adding type: " . $e->getMessage();
            }
        }
    }
}

// Handle type update
if (isset($_POST['edit_type'])) {
    $type_id = (int)$_POST['type_id'];
    $name = clean_input($_POST['name']);
    $icon = clean_input($_POST['icon']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name)) {
        $error_message = "Name is required.";
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE communication_types 
                SET name = ?, icon = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $icon, $is_active, $type_id]);
            
            $success_message = "Communication type updated successfully.";
            log_activity($_SESSION['user_id'], 'update', 'communication_type', "Updated communication type ID: $type_id");
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error_message = "A type with this name already exists.";
            } else {
                $error_message = "Error updating type: " . $e->getMessage();
            }
        }
    }
}

// Handle type deletion
if (isset($_POST['delete_type'])) {
    $type_id = (int)$_POST['type_id'];
    
    try {
        // Check if type is used in communications
        $stmt = $db->prepare("SELECT COUNT(*) FROM vendor_communications WHERE type = (SELECT name FROM communication_types WHERE id = ?)");
        $stmt->execute([$type_id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $error_message = "Cannot delete this type because it is used in $count communication records.";
        } else {
            $stmt = $db->prepare("DELETE FROM communication_types WHERE id = ?");
            $stmt->execute([$type_id]);
            
            $success_message = "Communication type deleted successfully.";
            log_activity($_SESSION['user_id'], 'delete', 'communication_type', "Deleted communication type ID: $type_id");
        }
    } catch (PDOException $e) {
        $error_message = "Error deleting type: " . $e->getMessage();
    }
}

// Get all communication types
$types = [];
try {
    $stmt = $db->query("SELECT * FROM communication_types ORDER BY name");
    $types = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error retrieving communication types: " . $e->getMessage();
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
                    <h1 class="m-0 text-primary">Communication Types</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="vendors.php">Vendors</a></li>
                        <li class="breadcrumb-item active">Communication Types</li>
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
            
            <div class="row">
                <div class="col-md-4">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Add New Type</h3>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Type Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="icon" class="form-label">Icon (FontAwesome)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-comments" id="icon_preview"></i></span>
                                        <input type="text" class="form-control" id="icon" name="icon" placeholder="fa-comments">
                                    </div>
                                    <small class="form-text text-muted">Enter FontAwesome icon class (e.g., fa-comments)</small>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                                
                                <button type="submit" name="add_type" class="btn btn-primary">Add Type</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Existing Communication Types</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($types)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No communication types have been defined yet.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Icon</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($types as $type): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($type['name']); ?></td>
                                                    <td>
                                                        <?php if (!empty($type['icon'])): ?>
                                                            <i class="fas <?php echo htmlspecialchars($type['icon']); ?>"></i> 
                                                            <?php echo htmlspecialchars($type['icon']); ?>
                                                        <?php else: ?>
                                                            <i>Default icon</i>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($type['is_active']): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <button type="button" class="btn btn-sm btn-primary edit-type-btn" 
                                                                    data-id="<?php echo $type['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($type['name']); ?>"
                                                                    data-icon="<?php echo htmlspecialchars($type['icon']); ?>"
                                                                    data-active="<?php echo $type['is_active']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            
                                                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this communication type? This may affect existing communications.');">
                                                                <input type="hidden" name="type_id" value="<?php echo $type['id']; ?>">
                                                                <button type="submit" name="delete_type" class="btn btn-sm btn-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Type Modal -->
<div class="modal fade" id="editTypeModal" tabindex="-1" aria-labelledby="editTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTypeModalLabel">Edit Communication Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_type_id" name="type_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Type Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_icon" class="form-label">Icon (FontAwesome)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-comments" id="edit_icon_preview"></i></span>
                            <input type="text" class="form-control" id="edit_icon" name="icon" placeholder="fa-comments">
                        </div>
                        <small class="form-text text-muted">Enter FontAwesome icon class (e.g., fa-comments)</small>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_type" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle icon preview
        const iconInput = document.getElementById('icon');
        const iconPreview = document.getElementById('icon_preview');
        
        iconInput.addEventListener('input', function() {
            updateIconPreview(this.value, iconPreview);
        });
        
        const editIconInput = document.getElementById('edit_icon');
        const editIconPreview = document.getElementById('edit_icon_preview');
        
        editIconInput.addEventListener('input', function() {
            updateIconPreview(this.value, editIconPreview);
        });
        
        function updateIconPreview(iconClass, previewElement) {
            previewElement.className = 'fas ' + (iconClass || 'fa-comments');
        }
        
        // Handle edit modal
        const editButtons = document.querySelectorAll('.edit-type-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const icon = this.getAttribute('data-icon');
                const active = this.getAttribute('data-active') === '1';
                
                document.getElementById('edit_type_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_icon').value = icon;
                document.getElementById('edit_is_active').checked = active;
                
                updateIconPreview(icon, editIconPreview);
                
                const modal = new bootstrap.Modal(document.getElementById('editTypeModal'));
                modal.show();
            });
        });
    });
</script>

<?php require_once 'components/footer.php'; ?>
