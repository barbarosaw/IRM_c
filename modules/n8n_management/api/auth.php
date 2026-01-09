<?php
/**
 * API Authentication Helper
 * Validates X-Chat-API-Key header for n8n requests
 */

function validateApiKey() {
    global $db;

    // Get API key from header
    $headers = getallheaders();
    $apiKey = $headers['X-Chat-API-Key'] ?? $headers['x-chat-api-key'] ?? null;

    if (!$apiKey) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Missing API key']);
        exit;
    }

    // Ensure database connection exists
    if (!$db) {
        require_once dirname(__DIR__, 3) . '/config/database.php';
    }

    $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key` = ?");
    $stmt->execute(['n8n_chat_api_key']);
    $storedKey = $stmt->fetchColumn();

    if (!$storedKey || !hash_equals($storedKey, $apiKey)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid API key']);
        exit;
    }

    return true;
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Chat-API-Key');
    echo json_encode($data);
    exit;
}

function handleCors() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Chat-API-Key');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}
