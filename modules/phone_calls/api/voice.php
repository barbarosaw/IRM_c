<?php
/**
 * Phone Calls Module - TwiML Voice Webhook
 *
 * This endpoint is called by Twilio when a call is initiated from the browser.
 * It returns TwiML instructions to dial the destination number.
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

// Set content type to XML for TwiML
header('Content-Type: text/xml');

try {
    // Load settings
    require_once '../models/PhoneCallSettings.php';
    require_once '../models/PhoneCall.php';

    $settings = new PhoneCallSettings($db);
    $phoneCallModel = new PhoneCall($db);

    // Get parameters from Twilio
    $to = $_POST['To'] ?? $_GET['To'] ?? '';
    $from = $settings->getPhoneNumber();
    $callSid = $_POST['CallSid'] ?? '';
    $caller = $_POST['Caller'] ?? ''; // This is the identity (user_X)

    // Extract user ID from caller identity
    $userId = null;
    if (preg_match('/user_(\d+)/', $caller, $matches)) {
        $userId = (int) $matches[1];
    }

    // Log the incoming request
    error_log("Phone Calls Voice Webhook: To=$to, From=$from, CallSid=$callSid, Caller=$caller, UserId=$userId");

    // Validate destination number (US or Philippines only)
    $isValidUS = preg_match('/^\+1[2-9][0-9]{9}$/', $to);
    $isValidPH = preg_match('/^\+63[2-9][0-9]{9}$/', $to);

    if (!$isValidUS && !$isValidPH) {
        // Invalid number - return error TwiML
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<Response>';
        echo '<Say voice="alice">Sorry, only United States and Philippines phone numbers are allowed.</Say>';
        echo '<Hangup/>';
        echo '</Response>';
        exit;
    }

    // Create call record in database
    if ($userId && $callSid) {
        $phoneCallModel->create([
            'call_sid' => $callSid,
            'user_id' => $userId,
            'direction' => 'outbound',
            'from_number' => $from,
            'to_number' => $to,
            'status' => 'initiated'
        ]);
    }

    // Build TwiML response
    $recordingEnabled = $settings->isRecordingEnabled();
    $maxDuration = $settings->getMaxCallDuration() * 60; // Convert to seconds
    $statusCallbackUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/modules/phone_calls/api/status-callback.php';
    $recordingCallbackUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/modules/phone_calls/api/recording-callback.php';

    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Response>';

    // Dial the number
    echo '<Dial';
    echo ' callerId="' . htmlspecialchars($from) . '"';
    echo ' timeout="30"';
    echo ' timeLimit="' . $maxDuration . '"';

    if ($recordingEnabled) {
        echo ' record="record-from-answer-dual"';
        echo ' recordingStatusCallback="' . htmlspecialchars($recordingCallbackUrl) . '"';
        echo ' recordingStatusCallbackMethod="POST"';
        echo ' recordingStatusCallbackEvent="completed"';
    }

    echo '>';
    echo '<Number';
    echo ' statusCallback="' . htmlspecialchars($statusCallbackUrl) . '"';
    echo ' statusCallbackMethod="POST"';
    echo ' statusCallbackEvent="initiated ringing answered completed"';
    echo '>' . htmlspecialchars($to) . '</Number>';
    echo '</Dial>';

    echo '</Response>';

} catch (Exception $e) {
    error_log("Phone Calls Voice Webhook Error: " . $e->getMessage());

    // Return error TwiML
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Response>';
    echo '<Say voice="alice">Sorry, an error occurred. Please try again later.</Say>';
    echo '<Hangup/>';
    echo '</Response>';
}
