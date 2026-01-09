<?php
/**
 * End Chat Session API
 * POST: Close a chat session and queue notification email
 */

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__, 4) . '/config/database.php';

handleCors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(['success' => false, 'error' => 'Invalid JSON input'], 400);
}

$sessionId = $input['session_id'] ?? null;
$visitorId = $input['visitor_id'] ?? null;
$reason = $input['reason'] ?? 'completed';

if (!$sessionId) {
    jsonResponse(['success' => false, 'error' => 'session_id is required'], 400);
}

// Check for API key or visitor_id authentication
$headers = getallheaders();
$apiKey = $headers['X-Chat-API-Key'] ?? $headers['x-chat-api-key'] ?? null;

if ($apiKey) {
    // Server-side call with API key
    validateApiKey();
} elseif ($visitorId) {
    // Widget call - verify visitor owns this session
    $stmt = $db->prepare("SELECT visitor_id FROM chat_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $sessionVisitor = $stmt->fetchColumn();

    if ($sessionVisitor !== $visitorId) {
        jsonResponse(['success' => false, 'error' => 'Unauthorized - visitor mismatch'], 403);
    }
} else {
    jsonResponse(['success' => false, 'error' => 'Authentication required'], 401);
}

try {
    // Get session details
    $stmt = $db->prepare("
        SELECT s.*,
               COUNT(m.id) as message_count,
               GROUP_CONCAT(DISTINCT m.intent) as intents
        FROM chat_sessions s
        LEFT JOIN chat_messages m ON m.session_id = s.id
        WHERE s.id = ?
        GROUP BY s.id
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        jsonResponse(['success' => false, 'error' => 'Session not found'], 404);
    }

    if ($session['status'] === 'closed') {
        jsonResponse([
            'success' => true,
            'message' => 'Session already closed',
            'session_id' => $sessionId,
            'email_queued' => false
        ]);
    }

    // Close the session
    $stmt = $db->prepare("
        UPDATE chat_sessions
        SET status = 'closed',
            last_activity = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$sessionId]);

    // Check if email notifications are enabled
    $stmt = $db->prepare("SELECT setting_value FROM n8n_chatbot_settings WHERE setting_key = 'email_on_session_end'");
    $stmt->execute();
    $emailEnabled = $stmt->fetchColumn() === 'true';

    $emailQueued = false;
    $emailId = null;

    if ($emailEnabled && (int)$session['message_count'] > 0) {
        // Get email settings
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM n8n_chatbot_settings WHERE setting_key IN ('email_to', 'email_cc', 'email_bcc')");
        $stmt->execute();
        $emailSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $emailTo = $emailSettings['email_to'] ?? '';

        if ($emailTo) {
            // Get conversation for email body
            $stmt = $db->prepare("
                SELECT role, content, created_at
                FROM chat_messages
                WHERE session_id = ?
                ORDER BY created_at ASC
            ");
            $stmt->execute([$sessionId]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format messages for email
            $messagesFormatted = '';
            foreach ($messages as $msg) {
                $roleLabel = strtoupper($msg['role']);
                $time = date('H:i', strtotime($msg['created_at']));
                $messagesFormatted .= "[{$time}] {$roleLabel}: {$msg['content']}\n\n";
            }

            // Build email subject and body
            $primaryIntent = $session['primary_intent'] ?: 'General';
            $subject = "[AbroadWorks Chat] New Conversation - {$primaryIntent}";

            $duration = '';
            if ($session['started_at']) {
                $start = new DateTime($session['started_at']);
                $end = new DateTime();
                $diff = $start->diff($end);
                $duration = $diff->format('%im %ss');
            }

            $irmLink = 'https://irm.abroadworks.com/modules/n8n_management/conversation-detail.php?id=' . $sessionId;

            $body = "══════════════════════════════════════\n";
            $body .= "SESSION DETAILS\n";
            $body .= "══════════════════════════════════════\n";
            $body .= "Session ID: {$sessionId}\n";
            $body .= "Date: " . date('Y-m-d H:i') . "\n";
            $body .= "Duration: {$duration}\n";
            $body .= "Messages: {$session['message_count']}\n";
            $body .= "Primary Intent: {$primaryIntent}\n";
            $body .= "Initial Page: {$session['initial_page_url']}\n\n";
            $body .= "══════════════════════════════════════\n";
            $body .= "CONVERSATION\n";
            $body .= "══════════════════════════════════════\n\n";
            $body .= $messagesFormatted;
            $body .= "══════════════════════════════════════\n";
            $body .= "View in IRM: {$irmLink}\n";

            // Generate tracking ID
            $trackingId = bin2hex(random_bytes(18));

            // Queue email
            $stmt = $db->prepare("
                INSERT INTO n8n_chat_emails
                (session_id, email_type, recipient_email, cc_emails, bcc_emails, subject, body, tracking_id, status, created_at)
                VALUES (?, 'session_summary', ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $sessionId,
                $emailTo,
                $emailSettings['email_cc'] ?? '',
                $emailSettings['email_bcc'] ?? '',
                $subject,
                $body,
                $trackingId
            ]);
            $emailId = $db->lastInsertId();
            $emailQueued = true;
        }
    }

    jsonResponse([
        'success' => true,
        'session_id' => $sessionId,
        'status' => 'closed',
        'email_queued' => $emailQueued,
        'email_id' => $emailId ? (int)$emailId : null
    ]);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
}
