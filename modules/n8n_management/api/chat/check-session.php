<?php
/**
 * Check Session API (Public)
 * POST: Check if visitor has an active session and return messages
 * Used by widget to restore chat history on page load
 */

require_once dirname(__DIR__, 4) . '/config/database.php';

// CORS headers for cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$visitorId = $input['visitor_id'] ?? null;

if (!$visitorId) {
    echo json_encode([
        'success' => false,
        'error' => 'visitor_id is required',
        'has_active_session' => false
    ]);
    exit;
}

try {
    // Look for active session for this visitor
    $stmt = $db->prepare("
        SELECT id, started_at, last_activity, status, total_messages,
               primary_intent, is_job_seeker, collected_info, off_topic_attempts
        FROM chat_sessions
        WHERE visitor_id = ? AND status = 'active'
        ORDER BY started_at DESC
        LIMIT 1
    ");
    $stmt->execute([$visitorId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        echo json_encode([
            'success' => true,
            'has_active_session' => false,
            'session_id' => null,
            'messages' => []
        ]);
        exit;
    }

    // Get messages for this session
    $stmt = $db->prepare("
        SELECT id, role, content, intent, created_at
        FROM chat_messages
        WHERE session_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$session['id']]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format messages for widget
    $formattedMessages = array_map(function($msg) {
        return [
            'id' => (int)$msg['id'],
            'role' => $msg['role'],
            'content' => $msg['content'],
            'intent' => $msg['intent'],
            'timestamp' => strtotime($msg['created_at']) * 1000 // JS timestamp
        ];
    }, $messages);

    // Parse collected_info if exists
    $collectedInfo = null;
    if ($session['collected_info']) {
        $collectedInfo = json_decode($session['collected_info'], true);
    }

    echo json_encode([
        'success' => true,
        'has_active_session' => true,
        'session_id' => $session['id'],
        'started_at' => $session['started_at'],
        'last_activity' => $session['last_activity'],
        'total_messages' => (int)$session['total_messages'],
        'primary_intent' => $session['primary_intent'],
        'is_job_seeker' => (bool)$session['is_job_seeker'],
        'collected_info' => $collectedInfo,
        'off_topic_attempts' => (int)$session['off_topic_attempts'],
        'messages' => $formattedMessages
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'has_active_session' => false
    ]);
}
