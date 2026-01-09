<?php
/**
 * TimeWorks Module - Send Email API
 *
 * Handles single and bulk email sending for activity reports
 *
 * @author ikinciadam@gmail.com
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once dirname(__DIR__, 3) . '/includes/init.php';
require_once dirname(__DIR__, 3) . '/includes/EmailHelper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_templates':
        getTemplates();
        break;
    case 'preview_email':
        previewEmail();
        break;
    case 'send_single':
        sendSingleEmail();
        break;
    case 'send_bulk_chunk':
        sendBulkChunk();
        break;
    case 'get_recipients_count':
        getRecipientsCount();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get active email templates
 */
function getTemplates() {
    global $db;

    try {
        $stmt = $db->query("SELECT id, code, name, subject, placeholders, description FROM email_templates WHERE is_active = 1 ORDER BY name ASC");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'templates' => $templates]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Preview email with user data
 */
function previewEmail() {
    global $db;

    $templateId = $_POST['template_id'] ?? 0;
    $userId = $_POST['user_id'] ?? '';

    if (!$templateId) {
        echo json_encode(['success' => false, 'message' => 'Template ID required']);
        return;
    }

    try {
        // Get template
        $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            echo json_encode(['success' => false, 'message' => 'Template not found']);
            return;
        }

        // Get user data if provided
        $userData = [];
        if ($userId) {
            $stmt = $db->prepare("SELECT * FROM twr_users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Get settings for placeholders
        $settings = [];
        $stmt = $db->query("SELECT `key`, `value` FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        // Build placeholder values
        $placeholders = buildPlaceholders($userData, $settings);

        // Replace placeholders in subject and body
        $subject = replacePlaceholders($template['subject'], $placeholders);
        $body = replacePlaceholders($template['body'], $placeholders);

        echo json_encode([
            'success' => true,
            'subject' => $subject,
            'body' => $body,
            'template_name' => $template['name']
        ]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Send email to a single user
 */
function sendSingleEmail() {
    global $db;

    $templateId = $_POST['template_id'] ?? 0;
    $userId = $_POST['user_id'] ?? '';

    if (!$templateId || !$userId) {
        echo json_encode(['success' => false, 'message' => 'Template ID and User ID required']);
        return;
    }

    try {
        // Get template
        $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ? AND is_active = 1");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            echo json_encode(['success' => false, 'message' => 'Template not found or inactive']);
            return;
        }

        // Get user data
        $stmt = $db->prepare("SELECT * FROM twr_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }

        if (empty($user['email'])) {
            echo json_encode(['success' => false, 'message' => 'User has no email address']);
            return;
        }

        // Get settings for placeholders
        $settings = [];
        $stmt = $db->query("SELECT `key`, `value` FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        // Build placeholder values
        $placeholders = buildPlaceholders($user, $settings);

        // Replace placeholders in subject and body
        $subject = replacePlaceholders($template['subject'], $placeholders);
        $body = replacePlaceholders($template['body'], $placeholders);

        // Send email
        $emailHelper = new EmailHelper($db);

        if (!$emailHelper->isEnabled()) {
            echo json_encode(['success' => false, 'message' => 'Email sending is disabled. Enable it in Settings > Email.']);
            return;
        }

        $sent = $emailHelper->send($user['email'], $subject, $emailHelper->wrapInTemplate($body));

        if ($sent) {
            // Log activity
            if (function_exists('log_activity')) {
                log_activity($_SESSION['user_id'], 'email_sent', 'twr_users', "Email sent to: {$user['email']} (Template: {$template['name']})");
            }

            echo json_encode([
                'success' => true,
                'message' => "Email sent successfully to {$user['email']}"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send email: ' . $emailHelper->getLastError()
            ]);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get count of recipients for bulk send
 */
function getRecipientsCount() {
    global $db;

    $userGroup = $_POST['user_group'] ?? 'without_activity';

    try {
        if ($userGroup === 'without_activity') {
            $stmt = $db->query("
                SELECT COUNT(*) FROM twr_users
                WHERE status = 'active'
                  AND email IS NOT NULL AND email != ''
                  AND (activity_checked_at IS NOT NULL AND activity_days = 0 AND activity_hours = 0)
            ");
        } else {
            $stmt = $db->query("
                SELECT COUNT(*) FROM twr_users
                WHERE status = 'active'
                  AND email IS NOT NULL AND email != ''
                  AND (activity_days > 0 OR activity_hours > 0)
            ");
        }

        $count = $stmt->fetchColumn();

        echo json_encode(['success' => true, 'count' => (int)$count]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Send emails in bulk (chunk by chunk)
 */
function sendBulkChunk() {
    global $db;

    $templateId = $_POST['template_id'] ?? 0;
    $userGroup = $_POST['user_group'] ?? 'without_activity';
    $offset = (int)($_POST['offset'] ?? 0);
    $limit = 10; // Send 10 at a time

    if (!$templateId) {
        echo json_encode(['success' => false, 'message' => 'Template ID required']);
        return;
    }

    try {
        // Get template
        $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ? AND is_active = 1");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            echo json_encode(['success' => false, 'message' => 'Template not found or inactive']);
            return;
        }

        // Get settings for placeholders
        $settings = [];
        $stmt = $db->query("SELECT `key`, `value` FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        // Get rate limit
        $rateLimit = (int)($settings['email_rate_limit'] ?? 5);

        // Get users based on group
        if ($userGroup === 'without_activity') {
            $stmt = $db->prepare("
                SELECT * FROM twr_users
                WHERE status = 'active'
                  AND email IS NOT NULL AND email != ''
                  AND (activity_checked_at IS NOT NULL AND activity_days = 0 AND activity_hours = 0)
                ORDER BY full_name ASC
                LIMIT ? OFFSET ?
            ");
        } else {
            $stmt = $db->prepare("
                SELECT * FROM twr_users
                WHERE status = 'active'
                  AND email IS NOT NULL AND email != ''
                  AND (activity_days > 0 OR activity_hours > 0)
                ORDER BY full_name ASC
                LIMIT ? OFFSET ?
            ");
        }
        $stmt->execute([$limit, $offset]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($users)) {
            echo json_encode([
                'success' => true,
                'done' => true,
                'sent' => 0,
                'failed' => 0,
                'results' => []
            ]);
            return;
        }

        $emailHelper = new EmailHelper($db);

        if (!$emailHelper->isEnabled()) {
            echo json_encode(['success' => false, 'message' => 'Email sending is disabled']);
            return;
        }

        $sent = 0;
        $failed = 0;
        $results = [];

        foreach ($users as $index => $user) {
            // Build placeholder values for this user
            $placeholders = buildPlaceholders($user, $settings);

            // Replace placeholders
            $subject = replacePlaceholders($template['subject'], $placeholders);
            $body = replacePlaceholders($template['body'], $placeholders);

            // Send email
            $success = $emailHelper->send($user['email'], $subject, $emailHelper->wrapInTemplate($body));

            if ($success) {
                $sent++;
                $results[] = [
                    'email' => $user['email'],
                    'name' => $user['full_name'],
                    'status' => 'sent'
                ];
            } else {
                $failed++;
                $results[] = [
                    'email' => $user['email'],
                    'name' => $user['full_name'],
                    'status' => 'failed',
                    'error' => $emailHelper->getLastError()
                ];
            }

            // Rate limiting (except for last email in chunk)
            if ($index < count($users) - 1 && $rateLimit > 0) {
                sleep($rateLimit);
            }
        }

        // Log activity
        if (function_exists('log_activity')) {
            log_activity($_SESSION['user_id'], 'bulk_email', 'twr_users', "Bulk email: {$sent} sent, {$failed} failed (Template: {$template['name']})");
        }

        echo json_encode([
            'success' => true,
            'done' => false,
            'sent' => $sent,
            'failed' => $failed,
            'results' => $results,
            'next_offset' => $offset + $limit
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Build placeholder values from user data and settings
 */
function buildPlaceholders($user, $settings) {
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    $placeholders = [
        // User placeholders
        'name' => $user['full_name'] ?? 'User',
        'first_name' => explode(' ', $user['full_name'] ?? 'User')[0],
        'last_name' => count(explode(' ', $user['full_name'] ?? '')) > 1 ? end(explode(' ', $user['full_name'])) : '',
        'email' => $user['email'] ?? '',
        'username' => $user['email'] ?? '',
        'roles' => $user['roles'] ?? '',

        // Activity placeholders
        'last_login' => isset($user['last_login_local']) && $user['last_login_local'] ? date('M j, Y', strtotime($user['last_login_local'])) : 'Never',
        'last_login_date' => isset($user['last_login_local']) && $user['last_login_local'] ? date('M j, Y', strtotime($user['last_login_local'])) : 'Never',
        'last_activity' => isset($user['last_activity_date']) && $user['last_activity_date'] ? date('M j, Y', strtotime($user['last_activity_date'])) : 'No activity',
        'last_activity_date' => isset($user['last_activity_date']) && $user['last_activity_date'] ? date('M j, Y', strtotime($user['last_activity_date'])) : 'No activity',
        'activity_days' => $user['activity_days'] ?? '0',
        'activity_hours' => isset($user['activity_hours']) ? round($user['activity_hours'], 1) : '0',
        'days_inactive' => isset($user['last_activity_date']) && $user['last_activity_date']
            ? (int)((time() - strtotime($user['last_activity_date'])) / 86400)
            : 'N/A',

        // System placeholders
        'site_name' => $settings['site_name'] ?? 'AbroadWorks IRM',
        'site_url' => $baseUrl,
        'company_name' => $settings['company_name'] ?? 'AbroadWorks',
        'company_email' => $settings['company_email'] ?? '',
        'support_email' => $settings['smtp_reply_to'] ?? $settings['company_email'] ?? '',
        'login_url' => $baseUrl . '/login.php',
        'timeworks_url' => $baseUrl . '/modules/timeworks/',

        // Date/Time placeholders
        'date' => date('Y-m-d'),
        'time' => date('H:i:s'),
        'year' => date('Y'),
        'today' => date('M j, Y'),
    ];

    return $placeholders;
}

/**
 * Replace placeholders in text
 */
function replacePlaceholders($text, $placeholders) {
    foreach ($placeholders as $key => $value) {
        $text = str_replace('{{' . $key . '}}', $value, $text);
    }
    return $text;
}
