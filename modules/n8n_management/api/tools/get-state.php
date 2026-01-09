<?php
/**
 * Get Session State v2 - Enhanced Context Builder
 * Returns comprehensive conversation state for AI Manager Agent
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';

handleCors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$sessionId = $input['session_id'] ?? null;
$currentMessage = $input['message'] ?? null; // Current user message

if (!$sessionId) {
    jsonResponse(['success' => false, 'error' => 'session_id required'], 400);
}

try {
    // Get brain state
    $stmt = $db->prepare("SELECT * FROM conversation_brain WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $brain = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get lead data
    $stmt = $db->prepare("SELECT * FROM chat_leads WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get ALL messages for analysis
    $stmt = $db->prepare("
        SELECT role, content, created_at
        FROM chat_messages
        WHERE session_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$sessionId]);
    $allMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build collected data (merge from multiple sources)
    $collected = [];
    if ($brain && !empty($brain['collected_data'])) {
        $collected = json_decode($brain['collected_data'], true) ?: [];
    }
    if ($lead) {
        if (!empty($lead['full_name'])) $collected['name'] = $lead['full_name'];
        if (!empty($lead['email'])) $collected['email'] = $lead['email'];
        if (!empty($lead['phone'])) $collected['phone'] = $lead['phone'];
        if (!empty($lead['business_name'])) $collected['company'] = $lead['business_name'];
        if (!empty($lead['needs_summary'])) $collected['need'] = $lead['needs_summary'];
    }

    // Determine missing required data
    $requiredFields = ['name', 'email', 'phone'];
    $missingData = [];
    foreach ($requiredFields as $field) {
        if (empty($collected[$field])) {
            $missingData[] = $field;
        }
    }

    // Build conversation summary (all except last user message)
    $historySummary = [];
    $userMessages = [];
    $lastUserMessage = null;

    foreach ($allMessages as $msg) {
        $content = mb_convert_encoding($msg['content'], 'UTF-8', 'UTF-8');

        if ($msg['role'] === 'user') {
            $userMessages[] = $content;
            $lastUserMessage = $content;
        }

        // Summarize (short version for context)
        $role = $msg['role'] === 'user' ? 'Customer' : 'Assistant';
        $short = mb_substr($content, 0, 150, 'UTF-8');
        if (mb_strlen($content, 'UTF-8') > 150) $short .= '...';
        $historySummary[] = "$role: $short";
    }

    // Use current message if provided, otherwise use last from DB
    if ($currentMessage) {
        $lastUserMessage = $currentMessage;
    }

    // Detect repetition - similar questions asked multiple times
    $repetitionScore = 0;
    $questionKeywords = [];
    foreach ($userMessages as $um) {
        // Extract keywords from questions
        $words = array_filter(explode(' ', strtolower(preg_replace('/[^\w\s]/', '', $um))), function($w) {
            return strlen($w) > 3;
        });
        foreach ($words as $w) {
            if (!isset($questionKeywords[$w])) $questionKeywords[$w] = 0;
            $questionKeywords[$w]++;
        }
    }
    // High repetition if same keywords appear 3+ times
    foreach ($questionKeywords as $w => $count) {
        if ($count >= 3) $repetitionScore++;
    }

    // Detect if current message is a question
    $isQuestion = false;
    if ($lastUserMessage) {
        $isQuestion = preg_match('/\?|what|how|why|when|where|which|can|do|does|is|are|will|would|could/i', $lastUserMessage);
    }

    // Get intent history
    $intentHistory = [];
    if ($brain && !empty($brain['raw_history'])) {
        $rawHistory = json_decode($brain['raw_history'], true) ?: [];
        foreach ($rawHistory as $rh) {
            if (!empty($rh['intent'])) {
                $intentHistory[] = $rh['intent'];
            }
        }
    }

    // Determine suggested action
    $suggestedAction = 'continue_conversation';
    $turnCount = (int)($brain['turn_count'] ?? 0);

    if ($repetitionScore >= 3) {
        $suggestedAction = 'redirect_live_or_change_topic';
    } elseif ($isQuestion) {
        $suggestedAction = 'search_kb_and_answer';
    } elseif (!empty($missingData) && $turnCount >= 2) {
        $suggestedAction = 'collect_data';
    }

    // Get greeting message from settings
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'chat_greeting_message'");
    $stmt->execute();
    $greetingMessage = $stmt->fetchColumn() ?: "Hello! How can I help you today?";

    // Get system prompt from settings
    $stmt = $db->prepare("SELECT value FROM settings WHERE `key` = 'chat_system_prompt'");
    $stmt->execute();
    $systemPrompt = $stmt->fetchColumn() ?: "";

    // Search KB if it's a question
    $kbResults = [];
    if ($isQuestion && $lastUserMessage) {
        $kbResults = searchKnowledgeBase($db, $lastUserMessage);
    }

    // Build comprehensive state
    $state = [
        'session_id' => $sessionId,
        'turn_count' => $turnCount,

        // Messages
        'last_message' => $lastUserMessage,
        'history_summary' => array_slice($historySummary, -10), // Last 10 exchanges

        // Data collection status
        'collected_data' => $collected,
        'missing_data' => $missingData,
        'data_complete' => empty($missingData),

        // Analysis
        'is_question' => $isQuestion,
        'repetition_score' => $repetitionScore,
        'intent_history' => array_unique($intentHistory),

        // AI guidance
        'suggested_action' => $suggestedAction,
        'kb_results' => $kbResults,

        // Settings
        'greeting_message' => $greetingMessage,
        'system_prompt' => $systemPrompt,

        // Goals
        'current_goal' => $brain['current_goal'] ?? 'greet_and_identify',
        'goals_completed' => json_decode($brain['goals_completed'] ?? '[]', true) ?: []
    ];

    // Remove empty arrays and nulls for cleaner JSON
    $state = array_filter($state, function($v) {
        if (is_array($v)) return !empty($v);
        return $v !== null && $v !== '';
    });

    jsonResponse(['success' => true, 'state' => $state]);

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}

/**
 * Search Knowledge Base for relevant answers
 */
function searchKnowledgeBase($db, $query, $limit = 3) {
    $normalizedQuery = strtolower(preg_replace('/[^\w\s]/', '', $query));
    $queryWords = array_filter(explode(' ', $normalizedQuery), function($w) {
        return strlen($w) > 2;
    });

    if (empty($queryWords)) return [];

    // Build search
    $conditions = [];
    $params = [];

    foreach ($queryWords as $word) {
        $conditions[] = "(
            LOWER(i.question) LIKE ? OR
            LOWER(i.answer) LIKE ? OR
            LOWER(i.keywords) LIKE ?
        )";
        $likeWord = "%$word%";
        $params[] = $likeWord;
        $params[] = $likeWord;
        $params[] = $likeWord;
    }

    $sql = "
        SELECT
            i.question,
            i.answer,
            c.name as category
        FROM chat_kb_items i
        JOIN chat_kb_categories c ON i.category_id = c.id
        WHERE i.is_active = 1 AND c.is_active = 1
        AND (" . implode(" OR ", $conditions) . ")
        LIMIT ?
    ";
    $params[] = $limit;

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}
