<?php
/**
 * Update Session API
 * POST: Update session flags and collected info
 * Used by n8n workflow to track job seekers, collected booking info, etc.
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

if (!$sessionId) {
    jsonResponse(['success' => false, 'error' => 'session_id is required'], 400);
}

try {
    // Verify session exists
    $stmt = $db->prepare("SELECT id, status FROM chat_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        jsonResponse(['success' => false, 'error' => 'Session not found'], 404);
    }

    // Build update query dynamically based on provided fields
    $updates = [];
    $params = [];

    // is_job_seeker flag
    if (isset($input['is_job_seeker'])) {
        $updates[] = 'is_job_seeker = ?';
        $params[] = $input['is_job_seeker'] ? 1 : 0;
    }

    // HubSpot contact ID
    if (isset($input['hubspot_contact_id'])) {
        $updates[] = 'hubspot_contact_id = ?';
        $params[] = $input['hubspot_contact_id'];
    }

    // Collected info (for booking flow)
    if (isset($input['collected_info'])) {
        $updates[] = 'collected_info = ?';
        $params[] = is_string($input['collected_info'])
            ? $input['collected_info']
            : json_encode($input['collected_info']);
    }

    // Off-topic attempts counter
    if (isset($input['off_topic_attempts'])) {
        $updates[] = 'off_topic_attempts = ?';
        $params[] = (int)$input['off_topic_attempts'];
    }

    // Increment off-topic attempts
    if (isset($input['increment_off_topic'])) {
        $updates[] = 'off_topic_attempts = off_topic_attempts + 1';
    }

    // Primary intent (if needs to be updated)
    if (isset($input['primary_intent'])) {
        $updates[] = 'primary_intent = ?';
        $params[] = $input['primary_intent'];
    }

    // Close session
    if (isset($input['close_session']) && $input['close_session']) {
        $updates[] = "status = 'closed'";
    }

    // Always update last_activity
    $updates[] = 'last_activity = NOW()';

    if (empty($updates)) {
        jsonResponse(['success' => false, 'error' => 'No fields to update'], 400);
    }

    // Add session_id to params
    $params[] = $sessionId;

    $sql = "UPDATE chat_sessions SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // Get updated session data
    $stmt = $db->prepare("
        SELECT is_job_seeker, hubspot_contact_id, collected_info, off_topic_attempts, status
        FROM chat_sessions WHERE id = ?
    ");
    $stmt->execute([$sessionId]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'session_id' => $sessionId,
        'is_job_seeker' => (bool)$updated['is_job_seeker'],
        'hubspot_contact_id' => $updated['hubspot_contact_id'],
        'collected_info' => $updated['collected_info'] ? json_decode($updated['collected_info'], true) : null,
        'off_topic_attempts' => (int)$updated['off_topic_attempts'],
        'status' => $updated['status']
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
