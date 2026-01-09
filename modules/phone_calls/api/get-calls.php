<?php
/**
 * Phone Calls Module - Get Calls API
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check permission
if (!has_permission('phone_calls-history') && !has_permission('phone_calls-view-all')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

require_once '../models/PhoneCall.php';
$phoneCallModel = new PhoneCall($db);

$canViewAll = has_permission('phone_calls-view-all');

// Build filters
$filters = [];

// If user can't view all, restrict to their own calls
if (!$canViewAll) {
    $filters['user_id'] = $_SESSION['user_id'];
} elseif (!empty($_GET['user_id'])) {
    $filters['user_id'] = (int) $_GET['user_id'];
}

if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

if (!empty($_GET['direction'])) {
    $filters['direction'] = $_GET['direction'];
}

if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// Pagination
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 500;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

// Get calls
$calls = $phoneCallModel->getAll($filters, $limit, $offset);
$total = $phoneCallModel->countAll($filters);

// Check if CSV export requested
if (!empty($_GET['format']) && $_GET['format'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="call_history_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Headers
    $headers = ['Date', 'User', 'Direction', 'From', 'To', 'Duration (sec)', 'Status', 'Cost'];
    fputcsv($output, $headers);

    // Data
    foreach ($calls as $call) {
        fputcsv($output, [
            $call['created_at'],
            $call['user_name'] ?? '',
            $call['direction'],
            $call['from_number'],
            $call['to_number'],
            $call['duration'],
            $call['status'],
            '$' . number_format($call['cost'], 4)
        ]);
    }

    fclose($output);
    exit;
}

// Return JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $calls,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
]);
