<?php
/**
 * Brain Status API - Simple endpoint to get brain state
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';

handleCors();
validateApiKey();

$sessionId = $_GET['session_id'] ?? null;

if (!$sessionId) {
    jsonResponse(['success' => false, 'error' => 'session_id is required'], 400);
}

try {
    $stmt = $db->prepare("SELECT * FROM conversation_brain WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $brain = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$brain) {
        jsonResponse([
            'success' => true,
            'brain' => [
                'state' => 'greeting',
                'engagement_level' => 'medium',
                'turn_count' => 0,
                'booking_intent' => null,
                'topics_discussed' => null,
                'last_user_intent' => null,
                'last_ai_action' => null,
                'active_rules' => null,
                'pending_actions' => null
            ]
        ]);
    }

    jsonResponse([
        'success' => true,
        'brain' => $brain
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
