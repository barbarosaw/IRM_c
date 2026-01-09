<?php
/**
 * n8n Status API
 * GET: Get n8n and chat statistics for dashboard
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
require_once dirname(__DIR__) . '/models/ChatSession.php';

$n8nApi = new N8nApi($db);
$sessionModel = new ChatSession($db);

$data = [
    'workflows' => ['total' => 0, 'active' => 0],
    'executions' => ['success' => 0, 'error' => 0],
    'chat_sessions' => 0
];

// Get n8n data if configured
if ($n8nApi->isConfigured()) {
    // Get workflows
    $workflowsResult = $n8nApi->getWorkflows();
    if ($workflowsResult['success']) {
        $workflows = $workflowsResult['workflows'];
        $data['workflows']['total'] = count($workflows);
        $data['workflows']['active'] = count(array_filter($workflows, function($w) {
            return $w['active'] ?? false;
        }));
    }

    // Get executions
    $execResult = $n8nApi->getExecutionStats();
    if ($execResult['success']) {
        $data['executions'] = $execResult['stats'];
    }
}

// Get today's chat sessions
$stats = $sessionModel->getStats(date('Y-m-d'), date('Y-m-d'));
$data['chat_sessions'] = $stats['total_sessions'];

echo json_encode([
    'success' => true,
    'data' => $data
]);
