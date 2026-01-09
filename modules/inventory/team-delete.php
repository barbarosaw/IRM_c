<?php
/**
 * Inventory Module - Delete Team
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
require_once $root_dir . '/modules/inventory/models/Team.php';

// Initialize models
$teamModel = new InventoryTeam($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['team_id'])) {
    $teamId = (int)$_POST['team_id'];
    
    // Get team details for logging
    $team = $teamModel->getById($teamId);
    
    if ($team) {
        $result = $teamModel->delete($teamId);
        
        if ($result) {
            $_SESSION['success_message'] = "Team '{$team['name']}' has been deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to delete team. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "Team not found.";
    }
} else {
    $_SESSION['error_message'] = "Invalid request.";
}

header('Location: teams.php');
exit;
?>
