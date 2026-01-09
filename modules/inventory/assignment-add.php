<?php
/**
 * Inventory Module - Add Assignment
 * 
 * @author System Generated
 */


// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory permissions
if (!has_permission('create_inventory_assignment')) {
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
require_once $root_dir . '/modules/inventory/models/Assignment.php';
require_once $root_dir . '/modules/inventory/models/UsageLog.php';

// Initialize models
$inventoryModel = new InventoryItem($db);
$assignmentModel = new InventoryAssignment($db);
$usageLogModel = new InventoryUsageLog($db);

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $itemId = (int)$_POST['item_id'];
        $assigneeType = $_POST['assignee_type']; // 'user' or 'team'
        
        // Handle both assignee_id (from main form) and user_id/team_id (from modal)
        if (isset($_POST['assignee_id'])) {
            $assigneeId = (int)$_POST['assignee_id'];
        } elseif ($assigneeType === 'user' && isset($_POST['user_id'])) {
            $assigneeId = (int)$_POST['user_id'];
        } elseif ($assigneeType === 'team' && isset($_POST['team_id'])) {
            $assigneeId = (int)$_POST['team_id'];
        } else {
            $assigneeId = 0;
        }
        
        $assignedDate = $_POST['assigned_date'] ?? date('Y-m-d');
        $notes = trim($_POST['notes'] ?? '');
        
        // Validate required fields
        if (!$itemId || !$assigneeType || !$assigneeId) {
            throw new Exception('All required fields must be filled.');
        }
        
        // Check if item exists and is active
        $item = $inventoryModel->getById($itemId);
        if (!$item || !$item['is_active']) {
            throw new Exception('Item not found or inactive.');
        }
        
        // Check assignment limits
        $currentAssignments = $assignmentModel->getByItemId($itemId);
        
        if ($assigneeType === 'user') {
            $userAssignments = array_filter($currentAssignments, function($a) {
                return $a['assignee_type'] === 'user';
            });
            
            if (count($userAssignments) >= $item['max_users']) {
                throw new Exception('Maximum user limit reached for this item.');
            }
            
            // Check if user already assigned to this item
            $alreadyAssigned = array_filter($userAssignments, function($a) use ($assigneeId) {
                return $a['assignee_id'] == $assigneeId;
            });
            
            if (!empty($alreadyAssigned)) {
                throw new Exception('User is already assigned to this item.');
            }
            
        } else if ($assigneeType === 'team') {
            $teamAssignments = array_filter($currentAssignments, function($a) {
                return $a['assignee_type'] === 'team';
            });
            
            if (count($teamAssignments) >= $item['max_teams']) {
                throw new Exception('Maximum team limit reached for this item.');
            }
            
            // Check if team already assigned to this item
            $alreadyAssigned = array_filter($teamAssignments, function($a) use ($assigneeId) {
                return $a['assignee_id'] == $assigneeId;
            });
            
            if (!empty($alreadyAssigned)) {
                throw new Exception('Team is already assigned to this item.');
            }
        }
        
        // Verify assignee exists
        if ($assigneeType === 'user') {
            $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$assigneeId]);
            if (!$stmt->fetchColumn()) {
                throw new Exception('User not found or inactive.');
            }
        } else {
            $stmt = $db->prepare("SELECT id FROM inventory_teams WHERE id = ? AND is_active = 1");
            $stmt->execute([$assigneeId]);
            if (!$stmt->fetchColumn()) {
                throw new Exception('Team not found or inactive.');
            }
        }
        
        // Create assignment
        $assignmentData = [
            'item_id' => $itemId,
            'assignee_type' => $assigneeType,
            'assignee_id' => $assigneeId,
            'assigned_by' => $_SESSION['user_id'],
            'usage_start_date' => $assignedDate,
            'usage_end_date' => null,
            'notes' => $notes,
            'status' => 'active'
        ];
        
        $assignmentId = $assignmentModel->create($assignmentData);
        
        if ($assignmentId) {
            $response['success'] = true;
            $response['message'] = 'Assignment created successfully.';
            $response['assignment_id'] = $assignmentId;
            
            // Log the activity
            $assigneeName = '';
            if ($assigneeType === 'user') {
                $stmt = $db->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$assigneeId]);
                $assigneeName = $stmt->fetchColumn();
            } else {
                $stmt = $db->prepare("SELECT name FROM inventory_teams WHERE id = ?");
                $stmt->execute([$assigneeId]);
                $assigneeName = $stmt->fetchColumn();
            }
            
            // Log assignment creation in usage logs
            $usageLogModel->logUsage([
                'item_id' => $itemId,
                'assignment_id' => $assignmentId,
                'user_id' => $_SESSION['user_id'],
                'action' => 'assignment_created',
                'description' => "Item assigned to {$assigneeType}: {$assigneeName}",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'logged_at' => date('Y-m-d H:i:s')
            ]);
            
            // activity_log('inventory_assignment', $assignmentId, 'create', 
            //     "Item '{$item['name']}' assigned to {$assigneeType} '{$assigneeName}'");
        } else {
            throw new Exception('Failed to create assignment.');
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
