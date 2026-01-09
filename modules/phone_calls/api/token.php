<?php
/**
 * Phone Calls Module - Generate Twilio Access Token
 *
 * This endpoint generates a Twilio Access Token for the Voice JavaScript SDK.
 * The token allows browser-based calling through Twilio.
 */

if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check permission
if (!has_permission('phone_calls-make')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

try {
    // Load settings
    require_once '../models/PhoneCallSettings.php';
    $settings = new PhoneCallSettings($db);

    if (!$settings->isConfigured()) {
        throw new Exception('Twilio is not configured. Please contact administrator.');
    }

    // Get credentials
    $accountSid = $settings->getAccountSid();
    $apiKeySid = $settings->getApiKeySid();
    $apiKeySecret = $settings->getApiKeySecret();
    $twimlAppSid = $settings->getTwimlAppSid();

    // Create identity from user ID and name
    $userId = $_SESSION['user_id'];
    $userName = $_SESSION['user_name'] ?? 'User' . $userId;
    $identity = 'user_' . $userId;

    // Generate Access Token manually (without Twilio SDK)
    // Token structure: header.payload.signature (JWT)

    $ttl = 3600; // 1 hour
    $now = time();

    // JWT Header
    $header = [
        'typ' => 'JWT',
        'alg' => 'HS256',
        'cty' => 'twilio-fpa;v=1'
    ];

    // JWT Payload (Claims)
    $payload = [
        'jti' => $apiKeySid . '-' . $now . '-' . mt_rand(1000, 9999),
        'iss' => $apiKeySid,
        'sub' => $accountSid,
        'iat' => $now,
        'nbf' => $now,
        'exp' => $now + $ttl,
        'grants' => [
            'identity' => $identity,
            'voice' => [
                'outgoing' => [
                    'application_sid' => $twimlAppSid
                ],
                'incoming' => [
                    'allow' => true
                ]
            ]
        ]
    ];

    // Encode header and payload
    $base64UrlHeader = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
    $base64UrlPayload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

    // Create signature
    $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, $apiKeySecret, true);
    $base64UrlSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    // Combine to form JWT
    $token = $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;

    // Get ICE servers (TURN credentials) from Twilio NTS for restrictive networks
    $iceServers = [];
    try {
        $authToken = $settings->getAuthToken();
        $ntsUrl = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Tokens.json";

        $ch = curl_init($ntsUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "{$accountSid}:{$authToken}",
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => 'Ttl=3600',
            CURLOPT_TIMEOUT => 10
        ]);

        $ntsResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201 && $ntsResponse) {
            $ntsData = json_decode($ntsResponse, true);
            if (isset($ntsData['ice_servers'])) {
                $iceServers = $ntsData['ice_servers'];
                error_log("Phone Calls: ICE servers obtained from Twilio NTS");
            }
        }
    } catch (Exception $e) {
        error_log("Phone Calls: Failed to get ICE servers: " . $e->getMessage());
    }

    // Log token generation
    error_log("Phone Calls: Token generated for user $userId ($identity)");

    echo json_encode([
        'success' => true,
        'token' => $token,
        'identity' => $identity,
        'expires_in' => $ttl,
        'iceServers' => $iceServers
    ]);

} catch (Exception $e) {
    error_log("Phone Calls Token Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
