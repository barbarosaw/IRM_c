<?php
/**
 * Inventory Module - Delete Subscription Type
 * 
 * @author System Generated
 */


// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory permissions
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
require_once $root_dir . '/modules/inventory/models/SubscriptionType.php';

// Initialize model
$subscriptionTypeModel = new InventorySubscriptionType($db);

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $typeId = (int)$_POST['type_id'];
        
        if (!$typeId) {
            throw new Exception('Subscription type ID is required.');
        }
        
        // Get subscription type details before deletion
        $type = $subscriptionTypeModel->getById($typeId);
        if (!$type) {
            throw new Exception('Subscription type not found.');
        }
        
        // Check if subscription type is in use
        if ($subscriptionTypeModel->isInUse($typeId)) {
            throw new Exception('Cannot delete subscription type that is currently in use by inventory items.');
        }
        
        // Delete subscription type (soft delete)
        if ($subscriptionTypeModel->delete($typeId)) {
            $response['success'] = true;
            $response['message'] = 'Subscription type deleted successfully.';
            
            // Log the activity
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (module, entity_id, action, description, user_id, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    'inventory_subscription_type',
                    $typeId,
                    'delete',
                    "Subscription type deleted: {$type['name']}",
                    $_SESSION['user_id']
                ]);
            } catch (Exception $e) {
                // Activity logging is optional, don't fail the main operation
                error_log("Activity log error: " . $e->getMessage());
            }
            
        } else {
            throw new Exception('Failed to delete subscription type.');
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
