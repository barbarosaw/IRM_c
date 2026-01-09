<?php
/**
 * PH Communications Module - Send SMS via m360 API
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../../includes/init.php';

header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check permission
if (!has_permission('ph_communications-send-sms')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$requiredFields = ['to_number', 'message'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Field '$field' is required"]);
        exit;
    }
}

try {
    // Load models
    require_once '../../models/PHSettings.php';
    require_once '../../models/SMSMessage.php';

    $settings = new PHSettings($db);
    $smsModel = new SMSMessage($db);

    // Get m360 credentials
    $credentials = $settings->getM360Credentials();

    if (empty($credentials['app_key']) || empty($credentials['app_secret'])) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'm360 credentials not configured']);
        exit;
    }

    // Normalize phone number (accept various formats)
    $toNumber = preg_replace('/[^0-9]/', '', $data['to_number']);

    // Convert to standard format (639XXXXXXXXX)
    if (strlen($toNumber) == 10) {
        $toNumber = '63' . $toNumber; // 9XXXXXXXXX -> 639XXXXXXXXX
    } elseif (strlen($toNumber) == 11 && $toNumber[0] == '0') {
        $toNumber = '63' . substr($toNumber, 1); // 09XXXXXXXXX -> 639XXXXXXXXX
    } elseif (strlen($toNumber) == 12 && $toNumber[0] != '6') {
        // Invalid format
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid phone number format']);
        exit;
    }

    // Validate PH number format
    if (!preg_match('/^639[0-9]{9}$/', $toNumber)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid Philippines phone number']);
        exit;
    }

    // Prepare m360 API request
    $clientTransId = uniqid('IRM_', true);
    $m360Payload = [
        'app_key' => $credentials['app_key'],
        'app_secret' => $credentials['app_secret'],
        'msisdn' => $toNumber,
        'content' => $data['message'],
        'rcvd_transid' => $clientTransId
    ];

    // Add shortcode if configured
    if (!empty($credentials['shortcode'])) {
        $m360Payload['shortcode_mask'] = $credentials['shortcode'];
    }

    // Send to m360 API
    $ch = curl_init($credentials['api_url']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($m360Payload),
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("m360 API Error: " . $curlError);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to connect to SMS provider']);
        exit;
    }

    $m360Response = json_decode($response, true);

    // Save to database
    $smsData = [
        'user_id' => $_SESSION['user_id'],
        'message_id' => $m360Response['messageId'] ?? null,
        'direction' => 'outbound',
        'from_number' => $credentials['shortcode'] ?? 'IRM',
        'to_number' => $toNumber,
        'message' => $data['message'],
        'status' => $httpCode == 201 ? 'sent' : 'failed',
        'telco_id' => $m360Response['telco_id'] ?? null,
        'msgcount' => $m360Response['msgcount'] ?? 1,
        'shortcode_mask' => $credentials['shortcode'] ?? null,
        'rcvd_transid' => $clientTransId
    ];

    $smsId = $smsModel->create($smsData);

    if ($httpCode == 201) {
        echo json_encode([
            'success' => true,
            'message' => 'SMS sent successfully',
            'data' => [
                'id' => $smsId,
                'message_id' => $m360Response['messageId'] ?? null,
                'timestamp' => $m360Response['timestamp'] ?? null,
                'msgcount' => $m360Response['msgcount'] ?? 1,
                'telco' => SMSMessage::getTelcoName($m360Response['telco_id'] ?? null)
            ]
        ]);
    } else {
        http_response_code($httpCode);
        echo json_encode([
            'success' => false,
            'error' => $m360Response['message'] ?? 'Failed to send SMS',
            'code' => $m360Response['code'] ?? $httpCode
        ]);
    }

} catch (Exception $e) {
    error_log("Send SMS Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
