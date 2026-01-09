<?php
/**
 * TimeWorks Module - Category Management
 *
 * Manage user categories (AbroadWorks Internal, ChabadWorks, Iink, Bulldog, etc.)
 *
 * @author ikinciadam@gmail.com
 */

require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission
if (!has_permission('timeworks_users_manage')) {
    header('Location: ../../access-denied.php');
    exit;
}

$page_title = "TimeWorks - Category Management";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Get all categories
$stmt = $db->query("SELECT * FROM twr_category_definitions WHERE is_active = 1 ORDER BY sort_order");
$categories = $stmt->fetchAll();

// Get users for assignment
$stmt = $db->query("SELECT user_id, full_name, email FROM twr_users WHERE status = 'active' ORDER BY full_name");
$users = $stmt->fetchAll();

// Get category statistics
$categoryStats = [];
$stmt = $db->query("
    SELECT
        cd.id,
        cd.code,
        cd.name,
        cd.color_code,
        COUNT(uc.id) as user_count
    FROM twr_category_definitions cd
    LEFT JOIN twr_user_categories uc ON cd.code = uc.category_code
    WHERE cd.is_active = 1
    GROUP BY cd.id, cd.code, cd.name, cd.color_code
    ORDER BY cd.sort_order
");
$categoryStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../components/header.php';
include '../../components/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-tags mr-2"></i><?= $page_title ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/modules/timeworks/">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Categories</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Category Summary Cards -->
            <div class="row">
                <?php foreach ($categoryStats as $cat): ?>
                <div class="col-lg-3 col-md-4 col-6">
                    <div class="info-box">
                        <span class="info-box-icon" style="background-color: <?= $cat['color_code'] ?: '#6c757d' ?>; color: white;">
                            <i class="fas fa-users"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text"><?= htmlspecialchars($cat['name']) ?></span>
                            <span class="info-box-number"><?= $cat['user_count'] ?> users</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="row">
                <!-- Category Definitions -->
                <div class="col-md-4">
                    <div class="card card-primary">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-list mr-2"></i>Category Definitions</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-bs-toggle="modal" data-bs-target="#newCategoryModal">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Color</th>
                                        <th>Name</th>
                                        <th>Code</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td>
                                            <span class="badge" style="background-color: <?= $cat['color_code'] ?: '#6c757d' ?>; width: 20px; height: 20px; display: inline-block;">&nbsp;</span>
                                        </td>
                                        <td><?= htmlspecialchars($cat['name']) ?></td>
                                        <td><code><?= htmlspecialchars($cat['code']) ?></code></td>
                                        <td>
                                            <button class="btn btn-xs btn-info btn-edit-category" data-id="<?= $cat['id'] ?>" data-name="<?= htmlspecialchars($cat['name']) ?>" data-code="<?= htmlspecialchars($cat['code']) ?>" data-color="<?= $cat['color_code'] ?>" data-description="<?= htmlspecialchars($cat['description'] ?? '') ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- User Category Assignments -->
                <div class="col-md-8">
                    <div class="card card-info">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-user-tag mr-2"></i>User Category Assignments</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-bs-toggle="modal" data-bs-target="#assignCategoryModal">
                                    <i class="fas fa-plus"></i> Assign
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Filters -->
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <select id="filterCategory" class="form-select">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" id="filterSearch" class="form-control" placeholder="Search users...">
                                </div>
                                <div class="col-md-4">
                                    <button id="btnExport" class="btn btn-success">
                                        <i class="fas fa-file-excel mr-1"></i> Export
                                    </button>
                                </div>
                            </div>

                            <table id="assignmentsTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Categories</th>
                                        <th>Referred By</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- New Category Modal -->
<div class="modal fade" id="newCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-plus-circle mr-2"></i>New Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="newCategoryForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="catName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" name="code" id="catCode" class="form-control" required placeholder="e.g., abroadworks_internal">
                        <small class="text-muted">Lowercase, underscores allowed</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="color" name="color_code" id="catColor" class="form-control form-control-color" value="#007bff">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="catDescription" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editCategoryForm">
                <input type="hidden" name="id" id="editCatId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editCatName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Code</label>
                        <input type="text" name="code" id="editCatCode" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Color</label>
                        <input type="color" name="color_code" id="editCatColor" class="form-control form-control-color">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editCatDescription" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Category Modal -->
<div class="modal fade" id="assignCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-user-tag mr-2"></i>Assign Category to User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="assignCategoryForm">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">User <span class="text-danger">*</span></label>
                                <select name="user_id" id="assignUserId" class="form-select select2" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?= htmlspecialchars($user['user_id']) ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                <select name="category_id" id="assignCategoryId" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Referred By</label>
                                <input type="text" name="referred_by" id="assignReferredBy" class="form-control" placeholder="e.g., Yuki, Red">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Client (if applicable)</label>
                                <select name="client_id" id="assignClientId" class="form-select select2">
                                    <option value="">No Client</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" id="assignNotes" class="form-control" rows="2" placeholder="Additional notes about this assignment..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Assign Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#assignCategoryModal')
    });

    // DataTable
    const table = $('#assignmentsTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: 'api/categories.php',
            type: 'POST',
            data: function(d) {
                return {
                    action: 'list_assignments',
                    category_id: $('#filterCategory').val(),
                    search: $('#filterSearch').val()
                };
            },
            dataSrc: function(json) {
                return json.data || [];
            }
        },
        columns: [
            { data: 'full_name' },
            {
                data: 'categories',
                render: function(data) {
                    if (!data || data.length === 0) return '<span class="text-muted">None</span>';
                    return data.map(c => `<span class="badge" style="background-color: ${c.color_code || '#6c757d'}">${c.name}</span>`).join(' ');
                }
            },
            {
                data: 'referred_by',
                render: function(data) {
                    return data || '<span class="text-muted">-</span>';
                }
            },
            {
                data: 'notes',
                render: function(data) {
                    if (!data) return '<span class="text-muted">-</span>';
                    return data.length > 50 ? data.substring(0, 50) + '...' : data;
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return `
                        <button class="btn btn-sm btn-danger btn-remove-category" data-user-id="${row.user_id}" title="Remove Categories">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                }
            }
        ],
        order: [[0, 'asc']],
        pageLength: 25
    });

    // Filter handlers
    $('#filterCategory, #filterSearch').on('change keyup', function() {
        table.ajax.reload();
    });

    // Auto-generate code from name
    $('#catName').on('keyup', function() {
        const code = $(this).val()
            .toLowerCase()
            .replace(/[^a-z0-9\s]/g, '')
            .replace(/\s+/g, '_');
        $('#catCode').val(code);
    });

    // New category form
    $('#newCategoryForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: 'api/categories.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'create_category',
                name: $('#catName').val(),
                code: $('#catCode').val(),
                color_code: $('#catColor').val(),
                description: $('#catDescription').val()
            }),
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success', 'Category created successfully', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }
        });
    });

    // Edit category button
    $('.btn-edit-category').on('click', function() {
        $('#editCatId').val($(this).data('id'));
        $('#editCatName').val($(this).data('name'));
        $('#editCatCode').val($(this).data('code'));
        $('#editCatColor').val($(this).data('color') || '#007bff');
        $('#editCatDescription').val($(this).data('description'));
        $('#editCategoryModal').modal('show');
    });

    // Edit category form
    $('#editCategoryForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: 'api/categories.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'update_category',
                id: $('#editCatId').val(),
                name: $('#editCatName').val(),
                color_code: $('#editCatColor').val(),
                description: $('#editCatDescription').val()
            }),
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success', 'Category updated successfully', 'success').then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }
        });
    });

    // Assign category form
    $('#assignCategoryForm').on('submit', function(e) {
        e.preventDefault();

        $.ajax({
            url: 'api/categories.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                action: 'assign',
                user_id: $('#assignUserId').val(),
                category_id: $('#assignCategoryId').val(),
                referred_by: $('#assignReferredBy').val(),
                client_id: $('#assignClientId').val(),
                notes: $('#assignNotes').val()
            }),
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success', 'Category assigned successfully', 'success');
                    $('#assignCategoryModal').modal('hide');
                    $('#assignCategoryForm')[0].reset();
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            }
        });
    });

    // Remove category
    $(document).on('click', '.btn-remove-category', function() {
        const userId = $(this).data('user-id');

        Swal.fire({
            title: 'Remove Categories',
            text: 'Remove all category assignments for this user?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'api/categories.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        action: 'remove_all',
                        user_id: userId
                    }),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Removed', 'Categories removed', 'success');
                            table.ajax.reload();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // Export
    $('#btnExport').on('click', function() {
        window.location.href = 'api/categories.php?action=export&category_id=' + $('#filterCategory').val();
    });
});
</script>

<?php include '../../components/footer.php'; ?>
