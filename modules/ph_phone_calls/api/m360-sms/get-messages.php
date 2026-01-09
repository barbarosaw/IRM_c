<?php
/**
 * PH Communications Module - Get SMS Messages API
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../../includes/init.php';

header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check permission
$direction = $_GET['direction'] ?? 'outbound';
$requiredPermission = $direction === 'inbound' ? 'ph_communications-view-inbox' : 'ph_communications-view-outbox';

if (!has_permission($requiredPermission) && !has_permission('ph_communications-view-all')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

require_once '../../models/SMSMessage.php';
$smsModel = new SMSMessage($db);

$canViewAll = has_permission('ph_communications-view-all');

// Build filters
$filters = ['direction' => $direction];

// If user can't view all, restrict to their own messages
if (!$canViewAll) {
    $filters['user_id'] = $_SESSION['user_id'];
} elseif (!empty($_GET['user_id'])) {
    $filters['user_id'] = (int) $_GET['user_id'];
}

if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

if (!empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

// Pagination
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 500;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

// Get messages
$messages = $smsModel->getAll($filters, $limit, $offset);
$total = $smsModel->countAll($filters);

// Return JSON
echo json_encode([
    'success' => true,
    'data' => $messages,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
]);
