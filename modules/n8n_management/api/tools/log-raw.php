<?php
/**
 * Log Raw Request - Fallback storage
 * Stores raw request data for recovery if needed
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';

handleCors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['session_id'] ?? null;
$endpoint = $input['endpoint'] ?? 'unknown';
$payload = $input['payload'] ?? $input;

if (!$sessionId) {
    jsonResponse(['success' => false, 'error' => 'session_id required'], 400);
}

try {
    $stmt = $db->prepare("
        INSERT INTO chat_raw_requests (session_id, endpoint_called, raw_payload)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $sessionId,
        $endpoint,
        json_encode($payload)
    ]);

    jsonResponse([
        'success' => true,
        'id' => $db->lastInsertId()
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
