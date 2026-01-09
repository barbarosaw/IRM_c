<?php
/**
 * Email Queue Processor
 * Cron job to send pending emails from the queue
 *
 * Usage: php /var/www/html/modules/n8n_management/cron/process-emails.php
 * Cron: */5 * * * * php /var/www/html/modules/n8n_management/cron/process-emails.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('ALLOW_WEB_CRON')) {
    die('This script must be run from command line');
}

require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/includes/EmailHelper.php';

// Configuration
$maxEmails = 10; // Process max 10 emails per run
$trackingBaseUrl = 'https://irm.abroadworks.com/modules/n8n_management/api/tracking/pixel.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting email queue processor...\n";

// Get pending emails
$stmt = $db->prepare("
    SELECT * FROM n8n_chat_emails
    WHERE status = 'pending'
    ORDER BY created_at ASC
    LIMIT ?
");
$stmt->execute([$maxEmails]);
$emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($emails)) {
    echo "[" . date('Y-m-d H:i:s') . "] No pending emails found.\n";
    exit(0);
}

echo "[" . date('Y-m-d H:i:s') . "] Found " . count($emails) . " pending email(s).\n";

// Get SMTP settings
$smtp = [];
$stmtSettings = $db->query("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'smtp_%' OR `key` = 'email_enabled'");
while ($row = $stmtSettings->fetch(PDO::FETCH_ASSOC)) {
    $smtp[$row['key']] = $row['value'];
}

// Check if email is enabled
if (empty($smtp['email_enabled']) || $smtp['email_enabled'] !== '1') {
    echo "[" . date('Y-m-d H:i:s') . "] Email sending is disabled in settings.\n";
    exit(0);
}

// Process each email
$processed = 0;
$failed = 0;

foreach ($emails as $email) {
    echo "[" . date('Y-m-d H:i:s') . "] Processing email ID: {$email['id']}...\n";

    try {
        // Add tracking pixel to email body
        $trackingPixelUrl = $trackingBaseUrl . '?id=' . urlencode($email['tracking_id']);
        $bodyWithTracking = $email['body'];

        // If it's plain text, add tracking pixel as HTML at the end
        if (strpos($bodyWithTracking, '<html') === false) {
            $bodyWithTracking = nl2br(htmlspecialchars($bodyWithTracking));
            $bodyWithTracking = "<html><body><pre style='font-family: monospace; white-space: pre-wrap;'>{$bodyWithTracking}</pre>";
            $bodyWithTracking .= "<img src=\"{$trackingPixelUrl}\" width=\"1\" height=\"1\" style=\"display:none;\" alt=\"\" />";
            $bodyWithTracking .= "</body></html>";
        } else {
            // Add tracking pixel before </body>
            $bodyWithTracking = str_replace('</body>', "<img src=\"{$trackingPixelUrl}\" width=\"1\" height=\"1\" style=\"display:none;\" alt=\"\" /></body>", $bodyWithTracking);
        }

        // Parse CC and BCC
        $ccEmails = array_filter(array_map('trim', explode(',', $email['cc_emails'] ?? '')));
        $bccEmails = array_filter(array_map('trim', explode(',', $email['bcc_emails'] ?? '')));

        // Send email using EmailHelper
        $result = sendEmail(
            $email['recipient_email'],
            $email['subject'],
            $bodyWithTracking,
            true, // isHtml
            $ccEmails,
            $bccEmails
        );

        if ($result === true) {
            // Mark as sent
            $stmt = $db->prepare("UPDATE n8n_chat_emails SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $stmt->execute([$email['id']]);
            echo "[" . date('Y-m-d H:i:s') . "] Email ID {$email['id']} sent successfully.\n";
            $processed++;
        } else {
            // Mark as failed
            $errorMsg = is_string($result) ? $result : 'Unknown error';
            $stmt = $db->prepare("UPDATE n8n_chat_emails SET status = 'failed', error_message = ? WHERE id = ?");
            $stmt->execute([$errorMsg, $email['id']]);
            echo "[" . date('Y-m-d H:i:s') . "] Email ID {$email['id']} failed: {$errorMsg}\n";
            $failed++;
        }

        // Rate limiting - wait between emails
        $rateLimit = (int)($smtp['email_rate_limit'] ?? 5);
        if ($rateLimit > 0) {
            sleep($rateLimit);
        }

    } catch (Exception $e) {
        $stmt = $db->prepare("UPDATE n8n_chat_emails SET status = 'failed', error_message = ? WHERE id = ?");
        $stmt->execute([$e->getMessage(), $email['id']]);
        echo "[" . date('Y-m-d H:i:s') . "] Email ID {$email['id']} exception: {$e->getMessage()}\n";
        $failed++;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Completed. Processed: {$processed}, Failed: {$failed}\n";

/**
 * Send email using PHPMailer (from EmailHelper.php)
 */
function sendEmail($to, $subject, $body, $isHtml = true, $cc = [], $bcc = []) {
    global $smtp;

    // Check if EmailHelper function exists
    if (function_exists('send_email')) {
        return send_email($to, $subject, $body, $isHtml, [], $cc, $bcc);
    }

    // Fallback to PHPMailer directly
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $autoloadPath = dirname(__DIR__, 3) . '/vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        } else {
            return 'PHPMailer not found';
        }
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $smtp['smtp_host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['smtp_username'] ?? '';
        $mail->Password = $smtp['smtp_password'] ?? '';
        $mail->SMTPSecure = $smtp['smtp_encryption'] ?? 'tls';
        $mail->Port = (int)($smtp['smtp_port'] ?? 587);

        $mail->setFrom(
            $smtp['smtp_from_email'] ?? $smtp['smtp_username'] ?? '',
            $smtp['smtp_from_name'] ?? 'AbroadWorks Chat'
        );

        if (!empty($smtp['smtp_reply_to'])) {
            $mail->addReplyTo($smtp['smtp_reply_to']);
        }

        $mail->addAddress($to);

        foreach ($cc as $ccEmail) {
            if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($ccEmail);
            }
        }

        foreach ($bcc as $bccEmail) {
            if (filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                $mail->addBCC($bccEmail);
            }
        }

        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $body;

        if ($isHtml) {
            $mail->AltBody = strip_tags($body);
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}
