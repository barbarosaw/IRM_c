<?php
/**
 * Smart Context API v2
 * GET: Retrieve compact context for AI
 * POST: Update lead data and context
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
        // Get lead data
        $stmt = $db->prepare("SELECT * FROM chat_leads WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lead) {
            // No lead yet - return empty context
            jsonResponse([
                'success' => true,
                'has_lead' => false,
                'context_for_ai' => buildEmptyContext(),
                'lead_data' => null,
                'missing_fields' => ['full_name', 'email', 'phone', 'business_name']
            ]);
        }

        // Build compact context for AI
        $contextForAI = buildContextForAI($lead);
        $missingFields = getMissingFields($lead);

        jsonResponse([
            'success' => true,
            'has_lead' => true,
            'context_for_ai' => $contextForAI,
            'lead_data' => [
                'full_name' => $lead['full_name'],
                'email' => $lead['email'],
                'phone' => $lead['phone'],
                'position_needed' => $lead['position_needed'],
                'business_name' => $lead['business_name'],
                'location' => $lead['location'],
                'company_size' => $lead['company_size'],
                'industry' => $lead['industry'],
                'primary_intent' => $lead['primary_intent'],
                'purchase_likelihood' => $lead['purchase_likelihood']
            ],
            'interests' => $lead['interests'] ? json_decode($lead['interests'], true) : [],
            'qa_history' => $lead['qa_history'] ? json_decode($lead['qa_history'], true) : [],
            'missing_fields' => $missingFields
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
    $visitorId = $input['visitor_id'] ?? null;

    if (!$sessionId) {
        jsonResponse(['success' => false, 'error' => 'session_id is required'], 400);
    }

    try {
        // Check if lead exists
        $stmt = $db->prepare("SELECT * FROM chat_leads WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            // Create new lead
            $stmt = $db->prepare("INSERT INTO chat_leads (session_id, visitor_id) VALUES (?, ?)");
            $stmt->execute([$sessionId, $visitorId]);
            $existing = [
                'interests' => null,
                'qa_history' => null
            ];
        }

        // Prepare updates
        $updates = [];
        $params = [];

        // User info fields
        $fields = ['full_name', 'email', 'phone', 'position_needed', 'business_name',
                   'location', 'company_size', 'industry', 'primary_intent', 'purchase_likelihood'];

        foreach ($fields as $field) {
            if (isset($input[$field]) && $input[$field] !== null && $input[$field] !== '' && $input[$field] !== 'null') {
                $updates[] = "$field = ?";
                $params[] = $input[$field];
            }
        }

        // Handle interests (merge with existing)
        if (isset($input['interests']) && is_array($input['interests'])) {
            $existingInterests = $existing['interests'] ? json_decode($existing['interests'], true) : [];
            $newInterests = array_unique(array_merge($existingInterests, $input['interests']));
            $updates[] = "interests = ?";
            $params[] = json_encode($newInterests);
        }

        // Handle QA history (append new entries)
        if (isset($input['qa_entry']) && is_array($input['qa_entry'])) {
            $existingQA = $existing['qa_history'] ? json_decode($existing['qa_history'], true) : [];
            // Keep only last 10 entries to limit context size
            $existingQA[] = $input['qa_entry'];
            if (count($existingQA) > 10) {
                $existingQA = array_slice($existingQA, -10);
            }
            $updates[] = "qa_history = ?";
            $params[] = json_encode($existingQA);
        }

        // Update context summary
        if (isset($input['context_summary'])) {
            $updates[] = "context_summary = ?";
            $params[] = $input['context_summary'];
        }

        if (!empty($updates)) {
            $params[] = $sessionId;
            $sql = "UPDATE chat_leads SET " . implode(', ', $updates) . " WHERE session_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
        }

        // Get updated lead
        $stmt = $db->prepare("SELECT * FROM chat_leads WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        $missingFields = getMissingFields($lead);

        jsonResponse([
            'success' => true,
            'lead_data' => [
                'full_name' => $lead['full_name'],
                'email' => $lead['email'],
                'phone' => $lead['phone'],
                'position_needed' => $lead['position_needed'],
                'business_name' => $lead['business_name'],
                'location' => $lead['location'],
                'company_size' => $lead['company_size'],
                'industry' => $lead['industry'],
                'primary_intent' => $lead['primary_intent'],
                'purchase_likelihood' => $lead['purchase_likelihood']
            ],
            'missing_fields' => $missingFields,
            'is_complete' => empty($missingFields),
            'context_for_ai' => buildContextForAI($lead)
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
    }
}

jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);

/**
 * Build empty context for new sessions
 */
function buildEmptyContext() {
    return "NEW VISITOR - No information collected yet.\n" .
           "Priority: Collect name, email, phone, company name.\n" .
           "Be friendly and ask for ONE piece of info naturally in your response.";
}

/**
 * Build compact context for AI
 */
function buildContextForAI($lead) {
    $lines = [];

    // User Profile Section
    $profile = [];
    if ($lead['full_name']) $profile[] = "Name: {$lead['full_name']}";
    if ($lead['email']) $profile[] = "Email: {$lead['email']}";
    if ($lead['phone']) $profile[] = "Phone: {$lead['phone']}";
    if ($lead['business_name']) $profile[] = "Company: {$lead['business_name']}";
    if ($lead['industry']) $profile[] = "Industry: {$lead['industry']}";
    if ($lead['company_size']) $profile[] = "Size: {$lead['company_size']}";
    if ($lead['location']) $profile[] = "Location: {$lead['location']}";
    if ($lead['position_needed']) $profile[] = "Looking for: {$lead['position_needed']}";

    if (!empty($profile)) {
        $lines[] = "=== USER (ALREADY KNOWN - DO NOT ASK AGAIN) ===";
        $lines[] = implode(" | ", $profile);
    }

    // Missing fields
    $missing = getMissingFields($lead);
    if (!empty($missing)) {
        $priority = array_slice($missing, 0, 2); // Show top 2 missing
        $lines[] = "";
        $lines[] = "=== NEED TO COLLECT (ask ONE naturally) ===";
        $lines[] = implode(", ", $priority);
    } else {
        $lines[] = "";
        $lines[] = "=== ALL INFO COLLECTED - Focus on helping ===";
    }

    // Interests
    $interests = $lead['interests'] ? json_decode($lead['interests'], true) : [];
    if (!empty($interests)) {
        $lines[] = "";
        $lines[] = "=== INTERESTS ===";
        $lines[] = implode(", ", $interests);
    }

    // Intent
    if ($lead['primary_intent']) {
        $lines[] = "";
        $lines[] = "=== INTENT: {$lead['primary_intent']} ===";
    }

    // QA History (compact)
    $qaHistory = $lead['qa_history'] ? json_decode($lead['qa_history'], true) : [];
    if (!empty($qaHistory)) {
        $lines[] = "";
        $lines[] = "=== PREVIOUS Q&A (don't repeat) ===";
        foreach ($qaHistory as $qa) {
            $q = $qa['q'] ?? '';
            $a = $qa['a'] ?? '';
            if ($q && $a) {
                $lines[] = "Q: $q → A: $a";
            }
        }
    }

    // Purchase likelihood
    if ($lead['purchase_likelihood']) {
        $lines[] = "";
        $lines[] = "=== PURCHASE LIKELIHOOD: {$lead['purchase_likelihood']} ===";
    }

    return implode("\n", $lines);
}

/**
 * Get list of missing required fields
 */
function getMissingFields($lead) {
    $required = [
        'full_name' => 'name',
        'email' => 'email',
        'phone' => 'phone',
        'business_name' => 'company'
    ];

    $missing = [];
    foreach ($required as $field => $label) {
        if (empty($lead[$field])) {
            $missing[] = $label;
        }
    }
    return $missing;
}
