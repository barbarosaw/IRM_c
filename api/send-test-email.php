<?php
/**
 * AbroadWorks Management System - Send Test Email API
 *
 * @author ikinciadam@gmail.com
 */

// Error handling for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    require_once '../includes/init.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Init error: ' . $e->getMessage()]);
    exit;
}

try {
    require_once '../vendor/autoload.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Autoload error: ' . $e->getMessage()]);
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login first']);
    exit;
}

// Get request data
$toEmail = $_POST['to_email'] ?? '';

if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// Get email settings from database
$emailSettings = [];
$stmt = $db->query("SELECT `key`, `value` FROM settings WHERE `group` = 'email'");
while ($row = $stmt->fetch()) {
    $emailSettings[$row['key']] = $row['value'];
}

// Check if email is enabled
if (empty($emailSettings['email_enabled']) || $emailSettings['email_enabled'] != '1') {
    echo json_encode(['success' => false, 'message' => 'Email sending is disabled. Enable it in settings first.']);
    exit;
}

// Check required settings
if (empty($emailSettings['smtp_host']) || empty($emailSettings['smtp_username']) || empty($emailSettings['smtp_password'])) {
    echo json_encode(['success' => false, 'message' => 'SMTP settings are incomplete. Please configure SMTP host, username and password.']);
    exit;
}

try {
    $mail = new PHPMailer(true);

    // Server settings
    $mail->SMTPDebug = SMTP::DEBUG_OFF; // Disable debug output
    $mail->isSMTP();
    $mail->Host = $emailSettings['smtp_host'];
    $mail->SMTPAuth = true;
    $mail->Username = $emailSettings['smtp_username'];
    $mail->Password = $emailSettings['smtp_password'];

    // Set encryption
    $encryption = $emailSettings['smtp_encryption'] ?? 'tls';
    if ($encryption === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } elseif ($encryption === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    }

    $mail->Port = (int)($emailSettings['smtp_port'] ?? 587);

    // Recipients
    $fromEmail = $emailSettings['smtp_from_email'] ?: $emailSettings['smtp_username'];
    $fromName = $emailSettings['smtp_from_name'] ?: 'AbroadWorks IRM';

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($toEmail);

    if (!empty($emailSettings['smtp_reply_to'])) {
        $mail->addReplyTo($emailSettings['smtp_reply_to'], $fromName);
    }

    // Content
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'Test Email from AbroadWorks IRM';

    $currentTime = date('Y-m-d H:i:s');
    $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';

    $mail->Body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
            .footer { background: #333; color: #999; padding: 15px; text-align: center; font-size: 12px; border-radius: 0 0 5px 5px; }
            .success { color: #28a745; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>AbroadWorks IRM</h1>
            </div>
            <div class='content'>
                <h2 class='success'>Email Configuration Test Successful!</h2>
                <p>Congratulations! Your email settings are configured correctly.</p>
                <p><strong>Details:</strong></p>
                <ul>
                    <li><strong>SMTP Host:</strong> {$emailSettings['smtp_host']}</li>
                    <li><strong>SMTP Port:</strong> {$emailSettings['smtp_port']}</li>
                    <li><strong>Encryption:</strong> {$encryption}</li>
                    <li><strong>From:</strong> {$fromName} &lt;{$fromEmail}&gt;</li>
                    <li><strong>Sent At:</strong> {$currentTime}</li>
                    <li><strong>Server:</strong> {$serverName}</li>
                </ul>
                <p>You can now use the email system to send notifications and bulk emails.</p>
            </div>
            <div class='footer'>
                This is an automated test email from AbroadWorks IRM System.
            </div>
        </div>
    </body>
    </html>
    ";

    $mail->AltBody = "Email Configuration Test Successful!\n\n" .
        "Your email settings are configured correctly.\n\n" .
        "SMTP Host: {$emailSettings['smtp_host']}\n" .
        "SMTP Port: {$emailSettings['smtp_port']}\n" .
        "Encryption: {$encryption}\n" .
        "From: {$fromName} <{$fromEmail}>\n" .
        "Sent At: {$currentTime}\n\n" .
        "This is an automated test email from AbroadWorks IRM System.";

    $mail->send();

    // Log the activity
    if (function_exists('log_activity')) {
        log_activity($_SESSION['user_id'], 'email_test', 'email', "Test email sent to: {$toEmail}");
    }

    echo json_encode([
        'success' => true,
        'message' => "Test email sent successfully to {$toEmail}"
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send email: ' . $e->getMessage(),
        'debug' => isset($mail) ? $mail->ErrorInfo : $e->getTraceAsString()
    ]);
}
