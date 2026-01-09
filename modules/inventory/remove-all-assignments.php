<?php
/**
 * Inventory Module - Remove All Assignments from Item
 * 
 * @author System Generated
 */

// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check permissions
if (!has_permission('edit_inventory')) {
    header('Location: ../../access-denied.php');
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { header('Location: ../../module-inactive.php'); exit; }

$root_dir = dirname(__DIR__, 2);

// Load models
require_once $root_dir . '/modules/inventory/models/Item.php';

// Initialize models
$itemModel = new InventoryItem($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $itemId = (int)($_POST['item_id'] ?? 0);
        
        if (!$itemId) {
            throw new Exception('Invalid item ID.');
        }
        
        // Get item details for logging
        $item = $itemModel->getById($itemId);
        if (!$item) {
            throw new Exception('Item not found.');
        }
        
        // Remove all assignments
        if ($itemModel->removeAllAssignments($itemId)) {
            $_SESSION['success_message'] = 'All assignments removed successfully from: ' . $item['name'];
            
            // Log the action
            $stmt = $db->prepare("
                INSERT INTO inventory_usage_logs (
                    item_id, action, action_details, action_date, logged_by, created_at
                ) VALUES (?, ?, ?, NOW(), ?, NOW())
            ");
            $stmt->execute([
                $itemId,
                'assignments_removed',
                'All assignments removed from item: ' . $item['name'],
                $_SESSION['user_id']
            ]);
            
        } else {
            throw new Exception('Failed to remove assignments.');
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Redirect back to the referring page or item view
$returnUrl = $_SERVER['HTTP_REFERER'] ?? 'subscription-types.php';
header('Location: ' . $returnUrl);
exit;
?>
