<?php
/**
 * Chat Message API
 * POST: Save a chat message (user or assistant)
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';

handleCors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

validateApiKey();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(['success' => false, 'error' => 'Invalid JSON input'], 400);
}

$sessionId = $input['session_id'] ?? null;
$role = $input['role'] ?? null;
$content = $input['content'] ?? '';
$intent = substr($input['intent'] ?? '', 0, 50);
$topic = substr($input['topic'] ?? '', 0, 50);
$agentCode = substr($input['agent_code'] ?? '', 0, 50);
$pageUrl = substr($input['page_url'] ?? '', 0, 500);
$tokensUsed = (int)($input['tokens_used'] ?? 0);
$responseTimeMs = (int)($input['response_time_ms'] ?? 0);

// Validation
if (!$sessionId) {
    jsonResponse(['success' => false, 'error' => 'session_id is required'], 400);
}

if (!in_array($role, ['user', 'assistant', 'system'])) {
    jsonResponse(['success' => false, 'error' => 'role must be user, assistant, or system'], 400);
}

if (strlen($content) > 10000) {
    jsonResponse(['success' => false, 'error' => 'content exceeds maximum length'], 400);
}

try {
    // Verify session exists
    $stmt = $db->prepare("SELECT id, status FROM chat_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        jsonResponse(['success' => false, 'error' => 'Session not found'], 404);
    }

    // Insert message
    $stmt = $db->prepare("
        INSERT INTO chat_messages
        (session_id, role, content, intent, topic, agent_code, page_url, tokens_used, response_time_ms, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$sessionId, $role, $content, $intent, $topic, $agentCode, $pageUrl, $tokensUsed, $responseTimeMs]);
    $messageId = $db->lastInsertId();

    // Update session stats
    $stmt = $db->prepare("
        UPDATE chat_sessions
        SET last_activity = NOW(),
            total_messages = total_messages + 1,
            total_tokens = total_tokens + ?,
            primary_intent = COALESCE(primary_intent, ?)
        WHERE id = ?
    ");
    $stmt->execute([$tokensUsed, $intent, $sessionId]);

    jsonResponse([
        'success' => true,
        'message_id' => (int)$messageId,
        'session_id' => $sessionId
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
