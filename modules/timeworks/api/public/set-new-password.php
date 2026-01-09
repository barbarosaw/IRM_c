<?php
/**
 * TimeWorks Module - Set New Password (Public API)
 *
 * Sets the new password after verification
 * No authentication required, but requires valid reset_token
 *
 * @author ikinciadam@gmail.com
 */

define('AW_SYSTEM', true);
define('PUBLIC_API', true);

// Define root path
$root_path = dirname(dirname(dirname(dirname(__DIR__))));

require_once $root_path . '/config/database.php';
require_once $root_path . '/includes/EmailHelper.php';
require_once dirname(dirname(__DIR__)) . '/models/TimeWorksAPI.php';

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
 * Validate password requirements
 * - Minimum 8 characters
 * - At least 1 uppercase letter
 * - At least 1 lowercase letter
 * - At least 1 number
 * - At least 1 special character
 */
function validatePassword($password) {
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }

    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:\'",.<>\/?\\\\`~]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }

    return $errors;
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
    $resetToken = trim($input['reset_token'] ?? '');
    $password = $input['password'] ?? '';
    $passwordConfirm = $input['password_confirm'] ?? '';

    // Validate input
    if (empty($resetToken)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Reset token is required'
        ]);
        exit;
    }

    if (empty($password) || empty($passwordConfirm)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Password and confirmation are required'
        ]);
        exit;
    }

    if ($password !== $passwordConfirm) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Passwords do not match'
        ]);
        exit;
    }

    // Validate password requirements
    $passwordErrors = validatePassword($password);
    if (!empty($passwordErrors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Password does not meet requirements',
            'errors' => $passwordErrors
        ]);
        exit;
    }

    // Find the reset token record
    $stmt = $db->prepare("
        SELECT rc.id, rc.email, rc.user_id, rc.created_at, rc.is_used, rc.is_invalidated,
               u.full_name
        FROM twr_password_reset_codes rc
        JOIN twr_users u ON u.user_id = rc.user_id
        WHERE rc.reset_token = ?
        LIMIT 1
    ");
    $stmt->execute([$resetToken]);
    $record = $stmt->fetch();

    if (!$record) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or expired reset token. Please start the process again.'
        ]);
        exit;
    }

    // Check if already used
    if ($record['is_used']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'This reset token has already been used. Please start the process again.'
        ]);
        exit;
    }

    // Check if invalidated
    if ($record['is_invalidated']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'This reset token is no longer valid. Please start the process again.'
        ]);
        exit;
    }

    // Check token expiry (10 minutes from code verification - we check created_at + 40 minutes total)
    $tokenAge = time() - strtotime($record['created_at']);
    if ($tokenAge > 2400) { // 40 minutes (30 min code + 10 min token)
        $stmt = $db->prepare("UPDATE twr_password_reset_codes SET is_invalidated = 1 WHERE id = ?");
        $stmt->execute([$record['id']]);

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Reset token has expired. Please start the process again.'
        ]);
        exit;
    }

    // Update password via TimeWorks API
    $api = new TimeWorksAPI($db);
    $apiResult = $api->updateUserPassword($record['user_id'], $password);

    if ($apiResult === null) {
        throw new Exception('Failed to update password via TimeWorks API');
    }

    // Mark the code as used
    $stmt = $db->prepare("UPDATE twr_password_reset_codes SET is_used = 1 WHERE id = ?");
    $stmt->execute([$record['id']]);

    // Send confirmation email
    $emailHelper = new EmailHelper($db);

    $variables = [
        'name' => $record['full_name'],
        'email' => $record['email'],
        'site_name' => getSetting($db, 'site_name', 'AbroadWorks IRM'),
        'date' => date('Y-m-d'),
        'time' => date('H:i:s')
    ];

    $emailSent = $emailHelper->sendTemplate($record['email'], 'timeworks_password_changed', $variables);

    if (!$emailSent) {
        // Fallback: send basic email
        $subject = 'Your TimeWorks Password Has Been Changed';
        $body = $emailHelper->wrapInTemplate("
            <p>Hello <strong>{$record['full_name']}</strong>,</p>
            <p>Your TimeWorks password has been successfully changed on " . date('Y-m-d') . " at " . date('H:i:s') . ".</p>
            <div style='background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; padding: 15px; margin: 20px 0;'>
                <p style='margin: 0; color: #721c24;'>
                    <strong>⚠️ Did not make this change?</strong><br>
                    If you did not change your password, please contact IT support immediately.
                </p>
            </div>
            <p>You can now log in to TimeWorks with your new password.</p>
        ", 'Password Changed');

        $emailSent = $emailHelper->send($record['email'], $subject, $body);
    }

    // Log activity
    error_log("[TimeWorks Password Reset] Password changed successfully for {$record['email']} (user_id: {$record['user_id']})");

    echo json_encode([
        'success' => true,
        'message' => 'Your password has been changed successfully. You can now log in with your new password.',
        'email_sent' => $emailSent
    ]);

} catch (Exception $e) {
    error_log("[TimeWorks Password Reset] Set password error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while changing your password. Please try again later.'
    ]);
}
