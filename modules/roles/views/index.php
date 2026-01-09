<?php
/**
 * AbroadWorks Management System - Roles View
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Roles Management</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>index.php">Home</a></li>
                        <li class="breadcrumb-item active">Roles</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if (has_message()): ?>
                <div class="alert alert-<?php echo get_message_type(); ?> alert-dismissible fade show" role="alert">
                    <?php echo get_message(); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3 class="card-title">Role List</h3>
                        <?php if (has_permission('roles-manage')): ?>
                            <a href="<?php echo $root_path; ?>role-add.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add New Role
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped datatable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Role Name</th>
                                    <th>Description</th>
                                    <th>Permissions</th>
                                    <th>Users</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($roles as $role): ?>
                                <tr>
                                    <td><?php echo $role['id']; ?></td>
                                    <td><?php echo htmlspecialchars($role['name']); ?></td>
                                    <td><?php echo htmlspecialchars($role['description'] ?? ''); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $role['permission_count']; ?> permissions</span>
                                    </td>
                                    <td>
                                        <?php if ($role['user_count'] > 0): ?>
                                            <a href="<?php echo $root_path; ?>modules/users/?role=<?php echo $role['id']; ?>" class="badge bg-primary">
                                                <?php echo $role['user_count']; ?> users
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">0 users</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($role['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="<?php echo $root_path; ?>role-edit.php?id=<?php echo $role['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="Edit Role">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <?php if ($role['user_count'] == 0 && has_permission('roles-manage')): ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this role? This action cannot be undone.');">
                                                    <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                                    <button type="submit" name="delete_role" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="Delete Role">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable
    if ($.fn.dataTable.isDataTable('.datatable')) {
        $('.datatable').DataTable().destroy();
    }
    
    $('.datatable').DataTable({
        "paging": true,
        "lengthChange": true,
        "searching": true,
        "ordering": true,
        "info": true,
        "autoWidth": false,
        "responsive": true
    });
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>
