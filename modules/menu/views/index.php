<?php
/**
 * AbroadWorks Management System - Menu View
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
                    <h1 class="m-0"><?php echo $page_title; ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active"><?php echo $page_title; ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <?php if (has_message()): ?>
                <div class="alert alert-<?php echo get_message_type(); ?> alert-dismissible fade show">
                    <?php echo get_message(); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Menu Items</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMenuItemModal">
                                    <i class="fas fa-plus"></i> Add New Menu Item
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;"><i class="fas fa-arrows-alt"></i></th>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>URL</th>
                                        <th>Icon</th>
                                        <th>Permission</th>
                                        <th>Order</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="sortable-menu">
                                    <?php 
                                    function renderMenuItems($items, $level = 0) {
                                        foreach ($items as $item): 
                                            $padding = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
                                            $isMainMenu = $item['parent_id'] === null;
                                            $rowClass = $isMainMenu ? 'main-menu-item' : '';
                                    ?>
                                        <tr data-id="<?php echo $item['id']; ?>" class="<?php echo $rowClass; ?>">
                                            <td><i class="fas fa-grip-vertical drag-handle" style="cursor: move;"></i></td>
                                            <td><?php echo $item['id']; ?></td>
                                            <td class="menu-name"><?php echo $padding . ($level > 0 ? '└ ' : '') . htmlspecialchars($item['name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['url']); ?></td>
                                            <td><i class="fas <?php echo $item['icon']; ?>"></i> <?php echo $item['icon']; ?></td>
                                            <td><?php echo $item['permission'] ? htmlspecialchars($item['permission']) : '<span class="text-muted">None</span>'; ?></td>
                                            <td><?php echo $item['display_order']; ?></td>
                                            <td>
                                                <?php if ($item['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-info btn-sm edit-menu-item" 
                                                        data-id="<?php echo $item['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                        data-url="<?php echo htmlspecialchars($item['url']); ?>"
                                                        data-icon="<?php echo $item['icon']; ?>"
                                                        data-permission="<?php echo $item['permission']; ?>"
                                                        data-parent-id="<?php echo $item['parent_id']; ?>"
                                                        data-display-order="<?php echo $item['display_order']; ?>"
                                                        data-is-active="<?php echo $item['is_active']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#editMenuItemModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm delete-menu-item" 
                                                        data-id="<?php echo $item['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#deleteMenuItemModal">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php 
                                            if (!empty($item['children'])) {
                                                renderMenuItems($item['children'], $level + 1);
                                            }
                                        endforeach; 
                                    }
                                    
                                    renderMenuItems($menu_tree);
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Add Menu Item Modal -->
<div class="modal fade" id="addMenuItemModal" tabindex="-1" role="dialog" aria-labelledby="addMenuItemModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="">
                <input type="hidden" name="action" value="add">
                <input type="hidden" id="icon" name="icon" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="addMenuItemModalLabel">Add New Menu Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="url">URL</label>
                        <input type="text" class="form-control" id="url" name="url" required>
                        <small class="form-text text-muted">Use '#' for submenu</small>
                    </div>
                    <div class="form-group">
                        <label>Icon</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i id="icon-preview" class="fas fa-home"></i></span>
                            </div>
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle icon-btn" data-bs-toggle="dropdown" aria-expanded="false">
                                Select Icon
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end icon-dropdown">
                                <?php foreach ($font_awesome_icons as $icon_class => $icon_name): ?>
                                <li><a class="dropdown-item icon-item" href="#" data-icon="<?php echo $icon_class; ?>">
                                    <i class="<?php echo $icon_class; ?>"></i> <?php echo $icon_name; ?>
                                </a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <small class="form-text text-muted">Select FontAwesome icon</small>
                    </div>
                    <div class="form-group">
                        <label for="permission">Permission</label>
                        <select class="form-control" id="permission" name="permission">
                            <option value="">No Permission Required</option>
                            <?php foreach ($permissions as $permission): ?>
                                <option value="<?php echo $permission['code']; ?>"><?php echo $permission['name']; ?> (<?php echo $permission['code']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="parent_id">Parent Menu</label>
                        <select class="form-control" id="parent_id" name="parent_id">
                            <option value="">Main Menu</option>
                            <?php foreach ($menu_tree as $item): ?>
                                <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="display_order">Order</label>
                        <input type="number" class="form-control" id="display_order" name="display_order" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" checked>
                            <label class="custom-control-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Menu Item Modal -->
<div class="modal fade" id="editMenuItemModal" tabindex="-1" role="dialog" aria-labelledby="editMenuItemModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" id="edit_icon" name="icon" value="">
                <div class="modal-header">
                    <h5 class="modal-title" id="editMenuItemModalLabel">Edit Menu Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_name">Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_url">URL</label>
                        <input type="text" class="form-control" id="edit_url" name="url" required>
                        <small class="form-text text-muted">Use '#' for submenu</small>
                    </div>
                    <div class="form-group">
                        <label>Icon</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i id="edit-icon-preview" class="fas fa-home"></i></span>
                            </div>
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle icon-btn" id="edit-icon-btn" data-bs-toggle="dropdown" aria-expanded="false">
                                Select Icon
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end icon-dropdown">
                                <?php foreach ($font_awesome_icons as $icon_class => $icon_name): ?>
                                <li><a class="dropdown-item edit-icon-item" href="#" data-icon="<?php echo $icon_class; ?>">
                                    <i class="<?php echo $icon_class; ?>"></i> <?php echo $icon_name; ?>
                                </a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <small class="form-text text-muted">Select FontAwesome icon</small>
                    </div>
                    <div class="form-group">
                        <label for="edit_permission">Permission</label>
                        <select class="form-control" id="edit_permission" name="permission">
                            <option value="">No Permission Required</option>
                            <?php foreach ($permissions as $permission): ?>
                                <option value="<?php echo $permission['code']; ?>"><?php echo $permission['name']; ?> (<?php echo $permission['code']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_parent_id">Parent Menu</label>
                        <select class="form-control" id="edit_parent_id" name="parent_id">
                            <option value="">Main Menu</option>
                            <?php foreach ($menu_tree as $item): ?>
                                <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_display_order">Order</label>
                        <input type="number" class="form-control" id="edit_display_order" name="display_order" value="0" min="0">
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="edit_is_active" name="is_active">
                            <label class="custom-control-label" for="edit_is_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Menu Item Modal -->
<div class="modal fade" id="deleteMenuItemModal" tabindex="-1" role="dialog" aria-labelledby="deleteMenuItemModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteMenuItemModalLabel">Delete Menu Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this menu item? <strong id="delete_name"></strong></p>
                    <p class="text-danger">This action cannot be undone!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Make sure jQuery UI is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Check if jQuery is loaded
    if (typeof jQuery === 'undefined') {
        console.error("jQuery is not loaded!");
        alert("jQuery is not loaded. Drag and drop functionality will not work.");
        return;
    }
    
    // Load jQuery UI dynamically
    var script = document.createElement('script');
    script.src = "https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js";
    script.onload = function() {
        console.log("jQuery UI loaded successfully!");
        initSortable();
    };
    script.onerror = function() {
        console.error("Failed to load jQuery UI!");
        alert("Failed to load jQuery UI. Drag and drop functionality will not work.");
    };
    document.head.appendChild(script);
});

function initSortable() {
    // Icon selection for Add form
    $('.icon-item').on('click', function(e) {
        e.preventDefault();
        var iconClass = $(this).data('icon');
        $('#icon').val(iconClass);
        $('#icon-preview').attr('class', iconClass);
        $('.icon-btn').text($(this).text().trim());
    });
    
    // Icon selection for Edit form
    $('.edit-icon-item').on('click', function(e) {
        e.preventDefault();
        var iconClass = $(this).data('icon');
        $('#edit_icon').val(iconClass);
        $('#edit-icon-preview').attr('class', iconClass);
        $('#edit-icon-btn').text($(this).text().trim());
    });
    
    // Drag and drop sorting
    $(".sortable-menu").sortable({
        items: "tr",
        handle: ".drag-handle",
        axis: "y",
        helper: function(e, tr) {
            var $originals = tr.children();
            var $helper = tr.clone();
            $helper.children().each(function(index) {
                $(this).width($originals.eq(index).width());
            });
            return $helper;
        },
        placeholder: "ui-state-highlight",
        start: function(e, ui) {
            ui.placeholder.height(ui.item.height());
            console.log("Drag started");
        },
        stop: function(e, ui) {
            console.log("Drag stopped");
        },
        update: function(event, ui) {
            console.log("Order updated, saving new order...");
            // Save new order when sorting changes
            var items = [];
            $(this).find("tr").each(function(index) {
                var id = $(this).data("id");
                if (id) {
                    items.push({
                        id: id,
                        order: index + 1
                    });
                }
            });
            
            console.log("Items to update:", items);
            
            // Update order via AJAX
            $.ajax({
                url: window.location.href,
                method: "POST",
                data: {
                    action: "reorder",
                    items: JSON.stringify(items)
                },
                success: function(response) {
                    console.log("AJAX success:", response);
                    // Show success message
                    alert("Menu order updated successfully.");
                },
                error: function(xhr, status, error) {
                    console.error("AJAX error:", status, error);
                    alert("An error occurred while updating the menu order: " + error);
                }
            });
        }
    }).disableSelection();
    
    console.log("Sortable initialized");
    
    // Edit menu item
    $('.edit-menu-item').on('click', function() {
        $('#edit_id').val($(this).data('id'));
        $('#edit_name').val($(this).data('name'));
        $('#edit_url').val($(this).data('url'));
        
        // Set icon and update preview
        var iconClass = $(this).data('icon');
        $('#edit_icon').val(iconClass);
        if (iconClass) {
            $('#edit-icon-preview').attr('class', iconClass);
            // Find the matching icon text
            var iconText = '';
            $('.edit-icon-item').each(function() {
                if ($(this).data('icon') === iconClass) {
                    iconText = $(this).text().trim();
                    return false; // break the loop
                }
            });
            $('#edit-icon-btn').text(iconText || 'Select Icon');
        } else {
            $('#edit-icon-preview').attr('class', 'fas fa-home');
        }
        
        // Permission select
        var permissionValue = $(this).data('permission');
        $('#edit_permission').val(permissionValue);
        
        // Parent select
        var parentValue = $(this).data('parent-id');
        $('#edit_parent_id').val(parentValue);
        
        $('#edit_display_order').val($(this).data('display-order'));
        $('#edit_is_active').prop('checked', $(this).data('is-active') == 1);
    });
    
    // Delete menu item
    $('.delete-menu-item').on('click', function() {
        $('#delete_id').val($(this).data('id'));
        $('#delete_name').text($(this).data('name'));
    });
}
</script>

<style>
    .sortable-menu tr.ui-sortable-helper {
        display: table-row;
        border: 1px dashed #ccc;
        background-color: #f9f9f9;
        opacity: 0.8;
    }
    .sortable-menu tr.ui-sortable-placeholder {
        visibility: visible !important;
        background-color: #f0f0f0;
        height: 40px;
    }
    .drag-handle {
        cursor: move;
        color: #999;
    }
    .drag-handle:hover {
        color: #333;
    }
    
    /* Icon dropdown styles */
    .icon-dropdown {
        max-height: 300px;
        overflow-y: auto;
        width: 250px;
    }
    .icon-dropdown .dropdown-item {
        display: flex;
        align-items: center;
        padding: 8px 16px;
    }
    .icon-dropdown .dropdown-item i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
    }
    .icon-dropdown .dropdown-item:hover {
        background-color: #f8f9fa;
    }
    .icon-preview {
        width: 20px;
        text-align: center;
    }
    .icon-btn {
        text-align: left;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>
