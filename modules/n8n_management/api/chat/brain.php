<?php
/**
 * Conversation Brain API
 * GET: Get brain state and AI instructions
 * POST: Update brain state after AI response
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/lib/RulesEngine.php';

handleCors();
validateApiKey();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $sessionId = $_GET['session_id'] ?? null;
    $userMessage = $_GET['message'] ?? '';
    $detectedIntent = $_GET['intent'] ?? 'unknown';

    if (!$sessionId) {
        jsonResponse(['success' => false, 'error' => 'session_id is required'], 400);
    }

    try {
        // Get or create brain
        $brain = getBrain($db, $sessionId);

        // Get lead data
        $lead = getLead($db, $sessionId);

        // Build context for rules engine
        $context = buildContext($brain, $lead, $userMessage, $detectedIntent);

        // Run rules engine
        $engine = new RulesEngine($db);
        $result = $engine->evaluate($context);

        // Apply state updates from rules
        $updates = [];
        if (isset($context['new_state'])) {
            $updates['state'] = $context['new_state'];
        }
        if (isset($context['new_engagement'])) {
            $updates['engagement_level'] = $context['new_engagement'];
        }
        if (isset($context['booking_updates'])) {
            $bookingIntent = $brain['booking_intent'] ? json_decode($brain['booking_intent'], true) : [];
            $bookingIntent = array_merge($bookingIntent, $context['booking_updates']);
            $updates['booking_intent'] = json_encode($bookingIntent);
        }
        if (isset($context['topic_updates'])) {
            $topics = $brain['topics_discussed'] ? json_decode($brain['topics_discussed'], true) : [];
            foreach ($context['topic_updates'] as $topic => $data) {
                if (!isset($topics[$topic])) {
                    $topics[$topic] = [];
                }
                $topics[$topic] = array_merge($topics[$topic], $data);
            }
            $updates['topics_discussed'] = json_encode($topics);
        }

        // Update brain with pending actions and active rules
        $updates['pending_actions'] = json_encode($result['pending_actions']);
        $updates['active_rules'] = json_encode($result['triggered_rules']);
        $updates['ai_instructions'] = $result['ai_instructions'];
        $updates['last_user_intent'] = $detectedIntent;

        updateBrain($db, $sessionId, $updates);

        // Build final context summary for AI
        $contextSummary = buildContextSummary($brain, $lead, $result);

        jsonResponse([
            'success' => true,
            'brain' => [
                'state' => $updates['state'] ?? $brain['state'],
                'engagement_level' => $updates['engagement_level'] ?? $brain['engagement_level'],
                'turn_count' => (int)$brain['turn_count']
            ],
            'lead' => $lead,
            'lead_completeness' => calculateLeadCompleteness($lead),
            'triggered_rules' => $result['triggered_rules'],
            'pending_actions' => $result['pending_actions'],
            'context_for_ai' => $contextSummary,
            'ai_instructions' => $result['ai_instructions']
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
    }
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['session_id'])) {
        jsonResponse(['success' => false, 'error' => 'session_id is required'], 400);
    }

    $sessionId = $input['session_id'];

    try {
        $updates = [];

        // Update booking intent
        if (isset($input['booking'])) {
            $stmt = $db->prepare("SELECT booking_intent FROM conversation_brain WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $current = $stmt->fetchColumn();
            $bookingIntent = $current ? json_decode($current, true) : [];
            $bookingIntent = array_merge($bookingIntent, $input['booking']);
            $updates['booking_intent'] = json_encode($bookingIntent);
        }

        // Update topics discussed
        if (isset($input['topics'])) {
            $stmt = $db->prepare("SELECT topics_discussed FROM conversation_brain WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $current = $stmt->fetchColumn();
            $topics = $current ? json_decode($current, true) : [];
            foreach ($input['topics'] as $topic => $data) {
                if (!isset($topics[$topic])) {
                    $topics[$topic] = [];
                }
                $topics[$topic] = array_merge($topics[$topic], $data);
            }
            $updates['topics_discussed'] = json_encode($topics);
        }

        // Update state
        if (isset($input['state'])) {
            $updates['state'] = $input['state'];
        }

        // Update engagement
        if (isset($input['engagement_level'])) {
            $updates['engagement_level'] = $input['engagement_level'];
        }

        // Update last AI action
        if (isset($input['last_ai_action'])) {
            $updates['last_ai_action'] = $input['last_ai_action'];
        }

        // Increment turn count
        $updates['turn_count_increment'] = true;

        // Update lead data
        if (isset($input['lead'])) {
            updateLead($db, $sessionId, $input['visitor_id'] ?? null, $input['lead']);
        }

        if (!empty($updates)) {
            updateBrain($db, $sessionId, $updates);
        }

        jsonResponse(['success' => true, 'updated' => array_keys($updates)]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Error: ' . $e->getMessage()], 500);
    }
}

jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);

// ============================================
// HELPER FUNCTIONS
// ============================================

function getBrain($db, $sessionId)
{
    $stmt = $db->prepare("SELECT * FROM conversation_brain WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $brain = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$brain) {
        // Create new brain
        $stmt = $db->prepare("INSERT INTO conversation_brain (session_id) VALUES (?)");
        $stmt->execute([$sessionId]);

        return [
            'session_id' => $sessionId,
            'state' => 'greeting',
            'engagement_level' => 'medium',
            'booking_intent' => null,
            'topics_discussed' => null,
            'pending_actions' => null,
            'active_rules' => null,
            'context_summary' => null,
            'ai_instructions' => null,
            'last_user_intent' => null,
            'last_ai_action' => null,
            'turn_count' => 0
        ];
    }

    return $brain;
}

function getLead($db, $sessionId)
{
    $stmt = $db->prepare("SELECT * FROM chat_leads WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lead) {
        return [
            'full_name' => null,
            'email' => null,
            'phone' => null,
            'business_name' => null,
            'industry' => null,
            'company_size' => null,
            'location' => null,
            'position_needed' => null,
            'primary_intent' => null,
            'purchase_likelihood' => null
        ];
    }

    return $lead;
}

function updateBrain($db, $sessionId, $updates)
{
    $setClauses = [];
    $params = [];

    foreach ($updates as $field => $value) {
        if ($field === 'turn_count_increment') {
            $setClauses[] = "turn_count = turn_count + 1";
        } else {
            $setClauses[] = "$field = ?";
            $params[] = $value;
        }
    }

    if (empty($setClauses)) {
        return;
    }

    $params[] = $sessionId;
    $sql = "UPDATE conversation_brain SET " . implode(', ', $setClauses) . " WHERE session_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
}

function updateLead($db, $sessionId, $visitorId, $leadData)
{
    // Check if lead exists
    $stmt = $db->prepare("SELECT id FROM chat_leads WHERE session_id = ?");
    $stmt->execute([$sessionId]);

    if (!$stmt->fetchColumn()) {
        // Create lead
        $stmt = $db->prepare("INSERT INTO chat_leads (session_id, visitor_id) VALUES (?, ?)");
        $stmt->execute([$sessionId, $visitorId]);
    }

    // Update fields
    $fields = ['full_name', 'email', 'phone', 'business_name', 'industry', 'company_size', 'location', 'position_needed', 'primary_intent', 'purchase_likelihood'];

    $setClauses = [];
    $params = [];

    foreach ($leadData as $field => $value) {
        if (in_array($field, $fields) && $value !== null && $value !== '' && $value !== 'null') {
            $setClauses[] = "$field = ?";
            $params[] = $value;
        }
    }

    // Handle interests and qa_history
    if (isset($leadData['interests']) && is_array($leadData['interests'])) {
        $stmt = $db->prepare("SELECT interests FROM chat_leads WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $current = $stmt->fetchColumn();
        $existing = $current ? json_decode($current, true) : [];
        $merged = array_unique(array_merge($existing, $leadData['interests']));
        $setClauses[] = "interests = ?";
        $params[] = json_encode($merged);
    }

    if (isset($leadData['qa_entry']) && is_array($leadData['qa_entry'])) {
        $stmt = $db->prepare("SELECT qa_history FROM chat_leads WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $current = $stmt->fetchColumn();
        $existing = $current ? json_decode($current, true) : [];
        $existing[] = $leadData['qa_entry'];
        if (count($existing) > 15) {
            $existing = array_slice($existing, -15);
        }
        $setClauses[] = "qa_history = ?";
        $params[] = json_encode($existing);
    }

    if (!empty($setClauses)) {
        $params[] = $sessionId;
        $sql = "UPDATE chat_leads SET " . implode(', ', $setClauses) . " WHERE session_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }
}

function buildContext($brain, $lead, $userMessage, $detectedIntent)
{
    $topics = $brain['topics_discussed'] ? json_decode($brain['topics_discussed'], true) : [];
    $booking = $brain['booking_intent'] ? json_decode($brain['booking_intent'], true) : [];

    // Check if any topic has been explained
    $topicsAnyExplained = false;
    foreach ($topics as $topic => $data) {
        if (!empty($data['service_explained']) || !empty($data['mentioned'])) {
            $topicsAnyExplained = true;
            break;
        }
    }

    // Detect repeated question
    $repeatedQuestion = (
        stripos($userMessage, 'already') !== false ||
        stripos($userMessage, 'i told you') !== false ||
        stripos($userMessage, 'i said') !== false ||
        stripos($userMessage, 'again') !== false
    );

    return [
        'state' => $brain['state'],
        'engagement_level' => $brain['engagement_level'],
        'turn_count' => (int)$brain['turn_count'],
        'last_user_intent' => $detectedIntent,
        'user_message' => $userMessage,

        'lead' => $lead,
        'lead_completeness' => calculateLeadCompleteness($lead),

        'booking' => $booking,

        'topics' => $topics,
        'topics_any_explained' => $topicsAnyExplained,

        'repeated_question_detected' => $repeatedQuestion,
        'message_contains_competitor' => detectCompetitor($userMessage)
    ];
}

function calculateLeadCompleteness($lead)
{
    $required = ['full_name', 'email', 'phone', 'business_name'];
    $filled = 0;

    foreach ($required as $field) {
        if (!empty($lead[$field])) {
            $filled++;
        }
    }

    return round(($filled / count($required)) * 100);
}

function detectCompetitor($message)
{
    $competitors = ['upwork', 'fiverr', 'belay', 'time etc', 'boldly', 'fancy hands'];
    $lower = strtolower($message);

    foreach ($competitors as $comp) {
        if (stripos($lower, $comp) !== false) {
            return true;
        }
    }

    return false;
}

function buildContextSummary($brain, $lead, $rulesResult)
{
    $lines = [];

    // Lead info
    $leadInfo = [];
    if (!empty($lead['full_name'])) $leadInfo[] = "Name: {$lead['full_name']}";
    if (!empty($lead['email'])) $leadInfo[] = "Email: {$lead['email']}";
    if (!empty($lead['phone'])) $leadInfo[] = "Phone: {$lead['phone']}";
    if (!empty($lead['business_name'])) $leadInfo[] = "Company: {$lead['business_name']}";
    if (!empty($lead['industry'])) $leadInfo[] = "Industry: {$lead['industry']}";
    if (!empty($lead['position_needed'])) $leadInfo[] = "Looking for: {$lead['position_needed']}";

    if (!empty($leadInfo)) {
        $lines[] = "=== USER INFO (DO NOT ASK FOR THESE AGAIN) ===";
        $lines[] = implode(' | ', $leadInfo);
        $lines[] = "";
    }

    // Missing lead fields
    $missing = [];
    if (empty($lead['full_name'])) $missing[] = 'name';
    if (empty($lead['email'])) $missing[] = 'email';
    if (empty($lead['phone'])) $missing[] = 'phone';
    if (empty($lead['business_name'])) $missing[] = 'company';

    if (!empty($missing)) {
        $lines[] = "=== MISSING INFO (collect ONE naturally) ===";
        $lines[] = implode(', ', $missing);
        $lines[] = "";
    }

    // Booking status
    $booking = $brain['booking_intent'] ? json_decode($brain['booking_intent'], true) : [];
    if (!empty($booking)) {
        $lines[] = "=== BOOKING INFO ===";
        if (!empty($booking['requested_date'])) {
            $lines[] = "Date: {$booking['requested_date']}";
        }
        if (!empty($booking['requested_time'])) {
            $lines[] = "Time: {$booking['requested_time']}";
            if (empty($booking['timezone_confirmed'])) {
                $lines[] = "** TIMEZONE NOT CONFIRMED **";
            }
        }
        if (!empty($booking['status'])) {
            $lines[] = "Status: {$booking['status']}";
        }
        $lines[] = "";
    }

    // Topics discussed
    $topics = $brain['topics_discussed'] ? json_decode($brain['topics_discussed'], true) : [];
    if (!empty($topics)) {
        $lines[] = "=== TOPICS DISCUSSED ===";
        foreach ($topics as $topic => $data) {
            $details = [];
            if (!empty($data['service_explained'])) $details[] = 'explained';
            if (!empty($data['pricing_shared'])) $details[] = 'pricing given';
            $lines[] = "- " . str_replace('_', ' ', $topic) . (!empty($details) ? ' (' . implode(', ', $details) . ')' : '');
        }
        $lines[] = "";
    }

    // Current state
    $lines[] = "=== CURRENT STATE: {$brain['state']} ===";
    $lines[] = "";

    return implode("\n", $lines);
}
