<?php
/**
 * PH Communications Module - m360 MO (Mobile Originated) Webhook
 *
 * This endpoint receives incoming SMS messages from m360
 * Sample URL: https://irm.abroadworks.com/modules/ph_phone_calls/api/m360-sms/receive.php
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../../includes/init.php';

// Log incoming request
$requestData = $_GET;
error_log("m360 MO Webhook: " . json_encode($requestData));

// Validate required parameters
$requiredParams = ['transid', 'msisdn', 'message', 'timestamp', 'from'];
foreach ($requiredParams as $param) {
    if (!isset($_GET[$param])) {
        http_response_code(400);
        echo "Missing parameter: $param";
        exit;
    }
}

try {
    // Load models
    require_once '../../models/SMSMessage.php';
    $smsModel = new SMSMessage($db);

    // Check if we already received this message (duplicate prevention)
    $existing = $smsModel->getByMessageId($_GET['transid']);
    if ($existing) {
        error_log("m360 MO: Duplicate message ignored - " . $_GET['transid']);
        http_response_code(200);
        echo "OK";
        exit;
    }

    // Decode URL-encoded message
    $message = urldecode($_GET['message']);

    // Prepare SMS data
    $smsData = [
        'user_id' => 1, // System user for inbound SMS (or assign to specific user based on shortcode)
        'message_id' => $_GET['transid'],
        'direction' => 'inbound',
        'from_number' => $_GET['msisdn'],
        'to_number' => $_GET['from'], // Shortcode that received the message
        'message' => $message,
        'status' => 'received',
        'telco_id' => isset($_GET['telco_id']) ? (int)$_GET['telco_id'] : null,
        'msgcount' => isset($_GET['msgcount']) ? (int)$_GET['msgcount'] : 1
    ];

    // Save to database
    $smsId = $smsModel->create($smsData);

    if ($smsId) {
        error_log("m360 MO: Saved inbound SMS - ID: $smsId, From: {$_GET['msisdn']}");
        http_response_code(200);
        echo "OK";
    } else {
        error_log("m360 MO: Failed to save inbound SMS");
        http_response_code(500);
        echo "Failed to save message";
    }

} catch (Exception $e) {
    error_log("m360 MO Error: " . $e->getMessage());
    http_response_code(500);
    echo "Error processing inbound SMS";
}
