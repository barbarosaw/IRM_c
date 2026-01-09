<?php
/**
 * AbroadWorks Management System - Menu Module
 * 
 * @author ikinciadam@gmail.com
 */

// Define system constant to prevent direct access to module files
define('AW_SYSTEM', true);

// Include required files
require_once '../../includes/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check if user has access to this module
if (!has_module_access('menu')) {
    header('Location: ../../access-denied.php');
    exit;
}

// Check if module is active
$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = 'menu'");
$stmt->execute();
$is_active = $stmt->fetchColumn();

// If module is not active and user is not an owner, redirect to module-inactive page
if ($is_active === false || $is_active == 0) {
    if (!isset($_SESSION['is_owner']) || !$_SESSION['is_owner']) {
        header('Location: ../../module-inactive.php?module=menu');
        exit;
    }
}

// Include model
require_once 'models/Menu.php';

// Initialize model
$menuModel = new Menu();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle menu item creation/update
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'reorder' && isset($_POST['items'])) {
            // Handle menu reordering via AJAX
            $items = json_decode($_POST['items'], true);
            
            if (is_array($items)) {
                if ($menuModel->updateMenuOrder($items)) {
                    // Log activity
                    log_activity($_SESSION['user_id'], 'update', 'menu_item', "Reordered menu items");
                    
                    // For AJAX requests, return success
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        echo json_encode(['success' => true]);
                        exit;
                    }
                    
                    show_message("Menu order updated successfully.", "success");
                } else {
                    // For AJAX requests, return error
                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        echo json_encode(['success' => false, 'message' => "Failed to update menu order."]);
                        exit;
                    }
                    
                    show_message("An error occurred while updating the menu order.", "danger");
                }
            }
        } else if ($_POST['action'] === 'add') {
            $name = clean_input($_POST['name']);
            $url = clean_input($_POST['url']);
            $icon = clean_input($_POST['icon']);
            $permission = !empty($_POST['permission']) ? clean_input($_POST['permission']) : null;
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $display_order = (int)$_POST['display_order'];
            $is_active = isset($_POST['is_active']) ? true : false;
            
            if ($menuModel->addMenuItem($name, $url, $icon, $permission, $parent_id, $display_order, $is_active)) {
                show_message("Menu item added successfully.", "success");
                log_activity($_SESSION['user_id'], 'create', 'menu_item', "Added menu item: $name");
            } else {
                show_message("Failed to add menu item.", "danger");
            }
        } else if ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $name = clean_input($_POST['name']);
            $url = clean_input($_POST['url']);
            $icon = clean_input($_POST['icon']);
            $permission = !empty($_POST['permission']) ? clean_input($_POST['permission']) : null;
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
            $display_order = (int)$_POST['display_order'];
            $is_active = isset($_POST['is_active']) ? true : false;
            
            if ($menuModel->updateMenuItem($id, $name, $url, $icon, $permission, $parent_id, $display_order, $is_active)) {
                show_message("Menu item updated successfully.", "success");
                log_activity($_SESSION['user_id'], 'update', 'menu_item', "Updated menu item ID: $id");
            } else {
                show_message("Failed to update menu item.", "danger");
            }
        } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            
            if ($menuModel->hasChildren($id)) {
                show_message("This menu item cannot be deleted because it has child items. Please delete the child items first.", "danger");
            } else if ($menuModel->deleteMenuItem($id)) {
                show_message("Menu item deleted successfully.", "success");
                log_activity($_SESSION['user_id'], 'delete', 'menu_item', "Deleted menu item ID: $id");
            } else {
                show_message("Failed to delete menu item.", "danger");
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: index.php");
    exit;
}

// Get menu items as a tree
$menu_tree = $menuModel->getMenuTree();

// Get all menu items (flat list)
$menu_items = $menuModel->getAllMenuItems();

// Get all permissions for dropdown
$permissions = $menuModel->getAllPermissions();

// Font Awesome icons array
$font_awesome_icons = [
    // Interface & UI
    'fas fa-home' => 'fa-home',
    'fas fa-bars' => 'fa-bars',
    'fas fa-hamburger' => 'fa-hamburger',
    'fas fa-ellipsis-h' => 'fa-ellipsis-h',
    'fas fa-ellipsis-v' => 'fa-ellipsis-v',
    'fas fa-th' => 'fa-th',
    'fas fa-th-large' => 'fa-th-large',
    'fas fa-th-list' => 'fa-th-list',
    'fas fa-columns' => 'fa-columns',
    'fas fa-grip-horizontal' => 'fa-grip-horizontal',
    'fas fa-grip-vertical' => 'fa-grip-vertical',
    'fas fa-sliders-h' => 'fa-sliders-h',
    'fas fa-toggle-on' => 'fa-toggle-on',
    'fas fa-toggle-off' => 'fa-toggle-off',
    
    // Users & People
    'fas fa-user' => 'fa-user',
    'fas fa-user-plus' => 'fa-user-plus',
    'fas fa-user-minus' => 'fa-user-minus',
    'fas fa-user-edit' => 'fa-user-edit',
    'fas fa-user-cog' => 'fa-user-cog',
    'fas fa-user-shield' => 'fa-user-shield',
    'fas fa-user-check' => 'fa-user-check',
    'fas fa-user-clock' => 'fa-user-clock',
    'fas fa-user-tie' => 'fa-user-tie',
    'fas fa-users' => 'fa-users',
    'fas fa-users-cog' => 'fa-users-cog',
    'fas fa-user-friends' => 'fa-user-friends',
    'fas fa-user-graduate' => 'fa-user-graduate',
    'fas fa-user-injured' => 'fa-user-injured',
    'fas fa-user-md' => 'fa-user-md',
    
    // Settings & Tools
    'fas fa-cog' => 'fa-cog',
    'fas fa-cogs' => 'fa-cogs',
    'fas fa-wrench' => 'fa-wrench',
    'fas fa-screwdriver' => 'fa-screwdriver',
    'fas fa-tools' => 'fa-tools',
    'fas fa-toolbox' => 'fa-toolbox',
    'fas fa-hammer' => 'fa-hammer',
    
    // Charts & Analytics
    'fas fa-chart-bar' => 'fa-chart-bar',
    'fas fa-chart-line' => 'fa-chart-line',
    'fas fa-chart-pie' => 'fa-chart-pie',
    'fas fa-chart-area' => 'fa-chart-area',
    'fas fa-analytics' => 'fa-analytics',
    'fas fa-tachometer-alt' => 'fa-tachometer-alt',
    'fas fa-project-diagram' => 'fa-project-diagram',
    
    // Files & Documents
    'fas fa-file' => 'fa-file',
    'fas fa-file-alt' => 'fa-file-alt',
    'fas fa-file-pdf' => 'fa-file-pdf',
    'fas fa-file-word' => 'fa-file-word',
    'fas fa-file-excel' => 'fa-file-excel',
    'fas fa-file-powerpoint' => 'fa-file-powerpoint',
    'fas fa-file-image' => 'fa-file-image',
    'fas fa-file-video' => 'fa-file-video',
    'fas fa-file-audio' => 'fa-file-audio',
    'fas fa-file-code' => 'fa-file-code',
    'fas fa-file-archive' => 'fa-file-archive',
    'fas fa-file-contract' => 'fa-file-contract',
    'fas fa-file-csv' => 'fa-file-csv',
    'fas fa-file-download' => 'fa-file-download',
    'fas fa-file-export' => 'fa-file-export',
    'fas fa-file-import' => 'fa-file-import',
    'fas fa-file-invoice' => 'fa-file-invoice',
    'fas fa-file-signature' => 'fa-file-signature',
    'fas fa-file-upload' => 'fa-file-upload',
    
    // Folders
    'fas fa-folder' => 'fa-folder',
    'fas fa-folder-open' => 'fa-folder-open',
    'fas fa-folder-plus' => 'fa-folder-plus',
    'fas fa-folder-minus' => 'fa-folder-minus',
    
    // Communication
    'fas fa-envelope' => 'fa-envelope',
    'fas fa-envelope-open' => 'fa-envelope-open',
    'fas fa-envelope-open-text' => 'fa-envelope-open-text',
    'fas fa-paper-plane' => 'fa-paper-plane',
    'fas fa-comment' => 'fa-comment',
    'fas fa-comment-alt' => 'fa-comment-alt',
    'fas fa-comment-dots' => 'fa-comment-dots',
    'fas fa-comments' => 'fa-comments',
    'fas fa-sms' => 'fa-sms',
    'fas fa-phone' => 'fa-phone',
    'fas fa-phone-alt' => 'fa-phone-alt',
    'fas fa-phone-slash' => 'fa-phone-slash',
    'fas fa-mobile' => 'fa-mobile',
    'fas fa-mobile-alt' => 'fa-mobile-alt',
    
    // Notifications
    'fas fa-bell' => 'fa-bell',
    'fas fa-bell-slash' => 'fa-bell-slash',
    'fas fa-exclamation' => 'fa-exclamation',
    'fas fa-exclamation-circle' => 'fa-exclamation-circle',
    'fas fa-exclamation-triangle' => 'fa-exclamation-triangle',
    'fas fa-info' => 'fa-info',
    'fas fa-info-circle' => 'fa-info-circle',
    'fas fa-question' => 'fa-question',
    'fas fa-question-circle' => 'fa-question-circle',
    
    // Time & Calendar
    'fas fa-calendar' => 'fa-calendar',
    'fas fa-calendar-alt' => 'fa-calendar-alt',
    'fas fa-calendar-check' => 'fa-calendar-check',
    'fas fa-calendar-day' => 'fa-calendar-day',
    'fas fa-calendar-week' => 'fa-calendar-week',
    'fas fa-calendar-plus' => 'fa-calendar-plus',
    'fas fa-calendar-minus' => 'fa-calendar-minus',
    'fas fa-calendar-times' => 'fa-calendar-times',
    'fas fa-clock' => 'fa-clock',
    'fas fa-hourglass' => 'fa-hourglass',
    'fas fa-hourglass-start' => 'fa-hourglass-start',
    'fas fa-hourglass-half' => 'fa-hourglass-half',
    'fas fa-hourglass-end' => 'fa-hourglass-end',
    'fas fa-stopwatch' => 'fa-stopwatch',
    
    // E-commerce
    'fas fa-shopping-cart' => 'fa-shopping-cart',
    'fas fa-shopping-bag' => 'fa-shopping-bag',
    'fas fa-shopping-basket' => 'fa-shopping-basket',
    'fas fa-cart-plus' => 'fa-cart-plus',
    'fas fa-cart-arrow-down' => 'fa-cart-arrow-down',
    'fas fa-cash-register' => 'fa-cash-register',
    'fas fa-store' => 'fa-store',
    'fas fa-store-alt' => 'fa-store-alt',
    
    // Finance
    'fas fa-dollar-sign' => 'fa-dollar-sign',
    'fas fa-euro-sign' => 'fa-euro-sign',
    'fas fa-pound-sign' => 'fa-pound-sign',
    'fas fa-yen-sign' => 'fa-yen-sign',
    'fas fa-ruble-sign' => 'fa-ruble-sign',
    'fas fa-rupee-sign' => 'fa-rupee-sign',
    'fas fa-money-bill' => 'fa-money-bill',
    'fas fa-money-bill-alt' => 'fa-money-bill-alt',
    'fas fa-money-bill-wave' => 'fa-money-bill-wave',
    'fas fa-money-check' => 'fa-money-check',
    'fas fa-money-check-alt' => 'fa-money-check-alt',
    'fas fa-credit-card' => 'fa-credit-card',
    'fas fa-cc-visa' => 'fa-cc-visa',
    'fas fa-cc-mastercard' => 'fa-cc-mastercard',
    'fas fa-cc-amex' => 'fa-cc-amex',
    'fas fa-cc-paypal' => 'fa-cc-paypal',
    'fas fa-wallet' => 'fa-wallet',
    'fas fa-coins' => 'fa-coins',
    'fas fa-piggy-bank' => 'fa-piggy-bank',
    'fas fa-percentage' => 'fa-percentage',
    'fas fa-donate' => 'fa-donate',
    
    // Shipping & Logistics
    'fas fa-truck' => 'fa-truck',
    'fas fa-truck-loading' => 'fa-truck-loading',
    'fas fa-truck-moving' => 'fa-truck-moving',
    'fas fa-shipping-fast' => 'fa-shipping-fast',
    'fas fa-dolly' => 'fa-dolly',
    'fas fa-dolly-flatbed' => 'fa-dolly-flatbed',
    'fas fa-pallet' => 'fa-pallet',
    'fas fa-box' => 'fa-box',
    'fas fa-boxes' => 'fa-boxes',
    'fas fa-box-open' => 'fa-box-open',
    'fas fa-warehouse' => 'fa-warehouse',
    
    // Documents & Lists
    'fas fa-clipboard' => 'fa-clipboard',
    'fas fa-clipboard-check' => 'fa-clipboard-check',
    'fas fa-clipboard-list' => 'fa-clipboard-list',
    'fas fa-paste' => 'fa-paste',
    'fas fa-list' => 'fa-list',
    'fas fa-list-alt' => 'fa-list-alt',
    'fas fa-list-ol' => 'fa-list-ol',
    'fas fa-list-ul' => 'fa-list-ul',
    'fas fa-tasks' => 'fa-tasks',
    'fas fa-table' => 'fa-table',
    'fas fa-table-cells' => 'fa-table-cells',
    
    // Editing & Actions
    'fas fa-edit' => 'fa-edit',
    'fas fa-pen' => 'fa-pen',
    'fas fa-pen-alt' => 'fa-pen-alt',
    'fas fa-pen-fancy' => 'fa-pen-fancy',
    'fas fa-pencil-alt' => 'fa-pencil-alt',
    'fas fa-highlighter' => 'fa-highlighter',
    'fas fa-eraser' => 'fa-eraser',
    'fas fa-trash' => 'fa-trash',
    'fas fa-trash-alt' => 'fa-trash-alt',
    'fas fa-trash-restore' => 'fa-trash-restore',
    'fas fa-trash-restore-alt' => 'fa-trash-restore-alt',
    'fas fa-plus' => 'fa-plus',
    'fas fa-plus-circle' => 'fa-plus-circle',
    'fas fa-plus-square' => 'fa-plus-square',
    'fas fa-minus' => 'fa-minus',
    'fas fa-minus-circle' => 'fa-minus-circle',
    'fas fa-minus-square' => 'fa-minus-square',
    'fas fa-times' => 'fa-times',
    'fas fa-times-circle' => 'fa-times-circle',
    'fas fa-search' => 'fa-search',
    'fas fa-search-plus' => 'fa-search-plus',
    'fas fa-search-minus' => 'fa-search-minus',
    'fas fa-search-location' => 'fa-search-location',
    'fas fa-check' => 'fa-check',
    'fas fa-check-circle' => 'fa-check-circle',
    'fas fa-check-square' => 'fa-check-square',
    'fas fa-check-double' => 'fa-check-double',
    
    // Security
    'fas fa-lock' => 'fa-lock',
    'fas fa-lock-open' => 'fa-lock-open',
    'fas fa-unlock' => 'fa-unlock',
    'fas fa-unlock-alt' => 'fa-unlock-alt',
    'fas fa-key' => 'fa-key',
    'fas fa-fingerprint' => 'fa-fingerprint',
    'fas fa-user-shield' => 'fa-user-shield',
    'fas fa-user-lock' => 'fa-user-lock',
    'fas fa-shield-alt' => 'fa-shield-alt',
    'fas fa-shield-virus' => 'fa-shield-virus',
    'fas fa-bug' => 'fa-bug',
    'fas fa-virus' => 'fa-virus',
    'fas fa-virus-slash' => 'fa-virus-slash',
    
    // Authentication
    'fas fa-sign-in-alt' => 'fa-sign-in-alt',
    'fas fa-sign-out-alt' => 'fa-sign-out-alt',
    'fas fa-user-secret' => 'fa-user-secret',
    'fas fa-id-badge' => 'fa-id-badge',
    'fas fa-id-card' => 'fa-id-card',
    'fas fa-id-card-alt' => 'fa-id-card-alt',
    'fas fa-passport' => 'fa-passport',
    
    // Arrows & Direction
    'fas fa-arrow-up' => 'fa-arrow-up',
    'fas fa-arrow-down' => 'fa-arrow-down',
    'fas fa-arrow-left' => 'fa-arrow-left',
    'fas fa-arrow-right' => 'fa-arrow-right',
    'fas fa-arrow-circle-up' => 'fa-arrow-circle-up',
    'fas fa-arrow-circle-down' => 'fa-arrow-circle-down',
    'fas fa-arrow-circle-left' => 'fa-arrow-circle-left',
    'fas fa-arrow-circle-right' => 'fa-arrow-circle-right',
    'fas fa-arrows-alt' => 'fa-arrows-alt',
    'fas fa-arrows-alt-h' => 'fa-arrows-alt-h',
    'fas fa-arrows-alt-v' => 'fa-arrows-alt-v',
    'fas fa-chevron-up' => 'fa-chevron-up',
    'fas fa-chevron-down' => 'fa-chevron-down',
    'fas fa-chevron-left' => 'fa-chevron-left',
    'fas fa-chevron-right' => 'fa-chevron-right',
    'fas fa-chevron-circle-up' => 'fa-chevron-circle-up',
    'fas fa-chevron-circle-down' => 'fa-chevron-circle-down',
    'fas fa-chevron-circle-left' => 'fa-chevron-circle-left',
    'fas fa-chevron-circle-right' => 'fa-chevron-circle-right',
    'fas fa-angle-up' => 'fa-angle-up',
    'fas fa-angle-down' => 'fa-angle-down',
    'fas fa-angle-left' => 'fa-angle-left',
    'fas fa-angle-right' => 'fa-angle-right',
    'fas fa-angle-double-up' => 'fa-angle-double-up',
    'fas fa-angle-double-down' => 'fa-angle-double-down',
    'fas fa-angle-double-left' => 'fa-angle-double-left',
    'fas fa-angle-double-right' => 'fa-angle-double-right',
    'fas fa-caret-up' => 'fa-caret-up',
    'fas fa-caret-down' => 'fa-caret-down',
    'fas fa-caret-left' => 'fa-caret-left',
    'fas fa-caret-right' => 'fa-caret-right',
    'fas fa-caret-square-up' => 'fa-caret-square-up',
    'fas fa-caret-square-down' => 'fa-caret-square-down',
    'fas fa-caret-square-left' => 'fa-caret-square-left',
    'fas fa-caret-square-right' => 'fa-caret-square-right',
    'fas fa-expand' => 'fa-expand',
    'fas fa-compress' => 'fa-compress',
    'fas fa-expand-alt' => 'fa-expand-alt',
    'fas fa-compress-alt' => 'fa-compress-alt',
    'fas fa-expand-arrows-alt' => 'fa-expand-arrows-alt',
    'fas fa-external-link-alt' => 'fa-external-link-alt',
    'fas fa-exchange-alt' => 'fa-exchange-alt',
    'fas fa-random' => 'fa-random',
    'fas fa-redo' => 'fa-redo',
    'fas fa-redo-alt' => 'fa-redo-alt',
    'fas fa-undo' => 'fa-undo',
    'fas fa-undo-alt' => 'fa-undo-alt',
    'fas fa-sync' => 'fa-sync',
    'fas fa-sync-alt' => 'fa-sync-alt',
    'fas fa-history' => 'fa-history',
    'fas fa-reply' => 'fa-reply',
    'fas fa-reply-all' => 'fa-reply-all',
    'fas fa-share' => 'fa-share',
    'fas fa-share-alt' => 'fa-share-alt',
    'fas fa-share-square' => 'fa-share-square',
    'fas fa-forward' => 'fa-forward',
    'fas fa-backward' => 'fa-backward',
    'fas fa-fast-forward' => 'fa-fast-forward',
    'fas fa-fast-backward' => 'fa-fast-backward',
    'fas fa-step-forward' => 'fa-step-forward',
    'fas fa-step-backward' => 'fa-step-backward',
    'fas fa-play' => 'fa-play',
    'fas fa-pause' => 'fa-pause',
    'fas fa-stop' => 'fa-stop'
];

// Set page title
$page_title = "Menu Management";

// Set root path for components
$root_path = '../../';

// Include header
include '../../components/header.php';

// Include jQuery UI if not already included
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.13.2/jquery-ui.min.js"></script>';

// Include custom CSS for menu styling
echo '<link rel="stylesheet" href="../../assets/css/menu-styles.css">';

// Include sidebar
include '../../components/sidebar.php';

// Include view
include 'views/index.php';

// Include footer
include '../../components/footer.php';
