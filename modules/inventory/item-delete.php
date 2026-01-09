<?php
/**
 * Inventory Module - Delete Item
 * 
 * @author System Generated
 */


// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory delete permission
if (!has_permission('delete_inventory')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->
prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/InventoryItem.php';

// Initialize models
$inventoryModel = new InventoryItem($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $itemId = (int)$_POST['item_id'];
    
    // Get item details for logging
    $item = $inventoryModel->getById($itemId, has_permission('view_license_keys'));
    
    if ($item) {
        $result = $inventoryModel->delete($itemId);
        
        if ($result) {
            $_SESSION['success_message'] = "Inventory item '{$item['name']}' has been deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete inventory item. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "Inventory item not found.";
    }
} else {
    $_SESSION['error_message'] = "Invalid request.";
}

header('Location: items.php');
exit;
?>
