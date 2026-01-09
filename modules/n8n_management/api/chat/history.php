<?php
/**
 * Chat History API
 * GET: Retrieve conversation history for a session
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';

handleCors();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

validateApiKey();

$sessionId = $_GET['session_id'] ?? null;
$limit = min((int)($_GET['limit'] ?? 20), 50); // Max 50 messages

if (!$sessionId) {
    jsonResponse(['success' => false, 'error' => 'session_id is required'], 400);
}

try {
    // Get recent messages for context
    $stmt = $db->prepare("
        SELECT role, content, created_at
        FROM chat_messages
        WHERE session_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$sessionId, $limit]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Reverse to get chronological order
    $messages = array_reverse($messages);

    // Format for OpenAI
    $formatted = array_map(function($msg) {
        return [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }, $messages);

    jsonResponse([
        'success' => true,
        'messages' => $formatted,
        'count' => count($formatted)
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
