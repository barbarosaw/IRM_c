<?php
/**
 * TimeWorks Module - Send Password Reset Code (Public API)
 *
 * Sends a verification code to user's email for password reset
 * No authentication required
 *
 * @author ikinciadam@gmail.com
 */

// Allow this to run without session authentication
define('AW_SYSTEM', true);
define('PUBLIC_API', true);

// Define root path
$root_path = dirname(dirname(dirname(dirname(__DIR__))));

require_once $root_path . '/config/database.php';
require_once $root_path . '/includes/EmailHelper.php';
require_once $root_path . '/includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

/**
 * Generate 8-character alphanumeric code
 */
function generateVerificationCode() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed confusing chars (0, O, 1, I)
    $code = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Get setting value
 */
function getSetting($db, $key, $default = null) {
    try {
        $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    } catch (Exception $e) {
        return $default;
    }
}

try {
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');

    // Validate email format
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Return generic success to prevent email enumeration
        echo json_encode([
            'success' => true,
            'message' => 'If this email exists in our system, you will receive a verification code shortly.'
        ]);
        exit;
    }

    $email = strtolower($email);

    // Check rate limiting (2 minutes between requests)
    $stmt = $db->prepare("
        SELECT created_at FROM twr_password_reset_codes
        WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$email]);
    $recentCode = $stmt->fetch();

    if ($recentCode) {
        $waitTime = 120 - (time() - strtotime($recentCode['created_at']));
        echo json_encode([
            'success' => false,
            'message' => "Please wait {$waitTime} seconds before requesting a new code.",
            'wait_seconds' => $waitTime
        ]);
        exit;
    }

    // Find user in twr_users
    $stmt = $db->prepare("SELECT user_id, full_name, email FROM twr_users WHERE LOWER(email) = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // Return generic success to prevent email enumeration
        echo json_encode([
            'success' => true,
            'message' => 'If this email exists in our system, you will receive a verification code shortly.'
        ]);
        exit;
    }

    // Invalidate any existing active codes for this email
    $stmt = $db->prepare("
        UPDATE twr_password_reset_codes
        SET is_invalidated = 1
        WHERE email = ? AND is_used = 0 AND is_invalidated = 0
    ");
    $stmt->execute([$email]);

    // Generate new verification code
    $code = generateVerificationCode();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

    // Save to database
    $stmt = $db->prepare("
        INSERT INTO twr_password_reset_codes (email, user_id, code, expires_at, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$email, $user['user_id'], $code, $expiresAt]);

    // Send email
    $emailHelper = new EmailHelper($db);

    $variables = [
        'name' => $user['full_name'],
        'email' => $user['email'],
        'code' => $code,
        'site_name' => getSetting($db, 'site_name', 'AbroadWorks IRM'),
        'date' => date('Y-m-d'),
        'time' => date('H:i:s')
    ];

    $emailSent = $emailHelper->sendTemplate($user['email'], 'timeworks_verification_code', $variables);

    if (!$emailSent) {
        // Fallback: send basic email
        $subject = 'TimeWorks Password Reset Code: ' . $code;
        $body = $emailHelper->wrapInTemplate("
            <p>Hello <strong>{$user['full_name']}</strong>,</p>
            <p>Your verification code is:</p>
            <div style='text-align: center; margin: 20px 0;'>
                <span style='font-size: 32px; font-weight: bold; letter-spacing: 8px; background: #f0f0f0; padding: 15px 30px; border-radius: 8px; font-family: monospace;'>{$code}</span>
            </div>
            <p><strong>This code expires in 30 minutes.</strong></p>
            <p>If you did not request this, please ignore this email.</p>
        ", 'Password Reset');

        $emailSent = $emailHelper->send($user['email'], $subject, $body);
    }

    // Log activity
    error_log("[TimeWorks Password Reset] Code sent to {$email}, Code: {$code}, Expires: {$expiresAt}");

    echo json_encode([
        'success' => true,
        'message' => 'If this email exists in our system, you will receive a verification code shortly.',
        'expires_in_minutes' => 30
    ]);

} catch (Exception $e) {
    error_log("[TimeWorks Password Reset] Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}
