<?php
/**
 * Inventory Module - Remove Assignment
 * 
 * @author System Generated
 */

// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory permissions
if (!has_permission('delete_inventory')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$stmt = $db->prepare("SELECT is_active FROM modules WHERE code = ?");
$stmt->execute(['inventory']);
$is_active = $stmt->fetchColumn();
if (!$is_active) { 
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Module inactive']);
    exit; 
}

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
        
        // Validate required fields
        if (!$assignmentId) {
            throw new Exception('Assignment ID is required.');
        }
        
        // Get assignment details before removal
        $assignment = $assignmentModel->getById($assignmentId);
        if (!$assignment) {
            throw new Exception('Assignment not found.');
        }
        
        // Check if user has permission to remove this assignment
        if (!has_permission('delete_inventory')) {
            throw new Exception('You do not have permission to remove assignments.');
        }
        
        // Remove assignment (soft delete)
        $result = $assignmentModel->delete($assignmentId);
        
        if ($result) {
            $response['success'] = true;
            $response['message'] = 'Assignment removed successfully.';
            
            // Get assignee name for logging
            $assigneeName = '';
            if ($assignment['assignee_type'] === 'user') {
                $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$assignment['assignee_id']]);
                $assigneeName = $stmt->fetchColumn();
            } else {
                $stmt = $db->prepare("SELECT name FROM inventory_teams WHERE id = ?");
                $stmt->execute([$assignment['assignee_id']]);
                $assigneeName = $stmt->fetchColumn();
            }
            
            // Log assignment removal in usage logs
            $usageLogModel->logUsage([
                'item_id' => $assignment['item_id'],
                'assignment_id' => $assignmentId,
                'user_id' => $_SESSION['user_id'],
                'action' => 'assignment_removed',
                'description' => "Assignment removed from {$assignment['assignee_type']}: {$assigneeName}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'logged_at' => date('Y-m-d H:i:s')
            ]);
            
            // activity_log('inventory_assignment', $assignmentId, 'delete', 
            //     "Assignment removed for item ID: {$assignment['item_id']}");
        } else {
            throw new Exception('Failed to remove assignment.');
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
