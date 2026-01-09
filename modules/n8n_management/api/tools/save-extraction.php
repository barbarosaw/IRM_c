<?php
/**
 * Save Extraction - Tool for n8n Analyzer Agent
 * Saves extracted data from user message
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';

handleCors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['session_id'] ?? null;
$extracted = $input['extracted'] ?? [];
$intent = $input['intent'] ?? null;
$confidence = $input['confidence'] ?? null;
$sentiment = $input['sentiment'] ?? null;

if (!$sessionId) {
    jsonResponse(['success' => false, 'error' => 'session_id required'], 400);
}

try {
    // Get current collected_data
    $stmt = $db->prepare("SELECT collected_data FROM conversation_brain WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $currentData = [];
    if ($row && !empty($row['collected_data'])) {
        $currentData = json_decode($row['collected_data'], true) ?: [];
    }

    // Merge new extracted data (don't overwrite existing)
    foreach ($extracted as $key => $value) {
        if (!empty($value) && empty($currentData[$key])) {
            $currentData[$key] = $value;
        }
    }

    // Update brain
    $stmt = $db->prepare("
        UPDATE conversation_brain
        SET collected_data = ?,
            last_user_intent = ?,
            turn_count = turn_count + 1,
            updated_at = NOW()
        WHERE session_id = ?
    ");
    $stmt->execute([
        json_encode($currentData),
        $intent,
        $sessionId
    ]);

    // Also update chat_leads with specific fields
    if (!empty($extracted)) {
        $leadUpdates = [];
        $leadParams = [];

        if (!empty($extracted['name'])) {
            $leadUpdates[] = "full_name = COALESCE(NULLIF(full_name, ''), ?)";
            $leadParams[] = $extracted['name'];
        }
        if (!empty($extracted['email'])) {
            $leadUpdates[] = "email = COALESCE(NULLIF(email, ''), ?)";
            $leadParams[] = $extracted['email'];
        }
        if (!empty($extracted['phone'])) {
            $leadUpdates[] = "phone = COALESCE(NULLIF(phone, ''), ?)";
            $leadParams[] = $extracted['phone'];
        }
        if (!empty($extracted['company'])) {
            $leadUpdates[] = "business_name = COALESCE(NULLIF(business_name, ''), ?)";
            $leadParams[] = $extracted['company'];
        }
        if (!empty($intent)) {
            $leadUpdates[] = "primary_intent = ?";
            $leadParams[] = $intent;
        }

        if (!empty($leadUpdates)) {
            $leadParams[] = $sessionId;
            $sql = "UPDATE chat_leads SET " . implode(', ', $leadUpdates) . ", updated_at = NOW() WHERE session_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($leadParams);
        }
    }

    jsonResponse([
        'success' => true,
        'collected' => $currentData,
        'message' => 'Extraction saved'
    ]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}
