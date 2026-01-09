<?php
/**
 * Phone Calls Module - Recording Callback Webhook
 *
 * This endpoint receives recording status updates from Twilio.
 * Called when a recording is ready.
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

// Log all requests for debugging
error_log("Phone Calls Recording Callback: " . json_encode($_POST));

header('Content-Type: application/json');

try {
    // Load models
    require_once '../models/PhoneCall.php';

    $phoneCallModel = new PhoneCall($db);

    // Get parameters from Twilio
    $callSid = $_POST['CallSid'] ?? '';
    $recordingSid = $_POST['RecordingSid'] ?? '';
    $recordingUrl = $_POST['RecordingUrl'] ?? '';
    $recordingDuration = isset($_POST['RecordingDuration']) ? (int) $_POST['RecordingDuration'] : 0;
    $recordingStatus = $_POST['RecordingStatus'] ?? '';

    if (empty($callSid) || empty($recordingSid)) {
        throw new Exception('Missing required parameters');
    }

    // Only process completed recordings
    if ($recordingStatus !== 'completed') {
        echo json_encode(['success' => true, 'message' => 'Recording not completed yet']);
        exit;
    }

    // Get the call record
    $call = $phoneCallModel->getBySid($callSid);

    if (!$call) {
        throw new Exception("Call not found: $callSid");
    }

    // Update call with recording URL
    $phoneCallModel->updateBySid($callSid, [
        'recording_url' => $recordingUrl . '.mp3', // Add .mp3 for direct playback
        'recording_duration' => $recordingDuration
    ]);

    // Also save to recordings table
    $stmt = $db->prepare("
        INSERT INTO phone_call_recordings (call_id, recording_sid, url, duration)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE url = VALUES(url), duration = VALUES(duration)
    ");
    $stmt->execute([
        $call['id'],
        $recordingSid,
        $recordingUrl . '.mp3',
        $recordingDuration
    ]);

    error_log("Phone Calls: Recording saved for call $callSid (RecordingSid: $recordingSid)");

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Phone Calls Recording Callback Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
