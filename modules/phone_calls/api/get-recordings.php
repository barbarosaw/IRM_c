<?php
/**
 * Phone Calls Module - Get Recordings API
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check permission
if (!has_permission('phone_calls-recordings')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

try {
    // Build query
    $where = ["pc.recording_url IS NOT NULL", "pc.recording_url != ''"];
    $params = [];

    if (!empty($_GET['user_id'])) {
        $where[] = "pc.user_id = ?";
        $params[] = (int) $_GET['user_id'];
    }

    if (!empty($_GET['date_from'])) {
        $where[] = "DATE(pc.created_at) >= ?";
        $params[] = $_GET['date_from'];
    }

    if (!empty($_GET['date_to'])) {
        $where[] = "DATE(pc.created_at) <= ?";
        $params[] = $_GET['date_to'];
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $db->prepare("
        SELECT
            pc.id,
            pc.call_sid,
            pc.user_id,
            u.name as user_name,
            pc.to_number,
            pc.from_number,
            pc.duration,
            pc.recording_url,
            pc.recording_duration,
            pc.created_at
        FROM phone_calls pc
        LEFT JOIN users u ON pc.user_id = u.id
        WHERE $whereClause
        ORDER BY pc.created_at DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    $recordings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $recordings
    ]);

} catch (Exception $e) {
    error_log("Get Recordings Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
