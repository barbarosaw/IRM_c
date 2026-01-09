<?php
/**
 * Chat Webhook Proxy
 * Forwards chat requests to n8n webhook to avoid CORS issues
 * POST: Forward message to n8n and return response
 */

require_once dirname(__DIR__, 4) . '/config/database.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get webhook URL from settings
$stmt = $db->query("SELECT setting_value FROM n8n_chatbot_settings WHERE setting_key = 'webhook_url'");
$webhookUrl = $stmt->fetchColumn();

if (!$webhookUrl) {
    echo json_encode([
        'success' => false,
        'error' => 'Webhook URL not configured',
        'response' => 'Chat is not configured. Please contact support.'
    ]);
    exit;
}

// Get input data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON input'
    ]);
    exit;
}

// Forward to n8n webhook
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $webhookUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_POSTFIELDS => $input,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    error_log("n8n webhook error: $error");
    echo json_encode([
        'success' => false,
        'error' => 'Failed to connect to chat service',
        'response' => 'Sorry, I\'m having trouble connecting. Please try again in a moment.'
    ]);
    exit;
}

if ($httpCode >= 400) {
    error_log("n8n webhook HTTP error: $httpCode - $response");
    echo json_encode([
        'success' => false,
        'error' => "Chat service error (HTTP $httpCode)",
        'response' => 'Sorry, something went wrong. Please try again.'
    ]);
    exit;
}

// Parse and forward response
$result = json_decode($response, true);

if ($result === null) {
    // If n8n returns plain text, wrap it
    echo json_encode([
        'success' => true,
        'response' => $response
    ]);
} else {
    // Forward JSON response as-is
    echo $response;
}
