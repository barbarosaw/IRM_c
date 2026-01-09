<?php
/**
 * Widget Configuration API (Public)
 * GET: Returns widget configuration for the chat widget
 * No authentication required - this is called from external websites
 */

require_once dirname(__DIR__, 4) . '/config/database.php';

// CORS headers for cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get all chatbot settings
    $stmt = $db->query("SELECT setting_key, setting_value, setting_type FROM n8n_chatbot_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $config = [];
    foreach ($settings as $setting) {
        $value = $setting['setting_value'];

        // Convert value based on type
        switch ($setting['setting_type']) {
            case 'number':
                $value = (int)$value;
                break;
            case 'boolean':
                $value = $value === 'true' || $value === '1';
                break;
            case 'json':
                $value = json_decode($value, true);
                break;
        }

        // Convert setting keys to camelCase for JavaScript
        $key = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $setting['setting_key']))));
        $config[$key] = $value;
    }

    // Add IRM base URL for tracking pixel
    $config['irmBaseUrl'] = 'https://irm.abroadworks.com';

    // Use proxy URL instead of direct n8n webhook to avoid CORS issues
    // Widget will call IRM proxy, which forwards to n8n
    $config['webhookUrl'] = 'https://irm.abroadworks.com/modules/n8n_management/api/chat/webhook-proxy.php';

    echo json_encode([
        'success' => true,
        'config' => $config
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration error']);
}
