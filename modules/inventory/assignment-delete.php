<?php
/**
 * Inventory Module - Delete Assignment
 * 
 * @author System Generated
 */


// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory permissions
if (!has_permission('delete_inventory_assignment')) {
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
require_once $root_dir . '/modules/inventory/models/Assignment.php';
require_once $root_dir . '/modules/inventory/models/UsageLog.php';

// Initialize models
$assignmentModel = new InventoryAssignment($db);
$usageLogModel = new InventoryUsageLog($db);

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $assignmentId = (int)$_POST['assignment_id'];
        
        if (!$assignmentId) {
            throw new Exception('Assignment ID is required.');
        }
        
        // Get assignment details before deletion
        $assignment = $assignmentModel->getById($assignmentId);
        if (!$assignment) {
            throw new Exception('Assignment not found.');
        }
        
        // Delete assignment
        if ($assignmentModel->delete($assignmentId)) {
            $response['success'] = true;
            $response['message'] = 'Assignment deleted successfully.';
            
            // Log the activity (simulated since activity_log function may not exist)
            try {
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (module, entity_id, action, description, user_id, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    'inventory_assignment',
                    $assignmentId,
                    'delete',
                    "Assignment deleted: {$assignment['item_name']} from {$assignment['assignee_type']} {$assignment['assignee_name']}",
                    $_SESSION['user_id']
                ]);
            } catch (Exception $e) {
                // Activity logging is optional, don't fail the main operation
                error_log("Activity log error: " . $e->getMessage());
            }
            
            // Log assignment deletion in usage logs
            try {
                $usageLogModel->logUsage([
                    'item_id' => $assignment['item_id'],
                    'assignment_id' => $assignmentId,
                    'user_id' => $_SESSION['user_id'],
                    'action' => 'assignment_deleted',
                    'description' => "Assignment deleted: {$assignment['item_name']} from {$assignment['assignee_type']}: {$assignment['assignee_name']}",
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'logged_at' => date('Y-m-d H:i:s')
                ]);
            } catch (Exception $e) {
                // Usage logging is optional, don't fail the main operation
                error_log("Usage log error: " . $e->getMessage());
            }
            
        } else {
            throw new Exception('Failed to delete assignment.');
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
