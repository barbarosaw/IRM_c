<?php
/**
 * Update Strategy - Tool for n8n Strategist Agent
 * Updates goals and conversation strategy
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';

handleCors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['session_id'] ?? null;
$currentGoal = $input['current_goal'] ?? null;
$nextGoal = $input['next_goal'] ?? null;
$goalCompleted = $input['goal_completed'] ?? null;
$engagement = $input['engagement'] ?? null;
$aiInstructions = $input['ai_instructions'] ?? null;
$contextSummary = $input['context_summary'] ?? null;

if (!$sessionId) {
    jsonResponse(['success' => false, 'error' => 'session_id required'], 400);
}

try {
    // Get current goals_completed
    $stmt = $db->prepare("SELECT goals_completed FROM conversation_brain WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $goalsCompleted = [];
    if ($row && !empty($row['goals_completed'])) {
        $goalsCompleted = json_decode($row['goals_completed'], true) ?: [];
    }

    // Add new completed goal if provided
    if ($goalCompleted && !in_array($goalCompleted, $goalsCompleted)) {
        $goalsCompleted[] = $goalCompleted;
    }

    // Build update query
    $updates = ['updated_at = NOW()'];
    $params = [];

    if ($currentGoal !== null) {
        $updates[] = 'current_goal = ?';
        $params[] = $currentGoal;
    }
    if ($nextGoal !== null) {
        $updates[] = 'next_goal = ?';
        $params[] = $nextGoal;
    }
    if (!empty($goalsCompleted)) {
        $updates[] = 'goals_completed = ?';
        $params[] = json_encode($goalsCompleted);
    }
    if ($engagement !== null) {
        $updates[] = 'engagement_level = ?';
        $params[] = $engagement;
    }
    if ($aiInstructions !== null) {
        $updates[] = 'ai_instructions = ?';
        $params[] = $aiInstructions;
    }
    if ($contextSummary !== null) {
        $updates[] = 'context_summary = ?';
        $params[] = $contextSummary;
    }

    $params[] = $sessionId;
    $sql = "UPDATE conversation_brain SET " . implode(', ', $updates) . " WHERE session_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    jsonResponse([
        'success' => true,
        'current_goal' => $currentGoal,
        'next_goal' => $nextGoal,
        'goals_completed' => $goalsCompleted,
        'message' => 'Strategy updated'
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
