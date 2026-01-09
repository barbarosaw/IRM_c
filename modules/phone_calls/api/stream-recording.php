<?php
/**
 * Phone Calls Module - Stream Recording Proxy
 *
 * This endpoint proxies recording audio from Twilio with proper authentication.
 * Direct Twilio recording URLs require Basic Auth, so we fetch and stream them.
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Check permission - also allow phone_calls-history permission
if (!has_permission('phone_calls-recordings') && !has_permission('phone_calls-history')) {
    http_response_code(403);
    exit('Permission denied');
}

// Get call ID from request
$callId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$callId) {
    http_response_code(400);
    exit('Missing call ID');
}

try {
    // Load settings and models
    require_once '../models/PhoneCallSettings.php';
    require_once '../models/PhoneCall.php';

    $settings = new PhoneCallSettings($db);
    $phoneCallModel = new PhoneCall($db);

    // Get call record
    $call = $phoneCallModel->getById($callId);

    if (!$call || empty($call['recording_url'])) {
        http_response_code(404);
        exit('Recording not found');
    }

    // Get Twilio credentials
    $accountSid = $settings->getAccountSid();
    $authToken = $settings->getAuthToken();

    if (!$accountSid || !$authToken) {
        http_response_code(500);
        exit('Twilio not configured');
    }

    // Twilio recording URL - ensure it ends with .mp3 for proper format
    $recordingUrl = $call['recording_url'];
    if (strpos($recordingUrl, '.mp3') === false && strpos($recordingUrl, '.wav') === false) {
        $recordingUrl .= '.mp3';
    }

    // Fetch recording from Twilio with authentication
    $ch = curl_init($recordingUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERPWD => "{$accountSid}:{$authToken}",
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_MAXREDIRS => 5
    ]);

    $audioData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200 || $curlError) {
        error_log("Stream Recording: Failed to fetch recording for call $callId - HTTP $httpCode, Error: $curlError, URL: $recordingUrl");
        http_response_code(404);
        exit('Recording not available');
    }

    if (empty($audioData)) {
        error_log("Stream Recording: Empty response for call $callId");
        http_response_code(404);
        exit('Recording not available');
    }

    // Clean ALL output buffers before sending audio
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Set proper headers for audio streaming
    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . strlen($audioData));
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=86400');
    header('Content-Disposition: inline; filename="recording_' . $callId . '.mp3"');

    // Output the audio data
    echo $audioData;
    exit;

} catch (Exception $e) {
    error_log("Stream Recording Error: " . $e->getMessage());
    http_response_code(500);
    exit('Error streaming recording');
}
