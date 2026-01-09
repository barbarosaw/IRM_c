<?php
/**
 * TimeWorks Module - Toggle User Status API
 *
 * Toggles user status between active and inactive
 *
 * @author ikinciadam@gmail.com
 */

define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Permission check
if (!has_permission('timeworks_users_manage')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $userId = $_POST['user_id'] ?? null;

    if (!$userId) {
        throw new Exception('User ID is required');
    }

    // Get current status
    $stmt = $db->prepare("SELECT id, user_id, full_name, status FROM twr_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('User not found');
    }

    // Toggle status
    $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';

    $stmt = $db->prepare("UPDATE twr_users SET status = ?, updated_at = NOW() WHERE user_id = ?");
    $stmt->execute([$newStatus, $userId]);

    // Log activity
    log_activity(
        $_SESSION['user_id'],
        'update',
        'timeworks_user',
        "Changed status of {$user['full_name']} from {$user['status']} to {$newStatus}"
    );

    echo json_encode([
        'success' => true,
        'message' => 'User status updated successfully',
        'new_status' => $newStatus,
        'user_name' => $user['full_name']
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
