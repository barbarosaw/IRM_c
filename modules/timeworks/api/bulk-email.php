<?php
/**
 * TimeWorks Module - Bulk Email API
 *
 * Handles bulk email campaigns with tracking functionality
 *
 * @author ikinciadam@gmail.com
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

require_once dirname(__DIR__, 3) . '/includes/init.php';
require_once dirname(__DIR__, 3) . '/includes/EmailHelper.php';
require_once dirname(__DIR__) . '/helpers/EmailTrackingHelper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check permission
if (!has_permission('timeworks_email_manage')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_templates':
        getTemplates();
        break;
    case 'get_template':
        getTemplate();
        break;
    case 'get_recipients':
        getRecipients();
        break;
    case 'preview_email':
        previewEmail();
        break;
    case 'create_campaign':
        createCampaign();
        break;
    case 'start_campaign':
        startCampaign();
        break;
    case 'send_chunk':
        sendChunk();
        break;
    case 'pause_campaign':
        pauseCampaign();
        break;
    case 'resume_campaign':
        resumeCampaign();
        break;
    case 'cancel_campaign':
        cancelCampaign();
        break;
    case 'get_campaign_status':
        getCampaignStatus();
        break;
    case 'get_campaigns':
        getCampaigns();
        break;
    case 'get_campaign':
        getCampaign();
        break;
    case 'get_campaign_recipients':
        getCampaignRecipients();
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
        $stmt = $db->query("
            SELECT id, code, name, subject, placeholders, description
            FROM email_templates
            WHERE is_active = 1
            ORDER BY name ASC
        ");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'templates' => $templates]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get single template with body
 */
function getTemplate() {
    global $db;

    $templateId = (int)($_POST['template_id'] ?? $_GET['template_id'] ?? 0);

    if (!$templateId) {
        echo json_encode(['success' => false, 'message' => 'Template ID required']);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            echo json_encode(['success' => false, 'message' => 'Template not found']);
            return;
        }

        echo json_encode(['success' => true, 'template' => $template]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get recipients based on filter
 */
function getRecipients() {
    global $db;

    $filter = $_POST['filter'] ?? 'all';
    $countOnly = isset($_POST['count_only']);

    try {
        // Check if filter is a group
        if (strpos($filter, 'group:') === 0) {
            $groupId = (int)substr($filter, 6);

            if ($countOnly) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM email_group_members WHERE group_id = ?");
                $stmt->execute([$groupId]);
                $count = (int)$stmt->fetchColumn();
                echo json_encode(['success' => true, 'count' => $count]);
            } else {
                $stmt = $db->prepare("
                    SELECT m.user_id, m.name as full_name, m.email, 'active' as status, '' as roles
                    FROM email_group_members m
                    WHERE m.group_id = ?
                    ORDER BY m.name ASC
                ");
                $stmt->execute([$groupId]);
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'recipients' => $recipients, 'count' => count($recipients)]);
            }
            return;
        }

        // Standard user filter
        $where = "email IS NOT NULL AND email != ''";

        switch ($filter) {
            case 'active':
                $where .= " AND status = 'active'";
                break;
            case 'inactive':
                $where .= " AND status = 'inactive'";
                break;
            case 'without_activity':
                $where .= " AND status = 'active' AND (activity_checked_at IS NOT NULL AND activity_days = 0 AND activity_hours = 0)";
                break;
            case 'with_activity':
                $where .= " AND status = 'active' AND (activity_days > 0 OR activity_hours > 0)";
                break;
            case 'all':
            default:
                // No additional filter
                break;
        }

        if ($countOnly) {
            $stmt = $db->query("SELECT COUNT(*) FROM twr_users WHERE {$where}");
            $count = (int)$stmt->fetchColumn();
            echo json_encode(['success' => true, 'count' => $count]);
        } else {
            $stmt = $db->query("
                SELECT user_id, full_name, email, status, roles
                FROM twr_users
                WHERE {$where}
                ORDER BY full_name ASC
            ");
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'recipients' => $recipients, 'count' => count($recipients)]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Preview email with sample data
 */
function previewEmail() {
    global $db;

    $templateId = (int)($_POST['template_id'] ?? 0);
    $customSubject = $_POST['custom_subject'] ?? '';
    $customBody = $_POST['custom_body'] ?? '';

    if (!$templateId && empty($customBody)) {
        echo json_encode(['success' => false, 'message' => 'Template ID or custom body required']);
        return;
    }

    try {
        $subject = $customSubject;
        $body = $customBody;

        if ($templateId) {
            $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                echo json_encode(['success' => false, 'message' => 'Template not found']);
                return;
            }

            $subject = $customSubject ?: $template['subject'];
            $body = $customBody ?: $template['body'];
        }

        // Get settings
        $settings = [];
        $stmt = $db->query("SELECT `key`, `value` FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        // Sample user data for preview
        $sampleUser = [
            'full_name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'roles' => 'Employee',
            'last_login_local' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'last_activity_date' => date('Y-m-d', strtotime('-1 day')),
            'activity_days' => 5,
            'activity_hours' => 7.5
        ];

        $placeholders = buildPlaceholders($sampleUser, $settings);

        // Add extra placeholders for announcements
        $placeholders['announcement_title'] = 'Sample Announcement Title';
        $placeholders['announcement_message'] = 'This is a sample announcement message for preview purposes.';
        $placeholders['announcement_points'] = '<li>Point 1: Sample information</li><li>Point 2: Another sample point</li><li>Point 3: Final sample point</li>';
        $placeholders['action_required'] = 'Please review this information and take necessary action.';
        $placeholders['effective_date'] = date('F j, Y', strtotime('+1 week'));
        $placeholders['deadline'] = date('F j, Y', strtotime('+2 weeks'));
        $placeholders['pwpush_url'] = 'https://pwpush.com/p/sample-token-xyz';
        $placeholders['expire_days'] = '7';
        $placeholders['expire_views'] = '5';

        $previewSubject = replacePlaceholders($subject, $placeholders);
        $previewBody = replacePlaceholders($body, $placeholders);

        // Wrap in email template
        $emailHelper = new EmailHelper($db);
        $wrappedBody = $emailHelper->wrapInTemplate($previewBody);

        echo json_encode([
            'success' => true,
            'subject' => $previewSubject,
            'body' => $wrappedBody,
            'raw_body' => $previewBody
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Create a new email campaign
 */
function createCampaign() {
    global $db;

    $templateId = (int)($_POST['template_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = $_POST['body'] ?? '';
    $recipientFilter = $_POST['recipient_filter'] ?? 'all';
    $delaySeconds = (int)($_POST['delay_seconds'] ?? 5);
    $recipientIds = $_POST['recipient_ids'] ?? []; // For custom selection

    if (empty($subject) || empty($body)) {
        echo json_encode(['success' => false, 'message' => 'Subject and body are required']);
        return;
    }

    if ($delaySeconds < 0) {
        $delaySeconds = 0;
    }
    if ($delaySeconds > 60) {
        $delaySeconds = 60;
    }

    try {
        $db->beginTransaction();

        // Generate campaign name if not provided
        if (empty($name)) {
            $name = 'Campaign ' . date('Y-m-d H:i:s');
        }

        // Get recipients based on filter or custom selection
        if (!empty($recipientIds) && is_array($recipientIds)) {
            // Custom selection
            $placeholders = implode(',', array_fill(0, count($recipientIds), '?'));
            $stmt = $db->prepare("
                SELECT user_id, full_name, email
                FROM twr_users
                WHERE user_id IN ({$placeholders})
                AND email IS NOT NULL AND email != ''
            ");
            $stmt->execute($recipientIds);
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif (strpos($recipientFilter, 'group:') === 0) {
            // Email group selection
            $groupId = (int)substr($recipientFilter, 6);
            $stmt = $db->prepare("
                SELECT m.user_id, m.name as full_name, m.email
                FROM email_group_members m
                WHERE m.group_id = ?
                ORDER BY m.name ASC
            ");
            $stmt->execute([$groupId]);
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Filter-based selection
            $where = "email IS NOT NULL AND email != ''";
            switch ($recipientFilter) {
                case 'active':
                    $where .= " AND status = 'active'";
                    break;
                case 'inactive':
                    $where .= " AND status = 'inactive'";
                    break;
                case 'without_activity':
                    $where .= " AND status = 'active' AND (activity_checked_at IS NOT NULL AND activity_days = 0 AND activity_hours = 0)";
                    break;
                case 'with_activity':
                    $where .= " AND status = 'active' AND (activity_days > 0 OR activity_hours > 0)";
                    break;
            }
            $stmt = $db->query("SELECT user_id, full_name, email FROM twr_users WHERE {$where} ORDER BY full_name ASC");
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if (empty($recipients)) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'No recipients found']);
            return;
        }

        // Create campaign
        $stmt = $db->prepare("
            INSERT INTO email_campaigns (
                template_id, name, subject, body, recipient_filter,
                delay_seconds, total_recipients, status, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?, NOW())
        ");
        $stmt->execute([
            $templateId ?: null,
            $name,
            $subject,
            $body,
            $recipientFilter,
            $delaySeconds,
            count($recipients),
            $_SESSION['user_id']
        ]);

        $campaignId = $db->lastInsertId();

        // Create tracking helper
        $trackingHelper = new EmailTrackingHelper($db);

        // Create send records for each recipient
        $stmt = $db->prepare("
            INSERT INTO email_sends (campaign_id, user_id, email, recipient_name, token, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");

        foreach ($recipients as $recipient) {
            $token = $trackingHelper->generateToken();
            $stmt->execute([
                $campaignId,
                $recipient['user_id'],
                $recipient['email'],
                $recipient['full_name'],
                $token
            ]);
        }

        $db->commit();

        // Log activity
        if (function_exists('log_activity')) {
            log_activity($_SESSION['user_id'], 'create', 'email_campaigns', "Created campaign: {$name} with {$count} recipients");
        }

        echo json_encode([
            'success' => true,
            'message' => 'Campaign created successfully',
            'campaign_id' => $campaignId,
            'recipient_count' => count($recipients)
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Start sending a campaign
 */
function startCampaign() {
    global $db;

    $campaignId = (int)($_POST['campaign_id'] ?? 0);

    if (!$campaignId) {
        echo json_encode(['success' => false, 'message' => 'Campaign ID required']);
        return;
    }

    try {
        // Get campaign
        $stmt = $db->prepare("SELECT * FROM email_campaigns WHERE id = ?");
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            echo json_encode(['success' => false, 'message' => 'Campaign not found']);
            return;
        }

        if (!in_array($campaign['status'], ['draft', 'paused'])) {
            echo json_encode(['success' => false, 'message' => 'Campaign cannot be started (status: ' . $campaign['status'] . ')']);
            return;
        }

        // Update campaign status
        $stmt = $db->prepare("UPDATE email_campaigns SET status = 'sending', started_at = COALESCE(started_at, NOW()) WHERE id = ?");
        $stmt->execute([$campaignId]);

        echo json_encode([
            'success' => true,
            'message' => 'Campaign started',
            'campaign_id' => $campaignId
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Send a chunk of emails
 */
function sendChunk() {
    global $db;

    $campaignId = (int)($_POST['campaign_id'] ?? 0);
    $limit = 10; // Process 10 at a time

    if (!$campaignId) {
        echo json_encode(['success' => false, 'message' => 'Campaign ID required']);
        return;
    }

    try {
        // Get campaign
        $stmt = $db->prepare("SELECT * FROM email_campaigns WHERE id = ?");
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            echo json_encode(['success' => false, 'message' => 'Campaign not found']);
            return;
        }

        if ($campaign['status'] !== 'sending') {
            echo json_encode([
                'success' => true,
                'done' => true,
                'paused' => $campaign['status'] === 'paused',
                'cancelled' => $campaign['status'] === 'cancelled',
                'message' => 'Campaign is not in sending status'
            ]);
            return;
        }

        // Get pending sends
        $stmt = $db->prepare("
            SELECT es.*, u.full_name, u.last_login_local, u.last_activity_date,
                   u.activity_days, u.activity_hours, u.roles, u.status as user_status
            FROM email_sends es
            LEFT JOIN twr_users u ON es.user_id = u.user_id
            WHERE es.campaign_id = ? AND es.status = 'pending'
            ORDER BY es.id ASC
            LIMIT ?
        ");
        $stmt->execute([$campaignId, $limit]);
        $pendingSends = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pendingSends)) {
            // Mark campaign as completed
            $stmt = $db->prepare("UPDATE email_campaigns SET status = 'completed', completed_at = NOW() WHERE id = ?");
            $stmt->execute([$campaignId]);

            echo json_encode([
                'success' => true,
                'done' => true,
                'message' => 'Campaign completed'
            ]);
            return;
        }

        // Get settings for placeholders
        $settings = [];
        $stmt = $db->query("SELECT `key`, `value` FROM settings");
        while ($row = $stmt->fetch()) {
            $settings[$row['key']] = $row['value'];
        }

        // Create helpers
        $emailHelper = new EmailHelper($db);
        $trackingHelper = new EmailTrackingHelper($db);

        if (!$emailHelper->isEnabled()) {
            echo json_encode(['success' => false, 'message' => 'Email sending is disabled']);
            return;
        }

        $sent = 0;
        $failed = 0;
        $results = [];

        foreach ($pendingSends as $index => $send) {
            // Build user data for placeholders
            $userData = [
                'full_name' => $send['recipient_name'] ?? $send['full_name'] ?? 'User',
                'email' => $send['email'],
                'roles' => $send['roles'] ?? '',
                'last_login_local' => $send['last_login_local'],
                'last_activity_date' => $send['last_activity_date'],
                'activity_days' => $send['activity_days'] ?? 0,
                'activity_hours' => $send['activity_hours'] ?? 0
            ];

            $placeholders = buildPlaceholders($userData, $settings);

            // Add extra placeholders
            $placeholders['announcement_title'] = 'Important Update';
            $placeholders['announcement_message'] = '';
            $placeholders['announcement_points'] = '';
            $placeholders['action_required'] = '';
            $placeholders['effective_date'] = date('F j, Y');
            $placeholders['deadline'] = '';
            $placeholders['pwpush_url'] = '';
            $placeholders['expire_days'] = '7';
            $placeholders['expire_views'] = '5';

            // Replace placeholders
            $subject = replacePlaceholders($campaign['subject'], $placeholders);
            $body = replacePlaceholders($campaign['body'], $placeholders);

            // Add tracking
            $trackedBody = $trackingHelper->prepareEmailForTracking($body, $send['token']);

            // Wrap in template
            $wrappedBody = $emailHelper->wrapInTemplate($trackedBody);

            // Send email
            $success = $emailHelper->send($send['email'], $subject, $wrappedBody);

            $updateStmt = $db->prepare("UPDATE email_sends SET status = ?, sent_at = ?, error_message = ? WHERE id = ?");

            if ($success) {
                $updateStmt->execute(['sent', date('Y-m-d H:i:s'), null, $send['id']]);
                $sent++;
                $results[] = [
                    'email' => $send['email'],
                    'name' => $send['recipient_name'],
                    'status' => 'sent'
                ];
            } else {
                $error = $emailHelper->getLastError();
                $updateStmt->execute(['failed', null, $error, $send['id']]);
                $failed++;
                $results[] = [
                    'email' => $send['email'],
                    'name' => $send['recipient_name'],
                    'status' => 'failed',
                    'error' => $error
                ];
            }

            // Rate limiting (except for last email in chunk)
            if ($index < count($pendingSends) - 1 && $campaign['delay_seconds'] > 0) {
                sleep($campaign['delay_seconds']);
            }
        }

        // Get updated stats
        $stats = $trackingHelper->getCampaignStats($campaignId);

        echo json_encode([
            'success' => true,
            'done' => false,
            'sent_this_chunk' => $sent,
            'failed_this_chunk' => $failed,
            'results' => $results,
            'stats' => $stats
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Pause a campaign
 */
function pauseCampaign() {
    global $db;

    $campaignId = (int)($_POST['campaign_id'] ?? 0);

    if (!$campaignId) {
        echo json_encode(['success' => false, 'message' => 'Campaign ID required']);
        return;
    }

    try {
        $stmt = $db->prepare("UPDATE email_campaigns SET status = 'paused' WHERE id = ? AND status = 'sending'");
        $stmt->execute([$campaignId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Campaign paused']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Campaign not found or not in sending status']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Resume a paused campaign
 */
function resumeCampaign() {
    global $db;

    $campaignId = (int)($_POST['campaign_id'] ?? 0);

    if (!$campaignId) {
        echo json_encode(['success' => false, 'message' => 'Campaign ID required']);
        return;
    }

    try {
        $stmt = $db->prepare("UPDATE email_campaigns SET status = 'sending' WHERE id = ? AND status = 'paused'");
        $stmt->execute([$campaignId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Campaign resumed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Campaign not found or not paused']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Cancel a campaign
 */
function cancelCampaign() {
    global $db;

    $campaignId = (int)($_POST['campaign_id'] ?? 0);

    if (!$campaignId) {
        echo json_encode(['success' => false, 'message' => 'Campaign ID required']);
        return;
    }

    try {
        $stmt = $db->prepare("UPDATE email_campaigns SET status = 'cancelled' WHERE id = ? AND status IN ('draft', 'sending', 'paused')");
        $stmt->execute([$campaignId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Campaign cancelled']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Campaign not found or already completed']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get campaign status and stats
 */
function getCampaignStatus() {
    global $db;

    $campaignId = (int)($_POST['campaign_id'] ?? $_GET['campaign_id'] ?? 0);

    if (!$campaignId) {
        echo json_encode(['success' => false, 'message' => 'Campaign ID required']);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM email_campaigns WHERE id = ?");
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            echo json_encode(['success' => false, 'message' => 'Campaign not found']);
            return;
        }

        $trackingHelper = new EmailTrackingHelper($db);
        $stats = $trackingHelper->getCampaignStats($campaignId);

        echo json_encode([
            'success' => true,
            'campaign' => $campaign,
            'stats' => $stats
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get list of campaigns
 */
function getCampaigns() {
    global $db;

    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);

    try {
        $stmt = $db->prepare("
            SELECT c.*, u.name as created_by_name,
                   (SELECT COUNT(*) FROM email_sends WHERE campaign_id = c.id AND status = 'sent') as sent_count,
                   (SELECT COUNT(DISTINCT es.id) FROM email_sends es
                    INNER JOIN email_opens eo ON es.id = eo.send_id
                    WHERE es.campaign_id = c.id) as opened_count
            FROM email_campaigns c
            LEFT JOIN users u ON c.created_by = u.id
            ORDER BY c.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->query("SELECT COUNT(*) FROM email_campaigns");
        $total = (int)$stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'campaigns' => $campaigns,
            'total' => $total
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get single campaign with full details
 */
function getCampaign() {
    global $db;

    $campaignId = (int)($_GET['campaign_id'] ?? 0);

    if (!$campaignId) {
        echo json_encode(['success' => false, 'message' => 'Campaign ID required']);
        return;
    }

    try {
        $stmt = $db->prepare("
            SELECT c.*, u.name as created_by_name, t.name as template_name
            FROM email_campaigns c
            LEFT JOIN users u ON c.created_by = u.id
            LEFT JOIN email_templates t ON c.template_id = t.id
            WHERE c.id = ?
        ");
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$campaign) {
            echo json_encode(['success' => false, 'message' => 'Campaign not found']);
            return;
        }

        $trackingHelper = new EmailTrackingHelper($db);
        $stats = $trackingHelper->getCampaignStats($campaignId);
        $linkStats = $trackingHelper->getCampaignLinkStats($campaignId);

        echo json_encode([
            'success' => true,
            'campaign' => $campaign,
            'stats' => $stats,
            'link_stats' => $linkStats
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get campaign recipients with tracking status
 */
function getCampaignRecipients() {
    global $db;

    $campaignId = (int)($_GET['campaign_id'] ?? 0);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);
    $filter = $_GET['filter'] ?? 'all'; // all, opened, not_opened, clicked

    if (!$campaignId) {
        echo json_encode(['success' => false, 'message' => 'Campaign ID required']);
        return;
    }

    try {
        $trackingHelper = new EmailTrackingHelper($db);
        $recipients = $trackingHelper->getCampaignRecipients($campaignId, $limit, $offset);

        // Apply filter
        if ($filter !== 'all') {
            $recipients = array_filter($recipients, function($r) use ($filter) {
                switch ($filter) {
                    case 'opened':
                        return $r['open_count'] > 0;
                    case 'not_opened':
                        return $r['open_count'] == 0 && $r['status'] === 'sent';
                    case 'clicked':
                        return $r['click_count'] > 0;
                    default:
                        return true;
                }
            });
            $recipients = array_values($recipients);
        }

        // Get total count
        $stmt = $db->prepare("SELECT COUNT(*) FROM email_sends WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
        $total = (int)$stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'recipients' => $recipients,
            'total' => $total
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Build placeholder values from user data and settings
 */
function buildPlaceholders($user, $settings) {
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'irm.abroadworks.com');

    $fullName = $user['full_name'] ?? 'User';
    $nameParts = explode(' ', $fullName);

    $placeholders = [
        // User placeholders
        'name' => $fullName,
        'first_name' => $nameParts[0],
        'last_name' => count($nameParts) > 1 ? end($nameParts) : '',
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
