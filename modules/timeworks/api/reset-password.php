<?php
/**
 * TimeWorks Module - Reset Password API
 *
 * Resets user password, pushes to pwpush.com, and sends email notification
 *
 * @author ikinciadam@gmail.com
 */

define('AW_SYSTEM', true);
require_once '../../../includes/init.php';
require_once '../models/TimeWorksAPI.php';
require_once '../../../includes/EmailHelper.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Permission check
if (!has_permission('timeworks_users_manage')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

/**
 * Generate a complex 12-character password
 * First and last characters are alphanumeric only
 * Middle characters can include special characters
 *
 * @return string
 */
function generateComplexPassword() {
    $alphanumeric = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $allChars = $alphanumeric . '!@#$%^&*()_+-=[]{}|;:,.<>?';

    $password = '';

    // First character - alphanumeric only
    $password .= $alphanumeric[random_int(0, strlen($alphanumeric) - 1)];

    // Middle 10 characters - can include special chars
    for ($i = 0; $i < 10; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }

    // Last character - alphanumeric only
    $password .= $alphanumeric[random_int(0, strlen($alphanumeric) - 1)];

    return $password;
}

/**
 * Push password to pwpush.com
 *
 * @param string $password The password to push
 * @param int $expireDays Days until expiry
 * @param int $expireViews Number of views until expiry
 * @param string $apiToken Optional API token for authentication
 * @param string $userEmail Optional user email for authentication
 * @param string $baseUrl Optional base URL (default: https://pwpush.com)
 * @return array|null
 */
function pushToPwPush($password, $expireDays = 7, $expireViews = 5, $apiToken = '', $userEmail = '', $baseUrl = 'https://pwpush.com') {
    $url = rtrim($baseUrl, '/') . '/p.json';

    $data = [
        'password' => [
            'payload' => $password,
            'expire_after_days' => (int)$expireDays,
            'expire_after_views' => (int)$expireViews,
            'deletable_by_viewer' => false
        ]
    ];

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    // Add API authentication headers if provided
    if (!empty($apiToken)) {
        $headers[] = 'X-User-Token: ' . $apiToken;
    }
    if (!empty($userEmail)) {
        $headers[] = 'X-User-Email: ' . $userEmail;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("PWPush: cURL error - " . $curlError);
        return null;
    }

    if ($httpCode !== 201 && $httpCode !== 200) {
        error_log("PWPush: HTTP {$httpCode} error - Response: " . $response);
        return null;
    }

    $result = json_decode($response, true);

    // Log full response for debugging
    error_log("[PWPush Debug] Raw API response: " . $response);

    if (isset($result['url_token'])) {
        // Use html_url from response if available, otherwise build URL
        $pwpushUrl = $result['html_url'] ?? (rtrim($baseUrl, '/') . '/p/' . $result['url_token']);

        return [
            'url' => $pwpushUrl,
            'token' => $result['url_token'],
            'expire_after_days' => $result['expire_after_days'] ?? $expireDays,
            'expire_after_views' => $result['expire_after_views'] ?? $expireViews
        ];
    }

    error_log("PWPush: No url_token in response - " . $response);
    return null;
}

/**
 * Get setting value from database
 *
 * @param PDO $db
 * @param string $key
 * @param mixed $default
 * @return mixed
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
    $userId = $_POST['user_id'] ?? null;

    if (!$userId) {
        throw new Exception('User ID is required');
    }

    // Get user details
    $stmt = $db->prepare("SELECT id, user_id, full_name, email FROM twr_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('User not found');
    }

    if (empty($user['email'])) {
        throw new Exception('User does not have an email address');
    }

    // Get pwpush settings from database
    $pwpushDays = (int)getSetting($db, 'pwpush_expire_days', 7);
    $pwpushViews = (int)getSetting($db, 'pwpush_expire_views', 5);
    $pwpushApiToken = getSetting($db, 'pwpush_api_token', '');
    $pwpushUserEmail = getSetting($db, 'pwpush_user_email', '');
    $pwpushBaseUrl = getSetting($db, 'pwpush_base_url', 'https://pwpush.com');

    // Log settings for debugging
    error_log("[PWPush Debug] Settings from DB - Days: {$pwpushDays}, Views: {$pwpushViews}, BaseURL: {$pwpushBaseUrl}, Email: {$pwpushUserEmail}");

    // Generate new password
    $newPassword = generateComplexPassword();

    // Push to pwpush.com
    $pwpushResult = pushToPwPush($newPassword, $pwpushDays, $pwpushViews, $pwpushApiToken, $pwpushUserEmail, $pwpushBaseUrl);
    if (!$pwpushResult) {
        throw new Exception('Failed to push password to PWPush service');
    }

    // Log PWPush response with full details
    error_log("[PWPush Debug] Response - Days: {$pwpushResult['expire_after_days']}, Views: {$pwpushResult['expire_after_views']}, URL: {$pwpushResult['url']}, Token: {$pwpushResult['token']}");

    // Update password via TimeWorks API
    $api = new TimeWorksAPI($db);
    $apiResult = $api->updateUserPassword($userId, $newPassword);

    if ($apiResult === null) {
        throw new Exception('Failed to update password via TimeWorks API');
    }

    // Send email notification
    $emailHelper = new EmailHelper($db);

    // Prepare email variables
    $variables = [
        'name' => $user['full_name'],
        'email' => $user['email'],
        'pwpush_url' => $pwpushResult['url'],
        'expire_days' => $pwpushResult['expire_after_days'],
        'expire_views' => $pwpushResult['expire_after_views'],
        'date' => date('Y-m-d'),
        'time' => date('H:i:s'),
        'site_name' => getSetting($db, 'site_name', 'AbroadWorks IRM'),
        'site_url' => getSetting($db, 'site_url', 'https://irm.abroadworks.com')
    ];

    // Try to send using template
    $emailSent = $emailHelper->sendTemplate($user['email'], 'password_reset', $variables);

    // If template doesn't exist or failed, send a basic email
    if (!$emailSent) {
        $subject = 'Your TimeWorks Password Has Been Reset';
        $body = $emailHelper->wrapInTemplate("
            <p>Hello <strong>{$user['full_name']}</strong>,</p>
            <p>Your TimeWorks password has been reset by an administrator.</p>
            <p>Please click the link below to view your new password:</p>
            <p><a href=\"{$pwpushResult['url']}\" class=\"btn\" style=\"display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;\">View Password</a></p>
            <p><strong>Important:</strong></p>
            <ul>
                <li>This link will expire after <strong>{$pwpushResult['expire_after_views']}</strong> views or <strong>{$pwpushResult['expire_after_days']}</strong> days</li>
                <li>Please change your password after logging in</li>
            </ul>
            <p>If you did not request this password reset, please contact your administrator immediately.</p>
        ", 'Password Reset');

        $emailSent = $emailHelper->send($user['email'], $subject, $body);
    }

    // Log activity
    log_activity(
        $_SESSION['user_id'],
        'password_reset',
        'timeworks_user',
        "Reset password for {$user['full_name']} ({$user['email']})"
    );

    $message = "Password reset successful for <strong>{$user['full_name']}</strong>";
    if ($emailSent) {
        $message .= "<br><small class=\"text-muted\">Email sent to {$user['email']}</small>";
    } else {
        $message .= "<br><small class=\"text-warning\">Warning: Email could not be sent</small>";
        $message .= "<br><small>PWPush URL: <a href=\"{$pwpushResult['url']}\" target=\"_blank\">{$pwpushResult['url']}</a></small>";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'pwpush_url' => $pwpushResult['url'],
        'email_sent' => $emailSent
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
