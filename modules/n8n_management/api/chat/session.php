<?php
/**
 * Chat Session API
 * POST: Create or update a chat session
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

$visitorId = $input['visitor_id'] ?? null;
$pageUrl = substr($input['page_url'] ?? '', 0, 500);
$pageTitle = substr($input['page_title'] ?? '', 0, 200);
$userAgent = substr($input['user_agent'] ?? '', 0, 500);
$ipAddress = substr($input['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? '', 0, 45);

if (!$visitorId) {
    jsonResponse(['success' => false, 'error' => 'visitor_id is required'], 400);
}

try {
    // Check for existing active session for this visitor
    $stmt = $db->prepare("
        SELECT id, started_at, total_messages
        FROM chat_sessions
        WHERE visitor_id = ? AND status = 'active'
        ORDER BY started_at DESC
        LIMIT 1
    ");
    $stmt->execute([$visitorId]);
    $existingSession = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingSession) {
        // Update existing session
        $stmt = $db->prepare("
            UPDATE chat_sessions
            SET last_activity = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$existingSession['id']]);

        jsonResponse([
            'success' => true,
            'session_id' => $existingSession['id'],
            'is_new' => false,
            'message_count' => (int)$existingSession['total_messages']
        ]);
    } else {
        // Create new session
        $sessionId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        $stmt = $db->prepare("
            INSERT INTO chat_sessions
            (id, visitor_id, started_at, last_activity, initial_page_url, initial_page_title, user_agent, ip_address, status, total_messages, total_tokens)
            VALUES (?, ?, NOW(), NOW(), ?, ?, ?, ?, 'active', 0, 0)
        ");
        $stmt->execute([$sessionId, $visitorId, $pageUrl, $pageTitle, $userAgent, $ipAddress]);

        jsonResponse([
            'success' => true,
            'session_id' => $sessionId,
            'is_new' => true,
            'message_count' => 0
        ]);
    }
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
