<?php
/**
 * Chat Leads API
 * POST: Update collected lead information for a session
 * GET: Retrieve lead information for a session
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';

handleCors();

validateApiKey();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $sessionId = $_GET['session_id'] ?? null;

    if (!$sessionId) {
        jsonResponse(['success' => false, 'error' => 'session_id is required'], 400);
    }

    try {
        $stmt = $db->prepare("
            SELECT primary_intent, collected_info, is_job_seeker
            FROM chat_sessions
            WHERE id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            jsonResponse(['success' => false, 'error' => 'Session not found'], 404);
        }

        $collectedInfo = $session['collected_info'] ? json_decode($session['collected_info'], true) : [];

        jsonResponse([
            'success' => true,
            'primary_intent' => $session['primary_intent'],
            'is_job_seeker' => (bool)$session['is_job_seeker'],
            'collected_info' => $collectedInfo
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        jsonResponse(['success' => false, 'error' => 'Invalid JSON input'], 400);
    }

    $sessionId = $input['session_id'] ?? null;

    if (!$sessionId) {
        jsonResponse(['success' => false, 'error' => 'session_id is required'], 400);
    }

    try {
        // Get current collected_info
        $stmt = $db->prepare("SELECT collected_info FROM chat_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $current = $stmt->fetchColumn();
        $currentInfo = $current ? json_decode($current, true) : [];

        // Merge new data with existing
        $newData = $input['collected_info'] ?? [];
        $mergedInfo = array_merge($currentInfo, $newData);

        // Remove null/empty values
        $mergedInfo = array_filter($mergedInfo, function($v) {
            return $v !== null && $v !== '';
        });

        // Build update query
        $updates = ['collected_info = ?'];
        $params = [json_encode($mergedInfo)];

        if (isset($input['primary_intent'])) {
            $updates[] = 'primary_intent = ?';
            $params[] = $input['primary_intent'];
        }

        if (isset($input['is_job_seeker'])) {
            $updates[] = 'is_job_seeker = ?';
            $params[] = (int)$input['is_job_seeker'];
        }

        $params[] = $sessionId;

        $sql = "UPDATE chat_sessions SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        // Calculate completion percentage
        $requiredFields = ['name', 'email', 'phone', 'company'];
        $optionalFields = ['sector', 'employee_count', 'interest_area'];

        $requiredFilled = 0;
        $optionalFilled = 0;

        foreach ($requiredFields as $field) {
            if (!empty($mergedInfo[$field])) $requiredFilled++;
        }
        foreach ($optionalFields as $field) {
            if (!empty($mergedInfo[$field])) $optionalFilled++;
        }

        $completion = ($requiredFilled / count($requiredFields)) * 100;
        $isComplete = $requiredFilled === count($requiredFields);

        jsonResponse([
            'success' => true,
            'collected_info' => $mergedInfo,
            'completion_percent' => round($completion),
            'is_complete' => $isComplete,
            'missing_required' => array_values(array_filter($requiredFields, function($f) use ($mergedInfo) {
                return empty($mergedInfo[$f]);
            }))
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
