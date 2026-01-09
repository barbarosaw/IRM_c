<?php
/**
 * n8n Connection Test API
 * GET: Test connection to n8n instance
 */

session_start();
require_once dirname(__DIR__, 3) . '/config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

require_once dirname(__DIR__) . '/models/N8nApi.php';

$n8nApi = new N8nApi($db);

if (!$n8nApi->isConfigured()) {
    echo json_encode([
        'success' => false,
        'message' => 'n8n is not configured. Please set the host URL and API key.'
    ]);
    exit;
}

$result = $n8nApi->testConnection();

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Connection successful!',
        'workflows' => $result['workflows'] ?? 0
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => $result['error'] ?? 'Connection failed'
    ]);
}
