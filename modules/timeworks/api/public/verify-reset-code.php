<?php
/**
 * TimeWorks Module - Verify Reset Code (Public API)
 *
 * Verifies the verification code and returns a reset token
 * No authentication required
 *
 * @author ikinciadam@gmail.com
 */

define('AW_SYSTEM', true);
define('PUBLIC_API', true);

// Define root path
$root_path = dirname(dirname(dirname(dirname(__DIR__))));

require_once $root_path . '/config/database.php';

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
 * Generate secure reset token
 */
function generateResetToken() {
    return bin2hex(random_bytes(32)); // 64-char hex string
}

try {
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    $email = strtolower(trim($input['email'] ?? ''));
    $code = strtoupper(trim($input['code'] ?? ''));

    // Validate input
    if (empty($email) || empty($code)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email and code are required'
        ]);
        exit;
    }

    // Validate code format (8 alphanumeric)
    if (!preg_match('/^[A-Z0-9]{8}$/', $code)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid code format'
        ]);
        exit;
    }

    // Find the most recent active code for this email
    $stmt = $db->prepare("
        SELECT id, code, attempts, expires_at, is_used, is_invalidated
        FROM twr_password_reset_codes
        WHERE email = ? AND is_used = 0 AND is_invalidated = 0
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $record = $stmt->fetch();

    if (!$record) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No active verification code found. Please request a new code.'
        ]);
        exit;
    }

    // Check if code has expired
    if (strtotime($record['expires_at']) < time()) {
        // Mark as invalidated
        $stmt = $db->prepare("UPDATE twr_password_reset_codes SET is_invalidated = 1 WHERE id = ?");
        $stmt->execute([$record['id']]);

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Verification code has expired. Please request a new code.',
            'expired' => true
        ]);
        exit;
    }

    // Check attempt limit (3 attempts max)
    if ($record['attempts'] >= 3) {
        // Mark as invalidated
        $stmt = $db->prepare("UPDATE twr_password_reset_codes SET is_invalidated = 1 WHERE id = ?");
        $stmt->execute([$record['id']]);

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Too many failed attempts. Please request a new code.',
            'max_attempts_reached' => true
        ]);
        exit;
    }

    // Verify code
    if ($record['code'] !== $code) {
        // Increment attempts
        $newAttempts = $record['attempts'] + 1;
        $stmt = $db->prepare("UPDATE twr_password_reset_codes SET attempts = ? WHERE id = ?");
        $stmt->execute([$newAttempts, $record['id']]);

        $remainingAttempts = 3 - $newAttempts;

        if ($remainingAttempts <= 0) {
            // Mark as invalidated
            $stmt = $db->prepare("UPDATE twr_password_reset_codes SET is_invalidated = 1 WHERE id = ?");
            $stmt->execute([$record['id']]);

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid code. Too many failed attempts. Please request a new code.',
                'remaining_attempts' => 0,
                'max_attempts_reached' => true
            ]);
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Invalid code. You have {$remainingAttempts} attempt(s) remaining.",
                'remaining_attempts' => $remainingAttempts
            ]);
        }
        exit;
    }

    // Code is correct! Generate reset token
    $resetToken = generateResetToken();

    // Save reset token (valid for 10 minutes)
    $stmt = $db->prepare("UPDATE twr_password_reset_codes SET reset_token = ? WHERE id = ?");
    $stmt->execute([$resetToken, $record['id']]);

    // Log
    error_log("[TimeWorks Password Reset] Code verified for {$email}, Reset token generated");

    echo json_encode([
        'success' => true,
        'message' => 'Code verified successfully',
        'reset_token' => $resetToken,
        'token_expires_in_minutes' => 10
    ]);

} catch (Exception $e) {
    error_log("[TimeWorks Password Reset] Verify error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}
