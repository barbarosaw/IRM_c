<?php
/**
 * PH Communications Module - m360 DLR (Delivery Report) Webhook
 *
 * This endpoint receives delivery status updates from m360
 * Sample URL: https://irm.abroadworks.com/modules/ph_phone_calls/api/m360-sms/dlr.php
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../../includes/init.php';

// Log incoming request
$requestData = $_GET;
error_log("m360 DLR Webhook: " . json_encode($requestData));

// Validate required parameters
$requiredParams = ['transid', 'msisdn', 'status_code', 'timestamp'];
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

    // Get message by m360 message ID
    $message = $smsModel->getByMessageId($_GET['transid']);

    if (!$message) {
        error_log("m360 DLR: Message not found - " . $_GET['transid']);
        http_response_code(404);
        echo "Message not found";
        exit;
    }

    // Map m360 status codes to our status
    $statusMap = [
        8 => 'acknowledged',  // Acknowledged/Successful
        1 => 'delivered',     // Delivered
        16 => 'rejected',     // Rejected
        34 => 'expired',      // Expired
        2 => 'undelivered'    // Undelivered
    ];

    $statusCode = (int) $_GET['status_code'];
    $newStatus = $statusMap[$statusCode] ?? 'unknown';

    // Update message status
    $updateData = [
        'status' => $newStatus,
        'status_code' => $statusCode
    ];

    // If delivered, set delivered_at timestamp
    if ($statusCode == 1 || $statusCode == 8) {
        $updateData['delivered_at'] = date('Y-m-d H:i:s', strtotime($_GET['timestamp']));
    }

    $smsModel->updateByMessageId($_GET['transid'], $updateData);

    error_log("m360 DLR: Updated message {$_GET['transid']} to status: $newStatus");

    http_response_code(200);
    echo "OK";

} catch (Exception $e) {
    error_log("m360 DLR Error: " . $e->getMessage());
    http_response_code(500);
    echo "Error processing DLR";
}
