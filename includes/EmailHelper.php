<?php
/**
 * AbroadWorks Management System - Email Helper Class
 *
 * Handles all email sending functionality using PHPMailer
 *
 * @author ikinciadam@gmail.com
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailHelper
{
    private $db;
    private $settings = [];
    private $lastError = '';

    /**
     * Constructor
     *
     * @param PDO $db Database connection
     */
    public function __construct($db = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            global $db;
            $this->db = $db;
        }

        $this->loadSettings();
    }

    /**
     * Load email settings from database
     */
    private function loadSettings()
    {
        try {
            $stmt = $this->db->query("SELECT `key`, `value` FROM settings WHERE `group` = 'email'");
            while ($row = $stmt->fetch()) {
                $this->settings[$row['key']] = $row['value'];
            }
        } catch (Exception $e) {
            error_log("EmailHelper: Error loading settings - " . $e->getMessage());
        }
    }

    /**
     * Check if email sending is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return !empty($this->settings['email_enabled']) && $this->settings['email_enabled'] == '1';
    }

    /**
     * Get the rate limit in seconds
     *
     * @return int
     */
    public function getRateLimit()
    {
        return (int)($this->settings['email_rate_limit'] ?? 5);
    }

    /**
     * Get the last error message
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Create a configured PHPMailer instance
     *
     * @return PHPMailer|null
     */
    private function createMailer()
    {
        if (!$this->isEnabled()) {
            $this->lastError = 'Email sending is disabled';
            return null;
        }

        if (empty($this->settings['smtp_host']) || empty($this->settings['smtp_username']) || empty($this->settings['smtp_password'])) {
            $this->lastError = 'SMTP settings are incomplete';
            return null;
        }

        try {
            $mail = new PHPMailer(true);

            // Server settings
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            $mail->isSMTP();
            $mail->Host = $this->settings['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->settings['smtp_username'];
            $mail->Password = $this->settings['smtp_password'];

            // Set encryption
            $encryption = $this->settings['smtp_encryption'] ?? 'tls';
            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->Port = (int)($this->settings['smtp_port'] ?? 587);

            // Set default from
            $fromEmail = $this->settings['smtp_from_email'] ?: $this->settings['smtp_username'];
            $fromName = $this->settings['smtp_from_name'] ?: 'AbroadWorks IRM';
            $mail->setFrom($fromEmail, $fromName);

            // Set reply-to if configured
            if (!empty($this->settings['smtp_reply_to'])) {
                $mail->addReplyTo($this->settings['smtp_reply_to'], $fromName);
            }

            // Add CC if configured
            if (!empty($this->settings['smtp_cc'])) {
                $mail->addCC($this->settings['smtp_cc']);
            }

            $mail->CharSet = 'UTF-8';

            return $mail;

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    /**
     * Send a single email
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body HTML body
     * @param string $altBody Plain text body (optional)
     * @param array $attachments Array of file paths (optional)
     * @return bool
     */
    public function send($to, $subject, $body, $altBody = '', $attachments = [])
    {
        $mail = $this->createMailer();
        if (!$mail) {
            return false;
        }

        try {
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;

            // Wrap body in 80% width centered container for better email rendering
            $wrappedBody = $this->wrapBodyInContainer($body);
            $mail->Body = $wrappedBody;
            $mail->AltBody = $altBody ?: strip_tags($body);

            // Add attachments
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }

            $mail->send();
            return true;

        } catch (Exception $e) {
            $this->lastError = $mail->ErrorInfo;
            return false;
        }
    }

    /**
     * Send email using a template
     *
     * @param string $to Recipient email
     * @param string $templateCode Template code from database
     * @param array $variables Variables to replace in template
     * @return bool
     */
    public function sendTemplate($to, $templateCode, $variables = [])
    {
        // Get template from database
        try {
            $stmt = $this->db->prepare("SELECT * FROM email_templates WHERE code = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$templateCode]);
            $template = $stmt->fetch();

            if (!$template) {
                $this->lastError = "Template not found: {$templateCode}";
                return false;
            }

            // Replace variables in subject and body
            $subject = $template['subject'];
            $body = $template['body'];

            foreach ($variables as $key => $value) {
                $subject = str_replace('{{' . $key . '}}', $value, $subject);
                $body = str_replace('{{' . $key . '}}', $value, $body);
            }

            // Wrap body with header/footer if assigned to template
            $headerId = $template['header_id'] ?? null;
            $footerId = $template['footer_id'] ?? null;

            if ($headerId || $footerId) {
                $body = $this->wrapInTemplate($body, '', $headerId, $footerId);
            }

            return $this->send($to, $subject, $body);

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Send bulk emails with rate limiting
     *
     * @param array $recipients Array of ['email' => '', 'name' => '', 'variables' => []]
     * @param string $subject Email subject
     * @param string $body HTML body (with {{variable}} placeholders)
     * @param callable|null $progressCallback Optional callback for progress updates
     * @return array ['sent' => int, 'failed' => int, 'errors' => []]
     */
    public function sendBulk($recipients, $subject, $body, $progressCallback = null)
    {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'errors' => []
        ];

        $rateLimit = $this->getRateLimit();
        $total = count($recipients);
        $current = 0;

        foreach ($recipients as $recipient) {
            $current++;

            $email = $recipient['email'] ?? $recipient;
            $name = $recipient['name'] ?? '';
            $variables = $recipient['variables'] ?? [];

            // Replace variables in subject and body
            $personalizedSubject = $subject;
            $personalizedBody = $body;

            // Default variables
            $variables['name'] = $name;
            $variables['email'] = $email;
            $variables['date'] = date('Y-m-d');
            $variables['time'] = date('H:i:s');

            foreach ($variables as $key => $value) {
                $personalizedSubject = str_replace('{{' . $key . '}}', $value, $personalizedSubject);
                $personalizedBody = str_replace('{{' . $key . '}}', $value, $personalizedBody);
            }

            if ($this->send($email, $personalizedSubject, $personalizedBody)) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'email' => $email,
                    'error' => $this->lastError
                ];
            }

            // Progress callback
            if ($progressCallback && is_callable($progressCallback)) {
                $progressCallback($current, $total, $results);
            }

            // Rate limiting (except for last email)
            if ($current < $total && $rateLimit > 0) {
                sleep($rateLimit);
            }
        }

        return $results;
    }

    /**
     * Wrap content in an email template with header and footer
     *
     * @param string $content The main content
     * @param string $title Optional title (deprecated, kept for backward compatibility)
     * @param int|null $headerId Header ID (null = no header)
     * @param int|null $footerId Footer ID (null = no footer)
     * @return string HTML email body
     */
    public function wrapInTemplate($content, $title = '', $headerId = null, $footerId = null)
    {
        // Get header content if ID is provided
        $header = $this->getTemplatePart('header', $headerId);

        // Get footer content if ID is provided
        $footer = $this->getTemplatePart('footer', $footerId);

        // If title is provided but not in header, prepend it to content
        if (!empty($title)) {
            $content = "<h2>{$title}</h2>" . $content;
        }

        // Replace placeholders in header and footer
        $siteName = $this->getSetting('site_name', 'AbroadWorks IRM');
        $siteUrl = $this->getSetting('site_url', '');

        $replacements = [
            '{{site_name}}' => $siteName,
            '{{site_url}}' => $siteUrl,
            '{{year}}' => date('Y'),
            '{{date}}' => date('d.m.Y'),
            '{{time}}' => date('H:i'),
        ];

        foreach ($replacements as $placeholder => $value) {
            $header = str_replace($placeholder, $value, $header);
            $footer = str_replace($placeholder, $value, $footer);
        }

        // If no header/footer selected, return content as-is (backward compatibility)
        if (empty($header) && empty($footer)) {
            return $content;
        }

        return $header . $content . $footer;
    }

    /**
     * Get a template part (header or footer) from database
     *
     * @param string $type 'header' or 'footer'
     * @param int|null $id Part ID (null = no part)
     * @return string HTML content
     */
    private function getTemplatePart($type, $id = null)
    {
        if (empty($id)) {
            return ''; // No header/footer requested
        }

        try {
            $stmt = $this->db->prepare("SELECT content FROM email_template_parts WHERE id = ? AND type = ? AND is_active = 1");
            $stmt->execute([$id, $type]);
            $content = $stmt->fetchColumn();

            return $content ?: '';
        } catch (Exception $e) {
            error_log("EmailHelper: Error getting template part - " . $e->getMessage());
            return '';
        }
    }

    /**
     * Get a setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed
     */
    private function getSetting($key, $default = '')
    {
        try {
            $stmt = $this->db->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value !== false ? $value : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    /**
     * Wrap email body in an 80% width centered container
     *
     * @param string $body The email HTML body
     * @return string Wrapped HTML body
     */
    private function wrapBodyInContainer($body)
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" width="80%" cellspacing="0" cellpadding="0" border="0" style="max-width: 800px; background-color: #ffffff;">
                    <tr>
                        <td style="padding: 0;">
                            ' . $body . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
}
