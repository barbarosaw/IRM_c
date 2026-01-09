<?php
/**
 * Phone Calls Module - Status Callback Webhook
 *
 * This endpoint receives call status updates from Twilio.
 * Events: initiated, ringing, answered, completed
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

// Log all requests for debugging
error_log("Phone Calls Status Callback: " . json_encode($_POST));

header('Content-Type: application/json');

try {
    // Load models
    require_once '../models/PhoneCallSettings.php';
    require_once '../models/PhoneCall.php';

    $settings = new PhoneCallSettings($db);
    $phoneCallModel = new PhoneCall($db);

    // Get parameters from Twilio
    $callSid = $_POST['CallSid'] ?? $_POST['ParentCallSid'] ?? '';
    $callStatus = $_POST['CallStatus'] ?? '';
    $callDuration = isset($_POST['CallDuration']) ? (int) $_POST['CallDuration'] : 0;
    $timestamp = $_POST['Timestamp'] ?? date('Y-m-d H:i:s');

    if (empty($callSid)) {
        throw new Exception('Missing CallSid');
    }

    // Map Twilio status to our status
    $statusMap = [
        'queued' => 'initiated',
        'initiated' => 'initiated',
        'ringing' => 'ringing',
        'in-progress' => 'in-progress',
        'completed' => 'completed',
        'busy' => 'busy',
        'no-answer' => 'no-answer',
        'canceled' => 'canceled',
        'failed' => 'failed'
    ];

    $status = $statusMap[$callStatus] ?? $callStatus;

    // Prepare update data
    $updateData = [
        'status' => $status
    ];

    // Add duration if call is completed
    if (in_array($status, ['completed', 'busy', 'no-answer', 'canceled', 'failed'])) {
        $updateData['duration'] = $callDuration;
        $updateData['ended_at'] = date('Y-m-d H:i:s');

        // Calculate cost (approximate)
        // SDK: $0.004/min + PSTN US: $0.014/min = ~$0.018/min
        $minutes = ceil($callDuration / 60);
        $updateData['cost'] = $minutes * 0.018;
    }

    // Update call record
    $result = $phoneCallModel->updateBySid($callSid, $updateData);

    if ($result) {
        error_log("Phone Calls: Updated call $callSid to status $status");
    } else {
        error_log("Phone Calls: Failed to update call $callSid (may not exist yet)");
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Phone Calls Status Callback Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
