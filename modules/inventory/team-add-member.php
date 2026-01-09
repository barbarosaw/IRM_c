<?php
/**
 * Inventory Module - Add Team Member
 * 
 * @author System Generated
 */

// Define system constant to allow access to models
define('AW_SYSTEM', true);
require_once '../../includes/init.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../../login.php'); exit; }

// Check inventory edit permission
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
require_once $root_dir . '/modules/inventory/models/Team.php';

// Initialize models
$teamModel = new InventoryTeam($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $teamId = (int)$_POST['team_id'];
        $userId = (int)$_POST['user_id'];
        $role = $_POST['role'] ?? 'member';
        $isLead = isset($_POST['is_lead']) ? 1 : 0;
        
        if (!$teamId || !$userId) {
            throw new Exception('Invalid team or user ID.');
        }
        
        // Check if team exists
        $team = $teamModel->getById($teamId);
        if (!$team) {
            throw new Exception('Team not found.');
        }
        
        // Check if user is already a member
        $stmt = $db->prepare("
            SELECT id FROM inventory_team_members 
            WHERE team_id = ? AND user_id = ? AND is_active = 1
        ");
        $stmt->execute([$teamId, $userId]);
        if ($stmt->fetch()) {
            throw new Exception('User is already a member of this team.');
        }
        
        // If setting as lead, remove lead status from others
        if ($isLead) {
            $stmt = $db->prepare("UPDATE inventory_team_members SET is_lead = 0 WHERE team_id = ?");
            $stmt->execute([$teamId]);
        }
        
        // Add team member
        $stmt = $db->prepare("
            INSERT INTO inventory_team_members (team_id, user_id, role, is_lead, added_by, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$teamId, $userId, $role, $isLead, $_SESSION['user_id']]);
        
        $_SESSION['success_message'] = 'Team member added successfully.';
        header('Location: team-view.php?id=' . $teamId);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: team-view.php?id=' . ($teamId ?? 0));
        exit;
    }
} else {
    header('Location: teams.php');
    exit;
}
?>
