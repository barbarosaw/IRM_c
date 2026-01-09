<?php
/**
 * Conversation Orchestrator API v2
 * Simplified orchestrator - n8n v9 handles all the logic
 *
 * POST: Process user message
 * - Save user message
 * - Call n8n v9 multi-agent workflow
 * - Save bot response
 * - Return response
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';

handleCors();
validateApiKey();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['session_id']) || !isset($input['message'])) {
    jsonResponse(['success' => false, 'error' => 'session_id and message are required'], 400);
}

$sessionId = $input['session_id'];
$visitorId = $input['visitor_id'] ?? null;
$message = trim($input['message']);

if (empty($message)) {
    jsonResponse(['success' => false, 'error' => 'Message cannot be empty'], 400);
}

$startTime = microtime(true);
$debugLog = [];

try {
    // Step 1: Ensure session and lead exist
    ensureSessionExists($db, $sessionId, $visitorId);
    ensureLeadExists($db, $sessionId, $visitorId);
    ensureBrainExists($db, $sessionId);
    $debugLog['setup'] = round((microtime(true) - $startTime) * 1000) . 'ms';

    // Step 2: Save user message
    saveMessage($db, $sessionId, 'user', $message);
    $debugLog['save_user'] = round((microtime(true) - $startTime) * 1000) . 'ms';

    // Step 3: Log raw request for fallback
    logRawRequest($db, $sessionId, 'orchestrator', [
        'session_id' => $sessionId,
        'visitor_id' => $visitorId,
        'message' => $message
    ]);

    // Step 4: Call n8n v9 multi-agent workflow
    $debugLog['n8n_start'] = round((microtime(true) - $startTime) * 1000) . 'ms';
    $n8nResponse = callN8N($sessionId, $message, $visitorId);
    $debugLog['n8n_done'] = round((microtime(true) - $startTime) * 1000) . 'ms';
    $debugLog['n8n_success'] = $n8nResponse['success'];

    if (!$n8nResponse['success']) {
        throw new Exception('n8n call failed: ' . ($n8nResponse['error'] ?? 'Unknown error'));
    }

    $aiResponse = $n8nResponse['response'];
    $debugLog['response_length'] = strlen($aiResponse);

    // Step 5: Save bot response
    saveMessage($db, $sessionId, 'assistant', $aiResponse);
    $debugLog['save_bot'] = round((microtime(true) - $startTime) * 1000) . 'ms';

    // Step 6: Update session activity
    $db->prepare("UPDATE chat_sessions SET last_activity = NOW(), total_messages = total_messages + 2 WHERE id = ?")->execute([$sessionId]);

    $debugLog['total'] = round((microtime(true) - $startTime) * 1000) . 'ms';

    // Return response
    jsonResponse([
        'success' => true,
        'response' => $aiResponse,
        'debug' => [
            'timing' => $debugLog,
            'n8n_debug' => $n8nResponse['debug'] ?? null
        ]
    ]);

} catch (Exception $e) {
    error_log('Orchestrator error: ' . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $debugLog
    ], 500);
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function ensureSessionExists($db, $sessionId, $visitorId = null)
{
    $stmt = $db->prepare("SELECT id FROM chat_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);

    if (!$stmt->fetch()) {
        if (empty($visitorId)) {
            $visitorId = 'v_' . bin2hex(random_bytes(8));
        }
        $stmt = $db->prepare("
            INSERT INTO chat_sessions
            (id, visitor_id, started_at, last_activity, status, total_messages, total_tokens)
            VALUES (?, ?, NOW(), NOW(), 'active', 0, 0)
        ");
        $stmt->execute([$sessionId, $visitorId]);
    }
}

function ensureLeadExists($db, $sessionId, $visitorId = null)
{
    $stmt = $db->prepare("SELECT id FROM chat_leads WHERE session_id = ?");
    $stmt->execute([$sessionId]);

    if (!$stmt->fetch()) {
        if (empty($visitorId)) {
            $visitorId = 'v_' . bin2hex(random_bytes(8));
        }
        $stmt = $db->prepare("INSERT INTO chat_leads (session_id, visitor_id) VALUES (?, ?)");
        $stmt->execute([$sessionId, $visitorId]);
    }
}

function ensureBrainExists($db, $sessionId)
{
    $stmt = $db->prepare("SELECT id FROM conversation_brain WHERE session_id = ?");
    $stmt->execute([$sessionId]);

    if (!$stmt->fetch()) {
        $stmt = $db->prepare("
            INSERT INTO conversation_brain (session_id, state, engagement_level, turn_count, current_goal)
            VALUES (?, 'greeting', 'medium', 0, 'greet_and_identify')
        ");
        $stmt->execute([$sessionId]);
    }
}

function saveMessage($db, $sessionId, $role, $content)
{
    $stmt = $db->prepare("
        INSERT INTO chat_messages (session_id, role, content, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$sessionId, $role, $content]);
}

function logRawRequest($db, $sessionId, $endpoint, $payload)
{
    try {
        $stmt = $db->prepare("
            INSERT INTO chat_raw_requests (session_id, endpoint_called, raw_payload)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$sessionId, $endpoint, json_encode($payload)]);
    } catch (Exception $e) {
        // Don't fail if logging fails
        error_log('Raw request logging failed: ' . $e->getMessage());
    }
}

function callN8N($sessionId, $message, $visitorId)
{
    $webhookUrl = 'https://n8n.abroadworks.com/webhook/aw-chat-v12';

    $payload = [
        'session_id' => $sessionId,
        'message' => $message,
        'visitor_id' => $visitorId
    ];

    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 60  // Longer timeout for 3 agents
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => "CURL error: $error"];
    }

    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP $httpCode: $response"];
    }

    $data = json_decode($response, true);
    if (!$data) {
        return ['success' => false, 'error' => 'Invalid JSON response: ' . substr($response, 0, 200)];
    }

    return [
        'success' => true,
        'response' => $data['response'] ?? 'No response generated',
        'debug' => $data['debug'] ?? null
    ];
}
