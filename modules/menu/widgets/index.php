<?php
/**
 * AbroadWorks Management System - Menu Widget
 * 
 * @author ikinciadam@gmail.com
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access is not allowed.');
}

// Check if user has permission to view this widget
if (!has_permission('menu-widget-view')) {
    return;
}

// Include model if not already included
if (!class_exists('Menu')) {
    require_once $root_path . 'modules/menu/models/Menu.php';
}

// Initialize model
$menuModel = new Menu();

// Get menu items
$menu_items = $menuModel->getAllMenuItems();

// Count active and inactive menu items
$active_count = 0;
$inactive_count = 0;
$main_menu_count = 0;
$submenu_count = 0;

foreach ($menu_items as $item) {
    if ($item['is_active']) {
        $active_count++;
    } else {
        $inactive_count++;
    }
    
    if ($item['parent_id'] === null) {
        $main_menu_count++;
    } else {
        $submenu_count++;
    }
}

// Get total count
$total_count = count($menu_items);
?>

<div class="col-md-6 mb-4">
    <div class="card h-100">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-bars me-2"></i> Menu Management
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <span class="info-box-text text-center text-muted">Total Menu Items</span>
                            <span class="info-box-number text-center text-muted mb-0"><?php echo $total_count; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <span class="info-box-text text-center text-muted">Active Items</span>
                            <span class="info-box-number text-center text-success mb-0"><?php echo $active_count; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <span class="info-box-text text-center text-muted">Main Menu</span>
                            <span class="info-box-number text-center text-primary mb-0"><?php echo $main_menu_count; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-box bg-light">
                        <div class="info-box-content">
                            <span class="info-box-text text-center text-muted">Submenus</span>
                            <span class="info-box-number text-center text-info mb-0"><?php echo $submenu_count; ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($menu_items)): ?>
                <div class="mt-4">
                    <h6 class="text-muted">Recent Menu Items</h6>
                    <ul class="list-group list-group-flush">
                        <?php 
                        // Sort menu items by ID (descending) to get the most recent ones
                        usort($menu_items, function($a, $b) {
                            return $b['id'] - $a['id'];
                        });
                        
                        // Display the 5 most recent menu items
                        foreach (array_slice($menu_items, 0, 5) as $item): 
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="<?php echo $item['icon']; ?> me-2"></i>
                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                    <small class="text-muted d-block"><?php echo htmlspecialchars($item['url']); ?></small>
                                </div>
                                <div>
                                    <?php if ($item['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer text-center">
            <a href="<?php echo $root_path; ?>modules/menu/" class="btn btn-sm btn-outline-primary">Manage Menu</a>
            <?php if (has_permission('menu-manage')): ?>
                <a href="<?php echo $root_path; ?>modules/menu/#addMenuItemModal" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addMenuItemModal">Add New Item</a>
            <?php endif; ?>
        </div>
    </div>
</div>
